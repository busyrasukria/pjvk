<?php
require_once 'db.php';
header('Content-Type: application/json');

$admin_password = "Admin404"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$ticket_id = $_POST['ticket_id'] ?? null;
$password = $_POST['password'] ?? null;

if (empty($ticket_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Missing ticket ID or password.']);
    exit;
}

if ($password !== $admin_password) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. GET UNIQUE NO (Needed to find warehouse_out record)
    $stmt_get = $pdo->prepare("SELECT unique_no FROM transfer_tickets WHERE ticket_id = ?");
    $stmt_get->execute([$ticket_id]);
    $unique_no = $stmt_get->fetchColumn();

    if ($unique_no) {
        // 2. CHECK WAREHOUSE OUT & FIX TRIP COUNTS
        // We must find if this ticket was shipped, and if so, decrement the trip count.
        $stmt_out = $pdo->prepare("SELECT master_trip_id, trip FROM warehouse_out WHERE ticket_qr LIKE ?");
        $stmt_out->execute(["%|$unique_no|%"]);
        $out_row = $stmt_out->fetch(PDO::FETCH_ASSOC);

        if ($out_row) {
            $master_id = $out_row['master_trip_id'];
            $trip_col = $out_row['trip']; // e.g. 'TRIP_1'
            
            // Safe column check
            $allowed_trips = ['TRIP_1', 'TRIP_2', 'TRIP_3', 'TRIP_4', 'TRIP_5', 'TRIP_6'];
            if (in_array($trip_col, $allowed_trips)) {
                $actual_col = "ACTUAL_" . $trip_col;
                // Decrement count
                $sql_update = "UPDATE master_trip SET $actual_col = GREATEST($actual_col - 1, 0) WHERE id = ?";
                $pdo->prepare($sql_update)->execute([$master_id]);
            }

            // Now delete the Warehouse Out record
            $pdo->prepare("DELETE FROM warehouse_out WHERE ticket_qr LIKE ?")->execute(["%|$unique_no|%"]);
            
            // Delete Customer Out Log
            $pdo->prepare("DELETE FROM ticket_status_log WHERE ticket_id = ? AND status_code = 'CUSTOMER_OUT'")->execute([$ticket_id]);
        }
    }

    // 3. DELETE DOWNSTREAM PNE RECORDS (If any exist - DB cascade might handle this, but manual is safer)
    $pdo->prepare("DELETE FROM pne_warehouse_in WHERE transfer_ticket_id = ?")->execute([$ticket_id]);
    $pdo->prepare("DELETE FROM warehouse_out_pne WHERE transfer_ticket_id = ?")->execute([$ticket_id]);

    // 4. DELETE THE TICKET (This is the root)
    $stmt = $pdo->prepare("DELETE FROM transfer_tickets WHERE ticket_id = ?");
    $stmt->execute([$ticket_id]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ticket and all linked process flow data deleted.']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error deleting ticket: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>