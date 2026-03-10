<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = $_POST['id'] ?? null;
$supplier = $_POST['supplier'] ?? null;
$password = $_POST['password'] ?? null;
$admin_password = "Admin404"; // Ensure this matches your system's admin password

if (!$id || !$supplier || !$password) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if ($password !== $admin_password) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

$tables = [
    'YTEC' => 'receiving_log_ytec',
    'MAZDA' => 'receiving_log_mazda',
    'MARZ' => 'receiving_log_marz'
];

$table = $tables[$supplier] ?? null;
if (!$table) {
    echo json_encode(['success' => false, 'message' => 'Invalid supplier']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found or already deleted']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>