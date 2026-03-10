<?php
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$log_id = $_POST['log_id'] ?? '';
$password = $_POST['password'] ?? '';

// 1. Basic Security Check
if ($password !== 'admin404') {
    echo json_encode(['success' => false, 'message' => 'Invalid Password.']);
    exit;
}

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'Missing Log ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. GET SCAN INFO BEFORE DELETING
    // We fetch details to decide if we should decrement the master count
    $sql_get = "SELECT l.*, mt.MODEL, mt.TYPE, mt.VARIANT 
                FROM warehouse_out l 
                JOIN master_trip mt ON l.master_trip_id = mt.id 
                WHERE l.log_id = ?";
    $stmt = $pdo->prepare($sql_get);
    $stmt->execute([$log_id]);
    $scan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scan) {
        throw new Exception("Scan record not found.");
    }

    $master_id = $scan['master_trip_id'];
    $trip_col_name = $scan['trip']; 
    $scan_ts = $scan['scan_timestamp'];
    
    // Parse Ticket Qty from QR
    // QR Format: DATE|UNIQUE|ERP|RELEASED|QTY
    $qr_parts = explode('|', $scan['ticket_qr']);
    $ticket_qty = isset($qr_parts[4]) ? (int)$qr_parts[4] : 1;

    // 3. DETERMINE DECREMENT AMOUNT (Smart Logic)
    $decrement_amount = 1; // Default: Delete 1 ticket = Remove 1 Count

    // --- LOGIC: CX30 / TBA / TRIP 1 (Split 2 + 2) ---
    if ($scan['MODEL'] === 'CX30' && $scan['TYPE'] === 'TBA' && $trip_col_name === 'TRIP_1') {
        // If it's a standard Qty 4, it counts as 1. 
        // If it's Qty 2, it's a partial.
        if ($ticket_qty === 2) {
            // Check if a "Partner" exists (Same Batch)
            // We look for another scan with same master_id, close timestamp, but different log_id
            $sql_partner = "SELECT count(*) FROM warehouse_out 
                            WHERE master_trip_id = ? 
                            AND log_id != ? 
                            AND ABS(TIMESTAMPDIFF(SECOND, scan_timestamp, ?)) < 5"; 
            $stmt_p = $pdo->prepare($sql_partner);
            $stmt_p->execute([$master_id, $log_id, $scan_ts]);
            $partner_count = $stmt_p->fetchColumn();

            if ($partner_count > 0) {
                // Partner exists. We are breaking a Full Set.
                $decrement_amount = 1;
            } else {
                // No partner. The set is already broken/orphan. Count was already removed.
                $decrement_amount = 0;
            }
        }
    }

    // --- LOGIC: CX30 / BODY / TRIP 2 (Split 1 + 1 OR Single 2) ---
    elseif ($scan['MODEL'] === 'CX30' && $scan['TYPE'] === 'BODY' && $trip_col_name === 'TRIP_2') {
        // Plan 1,2 = Qty 3 (Standard)
        // Plan 3 = Qty 2 (Standard) OR Qty 1 (Partial)
        
        if ($ticket_qty === 1) {
            // It's a partial. Check for partner.
            $sql_partner = "SELECT count(*) FROM warehouse_out 
                            WHERE master_trip_id = ? 
                            AND log_id != ? 
                            AND ABS(TIMESTAMPDIFF(SECOND, scan_timestamp, ?)) < 5"; 
            $stmt_p = $pdo->prepare($sql_partner);
            $stmt_p->execute([$master_id, $log_id, $scan_ts]);
            $partner_count = $stmt_p->fetchColumn();

            if ($partner_count > 0) {
                $decrement_amount = 1; // Partner exists, we are breaking the set.
            } else {
                $decrement_amount = 0; // Already broken, don't double dip.
            }
        }
        // Note: If Qty is 2 or 3, it's treated as a full count ($decrement_amount stays 1)
    }

    // 4. DECREMENT THE MASTER COUNT
    $allowed_trips = ['TRIP_1', 'TRIP_2', 'TRIP_3', 'TRIP_4', 'TRIP_5', 'TRIP_6'];
    
    if (in_array($trip_col_name, $allowed_trips) && $master_id && $decrement_amount > 0) {
        $actual_col = "ACTUAL_" . $trip_col_name; 
        
        // Update DB
        $sql_update = "UPDATE master_trip SET $actual_col = GREATEST($actual_col - ?, 0) WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$decrement_amount, $master_id]);
    }

    // 5. DELETE THE LOG ROW
    $stmt_del = $pdo->prepare("DELETE FROM warehouse_out WHERE log_id = ?");
    $stmt_del->execute([$log_id]);

    $pdo->commit();
    
    // Message customization for clarity
    $msg = 'Scan deleted.';
    if ($decrement_amount === 0) {
        $msg .= ' (Trip count NOT reduced: Partner ticket was already missing).';
    } else {
        $msg .= ' (Trip count updated).';
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>