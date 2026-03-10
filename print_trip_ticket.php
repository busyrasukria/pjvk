<?php
require_once 'db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$type = $_GET['type'] ?? '';
$model = $_GET['model'] ?? '';
$variant = $_GET['variant'] ?? '';
$trip = $_GET['trip'] ?? '';
$lot = $_GET['lot'] ?? ''; 
$msc_code_param = $_GET['msc_code'] ?? ''; 
$autoprint = isset($_GET['autoprint']) ? true : false;

$tripNumber = str_pad(str_replace('TRIP_', '', $trip), 2, '0', STR_PAD_LEFT);
$dateDisplay = date('d/m/Y');
$timeDisplay = date('H:i');
$combinedModel = "$model $variant ($type)";

$ticketItems = [];
$mscCode = $msc_code_param;
$finalLot = $lot; // Will update this if we auto-detect a different lot

try {
    $sqlMaster = "SELECT id FROM master_trip WHERE TYPE = ? AND MODEL = ? AND VARIANT = ? AND $trip > 0";
    $stmtM = $pdo->prepare($sqlMaster);
    $stmtM->execute([$type, $model, $variant]);
    $masterData = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    $masterIds = array_column($masterData, 'id');

    if (!empty($masterIds)) {
        $idsStr = implode(',', $masterIds);
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        // 1. Try to find scans with the SPECIFIC Lot No you typed
        $sqlLog = "SELECT ticket_qr, msc_code, erp_code, lot_no FROM warehouse_out 
                   WHERE master_trip_id IN ($idsStr) 
                   AND trip = ?
                   AND lot_no = ? 
                   AND scan_timestamp BETWEEN ? AND ?
                   ORDER BY scan_timestamp ASC";
                   
        $stmtL = $pdo->prepare($sqlLog);
        $stmtL->execute([$trip, $lot, $todayStart, $todayEnd]);
        $rows = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        // 2. FALLBACK: If 0 results, find ANY scans for this Trip Today (Auto-Detect Lot)
        if (empty($rows)) {
            $sqlLogFallback = "SELECT ticket_qr, msc_code, erp_code, lot_no FROM warehouse_out 
                               WHERE master_trip_id IN ($idsStr) 
                               AND trip = ?
                               AND scan_timestamp BETWEEN ? AND ?
                               ORDER BY scan_timestamp ASC";
            $stmtL2 = $pdo->prepare($sqlLogFallback);
            $stmtL2->execute([$trip, $todayStart, $todayEnd]);
            $rows = $stmtL2->fetchAll(PDO::FETCH_ASSOC);
            
            // If found, update the Lot No on the label to the REAL one from database
            if (!empty($rows)) {
                $finalLot = $rows[0]['lot_no']; 
            }
        }
        
        if (!empty($rows) && !empty($rows[0]['msc_code'])) {
            $mscCode = $rows[0]['msc_code'];
        }
        
        foreach ($rows as $row) {
            // Extract the ID from the QR code string: DATE|ID|ERP|...
            if (!empty($row['ticket_qr'])) {
                $parts = explode('|', $row['ticket_qr']);
                $extracted_id = $parts[1] ?? 'UNKNOWN'; 
                $ticketItems[] = ['id' => $extracted_id, 'erp' => $row['erp_code']];
            }
        }
    }
} catch (Exception $e) { }

$totalScanned = count($ticketItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trip Ticket - <?php echo $combinedModel; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Roboto+Mono:wght@500&display=swap');
        @page { size: 90mm 60mm; margin: 0; }
        html, body { height: 100%; margin: 0; padding: 0; width: 100%; }
        body { width: 90mm; margin: 0 auto; font-family: 'Arial', sans-serif; background: white; color: black; overflow: hidden; box-sizing: border-box; }
        .container { width: 100%; height: 55mm; border: 2px solid #000; display: flex; flex-direction: column; box-sizing: border-box; background: white; }
        .header { background: #000; color: #fff; text-align: center; padding: 0px; height: 4mm; display:flex; align-items:center; justify-content:center; }
        .header-title { font-family: 'Oswald', sans-serif; font-size: 9pt; letter-spacing: 1px; line-height: 1; }
        .grid-row { display: flex; border-bottom: 1px solid #000; height: 4.8mm; }
        .cell { flex: 1; padding: 0px 2px; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .cell:last-child { border-right: none; }
        .row-1 { height: 5.5mm; } .row-2 { height: 4.8mm; } .row-3 { height: 4.8mm; }
        .label { font-size: 4pt; color: #555; text-transform: uppercase; font-weight: bold; margin-bottom: 0px; line-height: 1; }
        .value { font-size: 7pt; font-weight: 900; text-align: center; line-height: 1; white-space: nowrap; }
        .trip-num { font-size: 10pt; font-weight: 900; line-height: 0.9; }
        .content-area { display: flex; border-bottom: 1px solid #000; flex-grow: 1; overflow: hidden; }
        .list-wrapper { flex-grow: 1; padding: 1px; display: flex; flex-direction: column; }
        .list-header { display: flex; justify-content: space-between; font-weight: bold; font-size: 5pt; border-bottom: 1px solid black; margin-bottom: 1px; padding-bottom: 0px; }
        .items-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 2mm; row-gap: 0px; font-family: 'Roboto Mono', monospace; font-size: 6.5pt; font-weight: 700; line-height: 1.05; }
        .item-row { display: flex; justify-content: space-between; align-items: center; }
        .vertical-banner { width: 5mm; background: #000; color: white; display: flex; align-items: center; justify-content: center; writing-mode: vertical-rl; text-orientation: mixed; font-weight: bold; font-size: 6pt; letter-spacing: 1px; border-left: 1px solid #000; }
        .footer { background: #000; color: white; padding: 0px 4px; display: flex; justify-content: space-between; align-items: center; font-size: 5.5pt; font-weight: bold; height: 3.5mm; margin-top: auto; }
        @media print { .no-print { display: none; } html, body { height: 100%; margin: 0; padding: 0; } .container { border: 2px solid #000; width: 100%; height: 55mm; } body { padding-top: 0; box-sizing: border-box; } }
    </style>
</head>
<body>
    <?php if(!$autoprint): ?>
    <div class="no-print" style="text-align:center; margin-bottom:10px;">
         <button onclick="window.print()" style="background:#007bff; color:white; padding:5px 10px; border:none; border-radius:3px;">Print</button>
    </div>
    <?php endif; ?>

    <div class="container">
        <div class="header"><div class="header-title">TRIP COMPLETE</div></div>
        <div class="grid-row row-1">
            <div class="cell" style="flex: 2.5;"><span class="label">Model / Variant / Type</span><span class="value"><?php echo $combinedModel; ?></span></div>
            <div class="cell" style="flex: 2.5;"><span class="label">Trip</span><span class="value trip-num"><?php echo $tripNumber; ?></span></div>
        </div>
        <div class="grid-row row-2">
            <div class="cell"><span class="label">MSC Code</span><span class="value"><?php echo $mscCode; ?></span></div>
            <div class="cell"><span class="label">Lot No</span><span class="value"><?php echo $finalLot; ?></span></div>
        </div>
        <div class="grid-row row-3">
            <div class="cell"><span class="label">Date</span><span class="value"><?php echo $dateDisplay; ?></span></div>
            <div class="cell"><span class="label">Time</span><span class="value"><?php echo $timeDisplay; ?></span></div>
        </div>
        <div class="content-area">
            <div class="list-wrapper">
                <div class="list-header"><span>TICKET ID (ERP)</span> <span>STATUS</span></div>
                <div class="items-grid">
                    <?php 
                    $limit = 24; $count = 0;
                    foreach($ticketItems as $item): 
                        if($count >= $limit) break;
                        $formatted_id = str_pad($item['id'], 8, '0', STR_PAD_LEFT);
                        $short_erp = $item['erp'] ? "({$item['erp']})" : "";
                    ?>
                    <div class="item-row">
                        <span>#<?php echo $formatted_id . ' ' . $short_erp; ?></span> 
                        <span style="font-weight:bold;">SCAN</span>
                    </div>
                    <?php $count++; endforeach; ?>
                </div>
                <?php if($totalScanned > $limit): ?><div style="text-align:center; font-size:5pt; margin-top:0px; font-style:italic;">+ <?php echo $totalScanned - $limit; ?> more...</div><?php endif; ?>
                <?php if(empty($ticketItems)): ?><div style="text-align:center; margin-top:5mm; color:#999; font-size:7pt;">NO ITEMS SCANNED</div><?php endif; ?>
            </div>
            <div class="vertical-banner">CONFIRMED</div>
        </div>
        <div class="footer"><span>PJVK WMS</span><span>TOTAL: <?php echo $totalScanned; ?> PCS &nbsp;|&nbsp; <?php echo $timeDisplay; ?></span></div>
    </div>
    <?php if($autoprint): ?><script>window.onload = function() { window.print(); setTimeout(function(){ window.close(); }, 2000); }</script><?php endif; ?>
</body>
</html>