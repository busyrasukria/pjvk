<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Validate required fields
$required = ['part_no_FG', 'erp_code_FG', 'part_description', 'model', 'std_packing'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        // Note: Changed 'part_no' to 'part_no_FG' to match the form
        header('Location: index.php?status=error&message=' . urlencode("Missing required field: $field"));
        exit;
    }
}

// Clean input data
$part_no_B = trim($_POST['part_no_B']) ?: null; // NEW
$erp_code_B = trim($_POST['erp_code_B']) ?: null; // NEW
$part_no_FG = trim($_POST['part_no_FG']); // UPDATED
$erp_code_FG = trim($_POST['erp_code_FG']); // UPDATED
$part_description = trim($_POST['part_description']);
$stock_type = trim($_POST['stock_type']); // NEW
$model = trim($_POST['model']);
$line = trim($_POST['line']) ?: null;
$location = trim($_POST['location']) ?: null;
$std_packing = (int)$_POST['std_packing'];

// Validate std_packing
if ($std_packing <= 0) {
    header('Location: index.php?status=error&message=' . urlencode("Standard packing must be greater than 0"));
    exit;
}

try {
    // Check if ERP code (FG) already exists
    $stmt = $pdo->prepare("SELECT id FROM master WHERE erp_code_FG = ?");
    $stmt->execute([$erp_code_FG]);
    if ($stmt->fetch()) {
        header('Location: index.php?status=error&message=' . urlencode("ERP code (FG) already exists"));
        exit;
    }

    // Insert new part
    $stmt = $pdo->prepare("
        INSERT INTO master (
            part_no_B, erp_code_B, part_no_FG, erp_code_FG, 
            part_description, stock_type, model, line, location, std_packing
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $part_no_B,
        $erp_code_B,
        $part_no_FG,
        $erp_code_FG,
        $part_description,
        $stock_type, // UPDATED
        $model,
        $line,
        $location,
        $std_packing
    ]);

    // Redirect back with success message
    header("Location: tt.php?model=" . urlencode($model) . "&status=part_added");
    exit;

} catch (PDOException $e) {
    error_log("Error adding part: " . $e->getMessage());
    
    // Check for duplicate entry error
    if ($e->getCode() == '23000') {
        header('Location: index.php?status=error&message=' . urlencode("Part with this ERP code (FG) already exists"));
    } else {
        header('Location: index.php?status=error&message=' . urlencode("Database error occurred"));
    }
    exit;
}
?>