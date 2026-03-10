<?php
require_once 'db.php';
header('Content-Type: application/json');

// Validate Request
$type = $_GET['type'] ?? ''; // 'location' or 'part'
$query = trim($_GET['query'] ?? '');

if (empty($type) || empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Invalid search parameters.']);
    exit;
}

try {
    // QUERY TARGETING STOCKTAKE_LOG
    // We alias the columns to match what the JavaScript expects (RACKING_LOCATION, PART_NO, etc.)
    $sql = "SELECT 
                TRIM(scanned_location) as RACKING_LOCATION, 
                DATE(receiving_date) as R_DATE, 
                TRIM(part_no) as PART_NO, 
                TRIM(erp_code) as ERP_CODE, 
                TRIM(seq_no) as SEQ_NO, 
                part_name as PART_NAME, 
                SUM(qty) as total_qty, 
                COUNT(*) as box_count,
                DATEDIFF(NOW(), DATE(receiving_date)) as days_in_stock
            FROM stocktake_log ";

    $params = [];

    if ($type === 'location') {
        $sql .= "WHERE scanned_location LIKE ? ";
        $params[] = "%$query%";

    } elseif ($type === 'part') {
        // === STRICT NUMERIC MATCH LOGIC (Same as before) ===
        $sql .= "WHERE ";
        
        // --- ERP CODE ---
        $sql .= "(TRIM(erp_code) = ? OR (TRIM(erp_code) REGEXP '^[0-9]+$' AND TRIM(erp_code) + 0 = ?)) ";
        $params[] = $query;
        $params[] = $query;

        // --- SEQ NO ---
        $sql .= "OR (TRIM(seq_no) = ? OR (TRIM(seq_no) REGEXP '^[0-9]+$' AND TRIM(seq_no) + 0 = ?)) ";
        $params[] = $query;
        $params[] = $query;

        // --- PART NO ---
        $sql .= "OR (TRIM(part_no) = ? OR (TRIM(part_no) REGEXP '^[0-9]+$' AND TRIM(part_no) + 0 = ?)) ";
        $params[] = $query;
        $params[] = $query;
    }

    // Grouping results so we see totals per item/date
    $sql .= "GROUP BY TRIM(scanned_location), TRIM(part_no), TRIM(erp_code), TRIM(seq_no), DATE(receiving_date) 
             ORDER BY scanned_location ASC, DATE(receiving_date) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for the frontend
    foreach ($data as &$row) {
        $row['date_fmt'] = date('d/m/Y', strtotime($row['R_DATE']));
        
        // Keep the FIFO color logic
        if ($row['days_in_stock'] > 60) {
            $row['fifo_status'] = 'critical'; // Red
        } elseif ($row['days_in_stock'] > 30) {
            $row['fifo_status'] = 'warning'; // Orange/Yellow
        } else {
            $row['fifo_status'] = 'fresh'; // Normal
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>