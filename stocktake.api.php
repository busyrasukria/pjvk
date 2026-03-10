<?php
header('Content-Type: application/json');
require_once 'db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// Helper to return JSON and stop
function send_json($data) { 
    echo json_encode($data); 
    exit; 
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {

    // =========================================================
    // 1. EDIT LOGIC (NO PASSWORD REQUIRED)
    // =========================================================
    if ($action === 'edit_scan') {
        $log_id = (int)($input['log_id'] ?? 0);
        // $password = $input['password'] ?? ''; // REMOVED
        
        $new_tag_id  = trim($input['tag_id'] ?? ''); 
        $new_part_no = trim($input['part_no'] ?? '');
        $new_erp     = trim($input['erp_code'] ?? '');
        $new_name    = trim($input['part_name'] ?? '');
        $new_seq     = trim($input['seq_no'] ?? '');
        $new_qty     = trim($input['qty'] ?? '');
        $new_loc     = trim($input['scanned_location'] ?? '');

        // --- PASSWORD CHECK REMOVED ---
        
        // Validation
        if ($log_id <= 0) {
            send_json(['success' => false, 'message' => "Error: System ID (Log ID) is missing. Please refresh the page."]);
        }
        if (empty($new_tag_id)) {
            send_json(['success' => false, 'message' => "Error: Tag ID cannot be empty."]);
        }
        if (empty($new_part_no)) {
            send_json(['success' => false, 'message' => "Error: Part No cannot be empty."]);
        }
        if (empty($new_loc)) {
            send_json(['success' => false, 'message' => "Error: Location cannot be empty."]);
        }

        // Update Database
        $stmt = $pdo->prepare("
            UPDATE stocktake_log 
            SET tag_id = ?, part_no = ?, erp_code = ?, part_name = ?, seq_no = ?, qty = ?, scanned_location = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$new_tag_id, $new_part_no, $new_erp, $new_name, $new_seq, $new_qty, $new_loc, $log_id]);

        send_json(['success' => true]);
    }

    // =========================================================
    // 2. DELETE LOGIC (PASSWORD STILL REQUIRED)
    // =========================================================
    if ($action === 'delete_scan') {
        $log_id = (int)($input['log_id'] ?? 0);
        $password = $input['password'] ?? '';

        if ($password !== 'Admin404') { 
            send_json(['success' => false, 'message' => "Invalid Admin Password."]);
        }

        $stmt = $pdo->prepare("DELETE FROM stocktake_log WHERE id = ?");
        $stmt->execute([$log_id]);
        send_json(['success' => true]);
    }

   // =========================================================
    // 3. FETCH DETAILS LOGIC
    // =========================================================
    if ($action === 'fetch_details') {
        $ticket_id = trim($input['ticket_id'] ?? '');

        if (empty($ticket_id)) {
            send_json(['success' => false, 'message' => "Ticket ID is required."]);
        }

        $stmt = $pdo->prepare("
            SELECT 
                r.ID_CODE, 
                r.PART_NO, 
                r.RECEIVING_DATE,
                r.RACK_IN AS system_qty,  
                m.stock_desc AS part_name,
                m.erp_code,
                m.seq_number AS seq_no,
                m.std_packing
            FROM racking_in r
            LEFT JOIN master_incoming m ON r.PART_NO = m.part_no
            WHERE r.ID_CODE = ?
            LIMIT 1
        ");
        
        $stmt->execute([$ticket_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $qty = $row['system_qty'] ?? $row['std_packing'] ?? 0;

            send_json([
                'success' => true,
                'data' => [
                    'part_name' => $row['part_name'] ?? 'N/A',
                    'part_no_fg' => $row['PART_NO'],
                    'erp_code' => $row['erp_code'] ?? '-',
                    'seq_no' => $row['seq_no'] ?? '-',
                    'rack_in' => $qty, 
                    'receiving_date_fmt' => date('d-m-Y', strtotime($row['RECEIVING_DATE'] ?? 'now'))
                ]
            ]);
        } else {
            send_json(['success' => false, 'message' => "Tag ID not found in System Racking."]);
        }
    }

    // =========================================================
    // 4. SUBMIT SCAN / MANUAL LOGIC
    // =========================================================
    if ($action === 'submit_scan' || $action === 'submit_manual') {
        $raw_qr = trim($input['qr_data'] ?? $input['ticket_id'] ?? '');
        $scanned_loc = strtoupper(trim($input['racking_location'] ?? ''));

        // --- DOUBLE SCAN PROTECTION ---
        if (substr_count($raw_qr, '|') > 2) {
             send_json([
                 'success' => false, 
                 'message' => "⚠️ OVER-SPEED: Multiple codes detected. Please scan one item at a time."
             ]);
        }

        if (empty($raw_qr) || empty($scanned_loc)) {
            send_json(['success' => false, 'message' => "Ticket ID and Location are required."]);
        }

        // --- A. PARSE THE INPUT ---
        if (strpos($raw_qr, '|') !== false) {
            $qr_parts = explode('|', $raw_qr);
            if (count($qr_parts) >= 3) {
                $part_no = strtoupper(trim($qr_parts[0]));
                $rec_date = date('Y-m-d', strtotime(trim($qr_parts[1])));
                $tag_id  = strtoupper(trim($qr_parts[2]));
            } else {
                send_json(['success' => false, 'message' => "Invalid QR Format."]);
            }
        } else {
            // Manual ID logic
            $tag_id = strtoupper($raw_qr);
            
            $stmt_lookup = $pdo->prepare("SELECT PART_NO, RECEIVING_DATE FROM racking_in WHERE ID_CODE = ? LIMIT 1");
            $stmt_lookup->execute([$tag_id]);
            $tag_data = $stmt_lookup->fetch(PDO::FETCH_ASSOC);

            if ($tag_data) {
                $part_no = $tag_data['PART_NO'];
                $rec_date = $tag_data['RECEIVING_DATE'];
            } else {
                send_json(['success' => false, 'message' => "Tag ID not found in system."]);
            }
        }

        // --- B. DUPLICATE CHECK ---
        $check_dup = $pdo->prepare("SELECT id FROM stocktake_log WHERE tag_id = ? AND DATE(scan_time) = CURDATE()");
        $check_dup->execute([$tag_id]);
        if ($check_dup->rowCount() > 0) {
            send_json(['success' => false, 'message' => "Error: This tag was already stocktaked today."]);
        }

        // --- C. GET MASTER DETAILS ---
        $stmt_master = $pdo->prepare("SELECT stock_desc, erp_code, seq_number, std_packing FROM master_incoming WHERE part_no = ? LIMIT 1");
        $stmt_master->execute([$part_no]);
        $master = $stmt_master->fetch(PDO::FETCH_ASSOC);

        // --- D. CHECK SYSTEM LOCATION ---
        $stmt_sys = $pdo->prepare("SELECT RACKING_LOCATION FROM racking_in WHERE ID_CODE = ?");
        $stmt_sys->execute([$tag_id]);
        $sys_data = $stmt_sys->fetch(PDO::FETCH_ASSOC);
        $sys_loc = $sys_data['RACKING_LOCATION'] ?? null;
        
        // --- E. COMPARE LOCATIONS ---
        if (!$sys_loc) {
            $status = 'NOT REGISTERED';
            $display_loc = $scanned_loc; 
        } elseif ($sys_loc === $scanned_loc) {
            $status = 'MATCH';
            $display_loc = $scanned_loc;
        } else {
            $status = 'UNMATCH';
            $display_loc = "RCK: $sys_loc | ST: $scanned_loc";
        }

        // --- F. INSERT LOG ---
        $sql_ins = "INSERT INTO stocktake_log (tag_id, receiving_date, part_name, part_no, erp_code, seq_no, qty, scanned_location, system_location, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $pdo->prepare($sql_ins);
        $stmt_ins->execute([
            $tag_id, 
            $rec_date, 
            $master['stock_desc'] ?? 'N/A',
            $part_no, 
            $master['erp_code'] ?? 'N/A', 
            $master['seq_number'] ?? 'N/A',
            $master['std_packing'] ?? 0, 
            $display_loc, 
            $sys_loc, 
            $status
        ]);

        send_json([
            'success' => true,
            'scanData' => [
                'id' => $pdo->lastInsertId(),
                'scan_time' => date('d/m/Y H:i:s'),
                'tag_id' => $tag_id,
                'receiving_date' => date('d/m/Y', strtotime($rec_date)),
                'part_name' => $master['stock_desc'] ?? 'N/A',
                'part_no' => $part_no,
                'erp_code' => $master['erp_code'] ?? 'N/A',
                'seq_no' => $master['seq_number'] ?? 'N/A',
                'qty' => $master['std_packing'] ?? 0,
                'scanned_location' => $display_loc,
                'status' => $status
            ]
        ]);
    }

} catch (Exception $e) { 
    send_json(['success' => false, 'message' => "Server Error: " . $e->getMessage()]); 
}
?>