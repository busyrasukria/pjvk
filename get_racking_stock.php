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
    $sql = "SELECT 
                TRIM(RACKING_LOCATION) as RACKING_LOCATION, 
                DATE(RECEIVING_DATE) as R_DATE, 
                TRIM(PART_NO) as PART_NO, 
                TRIM(ERP_CODE) as ERP_CODE, 
                TRIM(SEQ_NO) as SEQ_NO, 
                PART_NAME, 
                SUM(RACK_IN) as total_qty, 
                COUNT(*) as box_count,
                DATEDIFF(NOW(), DATE(RECEIVING_DATE)) as days_in_stock
            FROM racking_in ";

    $params = [];

    if ($type === 'location') {
        $sql .= "WHERE RACKING_LOCATION LIKE ? ";
        $params[] = "%$query%";

    } elseif ($type === 'part') {
        // === STRICT NUMERIC MATCH LOGIC ===
        // 1. Exact String Match: Finds "A-1" if you type "A-1".
        // 2. Numeric Match: Finds "001" if you type "1".
        //    Logic: (Column + 0) turns text "001" into number 1.
        //    We utilize REGEXP '^[0-9]+$' to only apply math logic to actual numbers 
        //    (prevents errors or false matches with text like 'A-1').

        $sql .= "WHERE ";
        
        // --- ERP CODE ---
        $sql .= "(TRIM(ERP_CODE) = ? OR (TRIM(ERP_CODE) REGEXP '^[0-9]+$' AND TRIM(ERP_CODE) + 0 = ?)) ";
        $params[] = $query;
        $params[] = $query;

        // --- SEQ NO (The most important one for 001 vs 1) ---
        $sql .= "OR (TRIM(SEQ_NO) = ? OR (TRIM(SEQ_NO) REGEXP '^[0-9]+$' AND TRIM(SEQ_NO) + 0 = ?)) ";
        $params[] = $query;
        $params[] = $query;

        // --- PART NO ---
        $sql .= "OR (TRIM(PART_NO) = ? OR (TRIM(PART_NO) REGEXP '^[0-9]+$' AND TRIM(PART_NO) + 0 = ?)) ";
        $params[] = $query;
        $params[] = $query;
    }

    // Grouping
    $sql .= "GROUP BY TRIM(RACKING_LOCATION), TRIM(PART_NO), TRIM(ERP_CODE), TRIM(SEQ_NO), DATE(RECEIVING_DATE) 
             ORDER BY DATE(RECEIVING_DATE) ASC, RACKING_LOCATION ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for the frontend
    foreach ($data as &$row) {
        $row['date_fmt'] = date('d/m/Y', strtotime($row['R_DATE']));
        
        // FIFO Alert Logic
        if ($row['days_in_stock'] > 60) {
            $row['fifo_status'] = 'critical';
        } elseif ($row['days_in_stock'] > 30) {
            $row['fifo_status'] = 'warning';
        } else {
            $row['fifo_status'] = 'fresh';
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>