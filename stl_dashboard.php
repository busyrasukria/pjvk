<?php
// stl_dashboard.php - Enterprise Live Progress Tracking Board
require_once 'db.php';
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

$page_title = 'STL Live Dashboard'; // For header.php title

// Include the global system header
include 'layout/header.php'; 

$today = date('Y-m-d');

// Fetch the 100 most recent STL Orders
$stmt = $pdo->query("SELECT * FROM stl_orders ORDER BY requested_at DESC LIMIT 100");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate KPI Metrics
$total_today = 0;
$pending_supply = 0;
$pending_receive = 0;
$completed_today = 0;

foreach ($orders as $order) {
    // Check if the order was requested today
    if (strpos($order['requested_at'], $today) === 0) {
        $total_today++;
        if ($order['status'] === 'COMPLETED') {
            $completed_today++;
        }
    }
    // Total pending across all dates
    if ($order['status'] === 'PENDING_SUPPLY') $pending_supply++;
    if ($order['status'] === 'PENDING_RECEIVE') $pending_receive++;
}
?>

<style>
    .custom-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
    .pulse-dot { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .5; transform: scale(1.2); } }
</style>

<div class="flex-grow p-4 md:p-6 lg:p-8 max-w-[1800px] mx-auto w-full">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 animate-fade-in">
        <div class="flex items-center gap-4">
            <div class="bg-blue-600 text-white w-12 h-12 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                <i data-lucide="activity" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="font-black text-2xl text-gray-800 uppercase tracking-tight leading-tight">
                    Stock Transfer List Dashboard
                </h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-2 h-2 rounded-full bg-green-500 pulse-dot"></span>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Live Updates Enabled</p>
                </div>
            </div>
        </div>
        
        <a href="stl.php" class="group flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-6 py-3 rounded-xl shadow-[0_8px_15px_rgba(37,99,235,0.2)] hover:shadow-[0_12px_20px_rgba(37,99,235,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
            <i data-lucide="plus-circle" class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300"></i> START NEW PROCESS
        </a>
    </div>
    
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8 animate-fade-in">
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm flex items-center justify-between group hover:border-blue-300 transition-colors">
            <div>
                <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Today's Requests</p>
                <h3 class="text-3xl font-black text-gray-800"><?php echo $total_today; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
                <i data-lucide="file-text" class="w-6 h-6"></i>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm flex items-center justify-between group hover:border-teal-300 transition-colors">
            <div>
                <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Awaiting Supply</p>
                <h3 class="text-3xl font-black text-teal-600"><?php echo $pending_supply; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 group-hover:scale-110 transition-transform">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm flex items-center justify-between group hover:border-amber-300 transition-colors">
            <div>
                <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Awaiting Receive</p>
                <h3 class="text-3xl font-black text-amber-500"><?php echo $pending_receive; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform">
                <i data-lucide="truck" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm flex items-center justify-between group hover:border-green-300 transition-colors">
            <div>
                <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Completed Today</p>
                <h3 class="text-3xl font-black text-green-600"><?php echo $completed_today; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-green-600 group-hover:scale-110 transition-transform">
                <i data-lucide="check-circle" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center mb-6 animate-fade-in" style="animation-delay: 0.1s;">
        <div class="relative w-full md:max-w-md">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                <i data-lucide="search" class="w-5 h-5"></i>
            </div>
            <input type="text" id="searchInput" onkeyup="filterDashboard()" placeholder="Search FG Code, Model, Line..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-lg font-bold text-gray-700 outline-none focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-blue-400 transition-all">
        </div>
        
        <div class="flex gap-2 w-full md:w-auto">
            <select id="statusFilter" onchange="filterDashboard()" class="flex-1 md:w-auto p-3 bg-gray-50 border border-gray-200 rounded-lg font-bold text-gray-600 outline-none focus:border-blue-400 cursor-pointer">
                <option value="ALL">All Statuses</option>
                <option value="PENDING_SUPPLY">Awaiting Supply</option>
                <option value="PENDING_RECEIVE">Awaiting Receive</option>
                <option value="COMPLETED">Completed</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6 pb-12" id="dashboardGrid">
        <?php if(empty($orders)): ?>
            <div class="col-span-full text-center py-16 text-gray-400 bg-white rounded-2xl border border-dashed border-gray-300">
                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-4 text-gray-300"></i>
                <p class="font-bold text-lg">No orders found in the system.</p>
            </div>
        <?php else: ?>
            <?php foreach($orders as $index => $order): 
                $is_sup = $order['status'] === 'PENDING_SUPPLY';
                $is_rec = $order['status'] === 'PENDING_RECEIVE';
                $is_com = $order['status'] === 'COMPLETED';

                $status_badge = '';
                if($is_sup) $status_badge = '<span class="bg-teal-100 text-teal-700 px-3 py-1 rounded-md text-[10px] font-black tracking-widest uppercase border border-teal-200">Supply Pending</span>';
                if($is_rec) $status_badge = '<span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-md text-[10px] font-black tracking-widest uppercase border border-amber-200">Receive Pending</span>';
                if($is_com) $status_badge = '<span class="bg-green-100 text-green-700 px-3 py-1 rounded-md text-[10px] font-black tracking-widest uppercase border border-green-200">Completed</span>';
            ?>
            <div class="order-card bg-white rounded-2xl p-6 border border-gray-200 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden group animate-fade-in cursor-pointer" data-status="<?php echo $order['status']; ?>" style="animation-delay: <?php echo min($index * 0.05, 0.5); ?>s;" onclick="window.location.href='stl.php?model=<?php echo urlencode($order['model']); ?>&line=<?php echo urlencode($order['line']); ?>'">
                
                <div class="absolute top-0 left-0 bottom-0 w-1.5 <?php echo $is_sup ? 'bg-teal-400' : ($is_rec ? 'bg-amber-400' : 'bg-green-400'); ?>"></div>

                <div class="flex justify-between items-start mb-4 pl-2">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h3 class="font-black text-gray-800 text-lg">TRIP <?php echo $order['trip_number']; ?></h3>
                            <span class="text-[10px] bg-gray-800 text-white px-2 py-0.5 rounded uppercase font-bold"><?php echo $order['shift']; ?></span>
                            <span class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded font-black tracking-wider shadow-sm">
                                ID: #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                            </span>
                        </div>
                        <p class="text-xs font-bold text-gray-500">Model: <span class="text-blue-600"><?php echo htmlspecialchars($order['model']); ?></span> <span class="mx-1">|</span> Line <?php echo htmlspecialchars($order['line']); ?></p>
                    </div>
                    <div class="text-right">
                        <?php echo $status_badge; ?>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 mb-5 border border-gray-100 pl-4 ml-2">
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">FG Code</p>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Variance</p>
                    </div>
                    <div class="flex justify-between items-center">
                        <p class="font-bold text-gray-800"><?php echo htmlspecialchars($order['fg_code']); ?></p>
                        <p class="font-bold text-blue-600"><?php echo htmlspecialchars($order['variance'] ?: 'N/A'); ?></p>
                    </div>
                </div>

                <div class="pl-2 mb-5">
                    <p class="text-xs text-gray-500 font-medium mb-1"><i data-lucide="user" class="w-3.5 h-3.5 inline mr-1"></i> Req: <span class="font-bold text-gray-700"><?php echo htmlspecialchars($order['requested_by']); ?></span></p>
                    <p class="text-xs text-gray-500 font-medium"><i data-lucide="clock" class="w-3.5 h-3.5 inline mr-1"></i> Time: <span class="font-bold text-gray-700"><?php echo date('d M Y, h:i A', strtotime($order['requested_at'])); ?></span></p>
                </div>

                <div class="pl-2 mt-auto">
                    <div class="flex justify-between text-[10px] font-black uppercase tracking-widest mb-2">
                        <span class="text-teal-600">Req</span>
                        <span class="<?php echo ($is_rec || $is_com) ? 'text-amber-600' : 'text-gray-300'; ?>">Sup</span>
                        <span class="<?php echo $is_com ? 'text-green-600' : 'text-gray-300'; ?>">Recv</span>
                    </div>
                    <div class="flex h-2.5 gap-1 rounded-full overflow-hidden bg-gray-100">
                        <div class="w-1/3 bg-teal-500"></div>
                        <div class="w-1/3 <?php echo ($is_rec || $is_com) ? 'bg-amber-500' : 'bg-gray-200'; ?>"></div>
                        <div class="w-1/3 <?php echo $is_com ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
                    </div>
                </div>

                <div class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <a href="stl_form.php?export_csv=<?php echo $order['id']; ?>" onclick="event.stopPropagation();" class="bg-gray-800 hover:bg-blue-600 text-white p-2.5 rounded-lg shadow-lg flex items-center justify-center transition-colors" title="Download Official CSV">
                        <i data-lucide="download" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // JS Auto-refresh every 60 seconds (60000 ms)
    setTimeout(function(){
        window.location.reload();
    }, 60000);

    // Smart Live Filter
    function filterDashboard() {
        const searchVal = document.getElementById('searchInput').value.toLowerCase();
        const statusVal = document.getElementById('statusFilter').value;
        const cards = document.querySelectorAll('.order-card');

        cards.forEach(card => {
            const text = card.innerText.toLowerCase();
            const status = card.getAttribute('data-status');
            
            let matchesSearch = text.includes(searchVal);
            let matchesStatus = (statusVal === 'ALL') || (status === statusVal);

            if (matchesSearch && matchesStatus) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
</script>

<?php include 'layout/footer.php'; ?>