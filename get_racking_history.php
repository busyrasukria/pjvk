<?php
require_once 'db.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kuala_Lumpur');

$action = $_GET['action'] ?? 'get_data';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build Query
$sql_base = "FROM racking_in WHERE 1=1";
$params = [];

// 1. Search Filter (ID, Job/Scat, Part, ERP, Seq, Loc, Rack In)
if ($search) {
    $sql_base .= " AND (
        ID_CODE LIKE ? OR 
        PART_NO LIKE ? OR 
        ERP_CODE LIKE ? OR 
        SEQ_NO LIKE ? OR 
        RACKING_LOCATION LIKE ? OR 
        RACK_IN LIKE ? OR
        PART_NAME LIKE ?
    )";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
}

// 2. Date Range Filter
if ($date_from && $date_to) {
    $sql_base .= " AND DATE(DATE_IN) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
} elseif ($date_from) {
    $sql_base .= " AND DATE(DATE_IN) >= ?";
    $params[] = $date_from;
} elseif ($date_to) {
    $sql_base .= " AND DATE(DATE_IN) <= ?";
    $params[] = $date_to;
}

// --- ACTION: CSV EXPORT ---
if ($action === 'export_csv') {
    $sql = "SELECT DATE_IN, ID_CODE, RECEIVING_DATE, PART_NAME, PART_NO, ERP_CODE, SEQ_NO, RACK_IN, RACKING_LOCATION " . $sql_base . " ORDER BY DATE_IN DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="racking_history_'.date('Ymd_His').'.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, ['Date/Time In', 'ID', 'Receiving Date', 'Part Name', 'Part No', 'ERP Code', 'Seq No', 'Rack In', 'Location']);
    
    foreach ($rows as $row) {
        fputcsv($output, [
            date('d/m/Y H:i:s', strtotime($row['DATE_IN'])),
            $row['ID_CODE'],
            date('d/m/Y', strtotime($row['RECEIVING_DATE'])),
            $row['PART_NAME'],
            $row['PART_NO'],
            $row['ERP_CODE'],
            $row['SEQ_NO'],
            $row['RACK_IN'],
            $row['RACKING_LOCATION']
        ]);
    }
    fclose($output);
    exit;
}

// --- ACTION: JSON DATA (For Table) ---
$count_sql = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();

$sql = "SELECT * " . $sql_base . " ORDER BY DATE_IN DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format dates for JSON
foreach($data as &$row) {
    $row['scan_time_fmt'] = date('d/m/Y H:i:s', strtotime($row['DATE_IN']));
    $row['receiving_date_fmt'] = date('d/m/Y', strtotime($row['RECEIVING_DATE']));
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'total_records' => $total_records,
    'total_pages' => ceil($total_records / $limit),
    'current_page' => $page
]);
?>