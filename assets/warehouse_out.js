/**
 * Professional JavaScript for warehouse_out.php
 * Version 10.4: Fixed History Table Columns
 */
document.addEventListener("DOMContentLoaded", () => {
    
    const API_URL = 'warehouse_out_api.php?v=10.4';

    // --- State ---
    let currentJob = { type: null, model: null, variant: null, trip: null, lot_no: null, part: null, };
    let isJobActive = false;
    let mscAbortController = null;
    let hasTripCompleted = false; // State tracker
    
    // ACCUMULATOR STATE
    let scanDataCache = {
        pallet_qr: null,
        tt_qrs: [], 
        total_scanned_qty: 0,
        required_qty: 0,
        pallet_part_no: null,
        pallet_erp: null
    };

    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => document.querySelectorAll(selector);

    const filters = {
        type: $("#job_type"),
        model: $("#job_model"),
        variant: $("#job_variant"),
        trip: $("#job_trip"),
        lot_no: $("#job_lot_no"),
        msc: $("#job_msc_code"),
    };

    const buttons = {
        start: $("#startScanBtn"),
        stop: $("#stopScanBtn"),
        manualTT: $("#manualTTBtn"),
    };
    
    const bypassToggle = $("#bypassFifoToggle");

    const scanInputs = {
        pallet: $("#scan_1_pallet"),
        tt: $("#scan_2_tt"),
        mazda: $("#scan_3_mazda"),
        statusPallet: $("#status_1"),
        statusTT: $("#status_2"),
        statusMazda: $("#status_3"),
        scanResult: $("#scanResult"),
        ttList: $("#tt_list_container"),
        qtyDisplay: $("#qty_progress_display")
    };

    const manualModal = {
        el: $("#manualEntryModal"),
        inputId: $("#manual_ticket_id"),
        inputErp: $("#manual_erp"),
        status: $("#manualStatus"),
        cancel: $("#cancelManualBtn"),
        close: $("#closeManualModal"),
        submit: $("#submitManualBtn"),
    };

    const tableCard = {
        tripHeadRow: $("#masterTripTableHeadRow"),
        tripBody: $("#masterTripTableBody"),
        toggleLogBtn: $("#toggleScanLogBtn"),
        logContainer: $("#scanLogContainer"),
        tripActionsContainer: $("#tripActionsContainer"),
        historyBody: $("#scanLogTableBody"),
        historySearch: $("#historySearchInput"),
        historyDate: $("#historyDateInput"),
        historyModel: $("#historyModelFilter"),
        historyRefresh: $("#refreshHistoryBtn"),
        historyPagination: $("#historyPagination"),
        exportCsv: $("#exportCsvBtn")
    };

    let tsType, tsModel, tsVariant, tsTrip, tsMSC;

    // --- Helper Functions ---
    const showScanResult = (message, isError = false) => {
        const el = scanInputs.scanResult;
        el.textContent = message;
        el.classList.remove('bg-red-500', 'bg-gray-400', 'bg-emerald-600');
        if (isError) {
            el.classList.add('bg-red-500', 'text-white');
        } else {
            el.classList.add('bg-emerald-600', 'text-white');
        }
    };
    
    const setStatusIcon = (step, status) => {
        const icons = {
            pending: `<i data-lucide="loader-2" class="w-6 h-6 text-yellow-500 animate-spin"></i>`,
            success: `<i data-lucide="check-circle" class="w-6 h-6 text-green-500"></i>`,
            error: `<i data-lucide="x-circle" class="w-6 h-6 text-red-500"></i>`,
        };
        const el = $(`#status_${step}`);
        if (el) {
            el.innerHTML = icons[status] || '';
            lucide.createIcons();
        }
    };
    
    const parseAndFormatMazdaQR = (qr) => {
        const rawQR = qr.trim();
        if (rawQR.startsWith('#P') && /\s+/.test(rawQR)) {
            const parts = rawQR.split(/\s+/).filter(Boolean); 
            if (parts.length >= 2) {
                const mainPart = parts[0].substring(2);
                const rawMazdaID = parts[1].trim();    
                const mazdaID = rawMazdaID.replace(/^[0-9]+/, ''); 
                if (mainPart.length >= 19 && mazdaID.length > 0) {
                    const msc = mainPart.substring(0, 7);
                    const lot = mainPart.substring(7, 10);
                    const part = mainPart.substring(10, 19);
                    const formattedPart = part.length === 9 ? part.substring(0, 4) + '-' + part.substring(4) : part;
                    return `${msc}|${lot}|${formattedPart}|${mazdaID}`;
                }
            }
        }
        if (rawQR.startsWith('#P') && rawQR.length >= 21) {
            const msc = rawQR.substring(2, 9);
            const lot = rawQR.substring(9, 12);
            const part = rawQR.substring(12, 21);
            const formattedPart = part.length === 9 ? part.substring(0, 4) + '-' + part.substring(4) : part;
            return `${msc}|${lot}|${formattedPart}|N/A`; 
        }
        const splitParts = rawQR.split('|');
        if (splitParts.length === 3) return `${rawQR}|N/A`; 
        if (splitParts.length === 4) return rawQR; 
        return null;
    };

    // --- Trip Plan Table Logic ---
    const renderTripStatusBadge = (planQty, actualQty) => {
        let statusText = 'Pending';
        let statusClass = 'bg-gray-100 text-gray-800';
        if (planQty <= 0) { statusText = 'Pending'; statusClass = 'bg-gray-100 text-gray-500'; }
        else if (actualQty >= planQty) { statusText = 'Complete'; statusClass = 'bg-green-100 text-green-800'; }
        else if (actualQty > 0) { statusText = 'Ongoing'; statusClass = 'bg-blue-100 text-blue-800'; }
        return `<td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-semibold ${statusClass}">${statusText}</span></td>`;
    };

    function renderOverallProgressStepper(row) {
        let html = '<div class="flex gap-1">';
        for (let i = 1; i <= 6; i++) {
            const plan = parseInt(row[`TRIP_${i}`] || 0);
            const actual = parseInt(row[`ACTUAL_TRIP_${i}`] || 0);
            let cls = "w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold ";
            if (plan === 0) cls += "bg-gray-100 text-gray-300";
            else if (actual >= plan) cls += "bg-green-500 text-white";
            else if (actual > 0) cls += "bg-blue-500 text-white animate-pulse";
            else cls += "bg-gray-300 text-gray-600";
            
            html += `<span class="${cls}" title="Trip ${i}">${i}</span>`;
        }
        html += '</div>';
        return `<td class="px-4 py-3">${html}</td>`;
    }

    window.fetchTripPlan = async () => {
        const type = tsType.getValue();
        const model = tsModel.getValue();
        const variant = tsVariant.getValue();
        const selectedTrip = tsTrip.getValue();
        
        tableCard.tripBody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-gray-500">Loading...</td></tr>`;
        checkTripCompletionStatus(); 

        if (!selectedTrip) {
            tableCard.tripBody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-gray-400 italic">Select filters and a Trip to see parts.</td></tr>`;
            return;
        }

        try {
            const formData = new FormData();
            formData.append('type', type);
            formData.append('model', model);
            formData.append('variant', variant);
            formData.append('trip', selectedTrip);

            const response = await fetch(`${API_URL}&action=get_trip_plan`, { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success && data.parts.length > 0) {
                let html = '';
                data.parts.forEach((row, index) => {
                    const bgClass = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    const planQty = parseInt(row[selectedTrip] || 0);
                    const actualQty = parseInt(row[`ACTUAL_${selectedTrip}`] || 0);
                    const remainingQty = planQty - actualQty;
                    let remainingClass = remainingQty > 0 ? 'text-red-600 font-bold' : 'text-green-600 font-bold';
                    
                    html += `<tr class="${bgClass} hover:bg-indigo-50" 
                                 data-part-no="${row.PART_NO}" 
                                 data-master-id="${row.id}"
                                 data-plan="${planQty}"
                                 data-actual="${actualQty}">`;
                    html += `<td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">${row.MODEL}</td>`;
                    html += `<td class="px-4 py-3 whitespace-nowrap text-gray-600">${row.TYPE}</td>`;
                    html += `<td class="px-4 py-3 whitespace-nowrap font-semibold text-blue-700">${row.PART_NO}</td>`;
                    html += `<td class="px-4 py-3 whitespace-nowrap text-gray-600">${row.PART_DESCRIPTION}</td>`;
                    html += `<td class="px-4 py-3 text-center font-bold text-gray-800 plan-qty">${planQty}</td>`;
                    html += `<td class="px-4 py-3 text-center font-bold text-blue-600 actual-qty">${actualQty}</td>`;
                    html += `<td class="px-4 py-3 text-center ${remainingClass} remaining-qty">${remainingQty}</td>`;
                    html += renderTripStatusBadge(planQty, actualQty); 
                    html += renderOverallProgressStepper(row);       
                    html += `</tr>`;
                });
                tableCard.tripBody.innerHTML = html;
                lucide.createIcons();
            } else {
                tableCard.tripBody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-gray-500">No parts found for this selection.</td></tr>`;
            }
            checkTripCompletionStatus();
        } catch (error) {
            tableCard.tripBody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-red-500">Error loading data.</td></tr>`;
        }
    };

    const updateTableRow = (updatedRow) => {
        if (!updatedRow || !updatedRow.id) return;
        const selectedTrip = tsTrip.getValue();
        if (!selectedTrip) { fetchTripPlan(); return; }
        const rowElement = tableCard.tripBody.querySelector(`tr[data-master-id="${updatedRow.id}"]`);
        if (!rowElement) { fetchTripPlan(); return; }
        
        const planQty = parseInt(updatedRow[selectedTrip] || 0);
        const actualQty = parseInt(updatedRow[`ACTUAL_${selectedTrip}`] || 0);
        const remainingQty = planQty - actualQty;
        let remainingClass = remainingQty > 0 ? 'text-red-600 font-bold' : 'text-green-600 font-bold';
        
        rowElement.dataset.plan = planQty;
        rowElement.dataset.actual = actualQty;
        
        const cells = rowElement.querySelectorAll('td');
        if (cells.length === 9) { 
            cells[4].outerHTML = `<td class="px-4 py-3 text-center font-bold text-gray-800 plan-qty">${planQty}</td>`;
            cells[5].outerHTML = `<td class="px-4 py-3 text-center font-bold text-blue-600 actual-qty">${actualQty}</td>`;
            cells[6].outerHTML = `<td class="px-4 py-3 text-center ${remainingClass} remaining-qty">${remainingQty}</td>`;
            cells[7].outerHTML = renderTripStatusBadge(planQty, actualQty);
            cells[8].outerHTML = renderOverallProgressStepper(updatedRow);

            rowElement.classList.add('bg-indigo-100');
            setTimeout(() => { rowElement.classList.remove('bg-indigo-100'); }, 1500);
            lucide.createIcons(); 
            checkTripCompletionStatus();
        } else {
             fetchTripPlan(); 
        }
    };

    const checkTripCompletionStatus = () => {
    const rows = tableCard.tripBody.querySelectorAll('tr');
    
    // Safety check for empty or loading table
    if (rows.length === 0 || (rows.length === 1 && rows[0].querySelector('td[colspan="9"]'))) {
        tableCard.tripActionsContainer.innerHTML = ''; 
        hasTripCompleted = false; // Reset state
        return;
    }

    let isTripComplete = true;
    let totalPlan = 0;

    for (const row of rows) {
        if (row.querySelector('td[colspan="9"]')) { isTripComplete = false; break; }
        
        const plan = parseInt(row.dataset.plan || 0);
        const actual = parseInt(row.dataset.actual || 0);
        
        if (plan > 0) {
            totalPlan += plan;
            if (actual < plan) { isTripComplete = false; }
        }
    }

    // === AUTO PRINT LOGIC START ===
    if (isTripComplete && totalPlan > 0) {
        // Render the buttons
        tableCard.tripActionsContainer.innerHTML = `
            <div class="flex items-center gap-2 animate-bounce-in">
                <span class="text-xs font-bold text-green-600 uppercase mr-2 bg-green-100 px-2 py-1 rounded">
                    Trip Complete
                </span>
                <button id="printTripTicketBtn" 
                    class="bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold text-sm px-4 py-2 rounded-lg shadow-md flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> 
                    <span>PRINT TAG</span>
                </button>
                 <button id="resetEntireTripBtn" 
                        class="bg-white border-2 border-red-100 text-red-500 hover:bg-red-50 font-semibold text-sm px-3 py-2 rounded-lg shadow-sm" 
                        title="Reset Trip">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </button>
            </div>
        `;
        lucide.createIcons();

        // CHECK: Is this the *moment* it became complete? And is the user working?
        if (!hasTripCompleted && isJobActive) {
            console.log("Trip just completed! Auto-printing...");
            handlePrintTripTicket(true); // Pass true for auto-print
        }
        
        hasTripCompleted = true; // Mark as handled so it doesn't print again on refresh
    } else {
        tableCard.tripActionsContainer.innerHTML = '';
        hasTripCompleted = false; // Reset if they delete a scan and go back
    }
    // === AUTO PRINT LOGIC END ===
};


    const resetScanInterface = () => {
        scanInputs.pallet.value = '';
        scanInputs.tt.value = '';
        scanInputs.mazda.value = '';
        scanInputs.ttList.innerHTML = '';
        scanInputs.qtyDisplay.textContent = '';
        
        scanInputs.pallet.disabled = !isJobActive;
        scanInputs.tt.disabled = true;
        scanInputs.mazda.disabled = true;
        buttons.manualTT.disabled = true; 
        
        setStatusIcon(1, ''); setStatusIcon(2, ''); setStatusIcon(3, '');
        
        const el = scanInputs.scanResult;
        el.textContent = isJobActive ? 'Ready to Scan Pallet' : 'Job Stopped';
        el.className = "text-white bg-gray-400 px-6 py-3 text-center font-semibold text-sm";
        
        scanDataCache = { pallet_qr: null, tt_qrs: [], total_scanned_qty: 0, required_qty: 0, pallet_part_no: null, pallet_erp: null };
        
        $$('.scan-step [class*="ring-"]').forEach(el => el.classList.remove('ring-2', 'ring-indigo-300'));
        if (isJobActive) {
            scanInputs.pallet.focus();
            scanInputs.pallet.parentElement.classList.add('ring-2', 'ring-indigo-300');
        }
    };

    const checkFormValidity = () => {
        const isValid = tsType.getValue() && tsModel.getValue() && tsVariant.getValue() && tsTrip.getValue() && tsMSC.getValue() && filters.lot_no.value.trim() !== '';
        buttons.start.disabled = !isValid || isJobActive;
    };

    const startJob = () => {
        if (!tsTrip.getValue()) return alert("Select a Trip.");
        isJobActive = true;
        currentJob = {
            type: tsType.getValue(), model: tsModel.getValue(), variant: tsVariant.getValue(),
            trip: tsTrip.getValue(), lot_no: filters.lot_no.value.trim(), part: tsMSC.getValue(), 
        };
        [tsType, tsModel, tsVariant, tsTrip, tsMSC].forEach(ts => ts.disable());
        filters.lot_no.disabled = true;
        buttons.start.disabled = true;
        buttons.stop.disabled = false;
        bypassToggle.disabled = false; 
        resetScanInterface();
    };

    const stopJob = () => {
        isJobActive = false;
        [tsType, tsModel, tsVariant, tsTrip, tsMSC].forEach(ts => ts.enable());
        filters.lot_no.disabled = false;
        buttons.start.disabled = false;
        buttons.stop.disabled = true;
        bypassToggle.disabled = true; 
        bypassToggle.checked = false; 
        checkFormValidity();
        resetScanInterface();
    };

    const submitScan = async (scanData) => {
        setStatusIcon(3, 'pending');
        try {
            const formData = new FormData();
            formData.append('job', JSON.stringify(currentJob));
            formData.append('scan', JSON.stringify(scanData));
            formData.append('bypass_fifo', bypassToggle.checked ? '1' : '0');

            const response = await fetch(`${API_URL}&action=submit_scan`, { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                setStatusIcon(3, 'success');
                showScanResult(data.message, false);
                if (data.updatedRow) updateTableRow(data.updatedRow);
                fetchScanLog(); 
                setTimeout(resetScanInterface, 1500);
            } else {
                setStatusIcon(3, 'error');
                showScanResult(data.message, true);
                setTimeout(resetScanInterface, 2500);
            }
        } catch (error) {
            console.error("Submit Scan Error:", error); 
            showScanResult('Scan failed. Check console.', true); 
            setTimeout(resetScanInterface, 2000);
        }
    };

    const validateTicketOnServer = async (ticketQR) => {
        const parts = ticketQR.split('|');
        if (parts.length < 3) return { success: false, message: "Invalid QR format." };
        
        const unique_no = parts[1];
        const erp_code = parts[2];

        try {
            const formData = new FormData();
            formData.append('unique_no', unique_no);
            formData.append('erp_code', erp_code);
            formData.append('job', JSON.stringify(currentJob));
            formData.append('bypass_fifo', bypassToggle.checked ? '1' : '0');

            const res = await fetch(`${API_URL}&action=validate_ticket`, { method: 'POST', body: formData });
            return await res.json();
        } catch (e) {
            return { success: false, message: "Server Connection Error" };
        }
    };
    
    const checkPalletReqFromServer = async (palletQR) => {
        try {
            const formData = new FormData();
            formData.append('pallet_qr', palletQR);
            formData.append('job', JSON.stringify(currentJob));
            
            const res = await fetch(`${API_URL}&action=check_pallet_req`, { method: 'POST', body: formData });
            return await res.json();
        } catch (e) {
            return { success: false, message: "Connection Error" };
        }
    };

    const processValidTicket = (qr) => {
        const parts = qr.split('|');
        const qty = parseInt(parts[4]) || 1;
        const tt_id = parts[1];

        if (scanDataCache.tt_qrs.includes(qr)) {
            showScanResult("Ticket already scanned in this batch!", true);
            scanInputs.tt.value = '';
            return;
        }

        scanDataCache.tt_qrs.push(qr);
        scanDataCache.total_scanned_qty += qty;

        const div = document.createElement('div');
        div.className = "text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded border border-indigo-100 flex justify-between";
        div.innerHTML = `<span>#${tt_id}</span> <strong>Qty: ${qty}</strong>`;
        scanInputs.ttList.appendChild(div);

        scanInputs.qtyDisplay.textContent = `Scanned: ${scanDataCache.total_scanned_qty} / ${scanDataCache.required_qty}`;

        if (scanDataCache.total_scanned_qty >= scanDataCache.required_qty) {
            setStatusIcon(2, 'success');
            scanInputs.tt.disabled = true;
            buttons.manualTT.disabled = true;
            scanInputs.tt.parentElement.classList.remove('ring-2');
            scanInputs.mazda.disabled = false;
            scanInputs.mazda.parentElement.classList.add('ring-2', 'ring-indigo-300');
            scanInputs.mazda.focus();
            showScanResult("Quantity Met. Scan Mazda Label.", false);
        } else {
             scanInputs.tt.value = '';
             scanInputs.tt.focus();
             showScanResult(`Validated (Qty: ${qty}). Need more.`, false);
        }
    };

    // --- Event Listeners ---
    scanInputs.pallet.addEventListener('change', async () => {
        const qr = scanInputs.pallet.value.trim();
        if(!qr || !isJobActive) return;
        const parts = qr.split('|');
        if (parts.length !== 2) { showScanResult("Invalid Pallet QR", true); scanInputs.pallet.value = ''; return; }
        
        setStatusIcon(1, 'pending');
        scanInputs.pallet.disabled = true;
        
        const check = await checkPalletReqFromServer(qr);
        if (!check.success) {
             showScanResult(check.message, true);
             setStatusIcon(1, 'error');
             scanInputs.pallet.disabled = false;
             scanInputs.pallet.value = '';
             scanInputs.pallet.focus();
             return;
        }

        scanDataCache.pallet_qr = qr;
        scanDataCache.pallet_part_no = parts[0];
        scanDataCache.pallet_erp = parts[1];
        scanDataCache.required_qty = parseInt(check.required_qty);
        
        setStatusIcon(1, 'success');
        scanInputs.pallet.parentElement.classList.remove('ring-2');
        
        scanInputs.tt.disabled = false;
        buttons.manualTT.disabled = false; 
        scanInputs.tt.parentElement.classList.add('ring-2', 'ring-indigo-300');
        scanInputs.tt.focus();
        
        scanInputs.qtyDisplay.textContent = `Scanned: 0 / ${scanDataCache.required_qty}`;
        showScanResult(`Pallet OK. Need Qty: ${scanDataCache.required_qty}`);
    });

    scanInputs.tt.addEventListener('change', async () => {
        const qr = scanInputs.tt.value.trim();
        if(!qr || !isJobActive) return;
        
        const parts = qr.split('|');
        if (parts.length < 3) { showScanResult("Invalid Ticket QR", true); scanInputs.tt.value = ''; return; }
        if (parts[2] !== scanDataCache.pallet_erp) { showScanResult(`ERP Mismatch!`, true); scanInputs.tt.value = ''; return; }
        
        if (scanDataCache.total_scanned_qty >= scanDataCache.required_qty) {
             showScanResult("Quantity already met.", true); 
             return;
        }

        scanInputs.tt.disabled = true;
        buttons.manualTT.disabled = true;
        setStatusIcon(2, 'pending');
        showScanResult("Validating...", false);

        const validation = await validateTicketOnServer(qr);

        if (validation.success) {
            scanInputs.tt.disabled = false;
            buttons.manualTT.disabled = false;
            scanInputs.tt.value = '';
            processValidTicket(qr);
        } else {
            setStatusIcon(2, 'error');
            showScanResult("STOP: " + validation.message, true); 
            scanInputs.tt.value = ''; 
            scanInputs.tt.disabled = false; 
            buttons.manualTT.disabled = false;
            scanInputs.tt.focus();
        }
    });

    scanInputs.mazda.addEventListener('change', () => {
        const qr = scanInputs.mazda.value.trim();
        if(!qr || !isJobActive) return;
        const formatted = parseAndFormatMazdaQR(qr);
        if (!formatted) { showScanResult("Invalid Mazda QR", true); scanInputs.mazda.value = ''; return; }
        
        scanDataCache.mazda_qr = formatted;
        scanInputs.mazda.value = formatted; 
        scanInputs.mazda.disabled = true;
        scanInputs.mazda.parentElement.classList.remove('ring-2');
        
        submitScan({
            scan_pallet: scanDataCache.pallet_qr,
            scan_tt: scanDataCache.tt_qrs,
            scan_mazda: scanDataCache.mazda_qr
        });
    });

    // --- Manual Modal ---
    const toggleManualModal = (show) => {
        if (show) {
            if (!scanDataCache.pallet_qr) {
                alert("You MUST scan the Pallet QR (Step 1) first.");
                return;
            }
            manualModal.el.classList.remove('invisible', 'opacity-0');
            manualModal.el.querySelector('div').classList.remove('scale-95');
            manualModal.inputId.focus();
            manualModal.inputId.value = '';
            manualModal.inputErp.value = '';
            manualModal.status.textContent = '';
        } else {
            manualModal.el.classList.add('invisible', 'opacity-0');
            manualModal.el.querySelector('div').classList.add('scale-95');
        }
    };

    manualModal.submit.addEventListener("click", async () => {
        const id = manualModal.inputId.value.trim();
        const erp = manualModal.inputErp.value.trim();
        if (!id || !erp) {
            manualModal.status.textContent = "Both fields required.";
            manualModal.status.className = "text-red-500 text-center text-sm font-semibold";
            return;
        }
        manualModal.submit.disabled = true;
        manualModal.status.textContent = "Fetching details...";
        manualModal.status.className = "text-blue-500 text-center text-sm font-semibold";

        try {
            const res = await fetch("fetch_ticket_details.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ unique_no: id, erp_code_fg: erp })
            });
            const data = await res.json();

            if (!data.success) {
                manualModal.status.textContent = data.message;
                manualModal.status.className = "text-red-500 text-center text-sm font-semibold";
                manualModal.submit.disabled = false;
                return;
            }

            if (data.data.erp_code_FG !== scanDataCache.pallet_erp) {
                 manualModal.status.textContent = `ERP Mismatch! Pallet: ${scanDataCache.pallet_erp}`;
                 manualModal.status.className = "text-red-600 font-bold text-center text-sm";
                 manualModal.submit.disabled = false;
                 return;
            }
            
            const constructedQR = `${data.data.prod_date}|${data.data.unique_no}|${data.data.erp_code_FG}|${data.data.released_by_ids}|${data.data.quantity}`;
            
            manualModal.status.textContent = "Checking Process Flow...";
            const validation = await validateTicketOnServer(constructedQR);

            if (validation.success) {
                processValidTicket(constructedQR);
                toggleManualModal(false);
            } else {
                manualModal.status.textContent = validation.message;
                manualModal.status.className = "text-red-500 text-center text-sm font-semibold";
            }
        } catch(e) {
             manualModal.status.textContent = "Connection Error";
        }
        manualModal.submit.disabled = false;
    });

    buttons.manualTT.addEventListener("click", () => toggleManualModal(true));
    manualModal.cancel.addEventListener("click", () => toggleManualModal(false));
    manualModal.close.addEventListener("click", () => toggleManualModal(false));

    // --- History Table Logic ---
    let allLogRows = []; 
    let currentLogPage = 1;
    const logPerPage = 100;

    const fetchScanLog = async () => {
        try {
            const response = await fetch(`${API_URL}&action=get_scan_log`, { method: 'POST' });
            const data = await response.json();
            if (data.success && data.logs) {
                buildAndRenderHistoryTable(data.logs);
            }
        } catch (e) { console.error("Failed to fetch scan log", e); }
    };

    const buildAndRenderHistoryTable = (logs) => {
        allLogRows = []; 
        if (logs.length === 0) {
            tableCard.historyBody.innerHTML = `<tr><td colspan="16" class="p-6 text-center text-gray-500">No scans recorded yet.</td></tr>`;
            filterHistoryTable();
            return;
        }

        logs.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-indigo-50 transition-colors";
            tr.dataset.logId = row.log_id;
            
            const released_by = row.released_by || '-';
            const qty = row.quantity || 1;
            const prod_date = row.prod_date_formatted || '-';
            const tt_id = row.tt_id || '-';

            // FIXED: Corrected column order to match PHP Header
            tr.innerHTML = `
                <td class="px-4 py-3 text-gray-600">${row.scan_timestamp_formatted || '-'}</td>
                <td class="px-4 py-3 font-mono text-blue-600 font-bold">${tt_id}</td>
                <td class="px-4 py-3 text-gray-500">${prod_date}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">${row.part_name || '-'}</td>
                <td class="px-4 py-3 font-semibold">${row.part_no_fg || '-'}</td>
                <td class="px-4 py-3 font-mono text-indigo-700">${row.erp_code_fg || '-'}</td>
                <td class="px-4 py-3">${row.model || '-'}</td>
                <td class="px-4 py-3">${row.type || '-'}</td>
                <td class="px-4 py-3 text-gray-500">${row.prod_area || '-'}</td>
                <td class="px-4 py-3 text-center font-bold text-emerald-600">${qty}</td>
                <td class="px-4 py-3 text-gray-600">${released_by}</td>
                <td class="px-4 py-3 font-mono text-purple-700">${row.mazda_id || '-'}</td>
                <td class="px-4 py-3">${row.msc_code || '-'}</td>
                <td class="px-4 py-3">${row.trip || '-'}</td>
                <td class="px-4 py-3">${row.lot_no || '-'}</td>
                <td class="px-4 py-3 text-center">
                    <button onclick="deleteWarehouseOutScan(${row.log_id}, this)" class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition-colors" title="Delete Record">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>
            `;
            allLogRows.push(tr);
        });

        currentLogPage = 1;
        filterHistoryTable();
    };
    
    const filterHistoryTable = () => {
        const term = tableCard.historySearch.value.toLowerCase();
        const model = tableCard.historyModel.value;
        const date = tableCard.historyDate.value; 

        let filtered;
        if (allLogRows.length === 0) {
            filtered = [];
        } else {
             filtered = allLogRows.filter(row => {
                const text = row.innerText.toLowerCase();
                const rowModel = row.children[6].innerText; 
                const dateText = row.children[0].innerText.trim(); 
                let rowDate = "";
                if(dateText.length >= 10) {
                    const parts = dateText.split(' ')[0].split('/'); 
                    if(parts.length === 3) rowDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
                const matchSearch = text.includes(term);
                const matchModel = model === "" || rowModel === model;
                const matchDate = date === "" || rowDate === date;
                return matchSearch && matchModel && matchDate;
            });
        }

        const totalPages = Math.ceil(filtered.length / logPerPage);
        if (currentLogPage > totalPages) currentLogPage = 1;
        
        const start = (currentLogPage - 1) * logPerPage;
        const visible = filtered.slice(start, start + logPerPage);

        tableCard.historyBody.innerHTML = "";
        if (allLogRows.length === 0) {
             tableCard.historyBody.innerHTML = `<tr><td colspan="16" class="p-6 text-center text-gray-500">No scans recorded yet.</td></tr>`;
        } else if (visible.length === 0) {
             tableCard.historyBody.innerHTML = `<tr><td colspan="16" class="p-6 text-center text-gray-500">No scans match your filters.</td></tr>`;
        } else {
            visible.forEach(row => tableCard.historyBody.appendChild(row));
        }
        lucide.createIcons();
        renderHistoryPagination(totalPages);
    };

    const renderHistoryPagination = (pages) => {
        tableCard.historyPagination.innerHTML = '';
        if (pages <= 1) return;
        for(let i=1; i<=pages; i++) {
             const btn = document.createElement('button');
             btn.innerText = i;
             btn.className = `px-3 py-1 border rounded mx-1 ${i === currentLogPage ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100'}`;
             btn.onclick = () => { currentLogPage = i; filterHistoryTable(); };
             tableCard.historyPagination.appendChild(btn);
        }
    };

    tableCard.historySearch.addEventListener('input', filterHistoryTable);
    tableCard.historyModel.addEventListener('change', filterHistoryTable);
    tableCard.historyDate.addEventListener('change', filterHistoryTable);
    tableCard.historyRefresh.addEventListener('click', () => {
        tableCard.historySearch.value = '';
        tableCard.historyModel.value = '';
        tableCard.historyDate.value = '';
        fetchScanLog();
    });
    
    tableCard.toggleLogBtn.addEventListener('click', () => {
        tableCard.logContainer.classList.toggle('hidden');
        tableCard.toggleLogBtn.querySelector('span').innerText = tableCard.logContainer.classList.contains('hidden') ? 'Show Log' : 'Hide Log';
    });

    allLogRows = Array.from(tableCard.historyBody.querySelectorAll('tr[data-log-id]'));
    filterHistoryTable(); 
    buttons.stop.disabled = true;
    bypassToggle.disabled = true; 
    
    const tableChangeHandler = () => { checkFormValidity(); fetchTripPlan(); };
    const mscDependencyChangeHandler = () => { 
        tsMSC.clear(); tsMSC.clearOptions(); tsMSC.enable(); tsMSC.load();
        checkFormValidity(); fetchTripPlan();
    };

    tsType = new TomSelect(filters.type, { searchable: false, onChange: tableChangeHandler });
    tsModel = new TomSelect(filters.model, { searchable: false, onChange: mscDependencyChangeHandler });
    tsVariant = new TomSelect(filters.variant, { searchable: false, onChange: mscDependencyChangeHandler });
    tsTrip = new TomSelect(filters.trip, { searchable: false, onChange: tableChangeHandler });
    
    tsMSC = new TomSelect(filters.msc, {
        plugins: ['dropdown_input'],
        maxItems: 1, valueField: 'value', labelField: 'text', placeholder: "Select MSC...",
        onChange: checkFormValidity,
        load: async (query, callback) => {
            const model = tsModel.getValue(); const variant = tsVariant.getValue();
            if (!model || !variant) { return callback([]); }
            if (mscAbortController) { mscAbortController.abort(); }
            mscAbortController = new AbortController();
            try {
                const formData = new FormData();
                formData.append('model', model); formData.append('variant', variant);
                const response = await fetch(`${API_URL}&action=get_msc`, { method: 'POST', body: formData, signal: mscAbortController.signal });
                const data = await response.json();
                if (data.success && data.parts.length > 0) {
                    callback(data.parts.map(code => ({ value: code, text: code })));
                } else { callback([]); }
            } catch (error) { callback([]); }
        },
        render: { no_results: (data, escape) => `<div class="no-results p-2 text-gray-500">Please select Model & Variant.</div>` },
    });
    tsMSC.disable();

    // Updated function to support Auto-Print
const handlePrintTripTicket = (isAuto = false) => {
    const job = {
        type: tsType.getValue(), model: tsModel.getValue(),
        variant: tsVariant.getValue(), trip: tsTrip.getValue(),
        lot_no: filters.lot_no.value.trim(),
    };

    if (!job.type || !job.model || !job.variant || !job.trip || !job.lot_no) {
        if(!isAuto) alert("Error: Job data is incomplete."); // Only alert if user clicked manually
        return;
    }

    // Add &autoprint=1 if isAuto is true
    let url = `print_trip_ticket.php?type=${job.type}&model=${job.model}&variant=${job.variant}&trip=${job.trip}&lot=${job.lot_no}`;
    if (isAuto) {
        url += '&autoprint=1';
    }

    // Open popup
    window.open(url, '_blank', 'width=380,height=500,resizable=yes,scrollbars=yes');
};

    const handleResetEntireTrip = async (button) => {
        const job = {
            type: tsType.getValue(), model: tsModel.getValue(),
            variant: tsVariant.getValue(), trip: tsTrip.getValue(),
        };
        if (!job.type || !job.model || !job.variant || !job.trip) {
            alert("Error: Job data is incomplete. Cannot reset trip.");
            return;
        }
        const isConfirmed = confirm(`ARE YOU SURE?\n\nYou are about to reset all scanned counts for:\n\nTrip: ${job.trip}\nModel: ${job.model}\nVariant: ${job.variant}\n\nThis cannot be undone.`);
        if (isConfirmed) {
            try {
                button.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Resetting...</span>`;
                lucide.createIcons();
                const formData = new FormData();
                formData.append('type', job.type);
                formData.append('model', job.model);
                formData.append('variant', job.variant);
                formData.append('trip', job.trip);
                const response = await fetch(`${API_URL}&action=reset_entire_trip`, { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    alert(`Trip reset successfully. ${data.rows_affected} parts were updated.`);
                    fetchTripPlan();
                } else {
                    alert('Error: Could not reset trip. ' + data.message);
                    button.innerHTML = `<i data-lucide="rotate-ccw" class="w-4 h-4"></i><span>Reset Trip</span>`;
                    lucide.createIcons();
                }
            } catch (err) {
                alert('Connection error: ' + err.message);
                button.innerHTML = `<i data-lucide="rotate-ccw" class="w-4 h-4"></i><span>Reset Trip</span>`;
                lucide.createIcons();
            }
        }
    };

    tableCard.tripActionsContainer.addEventListener('click', (e) => {
        const printBtn = e.target.closest('#printTripTicketBtn');
        const resetBtn = e.target.closest('#resetEntireTripBtn');
        if (printBtn) { handlePrintTripTicket(); return; } 
        if (resetBtn) { handleResetEntireTrip(resetBtn); return; }
    });

    buttons.start.addEventListener('click', startJob);
    buttons.stop.addEventListener('click', stopJob);
    
    filters.lot_no.addEventListener('input', checkFormValidity);

    lucide.createIcons();

    // ============================================================
    // FIXED: CSV LOGIC MOVED INSIDE DOMContentLoaded
    // ============================================================
    const handleCsvExport = () => {
        // 1. Get current filter values
        const term = tableCard.historySearch.value.toLowerCase();
        const model = tableCard.historyModel.value;
        const date = tableCard.historyDate.value;

        // 2. Filter allLogRows (Reuse logic from filterHistoryTable so results match)
        let rowsToExport = [];
        
        if (allLogRows.length > 0) {
            rowsToExport = allLogRows.filter(row => {
                const text = row.innerText.toLowerCase();
                // Note: We access children by index based on your HTML structure
                const rowModel = row.children[6].innerText; 
                const dateText = row.children[0].innerText.trim(); 
                
                let rowDate = "";
                if(dateText.length >= 10) {
                    // Convert "09/02/2026 14:30" to "2026-02-09" for comparison
                    const parts = dateText.split(' ')[0].split('/'); 
                    if(parts.length === 3) rowDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
                }

                const matchSearch = text.includes(term);
                const matchModel = model === "" || rowModel === model;
                const matchDate = date === "" || rowDate === date;

                return matchSearch && matchModel && matchDate;
            });
        }

        if (rowsToExport.length === 0) {
            alert("No data to export based on current filters.");
            return;
        }

        // 3. Define Headers (Must match your HTML Table Headers)
        const headers = [
            "Date/Time Out", "TT ID", "Prod Date", "Part Name", 
            "Part No (FG)", "ERP Code (FG)", "Model", "Type", 
            "Prod Area", "Qty", "Released By", "Mazda ID", 
            "MSC Code", "Trip", "Lot No"
        ];

        // 4. Extract Data from Rows
        const csvRows = rowsToExport.map(row => {
            const cells = row.querySelectorAll('td');
            // We ignore the last column (Action/Delete button)
            const rowData = Array.from(cells).slice(0, 15).map(td => {
                // Escape quotes and handle commas within data
                let data = td.innerText.trim();
                return `"${data.replace(/"/g, '""')}"`; 
            });
            return rowData.join(",");
        });

        // 5. Combine Headers and Data
        const csvContent = [headers.join(","), ...csvRows].join("\n");

        // 6. Trigger Download
        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        
        // Create filename with timestamp
        const timestamp = new Date().toISOString().slice(0,10);
        link.setAttribute("href", url);
        link.setAttribute("download", `Warehouse_Out_Export_${timestamp}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Attach Listener
    if(tableCard.exportCsv) {
        tableCard.exportCsv.addEventListener("click", handleCsvExport);
    }

    lucide.createIcons();

});

function deleteWarehouseOutScan(logId, btn) {
    const pwd = prompt("Enter Admin Password to delete:");
    if(!pwd) return;
    
    const formData = new FormData();
    formData.append('log_id', logId);
    formData.append('password', pwd);

    const originalBtnContent = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>`;
    lucide.createIcons();
    btn.disabled = true;

    fetch('delete_warehouse_out_scan.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert("Deleted successfully.");
            
            if(typeof fetchTripPlan === 'function') {
                fetchTripPlan();
            }
             window.location.reload();
        } else {
            alert("Error: " + data.message);
            btn.innerHTML = originalBtnContent;
            btn.disabled = false;
            lucide.createIcons();
        }
    })
    .catch(err => {
        alert("Connection Error");
        btn.innerHTML = originalBtnContent;
        btn.disabled = false;
        lucide.createIcons();
    });


}