<?php
// Filename: get_ticket_status.php
require_once 'db.php';
header('Content-Type: application/json');

$unique_no = $_GET['unique_no'] ?? null;

// 1. Validation: Only Unique No is strictly required now
if (empty($unique_no)) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID (Unique No) is required.']);
    exit;
}

try {
    // 2. Get Ticket Details (ALL parts for this Unique No)
    // We removed "AND tt.erp_code_FG = ?" so it fetches everything matching the Unique No.
    $stmt_ticket = $pdo->prepare("
        SELECT 
            tt.ticket_id, tt.unique_no, tt.part_name, tt.model, 
            tt.quantity, tt.released_by, tt.created_at, tt.prod_area,
            tt.part_no_FG, tt.erp_code_FG, 
            m.part_no_B, m.erp_code_B, m.img_path, m.line
        FROM transfer_tickets tt 
        LEFT JOIN master m ON tt.erp_code_FG = m.erp_code_FG
        WHERE tt.unique_no = ?
    ");
    $stmt_ticket->execute([$unique_no]);
    $parts = $stmt_ticket->fetchAll(PDO::FETCH_ASSOC); // Changed fetch() to fetchAll()

    if (!$parts || count($parts) === 0) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    // 3. Get Manpower Images
    // We assume 'released_by' is the same for the whole ticket, so we take it from the first part ($parts[0])
    $manpower_images = [];
    $released_by_str = $parts[0]['released_by'] ?? '';

    if (!empty($released_by_str)) {
        // Split string by " / " or "," or "/"
        $names = preg_split('/[\/]+/', $released_by_str); 
        $names = array_map('trim', $names);
        
        if (!empty($names)) {
            // Create placeholders for SQL IN clause
            $placeholders = str_repeat('?,', count($names) - 1) . '?';
            
            // Check against nickname or name
            $sql_mp = "SELECT name, nickname, img_path, emp_id FROM manpower 
                       WHERE nickname IN ($placeholders) OR name IN ($placeholders)";
            
            // Duplicate array for the two checks (nickname OR name)
            $params = array_merge($names, $names);
            
            $stmt_mp = $pdo->prepare($sql_mp);
            $stmt_mp->execute($params);
            $manpower_images = $stmt_mp->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 4. Get History
    // We use the ticket_id of the FIRST part found to get the history.
    // (Assuming all parts in one unique_no batch move together)
    $representative_ticket_id = $parts[0]['ticket_id'];

    $stmt_log = $pdo->prepare(
        "SELECT status_message, status_code, status_timestamp, scanned_by 
         FROM ticket_status_log 
         WHERE ticket_id = ? 
         ORDER BY status_timestamp DESC"
    );
    $stmt_log->execute([$representative_ticket_id]);
    $history = $stmt_log->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'ticket_details' => $parts,      // Returns an ARRAY of parts
        'manpower_details' => $manpower_images,
        'tracking_history' => $history
    ]);

} catch (PDOException $e) {
    error_log("Get Ticket Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>