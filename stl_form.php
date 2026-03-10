<?php
// stl_form.php - Modern 2-Step Digital STL Workflow

// --- PHPMailer Namespaces ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Load PHPMailer (Ensure the PHPMailer folder is uploaded to your server!) ---
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

require_once 'db.php';
session_start();

// =====================================================================================
// NEW: AJAX LIVE POKA-YOKE VALIDATION (Checks if the scanned tag belongs to the order)
// =====================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'validate_tag') {
    header('Content-Type: application/json');
    $tag = trim($_POST['tag'] ?? '');
    $order_id = $_POST['order_id'] ?? '';
    
    // 1. Check if the Tag ID exists in the racking_in table
    // (Ensure your table has columns named ERP_CODE and QTY)
    $stmt = $pdo->prepare("SELECT ERP_CODE, QTY FROM racking_in WHERE ID_CODE = ?");
    $stmt->execute([$tag]);
    $box = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$box) {
        echo json_encode(['success' => false, 'message' => "Tag ID not found in the racking system."]);
        exit;
    }

    $erp_code = $box['ERP_CODE'] ?? $box['erp_code'] ?? '';
    $qty = $box['QTY'] ?? $box['qty'] ?? 0;

    // 2. Check if this part's ERP is actually required for this Order's FG Code
    $stmt2 = $pdo->prepare("SELECT model, line, fg_code, variance FROM stl_orders WHERE id = ?");
    $stmt2->execute([$order_id]);
    $order = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $line_cond = ($order['line'] == '5') ? "(line1 = ? OR UPPER(area) IN ('NW', 'PNE'))" : "(line1 = ? AND UPPER(area) NOT IN ('NW', 'PNE'))";
        
        if (!empty($order['variance'])) {
            $stmt3 = $pdo->prepare("SELECT id FROM master_stl WHERE model=? AND $line_cond AND (fg_code=? OR sub_assy=?) AND variance=? AND erp_code=?");
            $stmt3->execute([$order['model'], $order['line'], $order['fg_code'], $order['fg_code'], $order['variance'], $erp_code]);
        } else {
            $stmt3 = $pdo->prepare("SELECT id FROM master_stl WHERE model=? AND $line_cond AND (fg_code=? OR sub_assy=?) AND erp_code=?");
            $stmt3->execute([$order['model'], $order['line'], $order['fg_code'], $order['fg_code'], $erp_code]);
        }
        
        if ($stmt3->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => "Part {$erp_code} is NOT required for FG Code {$order['fg_code']}."]);
            exit;
        }
    }

    echo json_encode(['success' => true, 'erp' => $erp_code, 'qty' => $qty]);
    exit;
}
// =====================================================================================

date_default_timezone_set('Asia/Kuala_Lumpur');
$current_day = date('l'); 
$current_date = date('d M Y'); 

// --- TELEGRAM HELPER FUNCTION ---
function sendTelegramMessage($chat_id, $message) {
    $botToken = ""; 
    $website = "" . $botToken; 
    
    $params = [
        'chat_id' => $chat_id, 
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init($website . '/sendMessage');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// --- SIGNATURE IMAGE SAVER ---
function saveSignatureToFile($base64_string, $prefix) {
    if (empty($base64_string) || strpos($base64_string, 'data:image') === false) {
        return ''; 
    }
    $upload_dir = 'uploads/signatures/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $image_parts = explode(";base64,", $base64_string);
    $image_type_aux = explode("image/", $image_parts[0]);
    $image_type = $image_type_aux[1] ?? 'png';
    $image_base64 = base64_decode($image_parts[1]);
    
    $file_name = $prefix . '_' . time() . '_' . uniqid() . '.' . $image_type;
    $file_path = $upload_dir . $file_name;
    
    file_put_contents($file_path, $image_base64);
    return $file_path;
}

// --- EXCEL DOCUMENT BUILDER (For Email) ---
function buildExcelDocument($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT * FROM stl_orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return '';

    $stmt_sup = $pdo->prepare("SELECT * FROM stl_supply WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_sup->execute([$order_id]);
    $supply = $stmt_sup->fetch(PDO::FETCH_ASSOC);

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $uri_parts = explode('/', $_SERVER['REQUEST_URI']);
    array_pop($uri_parts); 
    $base_url = $protocol . "://" . $host . implode('/', $uri_parts) . "/";

    $req_name = $order['requested_by'] ? strtoupper($order['requested_by']) : 'N/A';
    $req_time = $order['requested_at'] ? date('d-M-Y h:i A', strtotime($order['requested_at'])) : 'N/A';
    $req_shift = $order['shift'] ? strtoupper($order['shift']) : 'N/A';
    $req_verify = $order['verified_by'] ? strtoupper($order['verified_by']) : 'N/A';
    $req_sig = (!empty($order['req_signature']) && strpos($order['req_signature'], 'data:image') === false) ? '<img src="'.$base_url.$order['req_signature'].'" width="100" height="35">' : '';

    $sup_name = $supply['supplied_by'] ?? 'N/A';
    $sup_time = isset($supply['supplied_at']) ? date('d-M-Y h:i A', strtotime($supply['supplied_at'])) : 'N/A';
    $sup_shift = $supply['shift'] ?? 'N/A';
    $sup_verify = $supply['verified_by'] ?? 'N/A';
    $sup_sig = (!empty($supply['sup_signature']) && strpos($supply['sup_signature'], 'data:image') === false) ? '<img src="'.$base_url.$supply['sup_signature'].'" width="100" height="35">' : '';

    $rec_name = $order['received_by'] ? strtoupper($order['received_by']) : 'N/A';
    $rec_time = $order['received_at'] ? date('d-M-Y h:i A', strtotime($order['received_at'])) : 'N/A';
    $rec_shift = isset($order['recv_shift']) && $order['recv_shift'] ? strtoupper($order['recv_shift']) : 'N/A';
    $rec_verify = isset($order['recv_verified_by']) && $order['recv_verified_by'] ? strtoupper($order['recv_verified_by']) : 'N/A';
    $rec_sig = (!empty($order['recv_signature']) && strpos($order['recv_signature'], 'data:image') === false) ? '<img src="'.$base_url.$order['recv_signature'].'" width="100" height="35">' : '';

    $date_fmt = date('d-M-Y', strtotime($order['requested_at']));
    $day_fmt = strtoupper(date('l', strtotime($order['requested_at'])));
    $var_fmt = $order['variance'] ? $order['variance'] : 'N/A';

    $csv_line_cond = ($order['line'] == '5') ? "(line1 = ? OR UPPER(area) IN ('NW', 'PNE'))" : "(line1 = ? AND UPPER(area) NOT IN ('NW', 'PNE'))";
    $stmt_parts = $pdo->prepare("SELECT * FROM master_stl WHERE model=? AND $csv_line_cond AND (fg_code=? OR sub_assy=?) AND variance=? ORDER BY stock_desc ASC");
    $stmt_parts->execute([$order['model'], $order['line'], $order['fg_code'], $order['fg_code'], $order['variance']]);
    $parts = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);

    $req_data = json_decode($order['req_qty'] ?? '{}', true) ?? [];
    $rec_data = json_decode($order['rec_qty'] ?? '{}', true) ?? [];
    $sup_data = $supply ? (json_decode($supply['sup_qty'] ?? '{}', true) ?? []) : [];

    ob_start();
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><style> td { border: .5pt solid windowtext; vertical-align: middle; } </style></head><body>';
    echo '<table style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px;">';
    echo '<tr><td colspan="4" style="border:none;"></td><td colspan="4" style="border:none; font-size: 18px; font-weight: bold; text-align: center;">STOCK TRANSFER LIST (STL DIGITAL)</td><td colspan="4" style="border:none; text-align: right; font-weight: bold;">REV : 01<br>FR-ML-0007</td></tr>';
    echo '<tr><td colspan="12" style="border:none; height: 10px;"></td></tr>';
    echo '<tr style="background-color: #d9d9d9; font-weight: bold; text-align: center; height: 25px;"><td colspan="2">DEPT</td><td colspan="2">NAME</td><td colspan="2">SIGN</td><td colspan="2">TIME</td><td colspan="2">SHIFT</td><td colspan="2">VERIFY BY</td></tr>';
    echo '<tr style="text-align: center; height: 40px;"><td colspan="2" style="font-weight:bold; background-color: #f2f2f2;">PROD (REQUEST)</td><td colspan="2">'.$req_name.'</td><td colspan="2">'.$req_sig.'</td><td colspan="2">'.$req_time.'</td><td colspan="2">'.$req_shift.'</td><td colspan="2">'.$req_verify.'</td></tr>';
    echo '<tr style="text-align: center; height: 40px;"><td colspan="2" style="font-weight:bold; background-color: #f2f2f2;">MPL (SUPPLY)</td><td colspan="2">'.$sup_name.'</td><td colspan="2">'.$sup_sig.'</td><td colspan="2">'.$sup_time.'</td><td colspan="2">'.$sup_shift.'</td><td colspan="2">'.$sup_verify.'</td></tr>';
    echo '<tr style="text-align: center; height: 40px;"><td colspan="2" style="font-weight:bold; background-color: #f2f2f2;">PROD (RECEIVE)</td><td colspan="2">'.$rec_name.'</td><td colspan="2">'.$rec_sig.'</td><td colspan="2">'.$rec_time.'</td><td colspan="2">'.$rec_shift.'</td><td colspan="2">'.$rec_verify.'</td></tr>';
    echo '<tr><td colspan="12" style="border:none; height: 15px;"></td></tr>';
    echo '<tr style="background-color: #d9d9d9; font-weight: bold; text-align: center;"><td colspan="2">DATE</td><td colspan="2">DAY</td><td colspan="2">MODEL</td><td colspan="2">LINE</td><td colspan="2">FG CODE</td><td colspan="2">VARIANCE</td></tr>';
    echo '<tr style="font-weight: bold; text-align: center; font-size: 13px;"><td colspan="2">'.$date_fmt.'</td><td colspan="2">'.$day_fmt.'</td><td colspan="2" style="color:#203764;">'.$order['model'].'</td><td colspan="2" style="color:#203764;">'.$order['line'].'</td><td colspan="2" style="color:#203764;">'.$order['fg_code'].'</td><td colspan="2">'.$var_fmt.'</td></tr>';
    echo '<tr><td colspan="12" style="border:none; height: 10px;"></td></tr>';
    echo '<tr><td colspan="12" style="text-align:left; font-size:16px; font-weight:black; border:none;">TRIP NO: <span style="color:#e11d48;">TRIP '.$order['trip_number'].'</span></td></tr>';
    echo '<tr style="background-color: #203764; color: white; font-weight: bold; text-align: center; height:30px;"><td style="width: 40px;">NO</td><td style="width: 90px;">IMAGE</td><td style="width: 250px;">PART NAME</td><td style="width: 120px;">PART NO</td><td style="width: 100px;">ERP CODE</td><td style="width: 90px;">STATION</td><td style="width: 80px;">USAGE</td><td style="width: 90px;">STD PACK</td><td style="width: 90px;">STD/TRIP</td><td style="background-color: #00b050; color: white; width: 90px;">REQ QTY</td><td style="background-color: #ffc000; color: black; width: 90px;">SUP QTY</td><td style="background-color: #0070c0; color: white; width: 90px;">REC QTY</td></tr>';
    
    $bil = 1;
    foreach ($parts as $p) {
        $pid = (string)$p['id'];
        $r = $req_data[$pid] ?? 0; $s = $sup_data[$pid] ?? 0; $rc = $rec_data[$pid] ?? 0;
        $img_tag = !empty($p['img']) ? '<img src="'.$base_url.$p['img'].'" width="70" height="70" style="margin: 0 auto; display: block;">' : '<span style="color:#ccc; font-size:9px;">NO IMG</span>';
        $qty_style = ($r != $s || $s != $rc) ? 'background-color: #fff2cc;' : '';
        echo '<tr style="'.$qty_style.'" height="85">';
        echo '<td style="text-align: center;">'.$bil++.'</td>';
        echo '<td width="90" height="85" style="text-align: center; vertical-align: middle; padding: 5px;">'.$img_tag.'</td>';
        echo '<td><strong>'.htmlspecialchars($p['stock_desc']).'</strong></td>';
        echo '<td style="text-align: center;">'.htmlspecialchars($p['part_no']).'</td>';
        echo '<td style="text-align: center;">'.htmlspecialchars($p['erp_code']).'</td>';
        echo '<td style="text-align: center;">'.htmlspecialchars($p['area']).'</td>';
        echo '<td style="text-align: center; font-weight: bold;">'.$p['usage'].'</td>';
        echo '<td style="text-align: center;">'.$p['std_pack'].'</td>';
        echo '<td style="text-align: center; font-weight: bold;">'.$p['std_pack_trip'].'</td>';
        echo '<td style="text-align: center; font-weight: bold; font-size: 14px; color: #008f40;">'.$r.'</td>';
        echo '<td style="text-align: center; font-weight: bold; font-size: 14px; color: #d99600;">'.$s.'</td>';
        echo '<td style="text-align: center; font-weight: bold; font-size: 14px; color: #005a9e;">'.$rc.'</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    return ob_get_clean();
}

// --- EMAIL SENDER (Using Resend SMTP API) ---
function sendEmailWithExcel($to_emails, $subject, $body, $filename, $html_content) {
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug  = 0; 
        $mail->isSMTP();
        $mail->Host       = ''; 
        $mail->SMTPAuth   = ;
        $mail->Username   = ''; 
        $mail->Password   = ''; 
        $mail->SMTPSecure = ''; 
        $mail->Port       = ; 

        $mail->setFrom('stl@warehousepjvk.xyz', 'PEPS-JV STL');
        
        $emails = explode(',', $to_emails);
        foreach($emails as $email) {
            if (trim($email) !== '') {
                $mail->addAddress(trim($email));
            }
        }

        $mail->addStringAttachment($html_content, $filename, 'base64', 'application/vnd.ms-excel');
        $mail->isHTML(false); 
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        
    } catch (\Exception $e) {
        error_log("Resend Mail Error: {$mail->ErrorInfo}");
    }
}

// --- EXCEL (XLS) STYLED EXPORT LOGIC ---
if (isset($_GET['export_csv'])) {
    $oid = $_GET['export_csv'];
    $stmt = $pdo->prepare("SELECT * FROM stl_orders WHERE id = ?");
    $stmt->execute([$oid]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $filename = 'STL_Record_Trip_' . $order['trip_number'] . '_' . date('Ymd') . '.xls';
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $stmt_sup = $pdo->prepare("SELECT * FROM stl_supply WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_sup->execute([$oid]);
        $supply = $stmt_sup->fetch(PDO::FETCH_ASSOC);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $uri_parts = explode('/', $_SERVER['REQUEST_URI']);
        array_pop($uri_parts); 
        $base_url = $protocol . "://" . $host . implode('/', $uri_parts) . "/";

        $req_name = $order['requested_by'] ? strtoupper($order['requested_by']) : 'N/A';
        $req_time = $order['requested_at'] ? date('d-M-Y h:i A', strtotime($order['requested_at'])) : 'N/A';
        $req_shift = $order['shift'] ? strtoupper($order['shift']) : 'N/A';
        $req_verify = $order['verified_by'] ? strtoupper($order['verified_by']) : 'N/A';
        $req_sig = (!empty($order['req_signature']) && strpos($order['req_signature'], 'data:image') === false) ? '<img src="'.$base_url.$order['req_signature'].'" width="100" height="35">' : '';

        $sup_name = $supply['supplied_by'] ?? 'N/A';
        $sup_time = isset($supply['supplied_at']) ? date('d-M-Y h:i A', strtotime($supply['supplied_at'])) : 'N/A';
        $sup_shift = $supply['shift'] ?? 'N/A';
        $sup_verify = $supply['verified_by'] ?? 'N/A';
        $sup_sig = (!empty($supply['sup_signature']) && strpos($supply['sup_signature'], 'data:image') === false) ? '<img src="'.$base_url.$supply['sup_signature'].'" width="100" height="35">' : '';

        $rec_name = $order['received_by'] ? strtoupper($order['received_by']) : 'N/A';
        $rec_time = $order['received_at'] ? date('d-M-Y h:i A', strtotime($order['received_at'])) : 'N/A';
        $rec_shift = isset($order['recv_shift']) && $order['recv_shift'] ? strtoupper($order['recv_shift']) : 'N/A';
        $rec_verify = isset($order['recv_verified_by']) && $order['recv_verified_by'] ? strtoupper($order['recv_verified_by']) : 'N/A';
        $rec_sig = (!empty($order['recv_signature']) && strpos($order['recv_signature'], 'data:image') === false) ? '<img src="'.$base_url.$order['recv_signature'].'" width="100" height="35">' : '';

        $date_fmt = date('d-M-Y', strtotime($order['requested_at']));
        $day_fmt = strtoupper(date('l', strtotime($order['requested_at'])));
        $var_fmt = $order['variance'] ? $order['variance'] : 'N/A';
        
        $csv_line_cond = ($order['line'] == '5') 
            ? "(line1 = ? OR UPPER(area) IN ('NW', 'PNE'))" 
            : "(line1 = ? AND UPPER(area) NOT IN ('NW', 'PNE'))";

        $stmt = $pdo->prepare("SELECT * FROM master_stl WHERE model=? AND $csv_line_cond AND (fg_code=? OR sub_assy=?) AND variance=? ORDER BY stock_desc ASC");
        $stmt->execute([$order['model'], $order['line'], $order['fg_code'], $order['fg_code'], $order['variance']]);
        $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $req_data = json_decode($order['req_qty'] ?? '{}', true) ?? [];
        $rec_data = json_decode($order['rec_qty'] ?? '{}', true) ?? [];
        $sup_data = $supply ? (json_decode($supply['sup_qty'] ?? '{}', true) ?? []) : [];

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="UTF-8"><style> td { border: .5pt solid windowtext; vertical-align: middle; } </style></head>';
        echo '<body>';
        echo '<table style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px;">';
        
        echo '<tr>';
        echo '<td colspan="4" style="border:none;"></td>';
        echo '<td colspan="4" style="border:none; font-size: 18px; font-weight: bold; text-align: center;">STOCK TRANSFER LIST (STL DIGITAL)</td>';
        echo '<td colspan="4" style="border:none; text-align: right; font-weight: bold;">REV : 01<br>FR-ML-0007</td>';
        echo '</tr>';
        echo '<tr><td colspan="12" style="border:none; height: 10px;"></td></tr>';
        
        echo '<tr style="background-color: #d9d9d9; font-weight: bold; text-align: center; height: 25px;">';
        echo '<td colspan="2">DEPT</td><td colspan="2">NAME</td><td colspan="2">SIGN</td><td colspan="2">TIME</td><td colspan="2">SHIFT</td><td colspan="2">VERIFY BY</td>';
        echo '</tr>';
        echo '<tr style="text-align: center; height: 40px;">';
        echo '<td colspan="2" style="font-weight:bold; background-color: #f2f2f2;">PROD (REQUEST)</td><td colspan="2">'.$req_name.'</td><td colspan="2">'.$req_sig.'</td><td colspan="2">'.$req_time.'</td><td colspan="2">'.$req_shift.'</td><td colspan="2">'.$req_verify.'</td>';
        echo '</tr>';
        echo '<tr style="text-align: center; height: 40px;">';
        echo '<td colspan="2" style="font-weight:bold; background-color: #f2f2f2;">MPL (SUPPLY)</td><td colspan="2">'.$sup_name.'</td><td colspan="2">'.$sup_sig.'</td><td colspan="2">'.$sup_time.'</td><td colspan="2">'.$sup_shift.'</td><td colspan="2">'.$sup_verify.'</td>';
        echo '</tr>';
        echo '<tr style="text-align: center; height: 40px;">';
        echo '<td colspan="2" style="font-weight:bold; background-color: #f2f2f2;">PROD (RECEIVE)</td><td colspan="2">'.$rec_name.'</td><td colspan="2">'.$rec_sig.'</td><td colspan="2">'.$rec_time.'</td><td colspan="2">'.$rec_shift.'</td><td colspan="2">'.$rec_verify.'</td>';
        echo '</tr>';
        
        echo '<tr><td colspan="12" style="border:none; height: 15px;"></td></tr>';
        
        echo '<tr style="background-color: #d9d9d9; font-weight: bold; text-align: center;">';
        echo '<td colspan="2">DATE</td><td colspan="2">DAY</td><td colspan="2">MODEL</td><td colspan="2">LINE</td><td colspan="2">FG CODE</td><td colspan="2">VARIANCE</td>';
        echo '</tr>';
        echo '<tr style="font-weight: bold; text-align: center; font-size: 13px;">';
        echo '<td colspan="2">'.$date_fmt.'</td><td colspan="2">'.$day_fmt.'</td><td colspan="2" style="color:#203764;">'.$order['model'].'</td><td colspan="2" style="color:#203764;">'.$order['line'].'</td><td colspan="2" style="color:#203764;">'.$order['fg_code'].'</td><td colspan="2">'.$var_fmt.'</td>';
        echo '</tr>';
        
        echo '<tr><td colspan="12" style="border:none; height: 10px;"></td></tr>';
        echo '<tr><td colspan="12" style="text-align:left; font-size:16px; font-weight:black; border:none;">TRIP NO: <span style="color:#e11d48;">TRIP '.$order['trip_number'].'</span></td></tr>';
        
        echo '<tr style="background-color: #203764; color: white; font-weight: bold; text-align: center; height:30px;">';
        echo '<td style="width: 40px;">NO</td>';
        echo '<td style="width: 90px;">IMAGE</td>';
        echo '<td style="width: 250px;">PART NAME</td>';
        echo '<td style="width: 120px;">PART NO</td>';
        echo '<td style="width: 100px;">ERP CODE</td>';
        echo '<td style="width: 90px;">STATION</td>';
        echo '<td style="width: 80px;">USAGE</td>';
        echo '<td style="width: 90px;">STD PACK</td>';
        echo '<td style="width: 90px;">STD/TRIP</td>';
        echo '<td style="background-color: #00b050; color: white; width: 90px;">REQ QTY</td>';
        echo '<td style="background-color: #ffc000; color: black; width: 90px;">SUP QTY</td>';
        echo '<td style="background-color: #0070c0; color: white; width: 90px;">REC QTY</td>';
        echo '</tr>';
        
        $bil = 1;
        foreach ($parts as $p) {
            $pid = (string)$p['id'];
            $r = $req_data[$pid] ?? 0;
            $s = $sup_data[$pid] ?? 0;
            $rc = $rec_data[$pid] ?? 0;
            
            $img_tag = '';
            if (!empty($p['img'])) {
                $full_img_url = $base_url . $p['img'];
                $img_tag = '<img src="'.$full_img_url.'" width="70" height="70" style="margin: 0 auto; display: block;">';
            } else {
                $img_tag = '<span style="color:#ccc; font-size:9px;">NO IMG</span>';
            }

            $qty_style = ($r != $s || $s != $rc) ? 'background-color: #fff2cc;' : '';
            
            echo '<tr style="'.$qty_style.'" height="85">';
            echo '<td style="text-align: center;">'.$bil++.'</td>';
            echo '<td width="90" height="85" style="text-align: center; vertical-align: middle; padding: 5px;">'.$img_tag.'</td>';
            echo '<td><strong>'.htmlspecialchars($p['stock_desc']).'</strong></td>';
            echo '<td style="text-align: center;">'.htmlspecialchars($p['part_no']).'</td>';
            echo '<td style="text-align: center;">'.htmlspecialchars($p['erp_code']).'</td>';
            echo '<td style="text-align: center;">'.htmlspecialchars($p['area']).'</td>';
            echo '<td style="text-align: center; font-weight: bold;">'.$p['usage'].'</td>';
            echo '<td style="text-align: center;">'.$p['std_pack'].'</td>';
            echo '<td style="text-align: center; font-weight: bold;">'.$p['std_pack_trip'].'</td>';
            echo '<td style="text-align: center; font-weight: bold; font-size: 14px; color: #008f40;">'.$r.'</td>';
            echo '<td style="text-align: center; font-weight: bold; font-size: 14px; color: #d99600;">'.$s.'</td>';
            echo '<td style="text-align: center; font-weight: bold; font-size: 14px; color: #005a9e;">'.$rc.'</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body></html>';
        exit;
    }
}

// --- 1. AUTHENTICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['action'])) {
    $role = $_POST['role'];
    $pass = $_POST['password'];
    $passwords = ['PROD_REQ' => '1234', 'MPL_SUP' => '5678', 'PROD_RECV' => '9999'];

    if (isset($passwords[$role]) && $passwords[$role] === $pass) {
        $_SESSION['stl_auth'] = true; $_SESSION['stl_role'] = $role;
        $_SESSION['stl_model'] = $_POST['model']; $_SESSION['stl_line'] = $_POST['line'];
        echo "<script>window.location.href='stl_form.php';</script>"; exit;
    } else {
        die("<div style='display:flex; justify-content:center; align-items:center; height:100vh; background:#f0fdfa; font-family:sans-serif;'><div style='text-align:center; padding:40px; background:white; border-radius:20px; box-shadow:0 10px 25px rgba(0,0,0,0.1);'><h2 style='color:#ef4444; margin-bottom:10px; font-size: 24px;'>Access Denied</h2><p style='color:#64748b;'>Incorrect PIN.</p><a href='stl.php' style='display:inline-block; margin-top:20px; padding: 10px 20px; background: #0d9488; border-radius: 8px; text-decoration:none; color:white; font-weight:bold;'>Try Again &rarr;</a></div></div>");
    }
}

if (!isset($_SESSION['stl_auth'])) { echo "<script>window.location.href='stl.php';</script>"; exit; }
$model = $_SESSION['stl_model']; $line = $_SESSION['stl_line']; $role = $_SESSION['stl_role'];

// --- 2. FORM SUBMISSION LOGIC ---
$action = $_POST['action'] ?? '';

if ($action == 'create_request') {
    $req_json = json_encode($_POST['req_qty'] ?? []);
    $current_day_name = date('l');
    
    // Convert base64 to real PNG file
    $sig_path = saveSignatureToFile($_POST['signature_data'], 'req');

    $stmt = $pdo->prepare("INSERT INTO stl_orders (model, line, fg_code, variance, trip_number, shift, status, requested_at, requested_day, requested_by, verified_by, req_signature, req_qty) VALUES (?, ?, ?, ?, ?, ?, 'PENDING_SUPPLY', NOW(), ?, ?, ?, ?, ?)");
    $stmt->execute([$model, $line, $_POST['fg_code'], $_POST['variance'] ?? '', $_POST['trip_number'], $_POST['shift'], $current_day_name, $_POST['staff_name'], $_POST['verify_name'], $sig_path, $req_json]);
    
    // --- TELEGRAM NOTIFICATION (PROD TO MPL) ---
    $mpl_chat_id = "-5113086281"; 
    
    $msg = "🆕 *NEW STL REQUEST*\n";
    $msg .= "Model: $model | Line: $line\n";
    $msg .= "FG Code: " . ($_POST['fg_code'] ?? '') . "\n";
    $msg .= "Trip: " . ($_POST['trip_number'] ?? '') . "\n";
    $msg .= "Req By: " . ($_POST['staff_name'] ?? '') . "\n";
    $msg .= "Status: ⏳ *PENDING SUPPLY*";
    
    sendTelegramMessage($mpl_chat_id, $msg);
    // ------------------------------------------------------------

    echo "<script>window.location.href='stl_form.php?success=1';</script>"; exit;

} elseif ($action == 'advance_to_receive') {
    $order_id = $_POST['order_id'];
    $staff_name = $_POST['staff_name'];
    $sup_json = json_encode($_POST['sup_qty'] ?? []);
    $current_day_name = date('l');
    
    // 1. UPDATE ORDER STATUS
    $stmt = $pdo->prepare("UPDATE stl_orders SET status = 'PENDING_RECEIVE' WHERE id = ?");
    $stmt->execute([$order_id]);

    // Convert base64 to real PNG file
    $sig_path = saveSignatureToFile($_POST['signature_data'], 'sup');

    $stmt = $pdo->prepare("INSERT INTO stl_supply (order_id, supplied_at, supplied_day, supplied_by, verified_by, shift, sup_signature, sup_qty) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$order_id, $current_day_name, $staff_name, $_POST['verify_name'], $_POST['shift'], $sig_path, $sup_json]);

    // ---------------------------------------------------------
    // NEW: SMART TAG SPLIT LOGIC (Checks racking_in via ID_CODE)
    // ---------------------------------------------------------
    $scanned_json = $_POST['scanned_barcodes'] ?? '[]';
    $barcodes = json_decode($scanned_json, true);
    $sup_qty_array = $_POST['sup_qty'] ?? [];

    // Step A: Calculate how much of each ERP code was supplied based on what MPL typed
    $supplied_erps = [];
    foreach ($sup_qty_array as $part_id => $qty) {
        $stmt_p = $pdo->prepare("SELECT erp_code FROM master_stl WHERE id = ?");
        $stmt_p->execute([$part_id]);
        $erp = $stmt_p->fetchColumn();
        if ($erp && (int)$qty > 0) {
            $supplied_erps[$erp] = ($supplied_erps[$erp] ?? 0) + (int)$qty;
        }
    }

    // Step B: Loop through scanned Tag IDs and deduct the quantities
    foreach ($barcodes as $tag_id) {
        // Find the tag in the racking_in table
        $verify = $pdo->prepare("SELECT ERP_CODE, QTY FROM racking_in WHERE ID_CODE = ?");
        $verify->execute([$tag_id]);
        $box = $verify->fetch(PDO::FETCH_ASSOC);

        if ($box) {
            $erp = $box['ERP_CODE'] ?? $box['erp_code'] ?? '';
            $box_qty = (int)($box['QTY'] ?? $box['qty'] ?? 0);
            $needed_qty = $supplied_erps[$erp] ?? 0;

            if ($needed_qty > 0) {
                // How much are we taking from this specific Tag ID?
                $qty_to_take = min($box_qty, $needed_qty);
                
                // Deduct from our running total
                $supplied_erps[$erp] -= $qty_to_take;

                if ($qty_to_take == $box_qty) {
                    // We took the WHOLE box. Deduct all qty from racking_in.
                    $trace_no = $tag_id;
                    $upd = $pdo->prepare("UPDATE racking_in SET QTY = 0 WHERE ID_CODE = ?");
                    $upd->execute([$tag_id]);
                } else {
                    // We only took PART of the box. We must SPLIT it digitally!
                    $trace_no = $tag_id . "-S" . $order_id; // Example: R105J5001-S15
                    
                    // Deduct the supplied qty from the original Tag ID (it stays on the warehouse rack)
                    $upd = $pdo->prepare("UPDATE racking_in SET QTY = QTY - ? WHERE ID_CODE = ?");
                    $upd->execute([$qty_to_take, $tag_id]);
                }

                // Step C: Save this movement to the Traceability Bridge
                $insert_trace = $pdo->prepare("
                    INSERT INTO stl_traceability (order_id, unique_no, erp_code, qty_supplied) 
                    VALUES (?, ?, ?, ?)
                ");
                $insert_trace->execute([$order_id, $trace_no, $erp, $qty_to_take]);
            }
        }
    }
    // ---------------------------------------------------------

    // --- TELEGRAM NOTIFICATION (MPL TO PROD) ---
    $prod_chat_id = "-5099061708"; 
    
    $msg = "✅ *STL SUPPLY READY*\n";
    $msg .= "Order ID: " . $order_id . "\n";
    $msg .= "Model: $model | Line: $line\n";
    $msg .= "MPL has prepared the parts. Please proceed to receive.\n";
    $msg .= "Supplied By: " . $staff_name;
    
    sendTelegramMessage($prod_chat_id, $msg);
    // -------------------------------------------

    echo "<script>window.location.href='stl_form.php?success=1';</script>"; exit;

} elseif ($action == 'advance_to_complete') {
    $rec_json = json_encode($_POST['rec_qty'] ?? []);
    
    // Convert base64 to real PNG file
    $sig_path = saveSignatureToFile($_POST['signature_data'], 'rec');
    
    $stmt = $pdo->prepare("UPDATE stl_orders SET status = 'COMPLETED', received_at = NOW(), received_by = ?, recv_verified_by = ?, recv_shift = ?, recv_signature = ?, rec_qty = ? WHERE id = ?");
    $stmt->execute([$_POST['staff_name'], $_POST['verify_name'], $_POST['shift'], $sig_path, $rec_json, $_POST['order_id']]);

    // --- TELEGRAM NOTIFICATION (JOB COMPLETED -> NOTIFY BOTH) ---
    $mpl_chat_id = "-5113086281";  
    $prod_chat_id = "-5099061708"; 
    
    $msg = "🏁 *STL ORDER COMPLETED*\n";
    $msg .= "Order ID: " . $_POST['order_id'] . "\n";
    $msg .= "Model: $model | Line: $line\n";
    $msg .= "Production has successfully received the parts from MPL.\n";
    $msg .= "Received By: " . $_POST['staff_name'];
    
    sendTelegramMessage($mpl_chat_id, $msg);
    sendTelegramMessage($prod_chat_id, $msg);
    // ------------------------------------------------------------

    // --- AUTOMATED EXCEL EMAIL TO HODs ---
        $stmt_order = $pdo->prepare("SELECT trip_number FROM stl_orders WHERE id = ?");
        $stmt_order->execute([$_POST['order_id']]);
        $order_details = $stmt_order->fetch(PDO::FETCH_ASSOC);
        $actual_trip = $order_details['trip_number'] ?? 'Completed';

        $excel_data = buildExcelDocument($pdo, $_POST['order_id']);
        
        $filename = "STL_Record_Trip_" . $actual_trip . "_" . date('Ymd') . ".xls";
        $hod_emails = ""; 
        $subject = "COMPLETED STL: $model Line $line (Trip " . $actual_trip . ")";
        
        $body_text = "Dear HOD,\n\n";
        $body_text .= "Please find the attached completed Stock Transfer List (STL) document.\n\n";
        $body_text .= "Model: $model\n";
        $body_text .= "Line: $line\n";
        $body_text .= "Status: COMPLETED\n\n";
        $body_text .= "This is an automated message from the Digital STL System.";
        
        sendEmailWithExcel($hod_emails, $subject, $body_text, $filename, $excel_data);
        // -------------------------------------
}

// --- 3. DATA FETCHING ---
$tasks = [];
$history_tasks = [];

if ($role === 'PROD_REQ') {
    $stmt = $pdo->prepare("SELECT * FROM stl_orders WHERE model = ? AND line = ? ORDER BY requested_at DESC LIMIT 100");
    $stmt->execute([$model, $line]);
    $history_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role === 'MPL_SUP') {
    $stmt = $pdo->prepare("SELECT * FROM stl_orders WHERE model = ? AND line = ? AND status = 'PENDING_SUPPLY' ORDER BY requested_at ASC");
    $stmt->execute([$model, $line]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM stl_orders WHERE model = ? AND line = ? AND status IN ('PENDING_RECEIVE', 'COMPLETED') ORDER BY requested_at DESC LIMIT 100");
    $stmt->execute([$model, $line]);
    $history_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role === 'PROD_RECV') {
    $stmt = $pdo->prepare("SELECT o.*, s.sup_qty FROM stl_orders o LEFT JOIN stl_supply s ON o.id = s.order_id WHERE o.model = ? AND o.line = ? AND o.status = 'PENDING_RECEIVE' ORDER BY o.requested_at ASC");
    $stmt->execute([$model, $line]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM stl_orders WHERE model = ? AND line = ? AND status = 'COMPLETED' ORDER BY requested_at DESC LIMIT 100");
    $stmt->execute([$model, $line]);
    $history_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$active_task = null; $req_data = []; $sup_data = [];
if (isset($_GET['order_id'])) {
    foreach($tasks as $t) { if($t['id'] == $_GET['order_id']) { $active_task = $t; break; } }
    if($active_task) {
        $req_data = json_decode($active_task['req_qty'] ?? '{}', true) ?? [];
        $sup_data = json_decode($active_task['sup_qty'] ?? '{}', true) ?? [];
        $_GET['fg'] = $active_task['fg_code'];
        $_GET['sub'] = 'ALL'; 
        $_GET['variance'] = $active_task['variance'];
    }
}

// --- VIRTUAL LINE OVERRIDE LOGIC ---
$line_cond = ($line == '5') 
    ? "(line1 = ? OR UPPER(area) IN ('NW', 'PNE'))" 
    : "(line1 = ? AND UPPER(area) NOT IN ('NW', 'PNE'))";

// 1. Fetch distinct FG Codes
$stmt_fg = $pdo->prepare("SELECT DISTINCT fg_code FROM master_stl WHERE model = ? AND $line_cond AND fg_code IS NOT NULL AND fg_code != '' ORDER BY fg_code ASC");
$stmt_fg->execute([$model, $line]);
$fg_codes = $stmt_fg->fetchAll(PDO::FETCH_COLUMN);
$selected_fg = $_GET['fg'] ?? ($fg_codes[0] ?? '');

// 2. Fetch Sub-Assemblies
$sub_assy_list = [];
if ($selected_fg) {
    $stmt_sub = $pdo->prepare("SELECT DISTINCT sub_assy FROM master_stl WHERE model = ? AND $line_cond AND fg_code = ? AND sub_assy IS NOT NULL AND sub_assy != '' ORDER BY sub_assy ASC");
    $stmt_sub->execute([$model, $line, $selected_fg]);
    $sub_assy_list = $stmt_sub->fetchAll(PDO::FETCH_COLUMN);
}
$selected_sub = $_GET['sub'] ?? 'ALL';

// 3. Fetch Variances
$variances = [];
if ($selected_fg) {
    $v_query = "SELECT DISTINCT variance FROM master_stl WHERE model = ? AND $line_cond AND fg_code = ? AND variance IS NOT NULL AND variance != ''";
    $v_params = [$model, $line, $selected_fg];
    
    if ($selected_sub !== 'ALL') {
        $v_query .= " AND sub_assy = ?";
        $v_params[] = $selected_sub;
    }
    
    $stmt_var = $pdo->prepare($v_query);
    $stmt_var->execute($v_params);
    $variances = $stmt_var->fetchAll(PDO::FETCH_COLUMN);
}
$selected_variance = $_GET['variance'] ?? ($variances[0] ?? '');

// 4. Fetch Parts list
$parts = [];
if ($selected_fg) {
    $query = "SELECT * FROM master_stl WHERE model = ? AND $line_cond AND fg_code = ?";
    $params = [$model, $line, $selected_fg];
    
    if ($selected_sub !== 'ALL') {
        $query .= " AND sub_assy = ?";
        $params[] = $selected_sub;
    }
    
    if ($selected_variance) { 
        $query .= " AND variance = ?"; 
        $params[] = $selected_variance; 
    }
    $query .= " ORDER BY stock_desc ASC";
    
    $stmt = $pdo->prepare($query); 
    $stmt->execute($params);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$theme = match($role) {
    'PROD_REQ'  => ['color' => 'teal', 'title' => 'Production Ordering', 'icon' => 'send', 'btn' => 'SUBMIT REQUEST'],
    'MPL_SUP'   => ['color' => 'amber', 'title' => 'MPL Supply Preparation', 'icon' => 'package-open', 'btn' => 'CONFIRM SUPPLY'],
    'PROD_RECV' => ['color' => 'emerald', 'title' => 'Production Receiving', 'icon' => 'check-check', 'btn' => 'CONFIRM RECEIVED']
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Digital STL System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .glass-panel { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(16px); }
        input[type=number]::-webkit-inner-spin-button { opacity: 1; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
    </style>
</head>
<body class="min-h-screen flex flex-col text-slate-800 selection:bg-<?php echo $theme['color']; ?>-200">

    <div class="glass-panel border-b border-slate-200 px-4 md:px-8 py-4 flex justify-between items-center sticky top-0 z-50 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="bg-gradient-to-br from-slate-800 to-slate-900 text-white w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg shadow-slate-500/30">
                <i data-lucide="<?php echo $theme['icon']; ?>" class="w-6 h-6 text-<?php echo $theme['color']; ?>-400"></i>
            </div>
            <div>
                <h1 class="font-black text-xl text-slate-800 uppercase tracking-tight leading-tight">
                    <?php echo htmlspecialchars($model); ?> <span class="text-slate-300 font-light mx-1">|</span> LINE <?php echo htmlspecialchars($line); ?>
                </h1>
                <p class="text-xs font-bold uppercase tracking-widest text-<?php echo $theme['color']; ?>-600 mt-0.5"><?php echo $theme['title']; ?></p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <a href="stl_dashboard.php" target="_blank" class="hidden sm:flex items-center gap-2 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 font-bold text-sm px-4 py-2.5 rounded-xl transition-all border border-indigo-200 shadow-sm">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Live Dashboard
            </a>
            <div class="hidden md:block text-right mx-4">
                <p class="text-sm font-black text-slate-700"><?php echo $current_day; ?></p>
                <p class="text-xs font-medium text-slate-500"><?php echo $current_date; ?></p>
            </div>
            <a href="stl.php?clear=1" onclick="sessionStorage.clear();" class="group flex items-center gap-2 text-slate-500 hover:text-red-600 font-bold text-sm bg-white px-4 py-2.5 rounded-xl transition-all duration-300 border border-slate-200 shadow-sm hover:shadow-md hover:border-red-200">
                <i data-lucide="log-out" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> <span class="hidden sm:inline">Exit</span>
            </a>
        </div>
    </div>

    <div class="flex-grow p-4 md:p-6 lg:p-8 overflow-y-auto custom-scroll w-full relative">
        
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 p-4 rounded-2xl shadow-sm mb-6 flex items-center gap-4 max-w-[1600px] mx-auto animate-fade-in">
                <div class="bg-green-500 text-white rounded-full p-2"><i data-lucide="check-circle-2" class="w-6 h-6"></i></div>
                <div>
                    <h3 class="font-bold text-green-900">Success!</h3>
                    <p class="text-sm text-green-700">The Stock Transfer List has been successfully updated.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'PROD_REQ'): ?>
        <form method="POST" id="mainOrderForm" enctype="multipart/form-data" class="max-w-[1600px] mx-auto w-full flex flex-col gap-6 animate-fade-in">
            <input type="hidden" name="action" value="create_request">
            <input type="hidden" name="staff_name" id="hidden_staff_name">
            <input type="hidden" name="shift" id="hidden_shift">
            <input type="hidden" name="verify_name" id="hidden_verify_name">
            <input type="hidden" name="signature_data" id="signature_data">

            <div id="step1-container" class="bg-white rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden transition-all duration-500">
                <div class="absolute top-0 left-0 bottom-0 w-1.5 bg-<?php echo $theme['color']; ?>-500"></div>
                <div id="step1-form" class="p-6 md:p-8 lg:p-10">
                    <h2 class="text-xl font-black text-slate-800 mb-8 flex items-center gap-3">
                        <span class="bg-<?php echo $theme['color']; ?>-100 text-<?php echo $theme['color']; ?>-700 w-10 h-10 rounded-2xl flex items-center justify-center text-base shadow-inner">1</span>
                        Authorization Details
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
                        <div class="space-y-6 flex flex-col justify-center">
                            <div>
                                <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Requestor Name</label>
                                <select id="ui_staff_name" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 focus:ring-4 focus:ring-<?php echo $theme['color']; ?>-500/10 transition-all appearance-none cursor-pointer">
                                    <option value="" disabled selected>Tap to select your name...</option>
                                    <option value="MOHD FAIZAL BIN MOHD YUSOFF">MOHD FAIZAL BIN MOHD YUSOFF</option>
                                    <option value="NARMEEN BIN ABDUL HAMID">NARMEEN BIN ABDUL HAMID</option>
                                    <option value="MUHD ZULHUSNI BIN ABU HASAN">MUHD ZULHUSNI BIN ABU HASAN</option>
                                    <option value="NURHAMIZAH BINTI RAMLI">NURHAMIZAH BINTI RAMLI</option>
                                    <option value="SITI NOR MAZLIFAH BINTI AZMI">SITI NOR MAZLIFAH BINTI AZMI</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Verify By</label>
                                    <input type="text" id="ui_verify_name" placeholder="NAME" oninput="this.value = this.value.toUpperCase()" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 uppercase outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 focus:ring-4 focus:ring-<?php echo $theme['color']; ?>-500/10 transition-all">
                                </div>
                                <div>
                                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Shift</label>
                                    <div class="flex p-1.5 bg-slate-100 rounded-2xl border border-slate-200/60 h-[58px]">
                                        <label class="flex-1 cursor-pointer relative"><input type="radio" name="ui_shift" value="DAY" class="peer sr-only" checked><div class="flex items-center justify-center h-full rounded-xl text-slate-500 font-bold peer-checked:bg-white peer-checked:text-amber-500 peer-checked:shadow-sm transition-all duration-300"><i data-lucide="sun" class="w-4 h-4 mr-2"></i> Day</div></label>
                                        <label class="flex-1 cursor-pointer relative"><input type="radio" name="ui_shift" value="NIGHT" class="peer sr-only"><div class="flex items-center justify-center h-full rounded-xl text-slate-500 font-bold peer-checked:bg-slate-800 peer-checked:text-blue-300 transition-all duration-300"><i data-lucide="moon" class="w-4 h-4 mr-2"></i> Night</div></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col h-full">
                            <div class="flex justify-between items-end mb-3">
                                <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Digital Signature</label>
                                <div class="flex gap-2">
                                    <label for="sig-upload" class="text-xs text-<?php echo $theme['color']; ?>-700 font-bold bg-<?php echo $theme['color']; ?>-50 px-3 py-1.5 rounded-lg cursor-pointer hover:bg-<?php echo $theme['color']; ?>-100 transition shadow-sm flex items-center border border-<?php echo $theme['color']; ?>-200"><i data-lucide="upload" class="w-3.5 h-3.5 mr-1.5"></i> Upload</label>
                                    <input type="file" id="sig-upload" accept="image/*" class="hidden" onchange="handleSigUpload(event, 'sig-canvas')">
                                    <button type="button" onclick="clearSignature('sig-canvas')" class="text-xs text-red-600 font-bold bg-red-50 px-3 py-1.5 rounded-lg hover:bg-red-100 transition shadow-sm flex items-center border border-red-200 active:scale-95"><i data-lucide="eraser" class="w-3.5 h-3.5 mr-1.5"></i> Clear</button>
                                </div>
                            </div>
                            <div class="flex-grow border-2 border-dashed border-slate-300 rounded-3xl relative bg-slate-50 cursor-crosshair overflow-hidden group hover:border-<?php echo $theme['color']; ?>-400 transition-all duration-300 min-h-[160px]">
                                <canvas id="sig-canvas" class="w-full h-full absolute inset-0 z-10"></canvas>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-20"><span class="font-black uppercase tracking-widest text-3xl text-slate-400">Sign Here</span></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="lockStep1()" class="mt-10 w-full py-4 bg-slate-900 hover:bg-<?php echo $theme['color']; ?>-600 text-white font-black text-lg rounded-2xl shadow-lg transition-all duration-300 active:scale-95 flex items-center justify-center gap-3"><i data-lucide="lock" class="w-5 h-5"></i> LOCK & PROCEED</button>
                </div>

                <div id="step1-summary" class="hidden p-5 md:p-6 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="bg-green-500 text-white w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/30"><i data-lucide="shield-check" class="w-6 h-6"></i></div>
                        <div>
                            <h3 class="font-black text-slate-800 text-lg">Authorization Locked</h3>
                            <p class="text-sm font-medium text-slate-500 mt-0.5">
                                <span class="font-bold text-slate-800" id="sum_staff"></span> 
                                <span class="mx-2 text-slate-300">|</span> 
                                Verifier: <span class="font-bold text-slate-800" id="sum_verify"></span> 
                                <span class="mx-2 text-slate-300">|</span> 
                                Shift: <span class="font-bold text-slate-800 uppercase" id="sum_shift"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 w-full md:w-auto justify-end">
                        <div class="h-14 w-36 bg-white border border-slate-200 rounded-xl p-1 flex items-center justify-center overflow-hidden"><img id="sum_sig_img" class="h-full w-full object-contain mix-blend-multiply" src=""></div>
                        <button type="button" onclick="unlockStep1()" class="px-5 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-100 transition-all active:scale-95"><i data-lucide="unlock" class="w-4 h-4 mr-2"></i> Edit</button>
                    </div>
                </div>
            </div>

            <div id="step2-container" class="hidden space-y-6">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 md:p-8 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-<?php echo $theme['color']; ?>-500"></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Target FG Code</label>
                            <select name="fg_code" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 transition-all appearance-none cursor-pointer" onchange="window.location.href='stl_form.php?fg='+encodeURIComponent(this.value)">
                                <?php foreach($fg_codes as $fg): ?>
                                    <option value="<?php echo htmlspecialchars($fg); ?>" <?php if($selected_fg == $fg) echo 'selected'; ?>><?php echo htmlspecialchars($fg); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Sub Assy</label>
                            <select name="sub_assy" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 transition-all appearance-none cursor-pointer" onchange="window.location.href='stl_form.php?fg=<?php echo urlencode($selected_fg); ?>&sub='+encodeURIComponent(this.value)">
                             <?php foreach($sub_assy_list as $sub): ?>
                                    <option value="<?php echo htmlspecialchars($sub); ?>" <?php if($selected_sub == $sub) echo 'selected'; ?>><?php echo htmlspecialchars($sub); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Variance</label>
                            <select name="variance" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 transition-all appearance-none cursor-pointer" onchange="window.location.href='stl_form.php?fg=<?php echo urlencode($selected_fg); ?>&sub=<?php echo urlencode($selected_sub); ?>&variance='+encodeURIComponent(this.value)">
                                <?php if(empty($variances)): ?>
                                    <option value="">N/A</option>
                                <?php else: foreach($variances as $var): ?>
                                    <option value="<?php echo htmlspecialchars($var); ?>" <?php if($selected_variance == $var) echo 'selected'; ?>><?php echo htmlspecialchars($var); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Select Trip</label>
                            <select name="trip_number" id="ui_trip" onchange="sessionStorage.setItem('stl_trip', this.value)" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 transition-all appearance-none cursor-pointer">
                                <option value="1">TRIP 1</option><option value="2">TRIP 2</option><option value="3">TRIP 3</option><option value="4">TRIP 4</option><option value="5">TRIP 5</option><option value="6">TRIP 6</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-md border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Bil</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Photo</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest">Part Name</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest">Part No</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest">ERP Code</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Area</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Usage</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Std Pckg</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center bg-slate-700/50">Std/Trip</th>
                                    <th class="p-3 text-[11px] font-black text-white uppercase tracking-widest text-center bg-<?php echo $theme['color']; ?>-600 w-44">
                                        <div class="flex flex-col gap-2 items-center">
                                            <span>Req Qty</span>
                                            <button type="button" onclick="autoFillQty('req')" class="w-full bg-white/20 hover:bg-white/30 text-white text-[10px] py-1.5 rounded-lg shadow-sm flex items-center justify-center gap-1 border border-white/10 active:scale-95"><i data-lucide="zap" class="w-3 h-3"></i> AUTO FILL</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if(!empty($parts)): foreach($parts as $index => $row): ?>
                                <tr class="hover:bg-slate-50/80 group">
                                    <td class="p-4 text-center font-bold text-slate-400"><?php echo $index + 1; ?></td>
                                    
                                    <td class="p-4 text-center relative group/img w-32">
                                        <div class="relative inline-block w-20 h-20 sm:w-24 sm:h-24">
                                            <?php if(!empty($row['img'])): ?>
                                                <img id="img-preview-<?php echo $row['id']; ?>" src="<?php echo htmlspecialchars($row['img']); ?>" onclick="openImagePopup(this.src)" class="w-full h-full object-cover rounded-xl border-2 border-slate-200 shadow-sm cursor-pointer hover:border-<?php echo $theme['color']; ?>-400 hover:scale-105 transition-all bg-white">
                                            <?php else: ?>
                                                <div id="img-placeholder-<?php echo $row['id']; ?>" class="w-full h-full bg-slate-100 rounded-xl border-2 border-slate-200 flex items-center justify-center text-slate-300">
                                                    <i data-lucide="image" class="w-6 h-6"></i>
                                                </div>
                                                <img id="img-preview-<?php echo $row['id']; ?>" src="" onclick="openImagePopup(this.src)" class="w-full h-full object-cover rounded-xl border-2 border-slate-200 shadow-sm cursor-pointer hover:border-<?php echo $theme['color']; ?>-400 hover:scale-105 transition-all bg-white hidden">
                                            <?php endif; ?>
                                            
                                            <label for="upload-part-<?php echo $row['id']; ?>" class="absolute -bottom-2 -right-2 bg-white text-<?php echo $theme['color']; ?>-600 border border-<?php echo $theme['color']; ?>-200 p-1.5 rounded-lg shadow-md cursor-pointer hover:bg-<?php echo $theme['color']; ?>-50 transition-transform hover:scale-110" title="Upload new photo">
                                                <i data-lucide="camera" class="w-4 h-4"></i>
                                            </label>
                                            <input type="file" id="upload-part-<?php echo $row['id']; ?>" name="part_image" accept="image/*" class="hidden" onchange="uploadPartImage(this, '<?php echo $row['id']; ?>')">
                                        </div>
                                    </td>
                                    
                                    <td class="p-4 font-bold text-slate-800 text-base"><?php echo htmlspecialchars($row['stock_desc']); ?></td>
                                    <td class="p-4 font-mono text-black-500 text-s bg-slate-50/50"><?php echo htmlspecialchars($row['part_no']); ?></td>
                                    <td class="p-4 font-mono text-black-500 text-s"><?php echo htmlspecialchars($row['erp_code']); ?></td>
                                    <td class="p-4 text-center"><span class="bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-full text-[11px] font-bold border border-indigo-100"><?php echo htmlspecialchars($row['area']); ?></span></td>
                                    <td class="p-4 text-center font-bold text-slate-700 text-base"><?php echo $row['usage']; ?></td>
                                    <td class="p-4 text-center font-bold text-slate-700"><?php echo $row['std_pack']; ?></td>
                                    <td class="p-4 text-center"><span class="bg-slate-800 text-white font-black px-3 py-1.5 rounded-lg text-sm shadow-sm"><?php echo $row['std_pack_trip']; ?></span></td>
                                    <td class="p-3 text-center bg-<?php echo $theme['color']; ?>-50/30"><input type="number" name="req_qty[<?php echo $row['id']; ?>]" data-std-trip="<?php echo $row['std_pack_trip']; ?>" class="qty-input-req w-full text-center border-2 border-<?php echo $theme['color']; ?>-100 rounded-xl p-3 font-black text-<?php echo $theme['color']; ?>-900 outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-500 transition-all bg-white/50 text-lg" placeholder="0"></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-4 pb-12">
                    <button type="button" onclick="openConfirmModal('mainOrderForm')" class="w-full md:w-auto px-12 py-5 bg-<?php echo $theme['color']; ?>-600 hover:bg-<?php echo $theme['color']; ?>-700 text-white font-black text-lg rounded-2xl shadow-xl transition-all transform active:scale-95 flex items-center justify-center gap-3"><span>SUBMIT ORDER REQUEST</span> <i data-lucide="send" class="w-5 h-5"></i></button>
                </div>
            </div>
        </form>


        <?php elseif ($role === 'MPL_SUP' || $role === 'PROD_RECV'): 
            $is_mpl = ($role === 'MPL_SUP');
            $process_action = $is_mpl ? 'advance_to_receive' : 'advance_to_complete';
            $auth_title = $is_mpl ? 'MPL Supplier' : 'Receiver';
        ?>
        <form method="POST" id="mainProcessForm" class="max-w-[1600px] mx-auto w-full flex flex-col gap-6 animate-fade-in">
            <input type="hidden" name="action" value="<?php echo $process_action; ?>">
            <input type="hidden" name="order_id" value="<?php echo $active_task['id'] ?? ''; ?>">
            <input type="hidden" name="staff_name" id="hidden_staff_name">
            <input type="hidden" name="shift" id="hidden_shift">
            <input type="hidden" name="verify_name" id="hidden_verify_name">
            <input type="hidden" name="signature_data" id="signature_data">
            
            <input type="hidden" name="scanned_barcodes" id="hiddenBarcodes" value="[]">

            <div id="step1-container" class="bg-white rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden transition-all duration-500">
                <div class="absolute top-0 left-0 bottom-0 w-1.5 bg-<?php echo $theme['color']; ?>-500"></div>
                
                <div id="step1-form" class="p-6 md:p-8 lg:p-10">
                    <h2 class="text-xl font-black text-slate-800 mb-8 flex items-center gap-3">
                        <span class="bg-<?php echo $theme['color']; ?>-100 text-<?php echo $theme['color']; ?>-700 w-10 h-10 rounded-2xl flex items-center justify-center text-base shadow-inner">1</span>
                        <?php echo $auth_title; ?> Authorization Details
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
                        <div class="space-y-6 flex flex-col justify-center">
                            <div>
                                <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">PIC Name</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="user" class="w-5 h-5"></i></div>
                                    <input type="text" id="ui_staff_name" placeholder="TYPE YOUR NAME..." oninput="this.value = this.value.toUpperCase()" class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 uppercase outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 focus:ring-4 focus:ring-<?php echo $theme['color']; ?>-500/10 transition-all">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Verify By</label>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i data-lucide="user-check" class="w-5 h-5"></i></div>
                                        <input type="text" id="ui_verify_name" placeholder="NAME" oninput="this.value = this.value.toUpperCase()" class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl font-bold text-slate-700 uppercase outline-none focus:bg-white focus:border-<?php echo $theme['color']; ?>-400 focus:ring-4 focus:ring-<?php echo $theme['color']; ?>-500/10 transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest block mb-2">Shift</label>
                                    <div class="flex p-1.5 bg-slate-100 rounded-2xl border border-slate-200/60 h-[58px]">
                                        <label class="flex-1 cursor-pointer relative"><input type="radio" name="ui_shift" value="DAY" class="peer sr-only" checked><div class="flex items-center justify-center h-full rounded-xl text-slate-500 font-bold peer-checked:bg-white peer-checked:text-amber-500 peer-checked:shadow-sm transition-all duration-300"><i data-lucide="sun" class="w-4 h-4 mr-2"></i> Day</div></label>
                                        <label class="flex-1 cursor-pointer relative"><input type="radio" name="ui_shift" value="NIGHT" class="peer sr-only"><div class="flex items-center justify-center h-full rounded-xl text-slate-500 font-bold peer-checked:bg-slate-800 peer-checked:text-blue-300 transition-all duration-300"><i data-lucide="moon" class="w-4 h-4 mr-2"></i> Night</div></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col h-full">
                            <div class="flex justify-between items-end mb-3">
                                <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Digital Signature</label>
                                <div class="flex gap-2">
                                    <label for="sig-upload" class="text-xs text-<?php echo $theme['color']; ?>-700 font-bold bg-<?php echo $theme['color']; ?>-50 px-3 py-1.5 rounded-lg cursor-pointer hover:bg-<?php echo $theme['color']; ?>-100 transition shadow-sm flex items-center border border-<?php echo $theme['color']; ?>-200"><i data-lucide="upload" class="w-3.5 h-3.5 mr-1.5"></i> Upload</label>
                                    <input type="file" id="sig-upload" accept="image/*" class="hidden" onchange="handleSigUpload(event, 'sig-canvas')">
                                    <button type="button" onclick="clearSignature('sig-canvas')" class="text-xs text-red-600 font-bold bg-red-50 px-3 py-1.5 rounded-lg hover:bg-red-100 transition shadow-sm flex items-center border border-red-200 active:scale-95"><i data-lucide="eraser" class="w-3.5 h-3.5 mr-1.5"></i> Clear</button>
                                </div>
                            </div>
                            <div class="flex-grow border-2 border-dashed border-slate-300 rounded-3xl relative bg-slate-50 cursor-crosshair overflow-hidden group hover:border-<?php echo $theme['color']; ?>-400 transition-all duration-300 min-h-[160px]">
                                <canvas id="sig-canvas" class="w-full h-full absolute inset-0 z-10"></canvas>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-20"><span class="font-black uppercase tracking-widest text-3xl text-slate-400">Sign Here</span></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="lockStep1()" class="mt-10 w-full py-4 bg-slate-900 hover:bg-<?php echo $theme['color']; ?>-600 text-white font-black text-lg rounded-2xl shadow-lg transition-all duration-300 active:scale-95 flex items-center justify-center gap-3"><i data-lucide="lock" class="w-5 h-5"></i> LOCK & PROCEED</button>
                </div>

                <div id="step1-summary" class="hidden p-5 md:p-6 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="bg-green-500 text-white w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/30"><i data-lucide="shield-check" class="w-6 h-6"></i></div>
                        <div>
                            <h3 class="font-black text-slate-800 text-lg">Authorization Locked</h3>
                            <p class="text-sm font-medium text-slate-500 mt-0.5">
                                <span class="font-bold text-slate-800" id="sum_staff"></span> 
                                <span class="mx-2 text-slate-300">|</span> 
                                Verifier: <span class="font-bold text-slate-800" id="sum_verify"></span> 
                                <span class="mx-2 text-slate-300">|</span> 
                                Shift: <span class="font-bold text-slate-800 uppercase" id="sum_shift"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 w-full md:w-auto justify-end">
                        <div class="h-14 w-36 bg-white border border-slate-200 rounded-xl p-1 flex items-center justify-center overflow-hidden"><img id="sum_sig_img" class="h-full w-full object-contain mix-blend-multiply" src=""></div>
                        <button type="button" onclick="unlockStep1()" class="px-5 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-100 transition-all active:scale-95"><i data-lucide="unlock" class="w-4 h-4 mr-2"></i> Edit</button>
                    </div>
                </div>
            </div>

            <div id="step2-container" class="hidden">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 md:p-8 relative overflow-hidden mb-6">
                    <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-3">
                        <span class="bg-<?php echo $theme['color']; ?>-100 text-<?php echo $theme['color']; ?>-700 w-10 h-10 rounded-2xl flex items-center justify-center text-base shadow-inner">2</span>
                        Select Pending Task
                    </h2>
                    
                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <div class="flex-1">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Filter by Date</label>
                            <input type="date" id="filter_date" onchange="filterTasks()" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-<?php echo $theme['color']; ?>-400 transition-all cursor-pointer">
                        </div>
                        <div class="flex-1">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Filter by Trip</label>
                            <select id="filter_trip" onchange="filterTasks()" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-<?php echo $theme['color']; ?>-400 transition-all cursor-pointer">
                                <option value="">ALL TRIPS</option>
                                <option value="1">TRIP 1</option><option value="2">TRIP 2</option><option value="3">TRIP 3</option>
                                <option value="4">TRIP 4</option><option value="5">TRIP 5</option><option value="6">TRIP 6</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-4 overflow-x-auto custom-scroll pb-4 snap-x" id="task-list">
                        <?php if(empty($tasks)): ?>
                            <div class="w-full text-center py-12 text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto mb-3 text-slate-300"></i>
                                <p class="font-bold text-lg">All caught up! No pending tasks.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($tasks as $task): 
                                $is_active = (isset($_GET['order_id']) && $_GET['order_id'] == $task['id']);
                                $bg = $is_active ? "border-".$theme['color']."-500 bg-".$theme['color']."-50 shadow-md ring-4 ring-".$theme['color']."-500/20 transform -translate-y-1" : "border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm";
                                $task_date = date('Y-m-d', strtotime($task['requested_at']));
                            ?>
                            <a href="stl_form.php?order_id=<?php echo $task['id']; ?>" class="task-card min-w-[280px] shrink-0 block p-5 rounded-2xl border-2 <?php echo $bg; ?> transition-all duration-300 snap-start" data-date="<?php echo $task_date; ?>" data-trip="<?php echo $task['trip_number']; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-black text-slate-800 text-lg flex items-center gap-2">
                                        TRIP <?php echo $task['trip_number']; ?> 
                                        <span class="text-[10px] bg-white border border-slate-200 px-2 py-1 rounded-lg uppercase shadow-sm"><?php echo $task['shift']; ?></span>
                                    </h4>
                                    <span class="text-[10px] text-slate-500 font-bold border border-slate-200 px-2 py-1 rounded-md bg-slate-50">
                                        #<?php echo str_pad($task['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </div>
                                <p class="text-xs font-bold text-slate-500 mb-3 flex items-center gap-1.5"><i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i> <?php echo date('d M Y, h:i A', strtotime($task['requested_at'])); ?></p>
                                <div class="flex flex-wrap gap-2">
                                    <span class="text-[11px] font-black text-slate-600 bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200">FG: <?php echo htmlspecialchars($task['fg_code']); ?></span>
<?php if($task['variance']): ?><span class="text-[11px] font-black text-<?php echo $theme['color']; ?>-700 bg-<?php echo $theme['color']; ?>-100 px-2.5 py-1 rounded-md border border-<?php echo $theme['color']; ?>-200"><?php echo htmlspecialchars($task['variance']); ?></span><?php endif; ?>                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($active_task): ?>
                <div class="bg-white rounded-3xl shadow-md border border-slate-200 overflow-hidden animate-fade-in">
                    <div class="px-6 py-5 bg-white border-b border-slate-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <h2 class="font-black text-slate-800 flex items-center gap-2 text-lg"><i data-lucide="list-checks" class="text-<?php echo $theme['color']; ?>-500 w-5 h-5"></i> Process Quantities</h2>
                        <div class="flex gap-2">
                            <span class="bg-slate-800 text-white border border-slate-900 px-4 py-1.5 rounded-xl text-xs font-bold shadow-sm">TRIP <?php echo $active_task['trip_number']; ?></span>
                            <span class="bg-slate-100 border border-slate-200 text-slate-700 px-4 py-1.5 rounded-xl text-xs font-bold">FG: <?php echo htmlspecialchars($active_task['fg_code']); ?></span>
                            <?php if($active_task['variance']): ?><span class="bg-<?php echo $theme['color']; ?>-50 border border-<?php echo $theme['color']; ?>-200 text-<?php echo $theme['color']; ?>-700 px-4 py-1.5 rounded-xl text-xs font-bold">VAR: <?php echo htmlspecialchars($active_task['variance']); ?></span><?php endif; ?>
                        </div>
                    </div>

                    <?php if($is_mpl): ?>
                    <div class="p-6 border-b border-slate-100 bg-amber-50/50">
                        <label class="text-[11px] font-black text-amber-800 uppercase tracking-widest block mb-3 flex items-center gap-2">
                            <i data-lucide="barcode" class="w-4 h-4"></i> Scan Box Tag ID (e.g. R105J5001)
                        </label>
                        <div class="flex gap-3 mb-2 max-w-2xl">
                            <input type="text" id="barcodeScanner" placeholder="Shoot barcode here..." class="w-full p-4 bg-white border border-amber-300 rounded-xl font-mono font-bold text-amber-900 focus:ring-4 focus:ring-amber-500/20 outline-none shadow-sm text-lg" autocomplete="off">
                            <button type="button" onclick="addScannedBarcode()" class="bg-amber-600 hover:bg-amber-700 text-white px-6 rounded-xl font-bold transition-colors shadow-md active:scale-95 flex items-center justify-center">
                                <i data-lucide="plus" class="w-6 h-6"></i>
                            </button>
                        </div>
                        <div id="scannedList" class="flex flex-wrap gap-2 mt-4 empty:hidden"></div>
                        <p class="text-xs text-amber-600 mt-3 font-medium flex items-center gap-1.5"><i data-lucide="info" class="w-4 h-4"></i> System will auto-verify the tag ID against the order requirements.</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="overflow-x-auto custom-scroll">
                        <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Bil</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Photo</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest">Part Details</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Area</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center">Usage</th>
                                    <th class="p-4 text-[11px] font-black text-slate-300 uppercase tracking-widest text-center bg-slate-700/50">Std/Trip</th>
                                    
                                    <?php if($is_mpl): ?>
                                        <th class="p-4 text-[11px] font-black text-blue-300 uppercase tracking-widest text-center bg-blue-900/30">Req Qty</th>
                                        <th class="p-3 text-[11px] font-black text-white uppercase tracking-widest text-center bg-amber-500 w-44">
                                            <div class="flex flex-col gap-2 items-center">
                                                <span>Supply Qty</span>
                                                <button type="button" onclick="autoFillQty('sup')" class="w-full bg-white/20 hover:bg-white/30 text-white text-[10px] py-1.5 px-2 rounded-lg shadow-sm transition flex justify-center items-center gap-1 active:scale-95 border border-white/10"><i data-lucide="zap" class="w-3 h-3"></i> AUTO FILL</button>
                                            </div>
                                        </th>
                                    <?php else: ?>
                                        <th class="p-4 text-[11px] font-black text-amber-300 uppercase tracking-widest text-center bg-amber-900/30">Sup Qty</th>
                                        <th class="p-3 text-[11px] font-black text-white uppercase tracking-widest text-center bg-emerald-600 w-44">
                                            <div class="flex flex-col gap-2 items-center">
                                                <span>Recv Qty</span>
                                                <button type="button" onclick="autoFillQty('rec')" class="w-full bg-white/20 hover:bg-white/30 text-white text-[10px] py-1.5 px-2 rounded-lg shadow-sm transition flex justify-center items-center gap-1 active:scale-95 border border-white/10"><i data-lucide="zap" class="w-3 h-3"></i> AUTO FILL</button>
                                            </div>
                                        </th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if(!empty($parts)): foreach($parts as $index => $row): 
                                    $part_id_str = (string)$row['id'];
                                    $req = $req_data[$part_id_str] ?? 0; 
                                    $sup = $sup_data[$part_id_str] ?? 0; 
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="p-4 text-center font-bold text-slate-400"><?php echo $index + 1; ?></td>
                                    <td class="p-4 text-center w-32">
                                        <div class="relative inline-block w-20 h-20 sm:w-24 sm:h-24">
                                            <?php if(!empty($row['img'])): ?><img src="<?php echo htmlspecialchars($row['img']); ?>" onclick="openImagePopup(this.src)" class="w-full h-full object-cover rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:border-<?php echo $theme['color']; ?>-400 hover:scale-105 transition-all bg-white"><?php else: ?><div class="w-full h-full bg-slate-100 rounded-xl border border-slate-200 flex items-center justify-center text-slate-300"><i data-lucide="image" class="w-6 h-6"></i></div><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4"><p class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($row['stock_desc']); ?></td>
                                    <td class="p-4 text-center"><span class="bg-slate-100 px-3 py-1.5 rounded-full text-[11px] font-bold text-slate-600 border border-slate-200"><?php echo htmlspecialchars($row['area']); ?></span></td>
                                    <td class="p-4 text-center font-bold text-slate-700 text-base"><?php echo $row['usage']; ?></td>
                                    <td class="p-4 text-center"><span class="bg-slate-800 text-white font-black px-3 py-1.5 rounded-lg text-sm shadow-sm"><?php echo $row['std_pack_trip']; ?></span></td>
                                    
                                    <?php if($is_mpl): ?>
                                        <td class="p-4 text-center font-black text-blue-700 bg-blue-50/30 text-xl border-l border-slate-100"><?php echo htmlspecialchars($req); ?></td>
                                        <td class="p-3 text-center bg-amber-50/50 border-l border-amber-100"><input type="number" name="sup_qty[<?php echo $row['id']; ?>]" data-req-qty="<?php echo htmlspecialchars($req); ?>" class="qty-input-sup w-full text-center border-2 border-amber-200 rounded-xl p-3 font-black text-amber-900 outline-none focus:ring-4 focus:ring-amber-500/20 focus:border-amber-500 bg-white shadow-sm transition-all text-lg" placeholder="0"></td>
                                    <?php else: ?>
                                        <td class="p-4 text-center font-black text-amber-700 bg-amber-50/30 text-xl border-l border-slate-100"><?php echo htmlspecialchars($sup); ?></td>
                                        <td class="p-3 text-center bg-emerald-50/50 border-l border-emerald-100"><input type="number" name="rec_qty[<?php echo $row['id']; ?>]" data-sup-qty="<?php echo htmlspecialchars($sup); ?>" class="qty-input-rec w-full text-center border-2 border-emerald-200 rounded-xl p-3 font-black text-emerald-900 outline-none focus:ring-4 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm transition-all text-lg" placeholder="0"></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-6 border-t border-slate-200 bg-slate-50 flex justify-end shrink-0 shadow-inner">
                        <button type="button" onclick="openConfirmModal('mainProcessForm', false)" class="w-full md:w-auto px-12 py-4 bg-<?php echo $theme['color']; ?>-600 hover:bg-<?php echo $theme['color']; ?>-700 text-white font-black text-lg rounded-2xl shadow-[0_8px_20px_rgba(0,0,0,0.1)] transition-all duration-300 active:scale-95 flex items-center justify-center gap-3"><span><?php echo $theme['btn']; ?></span> <i data-lucide="check-circle-2" class="w-5 h-5"></i></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>

        <div id="history-container" class="mt-12 bg-white rounded-3xl shadow-sm border border-slate-200 p-6 md:p-8 relative overflow-hidden animate-fade-in max-w-[1600px] mx-auto">
            <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-3">
                <i data-lucide="history" class="w-6 h-6 text-slate-400"></i>
                Recently Processed Tasks (History)
            </h2>
            
            <div class="flex flex-col sm:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Filter by Date</label>
                    <input type="date" id="history_filter_date" onchange="filterHistory()" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-<?php echo $theme['color']; ?>-400 transition-all cursor-pointer">
                </div>
                <div class="flex-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Filter by Trip</label>
                    <select id="history_filter_trip" onchange="filterHistory()" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 outline-none focus:border-<?php echo $theme['color']; ?>-400 transition-all cursor-pointer">
                        <option value="">ALL TRIPS</option>
                        <option value="1">TRIP 1</option>
                        <option value="2">TRIP 2</option>
                        <option value="3">TRIP 3</option>
                        <option value="4">TRIP 4</option>
                        <option value="5">TRIP 5</option>
                        <option value="6">TRIP 6</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Filter by FG Code</label>
                    <input type="text" id="history_filter_fg" placeholder="E.g. KT77" onkeyup="filterHistory()" oninput="this.value = this.value.toUpperCase()" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 uppercase outline-none focus:border-<?php echo $theme['color']; ?>-400 transition-all">
                </div>
            </div>

            <?php if(empty($history_tasks)): ?>
                <div class="text-center py-8 text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                    <p class="font-bold">No history available yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                        <thead class="bg-slate-100 text-slate-600">
                            <tr>
                                <th class="p-4 text-[11px] font-black uppercase tracking-widest border-b border-slate-200">Date/Time</th>
                                <th class="p-4 text-[11px] font-black uppercase tracking-widest border-b border-slate-200">Trip</th>
                                <th class="p-4 text-[11px] font-black uppercase tracking-widest border-b border-slate-200">FG Code</th>
                                <th class="p-4 text-[11px] font-black uppercase tracking-widest border-b border-slate-200 text-center">Status</th>
                                <th class="p-4 text-[11px] font-black uppercase tracking-widest border-b border-slate-200 text-center">Export</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($history_tasks as $h): 
                                $h_date = date('Y-m-d', strtotime($h['requested_at']));
                                $h_trip = $h['trip_number'];
                                $h_fg = htmlspecialchars($h['fg_code']);
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors history-row" data-date="<?php echo $h_date; ?>" data-trip="<?php echo $h_trip; ?>" data-fg="<?php echo $h_fg; ?>">
                                <td class="p-4 font-medium text-slate-700"><?php echo date('d M Y, h:i A', strtotime($h['requested_at'])); ?></td>
                                <td class="p-4 font-black text-slate-800">TRIP <?php echo $h['trip_number']; ?></td>
                                <td class="p-4 text-slate-600"><strong><?php echo htmlspecialchars($h['fg_code']); ?></strong> <?php if($h['variance']) echo " | " . htmlspecialchars($h['variance']); ?></td>
                                <td class="p-4 text-center">
                                    <?php if($h['status'] == 'PENDING_RECEIVE'): ?>
                                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold">WAITING REC</span>
                                    <?php elseif($h['status'] == 'COMPLETED'): ?>
                                        <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold">COMPLETED</span>
                                    <?php else: ?>
                                        <span class="bg-slate-100 text-slate-700 px-3 py-1 rounded-full text-xs font-bold"><?php echo $h['status']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <a href="stl_form.php?export_csv=<?php echo $h['id']; ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 text-indigo-600 text-xs font-bold rounded-lg transition-colors shadow-sm">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i> EXCEL
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="confirmModal" class="fixed inset-0 z-[9999] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity opacity-0" onclick="closeConfirmModal()">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 transform scale-95 transition-transform duration-300" id="confirmModalContent" onclick="event.stopPropagation()">
            <div class="flex items-center justify-center w-16 h-16 bg-<?php echo $theme['color']; ?>-100 text-<?php echo $theme['color']; ?>-600 rounded-full mb-6 mx-auto">
                <i data-lucide="help-circle" class="w-8 h-8"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800 text-center mb-2">Confirm Action</h3>
            <p class="text-slate-500 text-center font-medium mb-8">Are you sure you want to proceed? Please verify that all quantities and details are correct.</p>
            <div class="flex gap-4">
                <button type="button" onclick="closeConfirmModal()" class="flex-1 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors active:scale-95">Cancel</button>
                <button type="button" onclick="executeSubmit()" class="flex-1 py-3.5 bg-<?php echo $theme['color']; ?>-600 hover:bg-<?php echo $theme['color']; ?>-700 text-white font-bold rounded-xl shadow-lg shadow-<?php echo $theme['color']; ?>-600/30 transition-all active:scale-95">Yes, Proceed</button>
            </div>
        </div>
    </div>

    <div id="imagePopup" class="fixed inset-0 z-[9999] hidden bg-slate-900/90 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity opacity-0" onclick="closeImagePopup()">
        <div class="relative max-w-5xl w-full flex justify-center" onclick="event.stopPropagation()"><img id="popupImage" src="" class="max-h-[85vh] max-w-full rounded-2xl shadow-2xl border-4 border-white/10 object-contain"><button type="button" onclick="closeImagePopup()" class="absolute -top-4 -right-4 bg-white text-slate-900 rounded-full w-10 h-10 flex items-center justify-center shadow-lg hover:bg-slate-200 transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button></div>
    </div>

    <script>
        lucide.createIcons();

        // =================================================================================
        // NEW AJAX BARCODE SCANNER LOGIC (Checks ID_CODE)
        // =================================================================================
        let scannedCodes = [];

        async function addScannedBarcode() {
            const scannerInput = document.getElementById('barcodeScanner');
            const val = scannerInput.value.trim().toUpperCase(); // Handle Tag ID caps
            if (!val) return;
            
            // Check for duplicates on screen
            if (scannedCodes.includes(val)) {
                alert("This Tag ID is already in your scan list!");
                scannerInput.value = '';
                scannerInput.focus();
                return;
            }

            const orderIdInput = document.querySelector('input[name="order_id"]');
            const orderId = orderIdInput ? orderIdInput.value : '';

            // Visual feedback while verifying
            scannerInput.disabled = true;
            const originalPlaceholder = scannerInput.placeholder;
            scannerInput.placeholder = "Verifying Tag ID...";

            try {
                // Send AJAX request to validate the tag
                const formData = new FormData();
                formData.append('action', 'validate_tag');
                formData.append('tag', val);
                formData.append('order_id', orderId);

                const res = await fetch('stl_form.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();

                if (data.success) {
                    // Poka-Yoke Success
                    scannedCodes.push(val);
                    updateScannedUI();
                } else {
                    // Poka-Yoke Fail - Alert the user immediately!
                    alert("❌ POKA-YOKE ERROR:\n" + data.message);
                }
            } catch (e) {
                console.error(e);
                alert("Network Error while checking Tag ID. Please check your connection.");
            } finally {
                // Reset scanner input
                scannerInput.disabled = false;
                scannerInput.value = '';
                scannerInput.placeholder = originalPlaceholder;
                scannerInput.focus();
            }
        }

        function updateScannedUI() {
            const container = document.getElementById('scannedList');
            if(!container) return;
            container.innerHTML = '';
            scannedCodes.forEach(code => {
                container.innerHTML += `
                    <span class="bg-amber-200 text-amber-900 px-3 py-1.5 rounded-xl font-mono text-sm font-bold shadow-sm flex items-center gap-2 border border-amber-300">
                        <i data-lucide="package" class="w-4 h-4 text-amber-700"></i> ${code} 
                        <i data-lucide="x" class="w-4 h-4 cursor-pointer text-amber-500 hover:text-red-600 transition-colors ml-1" onclick="removeBarcode('${code}')"></i>
                    </span>
                `;
            });
            lucide.createIcons();
            
            // Update hidden input so PHP can save the array
            const hiddenInput = document.getElementById('hiddenBarcodes');
            if(hiddenInput) hiddenInput.value = JSON.stringify(scannedCodes);
        }

        function removeBarcode(code) {
            scannedCodes = scannedCodes.filter(c => c !== code);
            updateScannedUI();
            const scannerInput = document.getElementById('barcodeScanner');
            if(scannerInput) scannerInput.focus();
        }
        // =================================================================================

        function filterHistory() {
            const dateFilter = document.getElementById('history_filter_date').value;
            const tripFilter = document.getElementById('history_filter_trip').value;
            const fgFilter = document.getElementById('history_filter_fg').value.toUpperCase();
            
            const rows = document.querySelectorAll('.history-row');

            rows.forEach(row => {
                const rDate = row.getAttribute('data-date');
                const rTrip = row.getAttribute('data-trip');
                const rFg = row.getAttribute('data-fg').toUpperCase();
                
                let dateMatch = !dateFilter || rDate === dateFilter;
                let tripMatch = !tripFilter || rTrip === tripFilter;
                let fgMatch = !fgFilter || rFg.includes(fgFilter);

                if (dateMatch && tripMatch && fgMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function filterTasks() {
            const dateFilter = document.getElementById('filter_date').value;
            const tripFilter = document.getElementById('filter_trip').value;
            const tasks = document.querySelectorAll('.task-card');

            tasks.forEach(task => {
                const tDate = task.getAttribute('data-date');
                const tTrip = task.getAttribute('data-trip');
                
                let dateMatch = !dateFilter || tDate === dateFilter;
                let tripMatch = !tripFilter || tTrip === tripFilter;

                task.style.display = (dateMatch && tripMatch) ? 'block' : 'none';
            });
        }

        function initCanvasEvents(canvasId) {
            const canvas = document.getElementById(canvasId); if(!canvas) return;
            if(canvas.dataset.initialized === 'true') return;
            canvas.dataset.initialized = 'true';

            let writingMode = false;
            const ctx = canvas.getContext('2d');
            
            const getPos = (e) => { 
                const rect = canvas.getBoundingClientRect(); 
                const clientX = e.touches ? e.touches[0].clientX : e.clientX; 
                const clientY = e.touches ? e.touches[0].clientY : e.clientY; 
                return { x: clientX - rect.left, y: clientY - rect.top }; 
            }
            const start = (e) => { writingMode = true; ctx.beginPath(); getPos(e); e.preventDefault(); }
            const move = (e) => { if(!writingMode) return; const {x,y} = getPos(e); ctx.lineTo(x,y); ctx.stroke(); e.preventDefault(); }
            const end = () => { writingMode = false; }
            
            canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move);
            canvas.addEventListener('mouseup', end); canvas.addEventListener('mouseleave', end);
            canvas.addEventListener('touchstart', start, {passive: false}); canvas.addEventListener('touchmove', move, {passive: false}); canvas.addEventListener('touchend', end);
        }

        function resizeCanvas(canvasId) {
            const canvas = document.getElementById(canvasId); if(!canvas) return;
            const parent = canvas.parentElement;
            
            if (parent.clientWidth > 0 && parent.clientHeight > 0) {
                const currentData = canvas.toDataURL();
                
                canvas.width = parent.clientWidth; 
                canvas.height = parent.clientHeight;
                const ctx = canvas.getContext('2d');
                ctx.lineWidth = 3; ctx.lineJoin = 'round'; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                
                if (currentData.length > 10) {
                    const img = new Image();
                    img.onload = () => { ctx.drawImage(img, 0, 0, canvas.width, canvas.height); };
                    img.src = currentData;
                }
            }
        }

        let formToSubmit = null;

        function openConfirmModal(formId) {
            // NEW MPL SCANNER VALIDATION: Block submission if MPL hasn't scanned parts
            if (formId === 'mainProcessForm' && '<?php echo $role; ?>' === 'MPL_SUP') {
                if (scannedCodes.length === 0) {
                    alert("Error: You must scan at least one physical Tag ID before submitting.");
                    const scanner = document.getElementById('barcodeScanner');
                    if(scanner) scanner.focus();
                    return;
                }
            }

            formToSubmit = document.getElementById(formId);
            const modal = document.getElementById('confirmModal');
            const content = document.getElementById('confirmModalContent');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirmModal');
            const content = document.getElementById('confirmModalContent');
            
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => { modal.classList.add('hidden'); formToSubmit = null; }, 300);
        }

        function executeSubmit() {
            if (formToSubmit) formToSubmit.submit();
        }

        function lockStep1() {
            const staffNode = document.getElementById('ui_staff_name');
            const staff = staffNode ? staffNode.value : '';
            
            const shiftNode = document.querySelector('input[name="ui_shift"]:checked');
            const shift = shiftNode ? shiftNode.value : 'DAY';
            
            const verifyNode = document.getElementById('ui_verify_name');
            const verify = verifyNode ? verifyNode.value : '';
            
            const canvas = document.getElementById('sig-canvas');
            const sig = canvas ? canvas.toDataURL() : '';
            const blank = document.createElement('canvas'); 
            if(canvas) { blank.width = canvas.width; blank.height = canvas.height; }

            if(!staff || !verify || sig === blank.toDataURL()) {
                alert("Please fill all Authorization fields and sign before locking."); return;
            }

            sessionStorage.setItem('stl_locked', 'true'); 
            sessionStorage.setItem('stl_staff', staff);
            sessionStorage.setItem('stl_shift', shift); 
            sessionStorage.setItem('stl_verify', verify); 
            sessionStorage.setItem('stl_sig', sig);
            applyLockState();
        }

        function applyLockState() {
            const step1Form = document.getElementById('step1-form');
            if (step1Form) step1Form.classList.add('hidden');
            
            const step1Summary = document.getElementById('step1-summary');
            if (step1Summary) step1Summary.classList.remove('hidden');
            
            const step2Container = document.getElementById('step2-container');
            if (step2Container) step2Container.classList.remove('hidden');

            const historyContainer = document.getElementById('history-container');
            if (historyContainer) historyContainer.classList.add('hidden');
            
            const sumStaff = document.getElementById('sum_staff');
            if (sumStaff) sumStaff.innerText = sessionStorage.getItem('stl_staff') || '';
            
            const sumShift = document.getElementById('sum_shift');
            if (sumShift) sumShift.innerText = sessionStorage.getItem('stl_shift') || '';
            
            const sumVerify = document.getElementById('sum_verify');
            if (sumVerify) sumVerify.innerText = sessionStorage.getItem('stl_verify') || '';
            
            const sigImg = document.getElementById('sum_sig_img');
            if (sigImg) sigImg.src = sessionStorage.getItem('stl_sig') || '';
            
            const hiddenStaff = document.getElementById('hidden_staff_name');
            if (hiddenStaff) hiddenStaff.value = sessionStorage.getItem('stl_staff') || '';
            
            const hiddenShift = document.getElementById('hidden_shift');
            if (hiddenShift) hiddenShift.value = sessionStorage.getItem('stl_shift') || '';
            
            const hiddenVerify = document.getElementById('hidden_verify_name');
            if (hiddenVerify) hiddenVerify.value = sessionStorage.getItem('stl_verify') || '';
            
            const sigData = document.getElementById('signature_data');
            if (sigData) sigData.value = sessionStorage.getItem('stl_sig') || '';
            
            const uiStaff = document.getElementById('ui_staff_name');
            if (uiStaff) uiStaff.value = sessionStorage.getItem('stl_staff') || '';
            
            const uiVerify = document.getElementById('ui_verify_name');
            if (uiVerify) uiVerify.value = sessionStorage.getItem('stl_verify') || '';
            
            const savedShift = sessionStorage.getItem('stl_shift');
            if(savedShift) {
                document.getElementsByName('ui_shift').forEach(r => { 
                    if(r.value === savedShift) r.checked = true; 
                });
            }
        }

        function unlockStep1() {
            sessionStorage.removeItem('stl_locked');
            document.getElementById('step1-form').classList.remove('hidden');
            document.getElementById('step1-summary').classList.add('hidden');
            document.getElementById('step2-container').classList.add('hidden');

            const historyContainer = document.getElementById('history-container');
            if (historyContainer) historyContainer.classList.remove('hidden');
            
            setTimeout(() => {
                resizeCanvas('sig-canvas');
                const savedSig = sessionStorage.getItem('stl_sig');
                if(savedSig) {
                    const canvas = document.getElementById('sig-canvas'); const ctx = canvas.getContext('2d');
                    const img = new Image(); img.onload = function() { ctx.clearRect(0,0,canvas.width,canvas.height); ctx.drawImage(img,0,0,canvas.width,canvas.height); };
                    img.src = savedSig;
                }
            }, 50);
        }

        document.addEventListener("DOMContentLoaded", () => {
            initCanvasEvents('sig-canvas');
            
            if(sessionStorage.getItem('stl_locked') === 'true' && document.getElementById('step1-form')) {
                applyLockState();
            } else {
                resizeCanvas('sig-canvas');
            }
            
            const savedTrip = sessionStorage.getItem('stl_trip');
            if(savedTrip && document.getElementById('ui_trip')) document.getElementById('ui_trip').value = savedTrip;

            // Bind Enter key to the barcode scanner
            const scannerInput = document.getElementById('barcodeScanner');
            if (scannerInput) {
                scannerInput.focus();
                scannerInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addScannedBarcode();
                    }
                });
            }
        });

        function uploadPartImage(input, partId) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const imgPreview = document.getElementById('img-preview-' + partId);
                const placeholder = document.getElementById('img-placeholder-' + partId);
                const label = document.querySelector(`label[for="upload-part-${partId}"]`);
                const originalLabelHtml = label.innerHTML;

                label.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
                lucide.createIcons();

                const reader = new FileReader();
                reader.onload = function(e) { imgPreview.src = e.target.result; imgPreview.classList.remove('hidden'); if(placeholder) placeholder.classList.add('hidden'); }
                reader.readAsDataURL(file);

                const formData = new FormData();
                formData.append('action', 'upload_part_image');
                formData.append('part_id', partId);
                formData.append('part_image', file);

                fetch('stl_form.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) { imgPreview.src = data.filepath + '?t=' + new Date().getTime(); } 
                    else { alert('Upload failed: ' + data.message); input.value = ''; imgPreview.src = ''; imgPreview.classList.add('hidden'); if(placeholder) placeholder.classList.remove('hidden'); }
                })
                .catch(error => { console.error('Error:', error); alert('An error occurred during upload.'); input.value = ''; imgPreview.src = ''; imgPreview.classList.add('hidden'); if(placeholder) placeholder.classList.remove('hidden'); })
                .finally(() => { label.innerHTML = originalLabelHtml; lucide.createIcons(); });
            }
        }

        function openImagePopup(src) {
            if(!src) return;
            const popup = document.getElementById('imagePopup');
            document.getElementById('popupImage').src = src;
            popup.classList.remove('hidden');
            setTimeout(() => popup.classList.remove('opacity-0'), 10);
        }
        
        function closeImagePopup() {
            const popup = document.getElementById('imagePopup');
            popup.classList.add('opacity-0');
            setTimeout(() => popup.classList.add('hidden'), 300);
        }

        function handleSigUpload(event, canvasId) {
            const file = event.target.files[0]; if (!file) return;
            const reader = new FileReader(); reader.onload = function(e) {
                const img = new Image(); img.onload = function() {
                    const canvas = document.getElementById(canvasId); const ctx = canvas.getContext('2d');
                    ctx.clearRect(0,0,canvas.width,canvas.height); ctx.drawImage(img,0,0,canvas.width,canvas.height);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function clearSignature(canvasId) { const canvas = document.getElementById(canvasId); canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); }
        function autoFillQty(type) { if(type === 'req') document.querySelectorAll('.qty-input-req').forEach(el => el.value = el.getAttribute('data-std-trip')); else if (type === 'sup') document.querySelectorAll('.qty-input-sup').forEach(el => el.value = el.getAttribute('data-req-qty')); else if (type === 'rec') document.querySelectorAll('.qty-input-rec').forEach(el => el.value = el.getAttribute('data-sup-qty')); }
    </script>
</body>

</html>

