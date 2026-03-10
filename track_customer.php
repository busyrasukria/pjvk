<?php
require_once 'db.php';
include 'layout/header.php';
$page_title = 'Customer Shipment Tracker';
?>

<style>
    /* --- INDIGO THEME FOR CUSTOMER TRACKER --- */
    .track-step { position: relative; z-index: 10; }
    
    .icon-box { width: 2.5rem; height: 2.5rem; font-size: 0.75rem; } 
    @media (min-width: 768px) {
        .icon-box { width: 3.5rem; height: 3.5rem; font-size: 1rem; }
    }

    /* Active/Completed States */
    .track-step.active .icon-box { 
        background-color: #4f46e5; border-color: #4f46e5; color: white; /* Indigo-600 */
        transform: scale(1.1); box-shadow: 0 0 15px rgba(79, 70, 229, 0.4); 
    }
    .track-step.completed .icon-box { 
        background-color: #059669; border-color: #059669; color: white; /* Emerald-600 */
    }
    
    .track-step.completed .step-text { color: #059669; font-weight: 700; }
    .track-step.active .step-text { color: #4f46e5; font-weight: 700; }

    .progress-bar-container { position: absolute; top: 1.25rem; left: 0; width: 100%; height: 4px; z-index: 0; }
    @media (min-width: 768px) { .progress-bar-container { top: 1.75rem; } }

    .progress-bar-bg { width: 100%; height: 100%; background-color: #e5e7eb; }
    .progress-bar-fill { position: absolute; top: 0; left: 0; height: 100%; background-color: #059669; transition: width 1s ease-in-out; }

    .timeline-line { position: absolute; left: 1.25rem; top: 2.5rem; bottom: 0; width: 2px; background: #e5e7eb; z-index: 0; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    
    /* Hover effect for multiple results */
    .result-item-card:hover { border-color: #4f46e5; transform: translateY(-2px); }
</style>

<div class="container mx-auto px-4 py-6 md:py-10 min-h-screen bg-slate-50">

    <div class="max-w-5xl mx-auto text-center mb-8 animate-fade-in">
        <h1 class="text-2xl md:text-4xl font-black text-slate-800 mb-2 tracking-tight uppercase">
            Customer Shipment Tracker
        </h1>
        <p class="text-sm md:text-base text-slate-500 mb-6">Track your lot status. Enter Lot No to start.</p>

        <div class="bg-white p-3 rounded-2xl shadow-lg border border-indigo-100 flex flex-col md:flex-row gap-3 max-w-4xl mx-auto">
            
            <div class="flex-1 relative">
                <i data-lucide="layers" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="lotSearchInput" 
                       class="w-full pl-12 pr-4 py-3 bg-gray-50 md:bg-transparent rounded-xl border border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-700 text-base transition-all" 
                       placeholder="LOT NO">
            </div>
            
            <div class="w-px bg-gray-200 hidden md:block"></div>

            <div class="flex-1 relative">
                <i data-lucide="qr-code" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="mscSearchInput" 
                       class="w-full pl-12 pr-4 py-3 bg-gray-50 md:bg-transparent rounded-xl border border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-700 text-base uppercase transition-all" 
                       placeholder="MSC Code ">
            </div>

            <div class="w-px bg-gray-200 hidden md:block"></div>

            <div class="flex-1 relative">
                <i data-lucide="box" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="erpSearchInput" 
                       class="w-full pl-12 pr-4 py-3 bg-gray-50 md:bg-transparent rounded-xl border border-transparent focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-700 text-base uppercase transition-all" 
                       placeholder="ERP Code">
            </div>
            
            <button id="searchBtn" class="bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold py-3 px-8 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 w-full md:w-auto">
                <span class="tracking-wide">TRACK</span>
            </button>
        </div>
    </div>

    <div id="multipleResultContainer" class="hidden max-w-4xl mx-auto animate-slide-up pb-20">
        <h3 class="text-lg font-bold text-slate-700 mb-4 px-2">Multiple items found. Select one:</h3>
        <div id="multipleResultsList" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            </div>
    </div>

    <div id="resultContainer" class="hidden max-w-6xl mx-auto animate-slide-up pb-20">
        
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6 relative overflow-hidden">
            <div class="text-center mb-6">
                <span id="currentStatusBadge" class="inline-flex items-center px-4 py-1.5 rounded-full text-xs md:text-sm font-bold bg-gray-100 text-gray-600 shadow-sm">
                    Checking...
                </span>
            </div>

            <div class="relative px-2 md:px-8">
                <div class="progress-bar-container mx-6 md:mx-10 w-auto right-6 md:right-10">
                    <div class="progress-bar-bg rounded-full"></div>
                    <div id="progressBarFill" class="progress-bar-fill rounded-full" style="width: 0%;"></div>
                </div>

                <div class="flex justify-between items-start relative">
                    <div class="track-step flex flex-col items-center gap-2" id="step-printed">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="file-check" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Order Rec.</span>
                    </div>

                    <div class="track-step flex flex-col items-center gap-2" id="step-wh_in">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="warehouse" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Warehouse</span>
                    </div>

                    <div class="track-step flex flex-col items-center gap-2" id="step-pne">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="settings-2" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Packing</span>
                    </div>

                    <div class="track-step flex flex-col items-center gap-2" id="step-shipped">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="truck" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Dispatched</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2 order-2 lg:order-1">
                
                <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i data-lucide="list-checks" class="w-5 h-5 text-indigo-500"></i> Activity Log
                    </h3>
                    <div class="relative pl-2" id="timelineContainer">
                        </div>
                </div>

                <div id="releasedByCard" class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 hidden">
                    <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i data-lucide="users" class="w-5 h-5 text-indigo-500"></i> Personnel Information
                    </h3>
                    <div id="releasedByList" class="space-y-4">
                        </div>
                </div>
            </div>

            <div class="lg:col-span-1 order-1 lg:order-2">
                <div class="bg-white rounded-3xl shadow-xl border border-indigo-100 overflow-hidden sticky top-4">
                    <div class="bg-slate-50 p-6 flex justify-center items-center border-b border-slate-100">
                        <img id="detailImage" src="" alt="Part" class="h-32 md:h-40 object-contain drop-shadow-md transition-transform hover:scale-105">
                    </div>

                    <div class="p-5 md:p-6 space-y-3">
                        <div class="flex justify-between items-center py-2 border-b border-slate-50">
                            <span class="text-xs font-bold text-slate-400 uppercase">Lot No</span>
                            <span id="detailLotNo" class="font-mono font-bold text-indigo-600 text-lg">---</span>
                        </div>
                        
                        <div class="flex justify-between items-start py-2 border-b border-slate-50">
                            <span class="text-xs font-bold text-slate-400 uppercase mt-1">Part Description</span>
                            <span id="detailPartName" class="font-bold text-slate-700 text-right text-sm max-w-[60%] leading-tight">---</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="py-2 border-b border-slate-50">
                                <span class="text-xs font-bold text-slate-400 uppercase block">MSC Code</span>
                                <span id="detailMsc" class="font-bold text-slate-800 text-sm">---</span>
                            </div>
                            <div class="py-2 border-b border-slate-50 text-right">
                                <span class="text-xs font-bold text-slate-400 uppercase block">Model</span>
                                <span id="detailModel" class="font-bold text-slate-800 text-sm">---</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-slate-50">
                            <span class="text-xs font-bold text-slate-400 uppercase">Part No</span>
                            <span id="detailPartNo" class="font-bold text-slate-700 text-sm">---</span>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-slate-50">
                            <span class="text-xs font-bold text-slate-400 uppercase">ERP Code</span>
                            <span id="detailErp" class="font-mono font-bold text-indigo-600 bg-indigo-50 px-2 rounded text-sm">---</span>
                        </div>
                        
                        <div class="flex justify-between items-center py-2">
                             <span class="text-xs font-bold text-slate-400 uppercase">Reference Ticket</span>
                             <span id="detailUniqueNo" class="font-bold text-slate-500 text-sm">---</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <div id="errorContainer" class="hidden max-w-md mx-auto mt-10">
        <div class="bg-white rounded-2xl shadow-lg border border-red-100 p-8 text-center">
            <div class="w-14 h-14 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
                <i data-lucide="alert-circle" class="w-6 h-6"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">No Records Found</h3>
            <p class="text-slate-500 text-sm mt-2">Check details.</p>
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        lucide.createIcons();
        // Normal search button click (No specific ID)
        document.getElementById('searchBtn').addEventListener('click', () => performSearch(null));
    });

    // Accept optional specificId
    function performSearch(specificId = null) {
        let url = '';

        // UI Prep
        const btn = document.getElementById('searchBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>`;
        btn.disabled = true;
        
        document.getElementById('resultContainer').classList.add('hidden');
        document.getElementById('multipleResultContainer').classList.add('hidden'); // Hide list
        document.getElementById('errorContainer').classList.add('hidden');
        document.getElementById('releasedByCard').classList.add('hidden');

        // LOGIC: IF ID is passed, search by ID. ELSE search by input text.
        if (specificId) {
            url = `get_customer_status.php?id=${specificId}`;
        } else {
            let lot = document.getElementById('lotSearchInput').value.trim();
            let msc = document.getElementById('mscSearchInput').value.trim();
            let erp = document.getElementById('erpSearchInput').value.trim();

            if(!lot) { 
                alert("Please enter at least the Lot No."); 
                btn.innerHTML = originalText; 
                btn.disabled = false;
                return; 
            }
            url = `get_customer_status.php?lot=${lot}&msc=${msc}&erp=${erp}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    if (data.is_multiple) {
                        renderMultipleResults(data.items);
                    } else {
                        renderResult(data.ticket_details, data.tracking_history, data.manpower_details);
                    }
                } else {
                    const errorBox = document.getElementById('errorContainer');
                    errorBox.querySelector('p').innerHTML = `<span class="font-bold text-red-600">Error:</span> ${data.message}`;
                    errorBox.classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error(err);
                alert("Network Error");
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                lucide.createIcons();
            });
    }

    function renderMultipleResults(items) {
        const container = document.getElementById('multipleResultsList');
        container.innerHTML = '';

        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'result-item-card bg-white p-4 rounded-xl shadow-sm border border-gray-200 cursor-pointer transition-all duration-200 flex items-center justify-between group';
            
            // PASS THE ITEM OBJECT CORRECTLY
            div.onclick = () => selectItem(item);

            div.innerHTML = `
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold shrink-0">
                        <i data-lucide="box" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="font-bold text-slate-800 text-sm">${item.part_name || 'Unknown Part'}</div>
                        <div class="text-xs text-slate-500 font-mono mt-1">
                            <span class="bg-gray-100 px-1 rounded">MSC: ${item.msc_code || '-'}</span> 
                            <span class="bg-gray-100 px-1 rounded">ERP: ${item.erp_code_FG || '-'}</span>
                        </div>
                    </div>
                </div>
                <div class="text-indigo-400 group-hover:text-indigo-600">
                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                </div>
            `;
            container.appendChild(div);
        });

        document.getElementById('multipleResultContainer').classList.remove('hidden');
        lucide.createIcons();
    }

    function selectItem(item) {
        // 1. Visual: Fill the boxes so the user sees what they selected
        document.getElementById('mscSearchInput').value = item.msc_code || '';
        document.getElementById('erpSearchInput').value = item.erp_code_FG || '';

        // 2. Functional: Search using the UNIQUE ID (log_id)
        // This prevents the "blank field loop" issue
        performSearch(item.log_id);
    }
    
    // ... (Keep your existing formatManpowerWithImages, renderResult, updateProgressBar, renderTimeline functions exactly as they were) ...
    // DO NOT CHANGE THE REST OF THE CODE BELOW THIS LINE
    
    function formatManpowerWithImages(shortNameString, manpowerList) {
        if (!shortNameString) return '';
        if (!manpowerList || manpowerList.length === 0) return '';
        const nicknames = shortNameString.split(/[\/,]+/).map(s => s.trim());
        const outputHtml = nicknames.map(nick => {
            const person = manpowerList.find(m => m.nickname === nick || m.emp_id === nick);
            let name = nick;
            let imgSrc = 'assets/default_avatar.png'; 
            let nicknameDisplay = '';
            if (person) {
                name = person.name;
                if(person.img_path && person.img_path.trim() !== "") imgSrc = person.img_path;
                nicknameDisplay = `(${person.nickname})`;
            }
            return `<div class="flex items-center gap-4 py-2 animate-fade-in-up">
                <img src="${imgSrc}" alt="${nick}" class="w-12 h-12 rounded-full border border-gray-100 shadow-sm object-cover bg-white">
                <div class="flex flex-col"><span class="text-sm md:text-base font-bold text-slate-800 uppercase">${name}</span>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">RELEASED BY ${nicknameDisplay}</span></div></div>`;
        });
        return outputHtml.join('');
    }

    function renderResult(details, history, manpower) {
        document.getElementById('detailLotNo').textContent = details.lot_no || '---';
        document.getElementById('detailMsc').textContent = details.msc_code || '---';
        document.getElementById('detailUniqueNo').textContent = details.unique_no ? '#' + details.unique_no : 'N/A';
        document.getElementById('detailPartName').textContent = details.part_name;
        document.getElementById('detailModel').textContent = details.model;
        document.getElementById('detailPartNo').textContent = details.part_no_FG;
        document.getElementById('detailErp').textContent = details.erp_code_FG;
        document.getElementById('detailImage').src = details.img_path ? details.img_path : 'assets/no-image.png';

        const statusCodes = history.map(h => h.status_code);
        let stage = 1;
        let statusText = "Generated";
        let statusColor = "bg-gray-100 text-gray-600";
        let hasPne = false;

        if (statusCodes.includes('CUSTOMER_OUT')) { 
            stage = 4; statusText = "Dispatched to Customer"; statusColor = "bg-emerald-100 text-emerald-700";
        } else if (statusCodes.includes('PNE_IN')) { 
            stage = 3; statusText = "Returned from Process"; hasPne = true; statusColor = "bg-purple-100 text-purple-700";
        } else if (statusCodes.includes('PNE_OUT')) { 
            stage = 2.5; statusText = "At Process (PNE)"; hasPne = true; statusColor = "bg-orange-100 text-orange-700";
        } else if (statusCodes.includes('WH_IN')) { 
            stage = 2; statusText = "In Warehouse"; statusColor = "bg-blue-100 text-blue-700";
        }

        const badge = document.getElementById('currentStatusBadge');
        badge.textContent = statusText;
        badge.className = `inline-flex items-center px-4 py-1.5 rounded-full text-xs md:text-sm font-bold shadow-sm transition-all duration-500 ${statusColor}`;

        updateProgressBar(stage, hasPne);

        const releasedCard = document.getElementById('releasedByCard');
        const releasedList = document.getElementById('releasedByList');
        if (details.released_by) {
            releasedList.innerHTML = formatManpowerWithImages(details.released_by, manpower);
            releasedCard.classList.remove('hidden');
        } else {
            releasedCard.classList.add('hidden');
        }
        renderTimeline(history);
        document.getElementById('resultContainer').classList.remove('hidden');
    }

    function updateProgressBar(stage, hasPne) {
        const steps = ['printed', 'wh_in', 'pne', 'shipped'];
        steps.forEach(id => {
            const el = document.getElementById('step-' + id);
            if(el) el.classList.remove('active', 'completed');
        });
        const setStepStatus = (id, status) => { const el = document.getElementById('step-' + id); if (el && status) el.classList.add(status); };
        if (stage >= 1) setStepStatus('printed', stage > 1 ? 'completed' : 'active');
        if (stage >= 2) setStepStatus('wh_in', stage > 2 ? 'completed' : 'active');
        if (hasPne || stage >= 3) {
            let pneStatus = '';
            if (stage >= 3) pneStatus = 'completed'; else if (stage >= 2.5) pneStatus = 'active'; if (stage === 4) pneStatus = 'completed'; 
            if (pneStatus) setStepStatus('pne', pneStatus);
        }
        if (stage === 4) setStepStatus('shipped', 'completed');
        let width = 0;
        if (stage >= 2) width = 33; if (stage >= 2.5) width = 50; if (stage >= 3) width = 66; if (stage === 4) width = 100;
        const barFill = document.getElementById('progressBarFill');
        if(barFill) setTimeout(() => { barFill.style.width = width + '%'; }, 100);
    }

    function renderTimeline(history) {
        const container = document.getElementById('timelineContainer');
        container.innerHTML = '';
        if(history.length === 0) { container.innerHTML = '<div class="text-center text-slate-400 py-4 text-sm">No activity recorded yet.</div>'; return; }
        history.forEach((log, index) => {
            let icon = 'circle'; let color = 'text-gray-400'; let bg = 'bg-gray-100';
            if(index === 0) { color = 'text-indigo-600'; bg = 'bg-indigo-50'; icon = 'check-circle-2'; }
            if (log.status_code === 'CUSTOMER_OUT') icon = 'truck';
            if (log.status_code === 'WH_IN') icon = 'warehouse';
            if (log.status_code === 'PRINTED') icon = 'printer';
            const html = `
                <div class="relative pl-10 pb-8 group last:pb-0">
                    ${index !== history.length - 1 ? '<div class="timeline-line"></div>' : ''}
                    <div class="absolute left-0 top-0 w-10 h-10 rounded-full ${bg} flex items-center justify-center z-10 border-2 border-white shadow-sm">
                        <i data-lucide="${icon}" class="w-5 h-5 ${color}"></i>
                    </div>
                    <div class="">
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm md:text-base font-bold text-slate-800">${log.status_message}</h4>
                            <span class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded">${new Date(log.status_timestamp).toLocaleDateString('en-GB')}</span>
                        </div>
                        <div class="flex justify-end items-center mt-1">
                            <span class="text-[10px] text-slate-400 font-mono">${new Date(log.status_timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        });
        lucide.createIcons();
    }
</script>
<?php include 'layout/footer.php'; ?>