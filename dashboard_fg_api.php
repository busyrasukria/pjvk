<?php
require_once 'db.php';
// IMPORTANT for Real-time: Tell browser not to cache this response
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Disable error reporting to output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

try {
    // 1. Fetch Master Data
    $stmt = $pdo->query("SELECT id, part_no_B, erp_code_B, part_no_FG, erp_code_FG, part_description, model, line, std_packing FROM master");
    $masters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Safe Helper Function for Totals
    function getSafeTotals($pdo, $table, $column) {
        try {
            // Optimization: If performance is slow, remove these SHOW TABLES checks in production
            $check = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() == 0) return []; 

            $colCheck = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($colCheck->rowCount() == 0) return [];

            $stmt = $pdo->query("SELECT $column, SUM(quantity) as total FROM `$table` GROUP BY $column");
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            return [];
        }
    }

    // 3. Aggregate Data
    $wh_in_data   = getSafeTotals($pdo, 'warehouse_in', 'erp_code_FG');
    $pne_in_data  = getSafeTotals($pdo, 'pne_warehouse_in', 'erp_code_FG'); 
    $pne_out_data = getSafeTotals($pdo, 'warehouse_out_pne', 'erp_code_FG');
    
    // Fallback for Warehouse Out
    $wh_out_data  = getSafeTotals($pdo, 'warehouse_out', 'erp_code');
    if (empty($wh_out_data)) {
        $wh_out_data = getSafeTotals($pdo, 'warehouse_out', 'erp_code_FG');
    }

    $dashboard_data = [];

    foreach ($masters as $row) {
        $erp = $row['erp_code_FG'];

        $q_wh_in   = (int)($wh_in_data[$erp] ?? 0);
        $q_pne_in  = (int)($pne_in_data[$erp] ?? 0);
        $q_pne_out = (int)($pne_out_data[$erp] ?? 0);
        $q_wh_out  = (int)($wh_out_data[$erp] ?? 0);

        // Formulas
        $os_wh = ($q_wh_in + $q_pne_out) - ($q_wh_out + $q_pne_in);
        $os_pne = ($q_pne_in - $q_pne_out);
        $total_os = $os_wh + $os_pne;

        $dashboard_data[] = [
            'id'          => $row['id'], 
            'model'       => $row['model'],
            'part_no_fg'  => $row['part_no_FG'],
            'erp_code_fg' => $erp,
            'description' => $row['part_description'], 
            'wh_in'       => $q_wh_in,
            'pne_in'      => $q_pne_in,
            'pne_out'     => $q_pne_out,
            'wh_out'      => $q_wh_out,
            'os_wh'       => $os_wh,
            'os_pne'      => $os_pne,
            'total_os'    => $total_os
        ];
    }

    // Sort by Total OS High -> Low
    usort($dashboard_data, function($a, $b) {
        return $b['total_os'] <=> $a['total_os'];
    });

    echo json_encode(['success' => true, 'data' => $dashboard_data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>