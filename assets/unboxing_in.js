document.addEventListener("DOMContentLoaded", () => {
    const $ = (id) => document.getElementById(id);

    // Elements
    const startBtn = $("startScanBtn");
    const stopBtn = $("stopScanBtn");
    const scanContainer = $("scanFormContainer");
    const scanInput = $("scanInput");
    const scanResult = $("scanResult");
    const scanCount = $("scanCount");
    
    // Table Elements
    const tableBody = $("scanHistoryTableBody");
    const searchInput = $("historySearchInput");
    const dateFrom = $("dateFrom");
    const dateTo = $("dateTo");
    const refreshBtn = $("refreshBtn");
    const downloadCsvBtn = $("downloadCsvBtn");

    // Manual Modal Elements
    const manualBtn = $("manualEntryBtn");
    const manualModal = $("manualEntryModal");
    const closeManual = $("closeManualModal");
    const cancelManual = $("cancelManualBtn");
    const manualInput = $("manual_tag_id");
    const manualSubmit = $("submitManualEntry");
    const manualStatus = $("manualStatus");

    let isScanning = false;

    // --- 1. SCAN LOGIC ---
    if(startBtn) {
        startBtn.addEventListener("click", () => {
            isScanning = true;
            scanContainer.classList.remove("hidden");
            startBtn.disabled = true;
            stopBtn.disabled = false;
            scanCount.textContent = "0";
            scanInput.value = "";
            setTimeout(() => scanInput.focus(), 100); 
            updateStatus("Ready to scan", "info");
        });
    }

    if(stopBtn) {
        stopBtn.addEventListener("click", () => {
            isScanning = false;
            scanContainer.classList.add("hidden");
            startBtn.disabled = false;
            stopBtn.disabled = true;
            scanCount.textContent = "0";
            updateStatus("Scan Standby", "info");
        });
    }

    if(scanInput) {
        scanInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                handleScan(scanInput.value.trim());
            }
        });
    }

    function handleScan(qrData) {
        if (!qrData) return;
        scanInput.disabled = true; 
        updateStatus("Processing...", "info");

        const formData = new FormData();
        formData.append("action", "submit_scan");
        formData.append("qr_data", qrData);

        fetch("unboxing_in.api.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let displayId = qrData;
                    if(qrData.includes('|')) displayId = qrData.split('|')[2];

                    updateStatus("Success: " + displayId, "success");
                    addTableRow(data.data);
                    incrementCount();
                    scanInput.value = ""; 
                    
                    if(manualModal && !manualModal.classList.contains("hidden")) {
                        toggleModal(false);
                    }
                } else {
                    updateStatus(data.message, "error");
                    if(manualModal && !manualModal.classList.contains("hidden")) {
                        manualStatus.textContent = data.message;
                        manualStatus.className = "text-center text-sm font-bold mb-4 h-5 text-red-600";
                    }
                }
            })
            .catch(err => {
                console.error(err);
                updateStatus("Connection Error", "error");
            })
            .finally(() => {
                scanInput.disabled = false;
                scanInput.value = ""; 
                if(isScanning) scanInput.focus();
            });
    }

    function updateStatus(msg, type) {
        if(!scanResult) return;
        scanResult.textContent = msg;
        if (type === 'success') scanResult.className = "mt-2 text-center h-8 font-bold flex items-center justify-center rounded-lg bg-green-100 text-green-700 transition-all";
        else if (type === 'error') scanResult.className = "mt-2 text-center h-8 font-bold flex items-center justify-center rounded-lg bg-red-100 text-red-700 transition-all";
        else scanResult.className = "mt-2 text-center h-8 font-semibold text-gray-500 transition-all";
    }

    function incrementCount() {
        if(!scanCount) return;
        let c = parseInt(scanCount.textContent || "0");
        scanCount.textContent = c + 1;
    }

    // --- 2. TABLE & HISTORY ---
    function loadHistory() {
        const params = new URLSearchParams({
            action: 'get_history',
            search: searchInput ? searchInput.value : '',
            date_from: dateFrom ? dateFrom.value : '',
            date_to: dateTo ? dateTo.value : ''
        });

        fetch(`unboxing_in.api.php?${params}`)
            .then(res => res.json())
            .then(resp => {
                if (resp.success) renderTable(resp.data);
            });
    }

    function renderTable(data) {
        if(!tableBody) return;
        if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-10 text-gray-500">No records found.</td></tr>`;
            return;
        }
        tableBody.innerHTML = data.map(row => `
            <tr class="hover:bg-pink-50 transition-colors border-b border-gray-100">
                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${row.scan_time_fmt}</td>
                <td class="px-4 py-3 font-mono text-pink-700 font-bold">${row.ID_CODE}</td>
                <td class="px-4 py-3 text-gray-500">${row.rec_date_fmt}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">${row.PART_NAME}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">${row.PART_NO}</td>
                <td class="px-4 py-3 text-indigo-700 font-mono">${row.ERP_CODE}</td>
                <td class="px-4 py-3">${row.SEQ_NO}</td>
                <td class="px-4 py-3 font-bold text-emerald-600">${row.RACK_OUT}</td>
                <td class="px-4 py-3 font-bold text-gray-700">${row.LOCATION}</td>
                <td class="px-4 py-3 text-center">
                    <button onclick="deleteUnbox(${row.log_id}, this)"
                        class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold">
                        <i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete
                    </button>
                </td>
            </tr>
        `).join('');
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    function addTableRow(row) {
        if(!tableBody) return;
        const empty = tableBody.querySelector("td[colspan='10']");
        if(empty) empty.parentElement.remove();

        const tr = document.createElement('tr');
        tr.className = "animate-pulse bg-pink-100 border-b border-gray-100";
        tr.innerHTML = `
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${row.scan_time_fmt}</td>
            <td class="px-4 py-3 font-mono text-pink-700 font-bold">${row.ID_CODE}</td>
            <td class="px-4 py-3 text-gray-500">${row.rec_date_fmt}</td>
            <td class="px-4 py-3 text-gray-800 font-medium">${row.PART_NAME}</td>
            <td class="px-4 py-3 text-gray-800 font-medium">${row.PART_NO}</td>
            <td class="px-4 py-3 text-indigo-700 font-mono">${row.ERP_CODE}</td>
            <td class="px-4 py-3">${row.SEQ_NO}</td>
            <td class="px-4 py-3 font-bold text-emerald-600">${row.RACK_OUT}</td>
            <td class="px-4 py-3 font-bold text-gray-700">${row.LOCATION}</td>
            <td class="px-4 py-3 text-center">
                <button onclick="deleteUnbox(${row.log_id}, this)"
                    class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold">
                    <i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete
                </button>
            </td>
        `;
        tableBody.prepend(tr);
        if(typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => tr.classList.remove("animate-pulse", "bg-pink-100"), 1000);
    }

    // --- 3. MANUAL ENTRY ---
    function toggleModal(show) {
        if(!manualModal) return;
        if (show) {
            manualModal.classList.remove("hidden", "opacity-0");
            manualInput.value = "";
            manualStatus.textContent = "";
            manualInput.focus();
        } else {
            manualModal.classList.add("hidden", "opacity-0");
            if(isScanning && scanInput) scanInput.focus();
        }
    }

    if(manualBtn) manualBtn.addEventListener("click", () => toggleModal(true));
    if(closeManual) closeManual.addEventListener("click", () => toggleModal(false));
    if(cancelManual) cancelManual.addEventListener("click", () => toggleModal(false));
    if(manualSubmit) manualSubmit.addEventListener("click", () => handleScan(manualInput.value.trim()));

    // --- 4. FILTER LISTENERS ---
    if(dateFrom) dateFrom.addEventListener('change', () => {
        dateTo.min = dateFrom.value;
        loadHistory();
    });
    if(dateTo) dateTo.addEventListener('change', loadHistory);
    if(searchInput) searchInput.addEventListener("input", loadHistory);
    if(refreshBtn) refreshBtn.addEventListener("click", () => {
        searchInput.value = ''; dateFrom.value = ''; dateTo.value = '';
        loadHistory();
    });
    if(downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', () => {
            const params = new URLSearchParams({
                action: 'export_csv',
                search: searchInput.value,
                date_from: dateFrom.value,
                date_to: dateTo.value
            });
            window.location.href = `unboxing_in.api.php?${params}`;
        });
    }

    // Init
    loadHistory();
});

// --- GLOBAL DELETE FUNCTION ---
function deleteUnbox(id, btn) {
    const pwd = prompt("Enter Admin Password:");
    if (pwd === null) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_scan');
    fd.append('id', id);
    fd.append('password', pwd);

    fetch('unboxing_in.api.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                btn.closest('tr').remove();
                alert("Deleted.");
            } else {
                alert(data.message);
            }
        });
}

// ============================================================
// 5. STOCK CHECK / ADVANCE SEARCH (New Logic)
// ============================================================
let currentStockMode = ''; 

function openStockModal(mode) {
    currentStockMode = mode;
    const modal = document.getElementById('stockModal');
    const title = document.getElementById('stockModalTitle');
    const input = document.getElementById('stockSearchInput');
    const header = document.getElementById('stockModalHeader');
    const tbody = document.getElementById('stockTableBody');
    
    // Customize UI based on mode (Pink/Rose Theme)
    if (mode === 'location') {
        title.textContent = "Search by Racking Location";
        input.placeholder = "Scan or type Rack ID (e.g. A-01-02)...";
        header.className = "bg-gradient-to-r from-rose-700 to-pink-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0";
    } else {
        title.textContent = "Search by ERP / Seq / Part No";
        input.placeholder = "Scan ERP, Seq No or Part No...";
        header.className = "bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0";
    }

    // Reset Table
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400">Enter search term to view rack stock.</td></tr>`;
    input.value = '';

    // Show Modal
    modal.classList.remove('hidden', 'opacity-0');
    modal.querySelector('div').classList.remove('scale-95');
    modal.querySelector('div').classList.add('scale-100');
    
    setTimeout(() => input.focus(), 100); 
}

function closeStockModal() {
    const modal = document.getElementById('stockModal');
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

// Search Logic
const btnStockSearch = document.getElementById('btnStockSearch');
if(btnStockSearch) btnStockSearch.addEventListener('click', performStockSearch);

const stockSearchInput = document.getElementById('stockSearchInput');
if(stockSearchInput) stockSearchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') performStockSearch();
});

function performStockSearch() {
    const query = document.getElementById('stockSearchInput').value.trim();
    const tbody = document.getElementById('stockTableBody');

    if (!query) { alert("Please enter a search term."); return; }

    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-500"><i class="fas fa-spinner fa-spin"></i> Searching Racking...</td></tr>`;

    // CALL THE NEW API ENDPOINT
    fetch(`unboxing_in.api.php?action=search_racking_stock&type=${currentStockMode}&query=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                if (resp.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-500">No stock found in racks.</td></tr>`;
                    return;
                }

                let html = '';
                resp.data.forEach(row => {
                    let badge = '';
                    if (row.fifo_status === 'critical') {
                        badge = `<span class="ml-2 text-[10px] bg-red-600 text-white px-2 py-0.5 rounded-full font-bold shadow-sm">OLD (${row.days_in_stock}d)</span>`;
                    }

                    html += `
                        <tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                            <td class="px-4 py-3 font-bold text-gray-800">${row.RACKING_LOCATION}</td>
                            <td class="px-4 py-3">
                                <span class="text-gray-700 text-sm">${row.date_fmt}</span>
                                ${badge}
                            </td>
                            <td class="px-4 py-3 font-medium text-blue-600">${row.PART_NO}</td>
                            <td class="px-4 py-3 font-mono text-gray-600 text-xs">${row.ERP_CODE}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">${row.SEQ_NO}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-lg border border-emerald-200">${row.total_qty}</span>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
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