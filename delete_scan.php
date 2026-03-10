<?php
require_once 'db.php';
header('Content-Type: application/json');

// --- SET YOUR DELETE PASSWORD HERE ---
$admin_password = "Admin404"; // Update this to your actual password
// -------------------------------------

// 1. Check if the request is valid
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
    // 1. GET TICKET ID FIRST (We need this to find the tracking log)
    $stmt_find = $pdo->prepare("SELECT transfer_ticket_id FROM warehouse_in WHERE log_id = ?");
    $stmt_find->execute([$log_id]);
    $row = $stmt_find->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Scan record not found.']);
        exit;
    }
    $ticket_id = $row['transfer_ticket_id'];

    // 2. DELETE THE SCAN RECORD
    $stmt = $pdo->prepare("DELETE FROM warehouse_in WHERE log_id = ?");
    $stmt->execute([$log_id]);

    // 3. DELETE THE TRACKING HISTORY (Status: WH_IN)
    $stmt_log = $pdo->prepare("DELETE FROM ticket_status_log WHERE ticket_id = ? AND status_code = 'WH_IN'");
    $stmt_log->execute([$ticket_id]);

    echo json_encode(['success' => true, 'message' => 'Scan and tracking history deleted.']);

} catch (PDOException $e) {
    error_log("Error deleting scan log: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>