<?php
require_once 'db.php';
require_once 'phpqrcode/qrlib.php';

$batch_id = $_GET['batch_id'] ?? '';
$supplier = strtoupper(trim($_GET['supplier'] ?? ''));

if (!$batch_id || !$supplier) {
    die("Error: Missing Batch ID or Supplier.");
}

// 1. Determine Table based on Supplier
$table_map = [
    'YTEC' => 'receiving_log_ytec',
    'MAZDA' => 'receiving_log_mazda',
    'MASZ' => 'receiving_log_marz'
];
$table = $table_map[$supplier] ?? null;

if (!$table) {
    die("Error: Unknown Supplier: " . htmlspecialchars($supplier));
}

// 2. Fetch Items from this Batch
$stmt = $pdo->prepare("SELECT * FROM $table WHERE batch_id = ? ORDER BY id ASC");
$stmt->execute([$batch_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($items) === 0) {
    die("No items found for this batch.");
}

// 3. Prepare QR Temp Directory
$qr_dir = 'qr_temp/';
if (!is_dir($qr_dir)) mkdir($qr_dir);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Tags</title>
    <style>
        @page { size: 90mm 60mm; margin: 0; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #e5e5e5; }
        .sticker {
            width: 90mm; height: 59mm; background: white; border: 2px solid black; box-sizing: border-box;
            margin: 0 auto; display: grid; grid-template-rows: 10mm 1fr 10mm; page-break-inside: avoid;
        }
        .row-header, .row-body, .row-footer { display: grid; grid-template-columns: 1fr 1fr; }
        .row-header { border-bottom: 2px solid black; }
        .row-footer { border-top: 2px solid black; }
        .cell {
            display: flex; justify-content: center; align-items: center; text-align: center;
            overflow: hidden; white-space: nowrap; position: relative;
        }
        .border-right { border-right: 2px solid black; }
        .value-text { font-size: 11pt; font-weight: bold; }
        .month-text { font-size: 75pt; font-weight: bold; line-height: 0.8; letter-spacing: -2px; }
        .seq-text { font-size: 32pt; font-weight: bold; margin-top: 2mm; margin-bottom: 1mm; line-height: 1; }
        .footer-text { font-size: 10pt; font-weight: bold; }
        @media print {
            body { background-color: white; }
            .sticker { margin: 0; border: 2px solid black; }
        }
    </style>
</head>
<body onload="window.print()">

    <?php 
    $total_items = count($items);
    $counter = 0;

    foreach ($items as $item): 
        $counter++;
        
        // --- SAFE ID GENERATION LOGIC ---
        $db_id = $item['id'];
        $scat = $item['scat_no'] ?? 'N/A';
        $job  = $item['job_no'] ?? 'N/A';

        if ($supplier === 'MAZDA') {
            $tag_id = "M" . $db_id . "S" . $scat;
        } 
        elseif ($supplier === 'MASZ') {
            $tag_id = "MZ" . $db_id . "S" . $scat;
        } 
        else {
            $tag_id = "R" . $db_id . "J" . $job;
        }

        // Date Logic
        $dbDate = strtotime($item['scan_time']);
        $date_display = date('d Y', $dbDate); 
        $month_display = date('m', $dbDate); 
        
        // --- EXTRA INFO LOGIC (Invoice/Serial) ---
        $extra_info = '';
        if ($supplier === 'YTEC') {
            $extra_info = $item['inv_no'] ?? '';
        } else {
            $extra_info = $item['serial'] ?? '';
        }

        // QR Generation
        $qr_content = $item['generated_qr']; 
        $qr_filename = $qr_dir . 'batch_' . $db_id . '.png';
        QRcode::png($qr_content, $qr_filename, QR_ECLEVEL_L, 2, 0);

        $break_style = ($counter < $total_items) ? 'page-break-after: always;' : 'page-break-after: auto;';
    ?>

    <div class="sticker" style="<?php echo $break_style; ?>">
        
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

    <?php endforeach; ?>

</body>
</html>