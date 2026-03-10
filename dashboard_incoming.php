<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Ensure login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: login.php"); exit; }
include 'layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="container mx-auto px-4 py-8 bg-gray-50 min-h-screen">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 animate-fade-in">
        <div>
            <h1 class="text-3xl font-black text-slate-800 flex items-center gap-3">
                <i data-lucide="layout-dashboard" class="w-8 h-8 text-blue-600"></i>
                INCOMING DASHBOARD
            </h1>
            <p class="text-slate-500 mt-1">Real-time stock overview and analysis</p>
        </div>
        <div class="flex gap-3 mt-4 md:mt-0">
            <button onclick="manualRefresh()" id="btn-refresh" class="bg-indigo-600 hover:bg-indigo-700 text-white border border-indigo-700 px-4 py-2 rounded-lg font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh Data
            </button>

            <a href="index.php" class="bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-lg font-bold text-sm flex items-center gap-2 transition-all">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Home
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Total Receiving In</p>
                <h2 class="text-3xl font-black text-blue-600 mt-2" id="sum_rec_in">0</h2>
                <div class="mt-2 flex items-center text-xs font-medium text-slate-400">
                    <i data-lucide="arrow-down-to-line" class="w-4 h-4 mr-1"></i> Inbound Stock
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-indigo-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Total Racking In</p>
                <h2 class="text-3xl font-black text-indigo-600 mt-2" id="sum_rack_in">0</h2>
                <div class="mt-2 flex items-center text-xs font-medium text-slate-400">
                    <i data-lucide="layers" class="w-4 h-4 mr-1"></i> Stored on Racks
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-slate-100 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Total Racking Out</p>
                <h2 class="text-3xl font-black text-slate-600 mt-2" id="sum_rack_out">0</h2>
                <div class="mt-2 flex items-center text-xs font-medium text-slate-400">
                    <i data-lucide="package-open" class="w-4 h-4 mr-1"></i> Unboxing / Out
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-cyan-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider">Total Production In</p>
                <h2 class="text-3xl font-black text-cyan-600 mt-2" id="sum_prod_in">0</h2>
                <div class="mt-2 flex items-center text-xs font-medium text-slate-400">
                    <i data-lucide="factory" class="w-4 h-4 mr-1"></i> Factory Floor
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-l-4 border-amber-400 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-amber-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-amber-600 uppercase tracking-wider">OS Receiving</p>
                        <h2 class="text-3xl font-black text-slate-800 mt-2" id="sum_os_rec">0</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Receiving - Racking In</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-l-4 border-emerald-400 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-emerald-600 uppercase tracking-wider">OS Ranking</p>
                        <h2 class="text-3xl font-black text-slate-800 mt-2" id="sum_os_rank">0</h2>
                    </div>
                   
                </div>
                <p class="text-xs text-slate-400 mt-2">Racking In - Racking Out</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-l-4 border-purple-400 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-purple-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-purple-600 uppercase tracking-wider">OS Unboxing</p>
                        <h2 class="text-3xl font-black text-slate-800 mt-2" id="sum_os_unbox">0</h2>
                    </div>
                   
                </div>
                <p class="text-xs text-slate-400 mt-2">Production In - Racking In</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-l-4 border-rose-500 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 w-24 h-24 bg-rose-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
            <div class="relative">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-bold text-rose-600 uppercase tracking-wider">OS Total</p>
                        <h2 class="text-3xl font-black text-slate-800 mt-2" id="sum_os_total">0</h2>
                    </div>
                   
                </div>
                <p class="text-xs text-slate-400 mt-2">OS Receiving + OS Ranking</p>
            </div>
        </div>

    </div>

    <div class="flex justify-center mb-6">
        <div class="bg-white p-1 rounded-xl shadow-sm border border-slate-200 inline-flex">
            <button onclick="switchTab('receiving')" id="btn-receiving" class="px-6 py-2 rounded-lg text-sm font-bold transition-all bg-blue-600 text-white shadow-md">
                Receiving Analytics
            </button>
            <button onclick="switchTab('racking')" id="btn-racking" class="px-6 py-2 rounded-lg text-sm font-bold text-slate-500  transition-all">
                Racking Analytics
            </button>
        </div>
    </div>

    <div class="space-y-6 mb-8">
        
        <div id="tab-receiving" class="animate-fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100">
                    <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <div class="w-2 h-8 bg-blue-500 rounded-full"></div>
                        <div><span class="block text-sm text-slate-400">High Volume</span>Top 20 Receiving In</div>
                    </h3>
                    <div id="chartRecTop" class="w-full h-[280px]"></div>
                </div>

                <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100">
                    <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <div class="w-2 h-8 bg-orange-500 rounded-full"></div>
                        <div><span class="block text-sm text-slate-400">Low Volume</span>Bottom 20 Receiving In</div>
                    </h3>
                    <div id="chartRecBot" class="w-full h-[280px]"></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100">
                <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <div class="w-2 h-8 bg-purple-500 rounded-full"></div>
                    <div>
                        <span class="block text-sm text-slate-400">Extremes Analysis</span>
                        OS Receiving: Top 20 vs Bottom 20
                    </div>
                </h3>
                <div id="chartOsRecMix" class="w-full h-[350px]"></div>
            </div>
        </div>

        <div id="tab-racking" class="hidden animate-fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100">
                    <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <div class="w-2 h-8 bg-emerald-500 rounded-full"></div>
                        <div><span class="block text-sm text-slate-400">High Storage</span>Top 20 Racking In</div>
                    </h3>
                    <div id="chartRackTop" class="w-full h-[280px]"></div>
                </div>

                <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100">
                    <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <div class="w-2 h-8 bg-rose-500 rounded-full"></div>
                        <div><span class="block text-sm text-slate-400">Low Storage</span>Bottom 20 Racking In</div>
                    </h3>
                    <div id="chartRackBot" class="w-full h-[280px]"></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl shadow-lg border border-slate-100">
                <h3 class="font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <div class="w-2 h-8 bg-indigo-500 rounded-full"></div>
                    <div>
                        <span class="block text-sm text-slate-400">Discrepancy Analysis</span>
                        OS Rank: Top 20 vs Bottom 20
                    </div>
                </h3>
                <div id="chartOsRankMix" class="w-full h-[350px]"></div>
            </div>
        </div>

    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50 flex flex-col md:flex-row justify-between items-center gap-4">
            
            <h3 class="font-bold text-lg text-slate-700 flex items-center gap-2">
                <i data-lucide="database" class="w-5 h-5 text-blue-500"></i>
                Incoming Data
            </h3>
            
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto items-center">
                <div class="relative w-full md:w-72">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4"></i>
                    <input type="text" id="tableSearch" placeholder="Search Part, ERP, Desc, Seq..." 
                           class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm font-semibold ">
                </div>
                
                <button onclick="downloadCSV()" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-bold text-sm flex items-center justify-center gap-2 shadow-lg transition-all whitespace-nowrap">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[600px] overflow-y-auto relative shadow-inner">
    <table class="w-full text-sm text-left border-collapse">
        
        <thead class="bg-slate-800 text-white uppercase font-bold text-xs sticky top-0 z-10 shadow-md">
            <tr>
                <th class="px-4 py-4">Supplier</th>
                <th class="px-4 py-4">ERP Code</th>
                <th class="px-4 py-4">Part No</th>
                <th class="px-4 py-4">Stock Desc</th>
                <th class="px-4 py-4 text-center">Seq No</th>
                <th class="px-4 py-4 text-center">Model</th>
                <th class="px-4 py-4 text-center bg-blue-900">Receiving In</th>
                <th class="px-4 py-4 text-center bg-indigo-900">Racking In</th>
                <th class="px-4 py-4 text-center bg-slate-900">Racking Out</th>
                <th class="px-4 py-4 text-center bg-cyan-800">Production In</th>
                <th class="px-4 py-4 text-center bg-amber-700">OS Receiving</th>
                <th class="px-4 py-4 text-center bg-emerald-700">OS Ranking</th>
                <th class="px-4 py-4 text-center bg-purple-700">OS Unboxing</th>
                <th class="px-4 py-4 text-center bg-rose-700">OS Total</th>
            </tr>
        </thead>
        
        <tbody id="dashboardBody" class="divide-y divide-slate-100 font-medium text-slate-600">
            <tr><td colspan="14" class="text-center py-10"><i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i> Loading Real-time Data...</td></tr>
        </tbody>
    </table>
</div>
    </div>

</div>

<script>
    // --- VARIABLES ---
    let globalData = [];
    let chartInstances = {};

    // --- INITIALIZATION ---
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        fetchData();
        
        setInterval(fetchData, 30000); 

        // Search Input Listener
        document.getElementById('tableSearch').addEventListener('keyup', (e) => {
            const term = e.target.value;
            const filtered = filterDataByTerm(globalData, term);
            renderTable(filtered);
        });
    });

    // --- HELPER: CENTRALIZED FILTER LOGIC ---
    function filterDataByTerm(data, term) {
        if (!term) return data;
        const lowerTerm = term.toLowerCase();
        
        return data.filter(row => {
            const erp = (row.erp_code || '').toString().toLowerCase();
            const part = (row.part_no || '').toString().toLowerCase();
            const desc = (row.stock_desc || '').toString().toLowerCase();
            const seq = (row.seq_number || '').toString().toLowerCase();
            const model = (row.model || '').toString().toLowerCase(); 
            
            return erp.includes(lowerTerm) || 
                   part.includes(lowerTerm) || 
                   desc.includes(lowerTerm) || 
                   seq.includes(lowerTerm) || 
                   model.includes(lowerTerm);
        });
    }

    // --- REFRESH FUNCTION ---
    function manualRefresh() {
        const btn = document.getElementById('btn-refresh');
        const icon = btn.querySelector('svg'); 
        
        if (icon) icon.classList.add('animate-spin');
        
        // 1. CLEAR SEARCH BOX
        const searchInput = document.getElementById('tableSearch');
        if(searchInput) searchInput.value = '';

        fetchData().then(() => {
            setTimeout(() => {
                if(icon) icon.classList.remove('animate-spin');
            }, 1000);
        });
    }

    // --- TAB SWITCHING ---
    function switchTab(tabName) {
        const recDiv = document.getElementById('tab-receiving');
        const rackDiv = document.getElementById('tab-racking');
        const btnRec = document.getElementById('btn-receiving');
        const btnRack = document.getElementById('btn-racking');

        if(tabName === 'receiving') {
            recDiv.classList.remove('hidden');
            rackDiv.classList.add('hidden');
            
            btnRec.classList.add('bg-blue-600', 'text-white', 'shadow-md');
            btnRec.classList.remove('text-slate-500');
            
            btnRack.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
            btnRack.classList.add('text-slate-500');
        } else {
            recDiv.classList.add('hidden');
            rackDiv.classList.remove('hidden');

            btnRack.classList.add('bg-blue-600', 'text-white', 'shadow-md');
            btnRack.classList.remove('text-slate-500');
            
            btnRec.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
            btnRec.classList.add('text-slate-500');
        }
        window.dispatchEvent(new Event('resize'));
    }

    // --- FETCH DATA ---
    async function fetchData() {
        try {
            const res = await fetch('dashboard_incoming_api.php');
            const text = await res.text(); // Fetch as raw text first
            
            try {
                const json = JSON.parse(text); // Try parsing JSON
                if(json.success) {
                    globalData = json.data;
                    const summary = json.summary;

                    updateCards(summary);
                    
                    const searchInput = document.getElementById('tableSearch');
                    const currentTerm = searchInput ? searchInput.value : '';

                    const dataToShow = filterDataByTerm(globalData, currentTerm);

                    renderTable(dataToShow);
                    updateCharts(globalData);
                } else {
                    // Print database error cleanly
                    document.getElementById('dashboardBody').innerHTML = `
                        <tr><td colspan="14" class="text-center py-10 text-rose-600 font-bold bg-rose-50 border border-rose-200">
                        <i data-lucide="alert-triangle" class="w-6 h-6 inline-block mb-1"></i><br>
                        Database Error: ${json.message}<br>
                        <span class="text-xs font-normal text-slate-500 mt-2 block">Please review your database connections and queries.</span>
                        </td></tr>`;
                    lucide.createIcons();
                }
            } catch (jsonError) {
                console.error("JSON Parse Error", jsonError);
                document.getElementById('dashboardBody').innerHTML = `
                        <tr><td colspan="14" class="text-center py-10 text-rose-600 font-bold bg-rose-50 border border-rose-200">
                        <i data-lucide="alert-circle" class="w-6 h-6 inline-block mb-1"></i><br>
                        Server Error: ${text}<br>
                        <span class="text-xs font-normal text-slate-500 mt-2 block">Check your API response for PHP errors.</span>
                        </td></tr>`;
                lucide.createIcons();
            }

        } catch(e) { 
            console.error("Dashboard Error", e); 
        }
    }

    function updateCards(summary) {
        animateValue("sum_rec_in", summary.total_receiving_in);
        animateValue("sum_rack_in", summary.total_racking_in);
        animateValue("sum_rack_out", summary.total_racking_out);
        animateValue("sum_prod_in", summary.total_production_in);
        animateValue("sum_os_rec", summary.total_os_receiving);
        animateValue("sum_os_rank", summary.total_os_ranking);
        animateValue("sum_os_unbox", summary.total_os_unboxing);
        animateValue("sum_os_total", summary.total_os_overall);
    }

    function animateValue(id, end) {
        const obj = document.getElementById(id);
        if(!obj) return;
        const start = parseInt(obj.innerText.replace(/,/g, '')) || 0;
        if(start === end) return;
        obj.innerText = end.toLocaleString();
    }

    function renderTable(data) {
        const tbody = document.getElementById('dashboardBody');
        if(data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="14" class="text-center py-10 text-slate-400">No data found.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.map((row, idx) => `
            <tr class="hover:bg-blue-50 transition-colors ${idx % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
                <td class="px-4 py-3"><span class="font-bold text-xs px-2 py-1 rounded ${row.supplier === 'YTEC' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'}">${row.supplier}</span></td>
                <td class="px-4 py-3 font-mono text-slate-800 font-bold">${row.erp_code}</td>
                <td class="px-4 py-3 text-blue-600 font-semibold">${row.part_no}</td>
                <td class="px-4 py-3 text-xs truncate max-w-[200px]" title="${row.stock_desc}">${row.stock_desc}</td>
                <td class="px-4 py-3 text-center font-bold text-slate-500">${row.seq_number}</td>
                <td class="px-4 py-3 text-center font-bold text-slate-500">${row.model || '-'}</td>
                <td class="px-4 py-3 text-center font-bold text-blue-600 bg-blue-50/50">${row.receiving_in}</td>
                <td class="px-4 py-3 text-center font-bold text-indigo-600 bg-indigo-50/50">${row.racking_in}</td>
                <td class="px-4 py-3 text-center font-bold text-slate-600 bg-slate-100/50">${row.racking_out}</td>
                <td class="px-4 py-3 text-center font-bold text-cyan-600 bg-cyan-50/50">${row.production_in}</td>
                <td class="px-4 py-3 text-center font-bold text-amber-700 bg-amber-100 border-l-2 border-amber-200">${row.os_receiving}</td>
                <td class="px-4 py-3 text-center font-bold text-emerald-700 bg-emerald-100 border-l-2 border-emerald-200">${row.os_ranking}</td>
                <td class="px-4 py-3 text-center font-bold text-purple-700 bg-purple-100 border-l-2 border-purple-200">${row.os_unboxing}</td>
                <td class="px-4 py-3 text-center font-black text-rose-700 bg-rose-100 border-l-2 border-rose-200 text-lg">${row.os_total}</td>
            </tr>
        `).join('');
    }

    // --- CHART LOGIC ---
    function updateCharts(data) {
        const getSorted = (key, desc = true) => [...data].sort((a,b) => {
            if (b[key] === a[key]) return 0;
            return desc ? b[key] - a[key] : a[key] - b[key];
        });

        const getTop = (key, count) => getSorted(key, true).slice(0, count);
        const getBot = (key, count) => getSorted(key, false).slice(0, count);

        const getGapData = (key, countEach) => {
            const sortedDesc = getSorted(key, true);
            const topPart = sortedDesc.slice(0, countEach);
            const botPart = sortedDesc.slice(-countEach); 
            const gapItem = { erp_code: "", stock_desc: "", [key]: 0, isGap: true };
            return [...topPart, gapItem, gapItem, ...botPart];
        };

        // Palettes
        const topPalette = ['#2563eb', '#3b82f6', '#60a5fa', '#1d4ed8', '#1e40af', '#0ea5e9', '#0284c7', '#0369a1', '#075985', '#0c4a6e', '#2563eb', '#3b82f6', '#60a5fa', '#1d4ed8', '#1e40af', '#0ea5e9', '#0284c7', '#0369a1', '#075985', '#0c4a6e'];
        const botPalette = ['#ea580c', '#f97316', '#fb923c', '#c2410c', '#9a3412', '#dc2626', '#ef4444', '#f87171', '#b91c1c', '#991b1b', '#ea580c', '#f97316', '#fb923c', '#c2410c', '#9a3412', '#dc2626', '#ef4444', '#f87171', '#b91c1c', '#991b1b'];

        // TAB 1: RECEIVING
        renderChart('chartRecTop', getTop('receiving_in', 20), 'receiving_in', topPalette);
        renderChart('chartRecBot', getBot('receiving_in', 20), 'receiving_in', botPalette);
        renderMixedChart('chartOsRecMix', getGapData('os_receiving', 20), 'os_receiving', '#7c3aed', '#c026d3');

        // TAB 2: RACKING
        renderChart('chartRackTop', getTop('racking_in', 20), 'racking_in', ['#059669', '#10b981', '#34d399', '#047857', '#065f46', '#0d9488', '#14b8a6', '#5eead4', '#0f766e', '#134e4a', '#059669', '#10b981', '#34d399', '#047857', '#065f46', '#0d9488', '#14b8a6', '#5eead4', '#0f766e', '#134e4a']);
        renderChart('chartRackBot', getBot('racking_in', 20), 'racking_in', ['#e11d48', '#f43f5e', '#fb7185', '#be123c', '#881337', '#db2777', '#ec4899', '#f472b6', '#9d174d', '#831843', '#e11d48', '#f43f5e', '#fb7185', '#be123c', '#881337', '#db2777', '#ec4899', '#f472b6', '#9d174d', '#831843']);
        renderMixedChart('chartOsRankMix', getGapData('os_ranking', 20), 'os_ranking', '#0891b2', '#d97706');
    }

    function renderChart(elementId, dataSet, dataKey, colors) {
        const labels = dataSet.map(item => item.erp_code);
        const values = dataSet.map(item => item[dataKey]);
        const descriptions = dataSet.map(item => item.stock_desc);
        const models = dataSet.map(item => item.model); // Get models
        const options = {
            series: [{ name: 'Qty', data: values }],
            chart: {
                type: 'bar',
                height: 280, // Height Reduced
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif',
                animations: { enabled: true, easing: 'easeinout', speed: 800 },
                dropShadow: { enabled: true, top: 2, left: 2, blur: 3, opacity: 0.15 }
            },
            colors: colors,
            fill: {
                type: 'gradient',
                gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.3, opacityFrom: 0.9, opacityTo: 1, stops: [0, 90, 100] }
            },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%', distributed: true } },
            dataLabels: { enabled: false },
            legend: { show: false },
            xaxis: {
                categories: labels,
                labels: { rotate: -45, style: { fontSize: '10px' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            grid: { show: false, padding: { bottom: 0 } },
            tooltip: {
                theme: 'dark',
                y: { formatter: function (val) { return val + " units" } },
                custom: function({series, seriesIndex, dataPointIndex, w}) {
                    const value = series[seriesIndex][dataPointIndex];
                    const label = w.globals.labels[dataPointIndex];
                    const desc = descriptions[dataPointIndex];
                    const model = models[dataPointIndex] || 'N/A'; // Get current model
                    const color = w.config.colors[dataPointIndex];
                    
                    return `
                        <div class="px-3 py-2 bg-slate-800 text-white rounded shadow-lg border-l-4" style="border-color: ${color}">
                            <div class="flex justify-between items-center gap-2 mb-1">
                                <span class="font-bold text-sm">${label}</span>
                                <span class="text-[10px] bg-white/20 px-1.5 rounded uppercase tracking-wider">${model}</span>
                            </div>
                            <div class="text-xs text-slate-300 mb-1 max-w-[200px] whitespace-normal">${desc}</div>
                            <div class="font-bold text-lg">${value} <span class="text-xs font-normal text-slate-500">units</span></div>
                        </div>
                    `;
                }
            }
        };

        if(chartInstances[elementId]) {
            chartInstances[elementId].updateOptions(options);
            chartInstances[elementId].updateSeries([{ data: values }]);
        } else {
            chartInstances[elementId] = new ApexCharts(document.querySelector("#" + elementId), options);
            chartInstances[elementId].render();
        }
    }

    function renderMixedChart(elementId, dataSet, dataKey, colorTop, colorBot) {
        const labels = dataSet.map(item => item.isGap ? '' : item.erp_code);
        const values = dataSet.map(item => item[dataKey]);
        const descriptions = dataSet.map(item => item.stock_desc || '');
        const splitIndex = Math.floor(dataSet.length / 2);
        const models = dataSet.map(item => item.model); // Get models
        const colors = dataSet.map((item, index) => {
            if(item.isGap) return 'transparent';
            return index < splitIndex ? colorTop : colorBot;
        });

        const options = {
            series: [{ name: 'OS Qty', data: values }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif',
                animations: { enabled: true, speed: 800 },
                dropShadow: { enabled: true, top: 2, left: 2, blur: 2, opacity: 0.1 }
            },
            colors: colors,
            fill: {
                type: 'gradient',
                gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.25, opacityFrom: 0.85, opacityTo: 1, stops: [0, 100] }
            },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '80%', distributed: true } },
            dataLabels: { enabled: false },
            legend: { show: false },
            xaxis: {
                categories: labels,
                labels: { rotate: -90, style: { fontSize: '10px', fontWeight: 500 } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            grid: { show: true, borderColor: '#f1f5f9', strokeDashArray: 4, padding: { bottom: 0 } },
            tooltip: {
                theme: 'dark',
                custom: function({series, seriesIndex, dataPointIndex, w}) {
                    if(dataSet[dataPointIndex].isGap) return null;
                    const value = series[seriesIndex][dataPointIndex];
                    const label = w.globals.labels[dataPointIndex];
                    const desc = descriptions[dataPointIndex];
                    const color = w.config.colors[dataPointIndex];
                    const model = models[dataPointIndex] || 'N/A'; // Get current model

                    return `
                        <div class="px-3 py-2 bg-slate-800 text-white rounded shadow-lg border-l-4" style="border-color: ${color}">
                            <div class="flex justify-between items-center gap-2 mb-1">
                                <span class="font-bold text-sm">${label}</span>
                                <span class="text-[10px] bg-white/20 px-1.5 rounded uppercase tracking-wider">${model}</span>
                            </div>
                            <div class="text-xs text-slate-300 mb-1 max-w-[200px] whitespace-normal">${desc}</div>
                            <div class="font-bold text-lg">${value} <span class="text-xs font-normal text-slate-500">units</span></div>
                        </div>
                    `;
                }
            }
        };

        if(chartInstances[elementId]) {
            chartInstances[elementId].updateOptions(options);
            chartInstances[elementId].updateSeries([{ data: values }]);
        } else {
            chartInstances[elementId] = new ApexCharts(document.querySelector("#" + elementId), options);
            chartInstances[elementId].render();
        }
    }

    function downloadCSV() {
        // Get the current search term the user typed
        const searchInput = document.getElementById('tableSearch');
        const searchTerm = searchInput ? searchInput.value.trim() : '';

        // Build the URL for the PHP API
        const url = `dashboard_incoming_api.php?action=export_csv&search=${encodeURIComponent(searchTerm)}`;

        // Trigger the download by navigating to the URL
        window.location.href = url;
    }
</script>
<?php include 'layout/footer.php'; ?>