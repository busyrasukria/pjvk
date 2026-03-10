<?php
header('Content-Type: application/json');
require_once 'db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

function send_json($data) { echo json_encode($data); exit; }

// Helper: Convert DD-MM-YYYY to YYYY-MM-DD
function convert_date($dateStr) {
    // Try to create date from format d-m-Y (e.g., 18-11-2025)
    $d = DateTime::createFromFormat('d-m-Y', trim($dateStr));
    if ($d && $d->format('d-m-Y') === trim($dateStr)) {
        return $d->format('Y-m-d');
    }
    // Fallback if already in Y-m-d or other format
    return date('Y-m-d', strtotime($dateStr)); 
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_POST['action'] ?? $input['action'] ?? '';

try {
    // --- DELETE SCAN LOGIC ---
    if ($action === 'delete_scan') {
        $log_id = $input['log_id'] ?? '';
        $password = $input['password'] ?? '';
        $admin_password = "Admin404"; 

        if (empty($log_id)) send_json(['success' => false, 'message' => "Invalid ID."]);
        if ($password !== $admin_password) send_json(['success' => false, 'message' => "Incorrect Password."]);

        $stmt = $pdo->prepare("DELETE FROM racking_in WHERE id = ?");
        $stmt->execute([$log_id]);

        if ($stmt->rowCount() > 0) {
            send_json(['success' => true, 'message' => "Record deleted successfully."]);
        } else {
            send_json(['success' => false, 'message' => "Record not found or already deleted."]);
        }
    }
    
    // --- SUBMIT SCAN LOGIC ---
    if ($action === 'submit_scan') {
        
        $qr_data = trim($input['qr_data'] ?? '');
        $location = strtoupper(trim($input['racking_location'] ?? 'MANUAL'));
        
        if (empty($qr_data)) send_json(['success' => false, 'message' => "QR Data is empty."]);

        // 1. PARSE THE QR CODE
        // Format expected: PART NO|DATE|ID (e.g. BDNF34846|18-11-2025|R57486J451)
        $parts = explode('|', $qr_data);

        if (count($parts) < 3) {
            send_json(['success' => false, 'message' => "Invalid QR Format. Expected: PART|DATE|ID"]);
        }

        $part_no_qr = trim($parts[0]);
        $date_qr    = trim($parts[1]);
        $id_qr      = trim($parts[2]);

        // 2. CHECK DUPLICATE IN RACKING
        $check = $pdo->prepare("SELECT id, RACKING_LOCATION FROM racking_in WHERE ID_CODE = ?");
        $check->execute([$id_qr]);
        $existing = $check->fetch();

        if ($existing) {
            send_json([
                'success' => false, 
                'message' => "Duplicate! Tag ($id_qr) is already at " . $existing['RACKING_LOCATION']
            ]);
        }

        // 3. RETRIEVE DETAILS FROM MASTER_INCOMING TABLE
        // Columns from your image: stock_desc, erp_code, seq_number, std_packing
        $stmtMaster = $pdo->prepare("SELECT stock_desc, erp_code, seq_number, std_packing FROM master_incoming WHERE part_no = ? LIMIT 1");
        $stmtMaster->execute([$part_no_qr]);
        $masterData = $stmtMaster->fetch(PDO::FETCH_ASSOC);

        // Defaults if not found in master_incoming
        $part_name = $masterData['stock_desc'] ?? 'UNKNOWN PART';
        $erp_code  = $masterData['erp_code'] ?? 'N/A';
        $seq_no    = $masterData['seq_number'] ?? 'N/A';
        $qty       = $masterData['std_packing'] ?? 1; // Used std_packing as the Rack In quantity

        // Convert Date format
        $receiving_date = convert_date($date_qr);

        // 4. INSERT INTO RACKING_IN
        $sql_ins = "INSERT INTO racking_in 
                    (ID_CODE, RECEIVING_DATE, DATE_IN, PART_NAME, PART_NO, ERP_CODE, SEQ_NO, RACK_IN, RACKING_LOCATION) 
                    VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
        
        $stmt_ins = $pdo->prepare($sql_ins);
        $stmt_ins->execute([
            $id_qr,             // ID from QR
            $receiving_date,    // Date from QR
            $part_name,         // From master_incoming (stock_desc)
            $part_no_qr,        // From QR
            $erp_code,          // From master_incoming (erp_code)
            $seq_no,            // From master_incoming (seq_number)
            $qty,               // From master_incoming (std_packing)
            $location           // From User Input
        ]);

        // 5. RETURN DATA FOR TABLE
        $newId = $pdo->lastInsertId();
        $formatted_row = [
            'log_id' => $newId,
            'scan_time' => date('d/m/Y H:i:s'),
            'unique_no' => $id_qr,
            'receiving_date' => date('d/m/Y', strtotime($receiving_date)),
            'part_name' => $part_name,
            'part_no' => $part_no_qr,
            'erp_code' => $erp_code,
            'seq_no' => $seq_no,
            'rack_in' => $qty,
            'racking_location' => $location
        ];

        send_json(['success' => true, 'message' => "Success: $part_no_qr Racked", 'scanData' => $formatted_row]);
    }

    send_json(['success' => false, 'message' => 'Invalid Action']);

} catch (Exception $e) {
    send_json(['success' => false, 'message' => "Server Error: " . $e->getMessage()]);
}
?>