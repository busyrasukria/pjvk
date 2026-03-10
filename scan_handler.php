<?php
require_once 'db.php';
header('Content-Type: application/json');

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
$qr_data = $data['qr_data'] ?? null;

if (empty($qr_data)) {
    echo json_encode(['success' => false, 'message' => 'No QR data received.']);
    exit;
}

// 1. PARSE THE NEW QR CODE FORMAT
// Format: Date|TT ID|ERP CODE FG|RELEASE BY| QTY
// Example: 30/10/2025|00000490|AA031299|SHS|1
$parts = explode('|', $qr_data);
if (count($parts) < 5) {
    echo json_encode(['success' => false, 'message' => "Invalid QR code format. Expected 5 parts, got " . count($parts) . "."]);
    exit;
}

$prod_date_str = $parts[0] ?? null;
$unique_no = $parts[1] ?? null;
$erp_code_from_qr = $parts[2] ?? null;
$released_by_from_qr = $parts[3] ?? null; // This is the ID, e.g., 'SHS'
$quantity_from_qr = (int)($parts[4] ?? 0);

if (empty($unique_no) || empty($erp_code_from_qr) || empty($prod_date_str) || $quantity_from_qr <= 0) {
    echo json_encode(['success' => false, 'message' => "Invalid QR data. Check all fields."]);
    exit;
}

// Convert DD/MM/YYYY to YYYY-MM-DD for database
$prod_date_db = null;
try {
    $date_parts = date_parse_from_format('d/m/Y', $prod_date_str);
    if ($date_parts['error_count'] === 0 && checkdate($date_parts['month'], $date_parts['day'], $date_parts['year'])) {
        $prod_date_db = $date_parts['year'] . '-' . str_pad($date_parts['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts['day'], 2, '0', STR_PAD_LEFT);
    } else {
        throw new Exception('Invalid date format.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Invalid Production Date format in QR. Expected DD/MM/YYYY."]);
    exit;
}


// 2. FIND THE ORIGINAL TICKET to get its `ticket_id`
// This is VITAL for the UNIQUE constraint to prevent double scans
try {
    $stmt_ticket = $pdo->prepare("SELECT ticket_id FROM transfer_tickets WHERE unique_no = ? AND erp_code_FG = ?");
    $stmt_ticket->execute([$unique_no, $erp_code_from_qr]);
    $ticket = $stmt_ticket->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => "Transfer Ticket not found in database (ID: $unique_no)"]);
        exit;
    }
    $transfer_ticket_id = $ticket['ticket_id'];

    // 3. FIND THE MASTER PART DATA
    $stmt_master = $pdo->prepare("SELECT * FROM master WHERE erp_code_FG = ?");
    $stmt_master->execute([$erp_code_from_qr]);
    $master_data = $stmt_master->fetch();

    if (!$master_data) {
        echo json_encode(['success' => false, 'message' => "Part not found in Master (ERP: $erp_code_from_qr)"]);
        exit;
    }

    // 4. PREPARE MANPOWER DISPLAY
    // Get the display name (e.g., 'SHS' -> 'Syahir')
    $manpower_display = $released_by_from_qr; // Default
    if (!empty($released_by_from_qr)) {
        // We assume the QR stores the emp_id or nickname. Let's check both.
        $stmt_mp = $pdo->prepare("SELECT name, nickname FROM manpower WHERE emp_id = ? OR nickname = ?");
        $stmt_mp->execute([$released_by_from_qr, $released_by_from_qr]);
        $mp = $stmt_mp->fetch();
        if ($mp) {
            $manpower_display = $mp['nickname'] ?? (explode(' ', $mp['name'])[0]); // Use nickname or first name
        }
    }

    // 5. INSERT INTO WAREHOUSE IN
    // The database UNIQUE constraint on `transfer_ticket_id` will handle duplicates
    $stmt_insert = $pdo->prepare("
        INSERT INTO warehouse_in 
            (transfer_ticket_id, unique_no, prod_date, released_by, erp_code_FG, part_no_FG, part_name, prod_area, model, quantity)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt_insert->execute([
        $transfer_ticket_id,
        $unique_no,
        $prod_date_db,          // From QR
        $manpower_display,      // Display name
        $erp_code_from_qr,      // From QR
        $master_data['part_no_FG'],
        $master_data['part_description'],
        $master_data['line'],   // 'line' column from master is 'prod_area'
        $master_data['model'],
        $quantity_from_qr       // From QR
    ]);

    $new_log_id = $pdo->lastInsertId();

    // --- START: NEW TRACKING LOG CODE ---
        try {
            $stmt_log_status = $pdo->prepare(
                "INSERT INTO ticket_status_log (ticket_id, status_code, status_message, scanned_by) 
                VALUES (?, ?, ?, ?)"
            );
            $stmt_log_status->execute([
                $transfer_ticket_id,       // The ID of the ticket from transfer_tickets
                'WH_IN',
                'Scanned into Warehouse',
                $manpower_display          // The name of the person who scanned it
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log Warehouse In status: " . $e->getMessage());
        }
        // --- END: NEW TRACKING LOG CODE ---

    // 6. SEND BACK THE NEW ROW'S DATA
    echo json_encode([
        'success' => true,
        'message' => 'Ticket scanned successfully!',
        'scanData' => [
            'log_id' => $new_log_id,
            'scan_time' => date('d/m/Y H:i:s'), // Format current time
            'unique_no' => $unique_no,
            'prod_date' => $prod_date_str, // From QR (original DD/MM/YYYY)
            'part_name' => $master_data['part_description'],
            'part_no_FG' => $master_data['part_no_FG'],
            'erp_code_FG' => $erp_code_from_qr,
            'part_no_B' => $master_data['part_no_B'] ?? '-',
            'erp_code_B' => $master_data['erp_code_B'] ?? '-',
            'model' => $master_data['model'],
            'prod_area' => $master_data['line'] ?? '-',
            'quantity' => $quantity_from_qr,
            'released_by_display' => $manpower_display
        ]
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        // This catches the UNIQUE constraint violation
        echo json_encode(['success' => false, 'message' => "Error: This ticket (ID: $unique_no, ERP: $erp_code_from_qr) has already been scanned."]);
    } else {
        error_log("Scan Handler Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred. ' . $e->getMessage()]);
    }
}
?>