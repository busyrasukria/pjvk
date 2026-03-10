<?php
require_once 'db.php';

// 1. Start the session FIRST, before any output.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Check for login and redirect BEFORE any HTML is included.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// 3. NOW that we are sure the user is logged in, we can safely include the HTML header.
include 'layout/header.php'; // Includes auth check
$page_title = 'PNE In to Warehouse'; // Set page title for header

// --- Fetch Models for Filter Dropdown ---
try {
    $stmt_models = $pdo->prepare("SELECT DISTINCT model FROM master ORDER BY model");
    $stmt_models->execute();
    $models = $stmt_models->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $models = [];
}

// --- Fetch recent scans to display in the table on load ---
$recentScans = [];
try {
    // === CHANGED TABLE TO pne_warehouse_in ===
    $stmt = $pdo->prepare("
        SELECT 
            wil.*,  -- Select all columns from pne_warehouse_in
            m.part_no_B,
            m.erp_code_B
        FROM pne_warehouse_in wil
        LEFT JOIN master m ON wil.erp_code_FG = m.erp_code_FG
        ORDER BY wil.scan_time DESC
        LIMIT 50
    ");
    $stmt->execute();
    $recentScans = $stmt->fetchAll();
} catch (PDOException $e) {
    // You could set an error message here
}
?>

<div class="container mx-auto px-6 py-10">
    <div class="bg-gradient-to-r from-teal-600 via-cyan-600 to-blue-500 rounded-xl shadow-2xl px-6 py-5 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="text-xl md:text-2xl font-bold flex items-center space-x-3">
                    <i data-lucide="scan-line" class="w-8 h-8"></i>
                    <span>PNE In to Warehouse</span>
                </h2>
                <p class="text-cyan-100 text-sm mt-1">
                    Scan tickets or use "Manual Entry" to scan items in from PNE.
                </p>
            </div>
            
            <div class="flex items-center gap-4 w-full md:w-auto">
                <button id="manualEntryBtn" 
                        title="Manually enter a ticket"
                        class="bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105
                               w-48">
                    <i data-lucide="keyboard" class="w-5 h-5"></i>
                    <span>MANUAL</span>
                </button>
                <button id="startScanBtn" 
                        class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105
                               w-48 disabled:opacity-50 disabled:cursor-not-allowed disabled:scale-100">
                    <i data-lucide="play-circle" class="w-5 h-5"></i>
                    <span>START SCAN</span>
                </button>
                <button id="stopScanBtn" 
                        class="bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105
                               w-48 disabled:opacity-50 disabled:cursor-not-allowed disabled:scale-100" 
                        disabled>
                    <i data-lucide="stop-circle" class="w-5 h-5"></i>
                    <span>STOP SCAN</span>
                </button>
                <div class="bg-white/30 text-white p-4 rounded-lg text-center shadow-inner">
                    <span class="block text-xs font-semibold uppercase tracking-wider">Count</span>
                    <span id="scanCount" class="block text-3xl font-bold">0</span>
                </div>
            </div>
        </div>
    </div>

    <div id="scanFormContainer" class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 mb-8 hidden">
        <form id="scanForm">
            <label for="scanInput" class="block text-sm font-semibold text-gray-700 mb-2">Scan QR Code Here</label>
            <div class="relative">
                <i data-lucide="barcode" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-6 h-6"></i>
                <input type="text" id="scanInput" name="qr_data"
                       class="w-full pl-14 pr-4 py-4 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md text-lg"
                       placeholder="Scanner is active..."
                       autocomplete="off"
                       disabled> </div>
        </form>
        <div id="scanResult" class="mt-4 text-center h-5 font-semibold text-gray-500">Scan Standby</div>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        
        <div class="bg-gradient-to-r from-teal-600 via-cyan-600 to-blue-500 px-6 py-5 flex flex-col md:flex-row justify-between md:items-center gap-4 shadow-inner">
            <h3 class="text-xl font-bold text-white flex items-center tracking-wide drop-shadow-sm">
                <i data-lucide="history" class="w-6 h-6 mr-3 text-cyan-200"></i>
                <span>Recent PNE In Scan History</span>
            </h3>
            <div class="flex flex-wrap gap-3">
                <div class="relative">
                    <i data-lucide="filter" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <select id="modelFilter" class="pl-9 pr-6 py-2 rounded-lg text-sm border border-gray-300 bg-white focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none shadow-sm hover:shadow-md">
                        <option value="">All Models</option>
                        <?php foreach ($models as $model): ?>
                            <option value="<?php echo htmlspecialchars($model); ?>">
                                <?php echo htmlspecialchars($model); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="relative">
                    <i data-lucide="calendar-days" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="date" id="dateFilterInput"
                           class="pl-10 pr-4 py-2 rounded-lg text-sm border border-gray-300 text-gray-700 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-all duration-200 outline-none shadow-sm hover:shadow-md w-full md:w-auto" />
                </div>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="historySearchInput" 
                           placeholder="Search (TT ID, ERP, Part No, Released By...)"
                           class="pl-10 pr-4 py-2 rounded-lg text-sm border border-gray-300 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-all duration-200 outline-none shadow-sm hover:shadow-md w-full md:w-72" />
                </div>
                
                <button id="refreshBtn" title="Clear Filters"
                        class="p-2 rounded-lg text-sm border border-gray-300 bg-white text-gray-700
                               focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500
                               transition-all duration-200 outline-none shadow-sm hover:shadow-md
                               flex items-center gap-1.5">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-cyan-600"></i>
                    <span>Refresh</span>
                </button>
                <button id="downloadCsvBtn" title="Download as CSV"
                        class="p-2 rounded-lg text-sm border border-gray-300 bg-white text-gray-700
                            focus:ring-2 focus:ring-green-500 focus:border-green-500
                            transition-all duration-200 outline-none shadow-sm hover:shadow-md
                            flex items-center gap-1.5">
                    <i data-lucide="download" class="w-4 h-4 text-green-600"></i>
                    <span>CSV</span>
                </button>
            </div>
        </div>


        <div class="overflow-x-auto custom-scrollbar">
            <table id="scanHistoryTable" class="w-full text-sm">
                
                <thead class="sticky top-0 z-10"> 
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Date/Time PNE In</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">TT ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Prod Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Part Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Part No (FG)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">ERP Code (FG)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Part No (B)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">ERP Code (B)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Model</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Prod Area</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Released By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Action</th>
                    </tr>
                </thead>   

                <tbody id="scanHistoryTableBody" class="bg-white divide-y divide-gray-200">
                    <?php if (empty($recentScans)): ?>
                        <tr>
                            <td colspan="13" class="text-center py-10 text-gray-500">
                                <i data-lucide="inbox" class="w-12 h-12 mx-auto text-gray-300"></i>
                                No recent scans found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($recentScans as $scan): ?>
                        <tr data-log-id="<?php echo $scan['log_id']; ?>">
                            <td class="px-4 py-4 text-gray-600 whitespace-nowrap">
                                <?php echo date('d/m/Y H:i:s', strtotime($scan['scan_time'])); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="font-semibold text-blue-700">
                                    <?php echo htmlspecialchars($scan['unique_no']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-gray-500 whitespace-nowrap">
                                <?php echo date('d/m/Y', strtotime($scan['prod_date'])); ?>
                            </td>
                            <td class="px-4 py-4 text-gray-800 font-medium"><?php echo htmlspecialchars($scan['part_name']); ?></td>
                            <td class="px-4 py-4 text-gray-800 font-medium"><?php echo htmlspecialchars($scan['part_no_FG']); ?></td>
                            <td class="px-4 py-4 text-sm font-mono text-indigo-700"><?php echo htmlspecialchars($scan['erp_code_FG']); ?></td>
                            <td class="px-4 py-4 text-gray-800 font-medium"><?php echo htmlspecialchars($scan['part_no_B'] ?? '-'); ?></td>
                            <td class="px-4 py-4 text-sm font-mono text-gray-600"><?php echo htmlspecialchars($scan['erp_code_B'] ?? '-'); ?></td>
                            <td class="px-4 py-4 text-gray-800 font-medium"><?php echo htmlspecialchars($scan['model']); ?></td>
                            <td class="px-4 py-4 text-gray-700"><?php echo htmlspecialchars($scan['prod_area']); ?></td>
                            <td class="px-4 py-4">
                                <span class="font-bold text-emerald-700">
                                    <?php echo $scan['quantity']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-gray-700">
                                <?php echo htmlspecialchars($scan['released_by']); ?>
                            </td>
                            <td class="px-4 py-4">
                                <button 
                                    onclick="deletePneInScan(<?php echo $scan['log_id']; ?>, this)"
                                    class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold">
                                    <i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div> 
        <div id="paginationControls" class="flex justify-center items-center py-4 px-6 border-t border-gray-200 bg-gray-50">
            </div>

    </div> 
</div> <div id="manualEntryModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-start justify-center z-50 opacity-0 invisible transition-opacity duration-300 py-10">
    
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl mx-4 transform scale-95 transition-transform duration-300 hover:scale-100 flex flex-col max-h-full">
        
        <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-8 py-6 rounded-t-3xl flex items-center justify-between flex-shrink-0">
            <h2 class="text-xl md:text-2xl font-bold flex items-center space-x-3">
                    <i data-lucide="scan-line" class="w-8 h-8"></i>
                    <span>PNE In to Warehouse - Scan Ticket</span>
                </h2>
            <button type="button" id="closeManualModal" class="text-white/80 hover:text-red-600 text-2xl transition-colors duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="manualEntryForm" class="p-8 overflow-y-auto">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="manual_ticket_id" class="block text-sm font-semibold text-gray-700 mb-2">Ticket ID (Unique No) *</label>
                    <input type="text" id="manual_ticket_id" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md"
                           placeholder="e.g., 00000490">
                </div>
                <div>
                    <label for="manual_erp_code_fg" class="block text-sm font-semibold text-gray-700 mb-2">ERP Code (FG) *</label>
                    <input type="text" id="manual_erp_code_fg" required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md"
                           placeholder="e.g., AA031299">
                </div>
            </div>

            <div class="flex items-center justify-center mb-6">
                <button type="button" id="fetchDetailsBtn"
                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-xl transition-all duration-200 flex items-center space-x-2 shadow-md hover:shadow-lg">
                    <i data-lucide="search" class="w-5 h-5"></i>
                    <span>Fetch Ticket Details</span>
                </button>
            </div>

            <div id="manualDetailsContainer" class="hidden">
                <hr class="my-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Ticket Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">Prod Date:</span>
                        <span id="manual_prod_date" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner md:col-span-2">
                        <span class="text-gray-500 font-medium text-xs">Part Name:</span>
                        <span id="manual_part_name" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">Part No (FG):</span>
                        <span id="manual_part_no_fg" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">Part No (B):</span>
                        <span id="manual_part_no_b" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">ERP Code (B):</span>
                        <span id="manual_erp_code_b" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">Model:</span>
                        <span id="manual_model" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">Prod Area:</span>
                        <span id="manual_prod_area" class="block font-semibold text-gray-900">-</span>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner">
                        <span class="text-gray-500 font-medium text-xs">Quantity:</span>
                        <span id="manual_quantity" class="block font-bold text-green-600 text-lg">-</span>
                    </div>
                     <div class="bg-gray-50 px-4 py-3 rounded-xl shadow-inner md:col-span-3">
                        <span class="text-gray-500 font-medium text-xs">Released By:</span>
                        <span id="manual_released_by" class="block font-semibold text-gray-900">-</span>
                    </div>

                </div>
            </div>

            <div id="manualStatusMessage" class="mt-4 text-center h-5 font-semibold text-gray-500"></div>

            <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                <button type="button" id="cancelManualEntry"
                    class="px-6 py-3 bg-red-500 text-white font-semibold rounded-xl hover:bg-red-600 transition-all duration-200 shadow-sm hover:shadow-md">
                    Cancel
                </button>
                <button type="button" id="submitManualEntry"
                        class="px-8 py-3 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white font-bold rounded-xl transition-all duration-200 flex items-center space-x-2 shadow-md hover:shadow-lg
                               disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    <i data-lucide="save" class="w-5 h-5"></i>
                    <span>Submit Scan In</span>
                </button>
            </div>
        </form>
    </div>
</div>
<script src="assets/pne_in.js"></script>
<script>
    lucide.createIcons();
</script>

<?php include 'layout/footer.php'; ?>