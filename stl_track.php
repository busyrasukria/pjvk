<?php
session_start();
require_once 'db.php';
include 'layout/header.php';

$search_query = trim($_GET['search'] ?? '');
$order_info = null;
$child_parts = [];
$fg_ticket = null;
$fg_history = [];
$search_type = '';
$error_message = '';

if ($search_query) {
    if (is_numeric($search_query) && strlen($search_query) < 10) {
        $order_id = $search_query;
        $search_type = 'Order ID';
    } else {
        $stmt_find = $pdo->prepare("SELECT order_id FROM stl_traceability WHERE unique_no = ?");
        $stmt_find->execute([$search_query]);
        $found = $stmt_find->fetch(PDO::FETCH_ASSOC);
        
        if ($found) {
            $order_id = $found['order_id'];
            $search_type = 'Child Part Tag';
        } else {
            $error_message = "Could not find production history for Tag: " . htmlspecialchars($search_query);
        }
    }

    if (isset($order_id)) {
        $stmt = $pdo->prepare("SELECT o.* FROM stl_orders o WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order_info) {
            $stmt_parts = $pdo->prepare("
                SELECT t.unique_no, t.erp_code, t.qty_supplied, r.date_in
                FROM stl_traceability t
                LEFT JOIN racking_in r ON t.unique_no = r.ID_CODE
                WHERE t.order_id = ?
            ");
            $stmt_parts->execute([$order_id]);
            $child_parts = $stmt_parts->fetchAll(PDO::FETCH_ASSOC);

            try {
                $stmt_ticket = $pdo->prepare("SELECT * FROM transfer_tickets WHERE stl_order_id = ? LIMIT 1");
                $stmt_ticket->execute([$order_id]);
                $fg_ticket = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

                if ($fg_ticket) {
                    $stmt_log = $pdo->prepare("SELECT * FROM ticket_status_log WHERE ticket_id = ? ORDER BY status_timestamp ASC");
                    $stmt_log->execute([$fg_ticket['ticket_id']]);
                    $fg_history = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $fg_ticket = null; 
            }
        } else {
            $error_message = "STL Order #" . htmlspecialchars($order_id) . " not found.";
        }
    }
}

// Calculate 5-Step Progress Stage
$stage = 0; $progress_width = 0; $status_text = "Checking..."; $status_color = "bg-gray-100 text-gray-600";
$has_wh_in = false; $has_shipped = false; $wh_in_time = ''; $shipped_time = '';

if ($order_info) {
    $stage = 1; $progress_width = 0; $status_text = "Requested"; $status_color = "bg-yellow-100 text-yellow-700";
    
    if (!empty($order_info['supplied_at'])) {
        $stage = 2; $progress_width = 25; $status_text = "MPL Supplied"; $status_color = "bg-blue-100 text-blue-700";
    }
    if (!empty($order_info['received_at'])) {
        $stage = 3; $progress_width = 50; $status_text = "PROD Received"; $status_color = "bg-purple-100 text-purple-700";
    }
    
    if ($fg_ticket && !empty($fg_history)) {
        foreach ($fg_history as $log) {
            if ($log['status_code'] === 'WH_IN') {
                $has_wh_in = true; $wh_in_time = $log['status_timestamp'];
                $stage = 4; $progress_width = 75; $status_text = "FG In Stock"; $status_color = "bg-indigo-100 text-indigo-700";
            }
            if ($log['status_code'] === 'CUSTOMER_OUT' || $log['status_code'] === 'WH_OUT') {
                $has_shipped = true; $shipped_time = $log['status_timestamp'];
                $stage = 5; $progress_width = 100; $status_text = "Shipped / Whse Out"; $status_color = "bg-emerald-100 text-emerald-700";
            }
        }
    }
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
    .track-step { position: relative; z-index: 10; }
    .icon-box { width: 2.5rem; height: 2.5rem; font-size: 0.75rem; } 
    @media (min-width: 768px) { .icon-box { width: 3.5rem; height: 3.5rem; font-size: 1rem; } }
    .track-step.active .icon-box { background-color: #2563eb; border-color: #2563eb; color: white; transform: scale(1.1); box-shadow: 0 0 15px rgba(37, 99, 235, 0.4); }
    .track-step.completed .icon-box { background-color: #10b981; border-color: #10b981; color: white; }
    .progress-bar-container { position: absolute; top: 1.25rem; left: 0; width: 100%; height: 4px; z-index: 0; }
    @media (min-width: 768px) { .progress-bar-container { top: 1.75rem; } }
    .progress-bar-bg { width: 100%; height: 100%; background-color: #e5e7eb; }
    .progress-bar-fill { position: absolute; top: 0; left: 0; height: 100%; background-color: #10b981; transition: width 1s ease-in-out; }
    .timeline-line { position: absolute; left: 1.25rem; top: 2.5rem; bottom: 0; width: 2px; background: #e5e7eb; z-index: 0; }
</style>

<div class="flex flex-col min-h-[calc(100vh-100px)] bg-gray-50 font-sans">
    
    <div class="container mx-auto px-4 py-6 md:py-10 flex-grow">

        <div class="max-w-4xl mx-auto text-center mb-8">
            <h1 class="text-2xl md:text-4xl font-black text-slate-800 mb-2 tracking-tight uppercase">End-to-End Traceability</h1>
            <p class="text-sm md:text-base text-slate-500 mb-6">Trace completely from Raw Material Racking to Finished Goods Shipping.</p>

            <form method="GET" class="bg-white p-2 rounded-2xl shadow-lg border border-gray-200 flex flex-col md:flex-row gap-3 max-w-xl mx-auto">
                <div class="flex-1 relative">
                    <i data-lucide="scan-barcode" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" 
                           class="w-full pl-12 pr-4 py-3 bg-gray-50 md:bg-transparent rounded-xl outline-none font-bold text-gray-700" 
                           placeholder="Scan Order ID or Child Tag...">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl shadow-md">TRACK</button>
            </form>
        </div>

        <?php if ($order_info): ?>
        <div class="max-w-6xl mx-auto pb-20">

            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6 relative overflow-hidden">
                <div class="flex justify-between items-center mb-8">
                    <div>
                         <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Production Batch</span>
                         <span class="text-2xl md:text-3xl font-black text-blue-600 font-mono">#<?= $order_info['id'] ?></span>
                    </div>
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold shadow-sm <?= $status_color ?>">
                        <?= $status_text ?>
                    </span>
                </div>

                <div class="relative px-2 md:px-12">
                    <div class="progress-bar-container mx-8 md:mx-12 w-auto right-8 md:right-12">
                        <div class="progress-bar-bg rounded-full"></div>
                        <div class="progress-bar-fill rounded-full" style="width: <?= $progress_width ?>%;"></div>
                    </div>

                    <div class="flex justify-between items-start relative">
                        <div class="track-step flex flex-col items-center gap-2 <?= $stage >= 1 ? ($stage > 1 ? 'completed' : 'active') : '' ?>">
                            <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300">
                                <i data-lucide="file-plus" class="w-5 h-5"></i>
                            </div>
                            <span class="step-text text-[10px] md:text-xs font-bold uppercase text-center">Requested</span>
                        </div>

                        <div class="track-step flex flex-col items-center gap-2 <?= $stage >= 2 ? ($stage > 2 ? 'completed' : 'active') : '' ?>">
                            <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300">
                                <i data-lucide="package-search" class="w-5 h-5"></i>
                            </div>
                            <span class="step-text text-[10px] md:text-xs font-bold uppercase text-center">Supplied</span>
                        </div>

                        <div class="track-step flex flex-col items-center gap-2 <?= $stage >= 3 ? ($stage > 3 ? 'completed' : 'active') : '' ?>">
                            <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300">
                                <i data-lucide="settings" class="w-5 h-5"></i>
                            </div>
                            <span class="step-text text-[10px] md:text-xs font-bold uppercase text-center">Assembled</span>
                        </div>

                        <div class="track-step flex flex-col items-center gap-2 <?= $stage >= 4 ? ($stage > 4 ? 'completed' : 'active') : '' ?>">
                            <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300">
                                <i data-lucide="warehouse" class="w-5 h-5"></i>
                            </div>
                            <span class="step-text text-[10px] md:text-xs font-bold uppercase text-center">FG Stocked</span>
                        </div>

                        <div class="track-step flex flex-col items-center gap-2 <?= $stage >= 5 ? 'completed' : '' ?>">
                            <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300">
                                <i data-lucide="truck" class="w-5 h-5"></i>
                            </div>
                            <span class="step-text text-[10px] md:text-xs font-bold uppercase text-center">Shipped Out</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8">
                        <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                            <i data-lucide="history" class="w-5 h-5 text-blue-500"></i> Full Lifecycle
                        </h3>
                        
                        <div class="relative pl-2">
                            <div class="relative pl-10 pb-8 group">
                                <div class="timeline-line"></div>
                                <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center z-10 border-2 border-white shadow-sm">
                                    <i data-lucide="file-plus" class="w-5 h-5 text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">STL Requested</h4>
                                    <p class="text-xs text-slate-500"><?= $order_info['requested_at'] ?></p>
                                </div>
                            </div>

                            <?php if(!empty($order_info['supplied_at'])): ?>
                            <div class="relative pl-10 pb-8 group">
                                <div class="timeline-line"></div>
                                <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center z-10 border-2 border-white shadow-sm">
                                    <i data-lucide="package-search" class="w-5 h-5 text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">Child Parts Supplied</h4>
                                    <p class="text-xs text-slate-500">By: <?= htmlspecialchars($order_info['supplied_by']) ?> at <?= $order_info['supplied_at'] ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if(!empty($order_info['received_at'])): ?>
                            <div class="relative pl-10 pb-8 group">
                                <?= ($has_wh_in) ? '<div class="timeline-line"></div>' : '' ?>
                                <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center z-10 border-2 border-white shadow-sm">
                                    <i data-lucide="settings" class="w-5 h-5 text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">Production Assembled</h4>
                                    <p class="text-xs text-slate-500">By: <?= htmlspecialchars($order_info['received_by']) ?> at <?= $order_info['received_at'] ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if($has_wh_in): ?>
                            <div class="relative pl-10 pb-8 group">
                                <?= ($has_shipped) ? '<div class="timeline-line"></div>' : '' ?>
                                <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center z-10 border-2 border-white shadow-sm">
                                    <i data-lucide="warehouse" class="w-5 h-5 text-indigo-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">FG Entered Warehouse</h4>
                                    <p class="text-xs text-slate-500">Ticket: #<?= $fg_ticket['unique_no'] ?> at <?= $wh_in_time ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if($has_shipped): ?>
                            <div class="relative pl-10 pb-2 group">
                                <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center z-10 border-2 border-white shadow-sm">
                                    <i data-lucide="truck" class="w-5 h-5 text-emerald-600"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">Shipped to Customer</h4>
                                    <p class="text-xs text-slate-500">Dispatched at <?= $shipped_time ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if(!empty($order_info['received_at']) && !$fg_ticket): ?>
                                <div class="mt-4 p-3 bg-orange-50 rounded-lg text-xs text-orange-700 border border-orange-200">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i> Awaiting Transfer Ticket Generation for Finished Goods.
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="bg-white rounded-3xl shadow-xl border border-blue-100 overflow-hidden border-t-4 border-t-blue-500 p-6">
                        <h3 class="text-xs font-bold text-slate-400 uppercase mb-4">Finished Good Produced</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <span class="block text-xs text-slate-500">FG Code</span>
                                <span class="font-bold text-slate-800"><?= htmlspecialchars($order_info['fg_code']) ?></span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500">Model</span>
                                <span class="font-bold text-slate-800"><?= htmlspecialchars($order_info['model']) ?></span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500">Line</span>
                                <span class="font-bold text-slate-800"><?= htmlspecialchars($order_info['line']) ?></span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500">FG Ticket #</span>
                                <span class="font-bold <?= $fg_ticket ? 'text-indigo-600' : 'text-slate-400' ?>">
                                    <?= $fg_ticket ? '#' . $fg_ticket['unique_no'] : 'Pending' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2 mt-8">
                        <i data-lucide="blocks" class="w-5 h-5 text-blue-500"></i> Consumed Raw Materials
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($child_parts as $part): ?>
                        <div class="bg-white rounded-2xl shadow-md border border-slate-100 p-5 <?= ($search_type === 'Child Part Tag' && $search_query == $part['unique_no']) ? 'ring-2 ring-yellow-400' : '' ?>">
                            <div class="flex justify-between items-start mb-3 border-b border-slate-50 pb-2">
                                <span class="text-xs font-bold text-slate-400 uppercase">Tag ID</span>
                                <span class="font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($part['unique_no']) ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs text-slate-500">ERP Code</span>
                                <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($part['erp_code']) ?></span>
                            </div>
                            <div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-50">
                                <span class="text-xs text-slate-500">Qty Consumed</span>
                                <span class="font-black text-emerald-600"><?= htmlspecialchars($part['qty_supplied']) ?> pcs</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div> <script>document.addEventListener("DOMContentLoaded", () => lucide.createIcons());</script>

<?php include 'layout/footer.php'; ?>