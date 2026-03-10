<?php
require_once 'db.php';
include 'layout/header.php';
$page_title = 'Track Transfer Ticket';
?>

<style>
    /* --- CUSTOM TRACKER STYLES --- */
    .track-step { position: relative; z-index: 10; }
    
    /* Default (Mobile) Icon Size */
    .icon-box { width: 2.5rem; height: 2.5rem; font-size: 0.75rem; } 
    
    /* Desktop Icon Size */
    @media (min-width: 768px) {
        .icon-box { width: 3.5rem; height: 3.5rem; font-size: 1rem; }
    }

    /* Active/Completed States */
    .track-step.active .icon-box { 
        background-color: #2563eb; border-color: #2563eb; color: white; 
        transform: scale(1.1); box-shadow: 0 0 15px rgba(37, 99, 235, 0.4); 
    }
    .track-step.completed .icon-box { 
        background-color: #10b981; border-color: #10b981; color: white; 
    }
    
    /* Text Colors */
    .track-step.completed .step-text { color: #10b981; font-weight: 700; }
    .track-step.active .step-text { color: #2563eb; font-weight: 700; }

    /* Progress Line positioning */
    .progress-bar-container { position: absolute; top: 1.25rem; left: 0; width: 100%; height: 4px; z-index: 0; }
    @media (min-width: 768px) { .progress-bar-container { top: 1.75rem; } }

    .progress-bar-bg { width: 100%; height: 100%; background-color: #e5e7eb; }
    .progress-bar-fill { position: absolute; top: 0; left: 0; height: 100%; background-color: #10b981; transition: width 1s ease-in-out; }

    /* Timeline Vertical Line */
    .timeline-line { position: absolute; left: 1.25rem; top: 2.5rem; bottom: 0; width: 2px; background: #e5e7eb; z-index: 0; }
    
    /* Animation for new elements */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
</style>

<div class="container mx-auto px-4 py-6 md:py-10 min-h-screen bg-gray-50">

    <div class="max-w-4xl mx-auto text-center mb-8 animate-fade-in">
        <h1 class="text-2xl md:text-4xl font-black text-slate-800 mb-2 tracking-tight uppercase">
            Track Parts
        </h1>
        <p class="text-sm md:text-base text-slate-500 mb-6">Enter Ticket ID to check status for all parts.</p>

        <div class="bg-white p-2 rounded-2xl shadow-lg border border-gray-200 flex flex-col md:flex-row gap-3 max-w-xl mx-auto">
            <div class="flex-1 relative">
                <i data-lucide="hash" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="ticketSearchInput" 
                       class="w-full pl-12 pr-4 py-3 bg-gray-50 md:bg-transparent rounded-xl border border-transparent focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none font-bold text-gray-700 text-base md:text-lg transition-all" 
                       placeholder="Ticket ID (e.g. 496)">
            </div>
            
            <button id="searchBtn" class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold py-3 px-6 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 w-full md:w-auto">
                <span class="tracking-wide">SEARCH</span>
            </button>
        </div>
    </div>

    <div id="resultContainer" class="hidden max-w-6xl mx-auto animate-slide-up pb-20">
        
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6 relative overflow-hidden">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <div class="text-center md:text-left">
                     <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Ticket Reference</span>
                     <span id="displayTicketID" class="text-2xl md:text-3xl font-black text-blue-600 font-mono">#---</span>
                </div>
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
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Generated</span>
                    </div>

                    <div class="track-step flex flex-col items-center gap-2" id="step-wh_in">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="warehouse" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">In Stock</span>
                    </div>

                    <div class="track-step flex flex-col items-center gap-2" id="step-pne">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="settings-2" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Process</span>
                    </div>

                    <div class="track-step flex flex-col items-center gap-2" id="step-shipped">
                        <div class="icon-box rounded-full bg-white border-4 border-gray-100 flex items-center justify-center text-gray-300 transition-all duration-300">
                            <i data-lucide="truck" class="w-4 h-4 md:w-6 md:h-6"></i>
                        </div>
                        <span class="step-text text-[10px] md:text-xs font-bold text-gray-300 uppercase tracking-wider text-center">Shipped</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-1 order-2 lg:order-1 space-y-6">
                
                <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8">
                    <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i data-lucide="list-checks" class="w-5 h-5 text-blue-500"></i> Activity History
                    </h3>
                    <div class="relative pl-2" id="timelineContainer">
                        </div>
                </div>

                <div id="releasedByCard" class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 hidden">
                    <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i data-lucide="users" class="w-5 h-5 text-blue-500"></i> Released By
                    </h3>
                    <div id="releasedByList" class="space-y-4"></div>
                </div>

            </div>

            <div class="lg:col-span-2 order-1 lg:order-2">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2 px-2">
                    <i data-lucide="package" class="w-5 h-5 text-blue-500"></i> Parts in this Ticket
                </h3>
                
                <div id="partsListContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    </div>
            </div>

        </div>
    </div>
    
    <div id="errorContainer" class="hidden max-w-md mx-auto mt-10">
        <div class="bg-white rounded-2xl shadow-lg border border-red-100 p-8 text-center">
            <div class="w-14 h-14 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
                <i data-lucide="alert-circle" class="w-6 h-6"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Ticket Not Found</h3>
            <p class="text-slate-500 text-sm mt-2">Please check your Ticket ID.</p>
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        lucide.createIcons();
        
        // Auto-search from URL logic (Only Unique No needed now)
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('unique_no')) {
            document.getElementById('ticketSearchInput').value = urlParams.get('unique_no');
            performSearch();
        }

        document.getElementById('searchBtn').addEventListener('click', performSearch);
    });

    function performSearch() {
        let id = document.getElementById('ticketSearchInput').value.trim();

        if(!id) { alert("Please enter Ticket ID."); return; }

        id = id.padStart(8, '0');
        document.getElementById('ticketSearchInput').value = id;

        // Loading State
        const btn = document.getElementById('searchBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>`;
        btn.disabled = true;

        document.getElementById('resultContainer').classList.add('hidden');
        document.getElementById('errorContainer').classList.add('hidden');
        document.getElementById('releasedByCard').classList.add('hidden');

        // UPDATED: Only sending unique_no
        fetch(`get_ticket_status.php?unique_no=${id}`)
            .then(res => res.json())
            .then(data => {
                // Handle response - ensure we have an array of parts
                let parts = [];
                if (Array.isArray(data.ticket_details)) {
                    parts = data.ticket_details;
                } else if (data.ticket_details) {
                    parts = [data.ticket_details];
                }

                if(data.success && parts.length > 0) {
                    renderResult(parts, data.tracking_history, data.manpower_details);
                } else {
                    document.getElementById('errorContainer').classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error(err);
                alert("Network Error. Check console.");
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                lucide.createIcons();
            });
    }

    /**
     * Helper Function: Generate HTML for Manpower with Images
     */
    function formatManpowerWithImages(shortNameString, manpowerList) {
        if (!shortNameString) return '';
        if (!manpowerList || manpowerList.length === 0) return '';

        const nicknames = shortNameString.split(/[\/,]+/).map(s => s.trim());
        
        const outputHtml = nicknames.map(nick => {
            const person = manpowerList.find(m => m.nickname === nick || m.emp_id === nick);
            
            let name = nick;
            let imgSrc = 'assets/default_avatar.png'; // Fallback
            let nicknameDisplay = '';

            if (person) {
                name = person.name;
                if(person.img_path && person.img_path.trim() !== "") {
                    imgSrc = person.img_path;
                }
                nicknameDisplay = `(${person.nickname})`;
            }

            return `
            <div class="flex items-center gap-4 py-2 animate-fade-in-up">
                <img src="${imgSrc}" alt="${nick}" 
                     class="w-12 h-12 rounded-full border border-gray-100 shadow-sm object-cover bg-white">
                <div class="flex flex-col">
                    <span class="text-sm md:text-base font-bold text-slate-800 uppercase">${name}</span>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">RELEASED BY ${nicknameDisplay}</span>
                </div>
            </div>`;
        });

        return outputHtml.join('');
    }

    // UPDATED: Accepts 'parts' as an array and loops through them
    function renderResult(parts, history, manpower) {
        
        // 1. Display Ticket ID (Using the first part's ID as they share it)
        document.getElementById('displayTicketID').textContent = '#' + parts[0].unique_no;

        // 2. Generate Parts Grid
        const partsContainer = document.getElementById('partsListContainer');
        partsContainer.innerHTML = ''; // Clear previous

        parts.forEach((part, index) => {
            const imgSrc = part.img_path ? part.img_path : 'assets/no-image.png';
            
            // Create HTML for each part card
            const partHtml = `
            <div class="bg-white rounded-3xl shadow-xl border border-blue-50 overflow-hidden hover:border-blue-200 transition-all animate-fade-in-up" style="animation-delay: ${index * 100}ms">
                <div class="bg-slate-50 p-6 flex justify-center items-center border-b border-slate-100">
                    <img src="${imgSrc}" alt="Part" class="h-32 md:h-40 object-contain drop-shadow-md transition-transform hover:scale-105">
                </div>

                <div class="p-5 md:p-6 space-y-3">
                    <div class="flex justify-between items-start py-2 border-b border-slate-50">
                        <span class="text-xs font-bold text-slate-400 uppercase mt-1">Part Name</span>
                        <span class="font-bold text-slate-700 text-right text-sm max-w-[60%] leading-tight">${part.part_name}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="py-2 border-b border-slate-50">
                            <span class="text-xs font-bold text-slate-400 uppercase block">Model</span>
                            <span class="font-bold text-slate-800 text-sm">${part.model}</span>
                        </div>
                        <div class="py-2 border-b border-slate-50 text-right">
                            <span class="text-xs font-bold text-slate-400 uppercase block">Line</span>
                            <span class="font-bold text-slate-800 text-sm">${part.prod_area || part.line || '-'}</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-slate-50">
                        <span class="text-xs font-bold text-slate-400 uppercase">Part No (FG)</span>
                        <span class="font-bold text-slate-700 text-sm">${part.part_no_FG}</span>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-slate-50">
                        <span class="text-xs font-bold text-slate-400 uppercase">ERP Code</span>
                        <span class="font-mono font-bold text-indigo-600 bg-indigo-50 px-2 rounded text-sm">${part.erp_code_FG}</span>
                    </div>

                    <div class="flex justify-between items-center py-2">
                        <span class="text-xs font-bold text-slate-400 uppercase">Quantity</span>
                        <span class="font-bold text-emerald-600 text-xl">${part.quantity} pcs</span>
                    </div>
                </div>
            </div>`;
            
            partsContainer.insertAdjacentHTML('beforeend', partHtml);
        });

        // 3. Status Badge Logic (Based on history)
        const statusCodes = history.map(h => h.status_code);
        let stage = 1;
        let statusText = "Ticket Generated";
        let statusColor = "bg-gray-100 text-gray-600";
        let hasPne = false;

        if (statusCodes.includes('CUSTOMER_OUT')) { 
            stage = 4; statusText = "Shipped"; statusColor = "bg-emerald-100 text-emerald-700";
        } else if (statusCodes.includes('PNE_IN')) { 
            stage = 3; statusText = "Returned from PNE"; hasPne = true; statusColor = "bg-purple-100 text-purple-700";
        } else if (statusCodes.includes('PNE_OUT')) { 
            stage = 2.5; statusText = "At PNE Process"; hasPne = true; statusColor = "bg-orange-100 text-orange-700";
        } else if (statusCodes.includes('WH_IN')) { 
            stage = 2; statusText = "In Warehouse"; statusColor = "bg-blue-100 text-blue-700";
        }

        const badge = document.getElementById('currentStatusBadge');
        badge.textContent = statusText;
        badge.className = `inline-flex items-center px-4 py-1.5 rounded-full text-xs md:text-sm font-bold shadow-sm transition-all duration-500 ${statusColor}`;

        updateProgressBar(stage, hasPne);

        // 4. Released By Card
        // We take the 'released_by' from the first part, as it's usually the same for the whole ticket
        const releasedCard = document.getElementById('releasedByCard');
        const releasedList = document.getElementById('releasedByList');
        
        if (parts[0].released_by) {
            releasedList.innerHTML = formatManpowerWithImages(parts[0].released_by, manpower);
            releasedCard.classList.remove('hidden'); 
        } else {
            releasedCard.classList.add('hidden');
        }
        
        // 5. Render Timeline (History)
        renderTimeline(history);

        document.getElementById('resultContainer').classList.remove('hidden');
    }

    function updateProgressBar(stage, hasPne) {
        const steps = ['printed', 'wh_in', 'pne', 'shipped'];
        steps.forEach(id => {
            const el = document.getElementById('step-' + id);
            if(el) el.classList.remove('active', 'completed');
        });

        const setStepStatus = (id, status) => {
            const el = document.getElementById('step-' + id);
            if (el && status) el.classList.add(status);
        };

        if (stage >= 1) setStepStatus('printed', stage > 1 ? 'completed' : 'active');
        if (stage >= 2) setStepStatus('wh_in', stage > 2 ? 'completed' : 'active');
        if (hasPne || stage >= 3) {
            let pneStatus = '';
            if (stage >= 3) pneStatus = 'completed';
            else if (stage >= 2.5) pneStatus = 'active';
            if (stage === 4) pneStatus = 'completed'; 
            if (pneStatus) setStepStatus('pne', pneStatus);
        }
        if (stage === 4) setStepStatus('shipped', 'completed');

        let width = 0;
        if (stage >= 2) width = 33;
        if (stage >= 2.5) width = 50;
        if (stage >= 3) width = 66;
        if (stage === 4) width = 100;
        
        const barFill = document.getElementById('progressBarFill');
        if(barFill) setTimeout(() => { barFill.style.width = width + '%'; }, 100);
    }

    function renderTimeline(history) {
        const container = document.getElementById('timelineContainer');
        container.innerHTML = '';
        
        if(history.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 py-4 text-sm">No activity recorded yet.</div>';
            return;
        }

        history.forEach((log, index) => {
            let icon = 'circle';
            let color = 'text-gray-400';
            let bg = 'bg-gray-100';
            
            if(index === 0) { color = 'text-blue-600'; bg = 'bg-blue-50'; icon = 'check-circle-2'; }
            
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
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-[10px] text-black-400 font-mono">${new Date(log.status_timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        });
        lucide.createIcons();
    }
</script>
<?php include 'layout/footer.php'; ?>