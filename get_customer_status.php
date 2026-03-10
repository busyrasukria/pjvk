<?php
// get_customer_status.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
require_once 'db.php';

// 1. Check if we are searching by Specific ID (From the selection list)
$specific_id = isset($_GET['id']) ? trim($_GET['id']) : '';

// 2. Or searching by Filters
$lot_no   = isset($_GET['lot']) ? trim($_GET['lot']) : '';
$msc_code = isset($_GET['msc']) ? trim($_GET['msc']) : '';
$erp_code = isset($_GET['erp']) ? trim($_GET['erp']) : '';

// Validation
if (!$specific_id && !$lot_no) {
    echo json_encode(['success' => false, 'message' => 'Please fill in at least the Lot No.']);
    exit;
}

try {
    // ---------------------------------------------------------
    // BASE QUERY (Added wo.log_id)
    // ---------------------------------------------------------
    $sql = "
        SELECT 
            wo.log_id,
            wo.unique_no, 
            wo.lot_no,
            wo.msc_code,
            COALESCE(mt.ERP_CODE, wo.erp_code) as erp_code_FG, 
            mt.PART_NO as part_no_FG,
            mt.PART_DESCRIPTION as part_name,
            mt.MODEL as model,
            m.img_path,
            wo.scan_timestamp
        FROM warehouse_out wo
        LEFT JOIN master_trip mt ON wo.master_trip_id = mt.id
        LEFT JOIN master m ON wo.erp_code = m.erp_code_FG
    ";

    $params = [];

    // --- LOGIC SPLIT: EXACT ID vs SEARCH FILTERS ---
    
    if ($specific_id) {
        // CASE A: User clicked a specific item (Exact Match)
        $sql .= " WHERE wo.log_id = :id";
        $params[':id'] = $specific_id;
    } else {
        // CASE B: User typed in search box (Filter Match)
        $sql .= " WHERE UPPER(TRIM(wo.lot_no)) = UPPER(:lot)";
        $params[':lot'] = $lot_no;

        if (!empty($msc_code)) {
            $sql .= " AND UPPER(TRIM(wo.msc_code)) = UPPER(:msc)";
            $params[':msc'] = $msc_code;
        }

        if (!empty($erp_code)) {
            $sql .= " AND UPPER(TRIM(wo.erp_code)) = UPPER(:erp)";
            $params[':erp'] = $erp_code;
        }
    }

    $sql .= " ORDER BY wo.log_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------------------
    // LOGIC: Handle Results Count
    // ---------------------------------------------------------

    if (count($results) === 0) {
        echo json_encode(['success' => false, 'message' => "No records found."]);
        exit;
    }

    // If searching by text filters and multiple found -> Return List
    if (!$specific_id && count($results) > 1) {
        echo json_encode([
            'success' => true,
            'is_multiple' => true,
            'items' => $results
        ]);
        exit;
    }

    // Exact match found (either via ID or unique filter)
    $details = $results[0];
    
    // --- FETCH HISTORY & MANPOWER ---
    $history = [];
    $released_by = '';
    $quantity = 0;

    if (!empty($details['unique_no'])) {
        $ticketStmt = $pdo->prepare("SELECT ticket_id, released_by, quantity FROM transfer_tickets WHERE unique_no = :uid");
        $ticketStmt->execute([':uid' => $details['unique_no']]);
        $ticketData = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        if ($ticketData) {
            $released_by = $ticketData['released_by'];
            $quantity = $ticketData['quantity'];
            $ticket_id = $ticketData['ticket_id'];

            $histStmt = $pdo->prepare("
                SELECT status_code, status_message, status_timestamp, scanned_by 
                FROM ticket_status_log 
                WHERE ticket_id = :tid 
                ORDER BY status_timestamp DESC
            ");
            $histStmt->execute([':tid' => $ticket_id]);
            $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Fallback History
    if (empty($history)) {
        $date = isset($details['scan_timestamp']) ? $details['scan_timestamp'] : date('Y-m-d H:i:s');
        $history[] = [
            'status_code' => 'CUSTOMER_OUT',
            'status_message' => 'Scanned Out to Customer (Legacy Data)',
            'status_timestamp' => $date,
            'scanned_by' => 'System'
        ];
    }

    $mpStmt = $pdo->query("SELECT emp_id, name, nickname, img_path FROM manpower");
    $manpower = $mpStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'is_multiple' => false,
        'ticket_details' => array_merge($details, [
            'released_by' => $released_by,
            'quantity' => $quantity
        ]),
        'tracking_history' => $history,
        'manpower_details' => $manpower
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>