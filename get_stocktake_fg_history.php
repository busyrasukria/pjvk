<?php
require_once 'db.php';
header("Cache-Control: no-cache, no-store, must-revalidate");
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_stocktake_data';
$status_filter = $_GET['status_filter'] ?? 'all';

// *** FIX: Corrected table name from 'stocktake_log' to 'stocktake_fg' ***
$sql_base = " FROM stocktake_fg s WHERE 1=1 ";
$params = [];

// Apply Filter
if ($status_filter !== 'all') {
    $sql_base .= " AND s.status = ? ";
    $params[] = $status_filter;
}

// --- CSV EXPORT ---
if ($action === 'export_stocktake_csv') {
    $sql = "SELECT scan_time, tag_id, erp_code, released_by, qty, status " . $sql_base . " ORDER BY scan_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Stocktake_Export_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Scan Time', 'Ticket ID', 'ERP Code', 'Released By', 'Qty', 'Status']);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// --- JSON FETCH ---
try {
    $sql = "SELECT * " . $sql_base . " ORDER BY scan_time DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for JS
    foreach($data as &$row) {
        $row['scan_time_fmt'] = date('d/m/Y H:i:s', strtotime($row['scan_time']));
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>