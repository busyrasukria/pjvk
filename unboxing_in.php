<?php
require_once 'db.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

include 'layout/header.php'; 
$page_title = 'Unboxing In'; 
?>

<div class="container mx-auto px-6 py-10">

    <div class="bg-gradient-to-r from-pink-700 via-rose-600 to-red-600 rounded-xl shadow-2xl px-6 py-5 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="text-xl md:text-2xl font-bold flex items-center space-x-3">
                    <i data-lucide="package-open" class="w-8 h-8"></i>
                    <span>Unboxing In</span>
                </h2>
                <p class="text-pink-100 text-sm mt-1">
                    Scan tags to unbox items. (Must be Racked First)
                </p>
            </div>
            
            <div class="flex items-center gap-4 w-full md:w-auto">
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
        <div class="max-w-3xl mx-auto">
            <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide text-center">Scan Tag ID</label>
            
            <div class="relative mb-4">
                <i data-lucide="qr-code" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-6 h-6"></i>
                <input type="text" id="scanInput" class="w-full pl-14 pr-4 py-4 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-pink-500 transition-all text-lg text-center font-mono" placeholder="Waiting for scan..." autocomplete="off"> 
            </div>

            <div class="flex justify-center mb-4">
                 <button type="button" id="manualEntryBtn" 
                        class="text-pink-600 bg-pink-50 hover:bg-pink-100 border border-pink-200 font-bold py-2 px-6 rounded-full transition-all flex items-center gap-2 text-sm">
                    <i data-lucide="keyboard" class="w-4 h-4"></i> Manual Entry
                </button>
            </div>

            <div id="scanResult" class="mt-2 text-center h-8 font-semibold text-gray-500 flex items-center justify-center rounded-lg transition-all">Scan Standby</div>
        </div>
    </div>

 <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        
        <div onclick="openMasterModal()" role="button" tabindex="0"
             class="group flex items-center justify-between w-full bg-white p-4 rounded-2xl border-2 border-amber-300 shadow-sm 
                    hover:shadow-md hover:border-amber-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-amber-500 text-white flex items-center justify-center shadow-sm">
                    <i data-lucide="list" class="w-6 h-6"></i>
                </div>
                <div class="text-left">
                    <h4 class="text-base font-bold text-gray-800 group-hover:text-amber-700 leading-tight">Master List</h4>
                    <p class="text-xs text-gray-500 mt-1 leading-tight font-medium">Check Part/Seq/ERP</p>
                </div>
            </div>
            
            <i data-lucide="chevron-right" class="w-5 h-5 text-amber-300 group-hover:text-amber-600 transition-colors"></i>
        </div>

        <div onclick="openStockModal('location')" role="button" tabindex="0"
             class="group flex items-center justify-between w-full bg-white p-4 rounded-2xl border-2 border-blue-300 shadow-sm 
                    hover:shadow-md hover:border-blue-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-sm">
                    <i data-lucide="map-pin" class="w-6 h-6"></i>
                </div>
                <div class="text-left">
                    <h4 class="text-base font-bold text-gray-800 group-hover:text-blue-700 leading-tight">Check Location</h4>
                    <p class="text-xs text-gray-500 mt-1 leading-tight font-medium">View parts in a rack</p>
                </div>
            </div>
            
            <i data-lucide="chevron-right" class="w-5 h-5 text-blue-300 group-hover:text-blue-600 transition-colors"></i>
        </div>

        <div onclick="openStockModal('part')" role="button" tabindex="0"
             class="group flex items-center justify-between w-full bg-white p-4 rounded-2xl border-2 border-purple-300 shadow-sm 
                    hover:shadow-md hover:border-purple-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 text-white flex items-center justify-center shadow-sm">
                    <i data-lucide="search" class="w-6 h-6"></i>
                </div>
                <div class="text-left">
                    <h4 class="text-base font-bold text-gray-800 group-hover:text-purple-700 leading-tight">Find Part / FIFO</h4>
                    <p class="text-xs text-gray-500 mt-1 leading-tight font-medium">Locate items & FIFO</p>
                </div>
            </div>
            
            <i data-lucide="chevron-right" class="w-5 h-5 text-purple-300 group-hover:text-purple-600 transition-colors"></i>
        </div>

    </div>
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-pink-800 via-rose-700 to-red-700 px-6 py-5 flex flex-col md:flex-row justify-between md:items-center gap-4 shadow-inner">
            <h3 class="text-xl font-bold text-white flex items-center tracking-wide drop-shadow-sm">
                <i data-lucide="history" class="w-6 h-6 mr-3 text-pink-200"></i>
                <span>Unboxing History</span>
            </h3>
            
            <div class="flex flex-wrap gap-2 justify-end w-full md:w-auto">
                <div class="relative w-full md:w-64">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-pink-300"></i>
                    <input type="text" id="historySearchInput" placeholder="Search ..." class="pl-9 pr-4 py-2 rounded-lg text-sm border-none bg-white/10 text-white placeholder-pink-200 focus:bg-white focus:text-gray-800 focus:ring-2 focus:ring-pink-400 outline-none shadow-sm w-full transition-all" />
                </div>
                <div class="flex items-center gap-1 bg-white/10 p-1 rounded-lg border border-white/10">
                    <input type="date" id="dateFrom" class="bg-transparent text-white text-xs border-none focus:ring-0 px-2 py-1">
                    <span class="text-pink-200 text-xs">to</span>
                    <input type="date" id="dateTo" class="bg-transparent text-white text-xs border-none focus:ring-0 px-2 py-1">
                </div>
                <button id="refreshBtn" class="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-all shadow-md">
                    <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                </button>
                <button id="downloadCsvBtn" class="p-2 px-4 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm flex items-center gap-2 shadow-md transition-all transform hover:scale-105">
                    <i data-lucide="download" class="w-4 h-4"></i> CSV
                </button>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table id="scanHistoryTable" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-100 border-b border-gray-200"> 
                    <tr>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Date/Time Out</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">ID</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Rec. Date</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Part Name</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Part No</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">ERP Code</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Seq</th>
                        <th class="px-4 py-3 text-center font-bold text-gray-600 uppercase">Rack Out</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Location</th>
                        <th class="px-4 py-3 text-center font-bold text-gray-600 uppercase">Action</th>
                    </tr>
                </thead>   
                <tbody id="scanHistoryTableBody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="10" class="text-center py-10 text-gray-500">Loading...</td></tr>
                </tbody>
            </table>
        </div> 
    </div> 
</div>

<div id="manualEntryModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-start justify-center z-50 hidden opacity-0 transition-opacity duration-300 py-20">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 transform scale-95 transition-transform duration-300">
        <div class="bg-pink-700 px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold text-white">Manual Unbox</h3>
            <button id="closeManualModal" class="text-white/80 hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div class="p-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">Tag ID (e.g. R105J5001)</label>
            <input type="text" id="manual_tag_id" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-pink-500 uppercase mb-4" placeholder="Scan or Type ID...">
            
            <div id="manualStatus" class="text-center text-sm font-bold mb-4 h-5"></div>

            <div class="flex gap-3">
                <button id="cancelManualBtn" class="w-1/3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl shadow-sm transition-all">
                    Cancel
                </button>
                <button id="submitManualEntry" class="w-2/3 bg-pink-600 hover:bg-pink-700 text-white font-bold py-3 rounded-xl shadow-md transition-all">
                    Confirm Unbox
                </button>
            </div>
        </div>
    </div>
</div>

<div id="stockModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[80vh] flex flex-col transform scale-95 transition-transform duration-300 mx-4">
        
        <div id="stockModalHeader" class="bg-gradient-to-r from-rose-700 to-pink-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i id="stockModalIcon" data-lucide="box" class="w-5 h-5"></i> 
                <span id="stockModalTitle">Check Racking Stock</span>
            </h3>
            <button onclick="closeStockModal()" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="p-4 border-b border-gray-200 bg-gray-50 flex gap-2 flex-shrink-0">
            <input type="text" id="stockSearchInput" 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-rose-500 outline-none uppercase" 
                   placeholder="Scan/Type...">
            <button id="btnStockSearch" class="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2 rounded-lg font-bold transition-colors shadow-sm">
                SEARCH
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
            <table id="receivingHistoryTable" class="w-full text-sm text-left">
                <thead class="bg-black text-white uppercase font-bold sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Rec. Date (Age)</th>
                        <th class="px-4 py-3">Part No</th>
                        <th class="px-4 py-3">ERP Code</th>
                        <th class="px-4 py-3">Seq No</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                    </tr>
                </thead>
                <tbody id="stockTableBody" class="divide-y divide-gray-200">
                    <tr><td colspan="6" class="text-center py-10 text-gray-400">Enter search term to view rack stock.</td></tr>
                </tbody>
            </table>
        </div>
        
        <div class="p-3 border-t border-gray-200 bg-gray-50 text-center rounded-b-2xl text-xs text-gray-500">
            <span class="inline-block w-3 h-3 bg-red-100 border border-red-200 rounded-full mr-1 align-middle"></span> Old Stock (>60 Days)
            <span class="inline-block w-3 h-3 bg-gray-100 border border-gray-300 rounded-full ml-3 mr-1 align-middle"></span> New Stock
        </div>
    </div>
</div>
<div id="masterListModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col transform scale-95 transition-transform duration-300 mx-4">
        
        <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i data-lucide="list" class="w-6 h-6"></i> 
                <span>Master Part List</span>
            </h3>
            <div class="flex gap-2">
                <button onclick="openMasterInput('add')" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-bold transition-all flex items-center gap-1">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add New
                </button>
                <button onclick="closeMasterModal()" class="text-white/70 hover:text-white transition-colors ml-2">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
        </div>

        <div class="p-4 border-b border-gray-200 bg-amber-50 flex gap-2 flex-shrink-0">
            <div class="relative flex-1">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="masterSearchInput" 
                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 outline-none uppercase font-semibold text-gray-700" 
                       placeholder="Search by Part No, ERP, or Description...">
            </div>
            <button id="btnMasterSearch" class="bg-amber-600 hover:bg-amber-700 text-white px-8 py-2 rounded-xl font-bold transition-colors shadow-sm">
                SEARCH
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 text-gray-600 uppercase font-bold sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-4 py-3 bg-gray-100">Supplier</th>
                        <th class="px-4 py-3 bg-gray-100">Seq No</th>
                        <th class="px-4 py-3 bg-gray-100">Part No</th>
                        <th class="px-4 py-3 bg-gray-100">ERP Code</th>
                        <th class="px-4 py-3 bg-gray-100">Description</th>
                        <th class="px-4 py-3 bg-gray-100 text-center">Pack Qty</th>
                        <th class="px-4 py-3 bg-gray-100 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="masterTableBody" class="divide-y divide-gray-200">
                    <tr><td colspan="7" class="text-center py-10 text-gray-400">Enter search term to view master list.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="masterInputModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[60] hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg transform scale-95 transition-transform duration-300 mx-4 border-t-4 border-amber-500">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h3 id="masterInputTitle" class="text-lg font-bold text-gray-800">Add New Master Data</h3>
            <button onclick="closeMasterInput()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="mi_original_part_no">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Supplier</label>
                    <select id="mi_supplier" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-amber-500 outline-none bg-gray-50">
                        <option value="YTEC">YTEC</option>
                        <option value="LOCAL">LOCAL</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Seq No</label>
                    <input type="text" id="mi_seq" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-amber-500 outline-none uppercase">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Part No <span class="text-red-500">*</span></label>
                <input type="text" id="mi_part_no" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-amber-500 outline-none font-bold text-gray-800 uppercase">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ERP Code <span class="text-red-500">*</span></label>
                    <input type="text" id="mi_erp" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-amber-500 outline-none font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Std Pack</label>
                    <input type="number" id="mi_pack" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Part Description</label>
                <textarea id="mi_desc" rows="2" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-amber-500 outline-none uppercase text-sm"></textarea>
            </div>

            <button id="btnSaveMaster" onclick="saveMasterData()" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl shadow-lg mt-2 transition-all">
                SAVE DATA
            </button>
        </div>
    </div>
</div>
<script src="assets/unboxing_in.js"></script> 
<script>lucide.createIcons();</script>
<?php include 'layout/footer.php'; ?>