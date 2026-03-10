<?php
require_once 'db.php';

// 1. Force Real-Time: Prevent Browser Caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kuala_Lumpur');

$action = $_GET['action'] ?? 'get_stocktake_data';
$page = (int)($_GET['page'] ?? 1);
$limit = 50; 
$offset = ($page - 1) * $limit;

// Filters
$search = trim($_GET['search'] ?? '');
$search_col = $_GET['search_column'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status_filter'] ?? 'all';

// =================================================================================
// 🚀 OPTIMIZATION: REMOVED AUTO-SYNC 
// We rely on the 'status' saved during the scan. This makes loading 100x faster.
// =================================================================================

// 2. BUILD QUERY (Simple & Fast)
// We only select from the log table. No heavy joins.
$sql_base = " FROM stocktake_log s WHERE 1=1 ";
$params = [];

// 3. Search Logic (Optimized for Indexes)
if ($search) {
    // "Starts With" search is faster than "Contains"
    $term_start = "$search%"; 
    $term_any   = "%$search%";

    if ($search_col === 'all') {
        $sql_base .= " AND (s.tag_id LIKE ? OR s.part_no LIKE ? OR s.erp_code LIKE ? OR s.scanned_location LIKE ?)";
        array_push($params, $term_start, $term_start, $term_start, $term_any);
    } else {
        $columnMap = [
            'tag_id' => 's.tag_id',
            'part_no' => 's.part_no',
            'erp_code' => 's.erp_code',
            'seq_no' => 's.seq_no',
            'scanned_location' => 's.scanned_location'
        ];
        $db_col = $columnMap[$search_col] ?? 's.tag_id';
        
        // Use 'Starts With' for ID queries for speed
        $sql_base .= " AND $db_col LIKE ?";
        $params[] = $term_start;
    }
}

// 4. Date Range Logic
if (!empty($date_from) && !empty($date_to)) {
    $sql_base .= " AND DATE(s.scan_time) BETWEEN ? AND ?";
    array_push($params, $date_from, $date_to);
}

// 5. Status Filter Logic (Fast)
if ($status_filter !== 'all') {
    $sql_base .= " AND s.status = ?";
    $params[] = $status_filter;
}

// --- CSV EXPORT ACTION ---
if ($action === 'export_stocktake_csv') {
    header_remove('Content-Type');
    
    // Select the SAVED status directly
    $sql = "SELECT 
                s.scan_time, s.tag_id, s.receiving_date, s.part_name, s.part_no, s.erp_code, s.seq_no, s.qty, s.scanned_location, s.status
            " . $sql_base . " ORDER BY s.scan_time DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Stocktake_Export_'.date('Ymd_His').'.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, ['Scan Time', 'Tag ID', 'Rec. Date', 'Part Name', 'Part No', 'ERP Code', 'Seq No', 'Qty', 'Location', 'Status']);
    
    foreach ($rows as $row) {
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($row['scan_time'])),
            $row['tag_id'],
            $row['receiving_date'],
            $row['part_name'],
            $row['part_no'],
            $row['erp_code'],
            $row['seq_no'],
            $row['qty'],
            $row['scanned_location'],
            $row['status'] 
        ]);
    }
    fclose($output);
    exit;
}

// --- JSON DATA FETCHING ---
try {
    // A. Fast Count
    $count_sql = "SELECT COUNT(s.id) " . $sql_base;
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();

    // B. Fast Data Fetch (Limit & Offset)
    $sql = "SELECT s.* " . $sql_base . " ORDER BY s.scan_time DESC LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($data as &$row) {
        $row['scan_time_fmt'] = date('d/m/Y H:i:s', strtotime($row['scan_time']));
        $row['receiving_date_fmt'] = $row['receiving_date'] ? date('d/m/Y', strtotime($row['receiving_date'])) : '-';
        // Ensure uppercase for badge logic
        $row['status'] = strtoupper($row['status']); 
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'total_pages' => ceil($total_records / $limit),
        'current_page' => $page
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>