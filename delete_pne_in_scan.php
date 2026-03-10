<?php
require_once 'db.php';
header('Content-Type: application/json');

// --- CONFIGURATION ---
$admin_password = "Admin404"; 
// ---------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$log_id = $_POST['log_id'] ?? null;
$password = $_POST['password'] ?? null;

if (empty($log_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Missing ID or Password.']);
    exit;
}

if ($password !== $admin_password) {
    echo json_encode(['success' => false, 'message' => 'Incorrect Password.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. GET INFO
    $stmt_find = $pdo->prepare("SELECT transfer_ticket_id, unique_no FROM pne_warehouse_in WHERE log_id = ?");
    $stmt_find->execute([$log_id]);
    $row = $stmt_find->fetch();

    if (!$row) {
        throw new Exception('Record not found.');
    }
    $ticket_id = $row['transfer_ticket_id'];
    $unique_no = $row['unique_no'];

    // 2. CHECK & DELETE DOWNSTREAM (Warehouse Out / Customer)
    $stmt_out = $pdo->prepare("SELECT master_trip_id, trip FROM warehouse_out WHERE ticket_qr LIKE ?");
    $stmt_out->execute(["%|$unique_no|%"]);
    $out_row = $stmt_out->fetch(PDO::FETCH_ASSOC);

    if ($out_row) {
        // Fix Trip Count
        $master_id = $out_row['master_trip_id'];
        $trip_col = $out_row['trip']; 
        $allowed_trips = ['TRIP_1', 'TRIP_2', 'TRIP_3', 'TRIP_4', 'TRIP_5', 'TRIP_6'];
        
        if (in_array($trip_col, $allowed_trips)) {
            $actual_col = "ACTUAL_" . $trip_col;
            $pdo->prepare("UPDATE master_trip SET $actual_col = GREATEST($actual_col - 1, 0) WHERE id = ?")
                ->execute([$master_id]);
        }

        // Delete the Warehouse Out record
        $pdo->prepare("DELETE FROM warehouse_out WHERE ticket_qr LIKE ?")->execute(["%|$unique_no|%"]);
        
        // Delete the Log
        $pdo->prepare("DELETE FROM ticket_status_log WHERE ticket_id = ? AND status_code = 'CUSTOMER_OUT'")->execute([$ticket_id]);
    }

    // 3. DELETE CURRENT RECORD (PNE IN)
    $stmt = $pdo->prepare("DELETE FROM pne_warehouse_in WHERE log_id = ?");
    $stmt->execute([$log_id]);

    // 4. DELETE LOG (PNE IN)
    $pdo->prepare("DELETE FROM ticket_status_log WHERE ticket_id = ? AND status_code = 'PNE_IN'")->execute([$ticket_id]);

    $pdo->commit();
    
    $msg = 'PNE In record deleted.';
    if ($out_row) $msg .= ' (Also auto-deleted downstream Warehouse Out record)';
    
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Delete PNE In Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>