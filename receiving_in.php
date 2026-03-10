<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
include 'layout/header.php'; 
$page_title = 'Receiving In';
?>

<div class="container mx-auto px-6 py-10 h-auto min-h-0">
    <div id="step1_Selection" class="relative z-10 animate-fade-in mt-8 max-w-6xl mx-auto">
        <div class="text-center mb-16 relative">
            <span class="inline-block py-1 px-3 rounded-full bg-indigo-50 text-indigo-600 text-xs font-bold tracking-wider mb-3 border border-indigo-100 uppercase">Step 01: Identification</span>
            <h1 class="text-5xl md:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-slate-800 via-indigo-800 to-slate-800 mb-4 tracking-tight">CHOOSE SUPPLIER</h1>
            <p class="text-slate-500 text-lg max-w-xl mx-auto leading-relaxed">Select the supplier tag type to configure the scanner.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 px-4">
            <div onclick="selectSupplier('YTEC')" class="group relative bg-white rounded-[2rem] p-8 cursor-pointer transition-all duration-500 border-2 border-transparent hover:border-amber-200 shadow-sm hover:shadow-xl hover:-translate-y-2 overflow-hidden">
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-24 h-24 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mb-6 shadow-inner group-hover:scale-110 group-hover:rotate-3 transition-transform duration-500"><i data-lucide="package-check" class="w-10 h-10"></i></div>
                    <h3 class="text-2xl font-bold text-slate-800 group-hover:text-amber-700">YTEC</h3>
                </div>
            </div>
            
            <div onclick="selectSupplier('MAZDA')" class="group relative bg-white rounded-[2rem] p-8 cursor-pointer transition-all duration-500 border-2 border-transparent hover:border-red-200 shadow-sm hover:shadow-xl hover:-translate-y-2 overflow-hidden">
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-24 h-24 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center mb-6 shadow-inner group-hover:scale-110 group-hover:-rotate-3 transition-transform duration-500"><i data-lucide="car" class="w-10 h-10"></i></div>
                    <h3 class="text-2xl font-bold text-slate-800 group-hover:text-red-700">MAZDA</h3>
                </div>
            </div>

            <div onclick="selectSupplier('MASZ')" class="group relative bg-white rounded-[2rem] p-8 cursor-pointer transition-all duration-500 border-2 border-transparent hover:border-blue-200 shadow-sm hover:shadow-xl hover:-translate-y-2 overflow-hidden">
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-24 h-24 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mb-6 shadow-inner group-hover:scale-110 group-hover:rotate-3 transition-transform duration-500"><i data-lucide="container" class="w-10 h-10"></i></div>
                    <h3 class="text-2xl font-bold text-slate-800 group-hover:text-blue-700">MASZ</h3>
                </div>
            </div>
        </div>
    </div>

    <div id="step2_Scanning" class="hidden animate-slide-up">
        
       <div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
    <button onclick="resetSelection()" class="group relative overflow-hidden bg-white pl-4 pr-6 py-2.5 rounded-xl shadow-sm border-2 border-slate-300 text-slate-600 font-bold text-xs hover:border-blue-600 hover:text-blue-800 active:scale-95 transition-all">
        <div class="relative z-10 flex items-center gap-2.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span class="tracking-wide">Back to Selection</span>
        </div>
    </button>
    
    <div class="relative bg-white/90 backdrop-blur-xl pl-4 pr-6 py-2 rounded-lg shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 flex items-center justify-center">
            <i id="supplierIcon" data-lucide="truck" class="w-5 h-5 text-slate-500/80"></i>
        </div>
        
        <div class="flex flex-col justify-center">
            <div class="flex items-center gap-2">
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400">Online</span>
            </div>
            <div id="activeSupplierDisplay" class="text-xl font-black text-transparent bg-clip-text bg-gradient-to-r from-slate-800 via-blue-800 to-slate-800 leading-none">...</div>
        </div>
    </div>
</div>
        <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-cyan-800 rounded-xl shadow-xl p-4 md:p-5 mb-8 text-white flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <h2 class="text-xl md:text-2xl font-bold flex items-center space-x-3"><i data-lucide="scan-line" class="w-8 h-8"></i><span>Receive In - Scan Ticket</span></h2>
                <p class="text-blue-100 text-sm mt-1">Click START to begin Entry.</p>
            </div>
            <div class="flex items-center gap-3 w-full md:w-auto justify-center">
                <button id="manualBtn" class="bg-white/20 hover:bg-white/30 text-white font-bold py-3 px-4 rounded-lg shadow flex items-center gap-2 transition-all disabled:opacity-50" disabled><i data-lucide="keyboard" class="w-5 h-5"></i> <span class="hidden sm:inline">MANUAL</span></button>
                <button id="startScanBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center gap-2 transition-transform hover:scale-105"><i data-lucide="play" class="w-5 h-5"></i> START SCAN</button>
                <button id="stopScanBtn" class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center gap-2 transition-transform hover:scale-105 disabled:opacity-50" disabled><i data-lucide="stop-circle" class="w-5 h-5"></i> STOP SCAN</button>
                <div class="bg-white/30 text-white p-4 rounded-lg text-center shadow-inner"><span class="block text-xs font-semibold uppercase tracking-wider">Count</span><span id="scanCount" class="block text-3xl font-bold">0</span></div>
            </div>
        </div>

        <div id="jobInputCard" class="bg-white rounded-xl shadow-md border border-gray-200 p-6 mb-6 hidden transition-all duration-500 ease-in-out">
            <div class="max-w-md mx-auto">
                <label id="jobInputLabel" class="block text-sm font-bold text-gray-700 mb-2 text-center">ENTER JOB NUMBER</label>
                <div class="relative"><i data-lucide="file-text" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i><input type="text" id="jobNoInput" class="w-full pl-10 pr-4 py-4 text-lg border-2 border-gray-300 rounded-xl focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 font-bold text-gray-800 outline-none transition-all uppercase text-center" placeholder="1001" autocomplete="off"></div>
            </div>
        </div>

        <div id="scanFormContainer" class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 p-8 mb-8 hidden transition-all duration-500">
            <div class="relative">
                <label class="block text-sm font-bold text-center text-indigo-600 mb-2 animate-pulse">SCANNER READY - WAITING FOR QR</label>
                <div class="relative"><i data-lucide="qr-code" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-6 h-6"></i><input type="text" id="scanInput" class="w-full pl-12 pr-4 py-4 border-2 border-indigo-500 rounded-xl text-xl font-mono focus:outline-none shadow-inner bg-indigo-50" placeholder="Scanning..." autocomplete="off"></div>
                <div id="scanStatus" class="mt-4 text-center font-semibold text-gray-500 h-6"></div>
            </div>
        </div>

       <div id="stockCheckContainer" class="flex flex-wrap gap-3 mb-6 hidden animate-fade-in w-full">
    
    <div onclick="openMasterModal()" 
         class="w-full md:flex-1 cursor-pointer relative bg-white p-4 rounded-xl shadow-sm border-2 border-amber-200 
                hover:shadow-amber-100 hover:shadow-lg hover:border-amber-400 hover:-translate-y-0.5
                transition-all duration-200 overflow-hidden h-auto">
        <div class="absolute top-0 right-0 w-16 h-16 bg-amber-50 rounded-full -mr-6 -mt-6 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        
        <div class="relative flex items-center gap-3">
            <div class="w-10 h-10 flex-shrink-0 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md">
                <i data-lucide="list" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm font-bold text-gray-800 truncate">Master List</h4>
                <p class="text-xs text-gray-500 mt-0.5 truncate">Check Part/Seq/ERP</p>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-amber-300 flex-shrink-0"></i>
        </div>
    </div>

    <div onclick="openStockModal('location')" 
         class="w-full md:flex-1 cursor-pointer relative bg-white p-4 rounded-xl shadow-sm border-2 border-blue-200 
                hover:shadow-blue-100 hover:shadow-lg hover:border-blue-400 hover:-translate-y-0.5
                transition-all duration-200 overflow-hidden h-auto">
        <div class="absolute top-0 right-0 w-16 h-16 bg-blue-50 rounded-full -mr-6 -mt-6 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        <div class="relative flex items-center gap-3">
            <div class="w-10 h-10 flex-shrink-0 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                <i data-lucide="map-pin" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm font-bold text-gray-800 truncate">Check Location</h4>
                <p class="text-xs text-gray-500 mt-0.5 truncate">View parts in a rack</p>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-blue-300 flex-shrink-0"></i>
        </div>
    </div>

    <div onclick="openStockModal('part')" 
         class="w-full md:flex-1 cursor-pointer relative bg-white p-4 rounded-xl shadow-sm border-2 border-purple-200 
                hover:shadow-purple-100 hover:shadow-lg hover:border-purple-400 hover:-translate-y-0.5
                transition-all duration-200 overflow-hidden h-auto">
        <div class="absolute top-0 right-0 w-16 h-16 bg-purple-50 rounded-full -mr-6 -mt-6 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        <div class="relative flex items-center gap-3">
            <div class="w-10 h-10 flex-shrink-0 rounded-lg bg-gradient-to-br from-purple-600 to-fuchsia-600 text-white flex items-center justify-center shadow-md">
                <i data-lucide="search" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm font-bold text-gray-800 truncate">Find Part / FIFO</h4>
                <p class="text-xs text-gray-500 mt-0.5 truncate">Locate items & FIFO</p>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-purple-300 flex-shrink-0"></i>
        </div>
    </div>
</div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden mt-8">
            <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-cyan-800 px-6 py-5 flex flex-col md:flex-row justify-between md:items-center gap-4 shadow-inner text-white">
                <h3 class="font-bold text-lg flex items-center gap-2"><i data-lucide="history" class="w-6 h-6"></i><span id="historyTitle">Receiving History</span></h3>
                <div class="flex flex-wrap gap-2 justify-end w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-purple-300"></i>
                        <input type="text" id="historySearchInput" placeholder="Search..." class="pl-9 pr-4 py-2 rounded-lg text-sm border-none bg-white/10 text-white placeholder-purple-200 focus:bg-white focus:text-gray-800 focus:ring-2 focus:ring-purple-400 outline-none shadow-sm w-full transition-all" />
                    </div>
                    <div class="flex items-center gap-1 bg-white/10 p-1 rounded-lg border border-white/10">
                        <input type="date" id="dateFrom" class="bg-transparent text-white text-xs border-none focus:ring-0 px-2 py-1"><span class="text-purple-200 text-xs">to</span><input type="date" id="dateTo" class="bg-transparent text-white text-xs border-none focus:ring-0 px-2 py-1">
                    </div>
                    <button id="refreshBtn" class="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-all shadow-md"><i data-lucide="refresh-cw" class="w-5 h-5"></i></button>
                    <button id="downloadCsvBtn" class="p-2 px-4 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm flex items-center gap-2 shadow-md transition-all transform hover:scale-105"><i data-lucide="download" class="w-4 h-4"></i> CSV</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table id="receivingHistoryTable" class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-600 uppercase font-bold"><tr></tr></thead>
                    <tbody id="receivingHistoryTableBody" class="divide-y divide-gray-200">
                        <tr><td colspan="10" class="px-6 py-8 text-center text-gray-400 italic">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="historyPagination" class="flex justify-center items-center py-4 bg-gray-50 border-t border-gray-200 gap-2"></div>
        </div>

    <select id="supplierSelect" class="hidden" onchange="updateLabel()">
        <option value="">-</option>
        <option value="YTEC">YTEC</option>
        <option value="MAZDA">MAZDA</option>
        <option value="MASZ">MASZ</option>
    </select>
</div>

<div id="manualModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform scale-95 transition-transform duration-300">
        <div class="bg-indigo-600 px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-xl font-bold text-white flex items-center gap-2"><i data-lucide="keyboard" class="w-5 h-5"></i> Manual Entry</h3>
            <button id="closeModal" class="text-white/80 hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex gap-2">
                <input type="text" id="manualInputData" class="flex-1 px-4 py-2 border rounded-lg uppercase" placeholder="Part No or ERP">
                <button id="checkManualBtn" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold">CHECK</button>
            </div>
            <div id="manualFeedback" class="mt-2 text-sm font-bold min-h-[20px]"></div>
            <div id="manualDetails" class="hidden bg-gray-50 p-4 rounded-lg border">
                <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                    <div><span class="text-gray-500 block text-xs uppercase">Part No</span><span id="m_partNo" class="font-bold text-gray-900 block">-</span></div>
                    <div><span class="text-gray-500 block text-xs uppercase">ERP Code</span><span id="m_erpCode" class="font-bold text-gray-900 block">-</span></div>
                    <div class="col-span-2"><span class="text-gray-500 block text-xs uppercase">Part Name</span><span id="m_partName" class="font-bold text-gray-900 block">-</span></div>
                    <div><span class="text-gray-500 block text-xs uppercase">Seq No</span><span id="m_seqNo" class="font-bold text-gray-900 block">-</span></div>
                </div>
                <div id="extraInputContainer" class="hidden pt-3 border-t border-gray-200">
                    <label id="extraInputLabel" class="block text-xs font-bold text-gray-700 uppercase mb-1">Extra Info</label>
                    <input type="text" id="manualExtraInput" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:indigo-500 uppercase" placeholder="Enter Value">
                </div>
            </div>
        </div>
        <div class="p-6 border-t flex justify-end gap-3">
            <button id="cancelManual" class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-100 rounded-lg">Cancel</button>
            <button id="saveManualBtn" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 disabled:opacity-50" disabled>SAVE</button>
        </div>
    </div>
</div>
<div id="masterListModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-auto max-h-[85vh] flex flex-col transform scale-95 transition-transform duration-300 mx-4">        
        <div class="bg-gradient-to-r from-amber-600 to-orange-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i data-lucide="list" class="w-5 h-5"></i> 
                <span>Master Incoming List</span>
            </h3>
            
            <div class="flex items-center gap-2">
                <button onclick="openMasterInput('add')" class="bg-white/20 hover:bg-white/30 text-white p-2 rounded-lg transition-colors flex items-center gap-2 font-bold text-xs border border-white/20">
                    <i data-lucide="plus" class="w-4 h-4"></i> ADD NEW
                </button>
                <button onclick="closeMasterModal()" class="text-white/70 hover:text-white transition-colors p-1">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
        </div>

        <div class="p-4 border-b border-gray-200 bg-gray-50 flex gap-2 flex-shrink-0">
            <input type="text" id="masterSearchInput" 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none uppercase" 
                   placeholder="Search Part No, ERP, Seq or Description...">
            <button id="btnMasterSearch" class="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2 rounded-lg font-bold transition-colors shadow-sm">
                SEARCH
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-800 text-white uppercase font-bold sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Seq No</th>
                        <th class="px-4 py-3">Part No</th>
                        <th class="px-4 py-3">ERP Code</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-center">Std Pack</th>
                        <th class="px-4 py-3 text-center">Action</th> 
                    </tr>
                </thead>
                <tbody id="masterTableBody" class="divide-y divide-gray-200">
                    <tr><td colspan="7" class="text-center py-10 text-gray-400">Loading master list...</td></tr>
                </tbody>
            </table>
        </div>
        
        <div class="p-3 border-t border-gray-200 bg-gray-50 text-right rounded-b-2xl text-xs text-gray-500">
            Showing top 100 matches
        </div>
    </div>
</div>

<div id="masterInputModal" class="fixed inset-0 bg-black/80 backdrop-blur-md flex items-center justify-center z-[60] hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg transform scale-95 transition-transform duration-300 mx-4 border-2 border-amber-500">
        <div class="bg-amber-50 px-6 py-4 rounded-t-xl border-b border-amber-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-amber-800" id="masterInputTitle">Edit Master Data</h3>
            <button onclick="closeMasterInput()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        
        <div class="p-6 space-y-4">
            <input type="hidden" id="mi_original_part_no"> <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Supplier</label>
                    <select id="mi_supplier" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-amber-500 font-bold text-gray-700">
                        <option value="YTEC">YTEC</option>
                        <option value="MC">MC</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Seq No</label>
                    <input type="number" id="mi_seq" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Part No</label>
                    <input type="text" id="mi_part_no" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-amber-500 uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ERP Code</label>
                    <input type="text" id="mi_erp" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-amber-500 uppercase">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Part Description</label>
                <input type="text" id="mi_desc" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-amber-500 uppercase">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Std Packing</label>
                <input type="number" id="mi_pack" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-amber-500">
            </div>
        </div>

        <div class="p-4 border-t bg-gray-50 rounded-b-xl flex justify-end gap-2">
            <button onclick="closeMasterInput()" class="px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-lg font-bold text-sm">Cancel</button>
            <button onclick="saveMasterData()" id="btnSaveMaster" class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-sm shadow-md transition-all">
                SAVE DATA
            </button>
        </div>
    </div>
</div>
<div id="stockModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-auto max-h-[85vh] flex flex-col transform scale-95 transition-transform duration-300 mx-4">        
        <div id="stockModalHeader" class="bg-gradient-to-r from-purple-700 to-pink-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i id="stockModalIcon" data-lucide="box" class="w-5 h-5"></i> 
                <span id="stockModalTitle">Stock FIFO Check</span>
            </h3>
            <button onclick="closeStockModal()" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="p-4 border-b border-gray-200 bg-gray-50 flex gap-2 flex-shrink-0">
            <input type="text" id="stockSearchInput" 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none uppercase" 
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
                    <tr><td colspan="6" class="text-center py-10 text-gray-400">Enter search term to view stock.</td></tr>
                </tbody>
            </table>
        </div>
        
        <div class="p-3 border-t border-gray-200 bg-gray-50 text-center rounded-b-2xl text-xs text-gray-500">
            <span class="inline-block w-3 h-3 bg-red-100 border border-red-200 rounded-full mr-1 align-middle"></span> Old Stock (>60 Days)
            <span class="inline-block w-3 h-3 bg-gray-100 border border-gray-300 rounded-full ml-3 mr-1 align-middle"></span> New Stock
        </div>
    </div>
</div>
</div>

<script src="assets/receiving_in.js"></script>
<script>
    lucide.createIcons();
    function selectSupplier(supplier) {
        const selectEl = document.getElementById('supplierSelect');
        selectEl.value = supplier;
        if (typeof updateLabel === 'function') updateLabel();
        
        const displayEl = document.getElementById('activeSupplierDisplay');
        const iconEl = document.getElementById('supplierIcon');
        
        displayEl.textContent = supplier;
        displayEl.classList.remove('animate-pulse');
        void displayEl.offsetWidth; 
        displayEl.classList.add('animate-pulse'); 
        setTimeout(() => displayEl.classList.remove('animate-pulse'), 500);

        if (supplier === 'MAZDA') iconEl.setAttribute('data-lucide', 'car-check'); 
        else if (supplier === 'YTEC') iconEl.setAttribute('data-lucide', 'package-check');
        else iconEl.setAttribute('data-lucide', 'container');
        
        lucide.createIcons();

        // SHOW THE DUAL BUTTON GRID
        document.getElementById('stockCheckContainer').classList.remove('hidden');

        document.getElementById('historyTitle').textContent = supplier + " Recent History"; 
        document.getElementById('step1_Selection').classList.add('hidden');
        document.getElementById('step2_Scanning').classList.remove('hidden');
        if (typeof loadReceivingHistory === "function") loadReceivingHistory(1);
    }
    
    function resetSelection() {
        document.getElementById('supplierSelect').value = "";
        document.getElementById('activeSupplierDisplay').textContent = "...";
        document.getElementById('jobNoInput').value = "";
        document.getElementById('step2_Scanning').classList.add('hidden');
        document.getElementById('step1_Selection').classList.remove('hidden');
        const stopBtn = document.getElementById('stopScanBtn');
        if(!stopBtn.disabled) stopBtn.click(); 
        document.getElementById('jobInputCard').classList.add('hidden');
        document.getElementById('scanFormContainer').classList.add('hidden');

        document.getElementById('stockCheckContainer').classList.add('hidden');

        if(document.getElementById('historySearchInput')) document.getElementById('historySearchInput').value = '';
        if(document.getElementById('dateFrom')) document.getElementById('dateFrom').value = '';
        if(document.getElementById('dateTo')) document.getElementById('dateTo').value = '';
    }

    function updateLabel() {
        const supplier = document.getElementById('supplierSelect').value;
        const jobLabel = document.getElementById('jobInputLabel');
        const jobInput = document.getElementById('jobNoInput');
        
        if (supplier === 'MAZDA' || supplier === 'MASZ') {
            jobLabel.textContent = "ENTER SCAT NUMBER";
            jobInput.placeholder = "1234";
        } else {
            jobLabel.textContent = "ENTER JOB NUMBER";
            jobInput.placeholder = "1001";
        }
    }
</script>
<?php include 'layout/footer.php'; ?>