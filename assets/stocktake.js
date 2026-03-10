document.addEventListener("DOMContentLoaded", () => {
    const $ = (id) => document.getElementById(id);
  
    // --- Main Control Elements ---
    const startScanBtn = $("startScanBtn");
    const stopScanBtn = $("stopScanBtn");
    const scanCount = $("scanCount");
    const scanFormContainer = $("scanFormContainer");
    
    // --- Scanner Flow Elements ---
    const scanRackingInput = $("scanRackingInput"); 
    const btnInsertRack = $("btnInsertRack");       
    const qrBoxContainer = $("qrBoxContainer");
    const scanInput = $("scanInput");               
    const scanResult = $("scanResult");
    
    // --- Async Queue Variables ---
    const scanQueue = []; 
    let isWorkerBusy = false; 

    // --- History Filter Elements ---
    const tableBody = $("scanHistoryTableBody");
    const historySearchInput = $("historySearchInput");
    const searchColumn = $("searchColumn");
    const refreshBtn = $("refreshBtn");
    const dateFrom = $("dateFrom");
    const dateTo = $("dateTo");
    const downloadCsvBtn = $("downloadCsvBtn");
    const paginationControls = $("paginationControls");
    const statusFilter = $("statusFilter"); 

    // --- Manual Modal Elements ---
    const manualEntryBtn = $("manualEntryBtn");
    const manualEntryModal = $("manualEntryModal");
    const closeManualModal = $("closeManualModal");
    const cancelManualEntry = $("cancelManualEntry");
    const fetchDetailsBtn = $("fetchDetailsBtn");
    const submitManualEntry = $("submitManualEntry");
    const manualDetailsContainer = $("manualDetailsContainer");
    const manualStatusMessage = $("manualStatusMessage");
    const manualTagId = $("manual_tag_id"); 
    const modalRackingLoc = $("modalRackingLoc");
  
    let currentPage = 1;
    
    // --- GLOBAL DATA STORE FOR EDIT ---
    window.lastHistoryData = [];

    // ============================================================
    // 1. HISTORY & FILTERS
    // ============================================================
    
    function loadHistory(page = 1) {
        currentPage = page;
        if (tableBody && scanQueue.length === 0) {
           // tableBody.innerHTML = `<tr><td colspan="11" class="text-center py-10 text-gray-500">Loading history...</td></tr>`;
        }
        
        const params = new URLSearchParams({
            action: 'get_stocktake_data',
            page: page,
            search: historySearchInput ? historySearchInput.value : '',
            search_column: searchColumn ? searchColumn.value : 'tag_id',
            date_from: dateFrom ? dateFrom.value : '',
            date_to: dateTo ? dateTo.value : '',
            status_filter: statusFilter ? statusFilter.value : 'all'
        });

        fetch(`get_stocktake_history.php?${params}`)
            .then(res => res.json())
            .then(resp => {
                if(resp.success) {
                    renderHistoryTable(resp.data);
                    renderPagination(resp.total_pages, resp.current_page);
                } else {
                    tableBody.innerHTML = `<tr><td colspan="11" class="text-center py-10 text-red-500">Error loading data.</td></tr>`;
                }
            })
            .catch(err => console.error("Fetch error:", err));
    }

    function renderHistoryTable(data) {
        if (!tableBody) return;
        
        window.lastHistoryData = data; 
        
        if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="11" class="text-center py-10 text-gray-500">No records found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = data.map((row, index) => `
            <tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                <td class="px-4 py-3 text-gray-600">${row.scan_time_fmt}</td>
                <td class="px-4 py-3 font-bold">${row.tag_id}</td>
                <td class="px-4 py-3">${row.receiving_date_fmt}</td>
                <td class="px-4 py-3">${row.part_name}</td>
                <td class="px-4 py-3">${row.part_no}</td>
                <td class="px-4 py-3 font-mono">${row.erp_code}</td>
                <td class="px-4 py-3">${row.seq_no}</td>
                <td class="px-4 py-3 font-bold text-emerald-600">${row.qty}</td>
                
                <td class="px-4 py-3 font-bold text-gray-800 text-center">
                    ${row.scanned_location}
                </td>

                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-md text-[10px] font-bold border ${getStatusBadgeClass(row.status)}">
                        ${row.status}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="openEditModal(${index})" class="text-blue-600 hover:text-blue-800 transition-colors" title="Edit Full Info">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button onclick="deleteScan(${row.id}, this)" class="text-red-600 hover:text-red-800 transition-colors" title="Delete">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function getStatusBadgeClass(status) {
        const config = {
            'MATCH': 'bg-green-100 text-green-700 border-green-200',
            'UNMATCH': 'bg-red-100 text-red-700 border-red-200',
            'NOT REGISTERED': 'bg-gray-100 text-gray-500 border-gray-200'
        };
        return config[status] || 'bg-gray-50';
    }

    function renderPagination(totalPages, current) {
        if (!paginationControls) return;
        let html = '';
        if(totalPages > 1) {
            html += `<button class="px-3 py-1 border rounded mx-1 ${current === 1 ? 'opacity-50' : 'hover:bg-gray-100'}" onclick="changePage(${current - 1})" ${current === 1 ? 'disabled' : ''}>Prev</button>`;
            html += `<span class="px-3 py-1 text-gray-600">Page ${current} of ${totalPages}</span>`;
            html += `<button class="px-3 py-1 border rounded mx-1 ${current === totalPages ? 'opacity-50' : 'hover:bg-gray-100'}" onclick="changePage(${current + 1})" ${current === totalPages ? 'disabled' : ''}>Next</button>`;
        }
        paginationControls.innerHTML = html;
    }

    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', () => {
            const params = new URLSearchParams({
                action: 'export_stocktake_csv',
                search: historySearchInput ? historySearchInput.value : '',
                search_column: searchColumn ? searchColumn.value : 'tag_id',
                date_from: dateFrom ? dateFrom.value : '',
                date_to: dateTo ? dateTo.value : '',
                status_filter: statusFilter ? statusFilter.value : 'all'
            });
            window.location.href = `get_stocktake_history.php?${params.toString()}`;
        });
    }

    window.changePage = (p) => loadHistory(p);

    if (historySearchInput) historySearchInput.addEventListener('input', () => loadHistory(1));
    if (searchColumn) searchColumn.addEventListener('change', () => loadHistory(1));
    if (dateFrom) dateFrom.addEventListener('change', () => loadHistory(1));
    if (dateTo) dateTo.addEventListener('change', () => loadHistory(1));
    if (statusFilter) statusFilter.addEventListener('change', () => loadHistory(1));
    
    if (refreshBtn) refreshBtn.addEventListener('click', () => {
        historySearchInput.value = '';
        if(dateFrom) dateFrom.value = '';
        if(dateTo) dateTo.value = '';
        if(statusFilter) statusFilter.value = 'all';
        loadHistory(1);
    });

    if (startScanBtn) {
        startScanBtn.addEventListener("click", () => {
            scanFormContainer.classList.remove("hidden");
            resetScannerFlow();
            startScanBtn.disabled = true;
            stopScanBtn.disabled = false;
        });
    }

    if (stopScanBtn) {
        stopScanBtn.addEventListener("click", () => {
            scanFormContainer.classList.add("hidden");
            startScanBtn.disabled = false;
            stopScanBtn.disabled = true;
            resetScannerFlow();
            scanCount.textContent = "0"; 
            scanQueue.length = 0; 
        });
    }

    function resetScannerFlow() {
        scanRackingInput.value = "";
        scanRackingInput.disabled = false;
        scanRackingInput.focus();
        scanInput.value = "";
        scanInput.disabled = true;
        manualEntryBtn.disabled = true; 
        qrBoxContainer.classList.add("opacity-50");
        btnInsertRack.innerHTML = `<i data-lucide="lock" class="w-4 h-4"></i> INSERT / LOCK RACK`;
        btnInsertRack.classList.replace("bg-green-600", "bg-gray-800");
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    btnInsertRack.addEventListener("click", confirmRackingLocation);
    scanRackingInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") { e.preventDefault(); confirmRackingLocation(); }
    });

    function confirmRackingLocation() {
        const rackLoc = scanRackingInput.value.trim();
        if (!rackLoc) return;

        scanRackingInput.disabled = true;
        btnInsertRack.innerHTML = `<i data-lucide="check-circle" class="w-4 h-4"></i> LOCKED: ${rackLoc}`;
        btnInsertRack.classList.replace("bg-gray-800", "bg-green-600");
        
        qrBoxContainer.classList.remove("opacity-50");
        scanInput.disabled = false; 
        scanInput.focus();
        manualEntryBtn.disabled = false;
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    scanInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault(); 
            let qrData = scanInput.value.trim();
            scanInput.value = ""; 
            if (!qrData) return;
            scanQueue.push(qrData);
            updateQueueStatus();
            processQueue(); 
        }
    });

    async function processQueue() {
        if (isWorkerBusy) return;
        if (scanQueue.length === 0) {
            updateQueueStatus();
            return;
        }

        isWorkerBusy = true;
        const currentQrData = scanQueue[0]; 
        const rackLoc = scanRackingInput.value.trim();

        try {
            const response = await fetch("stocktake.api.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ 
                    action: 'submit_scan', 
                    qr_data: currentQrData, 
                    racking_location: rackLoc 
                })
            });

            const data = await response.json();

            if (data.success) {
                scanQueue.shift(); 
                updateScanCount();
                loadHistory(currentPage); 
            } else {
                scanQueue.shift(); 
                alert(`Error: ${data.message}`);
            }

        } catch (err) {
            console.error("Queue Error:", err);
            await new Promise(r => setTimeout(r, 2000));
        } finally {
            isWorkerBusy = false;
            updateQueueStatus();
            processQueue(); 
        }
    }

    function updateQueueStatus() {
        if (scanQueue.length > 0) {
            scanResult.innerHTML = `<span class="text-amber-600 font-bold animate-pulse">Processing ${scanQueue.length} items...</span>`;
        } else {
            scanResult.innerHTML = `<span class="text-green-600 font-bold">Ready to Scan</span>`;
        }
    }

    function updateScanCount() {
        let current = parseInt(scanCount.textContent);
        scanCount.textContent = current + 1;
    }

    window.openStockModal = function(mode) {
        window.currentStockMode = mode;
        const modal = $('stockModal');
        const input = $('modalStockSearchInput'); 
        
        $('stockModalTitle').textContent = (mode === 'location') ? "Search Scanned Location" : "Find Scanned Part / ERP / Seq";
        $('stockTableBody').innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">Waiting for search...</td></tr>`;
        
        input.value = '';
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
            input.focus();
        }, 10);
    };

    window.closeStockModal = function() {
        const modal = $('stockModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    };

    $('btnStockSearch').addEventListener('click', performStockSearch);
    $('modalStockSearchInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performStockSearch();
    });

    function performStockSearch() {
        const query = $('modalStockSearchInput').value.trim();
        const tbody = $('stockTableBody');
        if (!query) return;

        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-500">Searching Stocktake Records...</td></tr>`;

        fetch(`get_stocktake_lookup.php?type=${window.currentStockMode}&query=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(resp => {
                if (resp.success && resp.data.length > 0) {
                    tbody.innerHTML = resp.data.map(row => `
                        <tr class="hover:bg-gray-50 border-b">
                            <td class="px-4 py-3 font-bold text-gray-800">${row.RACKING_LOCATION}</td>
                            <td class="px-4 py-3 ${row.fifo_status === 'critical' ? 'text-red-600 font-bold' : ''}">${row.date_fmt}</td>
                            <td class="px-4 py-3">${row.PART_NO}</td>
                            <td class="px-4 py-3 font-mono text-indigo-600">${row.ERP_CODE}</td>
                            <td class="px-4 py-3">${row.SEQ_NO}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">${row.PART_NAME}</td>
                            <td class="px-4 py-3 text-center font-bold text-emerald-600">${row.total_qty}</td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400">No scanned items found for "${query}".</td></tr>`;
                }
            })
            .catch(() => {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-red-500">Database Connection Error</td></tr>`;
            });
    }

    manualEntryBtn.addEventListener("click", () => {
        const rackLoc = scanRackingInput.value.trim();
        if (!rackLoc) return alert("Please lock a Racking Location first!");
        
        modalRackingLoc.textContent = rackLoc;
        manualTagId.value = "";
        manualDetailsContainer.classList.add("hidden");
        submitManualEntry.disabled = true;
        manualStatusMessage.textContent = "";
        
        manualEntryModal.classList.remove("invisible", "opacity-0");
        manualTagId.focus();
    });

    fetchDetailsBtn.addEventListener("click", () => {
        const tId = manualTagId.value.trim();
        if (!tId) {
            alert("Please enter a Tag ID first.");
            return;
        }

        fetchDetailsBtn.innerText = "Checking...";
        fetchDetailsBtn.disabled = true;

        fetch("stocktake.api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "fetch_details", ticket_id: tId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const safeUpdate = (elementId, value) => {
                    const el = document.getElementById(elementId);
                    if (el) el.textContent = value || "-"; 
                };

                safeUpdate("manual_part_name", data.data.part_name);
                safeUpdate("manual_part_no_fg", data.data.part_no_fg);
                safeUpdate("manual_erp_code_b", data.data.erp_code);
                safeUpdate("manual_seq_no", data.data.seq_no);
                safeUpdate("manual_rack_in", data.data.rack_in);
                safeUpdate("manual_receiving_date", data.data.receiving_date_fmt);
                
                if (manualDetailsContainer) manualDetailsContainer.classList.remove("hidden");
                if (submitManualEntry) submitManualEntry.disabled = false;
                
                if (manualStatusMessage) {
                    manualStatusMessage.innerText = "Part Found!";
                    manualStatusMessage.className = "text-center text-green-600 font-bold";
                }
            } else {
                alert(data.message || "Tag not found in database.");
                if (manualDetailsContainer) manualDetailsContainer.classList.add("hidden");
                if (submitManualEntry) submitManualEntry.disabled = true;
                if (manualStatusMessage) manualStatusMessage.innerText = "";
            }
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            alert("System Error: Check console (F12) for details.");
        })
        .finally(() => { 
            fetchDetailsBtn.innerText = "Check"; 
            fetchDetailsBtn.disabled = false;
        });
    });

    submitManualEntry.addEventListener("click", () => {
        const rackLoc = scanRackingInput.value.trim();
        submitManualEntry.disabled = true;

        fetch("stocktake.api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "submit_manual",
                ticket_id: manualTagId.value.trim(),
                racking_location: rackLoc 
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                manualEntryModal.classList.add("invisible", "opacity-0");
                loadHistory(1); 
                updateScanCount(); 
                alert("Manual Stocktake Saved!");
            } else {
                alert("Error: " + data.message);
            }
        })
        .finally(() => { submitManualEntry.disabled = false; });
    });

    if (closeManualModal) {
        closeManualModal.addEventListener("click", () => {
            manualEntryModal.classList.add("invisible", "opacity-0");
        });
    }

    if (cancelManualEntry) {
        cancelManualEntry.addEventListener("click", () => {
            manualEntryModal.classList.add("invisible", "opacity-0");
        });
    }

    // --- FULL EDIT MODAL LOGIC ---
    const editScanModal = $("editScanModal");

    window.openEditModal = function(index) {
        const row = window.lastHistoryData[index];
        if (!row) return;

        // 1. Populate Fields
        $("edit_log_id").value = row.id;
        $("edit_tag_id").value = row.tag_id;
        $("edit_scan_time").textContent = row.scan_time_fmt;
        
        $("edit_part_no").value = row.part_no;
        $("edit_erp").value = row.erp_code;
        $("edit_part_name").value = row.part_name;
        $("edit_seq").value = row.seq_no;
        $("edit_qty").value = row.qty;
        $("edit_loc").value = row.scanned_location;

        // 2. Show Modal
        editScanModal.classList.remove("hidden");
        setTimeout(() => {
            editScanModal.classList.remove("opacity-0");
            editScanModal.querySelector('div').classList.remove("scale-95");
        }, 10);
    };

    window.closeEditModal = function() {
        editScanModal.classList.add("opacity-0");
        editScanModal.querySelector('div').classList.add("scale-95");
        setTimeout(() => editScanModal.classList.add("hidden"), 300);
    };

    window.saveEditScan = function() {
        // --- PASSWORD REMOVED ---
        // Just proceed to save
        
        const payload = {
            action: 'edit_scan',
            log_id: $("edit_log_id").value,
            // password: password, // REMOVED
            tag_id: $("edit_tag_id").value.trim(),
            part_no: $("edit_part_no").value.trim(),
            erp_code: $("edit_erp").value.trim(),
            part_name: $("edit_part_name").value.trim(),
            seq_no: $("edit_seq").value.trim(),
            qty: $("edit_qty").value.trim(),
            scanned_location: $("edit_loc").value.trim()
        };

        fetch("stocktake.api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Record Updated Successfully!");
                closeEditModal();
                loadHistory(currentPage); 
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert("System Error: " + err));
    };

    window.deleteScan = function(id, btn) {
        if(!confirm("Delete this record?")) return;
        const password = prompt("Admin Password:");
        if (password !== 'Admin404') return alert("Unauthorized");

        fetch("stocktake.api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: 'delete_scan', log_id: id, password: password })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.closest('tr').remove();
            } else {
                alert(data.message);
            }
        });
    };

    loadHistory(1);
});