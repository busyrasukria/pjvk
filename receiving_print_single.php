<?php
require_once 'db.php';
require_once 'phpqrcode/qrlib.php';

$id = $_GET['id'] ?? '';
$supplier = $_GET['supplier'] ?? '';

if (!$id || !$supplier) die("Invalid Request");

// 1. Determine Table
$tables = [
    'YTEC' => 'receiving_log_ytec',
    'MAZDA' => 'receiving_log_mazda',
    'MASZ' => 'receiving_log_marz'
];

$table = $tables[$supplier] ?? null;
if (!$table) die("Unknown Supplier");

// 2. Fetch Item
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) die("Record not found");

// 3. Prepare QR Temp
$qr_dir = 'qr_temp/';
if (!is_dir($qr_dir)) mkdir($qr_dir);

// --- LOGIC SETUP ---

// A. ID Logic
if ($supplier === 'MAZDA') {
    $tag_id = "M" . $item['id'] . "S" . $item['scat_no'];
} 
elseif ($supplier === 'MASZ') {
    $tag_id = "MZ" . $item['id'] . "S" . $item['scat_no'];
} 
else {
    $tag_id = "R" . $item['id'] . "J" . $item['job_no'];
}

// B. Date Logic
$dbDate = strtotime($item['scan_time']);
$date_display = date('d Y', $dbDate); 
$month_display = date('m', $dbDate); 

// C. Extra Info Logic (Invoice or Serial)
$extra_info = '';
if ($supplier === 'YTEC') {
    $extra_info = $item['inv_no'] ?? '';
} else {
    // Mazda and Masz use Serial
    $extra_info = $item['serial'] ?? '';
}

// D. QR Generation
$qr_content = $item['generated_qr']; 
$qr_filename = $qr_dir . 'single_' . $item['id'] . '.png';
QRcode::png($qr_content, $qr_filename, QR_ECLEVEL_L, 2, 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reprint PEPS Tag</title>
    <style>
        /* --- PAGE SETUP --- */
        @page {
            size: 90mm 60mm; 
            margin: 0; 
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #e5e5e5; 
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        /* --- STICKER CONTAINER --- */
        .sticker {
            width: 90mm;
            height: 59mm; 
            background: white;
            border: 2px solid black;
            box-sizing: border-box;
            margin: 0 auto; 
            display: grid;
            grid-template-rows: 10mm 1fr 10mm; 
            overflow: hidden;
        }

        /* --- UNIFORM GRID ROWS --- */
        .row-header, .row-body, .row-footer {
            display: grid;
            grid-template-columns: 1fr 1fr; 
        }

        .row-header { border-bottom: 2px solid black; }
        .row-footer { border-top: 2px solid black; }

        /* --- CELLS --- */
        .cell {
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }
        
        .border-right { border-right: 2px solid black; }

        /* --- TYPOGRAPHY --- */
        .value-text { font-size: 11pt; font-weight: bold; }
        
        .month-text {
            font-size: 75pt; 
            font-weight: bold;
            line-height: 0.8;
            letter-spacing: -2px; 
        }

        .seq-text {
            font-size: 32pt;
            font-weight: bold;
            margin-top: 2mm;
            margin-bottom: 1mm;
            line-height: 1;
        }

        .footer-text { font-size: 10pt; font-weight: bold; }

        /* --- PRINT SETTINGS --- */
        @media print {
            body { 
                background-color: white; 
                display: block; 
                height: auto;
            }
            .sticker { 
                margin: 0; 
                border: 2px solid black; 
            }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="sticker">
        
        <div class="row-header">
            <div class="cell border-right"><span class="value-text"><?php echo htmlspecialchars($item['part_no']); ?></span></div>
            <div class="cell"><span class="value-text"><?php echo htmlspecialchars($item['erp_code']); ?></span></div>
        </div>

        <div class="row-body">
            <div class="cell border-right"><span class="month-text"><?php echo $month_display; ?></span></div>
            <div class="cell" style="flex-direction: column; justify-content: flex-start;">
                <div class="seq-text"><?php echo htmlspecialchars($item['seq_no']); ?></div>
                <img src="<?php echo $qr_filename; ?>" style="width: 15mm; height: 15mm;">
                <div style="font-size: 10pt; font-weight: bold; margin-top: 2px;">
                    <?php echo htmlspecialchars($extra_info); ?>
                </div>
            </div>
        </div>

        <div class="row-footer">
            <div class="cell border-right"><span class="footer-text"><?php echo $date_display; ?></span></div>
            <div class="cell"><span class="footer-text"><?php echo $tag_id; ?></span></div>
        </div>

    </div>

</body>
</html>