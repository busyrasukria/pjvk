<?php
require_once 'db.php';
header('Content-Type: application/json');

// Get data from JSON POST
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$unique_no = $data['unique_no'] ?? null;
$erp_code_fg = $data['erp_code_fg'] ?? null;

if (empty($unique_no) || empty($erp_code_fg)) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID and ERP Code are required.']);
    exit;
}

try {
    // 1. Find the correct ticket_id first
    $stmt_ticket = $pdo->prepare("SELECT ticket_id FROM transfer_tickets WHERE unique_no = ? AND erp_code_FG = ?");
    $stmt_ticket->execute([$unique_no, $erp_code_fg]);
    $ticket_info = $stmt_ticket->fetch();

    if (!$ticket_info) {
        echo json_encode(['success' => false, 'message' => 'No matching ticket found for that ID and ERP Code.']);
        exit;
    }
    $transfer_ticket_id = $ticket_info['ticket_id'];


    // 2. Check if already scanned OUT
    // === LOGIC CORRECTED to use transfer_ticket_id and erp_code_fg ===
    $stmt_check_out = $pdo->prepare("SELECT 1 FROM warehouse_out_pne WHERE transfer_ticket_id = ? AND erp_code_FG = ?");
    $stmt_check_out->execute([$transfer_ticket_id, $erp_code_fg]);
    if ($stmt_check_out->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Error: This ticket (ID: ' . htmlspecialchars($unique_no) . ') has already been scanned OUT.']);
        exit;
    }

    // 3. --- (NEW!) CHECK IF SCANNED IN ---
    $stmt_check_in = $pdo->prepare("SELECT 1 FROM warehouse_in WHERE transfer_ticket_id = ? AND erp_code_FG = ?");
    $stmt_check_in->execute([$transfer_ticket_id, $erp_code_fg]);
    
    if (!$stmt_check_in->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Error: This ticket has not been scanned IN to the warehouse yet.']);
        exit;
    }
    // --- END OF NEW CHECK ---


    // 4. Fetch ticket and master data
    $stmt = $pdo->prepare("
        SELECT 
            tt.ticket_id, tt.unique_no, tt.created_at, tt.erp_code_FG, tt.part_no_FG, 
            tt.part_name, tt.model, tt.prod_area, tt.quantity, tt.released_by,
            m.part_no_B, m.erp_code_B
        FROM transfer_tickets tt
        LEFT JOIN master m ON tt.erp_code_FG = m.erp_code_FG
        WHERE tt.ticket_id = ?
    ");
    $stmt->execute([$transfer_ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        // This check is redundant now but safe to keep
        echo json_encode(['success' => false, 'message' => 'No matching ticket found for that ID and ERP Code.']);
        exit;
    }
    
    // 5. Get manpower display name(s)
    $manpower_display = $ticket['released_by']; // Default to IDs
    $emp_ids = array_map('trim', explode(',', $ticket['released_by']));
    if (!empty($emp_ids)) {
        $placeholders = str_repeat('?,', count($emp_ids) - 1) . '?';
        // Check by both emp_id AND nickname
        $stmt_mp = $pdo->prepare("SELECT emp_id, name, nickname FROM manpower WHERE emp_id IN ($placeholders) OR nickname IN ($placeholders)");
        $stmt_mp->execute(array_merge($emp_ids, $emp_ids));
        
        $manpowerMap = [];
        foreach ($stmt_mp->fetchAll() as $mp) {
            $displayName = $mp['nickname'] ?? (explode(' ', $mp['name'])[0]);
            if (!empty($mp['emp_id'])) $manpowerMap[$mp['emp_id']] = $displayName;
            if (!empty($mp['nickname'])) $manpowerMap[$mp['nickname']] = $displayName;
        }

        $names = [];
        foreach ($emp_ids as $id) {
            $names[] = $manpowerMap[$id] ?? $id;
        }
        $manpower_display = implode(' / ', $names);
    }


    // 6. Return all data
    echo json_encode([
        'success' => true,
        'data' => [
            'unique_no' => htmlspecialchars($ticket['unique_no']),
            'prod_date' => date('d/m/Y', strtotime($ticket['created_at'])),
            'erp_code_FG' => htmlspecialchars($ticket['erp_code_FG']),
            'part_no_FG' => htmlspecialchars($ticket['part_no_FG']),
            'part_name' => htmlspecialchars($ticket['part_name']),
            'part_no_B' => htmlspecialchars($ticket['part_no_B'] ?? '-'),
            'erp_code_B' => htmlspecialchars($ticket['erp_code_B'] ?? '-'),
            'model' => htmlspecialchars($ticket['model']),
            'prod_area' => htmlspecialchars($ticket['prod_area']),
            'quantity' => (int)$ticket['quantity'],
            'released_by_ids' => htmlspecialchars($ticket['released_by']), 
            'released_by_display' => htmlspecialchars($manpower_display)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Fetch Ticket PNE Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>