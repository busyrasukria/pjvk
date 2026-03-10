<?php
require_once 'db.php';
header('Content-Type: application/json');

// --- SET YOUR DELETE PASSWORD HERE ---
$admin_password = "Admin404"; 
// -------------------------------------

// 1. Check Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$log_id = $_POST['log_id'] ?? null;
$password = $_POST['password'] ?? null;

if (empty($log_id) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Missing Log ID or password.']);
    exit;
}

if ($password !== $admin_password) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. GET INFO BEFORE DELETING
    // We need the Ticket ID to find the downstream "PNE In" record
    $stmt_get = $pdo->prepare("SELECT transfer_ticket_id, unique_no FROM warehouse_out_pne WHERE log_id = ?");
    $stmt_get->execute([$log_id]);
    $row = $stmt_get->fetch();

    if (!$row) {
        throw new Exception('Record not found.');
    }

    $ticket_id = $row['transfer_ticket_id'];
    $unique_no = $row['unique_no'];

    // 3. DELETE DOWNSTREAM: PNE IN TO WAREHOUSE
    // If we delete the "Out", the "In" is invalid. Delete it.
    $stmt_del_next = $pdo->prepare("DELETE FROM pne_warehouse_in WHERE transfer_ticket_id = ?");
    $stmt_del_next->execute([$ticket_id]);
    $pne_in_deleted = $stmt_del_next->rowCount();

    // 4. DELETE DOWNSTREAM LOGS (PNE_IN)
    // Remove the tracking log for the step we just auto-deleted
    if ($pne_in_deleted > 0) {
        $pdo->prepare("DELETE FROM ticket_status_log WHERE ticket_id = ? AND status_code = 'PNE_IN'")
            ->execute([$ticket_id]);
    }

    // 5. DELETE CURRENT STEP: WAREHOUSE OUT TO PNE
    $stmt_del_current = $pdo->prepare("DELETE FROM warehouse_out_pne WHERE log_id = ?");
    $stmt_del_current->execute([$log_id]);

    // 6. DELETE CURRENT LOGS (PNE_OUT)
    $pdo->prepare("DELETE FROM ticket_status_log WHERE ticket_id = ? AND status_code = 'PNE_OUT'")
        ->execute([$ticket_id]);

    $pdo->commit();

    $msg = 'Scanned record deleted.';
    if ($pne_in_deleted > 0) {
        $msg .= ' (Auto-deleted downstream "PNE In" record)';
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete PNE Scan Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>