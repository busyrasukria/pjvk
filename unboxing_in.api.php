<?php
// Filename: unboxing_in.api.php
header('Content-Type: application/json');
require_once 'db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

function send_json($data) { echo json_encode($data); exit; }

// Capture JSON input (for POST requests) and URL parameters (for GET requests)
$input = json_decode(file_get_contents('php://input'), true);
$action = $_REQUEST['action'] ?? $input['action'] ?? '';

try {
    // 1. SUBMIT SCAN (Unboxing Logic)
    if ($action === 'submit_scan') {
        $raw_qr = trim($_REQUEST['qr_data'] ?? $input['qr_data'] ?? '');
        
        if (empty($raw_qr)) send_json(['success' => false, 'message' => "QR Data is required."]);

        // Parse QR (Format: Part|Date|ID)
        $id_to_process = $raw_qr; 
        if (strpos($raw_qr, '|') !== false) {
            $parts = explode('|', $raw_qr);
            if (count($parts) >= 3) {
                $id_to_process = trim($parts[2]);
            } else {
                send_json(['success' => false, 'message' => "Invalid QR Format. Expected: Part|Date|ID"]);
            }
        }

        // Check Duplicates
        $checkUnbox = $pdo->prepare("SELECT id FROM unboxing_in WHERE ID_CODE = ?");
        $checkUnbox->execute([$id_to_process]);
        if ($checkUnbox->fetch()) {
            send_json(['success' => false, 'message' => "Error: Item ($id_to_process) already Unboxed."]);
        }

        // Check Racking (Must exist)
        $stmtRack = $pdo->prepare("SELECT * FROM racking_in WHERE ID_CODE = ?");
        $stmtRack->execute([$id_to_process]);
        $rackData = $stmtRack->fetch(PDO::FETCH_ASSOC);

        if (!$rackData) {
            send_json(['success' => false, 'message' => "Error: Item ($id_to_process) not found in Racking. Please Rack In first."]);
        }

        // Insert into Unboxing (FIXED: Added DATE_OUT)
        $sql = "INSERT INTO unboxing_in 
                (ID_CODE, RECEIVING_DATE, DATE_OUT, PART_NAME, PART_NO, ERP_CODE, SEQ_NO, RACK_OUT, RACKING_LOCATION)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $rackData['ID_CODE'],
            $rackData['RECEIVING_DATE'],
            // NOW() is handled by SQL, so we don't pass a parameter for it
            $rackData['PART_NAME'],
            $rackData['PART_NO'],
            $rackData['ERP_CODE'],
            $rackData['SEQ_NO'],
            $rackData['RACK_IN'], 
            $rackData['RACKING_LOCATION']
        ]);

        $newId = $pdo->lastInsertId();

        // Return Data
        $responseData = [
            'log_id' => $newId,
            'scan_time_fmt' => date('d/m/Y H:i:s'),
            'ID_CODE' => $rackData['ID_CODE'],
            'rec_date_fmt' => date('d/m/Y', strtotime($rackData['RECEIVING_DATE'])),
            'PART_NAME' => $rackData['PART_NAME'],
            'PART_NO' => $rackData['PART_NO'],
            'ERP_CODE' => $rackData['ERP_CODE'],
            'SEQ_NO' => $rackData['SEQ_NO'],
            'RACK_OUT' => $rackData['RACK_IN'],
            'LOCATION' => $rackData['RACKING_LOCATION']
        ];

        send_json(['success' => true, 'message' => "Unboxed Successfully!", 'data' => $responseData]);
    }

    // 2. GET HISTORY & CSV
    if ($action === 'get_history' || $action === 'export_csv') {
        $search = $_GET['search'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        
        $sql = "SELECT * FROM unboxing_in WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (ID_CODE LIKE ? OR PART_NO LIKE ? OR ERP_CODE LIKE ? OR SEQ_NO LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }

        if (!empty($date_from)) {
            $sql .= " AND DATE(DATE_OUT) >= ?";
            $params[] = $date_from;
        }
        if (!empty($date_to)) {
            $sql .= " AND DATE(DATE_OUT) <= ?";
            $params[] = $date_to;
        }

        // Sort by DATE_OUT (This requires the column to exist)
        $sql .= " ORDER BY DATE_OUT DESC";
        
        if ($action !== 'export_csv') {
            $sql .= " LIMIT 50";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'export_csv') {
            $filename = "unboxing_history_" . date('Y-m-d') . ".csv";
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date/Time Out', 'ID', 'Rec. Date', 'Part Name', 'Part No', 'ERP Code', 'Seq No', 'Rack Out', 'Location']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['DATE_OUT'], $row['ID_CODE'], $row['RECEIVING_DATE'], $row['PART_NAME'],
                    $row['PART_NO'], $row['ERP_CODE'], $row['SEQ_NO'], $row['RACK_OUT'], $row['RACKING_LOCATION']
                ]);
            }
            fclose($output);
            exit;
        }

        // Format for JSON
        foreach ($rows as &$row) {
            // Handle cases where DATE_OUT might be null to prevent errors
            $row['scan_time_fmt'] = $row['DATE_OUT'] ? date('d/m/Y H:i:s', strtotime($row['DATE_OUT'])) : '-';
            $row['rec_date_fmt'] = $row['RECEIVING_DATE'] ? date('d/m/Y', strtotime($row['RECEIVING_DATE'])) : '-';
            $row['log_id'] = $row['id']; 
            $row['LOCATION'] = $row['RACKING_LOCATION'];
        }
        send_json(['success' => true, 'data' => $rows]);
    }

    // 3. DELETE SCAN
    if ($action === 'delete_scan') {
        $id = $_POST['id'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($password !== 'Admin404') send_json(['success' => false, 'message' => "Incorrect Password"]);

        $stmt = $pdo->prepare("DELETE FROM unboxing_in WHERE id = ?");
        $stmt->execute([$id]);
        
        send_json(['success' => true, 'message' => "Record deleted."]);
    }

    // 4. SEARCH RACKING STOCK
    if ($action === 'search_racking_stock') {
        $type = $_GET['type'] ?? '';
        $query = trim($_GET['query'] ?? '');

        if (empty($query)) send_json(['success' => false, 'message' => 'Empty search query']);

        $sql = "SELECT 
                    TRIM(RACKING_LOCATION) as RACKING_LOCATION, 
                    DATE(RECEIVING_DATE) as R_DATE, 
                    TRIM(PART_NO) as PART_NO, 
                    TRIM(ERP_CODE) as ERP_CODE, 
                    TRIM(SEQ_NO) as SEQ_NO, 
                    PART_NAME, 
                    SUM(RACK_IN) as total_qty, 
                    DATEDIFF(NOW(), DATE(RECEIVING_DATE)) as days_in_stock
                FROM racking_in ";

        $params = [];
        if ($type === 'location') {
            $sql .= "WHERE RACKING_LOCATION LIKE ? ";
            $params[] = "%$query%";
        } else {
            $sql .= "WHERE (ERP_CODE LIKE ? OR SEQ_NO LIKE ? OR PART_NO LIKE ?) ";
            $params[] = "%$query%"; $params[] = "%$query%"; $params[] = "%$query%";
        }

        $sql .= "GROUP BY TRIM(RACKING_LOCATION), TRIM(PART_NO), TRIM(ERP_CODE), TRIM(SEQ_NO), DATE(RECEIVING_DATE) 
                 ORDER BY DATE(RECEIVING_DATE) ASC, RACKING_LOCATION ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['date_fmt'] = date('d/m/Y', strtotime($row['R_DATE']));
            if ($row['days_in_stock'] > 60) $row['fifo_status'] = 'critical';
            elseif ($row['days_in_stock'] > 30) $row['fifo_status'] = 'warning';
            else $row['fifo_status'] = 'fresh';
        }

        send_json(['success' => true, 'data' => $data]);
    }

} catch (Exception $e) {
    send_json(['success' => false, 'message' => "Server Error: " . $e->getMessage()]);
}
?>