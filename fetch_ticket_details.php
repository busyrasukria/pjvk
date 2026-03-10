<?php
// fileName: fetch_ticket_details.php
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
    // =========================================================
    // FIX 1: CHECK WAREHOUSE OUT (Prevent Double Scanning)
    // We check if this specific ID + ERP combo is ALREADY in the 'out' table.
    // =========================================================
    $stmt_out = $pdo->prepare("SELECT 1 FROM warehouse_out WHERE unique_no = ? AND erp_code = ?");
    $stmt_out->execute([$unique_no, $erp_code_fg]);
    if ($stmt_out->fetch()) {
        echo json_encode(['success' => false, 'message' => "Error: Ticket $unique_no ($erp_code_fg) has ALREADY been scanned out."]);
        exit;
    }

    // =========================================================
    // FIX 2: CHECK WAREHOUSE IN (Ensure Stock Exists)
    // We OPTIONALLY check if it was ever scanned IN. 
    // If it's not in 'warehouse_in', we can't ship it.
    // =========================================================
    // Note: Assuming warehouse_in connects via transfer_ticket_id or unique_no. 
    // If your warehouse_in table structure is simple, this ensures validity.
    /* $stmt_in = $pdo->prepare("SELECT 1 FROM warehouse_in WHERE unique_no = ?");
    $stmt_in->execute([$unique_no]);
    if (!$stmt_in->fetch()) {
         echo json_encode(['success' => false, 'message' => "Error: Ticket $unique_no has not been scanned INTO the warehouse yet."]);
         exit;
    } 
    */

    // =========================================================
    // 3. FETCH TICKET DETAILS
    // Strict lookup: Must match Unique No AND ERP Code
    // =========================================================
    $stmt = $pdo->prepare("
        SELECT 
            tt.ticket_id, tt.unique_no, tt.created_at, tt.erp_code_FG, tt.part_no_FG, 
            tt.part_name, tt.model, tt.prod_area, tt.quantity, tt.released_by,
            m.part_no_B, m.erp_code_B
        FROM transfer_tickets tt
        LEFT JOIN master m ON tt.erp_code_FG = m.erp_code_FG
        WHERE tt.unique_no = ? AND tt.erp_code_FG = ?
    ");
    $stmt->execute([$unique_no, $erp_code_fg]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => "No ticket found for ID: $unique_no matching ERP: $erp_code_fg"]);
        exit;
    }
    
    // 4. MANPOWER NAME LOGIC (Kept your existing good logic)
    $manpower_display = $ticket['released_by']; 
    $emp_ids = array_map('trim', explode(',', $ticket['released_by']));
    if (!empty($emp_ids)) {
        $placeholders = str_repeat('?,', count($emp_ids) - 1) . '?';
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

    // 5. Return Data
    echo json_encode([
        'success' => true,
        'data' => [
            'unique_no' => htmlspecialchars($ticket['unique_no']),
            'prod_date' => date('d/m/Y', strtotime($ticket['created_at'])),
            'erp_code_FG' => htmlspecialchars($ticket['erp_code_FG']),
            'part_no_FG' => htmlspecialchars($ticket['part_no_FG']),
            'erp_code_B' => htmlspecialchars($ticket['erp_code_B']),
            'part_no_B' => htmlspecialchars($ticket['part_no_B']),
            'part_name' => htmlspecialchars($ticket['part_name']),
            'model' => htmlspecialchars($ticket['model']),
            'prod_area' => htmlspecialchars($ticket['prod_area']),
            'quantity' => (int)$ticket['quantity'],
            'released_by_ids' => htmlspecialchars($ticket['released_by']),
            'released_by_display' => htmlspecialchars($manpower_display)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Fetch Ticket Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Check logs.']);
}
?>