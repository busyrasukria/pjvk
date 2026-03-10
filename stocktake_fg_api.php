<?php
// 1. SYSTEM SETUP
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

// 2. INPUT HANDLING
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON Input']);
    exit;
}

$action = $data['action'] ?? '';

try {
    // ============================================================
    // ACTION: SUBMIT SCAN (QR Code)
    // ============================================================
    if ($action === 'submit_scan') {
        $qr_raw = trim($data['qr_data'] ?? '');
        
        if (empty($qr_raw)) {
            echo json_encode(['success' => false, 'message' => 'Scanner sent empty data']);
            exit;
        }

        // PARSE QR CODE (Format: Date|TicketID|ERP|ReleasedBy|Qty)
        $parts = explode('|', $qr_raw);
        
        if (count($parts) < 2) {
            echo json_encode(['success' => false, 'message' => 'Invalid QR Format: Missing separators']);
            exit;
        }

        $ticket_id   = trim($parts[1] ?? ''); 
        $erp_code    = trim($parts[2] ?? '');
        $released_by = trim($parts[3] ?? '');
        $qty         = (int)($parts[4] ?? 0); 

        if ($ticket_id === '') {
            echo json_encode(['success' => false, 'message' => 'Error: Ticket ID is empty']);
            exit;
        }

        // *** 1. DUPLICATE CHECK (NEW) ***
        // Check if this specific ticket has already been scanned in this session
        $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM stocktake_fg WHERE tag_id = ?");
        $stmt_dup->execute([$ticket_id]);
        if ($stmt_dup->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'DOUBLE SCAN: Ticket ' . $ticket_id . ' already scanned!']);
            exit;
        }

        // *** 2. WAREHOUSE CHECK (Validation) ***
        // Check if ticket exists in warehouse_in. 
        // We verify both ID and ERP to be sure.
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM warehouse_in WHERE (transfer_ticket_id = ? OR unique_no = ?) AND erp_code_FG = ?");
        $stmt_check->execute([$ticket_id, $ticket_id, $erp_code]);
        $exists = $stmt_check->fetchColumn();

        $status = ($exists > 0) ? 'MATCH' : 'UNMATCH';
        
        // If UNMATCH, we still accept it (Don't Reject), just mark it red.
        $message = ($status === 'MATCH') ? "Verified: $ticket_id" : "Ticket $ticket_id not found in System (UNMATCH)";

        // *** 3. INSERT RECORD ***
        $stmt_insert = $pdo->prepare("
            INSERT INTO stocktake_fg 
            (tag_id, erp_code, qty, released_by, status, scan_time) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt_insert->execute([$ticket_id, $erp_code, $qty, $released_by, $status]);
        
        echo json_encode([
            'success' => true, 
            'status' => $status,
            'message' => $message,
            'scanned_id' => $ticket_id
        ]);
        exit;
    }

    // ============================================================
    // ACTION: SUBMIT MANUAL (Typed ID)
    // ============================================================
    if ($action === 'submit_manual') {
        $ticket_id = trim($data['ticket_id'] ?? '');

        if (empty($ticket_id)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a Ticket ID']);
            exit;
        }

        // *** 1. DUPLICATE CHECK (NEW) ***
        $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM stocktake_fg WHERE tag_id = ?");
        $stmt_dup->execute([$ticket_id]);
        if ($stmt_dup->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'DOUBLE SCAN: Ticket ' . $ticket_id . ' already counted!']);
            exit;
        }

        // *** 2. WAREHOUSE CHECK ***
        $stmt = $pdo->prepare("SELECT erp_code_FG, quantity, released_by FROM warehouse_in WHERE transfer_ticket_id = ? OR unique_no = ? LIMIT 1");
        $stmt->execute([$ticket_id, $ticket_id]);
        $wh_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $status = ($wh_data) ? 'MATCH' : 'UNMATCH';
        
        $erp = $wh_data['erp_code_FG'] ?? 'UNKNOWN';
        $qty = $wh_data['quantity'] ?? 0;
        $rel = $wh_data['released_by'] ?? 'MANUAL_ENTRY';

        // *** 3. INSERT RECORD ***
        $stmt_insert = $pdo->prepare("
            INSERT INTO stocktake_fg 
            (tag_id, erp_code, qty, released_by, status, scan_time) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt_insert->execute([$ticket_id, $erp, $qty, $rel, $status]);

        echo json_encode([
            'success' => true,
            'status' => $status,
            'message' => ($status === 'MATCH') ? "Manual Match: $ticket_id" : "Manual Entry: $ticket_id (UNMATCH)"
        ]);
        exit;
    }

    // ============================================================
    // ACTION: DELETE SCAN
    // ============================================================
    if ($action === 'delete_scan') {
        $id = (int)($data['log_id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM stocktake_fg WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Record deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database failed to delete']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown Action']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
?>