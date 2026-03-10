document.addEventListener("DOMContentLoaded", () => {
    const $ = (id) => document.getElementById(id);

    // --- Main Elements ---
    const startBtn = $("startScanBtn");
    const stopBtn = $("stopScanBtn");
    const manualBtn = $("manualBtn");
    const supplierSelect = $("supplierSelect");
    const jobInput = $("jobNoInput");
    const jobInputCard = $("jobInputCard");
    const scanFormContainer = $("scanFormContainer");
    const scanInput = $("scanInput");
    const scanStatus = $("scanStatus");
    const scanCountEl = $("scanCount");

    // --- Modal Elements ---
    const modal = $("manualModal");
    const closeModal = $("closeModal");
    const cancelManual = $("cancelManual");
    const manualInputData = $("manualInputData");
    const checkManualBtn = $("checkManualBtn");
    const manualFeedback = $("manualFeedback");
    const saveManualBtn = $("saveManualBtn");
    const manualDetails = $("manualDetails");
    
    const extraInputContainer = $("extraInputContainer");
    const extraInputLabel = $("extraInputLabel");
    const manualExtraInput = $("manualExtraInput");

    // --- History & Filter Elements ---
    const historyTableHead = document.querySelector("#receivingHistoryTable thead tr");
    const historyTableBody = $("receivingHistoryTableBody");
    const historySearch = $("historySearchInput");
    const historyDateFrom = $("dateFrom");
    const historyDateTo = $("dateTo");
    const refreshBtn = $("refreshBtn");
    const downloadCsvBtn = $("downloadCsvBtn");
    const historyPagination = $("historyPagination");

    // --- State ---
    let isScanning = false;
    let currentBatchId = null;
    let sessionCount = 0;
    let currentHistoryPage = 1;

    // ==================================================
    // DATE LOGIC (Native HTML5)
    // ==================================================
    
    if (historyDateFrom && historyDateTo) {
        historyDateFrom.addEventListener('change', () => {
            const fromVal = historyDateFrom.value;
            historyDateTo.min = fromVal;
            if (historyDateTo.value && historyDateTo.value < fromVal) {
                historyDateTo.value = "";
            }
            loadReceivingHistory(1);
        });

        historyDateTo.addEventListener('change', () => {
            loadReceivingHistory(1);
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            if(historySearch) historySearch.value = '';
            if(historyDateFrom) historyDateFrom.value = '';
            if(historyDateTo) {
                historyDateTo.value = '';
                historyDateTo.removeAttribute('min'); 
            }
            loadReceivingHistory(1);
        });
    }

    // ==================================================
    // DATA LOADING
    // ==================================================
    window.loadReceivingHistory = function(page = 1) {
        const supplier = supplierSelect.value;
        if (!supplier) {
            if(historyTableBody) historyTableBody.innerHTML = `<tr><td colspan="10" class="px-6 py-8 text-center text-gray-400 italic">Please select a supplier first.</td></tr>`;
            return;
        }

        updateTableStructure(supplier);
        currentHistoryPage = page;
        
        // UPDATED: Check for MASZ here
        const colSpan = (supplier === 'YTEC' || supplier === 'MAZDA' || supplier === 'MASZ') ? 10 : 9;
        historyTableBody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-gray-500"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-indigo-600"></i>Loading data...</td></tr>`;
        lucide.createIcons();

        const params = new URLSearchParams({ 
            action: 'get_data', 
            supplier: supplier, 
            search: historySearch ? historySearch.value.trim() : '', 
            start_date: historyDateFrom ? historyDateFrom.value : '', 
            end_date: historyDateTo ? historyDateTo.value : '',     
            page: page 
        });

        fetch(`get_receiving_history.php?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) { 
                    renderHistoryTable(res.data, supplier); 
                    renderPagination(res.total_pages, res.current_page); 
                } else { 
                    historyTableBody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-red-500 font-bold">Error: ${res.message || 'Unknown error'}</td></tr>`; 
                }
            })
            .catch(err => {
                console.error("Fetch Error:", err);
                historyTableBody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-red-500">Connection Error. Check Console.</td></tr>`;
            });
    };

    window.updateTableStructure = function(supplier) {
        let headers = `<th class="px-6 py-3">ID</th><th class="px-6 py-3">Date/Time</th><th class="px-6 py-3">Job/Scat No</th><th class="px-6 py-3">Part No</th><th class="px-6 py-3">ERP Code</th><th class="px-6 py-3">Seq No</th><th class="px-6 py-3">Part Name</th><th class="px-6 py-3 text-center">Qty</th>`;
        
        // UPDATED: Check for MASZ here
        if (supplier === 'YTEC') headers += `<th class="px-6 py-3 text-center">Invoice No</th>`;
        else if (supplier === 'MAZDA' || supplier === 'MASZ') headers += `<th class="px-6 py-3 text-center">Serial</th>`;
        headers += `<th class="px-6 py-3 text-center">Action</th>`;
        
        if(historyTableHead) historyTableHead.innerHTML = headers;
    };

    function renderHistoryTable(data, supplier) {
        // UPDATED: Check for MASZ here
        const colSpan = (supplier === 'YTEC' || supplier === 'MAZDA' || supplier === 'MASZ') ? 10 : 9;
        if (!data || data.length === 0) { 
            historyTableBody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-gray-400 italic">No records found.</td></tr>`; 
            return; 
        }

        let idPrefix = 'R';
        if (supplier === 'MAZDA') idPrefix = 'M';
        else if (supplier === 'MASZ') idPrefix = 'MZ'; // MASZ uses MZ prefix

        historyTableBody.innerHTML = data.map(row => {
            let extraCell = '';
            // UPDATED: Check for MASZ here
            if (supplier === 'YTEC') extraCell = `<td class="px-6 py-4 text-center text-black-600 text-s">${row.inv_no || '-'}</td>`;
            else if (supplier === 'MAZDA' || supplier === 'MASZ') extraCell = `<td class="px-6 py-4 text-center text-black-600 text-s">${row.serial || '-'}</td>`;
            
            return `
                <tr class="bg-white hover:bg-gray-50 border-b transition-colors">
                    <td class="px-6 py-4 font-bold text-indigo-700">${idPrefix}${row.id}</td>
                    <td class="px-6 py-4 text-gray-700 whitespace-nowrap text-sm font-medium">${row.scan_time}</td>
                    <td class="px-6 py-4 font-semibold text-gray-700">${row.job_no}</td>
                    <td class="px-6 py-4 font-medium text-blue-600">${row.part_no}</td>
                    <td class="px-6 py-4 text-gray-800 font-mono text-sm font-bold">${row.erp_code}</td>
                    <td class="px-6 py-4 font-bold text-gray-700">${row.seq_no || '-'}</td>
                    <td class="px-6 py-4 text-gray-800 truncate max-w-xs">${row.part_name}</td>
                    <td class="px-6 py-4 text-center font-bold text-emerald-600">${row.qty}</td>
                    ${extraCell}
                    <td class="px-6 py-4 text-center flex justify-center gap-2">
                        <button onclick="reprintReceivingTag(${row.id}, '${supplier}')" class="bg-blue-50 hover:bg-blue-100 text-blue-600 p-2 rounded shadow-sm" title="Reprint"><i data-lucide="printer" class="w-4 h-4"></i></button>
                        <button onclick="deleteReceivingLog(${row.id}, '${supplier}', this)" class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded shadow-sm" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </td>
                </tr>
            `;
        }).join('');
        lucide.createIcons();
    }

    function renderPagination(total, current) { 
        if(!historyPagination) return;
        historyPagination.innerHTML = '';
        if (total <= 1) return;
        
        let html = `<button class="px-3 py-1 border rounded ${current === 1 ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-100'}" onclick="loadReceivingHistory(${current - 1})" ${current === 1 ? 'disabled' : ''}>Prev</button>`;
        
        let start = Math.max(1, current - 1);
        let end = Math.min(total, current + 1);
        for (let i = start; i <= end; i++) {
            html += `<button class="px-3 py-1 border rounded ${i === current ? 'bg-indigo-600 text-white border-indigo-600' : 'hover:bg-gray-100'}" onclick="loadReceivingHistory(${i})">${i}</button>`;
        }
        
        html += `<button class="px-3 py-1 border rounded ${current === total ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-100'}" onclick="loadReceivingHistory(${current + 1})" ${current === total ? 'disabled' : ''}>Next</button>`;
        historyPagination.innerHTML = html;
    }

    // --- Event Listeners ---
    if(historySearch) historySearch.addEventListener('input', () => loadReceivingHistory(1));
    
    if(downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', () => { 
            const params = new URLSearchParams({ 
                action: 'download_csv', 
                supplier: supplierSelect.value, 
                search: historySearch ? historySearch.value.trim() : '', 
                start_date: historyDateFrom ? historyDateFrom.value : '', 
                end_date: historyDateTo ? historyDateTo.value : ''
            });
            window.location.href = `get_receiving_history.php?${params.toString()}`; 
        });
    }

    // ==================================================
    // MODAL & SCAN LOGIC
    // ==================================================
    const toggleModal = (show) => {
        if (show) {
            if (!jobInput.value.trim()) { 
                const supplier = supplierSelect.value;
                // UPDATED: Check for MASZ here
                const label = (supplier === 'MAZDA' || supplier === 'MASZ') ? "Scat Number" : "Job Number";
                alert(`Please enter the ${label} first.`); 
                jobInput.focus(); 
                return; 
            }
            modal.classList.remove("hidden"); setTimeout(() => modal.classList.remove("opacity-0"), 10);
            manualInputData.value = ""; 
            manualExtraInput.value = "";
            manualInputData.focus(); 
            manualDetails.classList.add("hidden");
            saveManualBtn.disabled = true; 
            manualFeedback.textContent = "";
            const supplier = supplierSelect.value;
            if (supplier === 'YTEC') {
                extraInputContainer.classList.remove('hidden');
                extraInputLabel.textContent = "Invoice No";
                manualExtraInput.placeholder = "Enter Invoice No";
            } else if (supplier === 'MAZDA' || supplier === 'MASZ') { // UPDATED
                extraInputContainer.classList.remove('hidden');
                extraInputLabel.textContent = "Serial No";
                manualExtraInput.placeholder = "Enter Serial No";
            } else {
                extraInputContainer.classList.add('hidden');
            }
        } else {
            modal.classList.add("opacity-0"); setTimeout(() => modal.classList.add("hidden"), 300);
            if(isScanning) scanInput.focus();
        }
    };

    manualBtn.addEventListener("click", () => toggleModal(true));
    closeModal.addEventListener("click", () => toggleModal(false));
    cancelManual.addEventListener("click", () => toggleModal(false));

    checkManualBtn.addEventListener("click", async () => {
        const query = manualInputData.value.trim();
        if(!query) return;
        manualFeedback.textContent = "Checking..."; manualFeedback.className = "mt-2 text-sm font-bold text-blue-600";
        try {
            const res = await fetch("receiving_scan_handler.php", {
                method: "POST",
                body: JSON.stringify({ action: 'manual_check', query: query })
            });
            const data = await res.json();
            if(data.success) {
                manualFeedback.textContent = "Part Found!"; manualFeedback.className = "mt-2 text-sm font-bold text-green-600";
                $("m_partNo").textContent = data.data.part_no;
                $("m_erpCode").textContent = data.data.erp_code;
                $("m_partName").textContent = data.data.part_name;
                $("m_seqNo").textContent = data.data.seq_no;
                manualDetails.classList.remove("hidden"); 
                saveManualBtn.disabled = false;
            } else {
                manualFeedback.textContent = data.message; manualFeedback.className = "mt-2 text-sm font-bold text-red-600";
                manualDetails.classList.add("hidden"); saveManualBtn.disabled = true;
            }
        } catch(e) { console.error(e); }
    });

    saveManualBtn.addEventListener("click", () => {
        const data = manualInputData.value.trim();
        const extra = manualExtraInput.value.trim();
        const supplier = supplierSelect.value;
        // UPDATED: Check for MASZ
        if ((supplier === 'YTEC' || supplier === 'MAZDA' || supplier === 'MASZ') && !extra) {
            alert("Please fill in all fields.");
            manualExtraInput.focus();
            return;
        }
        processScanData(data, 'MANUAL', extra);
    });

    async function processScanData(dataString, type, manualExtraData = null) {
        if(type === 'SCAN') { scanInput.disabled = true; scanStatus.textContent = "Processing..."; }
        try {
            const payload = {
                qr_data: dataString,
                supplier: supplierSelect.value,
                job_no: jobInput.value.trim(),
                batch_id: currentBatchId,
                input_type: type
            };
            if (type === 'MANUAL' && manualExtraData) {
                if (supplierSelect.value === 'YTEC') payload.manual_inv_no = manualExtraData;
                // UPDATED: Check for MASZ
                if (supplierSelect.value === 'MAZDA' || supplierSelect.value === 'MASZ') payload.manual_serial = manualExtraData;
            }
            const response = await fetch("receiving_scan_handler.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const res = await response.json();
            if (res.success) {
                sessionCount++;
                if(scanCountEl) scanCountEl.textContent = sessionCount;
                loadReceivingHistory(1); 
                if(type === 'SCAN') {
                    scanStatus.textContent = res.message;
                    scanStatus.className = "mt-4 text-center font-semibold text-green-600 h-6";
                } else {
                    alert("Manual Entry Saved Successfully!");
                    toggleModal(false);
                }
            } else {
                const msg = "Error: " + res.message;
                if(type === 'SCAN') {
                    scanStatus.textContent = msg;
                    scanStatus.className = "mt-4 text-center font-bold text-red-600 h-6";
                } else { alert(msg); }
            }
        } catch (e) {
            console.error(e);
            if(type === 'SCAN') scanStatus.textContent = "Connection Error";
        } finally {
            if(type === 'SCAN') { scanInput.value = ""; scanInput.disabled = false; scanInput.focus(); }
        }
    }

    const generateBatchId = () => 'REC_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

    startBtn.addEventListener("click", () => { 
        if (!supplierSelect.value) { alert("Error: Supplier not selected."); return; }
        isScanning = true; 
        currentBatchId = generateBatchId(); 
        sessionCount = 0; 
        if(scanCountEl) scanCountEl.textContent = "0"; 
        startBtn.disabled = true; startBtn.classList.add("opacity-50", "cursor-not-allowed");
        stopBtn.disabled = false; stopBtn.classList.remove("opacity-50", "cursor-not-allowed");
        manualBtn.disabled = false; manualBtn.classList.remove("opacity-50", "cursor-not-allowed");
        jobInputCard.classList.remove("hidden"); 
        scanFormContainer.classList.remove("hidden"); 
        scanInput.disabled = false; scanInput.value = ""; jobInput.value = ""; jobInput.focus(); 
        if(scanStatus) scanStatus.textContent = "Enter Job/Scat No to begin."; 
    });

    stopBtn.addEventListener("click", () => { 
        isScanning = false; 
        scanFormContainer.classList.add("hidden"); 
        jobInputCard.classList.add("hidden"); 
        startBtn.disabled = false; startBtn.classList.remove("opacity-50", "cursor-not-allowed");
        stopBtn.disabled = true; stopBtn.classList.add("opacity-50", "cursor-not-allowed");
        manualBtn.disabled = true; manualBtn.classList.add("opacity-50", "cursor-not-allowed");
        if (sessionCount > 0) {
            if(confirm(`Session ended. ${sessionCount} items scanned. Print labels?`)) { 
                window.open(`receiving_print_batch.php?batch_id=${currentBatchId}&supplier=${supplierSelect.value}`, '_blank', 'width=1000,height=800'); 
            }
        }
        if(scanCountEl) scanCountEl.textContent = "0"; 
        sessionCount = 0;
        loadReceivingHistory(1); 
    });

    scanInput.addEventListener("change", async () => { 
        if (!isScanning) return; 
        if (!jobInput.value.trim()) { 
            const supplier = supplierSelect.value;
            // UPDATED
            const label = (supplier === 'MAZDA' || supplier === 'MASZ') ? "Scat Number" : "Job Number";
            alert(`Enter ${label}.`); 
            scanInput.value = ""; jobInput.focus(); return; 
        } 
        if(scanInput.value.trim()) processScanData(scanInput.value.trim(), 'SCAN'); 
    });

}); 

// Global Functions
function reprintReceivingTag(id, supplier) {
    const url = `receiving_print_single.php?id=${id}&supplier=${supplier}`;
    window.open(url, '_blank', 'width=1000,height=600');
}

function deleteReceivingLog(id, supplier, btn) {
    const pwd = prompt("Enter Admin Password to delete this record:");
    if (pwd === null) return; 
    if (pwd === "") { alert("Password required."); return; }
    if (!confirm("Are you sure? This cannot be undone.")) return;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
    lucide.createIcons();
    btn.disabled = true;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('supplier', supplier);
    formData.append('password', pwd);
    fetch('delete_receiving_log.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) { 
                alert("Deleted successfully."); 
                if(typeof loadReceivingHistory === 'function') loadReceivingHistory(); 
            } else { 
                alert("Error: " + data.message); 
                btn.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i>'; 
                btn.disabled = false; 
                lucide.createIcons(); 
            }
        })
        .catch(err => { 
            console.error(err); 
            alert("Connection Error"); 
            btn.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i>'; 
            btn.disabled = false; 
            lucide.createIcons(); 
        });
}

// ============================================================
// STOCK / FIFO CHECK LOGIC
// ============================================================

let currentStockMode = 'part'; // Default

function openStockModal(mode) {
    currentStockMode = mode;
    const modal = document.getElementById('stockModal');
    const input = document.getElementById('stockSearchInput');
    const tbody = document.getElementById('stockTableBody');
    const header = document.getElementById('stockModalHeader');
    const title = document.getElementById('stockModalTitle');
    const icon = document.getElementById('stockModalIcon');

    // Reset Table
    input.value = '';
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400">Enter search term...</td></tr>`;

    // --- DYNAMIC UI BASED ON MODE ---
    if (mode === 'location') {
        // BLUE THEME
        header.className = "bg-gradient-to-r from-blue-700 to-cyan-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0";
        title.textContent = "Search by Racking Location";
        input.placeholder = "Scan Rack ID (e.g., A-01-02)...";
        icon.setAttribute('data-lucide', 'map-pin');
    } else {
        // PURPLE THEME
        header.className = "bg-gradient-to-r from-purple-700 to-pink-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0";
        title.textContent = "Search Part / ERP / Seq";
        input.placeholder = "Scan Part No, ERP or Seq...";
        icon.setAttribute('data-lucide', 'search');
    }
    
    // Refresh Icon
    if(typeof lucide !== 'undefined') lucide.createIcons();

    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('div').classList.add('scale-100');
        input.focus();
    }, 10);
}

function closeStockModal() {
    const modal = document.getElementById('stockModal');
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

// Search Event Listeners
const btnSearch = document.getElementById('btnStockSearch');
if(btnSearch) btnSearch.addEventListener('click', performStockSearch);

const inputSearch = document.getElementById('stockSearchInput');
if(inputSearch) inputSearch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') performStockSearch();
});

function performStockSearch() {
    const query = document.getElementById('stockSearchInput').value.trim();
    const tbody = document.getElementById('stockTableBody');

    if (!query) { alert("Please enter a search term."); return; }

    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-500"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2"></i> Searching Stock...</td></tr>`;
    if(typeof lucide !== 'undefined') lucide.createIcons();

    // !!! IMPORTANT: Points to get_racking_stock.php to search the RACKS, not the receiving log !!!
    fetch(`get_racking_stock.php?type=${currentStockMode}&query=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                if (resp.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-500">No stock found in racks.</td></tr>`;
                    return;
                }
                
                tbody.innerHTML = resp.data.map(row => {
                    let rowClass = "hover:bg-gray-50 transition-colors border-b border-gray-100";
                    let badge = "";
                    
                    if (row.fifo_status === 'critical') {
                        rowClass = "bg-red-50 hover:bg-red-100 border-b border-red-100";
                        badge = `<span class="ml-2 text-[10px] bg-red-600 text-white px-2 py-0.5 rounded-full font-bold shadow-sm">OLD (${row.days_in_stock}d)</span>`;
                    } else if (row.fifo_status === 'warning') {
                        badge = `<span class="ml-2 text-[10px] bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-bold border border-orange-200">${row.days_in_stock}d</span>`;
                    }

                    let locationDisplay = row.RACKING_LOCATION;
                    let locationClass = "font-bold text-gray-800";
                    if(locationDisplay && locationDisplay.includes(',')) {
                        locationClass = "font-bold text-indigo-700 text-xs break-words max-w-[150px]";
                    }

                    return `
                        <tr class="${rowClass}">
                            <td class="px-4 py-3 align-middle"><div class="${locationClass}">${locationDisplay}</div></td>
                            <td class="px-4 py-3 align-middle"><span class="font-medium text-gray-700 text-sm block">${row.date_fmt}</span>${badge}</td>
                            <td class="px-4 py-3 font-medium text-blue-600 align-middle">${row.PART_NO}</td>
                            <td class="px-4 py-3 font-mono text-gray-600 align-middle text-xs">${row.ERP_CODE}</td>
                            <td class="px-4 py-3 text-gray-500 align-middle text-xs">${row.SEQ_NO}</td>
                            <td class="px-4 py-3 text-center align-middle"><span class="inline-block bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-lg border border-emerald-200">${row.total_qty}</span></td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-red-500">${resp.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-red-500">Connection Error</td></tr>`;
        });
}

// ============================================================
// MASTER LIST LOGIC (Updated with Add/Edit)
// ============================================================

// 1. Open/Close Logic for the LIST Modal
function openMasterModal() {
    const modal = document.getElementById('masterListModal');
    const input = document.getElementById('masterSearchInput');
    input.value = '';
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('div').classList.add('scale-100');
        input.focus();
        performMasterSearch();
    }, 10);
}

function closeMasterModal() {
    const modal = document.getElementById('masterListModal');
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

// 2. Open/Close Logic for the INPUT Modal (Add/Edit)
let currentMasterAction = 'add'; // 'add' or 'edit'

window.openMasterInput = function(action, data = null) {
    const modal = document.getElementById('masterInputModal');
    const title = document.getElementById('masterInputTitle');
    currentMasterAction = action;

    // Reset Fields
    document.getElementById('mi_supplier').value = 'YTEC';
    document.getElementById('mi_seq').value = '';
    document.getElementById('mi_part_no').value = '';
    document.getElementById('mi_erp').value = '';
    document.getElementById('mi_desc').value = '';
    document.getElementById('mi_pack').value = '';
    document.getElementById('mi_original_part_no').value = '';

    if (action === 'edit' && data) {
        title.textContent = "EDIT MASTER DATA";
        document.getElementById('mi_supplier').value = data.supplier;
        document.getElementById('mi_seq').value = data.seq_number;
        document.getElementById('mi_part_no').value = data.part_no;
        document.getElementById('mi_original_part_no').value = data.part_no; // Keep original for DB WHERE clause
        document.getElementById('mi_erp').value = data.erp_code;
        document.getElementById('mi_desc').value = data.stock_desc;
        document.getElementById('mi_pack').value = data.std_packing;
    } else {
        title.textContent = "ADD NEW MASTER DATA";
    }

    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('div').classList.add('scale-100');
        document.getElementById('mi_part_no').focus();
    }, 10);
};

window.closeMasterInput = function() {
    const modal = document.getElementById('masterInputModal');
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
};

// 3. Save Logic
window.saveMasterData = function() {
    const btn = document.getElementById('btnSaveMaster');
    const payload = {
        action: currentMasterAction,
        original_part_no: document.getElementById('mi_original_part_no').value,
        supplier: document.getElementById('mi_supplier').value,
        seq_no: document.getElementById('mi_seq').value,
        part_no: document.getElementById('mi_part_no').value,
        erp_code: document.getElementById('mi_erp').value,
        part_name: document.getElementById('mi_desc').value,
        std_packing: document.getElementById('mi_pack').value
    };

    if(!payload.part_no || !payload.erp_code) {
        alert("Part No and ERP Code are required.");
        return;
    }

    btn.disabled = true;
    btn.textContent = "SAVING...";

    fetch('get_master_list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert(data.message);
            closeMasterInput();
            performMasterSearch(); // Refresh list
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(err => alert("Connection Error"))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = "SAVE DATA";
    });
};

// 4. Search & Render Logic
const btnMasterSearch = document.getElementById('btnMasterSearch');
if(btnMasterSearch) btnMasterSearch.addEventListener('click', performMasterSearch);

const inputMasterSearch = document.getElementById('masterSearchInput');
if(inputMasterSearch) inputMasterSearch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') performMasterSearch();
});

function performMasterSearch() {
    const query = document.getElementById('masterSearchInput').value.trim();
    const tbody = document.getElementById('masterTableBody');

    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-500"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2"></i> Loading Data...</td></tr>`;
    if(typeof lucide !== 'undefined') lucide.createIcons();

    fetch(`get_master_list.php?search=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                if (resp.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-500 italic">No matching records found.</td></tr>`;
                    return;
                }
                
                tbody.innerHTML = resp.data.map(row => {
                    const supplierClass = row.supplier === 'YTEC' ? 'text-amber-600 bg-amber-50' : 'text-blue-600 bg-blue-50';
                    
                    // Safe stringify for the button
                    const rowData = JSON.stringify(row).replace(/"/g, '&quot;');

                    return `
                        <tr class="hover:bg-amber-50/50 transition-colors border-b border-gray-100">
                            
                            <td class="px-4 py-3 align-middle"><span class="px-2 py-1 rounded text-xs font-bold ${supplierClass}">${row.supplier}</span></td>
                            <td class="px-4 py-3 align-middle font-bold text-gray-700">${row.seq_number || '-'}</td>
                            <td class="px-4 py-3 align-middle font-medium text-blue-600">${row.part_no}</td>
                            <td class="px-4 py-3 align-middle font-mono text-gray-600 text-xs font-bold">${row.erp_code}</td>
                            <td class="px-4 py-3 align-middle text-gray-600 text-sm truncate max-w-xs" title="${row.stock_desc}">${row.stock_desc}</td>
                            <td class="px-4 py-3 text-center align-middle font-bold text-emerald-600">${row.std_packing}</td>
                            <td class="px-4 py-3 text-center align-middle">
                                <button onclick="openMasterInput('edit', ${rowData})" class="bg-gray-100 hover:bg-amber-100 text-gray-600 hover:text-amber-600 p-1.5 rounded transition-colors" title="Edit">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
                if(typeof lucide !== 'undefined') lucide.createIcons();
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-red-500 font-bold">Error: ${resp.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-red-500">Connection Error</td></tr>`;
        });
}