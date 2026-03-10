<?php
require_once 'db.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kuala_Lumpur');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'scan'; 

// --- 1. MANUAL CHECK ---
if ($action === 'manual_check') {
    $manual_query = strtoupper(trim($input['query'] ?? ''));
    if(!$manual_query) { echo json_encode(['success'=>false, 'message'=>'Empty input']); exit; }

    try {
        // Smart Search: if long string, check first 9 chars (for Mazda/Masz/YTEC parts)
        $search_val = (strlen($manual_query) > 9) ? substr($manual_query, 0, 9) : $manual_query;

        $stmt = $pdo->prepare("SELECT * FROM master_incoming WHERE part_no = ? OR part_no = ? OR erp_code = ? LIMIT 1");
        $stmt->execute([$manual_query, $search_val, $manual_query]); 
        $data = $stmt->fetch();

        if($data) {
            echo json_encode(['success'=>true, 'data'=>[
                'part_name' => $data['stock_desc'],
                'seq_no'    => $data['seq_number'],
                'part_no'   => $data['part_no'],
                'erp_code'  => $data['erp_code']
            ]]);
        } else {
            echo json_encode(['success'=>false, 'message'=>'Part not found in Master Incoming.']);
        }
    } catch(Exception $e) {
        echo json_encode(['success'=>false, 'message'=>'DB Error: ' . $e->getMessage()]);
    }
    exit;
}

// --- 2. MAIN SCAN HANDLER ---
$qr_data = trim($input['qr_data'] ?? ''); 
$supplier = $input['supplier'] ?? '';
$ref_no = strtoupper(trim($input['job_no'] ?? '')); 
$batch_id = $input['batch_id'] ?? '';
$input_type = $input['input_type'] ?? 'SCAN';

if (!$qr_data || !$supplier || !$ref_no || !$batch_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

try {
    $part_no = ''; $erp_code = ''; $master_data = null;
    $serial_no = null; $inv_no = null;    

    if ($input_type === 'MANUAL') {
        $qr_data = strtoupper($qr_data);
        
        $stmt = $pdo->prepare("SELECT * FROM master_incoming WHERE part_no = ? OR erp_code = ? LIMIT 1");
        $stmt->execute([$qr_data, $qr_data]);
        $master_data = $stmt->fetch();
        
        if (!$master_data && strlen($qr_data) > 9) {
             $short_part = substr($qr_data, 0, 9);
             $stmt = $pdo->prepare("SELECT * FROM master_incoming WHERE part_no = ? LIMIT 1");
             $stmt->execute([$short_part]);
             $master_data = $stmt->fetch();
        }

        if (!$master_data) throw new Exception("Manual Entry: Part not found.");

        if ($supplier === 'YTEC') {
            $inv_no = strtoupper(trim($input['manual_inv_no'] ?? ''));
            if(empty($inv_no)) throw new Exception("Invoice Number is required.");
        } elseif ($supplier === 'MAZDA' || $supplier === 'MASZ') { 
            $serial_no = strtoupper(trim($input['manual_serial'] ?? ''));
            if(empty($serial_no)) throw new Exception("Serial Number is required.");
        }
    } 
    else {
        // --- SCANNER LOGIC ---
        if ($supplier === 'MAZDA' || $supplier === 'MASZ') {
            $parts = explode(',', $qr_data);
            if (count($parts) < 10) throw new Exception("Invalid QR Format.");

            // 1. Get Part No (Last non-empty item)
            $raw_part = '';
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                if (trim($parts[$i]) !== '') { $raw_part = trim($parts[$i]); break; }
            }

            // === LOGIC: READ ONLY FIRST 9 CHARACTERS ===
            $search_part_no = substr($raw_part, 0, 9); 

            // 2. SMART SERIAL FINDER
            $date_index = -1;
            foreach ($parts as $idx => $val) {
                $val = trim($val);
                if (preg_match('/^20[0-9]{6}$/', $val)) { 
                    $date_index = $idx;
                    break;
                }
            }

            if ($date_index > 0) {
                $serial_no = trim($parts[$date_index - 1]); 
            } else {
                $serial_no = isset($parts[13]) ? trim($parts[13]) : null;
            }

            if (empty($serial_no) || $serial_no === 'SV') throw new Exception("Serial Error. Got: " . $serial_no);

            // Query using the truncated part number
            $stmt = $pdo->prepare("SELECT * FROM master_incoming WHERE part_no = ? LIMIT 1");
            $stmt->execute([$search_part_no]);
            $master_data = $stmt->fetch();
            
            if (!$master_data) throw new Exception("Part not found ($search_part_no).");

        } elseif ($supplier === 'YTEC') {
            $parts = explode(',', $qr_data);
            
            // Validation: Ensure QR has enough data points
            if (count($parts) < 4) throw new Exception("Invalid YTEC QR Format.");
            
            // 1. Get Raw Part & Clean it (Remove spaces)
            $raw_part = str_replace(' ', '', trim($parts[0])); 
            
            // 2. ABSTRACT 9 CHARACTERS (New Requirement)
            // If the part number is longer than 9 chars, we cut it. 
            // If it's shorter, we keep it as is.
            $search_part_no = (strlen($raw_part) > 9) ? substr($raw_part, 0, 9) : $raw_part;

            $inv_no = trim($parts[3]);
            
            // 3. Search DB using the 9-char string
            $stmt = $pdo->prepare("SELECT * FROM master_incoming WHERE part_no = ? LIMIT 1");
            $stmt->execute([$search_part_no]);
            $master_data = $stmt->fetch();
            
            // 4. Fallback: If 9-char search fails, try the raw ERP code (Second item in QR)
            if (!$master_data) {
                $raw_erp = trim($parts[1]);
                $stmt = $pdo->prepare("SELECT * FROM master_incoming WHERE erp_code = ? LIMIT 1");
                $stmt->execute([$raw_erp]);
                $master_data = $stmt->fetch();
            }
            
            // 5. Final validation
            if (!$master_data) throw new Exception("Part not found in System (Searched: $search_part_no).");
        }
    } // <--- CRITICAL FIX: This closing brace was missing in your previous code.

    // --- SAVE TO DATABASE ---
    $erp_code = $master_data['erp_code'];
    $part_name = $master_data['stock_desc'];
    $part_no = $master_data['part_no'];
    $seq_no = $master_data['seq_number'];
    $qty = (int)$master_data['std_packing'];
    $date_print = date('d-m-Y');

    $pdo->beginTransaction();
    
    // --- MAZDA HANDLER ---
    if ($supplier === 'MAZDA') {
        // FIXED: Removed 'scat_no' from the WHERE clause. 
        // We check if this Part + Serial exists ANYWHERE in the log.
        $stmt = $pdo->prepare("SELECT scat_no FROM receiving_log_mazda WHERE part_no = ? AND serial = ? LIMIT 1");
        $stmt->execute([$part_no, $serial_no]);
        $existing = $stmt->fetch();

        if($existing) {
            // Professional touch: Tell the user exactly which job holds the duplicate
            throw new Exception("Duplicate: Serial $serial_no already scanned in Job " . $existing['scat_no']);
        }
        
        $sql = "INSERT INTO receiving_log_mazda (batch_id, scat_no, part_no, erp_code, part_name, seq_no, qty, serial, input_type, generated_qr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$batch_id, $ref_no, $part_no, $erp_code, $part_name, $seq_no, $qty, $serial_no, $input_type, 'TEMP']);
        
        $last_id = $pdo->lastInsertId();
        $final_qr = "{$part_no}|{$date_print}|M{$last_id}S{$ref_no}";
        $pdo->prepare("UPDATE receiving_log_mazda SET generated_qr = ? WHERE id = ?")->execute([$final_qr, $last_id]);

    // --- MASZ HANDLER ---
    } elseif ($supplier === 'MASZ') { 
         // FIXED: Removed 'scat_no' from the WHERE clause.
         $stmt = $pdo->prepare("SELECT scat_no FROM receiving_log_marz WHERE part_no = ? AND serial = ? LIMIT 1");
         $stmt->execute([$part_no, $serial_no]);
         $existing = $stmt->fetch();

         if($existing) {
             throw new Exception("Duplicate: Serial $serial_no already scanned in Job " . $existing['scat_no']);
         }
 
         $sql = "INSERT INTO receiving_log_marz (batch_id, scat_no, part_no, erp_code, part_name, seq_no, qty, serial, input_type, generated_qr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
         $pdo->prepare($sql)->execute([$batch_id, $ref_no, $part_no, $erp_code, $part_name, $seq_no, $qty, $serial_no, $input_type, 'TEMP']);
         
         $last_id = $pdo->lastInsertId();
         $final_qr = "{$part_no}|{$date_print}|MZ{$last_id}S{$ref_no}"; 
         $pdo->prepare("UPDATE receiving_log_marz SET generated_qr = ? WHERE id = ?")->execute([$final_qr, $last_id]);

    } else { // YTEC
    

        $sql = "INSERT INTO receiving_log_ytec (batch_id, job_no, part_no, erp_code, part_name, seq_no, qty, inv_no, input_type, generated_qr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$batch_id, $ref_no, $part_no, $erp_code, $part_name, $seq_no, $qty, $inv_no, $input_type, 'TEMP']);
        
        $last_id = $pdo->lastInsertId();
        $final_qr = "{$part_no}|{$date_print}|R{$last_id}J{$ref_no}";
        $pdo->prepare("UPDATE receiving_log_ytec SET generated_qr = ? WHERE id = ?")->execute([$final_qr, $last_id]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Success: $part_no", 
        'data' => [
            'part_no' => $part_no, 
            'erp_code' => $erp_code, 
            'part_name' => $part_name, 
            'seq_no' => $seq_no, 
            'input_type' => $input_type, 
            'time' => date('H:i:s'),
            'extra' => ($supplier === 'YTEC' ? $inv_no : $serial_no)
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>