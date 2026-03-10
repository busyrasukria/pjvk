document.addEventListener("DOMContentLoaded", () => {
    console.log("System Loaded: Stocktake Script Active"); // Check your browser console for this!
    
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

    let isScanning = false;

    // ============================================================
    // 1. HISTORY & FILTERS
    // ============================================================
    
    function loadHistory(page = 1) {
        if (tableBody) {
            if(tableBody.children.length === 0 || tableBody.innerHTML.includes("Loading"))
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">Loading history...</td></tr>`;
        }
        
        const params = new URLSearchParams({
            action: 'get_stocktake_data',
            page: page,
            status_filter: statusFilter ? statusFilter.value : 'all'
        });

        fetch(`get_stocktake_fg_history.php?${params}`)
            .then(res => res.json())
            .then(resp => {
                if(resp.success) {
                    renderHistoryTable(resp.data);
                } else {
                    if(tableBody) tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-red-400">Error loading data.</td></tr>`;
                }
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
                <td class="px-6 py-4 text-gray-600 uppercase text-xs font-bold">${row.released_by || '-'}</td>
                <td class="px-6 py-4 font-bold text-center text-gray-700">${row.qty}</td>
                
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getStatusBadgeClass(row.status)}">
                        ${row.status}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <button onclick="deleteScan(${row.id}, this)" class="text-gray-400 hover:text-red-500 transition-colors p-1" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>
            </tr>
        `).join('');
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function getStatusBadgeClass(status) {
        if (status === 'MATCH') return 'bg-green-100 text-green-800 border-green-200';
        return 'bg-red-100 text-red-800 border-red-200';
    }

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
    if (refreshBtn) refreshBtn.addEventListener('click', () => loadHistory(1));

    // ============================================================
    // 2. SCANNER LOGIC
    // ============================================================
    
    function toggleScanner(active) {
        console.log("Toggle Scanner Clicked:", active); // Debugging
        isScanning = active;
        if(active) {
            if(scanFormContainer) scanFormContainer.classList.remove("hidden");
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
                scanInput.focus();
            }
        } else {
            if(scanFormContainer) scanFormContainer.classList.add("hidden");
            if(startScanBtn) {
                startScanBtn.disabled = false;
                startScanBtn.classList.remove("opacity-50");
            }
            if(stopScanBtn) {
                stopScanBtn.disabled = true;
                stopScanBtn.classList.add("opacity-50");
            }
        }
    }

    if (startScanBtn) {
        startScanBtn.addEventListener("click", () => toggleScanner(true));
        console.log("Start Button Connected");
    }
    if (stopScanBtn) stopScanBtn.addEventListener("click", () => toggleScanner(false));

    // Auto-focus logic
    document.addEventListener("click", (e) => {
        if(isScanning && scanInput && e.target !== scanInput && !e.target.closest("button") && !e.target.closest("a")) {
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
                updateScanCount();
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

    function updateScanCount() {
        if(!scanCount) return;
        let current = parseInt(scanCount.textContent) || 0;
        scanCount.textContent = current + 1;
    }

    function showScanResult(status, msg, isError = false) {
        if(!scanResult) return;
        
        if (isError) {
            scanResult.innerHTML = `
                <div class="inline-flex items-center px-4 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 shadow-sm animate-pulse">
                    <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i> 
                    <span class="font-bold">${msg}</span>
                </div>`;
        } else {
            const isMatch = (status === 'MATCH');
            const colorClass = isMatch ? 'bg-green-50 text-green-700 border-green-200' : 'bg-orange-50 text-orange-700 border-orange-200';
            const icon = isMatch ? 'check-circle' : 'alert-triangle';
            
            scanResult.innerHTML = `
                <div class="inline-flex items-center px-6 py-3 rounded-lg ${colorClass} border shadow-sm transform transition-all scale-100">
                    <i data-lucide="${icon}" class="w-6 h-6 mr-3"></i> 
                    <div>
                        <div class="font-bold text-lg leading-none">${status}</div>
                        <div class="text-xs opacity-80 uppercase tracking-wide mt-1">${msg}</div>
                    </div>
                </div>`;
        }
        if(typeof lucide !== 'undefined') lucide.createIcons();
        
        setTimeout(() => {
            if(scanResult.innerHTML !== "") scanResult.innerHTML = "";
        }, 3000);
    }

    window.deleteScan = function(id, btn) {
        if(!confirm("Are you sure you want to remove this record?")) return;
        
        fetch("stocktake_fg_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: 'delete_scan', log_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadHistory(1); 
            } else {
                alert(data.message || "Delete failed");
            }
        });
    };

    // Initial Load
    loadHistory(1);
});