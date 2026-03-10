<?php
require_once 'db.php';
session_start();

// 1. Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// 2. Return JSON
header('Content-Type: application/json');

// 3. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// 4. Validate Required Fields
$required = [
    'selected_parts_json',
    'released_by',
    'custom_date',
    'model',
    'num_copies'
];

foreach ($required as $f) {
    if (empty($_POST[$f])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $f"]);
        exit;
    }
}

// 5. Clean Input
$parts_data = json_decode($_POST['selected_parts_json'], true);
$released_by_ids_string = trim($_POST['released_by']);
$custom_date = trim($_POST['custom_date']);
$model = trim($_POST['model']);
$num_copies = (int)$_POST['num_copies'];
if ($num_copies <= 0) $num_copies = 1;
$custom_display_quantity = (int)($_POST['quantity'] ?? 0);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($parts_data) || empty($parts_data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid part data or no parts selected.']);
    exit;
}

// 6. VALIDATE DATE FORMAT
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Expected Y-m-d.']);
    exit;
}

// ==========================================================
// === FIX: PRE-VALIDATION BLOCK (PREVENT ID SKIPPING) ===
// ==========================================================
// We filter the parts list FIRST. Any bad part is removed 
// BEFORE we start the transaction. This prevents rollbacks.
$valid_parts = [];
foreach ($parts_data as $index => $part) {
    // Check if essential data is missing
    if (empty($part['erp']) || empty($part['partNo'])) {
        // Option A: Skip this specific item silently
        // Option B: Stop everything and tell user (uncomment below to stop everything)
        // echo json_encode(['success' => false, 'message' => "Item #" . ($index + 1) . " is missing ERP or PartNo."]); exit;
        
        // We will SKIP the bad item so the rest can print sequentially
        continue; 
    }
    $valid_parts[] = $part;
}

if (empty($valid_parts)) {
    echo json_encode(['success' => false, 'message' => 'No valid parts to print. Check ERP codes.']);
    exit;
}
// ==========================================================


try {
    $today = date('Y-m-d');
    $time_part = ($custom_date == $today) ? date('H:i:s') : '08:01:00';
    $date_with_time = $custom_date . ' ' . $time_part;

    // === Manpower Name Logic ===
    $released_by_names = '-';
    $runner_ids = [];

    if (!empty($released_by_ids_string)) {
        $runner_ids = array_filter(array_map('trim', explode(',', $released_by_ids_string)));
    }

    if (!empty($runner_ids)) {
        $placeholders = str_repeat('?,', count($runner_ids) - 1) . '?';
        $sql = "SELECT emp_id, name, nickname FROM manpower WHERE emp_id IN ($placeholders) ORDER BY FIELD(emp_id, $placeholders)";
        $stmt_names = $pdo->prepare($sql);
        $execute_params = array_merge($runner_ids, $runner_ids);
        $stmt_names->execute($execute_params);

        $runners_info = $stmt_names->fetchAll(PDO::FETCH_ASSOC);
        $runner_map = [];
        foreach ($runners_info as $row) { $runner_map[$row['emp_id']] = $row; }

        $names_array = [];
         foreach ($runner_ids as $id) {
            if (isset($runner_map[$id])) {
                $runner = $runner_map[$id];
                 $names_array[] = !empty($runner['nickname']) ? $runner['nickname'] : $runner['name'];
            } else {
                 $names_array[] = $id; 
            }
         }
        if (!empty($names_array)) { $released_by_names = implode(' / ', $names_array); }
    }

    // === START DATABASE TRANSACTION ===
    $pdo->beginTransaction();

    $inserted_ticket_ids = [];
    
    $stmt = $pdo->prepare("
        INSERT INTO transfer_tickets (
            unique_no, erp_code_FG, part_no_FG, part_name, model, prod_area, quantity, released_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Query to get the generated unique_no (Trigger/Auto-increment)
    $stmt_get_id = $pdo->prepare("SELECT unique_no FROM transfer_tickets WHERE ticket_id = ?");

    // Status Log Statement
    $stmt_log_status = $pdo->prepare(
        "INSERT INTO ticket_status_log (ticket_id, status_code, status_message, scanned_by) 
        VALUES (?, ?, ?, ?)"
    );

    // === MAIN INSERT LOOP ===
    for ($i = 1; $i <= $num_copies; $i++) {
        
        $shared_unique_no_for_this_copy = null;
        $is_first_part_of_this_copy = true;

        // Use the $valid_parts array we cleaned earlier
        foreach ($valid_parts as $part) {
            
            $erp_code_FG = trim($part['erp']);
            $part_no_FG = trim($part['partNo']);
            $part_name = trim($part['name']);
            $prod_area = trim($part['line'] ?? '');
            
            $quantity_to_save = ($custom_display_quantity > 0) ? $custom_display_quantity : max(1, (int)$part['stdQty']);
            
            // Logic for "Same ID for batch"
            if ($is_first_part_of_this_copy) {
                $id_to_insert = null; // Send NULL to let DB generate new unique_no
                $is_first_part_of_this_copy = false;
            } else {
                $id_to_insert = $shared_unique_no_for_this_copy; // Reuse the unique_no
            }

            // Execute Insert
            $stmt->execute([
                $id_to_insert,
                $erp_code_FG,
                $part_no_FG,
                $part_name,
                $model,
                $prod_area,
                $quantity_to_save,
                $released_by_names,
                $date_with_time
            ]);
            
            $newly_inserted_db_id = $pdo->lastInsertId();
            $inserted_ticket_ids[] = $newly_inserted_db_id;

            // Log Status (Safe Log)
            try {
                $stmt_log_status->execute([
                    $newly_inserted_db_id, 'PRINTED', 'Ticket Printed', $released_by_names
                ]);
            } catch (PDOException $e) {
                // Ignore log errors to prevent rollback of main ticket
            }
            
            // If we just generated a new unique_no (first part), fetch it so we can reuse it
            if ($id_to_insert === null) {
                $stmt_get_id->execute([$newly_inserted_db_id]);
                $fetched_id = $stmt_get_id->fetchColumn();
                
                if ($fetched_id === false) {
                    // Fallback if DB is slow
                    $shared_unique_no_for_this_copy = 'ERR-' . $newly_inserted_db_id;
                    error_log("Failed to fetch unique_no for ticket ID: " . $newly_inserted_db_id);
                } else {
                    $shared_unique_no_for_this_copy = $fetched_id;
                }
            }
        }
    } 

    $pdo->commit();

    // SUCCESS RESPONSE
    $ids_string = implode(',', $inserted_ticket_ids);
    echo json_encode([
        'success' => true,
        'ticket_ids' => $ids_string,
        'model' => $model
    ]);
    exit;

} catch (PDOException $e) {
     if ($pdo->inTransaction()) {
       $pdo->rollBack();
    }
     error_log("Database Error in print_ticket.php: " . $e->getMessage());
     echo json_encode(['success' => false, 'message' => 'Database error. Please check logs.']);
     exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
       $pdo->rollBack();
    }
    error_log("Error in print_ticket.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>