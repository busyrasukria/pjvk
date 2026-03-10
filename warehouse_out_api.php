<?php
header('Content-Type: application/json');
require_once 'db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// === AUTOMATIC DAILY RESET LOGIC ===
// ==========================================
function check_and_perform_daily_reset($pdo) {
    try {
        // File to track the last reset date (saved in same folder)
        $log_file = __DIR__ . '/daily_reset_tracker.txt';
        $today = date('Y-m-d');
        
        $last_reset_date = '';
        if (file_exists($log_file)) {
            $last_reset_date = trim(file_get_contents($log_file));
        }

        // If stored date is NOT today, reset the DB and update the file
        if ($last_reset_date !== $today) {
            
            // 1. Reset all ACTUAL columns in master_trip to 0
            $sql = "UPDATE master_trip SET 
                    ACTUAL_TRIP_1 = 0, ACTUAL_TRIP_2 = 0, ACTUAL_TRIP_3 = 0, 
                    ACTUAL_TRIP_4 = 0, ACTUAL_TRIP_5 = 0, ACTUAL_TRIP_6 = 0";
            $pdo->exec($sql);

            // 2. Update the tracker file
            file_put_contents($log_file, $today);
        }
    } catch (Exception $e) {
        // Silently fail or log error to avoid stopping the scanner if file permission issues exist
        error_log("Daily Reset Error: " . $e->getMessage());
    }
}

// Run the check immediately on every request
check_and_perform_daily_reset($pdo);

$action = $_GET['action'] ?? '';

// === PNE PARTS LIST (REQUIRE PNE FLOW) ===
const PNE_REQUIRED_PARTS = [
    'AA021298', 'AA051297', 'AA031299', 'AC020059', 
    'AC050058', 'AC030060', 'AD020140', 'AD050142' 
];

const ALLOWED_TRIPS = ['TRIP_1', 'TRIP_2', 'TRIP_3', 'TRIP_4', 'TRIP_5', 'TRIP_6'];

function send_json($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// --- HELPER: FIFO CHECK ---
function check_fifo_violation($pdo, $current_ticket_id, $erp_code, $current_prod_date, $is_pne_part) {
    $current_date_only = date('Y-m-d', strtotime($current_prod_date));

    // UPDATED: FIFO check now ensures we only look for the same ERP code
    $sql = "SELECT t.unique_no, wi.prod_date 
            FROM transfer_tickets t
            JOIN warehouse_in wi ON t.ticket_id = wi.transfer_ticket_id
            WHERE t.erp_code_FG = ? 
            AND DATE(wi.prod_date) < ? 
            AND t.ticket_id != ? 
            AND NOT EXISTS (
                SELECT 1 FROM warehouse_out wo 
                WHERE wo.unique_no = t.unique_no AND wo.erp_code = t.erp_code_FG
            )";

    if ($is_pne_part) {
        $sql .= " AND EXISTS (SELECT 1 FROM pne_warehouse_in pwi WHERE pwi.transfer_ticket_id = t.ticket_id)";
    }

    $sql .= " ORDER BY wi.prod_date ASC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$erp_code, $current_date_only, $current_ticket_id]);
    $older_ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($older_ticket) {
        $older_date_fmt = date('d/m/Y', strtotime($older_ticket['prod_date']));
        $current_date_fmt = date('d/m/Y', strtotime($current_prod_date));
        throw new Exception("FIFO VIOLATION:\nYou scanned a ticket from $current_date_fmt.\nBut Ticket #{$older_ticket['unique_no']} from $older_date_fmt is still in stock.\n\nPlease scan the older ticket first.");
    }
}

// --- HELPER: VALIDATE PROCESS FLOW ---
function validate_ticket_process($pdo, $unique_no, $erp_code) {
    // A. Find Ticket
    $stmt = $pdo->prepare("SELECT ticket_id, released_by, quantity FROM transfer_tickets WHERE unique_no = ? AND erp_code_FG = ?");
    $stmt->execute([$unique_no, $erp_code]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception("Ticket #$unique_no (ERP: $erp_code) not found in system.");
    }
    $db_id = $ticket['ticket_id'];

    // B. Check Warehouse In
    $stmt_in = $pdo->prepare("SELECT prod_date FROM warehouse_in WHERE transfer_ticket_id = ?");
    $stmt_in->execute([$db_id]);
    $wh_in_row = $stmt_in->fetch(PDO::FETCH_ASSOC);
    
    if (!$wh_in_row) {
        throw new Exception("Ticket #$unique_no has NOT been scanned into Warehouse In.");
    }
    $current_prod_date = $wh_in_row['prod_date'];

    // C. Check PNE Flow
    $is_pne_part = in_array($erp_code, PNE_REQUIRED_PARTS);
    if ($is_pne_part) {
        $stmt_pne_out = $pdo->prepare("SELECT 1 FROM warehouse_out_pne WHERE transfer_ticket_id = ?");
        $stmt_pne_out->execute([$db_id]);
        if (!$stmt_pne_out->fetch()) throw new Exception("Ticket #$unique_no requires PNE processing. Missing 'Warehouse Out to PNE'.");

        $stmt_pne_in = $pdo->prepare("SELECT 1 FROM pne_warehouse_in WHERE transfer_ticket_id = ?");
        $stmt_pne_in->execute([$db_id]);
        if (!$stmt_pne_in->fetch()) throw new Exception("Ticket #$unique_no is currently at PNE. Missing 'PNE In to Warehouse'.");
    }

    // D. Check Duplicate (UPDATED: Checks ID AND ERP Code)
    $stmt_dup = $pdo->prepare("SELECT 1 FROM warehouse_out WHERE unique_no = ? AND erp_code = ?");
    $stmt_dup->execute([$unique_no, $erp_code]); 
    if ($stmt_dup->fetch()) {
        throw new Exception("Ticket #$unique_no (ERP: $erp_code) has ALREADY been scanned out.");
    }
    
    // E. FIFO CHECK
    $bypass = isset($_POST['bypass_fifo']) && $_POST['bypass_fifo'] == '1';
    if (!$bypass) {
        check_fifo_violation($pdo, $db_id, $erp_code, $current_prod_date, $is_pne_part);
    }

    return $ticket;
}

switch ($action) {
    case 'validate_ticket':
        try {
            $unique_no = $_POST['unique_no'] ?? '';
            $erp_code = $_POST['erp_code'] ?? '';
            $job = json_decode($_POST['job'] ?? '{}', true);

            if (!$unique_no || !$erp_code) send_json(['success' => false, 'message' => 'Missing QR data.']);
            
            // Standard Validation
            $ticket = validate_ticket_process($pdo, $unique_no, $erp_code);
            $ticket_qty = (int)($ticket['quantity'] ?? 1);
            
            // === LOGIC UPDATE FOR CX30 SCANNING RULES ===
            if (isset($job['model']) && isset($job['trip']) && isset($job['type']) && isset($job['variant'])) {
                
                $sql = "SELECT * FROM master_trip WHERE ERP_CODE = ? AND MODEL = ? AND `TYPE` = ? AND VARIANT = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$erp_code, $job['model'], $job['type'], $job['variant']]);
                $master = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($master) {
                    $trip_col = $job['trip'];
                    $actual_col = 'ACTUAL_' . $trip_col;
                    
                    $plan_total = (int)$master[$trip_col];
                    $actual_total = (int)$master[$actual_col];
                    $remaining = $plan_total - $actual_total;
                    
                    // General Stop
                    if ($remaining <= 0) {
                        throw new Exception("TRIP COMPLETE!\nPlan Limit Reached ($actual_total / $plan_total).");
                    }

                    if ($job['model'] === 'CX30') {
                        
                        // RULE 1: CX30 / CHASSIS / TRIP 1
                        if ($job['type'] === 'CHASSIS' && $job['trip'] === 'TRIP_1') {
                            if ($remaining > 1) {
                                if ($ticket_qty != 4) throw new Exception("WRONG QTY!\nStandard Plan requires Ticket Qty: 4.");
                            } elseif ($remaining == 1) {
                                if ($ticket_qty != 2) throw new Exception("WRONG QTY!\nFinal Plan requires SPLIT tickets (Qty 2).");
                            }
                        }

                        // RULE 2: CX30 / BODY / TRIP 1
                        elseif ($job['type'] === 'BODY' && $job['trip'] === 'TRIP_1') {
                            // Always requires Qty 3
                            if ($ticket_qty != 3) throw new Exception("WRONG QTY!\nThis plan requires Ticket Qty: 3.");
                        }

                        // RULE 3: CX30 / BODY / TRIP 2
                        elseif ($job['type'] === 'BODY' && $job['trip'] === 'TRIP_2') {
                            if ($remaining > 1) {
                                if ($ticket_qty != 3) throw new Exception("WRONG QTY!\nStandard Plan requires Ticket Qty: 3.");
                            } elseif ($remaining == 1) {
                                if ($ticket_qty > 2) throw new Exception("WRONG QTY!\nFinal Plan max requirement is 2.");
                            }
                        }
                    }
                }
            }
            // ------------------------------------------------

            send_json(['success' => true, 'message' => 'Ticket Validated. Proceed.']);
        } catch (Exception $e) {
            send_json(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'check_pallet_req':
        try {
            $pallet_qr = $_POST['pallet_qr'] ?? '';
            $job = json_decode($_POST['job'] ?? '{}', true);
            
            $parts = explode('|', $pallet_qr);
            if(count($parts) < 2) send_json(['success' => false, 'message' => 'Invalid Pallet QR']);
            
            $part_no = $parts[0];
            $erp_code = $parts[1];
            
            $sql = "SELECT * FROM master_trip WHERE PART_NO = ? AND ERP_CODE = ? AND MODEL = ? AND `TYPE` = ? AND VARIANT = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$part_no, $erp_code, $job['model'], $job['type'], $job['variant']]);
            $master_part = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$master_part) send_json(['success' => false, 'message' => 'Part not found in Trip Plan']);
            
            $trip_col = $job['trip'];
            $actual_col = 'ACTUAL_' . $trip_col;
            $remaining = (int)$master_part[$trip_col] - (int)$master_part[$actual_col];

            $req_qty = 1; // Default

            // --- TARGET QUANTITY LOGIC ---
            if ($job['model'] === 'CX30') {
                
                // RULE 1: CX30 / CHASSIS / TRIP 1
                if ($job['type'] === 'CHASSIS' && $job['trip'] === 'TRIP_1') {
                    $req_qty = 4; // Always targets 4 total (even if scanned as 2+2)
                }
                // RULE 2: CX30 / BODY / TRIP 1
                elseif ($job['type'] === 'BODY' && $job['trip'] === 'TRIP_1') {
                    $req_qty = 3; // Always targets 3
                }
                // RULE 3: CX30 / BODY / TRIP 2
                elseif ($job['type'] === 'BODY' && $job['trip'] === 'TRIP_2') {
                    if ($remaining > 1) {
                        $req_qty = 3; 
                    } else {
                        $req_qty = 2; // Final plan targets 2
                    }
                }
            }
            
            send_json(['success' => true, 'required_qty' => $req_qty]);
            
        } catch (Exception $e) {
            send_json(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'submit_scan':
        try {
            $job = json_decode($_POST['job'] ?? '{}', true);
            $scan = json_decode($_POST['scan'] ?? '{}', true);
            $mazda_parts = explode('|', $scan['scan_mazda'] ?? '');
            $pallet_parts = explode('|', $scan['scan_pallet'] ?? '');
            
            $tt_list = $scan['scan_tt']; 
            if (!is_array($tt_list)) $tt_list = [$scan['scan_tt']]; 
            
            $pdo->beginTransaction();
            
            $master_sql = "SELECT * FROM master_trip WHERE PART_NO = ? AND ERP_CODE = ? AND MODEL = ? AND `TYPE` = ? AND VARIANT = ?";
            $stmt_m = $pdo->prepare($master_sql);
            $stmt_m->execute([$pallet_parts[0], $pallet_parts[1], $job['model'], $job['type'], $job['variant']]);
            $master_part = $stmt_m->fetch(PDO::FETCH_ASSOC);

            if (!$master_part) throw new Exception("Part not found in Trip Plan.");

            $trip_col = $job['trip'];
            $actual_col = 'ACTUAL_' . $trip_col;
            
            $current_actual = (int)$master_part[$actual_col];
            $plan_limit = (int)$master_part[$trip_col];
            $remaining = $plan_limit - $current_actual;

            if ($remaining <= 0) {
                throw new Exception("TRIP COMPLETE! Plan limit reached.");
            }

            $total_qty_this_scan = 0;
            foreach($tt_list as $tt_qr) {
                $tt_parts = explode('|', $tt_qr);
                if (count($tt_parts) < 3) throw new Exception("Invalid Ticket QR format.");
                $total_qty_this_scan += (int)($tt_parts[4] ?? 1);
            }

            // --- FINAL QUANTITY CHECK ---
            if ($job['model'] === 'CX30') {
                
                // RULE 1: CX30 / CHASSIS / TRIP 1
                if ($job['type'] === 'CHASSIS' && $job['trip'] === 'TRIP_1') {
                    // Must sum up to 4, regardless of remaining (Standard or Final)
                    if ($total_qty_this_scan != 4) {
                        throw new Exception("Total quantity must be 4. You scanned $total_qty_this_scan.");
                    }
                }

                // RULE 2: CX30 / BODY / TRIP 1
                if ($job['type'] === 'BODY' && $job['trip'] === 'TRIP_1') {
                    if ($total_qty_this_scan != 3) {
                        throw new Exception("Total quantity must be 3. You scanned $total_qty_this_scan.");
                    }
                }

                // RULE 3: CX30 / BODY / TRIP 2
                if ($job['type'] === 'BODY' && $job['trip'] === 'TRIP_2') {
                    if ($remaining == 1) { 
                        if ($total_qty_this_scan != 2) {
                            throw new Exception("Final Plan total must be 2. You scanned $total_qty_this_scan.");
                        }
                    } else {
                        if ($total_qty_this_scan != 3) {
                            throw new Exception("Standard Plan total must be 3. You scanned $total_qty_this_scan.");
                        }
                    }
                }
            }

            // Insert Records
            foreach($tt_list as $tt_qr) {
                $tt_parts = explode('|', $tt_qr);
                $tt_id = $tt_parts[1]; 
                $tt_erp = $tt_parts[2];
                
                // Re-validate to be safe
                $ticket_data = validate_ticket_process($pdo, $tt_id, $tt_erp);
                $db_ticket_id = $ticket_data['ticket_id'];
                
                $sql_ins = "INSERT INTO warehouse_out (master_trip_id, part_no, erp_code, trip, lot_no, msc_code, mazda_id, pallet_qr, ticket_qr, mazda_qr, unique_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql_ins)->execute([
                    $master_part['id'], $pallet_parts[0], $pallet_parts[1], $job['trip'],
                    $mazda_parts[1], $mazda_parts[0], ($mazda_parts[3] ?? 'N/A'),
                    $scan['scan_pallet'], $tt_qr, $scan['scan_mazda'],
                    $tt_id 
                ]);
                
                $scanned_by = $tt_parts[3] ?? 'Scanner';
                $pdo->prepare("INSERT INTO ticket_status_log (ticket_id, status_code, status_message, scanned_by) VALUES (?, 'CUSTOMER_OUT', 'Scanned Out to Customer', ?)")->execute([$db_ticket_id, $scanned_by]);
            }

            // Update Master Trip Count (+1 Trip)
            $pdo->prepare("UPDATE master_trip SET $actual_col = COALESCE($actual_col, 0) + 1 WHERE id = ?")->execute([$master_part['id']]);
            
            $pdo->commit();

            $stmt_get = $pdo->prepare("SELECT * FROM master_trip WHERE id = ?");
            $stmt_get->execute([$master_part['id']]);
            send_json(['success' => true, 'message' => "Scan Successful! ($total_qty_this_scan units)", 'updatedRow' => $stmt_get->fetch(PDO::FETCH_ASSOC)]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            send_json(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_msc':
        try {
            $model = $_POST['model'] ?? ''; $variant = $_POST['variant'] ?? '';
            $stmt = $pdo->prepare("SELECT DISTINCT msc_code FROM variant_listing WHERE model = ? AND variant = ? ORDER BY msc_code");
            $stmt->execute([$model, $variant]);
            send_json(['success' => true, 'parts' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        } catch (Exception $e) { send_json(['success' => false, 'parts' => []]); }
        break;

    case 'get_trip_plan':
        try {
            $type = $_POST['type'] ?? null; $model = $_POST['model'] ?? null; $variant = $_POST['variant'] ?? null; $trip = $_POST['trip'] ?? null;
            $params = []; $sql = "SELECT * FROM master_trip"; $where = [];
            if ($type) { $where[] = "TYPE = ?"; $params[] = $type; }
            if ($model) { $where[] = "MODEL = ?"; $params[] = $model; }
            if ($variant) { $where[] = "VARIANT = ?"; $params[] = $variant; }
            if ($trip && in_array($trip, ALLOWED_TRIPS)) { $where[] = "$trip > 0"; } else { send_json(['success' => true, 'parts' => []]); }
            if (count($where) > 0) { $sql .= " WHERE " . implode(" AND ", $where); }
            $sql .= " ORDER BY MODEL, TYPE, PART_NO";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            send_json(['success' => true, 'parts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            send_json(['success' => false, 'message' => $e->getMessage(), 'parts' => []]);
        }
        break;

    case 'get_scan_log':
         // Increased Limit to 500 to support larger pages
         $sql = "SELECT l.*, mt.PART_DESCRIPTION as part_name, mt.MODEL as model, mt.TYPE as type, m.line as prod_area 
                 FROM warehouse_out l LEFT JOIN master_trip mt ON l.master_trip_id = mt.id 
                 LEFT JOIN master m ON l.part_no = m.part_no_FG AND l.erp_code = m.erp_code_FG 
                 ORDER BY l.scan_timestamp DESC";
         $logs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
         foreach($logs as &$log) {
             $parts = explode('|', $log['ticket_qr']);
             $log['scan_timestamp_formatted'] = date('d/m/Y H:i:s', strtotime($log['scan_timestamp']));
             $log['tt_id'] = $parts[1] ?? '-';
             $log['prod_date_formatted'] = $parts[0] ?? '-';
             $log['released_by'] = $parts[3] ?? '-';
             $log['quantity'] = $parts[4] ?? 1;
             $log['part_no_fg'] = $log['part_no']; 
             $log['erp_code_fg'] = $log['erp_code'];
         }
         send_json(['success'=>true, 'logs'=>$logs]);
         break;

    case 'reset_entire_trip':
        try {
            $type = $_POST['type'] ?? ''; $model = $_POST['model'] ?? ''; 
            $var = $_POST['variant'] ?? ''; $trip = $_POST['trip'] ?? '';
            $col = "ACTUAL_".$trip;
            $pdo->prepare("UPDATE master_trip SET $col = 0 WHERE TYPE=? AND MODEL=? AND VARIANT=?")->execute([$type, $model, $var]);
            send_json(['success'=>true]);
        } catch (Exception $e) { send_json(['success'=>false, 'message'=>$e->getMessage()]); }
        break;
        
    default:
        send_json(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>