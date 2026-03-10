<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
include 'layout/header.php'; 
$page_title = 'Stocktake'; 
?>

<div class="container mx-auto px-6 py-10">

    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-900 rounded-xl shadow-2xl px-6 py-5 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="text-xl md:text-2xl font-bold flex items-center space-x-3">
                    <i data-lucide="layers" class="w-8 h-8"></i>
                    <span>Stocktake - Scan Ticket</span>
                </h2>
                <p class="text-purple-100 text-sm mt-1">
                    Scan validated Receiving Tags to lock into racks.
                </p>
            </div>
            
            <div class="flex items-center gap-4 w-full md:w-auto">
                <button id="startScanBtn" class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105 w-48 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="play-circle" class="w-5 h-5"></i> <span>START SCAN</span>
                </button>
                <button id="stopScanBtn" class="bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105 w-48 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i data-lucide="stop-circle" class="w-5 h-5"></i> <span>STOP SCAN</span>
                </button>
                <div class="bg-white/20 backdrop-blur-sm text-white p-4 rounded-lg text-center shadow-inner border border-white/10">
                    <span class="block text-xs font-semibold uppercase tracking-wider opacity-80">Count</span>
                    <span id="scanCount" class="block text-3xl font-bold">0</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div onclick="openMasterModal()" role="button" tabindex="0" class="group flex items-center justify-between w-full bg-white p-4 rounded-2xl border-2 border-amber-300 shadow-sm hover:shadow-md hover:border-amber-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
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

        <div onclick="openStockModal('location')" role="button" tabindex="0" class="group flex items-center justify-between w-full bg-white p-4 rounded-2xl border-2 border-blue-300 shadow-sm hover:shadow-md hover:border-blue-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
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

        <div onclick="openStockModal('part')" role="button" tabindex="0" class="group flex items-center justify-between w-full bg-white p-4 rounded-2xl border-2 border-purple-300 shadow-sm hover:shadow-md hover:border-purple-400 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-fuchsia-600 text-white flex items-center justify-center shadow-sm">
                    <i data-lucide="search" class="w-6 h-6"></i>
                </div>
                <div class="text-left">
                    <h4 class="text-base font-bold text-gray-800 group-hover:text-purple-700 leading-tight">Find Part / ERP</h4>
                    <p class="text-xs text-gray-500 mt-1 leading-tight font-medium">Locate items & FIFO</p>
                </div>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-purple-300 group-hover:text-purple-600 transition-colors"></i>
        </div>
    </div>

    <div id="stockModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[80vh] flex flex-col transform scale-95 transition-transform duration-300 mx-4">
            
            <div id="stockModalHeader" class="bg-gradient-to-r from-gray-800 to-gray-700 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <i id="stockModalIcon" data-lucide="box" class="w-5 h-5"></i> 
                    <span id="stockModalTitle">Stock Check</span>
                </h3>
                <button onclick="closeStockModal()" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="p-4 border-b border-gray-200 bg-gray-50 flex gap-2 flex-shrink-0">
                <input type="text" id="modalStockSearchInput" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none" 
                       placeholder="Type to search stock...">
                <button id="btnStockSearch" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold transition-colors shadow-sm">
                    SEARCH
                </button>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
                <table id="receivingHistoryTable" class="w-full text-sm text-left">
                    <thead class="bg-black text-white uppercase font-bold sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Rec. Date (FIFO)</th>
                            <th class="px-4 py-3">Part No</th>
                            <th class="px-4 py-3">ERP Code</th>
                            <th class="px-4 py-3">Seq No</th>
                            <th class="px-4 py-3">Part Desc</th>
                            <th class="px-4 py-3 text-center">Total Qty</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody" class="divide-y divide-gray-200">
                        <tr><td colspan="7" class="text-center py-10 text-gray-400">Enter search term to view stock.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-t border-gray-200 bg-gray-50 text-right rounded-b-2xl text-xs text-gray-500">
                PJVK Warehouse System
            </div>
        </div>
    </div>

    <div id="scanFormContainer" class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 mb-8 hidden">
        <form id="scanForm" class="flex flex-col md:flex-row gap-6 items-start">
            <div class="flex-1 w-full">
                <label for="scanRackingInput" class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">1. Scan Racking No</label>
                <div class="relative">
                    <i data-lucide="archive" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-6 h-6"></i>
                    <input type="text" id="scanRackingInput" class="w-full pl-14 pr-4 py-4 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all text-lg font-mono font-bold text-purple-900 placeholder-gray-400" placeholder="Scan Racking..." autocomplete="off">
                </div>
                <button type="button" id="btnInsertRack" class="mt-3 w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-xl shadow transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="lock" class="w-4 h-4"></i> INSERT / LOCK RACK
                </button>
                <p id="rackStatusMsg" class="text-xs text-gray-500 mt-2 text-center italic">Scan rack & click insert to proceed</p>
            </div>

            <div class="hidden md:flex items-center justify-center pt-10">
                <i data-lucide="arrow-right" class="w-8 h-8 text-gray-300"></i>
            </div>

            <div class="flex-1 w-full opacity-50 transition-all duration-300" id="qrBoxContainer">
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">2. Scan Tag ID or Enter Manual</label>
                <div class="relative mb-4">
                    <i data-lucide="qr-code" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-6 h-6"></i>
                    <input type="text" id="scanInput" name="qr_data" class="w-full pl-14 pr-4 py-4 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 transition-all text-lg" placeholder="Ready..." autocomplete="off" disabled> 
                </div>
                <div class="relative flex py-2 items-center mb-4">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="flex-shrink-0 mx-4 text-gray-400 text-xs uppercase font-bold">OR</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>
                <button type="button" id="manualEntryBtn" disabled class="w-full border-2 border-dashed border-purple-300 text-purple-600 bg-purple-50 hover:bg-purple-100 hover:border-purple-400 font-bold py-3 px-4 rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="keyboard" class="w-5 h-5"></i> Manual Entry
                </button>
                <div id="scanResult" class="mt-4 text-center h-5 font-semibold text-gray-500">Scan Standby</div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-950 via-teal-900 to-cyan-950 px-6 py-5 flex flex-col md:flex-row justify-between md:items-center gap-4 shadow-inner">
            <h3 class="text-xl font-bold text-white flex items-center tracking-wide drop-shadow-sm">
                <i data-lucide="history" class="w-6 h-6 mr-3 text-purple-200"></i>
                <span>Stocktake History</span>
            </h3>
            
            <div class="flex flex-wrap gap-2 justify-end w-full md:w-auto">
                <select id="searchColumn" class="bg-white/10 text-white text-xs font-bold border-white/20 rounded-lg focus:bg-white focus:text-gray-800 outline-none px-3 py-2">
                    <option value="tag_id">SEARCH BY ID</option>
                    <option value="scanned_location">SEARCH BY LOCATION</option>
                    <option value="seq_no">SEARCH BY SEQ NO</option>
                </select>

                <div class="relative w-full md:w-64">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-purple-300"></i>
                    <input type="text" id="historySearchInput" placeholder="Type here..." class="pl-9 pr-4 py-2 rounded-lg text-sm border-none bg-white/10 text-white placeholder-purple-200 focus:bg-white focus:text-gray-800 focus:ring-2 focus:ring-purple-400 outline-none shadow-sm w-full transition-all" />
                </div>
                <select id="statusFilter" class="bg-white/10 text-white text-xs font-bold border-white/20 rounded-lg focus:bg-white focus:text-gray-800 outline-none px-3 py-2">
                    <option value="all">ALL STATUS</option>
                    <option value="MATCH" class="text-green-600 font-bold">MATCH</option>
                    <option value="UNMATCH" class="text-red-600 font-bold">UNMATCH</option>
                    <option value="NOT REGISTERED" class="text-gray-500 font-bold">NOT REGISTERED</option>
                </select>

                <div class="flex items-center gap-1 bg-white/10 p-1 rounded-lg border border-white/10">
                    <input type="date" id="dateFrom" class="bg-transparent text-white text-xs border-none focus:ring-0 px-2 py-1" title="From Date">
                    <span class="text-purple-200 text-xs">to</span>
                    <input type="date" id="dateTo" class="bg-transparent text-white text-xs border-none focus:ring-0 px-2 py-1" title="To Date">
                </div>
                
                <button id="refreshBtn" class="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-all shadow-md" title="Reset Filters">
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
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Date/Time In</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">ID</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Rec. Date</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Part Name</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Part No</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">ERP Code</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Seq</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Rack In</th>
                        <th class="px-4 py-3 text-left font-bold text-gray-600 uppercase">Location</th>
                        <th class="px-4 py-3 text-center font-bold text-gray-600 uppercase">Status</th> 
                        <th class="px-4 py-3 text-center font-bold text-gray-600 uppercase">Action</th>
                    </tr>
                </thead>   
                <tbody id="scanHistoryTableBody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="11" class="text-center py-10 text-gray-500">Loading history...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="paginationControls" class="flex justify-center items-center py-4 px-6 border-t border-gray-200 bg-gray-50"></div>
    </div> 
</div>

<div id="manualEntryModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 opacity-0 invisible transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 transform scale-95 transition-transform duration-300 hover:scale-100 flex flex-col overflow-hidden">
        <div class="bg-gradient-to-r from-purple-800 to-indigo-700 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                <i data-lucide="keyboard" class="w-5 h-5"></i> <span>Manual Stocktake</span>
            </h3>
            <button type="button" id="closeManualModal" class="text-white/80 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <form id="manualEntryForm" class="p-5">
            <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-3 text-yellow-800 text-sm rounded-r-md shadow-sm flex items-center justify-between">
                <span class="flex items-center"><i data-lucide="map-pin" class="w-4 h-4 mr-2"></i> Target Location:</span>
                <strong id="modalRackingLoc" class="text-yellow-900 text-base">PENDING</strong>
            </div>
            
            <div class="mb-4">
                <label for="manual_tag_id" class="block text-xs font-bold text-gray-500 uppercase mb-1">Scan Tag ID</label>
                <div class="flex gap-2">
                    <input type="text" id="manual_tag_id" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 uppercase outline-none transition-all text-sm font-semibold" placeholder="e.g. R105J5001">
                    <button type="button" id="fetchDetailsBtn" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 shadow-sm transition-all text-sm">Check</button>
                </div>
            </div>

            <div id="manualDetailsContainer" class="hidden bg-slate-50 p-3 rounded-lg border border-slate-200 mb-4 shadow-inner">
                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div class="col-span-2 border-b border-gray-200 pb-1">
                        <span class="text-xs text-gray-400 block uppercase">Part Name</span>
                        <span id="manual_part_name" class="font-bold text-gray-800 text-sm truncate block"></span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block uppercase">Part No</span>
                        <span id="manual_part_no_fg" class="font-bold text-gray-800"></span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block uppercase">ERP Code</span>
                        <span id="manual_erp_code_b" class="font-bold text-gray-800"></span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block uppercase">Seq No</span>
                        <span id="manual_seq_no" class="font-bold text-gray-800"></span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block uppercase">Qty</span>
                        <span id="manual_rack_in" class="font-bold text-emerald-600 text-base"></span>
                    </div>
                    <div class="col-span-2 pt-1 border-t border-gray-200 mt-1">
                         <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-400 uppercase">Rec Date</span>
                            <span id="manual_receiving_date" class="font-bold text-gray-800"></span>
                         </div>
                    </div>
                </div>
            </div>

            <div id="manualStatusMessage" class="text-center min-h-[1.25rem] font-semibold text-xs mb-3 transition-all"></div>

            <div class="flex items-center justify-end space-x-3 pt-2 border-t border-gray-100">
                <button type="button" id="cancelManualEntry" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-bold rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="button" id="submitManualEntry" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-bold rounded-lg hover:from-purple-700 hover:to-indigo-700 shadow-md disabled:opacity-50 transition-all" disabled>
                    <i data-lucide="save" class="w-4 h-4 inline-block mr-1"></i> Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<div id="editScanModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[70] hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform scale-95 transition-transform duration-300 mx-4 border-t-4 border-blue-600">
        
        <div class="bg-gray-50 px-6 py-4 rounded-t-xl border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="edit-3" class="w-5 h-5 text-blue-600"></i> 
                <span>Edit Record</span>
            </h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <form id="editScanForm" class="p-6 space-y-4">
            <input type="hidden" id="edit_log_id">
            
            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 flex justify-between items-center">
                <div class="flex-1 mr-4">
                    <label for="edit_tag_id" class="block text-xs text-blue-500 uppercase font-bold mb-1">Tag ID</label>
                    <input type="text" id="edit_tag_id" class="w-full px-2 py-1 border border-blue-200 rounded text-lg font-bold text-blue-900 bg-white focus:ring-2 focus:ring-blue-500 outline-none uppercase">
                </div>
                <div class="text-right">
                    <span class="text-xs text-blue-500 uppercase font-bold">Scan Time</span>
                    <div id="edit_scan_time" class="text-sm font-semibold text-blue-800">...</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Part No</label>
                    <input type="text" id="edit_part_no" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono font-bold uppercase text-gray-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ERP Code</label>
                    <input type="text" id="edit_erp" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase text-gray-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Part Name</label>
                <input type="text" id="edit_part_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase text-gray-700">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Seq No</label>
                    <input type="text" id="edit_seq" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-center">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Qty</label>
                    <input type="number" id="edit_qty" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-bold text-emerald-600 text-center">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Location</label>
                    <input type="text" id="edit_loc" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-bold text-gray-800 text-center uppercase">
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg transition-colors text-sm">Cancel</button>
                <button type="button" onclick="saveEditScan()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-md transition-all text-sm flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> SAVE CHANGES
                </button>
            </div>
        </form>
    </div>
</div>

<div id="masterListModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[85vh] flex flex-col transform scale-95 transition-transform duration-300 mx-4">
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
            <input type="text" id="masterSearchInput" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none uppercase" placeholder="Search Part No, ERP, Seq or Description...">
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
            PJVK Warehouse System
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
            <input type="hidden" id="mi_original_part_no"> 
            <div class="grid grid-cols-2 gap-4">
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

<script src="assets/stocktake.js"></script> 
<script>lucide.createIcons();</script>
<?php include 'layout/footer.php'; ?>