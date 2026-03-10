<?php
require_once 'db.php';

// 1. Session & Security Check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

include 'layout/header.php';
$page_title = 'Main Dashboard';

// --- 2. DATA FETCHING (Make the dashboard alive) ---
$today = date('Y-m-d');
$incoming_count = 0;
$fg_count = 0;
$pending_rack = 0;

try {
    // A. Count Incoming Today (Sum of YTEC, MAZDA, MARZ)
    // Note: Adjust table names if your specific DB uses different variations
    $stmt_ytec = $pdo->prepare("SELECT COUNT(*) FROM receiving_log_ytec WHERE DATE(scan_time) = ?");
    $stmt_ytec->execute([$today]);
    $incoming_count += $stmt_ytec->fetchColumn();

    $stmt_mazda = $pdo->prepare("SELECT COUNT(*) FROM receiving_log_mazda WHERE DATE(scan_time) = ?");
    $stmt_mazda->execute([$today]);
    $incoming_count += $stmt_mazda->fetchColumn();

    // B. Count Finish Goods Generated Today
    $stmt_fg = $pdo->prepare("SELECT COUNT(*) FROM transfer_tickets WHERE DATE(created_at) = ?");
    $stmt_fg->execute([$today]);
    $fg_count = $stmt_fg->fetchColumn();

    // C. Check Racking Status (Items received but not yet racked)
    // This logic assumes items in receiving logs that aren't in racking_in table are pending
    // For performance, we just show a static "Check Pending" or simple query in a real scenario.
    // Here we will just display the live counts we have.

} catch (PDOException $e) {
    // Silently fail metrics on dashboard to not break the UI
    error_log("Dashboard metric error: " . $e->getMessage());
}
?>

<div class="container mx-auto px-4 py-8 min-h-[80vh] flex flex-col justify-center">

    <div class="text-center mb-12 animate-fade-in">
        <h1 class="text-4xl md:text-5xl font-black text-slate-800 mb-2 tracking-tight">
            WAREHOUSE <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">OPERATIONS</span>
        </h1>
        <p class="text-slate-500 text-lg">Select a module to begin your operations.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-6xl mx-auto w-full px-4">

        <a href="dashboard_incoming.php" class="group relative bg-white rounded-[2rem] border border-slate-200 shadow-xl hover:shadow-2xl hover:shadow-cyan-500/20 transition-all duration-300 overflow-hidden hover:-translate-y-1">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-full opacity-10 group-hover:scale-150 transition-transform duration-500"></div>
            
            <div class="p-8 relative z-10 h-full flex flex-col">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center shadow-inner group-hover:bg-cyan-600 group-hover:text-white transition-colors duration-300">
                        <i data-lucide="package-plus" class="w-8 h-8"></i>
                    </div>
                    <div class="text-right">
                        <span class="block text-3xl font-black text-slate-800 group-hover:text-cyan-600 transition-colors"><?php echo $incoming_count; ?></span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Received Today</span>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-slate-800 mb-2">Incoming Dashboard</h2>
                <p class="text-slate-500 text-sm mb-6 flex-grow">
                    Manage supplier receipts, verify incoming stock, racking placement, and unboxing processes.
                </p>

                <div class="grid grid-cols-2 gap-3 mt-auto">
                    <object>
                        <a href="receiving_in.php" class="flex items-center justify-center gap-2 py-2.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                            <i data-lucide="scan-line" class="w-4 h-4"></i> Receive
                        </a>
                    </object>
                    <object>
                        <a href="racking_in.php" class="flex items-center justify-center gap-2 py-2.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                            <i data-lucide="layers" class="w-4 h-4"></i> Rack In
                        </a>
                    </object>
                </div>
            </div>
        </a>

        <a href="dashboard_fg.php" class="group relative bg-white rounded-[2rem] border border-slate-200 shadow-xl hover:shadow-2xl hover:shadow-indigo-500/20 transition-all duration-300 overflow-hidden hover:-translate-y-1">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full opacity-10 group-hover:scale-150 transition-transform duration-500"></div>
            
            <div class="p-8 relative z-10 h-full flex flex-col">
                <div class="flex justify-between items-start mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shadow-inner group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                        <i data-lucide="truck" class="w-8 h-8"></i>
                    </div>
                    <div class="text-right">
                        <span class="block text-3xl font-black text-slate-800 group-hover:text-indigo-600 transition-colors"><?php echo $fg_count; ?></span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Tickets Today</span>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-slate-800 mb-2">Finish Good Dashboard</h2>
                <p class="text-slate-500 text-sm mb-6 flex-grow">
                    Generate transfer tickets, manage warehouse flow, PNE processing, and final shipment to customer.
                </p>

                <div class="grid grid-cols-2 gap-3 mt-auto">
                    <object>
                        <a href="tt.php" class="flex items-center justify-center gap-2 py-2.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                            <i data-lucide="ticket" class="w-4 h-4"></i> New Ticket
                        </a>
                    </object>
                    <object>
                        <a href="track_ticket.php" class="flex items-center justify-center gap-2 py-2.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                            <i data-lucide="ticket" class="w-4 h-4"></i> Track Ticket
                        </a>
                    </object>
                </div>
            </div>
        </a>

    </div>

    <div class="max-w-4xl mx-auto w-full mt-12">
        <div class="bg-white/60 backdrop-blur-md rounded-xl p-4 border border-white/50 shadow-sm flex flex-wrap justify-center gap-4">
            <a href="track_ticket.php" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 transition-all">
                <i data-lucide="search" class="w-4 h-4"></i> Track Ticket
            </a>
            <div class="w-px h-6 bg-slate-300 hidden sm:block"></div>
            <button onclick="window.location.reload()" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 transition-all">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh Data
            </button>
        </div>
    </div>

</div>

<script>
    // Initialize Icons
    lucide.createIcons();
</script>

<?php include 'layout/footer.php'; ?>