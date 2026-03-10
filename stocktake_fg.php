<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
include 'layout/header.php'; 
$page_title = 'Stocktake - Transfer Ticket Check'; 
?>

<div class="container mx-auto px-6 py-10">

    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-900 rounded-xl shadow-2xl px-6 py-5 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="text-xl md:text-2xl font-bold flex items-center space-x-3">
                    <i data-lucide="clipboard-check" class="w-8 h-8"></i>
                    <span>Stocktake - Transfer Verification</span>
                </h2>
                <p class="text-purple-100 text-sm mt-1">
                    Scan Transfer Tickets to verify against Warehouse In records.
                </p>
            </div>
            
            <div class="flex items-center gap-4 w-full md:w-auto">
                <button id="startScanBtn" class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105 w-40 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="play-circle" class="w-5 h-5"></i> <span>START</span>
                </button>
                <button id="stopScanBtn" class="bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105 w-40 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i data-lucide="stop-circle" class="w-5 h-5"></i> <span>STOP</span>
                </button>
                
                <button id="manualEntryBtn" class="bg-slate-600 hover:bg-slate-500 text-white font-bold py-3 px-6 rounded-lg shadow-lg flex items-center justify-center gap-2 transition-all duration-200 transform hover:scale-105 w-40">
                    <i data-lucide="keyboard" class="w-5 h-5"></i> <span>MANUAL</span>
                </button>

                <div class="bg-white/20 backdrop-blur-sm text-white p-4 rounded-lg text-center shadow-inner border border-white/10">
                    <span class="block text-xs font-semibold uppercase tracking-wider opacity-80">Count</span>
                    <span id="scanCount" class="block text-3xl font-bold">0</span>
                </div>
            </div>
        </div>
    </div>

    <div id="scanFormContainer" class="bg-white rounded-2xl shadow-xl border border-gray-200 px-8 py-4 mb-8 hidden">
        <form id="scanForm" class="flex flex-col items-center justify-center max-w-2xl mx-auto">
            <div class="w-full transition-all duration-300" id="qrBoxContainer">
                
                <div class="flex items-center justify-center space-x-2 mb-4">
                    <div id="scannerIndicator" class="w-3 h-3 rounded-full bg-green-500 transition-colors"></div>
                    <p id="scannerStatusText" class="text-sm font-medium text-gray-500">Scanner activated. Ready to scan.</p>
                </div>

                <label class="block text-center text-lg font-bold text-gray-700 mb-4 uppercase tracking-wide">
                    Scan Transfer Ticket QR
                </label>
                
                <div class="relative mb-4 w-full max-w-lg mx-auto">
                    <i data-lucide="qr-code" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-6 h-6"></i>
                    <input type="text" id="scanInput" name="qr_data" 
                           class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500 focus:border-blue-500 transition-all text-lg font-mono text-center font-bold placeholder-gray-300 shadow-sm" 
                           placeholder="Waiting for Scan..." autocomplete="off"> 
                </div>

                <div id="scanResult" class="mt-1 text-center min-h-[1rem] text-lg font-bold text-gray-500">
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-gray-800 to-gray-900 px-6 py-5 flex flex-col md:flex-row justify-between md:items-center gap-4 shadow-inner">
            <h3 class="text-xl font-bold text-white flex items-center tracking-wide">
                <i data-lucide="history" class="w-6 h-6 mr-3 text-blue-300"></i>
                <span>Verification History</span>
            </h3>
            
            <div class="flex flex-wrap gap-2 justify-end w-full md:w-auto">
                <select id="statusFilter" class="bg-white/10 text-white text-xs font-bold border-white/20 rounded-lg focus:bg-white focus:text-gray-800 outline-none px-3 py-2">
                    <option value="all">ALL STATUS</option>
                    <option value="MATCH" class="text-green-400 font-bold">MATCH</option>
                    <option value="UNMATCH" class="text-red-400 font-bold">UNMATCH</option>
                </select>

                <button id="refreshBtn" class="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-all shadow-md group" title="Reset & Refresh">
                    <i data-lucide="refresh-cw" class="w-5 h-5 transition-transform group-hover:rotate-180"></i>
                </button>
                <button id="downloadCsvBtn" class="p-2 px-4 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm flex items-center gap-2 shadow-md transition-all">
                    <i data-lucide="download" class="w-4 h-4"></i> CSV
                </button>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table id="scanHistoryTable" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-100 border-b border-gray-200"> 
                    <tr>
                        <th class="px-6 py-4 text-left font-bold text-gray-600 uppercase">Scan Time</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-600 uppercase">Ticket ID</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-600 uppercase">ERP Code</th>
                        <th class="px-6 py-4 text-left font-bold text-gray-600 uppercase">Released By</th>
                        <th class="px-6 py-4 text-center font-bold text-gray-600 uppercase">Qty</th>
                        <th class="px-6 py-4 text-center font-bold text-gray-600 uppercase">Status</th> 
                        <th class="px-6 py-4 text-center font-bold text-gray-600 uppercase">Action</th>
                    </tr>
                </thead>   
                <tbody id="scanHistoryTableBody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="7" class="text-center py-10 text-gray-500">Loading history...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="paginationControls" class="flex justify-center items-center py-4 px-6 border-t border-gray-200 bg-gray-50"></div>
    </div> 
</div>

<div id="manualModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center transition-all duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-95 opacity-0" id="modalContent">
        <div class="bg-slate-900 p-4 text-white flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-2"><i data-lucide="keyboard" class="w-5 h-5"></i> Manual Ticket Entry</h3>
            <button onclick="closeManualModal()" class="text-white/70 hover:text-white transition-colors"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <div class="p-8">
            <label class="block text-sm font-bold text-gray-600 mb-2 uppercase tracking-wide">Enter Ticket ID</label>
            <div class="relative mb-2">
                 <i data-lucide="tag" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                 <input type="text" id="manualTicketId" class="w-full pl-12 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none text-xl font-mono font-bold text-gray-800" placeholder="e.g. 00001298" autocomplete="off">
            </div>
            <p class="text-xs text-gray-400 mb-6">This will check against Warehouse In records.</p>
            
            <div class="flex gap-3">
                <button onclick="closeManualModal()" class="flex-1 py-3 border-2 border-gray-200 hover:bg-gray-50 rounded-lg font-bold text-gray-500 transition-all">CANCEL</button>
                <button onclick="submitManualEntry()" id="btnConfirmManual" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold shadow-md transition-all flex items-center justify-center gap-2">
                    <span>VERIFY & SAVE</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Helper
    const $ = (id) => document.getElementById(id);
  
    // --- Elements ---
    const startScanBtn = $("startScanBtn");
    const stopScanBtn = $("stopScanBtn");
    const scanCount = $("scanCount");
    const scanFormContainer = $("scanFormContainer");
    const scanInput = $("scanInput");               
    const scanResult = $("scanResult");
    const scannerStatusText = $("scannerStatusText");
    const scannerIndicator = $("scannerIndicator");
    
    const tableBody = $("scanHistoryTableBody");
    const refreshBtn = $("refreshBtn");
    const downloadCsvBtn = $("downloadCsvBtn");
    const statusFilter = $("statusFilter"); 

    const manualBtn = $("manualEntryBtn");
    const manualModal = $("manualModal");
    const modalContent = $("modalContent");
    const manualTicketId = $("manualTicketId");

    let isScanning = false;

    // --- TOGGLE LOGIC ---
    function toggleScanner(active) {
        isScanning = active;
        if(active) {
            if(scanFormContainer) {
                scanFormContainer.classList.remove("hidden");
                scanFormContainer.style.display = 'block'; 
            }
            if(startScanBtn) {
                startScanBtn.disabled = true;
                startScanBtn.classList.add("opacity-50");
            }
            if(stopScanBtn) {
                stopScanBtn.disabled = false;
                stopScanBtn.classList.remove("opacity-50");
            }
            if(scanInput) {
                scanInput.disabled = false;
                scanInput.value = "";
                setTimeout(() => scanInput.focus(), 100);
            }
        } else {
            if(scanFormContainer) {
                scanFormContainer.classList.add("hidden");
                scanFormContainer.style.display = 'none'; 
            }
            if(startScanBtn) {
                startScanBtn.disabled = false;
                startScanBtn.classList.remove("opacity-50");
            }
            if(stopScanBtn) {
                stopScanBtn.disabled = true;
                stopScanBtn.classList.add("opacity-50");
            }
            if(scanCount) {
                scanCount.textContent = "0";
            }
        }
    }

    if (startScanBtn) startScanBtn.addEventListener("click", () => toggleScanner(true));
    if (stopScanBtn) stopScanBtn.addEventListener("click", () => toggleScanner(false));

    // --- MANUAL ENTRY MODAL LOGIC ---
    if(manualBtn) {
        manualBtn.addEventListener("click", () => {
            if(isScanning) scanInput.disabled = true; 
            manualModal.classList.remove("hidden");
            setTimeout(() => {
                modalContent.classList.remove("scale-95", "opacity-0");
                manualTicketId.value = "";
                manualTicketId.focus();
            }, 10);
        });
    }

    window.closeManualModal = function() {
        modalContent.classList.add("scale-95", "opacity-0");
        setTimeout(() => {
            manualModal.classList.add("hidden");
            if(isScanning) {
                scanInput.disabled = false;
                scanInput.focus();
            }
        }, 200);
    };

    if(manualTicketId) {
        manualTicketId.addEventListener("keypress", (e) => {
            if(e.key === "Enter") {
                e.preventDefault();
                window.submitManualEntry();
            }
        });
    }

    window.submitManualEntry = function() {
        const tId = manualTicketId.value.trim();
        if(!tId) {
            alert("Please enter a Ticket ID");
            return;
        }
        const btn = $("btnConfirmManual");
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Checking...`;
        if(typeof lucide !== 'undefined') lucide.createIcons();

        fetch("stocktake_fg_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: 'submit_manual', ticket_id: tId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if(!scanFormContainer.classList.contains("hidden")) {
                    showScanResult(data.status, data.message);
                } else {
                    alert(data.message);
                }
                loadHistory(1);
                let current = parseInt(scanCount.textContent) || 0;
                scanCount.textContent = current + 1;
                closeManualModal();
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Connection Error");
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    };

    // --- HISTORY LOGIC ---
    function loadHistory(page = 1) {
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-500 flex flex-col items-center gap-2"><i data-lucide="loader" class="animate-spin w-6 h-6 text-blue-500"></i><span>Loading history...</span></td></tr>`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        
        const params = new URLSearchParams({
            action: 'get_stocktake_data',
            page: page,
            status_filter: statusFilter ? statusFilter.value : 'all'
        });

        fetch(`get_stocktake_fg_history.php?${params}`)
            .then(res => res.json())
            .then(resp => {
                if(resp.success) renderHistoryTable(resp.data);
                else if(tableBody) tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-red-400">Error loading data.</td></tr>`;
            })
            .catch(err => console.error("Fetch error:", err));
    }

    function renderHistoryTable(data) {
        if (!tableBody) return;
        if (!data || data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400 italic">No records scanned yet.</td></tr>`;
            return;
        }

        tableBody.innerHTML = data.map(row => `
            <tr class="hover:bg-blue-50 transition-colors group">
                <td class="px-6 py-4 text-gray-600 font-mono text-xs whitespace-nowrap">${row.scan_time_fmt}</td>
                <td class="px-6 py-4 font-bold text-gray-800">${row.tag_id}</td>
                <td class="px-6 py-4 font-mono text-blue-600 font-bold">${row.erp_code}</td>
                <td class="px-6 py-4 text-gray-600 uppercase text-s font-bold">${row.released_by || '-'}</td>
                <td class="px-6 py-4 font-bold text-center text-gray-700">${row.qty}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${row.status === 'MATCH' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200'}">
                        ${row.status}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <button onclick="deleteScan(${row.id})" class="text-gray-400 hover:text-red-500 transition-colors p-1" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>
            </tr>
        `).join('');
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // --- BUTTON LISTENERS ---
    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', () => {
            const params = new URLSearchParams({
                action: 'export_stocktake_csv',
                status_filter: statusFilter ? statusFilter.value : 'all'
            });
            window.location.href = `get_stocktake_fg_history.php?${params.toString()}`;
        });
    }

    if (statusFilter) statusFilter.addEventListener('change', () => loadHistory(1));
    
    // FIX: Refresh Button now resets the filter and triggers animation
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            const icon = refreshBtn.querySelector('i');
            if(icon) icon.classList.add('animate-spin');
            
            // *** RESET LOGIC ADDED HERE ***
            if(statusFilter) statusFilter.value = 'all';
            
            loadHistory(1);
            setTimeout(() => { if(icon) icon.classList.remove('animate-spin'); }, 1000);
        });
    }

    document.addEventListener("click", (e) => {
        if(isScanning && scanInput && e.target !== scanInput && !manualModal.contains(e.target) && !manualBtn.contains(e.target) && !e.target.closest("button") && !e.target.closest("a")) {
            scanInput.focus();
        }
    });

    if(scanInput) {
        scanInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter") { 
                e.preventDefault(); 
                handleQrSubmit(); 
            }
        });
    }

    // --- SCAN SUBMIT ---
    function handleQrSubmit() {
        let qrData = scanInput.value.trim();
        if (!qrData) return;

        scanInput.disabled = true;
        if(scannerStatusText) scannerStatusText.innerText = "Processing...";
        if(scannerIndicator) {
            scannerIndicator.classList.remove("bg-green-500");
            scannerIndicator.classList.add("bg-yellow-500");
        }

        fetch("stocktake_fg_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: 'submit_scan', qr_data: qrData })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showScanResult(data.status, data.message);
                loadHistory(1); 
                let current = parseInt(scanCount.textContent) || 0;
                scanCount.textContent = current + 1;
            } else {
                showScanResult("ERROR", data.message, true);
            }
        })
        .catch(() => showScanResult("ERROR", "Connection Error", true))
        .finally(() => {
            if(scanInput) {
                scanInput.value = "";
                scanInput.disabled = false; 
                scanInput.focus();
            }
            if(scannerStatusText) scannerStatusText.innerText = "Scanner activated. Ready to scan.";
            if(scannerIndicator) {
                scannerIndicator.classList.remove("bg-yellow-500", "bg-red-500");
                scannerIndicator.classList.add("bg-green-500");
            }
        });
    }

    function showScanResult(status, msg, isError = false) {
        if(!scanResult) return;
        const colorClass = (status === 'MATCH') ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200';
        const icon = (status === 'MATCH') ? 'check-circle' : 'alert-triangle';
        
        scanResult.innerHTML = `
            <div class="inline-flex items-center px-6 py-3 rounded-lg ${isError ? 'bg-red-50 text-red-700' : colorClass} border shadow-sm">
                <i data-lucide="${isError ? 'alert-circle' : icon}" class="w-6 h-6 mr-3"></i> 
                <div>
                    <div class="font-bold text-lg leading-none">${status}</div>
                    <div class="text-xs opacity-80 uppercase tracking-wide mt-1">${msg}</div>
                </div>
            </div>`;
        
        if(typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => { if(scanResult.innerHTML !== "") scanResult.innerHTML = ""; }, 3000);
    }

    window.deleteScan = function(id) {
        if(!confirm("Are you sure you want to remove this record?")) return;
        fetch("stocktake_fg_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: 'delete_scan', log_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) loadHistory(1); 
            else alert(data.message || "Delete failed");
        });
    };

    if (typeof lucide !== 'undefined') lucide.createIcons();
    loadHistory(1);
});
</script>

<?php include 'layout/footer.php'; ?>