<?php
require_once 'db.php';
header('Content-Type: application/json');

// === DEFINE PNE PARTS ===
const PNE_REQUIRED_PARTS = [
    'AA021298', 'AA051297', 'AA031299', 'AC020059', 
    'AC050058', 'AC030060', 'AD020140', 'AD050142' 
];

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
$qr_data = $data['qr_data'] ?? null;

if (empty($qr_data)) {
    echo json_encode(['success' => false, 'message' => 'No QR data received.']);
    exit;
}

// 1. PARSE QR CODE
$parts = explode('|', $qr_data);
if (count($parts) < 5) {
    echo json_encode(['success' => false, 'message' => "Invalid QR code format."]);
    exit;
}

$prod_date_str = $parts[0] ?? null;
$unique_no = $parts[1] ?? null;
$erp_code_from_qr = $parts[2] ?? null;
$released_by_from_qr = $parts[3] ?? null; 
$quantity_from_qr = (int)($parts[4] ?? 0);

if (empty($unique_no) || empty($erp_code_from_qr)) {
    echo json_encode(['success' => false, 'message' => "Invalid QR data."]);
    exit;
}

// === NEW LOGIC: BLOCK NON-PNE PARTS ===
if (!in_array($erp_code_from_qr, PNE_REQUIRED_PARTS)) {
    echo json_encode([
        'success' => false, 
        'message' => "Error: Part ($erp_code_from_qr) does not require PNE processing."
    ]);
    exit;
}
// =======================================

// Convert DD/MM/YYYY to YYYY-MM-DD
$prod_date_db = null;
try {
    $date_parts = date_parse_from_format('d/m/Y', $prod_date_str);
    if ($date_parts['error_count'] === 0 && checkdate($date_parts['month'], $date_parts['day'], $date_parts['year'])) {
        $prod_date_db = $date_parts['year'] . '-' . str_pad($date_parts['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts['day'], 2, '0', STR_PAD_LEFT);
    } else {
        throw new Exception('Invalid date format.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Invalid Date format."]);
    exit;
}

// 2. FIND ORIGINAL TICKET
try {
    $stmt_ticket = $pdo->prepare("SELECT ticket_id FROM transfer_tickets WHERE unique_no = ? AND erp_code_FG = ?");
    $stmt_ticket->execute([$unique_no, $erp_code_from_qr]);
    $ticket = $stmt_ticket->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => "Ticket not found in database."]);
        exit;
    }
    $transfer_ticket_id = $ticket['ticket_id'];

    // 3. CHECK IF TICKET IS IN WAREHOUSE_IN TABLE
    $stmt_check_in = $pdo->prepare("SELECT 1 FROM warehouse_in WHERE transfer_ticket_id = ? AND erp_code_FG = ?");
    $stmt_check_in->execute([$transfer_ticket_id, $erp_code_from_qr]);
    
    if (!$stmt_check_in->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Error: Ticket must be scanned IN to the warehouse first.']);
        exit;
    }

    // 4. FIND MASTER PART DATA
    $stmt_master = $pdo->prepare("SELECT * FROM master WHERE erp_code_FG = ?");
    $stmt_master->execute([$erp_code_from_qr]);
    $master_data = $stmt_master->fetch();

    if (!$master_data) {
        echo json_encode(['success' => false, 'message' => "Part not found in Master."]);
        exit;
    }

    // 5. PREPARE MANPOWER DISPLAY
    $manpower_display = $released_by_from_qr; 
    if (!empty($released_by_from_qr)) {
        $stmt_mp = $pdo->prepare("SELECT name, nickname FROM manpower WHERE emp_id = ? OR nickname = ?");
        $stmt_mp->execute([$released_by_from_qr, $released_by_from_qr]);
        $mp = $stmt_mp->fetch();
        if ($mp) {
            $manpower_display = $mp['nickname'] ?? (explode(' ', $mp['name'])[0]);
        }
    }

    // 6. INSERT INTO WAREHOUSE OUT PNE
    $stmt_insert = $pdo->prepare("
        INSERT INTO warehouse_out_pne 
            (transfer_ticket_id, unique_no, prod_date, released_by, erp_code_FG, part_no_FG, part_name, prod_area, model, quantity)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt_insert->execute([
        $transfer_ticket_id, $unique_no, $prod_date_db, $manpower_display, $erp_code_from_qr,
        $master_data['part_no_FG'], $master_data['part_description'], $master_data['line'],
        $master_data['model'], $quantity_from_qr
    ]);

    $new_log_id = $pdo->lastInsertId();

    // TRACKING LOG
    $pdo->prepare("INSERT INTO ticket_status_log (ticket_id, status_code, status_message, scanned_by) VALUES (?, ?, ?, ?)")
        ->execute([$transfer_ticket_id, 'PNE_OUT', 'Scanned Out to PNE', $manpower_display]);

    // 7. SUCCESS
    echo json_encode([
        'success' => true,
        'message' => 'Ticket Scanned OUT to PNE!',
        'scanData' => [
            'log_id' => $new_log_id,
            'scan_time' => date('d/m/Y H:i:s'),
            'unique_no' => $unique_no,
            'prod_date' => $prod_date_str,
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
        echo json_encode(['success' => false, 'message' => "Error: This ticket has already been scanned OUT to PNE."]);
    } else {
        error_log("Scan Handler PNE Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
    }
}
?>