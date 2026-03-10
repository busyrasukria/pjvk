<?php
require_once 'db.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kuala_Lumpur');

$action = $_GET['action'] ?? 'get_data'; 
$page = (int)($_GET['page'] ?? 1);
$limit = 10; 
$offset = ($page - 1) * $limit;

// Filters
$search_term = trim($_GET['search'] ?? '');
$filter_supplier = trim($_GET['supplier'] ?? ''); 
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

if (empty($filter_supplier)) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'No supplier selected']);
    exit;
}

// --- 1. UPDATE: ADD MASZ TO THIS LIST ---
$tables = [
    'YTEC'  => 'receiving_log_ytec',
    'MAZDA' => 'receiving_log_mazda',
    'MARZ'  => 'receiving_log_marz',
    'MASZ'  => 'receiving_log_marz' // <--- ADDED THIS LINE
];
$table_name = $tables[$filter_supplier] ?? null;

if (!$table_name) {
    echo json_encode(['success' => false, 'data' => [], 'message' => 'Invalid Supplier']); // Added message for better debugging
    exit;
}

// --- BASE COLUMNS ---
$columns = "l.id, l.scan_time, l.part_no, l.erp_code, l.part_name, l.qty, l.input_type, l.generated_qr, l.seq_no";

if ($filter_supplier === 'YTEC') {
    $columns .= ", l.job_no, l.inv_no"; 
    $job_col = "job_no";
} else {
    // Mazda/Marz/Masz use 'scat_no'
    $columns .= ", l.scat_no as job_no, l.serial"; 
    $job_col = "scat_no"; 
}

$sql = "SELECT $columns FROM `$table_name` l";

$conditions = [];
$params = [];

// 1. ENHANCED SEARCH CONDITION
if ($search_term) {
    // Strip prefix if user typed 'R123' or 'M123' to search purely by ID number
    $id_search = preg_replace('/[^0-9]/', '', $search_term);
    
    $search_clauses = [
        "l.part_no LIKE ?",
        "l.erp_code LIKE ?",
        "l.part_name LIKE ?",
        "l.$job_col LIKE ?", // job_no or scat_no
        "l.seq_no LIKE ?"
    ];
    
    // Add params for text fields
    for($i=0; $i<5; $i++) $params[] = "%$search_term%";

    // Add ID search if input was numeric
    if(is_numeric($id_search) && $id_search !== '') {
        $search_clauses[] = "l.id = ?";
        $params[] = $id_search;
    }

    $conditions[] = "(" . implode(" OR ", $search_clauses) . ")";
}

// 2. DATE RANGE CONDITION
if ($start_date && $end_date) {
    $conditions[] = "DATE(l.scan_time) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif ($start_date) {
    $conditions[] = "DATE(l.scan_time) >= ?";
    $params[] = $start_date;
} elseif ($end_date) {
    $conditions[] = "DATE(l.scan_time) <= ?";
    $params[] = $end_date;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY l.scan_time DESC";

// Count Logic for Pagination
$count_sql = "SELECT COUNT(*) FROM `$table_name` l";
if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(' AND ', $conditions);
}
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();

// --- CSV EXPORT LOGIC ---
if ($action === 'download_csv') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = $filter_supplier.'_receiving_'.date('Y-m-d').'.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $output = fopen('php://output', 'w');
    
    // --- 2. UPDATE: PREFIX LOGIC FOR MASZ ---
    // Handle prefixes: Mazda=M, Marz/Masz=MZ, Ytec=R
    $prefix = ($filter_supplier === 'MAZDA') ? 'M' : 
             (($filter_supplier === 'MARZ' || $filter_supplier === 'MASZ') ? 'MZ' : 'R');

    if ($filter_supplier === 'YTEC') {
        // Header: ID, Date/Time, Job No, Part No, ERP Code, Seq No, Part Name, Qty, Invoice No
        fputcsv($output, ['ID', 'Date/Time', 'Job No', 'Part No', 'ERP Code', 'Seq No', 'Part Name', 'Qty', 'Invoice No']);
        
        foreach ($results as $row) {
            fputcsv($output, [
                $prefix . $row['id'],
                date('d/m/y H:i:s', strtotime($row['scan_time'])),
                $row['job_no'],
                $row['part_no'],
                $row['erp_code'],
                $row['seq_no'],
                $row['part_name'],
                $row['qty'],
                $row['inv_no'] ?? '-'
            ]);
        }
    } 
    // --- 3. UPDATE: CONDITION FOR MASZ IN CSV ---
    elseif ($filter_supplier === 'MAZDA' || $filter_supplier === 'MARZ' || $filter_supplier === 'MASZ') {
        // Header: ID, Date/Time, Scat No, Part No, ERP Code, Seq No, Part Name, Qty, Serial
        fputcsv($output, ['ID', 'Date/Time', 'Scat No', 'Part No', 'ERP Code', 'Seq No', 'Part Name', 'Qty', 'Serial']);
        
        foreach ($results as $row) {
            fputcsv($output, [
                $prefix . $row['id'],
                date('d/m/y H:i:s', strtotime($row['scan_time'])),
                $row['job_no'], // This holds Scat No due to alias in query
                $row['part_no'],
                $row['erp_code'],
                $row['seq_no'],
                $row['part_name'],
                $row['qty'],
                $row['serial'] ?? '-'
            ]);
        }
    }
    
    fclose($output);
    exit;

} else {
    // --- JSON RESPONSE ---
    $sql .= " LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format Date for JSON display
    foreach($data as &$row) { 
        $row['scan_time'] = date('d/m/y H:i:s', strtotime($row['scan_time']));
        $row['supplier_type'] = $filter_supplier; 
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'total_records' => $total_records,
        'total_pages' => ceil($total_records / $limit),
        'current_page' => $page
    ]);
}
?>