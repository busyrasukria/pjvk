document.addEventListener("DOMContentLoaded", () => {
    const $ = (id) => document.getElementById(id);
  
    // --- Main Elements ---
    const startScanBtn = $("startScanBtn");
    const stopScanBtn = $("stopScanBtn");
    const scanCount = $("scanCount");
    const scanFormContainer = $("scanFormContainer");
    
    // --- Scanner Elements ---
    const scanForm = $("scanForm");
    const scanRackingInput = $("scanRackingInput"); 
    const btnInsertRack = $("btnInsertRack");       
    const qrBoxContainer = $("qrBoxContainer");
    const scanInput = $("scanInput");               
    const scanResult = $("scanResult");
    const rackStatusMsg = $("rackStatusMsg");
    
    // --- Filter Elements ---
    const tableBody = $("scanHistoryTableBody");
    const searchInput = $("historySearchInput");
    const dateFrom = $("dateFrom");
    const dateTo = $("dateTo");
    const refreshBtn = $("refreshBtn");
    const downloadCsvBtn = $("downloadCsvBtn");
    const paginationControls = $("paginationControls");
  
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
  
    let fetchedManualData = null; 
    let currentPage = 1;

    // ============================================================
    // 1. HISTORY & FILTERS
    // ============================================================
    
    function loadHistory(page = 1) {
        currentPage = page;
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-10 text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>`;
        }
        
        const params = new URLSearchParams({
            action: 'get_data',
            page: page,
            search: searchInput ? searchInput.value : '',
            date_from: dateFrom ? dateFrom.value : '',
            date_to: dateTo ? dateTo.value : ''
        });

        fetch(`get_racking_history.php?${params}`)
            .then(res => res.json())
            .then(resp => {
                if(resp.success) {
                    renderTable(resp.data);
                    renderPagination(resp.total_pages, resp.current_page);
                } else {
                    if (tableBody) tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-10 text-red-500">Error loading data</td></tr>`;
                }
            })
            .catch(err => console.error(err));
    }

    function renderTable(data) {
        if (!tableBody) return;
        if(data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-10 text-gray-500">No records found.</td></tr>`;
            return;
        }
        
        tableBody.innerHTML = data.map(row => `
            <tr class="hover:bg-purple-50 transition-colors border-b border-gray-100" id="row-${row.id}">
                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${row.scan_time_fmt}</td>
                <td class="px-4 py-3 font-mono text-purple-700 font-bold">${row.ID_CODE}</td>
                <td class="px-4 py-3 text-gray-500">${row.receiving_date_fmt}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">${row.PART_NAME}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">${row.PART_NO}</td>
                <td class="px-4 py-3 text-indigo-700 font-mono">${row.ERP_CODE}</td>
                <td class="px-4 py-3">${row.SEQ_NO}</td>
                <td class="px-4 py-3 font-bold text-emerald-600">${row.RACK_IN}</td>
                <td class="px-4 py-3 font-bold text-gray-700">${row.RACKING_LOCATION}</td>
                <td class="px-4 py-3 text-center">
                    <button onclick="deleteScan(${row.id}, this)"
                            class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold">
                        <i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    function renderPagination(totalPages, current) {
        if (!paginationControls) return;
        let html = '';
        if(totalPages > 1) {
            html += `<button class="px-3 py-1 border rounded mx-1 ${current===1?'opacity-50 cursor-not-allowed':'hover:bg-gray-100'}" onclick="changePage(${current-1})" ${current===1?'disabled':''}>Prev</button>`;
            html += `<span class="px-3 py-1 text-gray-600">Page ${current} of ${totalPages}</span>`;
            html += `<button class="px-3 py-1 border rounded mx-1 ${current===totalPages?'opacity-50 cursor-not-allowed':'hover:bg-gray-100'}" onclick="changePage(${current+1})" ${current===totalPages?'disabled':''}>Next</button>`;
        }
        paginationControls.innerHTML = html;
    }

    window.changePage = (p) => loadHistory(p);

    if (searchInput) searchInput.addEventListener('input', () => loadHistory(1));
    
    // --- DATE RANGE LOGIC ---
    if (dateFrom) {
        dateFrom.addEventListener('change', () => {
            if (dateTo) {
                dateTo.min = dateFrom.value;
                if (dateTo.value && dateTo.value < dateFrom.value) {
                    dateTo.value = "";
                }
            }
            loadHistory(1);
        });
    }

    if (dateTo) {
        dateTo.addEventListener('change', () => {
            if (dateFrom && dateFrom.value && dateTo.value && dateTo.value < dateFrom.value) {
                alert("End Date cannot be earlier than Start Date.");
                dateTo.value = "";
                return;
            }
            loadHistory(1);
        });
    }
    
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            if(searchInput) searchInput.value = '';
            if(dateFrom) {
                dateFrom.value = '';
                if(dateTo) dateTo.min = ''; 
            }
            if(dateTo) dateTo.value = '';
            loadHistory(1);
        });
    }

    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', () => {
            const params = new URLSearchParams({
                action: 'export_csv',
                search: searchInput.value,
                date_from: dateFrom.value,
                date_to: dateTo.value
            });
            window.location.href = `get_racking_history.php?${params}`;
        });
    }

    // Initial load
    loadHistory(1);

    // ============================================================
    // 2. SCANNER LOGIC
    // ============================================================
    if (startScanBtn) {
        startScanBtn.addEventListener("click", () => {
            scanFormContainer.classList.remove("hidden");
            resetScannerFlow(); // Clear everything on fresh start
            
            // Explicitly set count to 0 on start
            if (scanCount) scanCount.textContent = "0";

            startScanBtn.disabled = true;
            stopScanBtn.disabled = false;
        });
    }
    if (stopScanBtn) {
        stopScanBtn.addEventListener("click", () => {
            scanFormContainer.classList.add("hidden");
            startScanBtn.disabled = false;
            stopScanBtn.disabled = true;
            
            // --- UPDATED LOGIC FOR STOP BUTTON ---
            // When Stop is clicked, we reset the Count and the Rack Lock.
            if (scanCount) scanCount.textContent = "0";
            resetScannerFlow(); // This unlocks the rack for the next time
        });
    }

    function resetScannerFlow() {
        // Unlock Rack Input
        if (scanRackingInput) {
            scanRackingInput.value = "";
            scanRackingInput.disabled = false;
            scanRackingInput.focus();
        }
        
        // Disable Item Input
        if (scanInput) {
            scanInput.value = "";
            scanInput.disabled = true;
        }
        
        // Disable Manual Button & Style it
        if (manualEntryBtn) {
            manualEntryBtn.disabled = true; 
            manualEntryBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        
        // Disable container visual
        if (qrBoxContainer) {
            qrBoxContainer.classList.add("opacity-50");
        }
        
        // Reset Button State
        if (btnInsertRack) {
            btnInsertRack.innerHTML = `<i data-lucide="lock" class="w-4 h-4"></i> INSERT / LOCK RACK`;
            btnInsertRack.classList.remove("bg-green-600");
            btnInsertRack.classList.add("bg-gray-800");
        }
        
        if (rackStatusMsg) {
            rackStatusMsg.textContent = "Scan rack & click insert to proceed";
            rackStatusMsg.className = "text-xs text-gray-500 mt-1 text-center italic";
        }
        
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (btnInsertRack) btnInsertRack.addEventListener("click", confirmRackingLocation);
    if (scanRackingInput) {
        scanRackingInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter") { e.preventDefault(); confirmRackingLocation(); }
        });
    }

    function confirmRackingLocation() {
        const rackLoc = scanRackingInput.value.trim();
        if (rackLoc === "") {
            showScanResult("Racking Location is empty!", "error");
            return;
        }
        
        // Lock the Rack Input
        scanRackingInput.disabled = true;
        btnInsertRack.innerHTML = `<i data-lucide="check-circle" class="w-4 h-4"></i> LOCKED: ${rackLoc}`;
        btnInsertRack.classList.replace("bg-gray-800", "bg-green-600");
        
        if (rackStatusMsg) {
            rackStatusMsg.textContent = "Confirmed. Scan Tag or use Manual Entry.";
            rackStatusMsg.className = "text-xs text-green-600 mt-1 text-center font-bold";
        }
        
        // Enable Step 2 (Scanner AND Manual Button)
        if (qrBoxContainer) {
            qrBoxContainer.classList.remove("opacity-50");
        }
        
        if (scanInput) {
            scanInput.disabled = false;
            scanInput.focus();
        }
        
        // Enable Manual Button Logic & Style
        if (manualEntryBtn) {
            manualEntryBtn.disabled = false; 
            manualEntryBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        
        showScanResult("Waiting for Tag Scan...", "info");
        
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (scanInput) {
        scanInput.addEventListener("keypress", function(e) {
            if (e.key === "Enter") { e.preventDefault(); handleQrSubmit(); }
        });
    }

    function handleQrSubmit() {
        let qrData = scanInput.value.trim();
        const rackLoc = scanRackingInput.value.trim();
        
        if (!qrData) return;

        // Smart Parse for Composite QR (Tag|Date|ID)
        if (qrData.includes('|')) {
            const parts = qrData.split('|');
            const cleanParts = parts.filter(p => p.trim() !== '');
            if (cleanParts.length > 0) {
                qrData = cleanParts[cleanParts.length - 1].trim(); 
            }
        }

        showScanResult("Validating...", "info");
        scanInput.disabled = true; // Temporarily disable while fetching

        fetch("racking_in.api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ 
                action: 'submit_scan', 
                qr_data: qrData, 
                racking_location: rackLoc 
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // --- CHANGED LOGIC HERE ---
                // Do NOT reset the flow. Keep Rack Locked.
                // Just clear the item input for the next item.
                
                scanInput.value = "";
                scanInput.disabled = false; 
                scanInput.focus();
                
                showScanResult("Success: " + qrData, "success");
                handleNewScanRow(data.scanData); 
                updateScanCount(true); 
            } else {
                showScanResult(data.message, "error");
                scanInput.disabled = false;
                scanInput.value = ""; 
                scanInput.focus();
            }
        })
        .catch(err => {
            showScanResult("Server Error", "error");
            scanInput.disabled = false;
        });
    }

    // ============================================================
    // 3. MANUAL ENTRY LOGIC
    // ============================================================
    if (manualEntryBtn) manualEntryBtn.addEventListener("click", () => toggleModal(true));
    if (closeManualModal) closeManualModal.addEventListener("click", () => toggleModal(false));
    if (cancelManualEntry) cancelManualEntry.addEventListener("click", () => toggleModal(false));

    function toggleModal(show) {
        if (!manualEntryModal) return;
        if (show) {
            const rackLoc = scanRackingInput.value.trim();
            if (modalRackingLoc) modalRackingLoc.textContent = rackLoc || "UNKNOWN";
            
            if (manualTagId) manualTagId.value = "";
            fetchedManualData = null;
            
            if (manualDetailsContainer) manualDetailsContainer.classList.add("hidden");
            if (submitManualEntry) submitManualEntry.disabled = true;
            if (manualStatusMessage) manualStatusMessage.textContent = "";
            
            manualEntryModal.classList.remove("invisible", "opacity-0");
            const innerDiv = manualEntryModal.querySelector('div');
            if(innerDiv) {
                innerDiv.classList.remove("scale-95");
                innerDiv.classList.add("scale-100");
            }
            if (manualTagId) manualTagId.focus();
        } else {
            manualEntryModal.classList.add("invisible", "opacity-0");
            const innerDiv = manualEntryModal.querySelector('div');
            if(innerDiv) {
                innerDiv.classList.add("scale-95");
                innerDiv.classList.remove("scale-100");
            }
            // Return focus to scan input if we are in active scanning mode
            if(!startScanBtn.disabled && scanInput && !scanInput.disabled) {
                scanInput.focus();
            }
        }
    }

    if (fetchDetailsBtn) {
        fetchDetailsBtn.addEventListener("click", () => {
            const tId = manualTagId ? manualTagId.value.trim() : "";
            if (!tId) { showManualStatus("Please enter a Tag ID", "error"); return; }

            fetchDetailsBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Checking...`;
            fetchDetailsBtn.disabled = true;

            fetch("racking_in.api.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "fetch_details", ticket_id: tId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fetchedManualData = data.data;
                    $("manual_part_name").textContent = fetchedManualData.part_name;
                    $("manual_part_no_fg").textContent = fetchedManualData.part_no_fg;
                    $("manual_erp_code_b").textContent = fetchedManualData.erp_code;
                    $("manual_seq_no").textContent = fetchedManualData.seq_no;
                    $("manual_rack_in").textContent = fetchedManualData.rack_in;
                    $("manual_receiving_date").textContent = fetchedManualData.receiving_date_fmt;
                    
                    if (manualDetailsContainer) manualDetailsContainer.classList.remove("hidden");
                    if (submitManualEntry) submitManualEntry.disabled = false;
                    showManualStatus("Ticket Found in Receiving.", "success");
                } else {
                    fetchedManualData = null;
                    if (manualDetailsContainer) manualDetailsContainer.classList.add("hidden");
                    if (submitManualEntry) submitManualEntry.disabled = true;
                    showManualStatus(data.message, "error");
                }
            })
            .catch(err => showManualStatus("Connection Error", "error"))
            .finally(() => {
                fetchDetailsBtn.innerHTML = "Check";
                fetchDetailsBtn.disabled = false;
            });
        });
    }

   // ==========================================
    // MANUAL SUBMIT (KEEPS RACK LOCKED)
    // ==========================================

if (submitManualEntry) {
    submitManualEntry.addEventListener("click", () => {
        if (!fetchedManualData) return;
        
        // 1. Get the current LOCKED Rack Location
        const rackLoc = scanRackingInput.value.trim();
        
        const payload = {
            action: "submit_manual",
            ticket_id: manualTagId.value.trim(),
            racking_location: rackLoc 
        };
        
        // UI Loading State
        const originalBtnContent = submitManualEntry.innerHTML;
        submitManualEntry.disabled = true;
        submitManualEntry.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;

        fetch("racking_in.api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // --- SUCCESS SCENARIO ---
                
                // 1. Close the Modal immediately
                toggleModal(false);
                
                // 2. Update the Table with the new row
                handleNewScanRow(data.scanData);
                
                // 3. Increment Count
                updateScanCount(true); 
                
                
                showScanResult(`Manual Success: ${payload.ticket_id}`, "success");

                // 5. Clear the manual input for next time
                manualTagId.value = ""; 
                
                // 6. Refocus the main scanner for speed
                if(scanInput) {
                    scanInput.value = "";
                    scanInput.focus();
                }

            } else {
                // --- ERROR SCENARIO ---
                showManualStatus(data.message, "error");
            }
        })
        .catch(err => {
            showManualStatus("Connection Error", "error");
        })
        .finally(() => {
            // Reset button state (runs on both success and error)
            submitManualEntry.disabled = false; 
            submitManualEntry.innerHTML = originalBtnContent;
        });
    });
}
    // --- Utilities ---
    function showManualStatus(msg, type) {
        if (!manualStatusMessage) return;
        manualStatusMessage.textContent = msg;
        manualStatusMessage.className = type === 'error' ? "mt-4 text-center font-semibold text-red-600 text-sm" : "mt-4 text-center font-semibold text-green-600 text-sm";
    }

    function showScanResult(msg, type) {
        if (!scanResult) return;
        scanResult.textContent = msg;
        scanResult.className = type === 'error' ? 
            "mt-4 text-center h-5 font-semibold text-red-600" : 
            (type === 'success' ? "mt-4 text-center h-5 font-semibold text-green-600" : "mt-4 text-center h-5 font-semibold text-gray-500");
    }

    function updateScanCount(increment) {
        if (!scanCount) return;
        let c = parseInt(scanCount.textContent || "0");
        if (isNaN(c)) c = 0;
        if (increment) {
            scanCount.textContent = c + 1;
        }
    }
    
    // --- DOM Update Function ---
    function handleNewScanRow(scanData) {
        if (!tableBody) return;
        
        const emptyRow = tableBody.querySelector('td[colspan="10"]');
        if(emptyRow) emptyRow.parentElement.remove();

        const newRow = document.createElement("tr");
        newRow.id = `row-${scanData.log_id}`; 
        newRow.className = "animate-pulse bg-purple-100 border-b border-gray-100"; 
  
        newRow.innerHTML = `
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${scanData.scan_time}</td>
            <td class="px-4 py-3 font-mono text-purple-700 font-bold">${scanData.unique_no}</td>
            <td class="px-4 py-3 text-gray-500">${scanData.receiving_date}</td>
            <td class="px-4 py-3 text-gray-800 font-medium">${scanData.part_name}</td>
            <td class="px-4 py-3 text-gray-800 font-medium">${scanData.part_no}</td>
            <td class="px-4 py-3 text-indigo-700 font-mono">${scanData.erp_code}</td>
            <td class="px-4 py-3">${scanData.seq_no}</td>
            <td class="px-4 py-3 font-bold text-emerald-600">${scanData.rack_in}</td>
            <td class="px-4 py-3 font-bold text-gray-700">${scanData.racking_location}</td>
            <td class="px-4 py-3 text-center">
                <button onclick="deleteScan(${scanData.log_id}, this)"
                        class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold">
                    <i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete
                </button>
            </td>
        `;
  
        tableBody.prepend(newRow);
        setTimeout(() => newRow.classList.remove("animate-pulse", "bg-purple-100"), 1000);
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
});

// ============================================================
// 4. DELETE LOGIC
// ============================================================
window.deleteScan = function(id, btn) {
    if(!confirm("Are you sure you want to delete this Rack-In record?")) return;
    const password = prompt("Please enter Admin Password to delete:");
    if (!password) return;

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ...`;

    fetch("racking_in.api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
            action: 'delete_scan', 
            log_id: id,
            password: password 
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = btn.closest('tr');
            row.style.transition = "all 0.5s";
            row.style.opacity = "0";
            setTimeout(() => row.remove(), 500);
            alert("Deleted Successfully");
        } else {
            alert("Error: " + data.message);
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        console.error(err);
        alert("Connection Error");
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
};

                            