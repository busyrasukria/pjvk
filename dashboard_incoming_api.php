<?php
require_once 'db.php';

// Helper to handle query params
$search = $_GET['search'] ?? '';
$action = $_GET['action'] ?? 'get_data'; 

if ($action !== 'export_csv') {
    header('Content-Type: application/json');
}

try {
    // 1. Aggregate Receiving Data
    $sql_rec = "
        SELECT erp_code, SUM(qty) as total_rec 
        FROM (
            SELECT erp_code, qty FROM receiving_log_ytec
            UNION ALL
            SELECT erp_code, qty FROM receiving_log_mazda
            UNION ALL
            SELECT erp_code, qty FROM receiving_log_marz
            UNION ALL
            SELECT erp_code, qty FROM receiving_old
        ) as combined_rec 
        GROUP BY erp_code
    ";

    // 2. Aggregate Racking In
    $sql_rack = "SELECT ERP_CODE, SUM(RACK_IN) as total_rack FROM racking_in GROUP BY ERP_CODE";

    // 3. Aggregate Unboxing (Racking Out)
    $sql_out = "SELECT ERP_CODE, SUM(RACK_OUT) as total_out FROM unboxing_in GROUP BY ERP_CODE";

    // 3.5 Aggregate Production In 
    // FIXED: Joining stl_order with master_stl to get the erp_code. 
    // Make sure 'part_no' is the correct column that links these two tables together!
    $sql_prod = "
        SELECT ms.erp_code, SUM(so.rec_qty) as total_prod 
        FROM stl_orders so
        JOIN master_stl ms ON so.part_no = ms.part_no 
        WHERE so.status = 'Completed' 
        GROUP BY ms.erp_code
    ";

    // 4. Master Query
    $sql = "
        SELECT 
            m.erp_code, 
            m.stock_desc, 
            m.part_no, 
            m.supplier, 
            m.std_packing, 
            m.seq_number,
            m.model,
            COALESCE(r.total_rec, 0) as receiving_in,
            COALESCE(rk.total_rack, 0) as racking_in,
            COALESCE(u.total_out, 0) as racking_out,
            COALESCE(p.total_prod, 0) as production_in
        FROM master_incoming m
        LEFT JOIN ($sql_rec) r ON m.erp_code = r.erp_code
        LEFT JOIN ($sql_rack) rk ON m.erp_code = rk.ERP_CODE
        LEFT JOIN ($sql_out) u ON m.erp_code = u.ERP_CODE
        LEFT JOIN ($sql_prod) p ON m.erp_code = p.erp_code
        WHERE 1=1
    ";

    // 5. Apply Search
    if (!empty($search)) {
        $term = "%$search%";
        $sql .= " AND (m.erp_code LIKE ? OR m.stock_desc LIKE ? OR m.part_no LIKE ? OR m.seq_number LIKE ? OR m.model LIKE ?)";
        $params = [$term, $term, $term, $term, $term];
    } else {
        $params = [];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Calculate OS Logic & Grand Totals
    $final_data = [];
    $summary = [
        'total_receiving_in'  => 0,
        'total_racking_in'    => 0,
        'total_racking_out'   => 0,
        'total_production_in' => 0,
        'total_os_receiving'  => 0,
        'total_os_ranking'    => 0,
        'total_os_unboxing'   => 0,
        'total_os_overall'    => 0
    ];

    foreach ($raw_data as $row) {
        $rec_in   = (int)$row['receiving_in'];
        $rack_in  = (int)$row['racking_in'];
        $rack_out = (int)$row['racking_out'];
        $prod_in  = (int)$row['production_in'];

        // EXACT FORMULAS APPLIED:
        $os_receiving = $rec_in - $rack_in; 
        $os_ranking   = $rack_in - $rack_out; 
        $os_unboxing  = $prod_in - $rack_in; 
        $os_total     = $os_receiving + $os_ranking; 

        $row['os_receiving'] = $os_receiving;
        $row['os_ranking']   = $os_ranking;
        $row['os_unboxing']  = $os_unboxing;
        $row['os_total']     = $os_total;

        $summary['total_receiving_in']  += $rec_in;
        $summary['total_racking_in']    += $rack_in;
        $summary['total_racking_out']   += $rack_out;
        $summary['total_production_in'] += $prod_in;
        $summary['total_os_receiving']  += $os_receiving;
        $summary['total_os_ranking']    += $os_ranking;
        $summary['total_os_unboxing']   += $os_unboxing;
        $summary['total_os_overall']    += $os_total;

        $final_data[] = $row;
    }

    // 7. Sort by highest OS Total
    usort($final_data, function($a, $b) {
        return $b['os_total'] <=> $a['os_total'];
    });

    // 8. CSV Export Configuration
    if ($action === 'export_csv') {
        $filename = "incoming_dashboard_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Supplier', 'ERP Code', 'Part No', 'Description', 'Model', 'Seq No', 
            'Receiving In', 'Racking In', 'Racking Out', 'Production In',
            'OS Receiving', 'OS Ranking', 'OS Unboxing', 'OS Total'
        ]);

        foreach ($final_data as $row) {
            fputcsv($output, [
                $row['supplier'], $row['erp_code'], $row['part_no'], $row['stock_desc'],
                $row['model'], $row['seq_number'], $row['receiving_in'], $row['racking_in'],
                $row['racking_out'], $row['production_in'], $row['os_receiving'], $row['os_ranking'], 
                $row['os_unboxing'], $row['os_total']
            ]);
        }
        fclose($output);
        exit;
    }

    echo json_encode(['success' => true, 'summary' => $summary, 'data' => $final_data]);

} catch (Exception $e) {
    if ($action !== 'export_csv') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>