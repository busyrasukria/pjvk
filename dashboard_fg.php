<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'layout/header.php';
$page_title = 'Finish Good Dashboard';
?>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-3d.js"></script>
<script src="https://code.highcharts.com/modules/cylinder.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<!-- Custom CSS to fix the Dropdown Interface -->
<style>
    /* Prevent the box from growing infinitely */
    .ts-control {
        max-height: 46px !important; /* Limits height to roughly 1 row */
        overflow-y: auto !important; /* Adds scrollbar if too many items */
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }
    /* Make the selected tags compact */
    .ts-control .item {
        background-color: #eff6ff !important; /* Light blue bg */
        border: 1px solid #bfdbfe !important; /* Blue border */
        color: #1e40af !important; /* Dark blue text */
        border-radius: 4px !important;
        padding: 2px 6px !important;
        margin: 2px !important;
        font-weight: 700;
        font-family: monospace; /* Monospace for code look */
    }
    /* Ensure the input cursor is visible */
    .ts-control input {
        margin: 2px !important;
    }
</style>

<div class="container mx-auto px-4 py-8 bg-slate-50 min-h-screen">

    <div class="flex flex-col md:flex-row justify-between items-center mb-8">
        <div>
            <h1 class="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-800 to-indigo-600 flex items-center gap-3">
                <i data-lucide="bar-chart-2" class="w-10 h-10 text-indigo-600"></i>
                FINISH GOOD DASHBOARD
            </h1>
            <div class="flex items-center gap-2 mt-2">
                <p class="text-slate-500 font-medium">Real-time Stock Levels</p>
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
                <span class="text-xs font-bold text-green-600 uppercase tracking-wider">Live (1s)</span>
            </div>
        </div>
        <div class="flex gap-3 mt-4 md:mt-0">
    <button onclick="fetchData()" class="bg-indigo-600 hover:bg-indigo-700 text-white border border-indigo-700 px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh Data
    </button>

    <a href="index.php" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Home
    </a>
</div>
    </div>

    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 mb-10 relative">
        
        <div class="flex flex-col md:flex-row justify-between items-end mb-6 gap-4 z-20 relative">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-indigo-50 rounded-lg"><i data-lucide="cylinder" class="w-6 h-6 text-indigo-600"></i></div>
                <div>
                    <h3 class="font-bold text-xl text-slate-800">Stock Visualization</h3>
                    <p class="text-xs text-slate-400">Comparing OS WH vs OS PNE</p>
                </div>
            </div>
            
            <div class="w-full md:w-1/4">
                <label class="text-xs font-bold text-slate-500 uppercase ml-1 mb-1 block">Filter By Part Description</label>
                <select id="partFilter" class="w-full" multiple placeholder="Select parts..." autocomplete="off"></select>
            </div>
        </div>

        <div id="cylinderChart" style="width: 100%; height: 550px;"></div>
    </div>

    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="p-5 bg-slate-50 border-b border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <h3 class="font-bold text-lg text-slate-700 flex items-center gap-2">
                <i data-lucide="database" class="w-5 h-5 text-blue-500"></i>
                Finish Good Data
            </h3>
            
            <div class="flex flex-wrap gap-3 w-full md:w-auto">
                <div class="relative flex-grow md:flex-grow-0 md:w-80">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4"></i>
                    <input type="text" id="tableSearch" 
                           class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-semibold text-sm" 
                           placeholder="Search...">
                </div>
                <button onclick="downloadTableCSV()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg font-bold text-sm flex items-center gap-2 shadow-md">
                    <i data-lucide="download" class="w-4 h-4"></i> CSV
                </button>
            </div>
        </div>

  <div class="overflow-x-auto max-h-[600px] overflow-y-auto relative shadow-inner">
    <table class="w-full text-sm text-left border-collapse">
        
        <thead class="bg-slate-800 text-white uppercase font-bold text-xs sticky top-0 z-10 shadow-md">
                    <tr>
                        <th class="px-3 py-4">Model</th>
                        <th class="px-3 py-4">Part No (FG)</th>
                        <th class="px-3 py-4">ERP (FG)</th>
                        <th class="px-3 py-4">Description</th>
                        <th class="px-3 py-4 text-center bg-slate-700">WH IN</th>
                        <th class="px-3 py-4 text-center bg-slate-700">PNE IN</th>
                        <th class="px-3 py-4 text-center bg-slate-700">PNE OUT</th>
                        <th class="px-3 py-4 text-center bg-slate-700">WH OUT</th>
                        <th class="px-3 py-4 text-center bg-cyan-600 text-white">OS WH</th>
                        <th class="px-3 py-4 text-center bg-purple-600 text-white">OS PNE</th>
                        <th class="px-3 py-4 text-center bg-indigo-900 text-white">TOTAL OS</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="divide-y divide-slate-100 font-medium text-slate-600">
                    <tr><td colspan="11" class="text-center py-10">Loading Data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let globalData = [];
    let chartInstance = null;
    let filterControl = null;
    
    // Config: How often to refresh data (in milliseconds)
    const REFRESH_RATE = 1000; 

    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        initFilter();
        fetchData(); // Initial Fetch
        startLiveUpdate(); // Start the loop
        
        // Table Search Logic
        const searchInput = document.getElementById('tableSearch');
        searchInput.addEventListener('keyup', (e) => {
            const term = e.target.value.toLowerCase();
            // We now filter based on the dropdown selection, not just globalData
            const currentDropdownValues = filterControl.getValue();
            let baseData = globalData;
            if(currentDropdownValues.length > 0){
                 baseData = globalData.filter(item => currentDropdownValues.includes(item.erp_code_fg));
            }
            const filtered = filterDataByTerm(baseData, term);
            renderTable(filtered);
        });
    });

    function startLiveUpdate() {
        setInterval(() => {
            fetchData(true); 
        }, REFRESH_RATE);
    }

    function initFilter() {
        filterControl = new TomSelect("#partFilter", {
            maxItems: null,
            valueField: 'erp_code_fg',
            labelField: 'full_desc', // Search in the full description
            searchField: ['full_desc', 'part_no'],
            plugins: ['remove_button'],
            render: {
                // Dropdown List: Show FULL Description
                option: function(data, escape) {
                    return `<div>${escape(data.full_desc)}</div>`;
                },
                // Selected Item (Tag): Show ONLY Part No (Short)
                item: function(data, escape) {
                    return `<div>${escape(data.part_no)}</div>`;
                }
            },
            onChange: function(values) {
                // 1. Determine the "Active" data based on dropdown
                const activeData = values.length === 0 
                    ? globalData 
                    : globalData.filter(item => values.includes(item.erp_code_fg));
                
                // 2. Update Chart
                renderChart(activeData);

                // 3. Update Table (applying current search term to the NEW active data)
                const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
                const tableData = filterDataByTerm(activeData, searchTerm);
                renderTable(tableData);
            }
        });
    }

    async function fetchData(isBackgroundUpdate = false) {
        try {
            const response = await fetch('dashboard_fg_api.php');
            const result = await response.json();
            
            if(result.success) {
                globalData = result.data;
                
                if (!isBackgroundUpdate) {
                    updateFilterOptions(globalData);
                }
                
                // 1. Apply Dropdown Filter First
                const currentDropdownValues = filterControl.getValue();
                let activeData = globalData;
                if(currentDropdownValues.length > 0){
                     activeData = globalData.filter(item => currentDropdownValues.includes(item.erp_code_fg));
                }
                
                // 2. Render Chart with Active Data
                renderChart(activeData, !isBackgroundUpdate);

                // 3. Apply Search Filter on top of Active Data for Table
                const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
                let tableData = activeData; 
                if (searchTerm) {
                    tableData = filterDataByTerm(activeData, searchTerm);
                }
                renderTable(tableData);
            }
        } catch(err) {
            console.error("Fetch Error:", err);
        }
    }

    function filterDataByTerm(data, term) {
        if (!term) return data;
        return data.filter(row => 
            (row.description || '').toLowerCase().includes(term) ||
            (row.part_no_fg || '').toLowerCase().includes(term) ||
            (row.model || '').toLowerCase().includes(term)
        );
    }

    function updateFilterOptions(data) {
        filterControl.clearOptions();
        // Prepare data with separate fields for display
        const options = data.map(item => ({
            erp_code_fg: item.erp_code_fg,
            part_no: item.part_no_fg, // Short Label
            full_desc: `${item.description} (${item.part_no_fg})` // Long Search Label
        }));
        filterControl.addOptions(options);
    }

    function renderChart(data, animate = true) {
        const categories = data.map(item => item.description);
        
        const whValues = data.map(item => ({
            y: item.os_wh,
            custom: item 
        }));
        
        const pneValues = data.map(item => ({
            y: item.os_pne,
            custom: item
        }));

        if (chartInstance) {
            const currentCategories = chartInstance.xAxis[0].categories;
            const categoriesChanged = JSON.stringify(currentCategories) !== JSON.stringify(categories);

            if (categoriesChanged) {
                chartInstance.xAxis[0].setCategories(categories, false);
                chartInstance.xAxis[0].update({
                    min: null,
                    max: null
                }, false);
            }

            chartInstance.series[0].setData(whValues, false, animate);
            chartInstance.series[1].setData(pneValues, false, animate);
            chartInstance.redraw(animate);
            return;
        }

        chartInstance = Highcharts.chart('cylinderChart', {
            chart: {
                type: 'cylinder', 
                options3d: {
                    enabled: true,
                    alpha: 5,   
                    beta: 0,    
                    depth: 50,
                    viewDistance: 25
                },
                backgroundColor: 'transparent'
            },
            exporting: { enabled: false }, 
            title: { text: '' },
            tooltip: {
                animation: false, 
                hideDelay: 0,     
                shared: true,
                useHTML: true,
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                borderColor: '#e2e8f0',
                borderRadius: 8,
                padding: 12,
                shadow: true,
                formatter: function () {
                    const pointData = this.points[0].point.custom; 
                    
                    const whPoint = this.points.find(p => p.series.name === 'OS WH');
                    const pnePoint = this.points.find(p => p.series.name === 'OS PNE');
                    const whVal = whPoint ? whPoint.y : 0;
                    const pneVal = pnePoint ? pnePoint.y : 0;
                    
                    return `
                        <div class="text-left font-sans">
                            <div class="font-bold text-lg text-slate-800 mb-0.5">${pointData.model}</div>
                            <div class="font-mono text-xs font-semibold text-blue-600 mb-0.5">${pointData.part_no_fg}</div>
                            <div class="font-mono text-xs text-slate-500 mb-3">${pointData.erp_code_fg}</div>
                            
                            <div class="grid grid-cols-[40px_1fr] gap-1 text-sm">
                                <div class="font-bold text-cyan-600">WH:</div>
                                <div class="font-bold text-slate-700 text-right">${whVal}</div>
                                
                                <div class="font-bold text-purple-600">PNE:</div>
                                <div class="font-bold text-slate-700 text-right">${pneVal}</div>
                            </div>
                        </div>
                    `;
                }
            },
            xAxis: {
                categories: categories,
                min: null, 
                max: null,
                scrollbar: { enabled: true }, 
                labels: {
                    rotation: -45, 
                    style: { fontSize: '10px', fontWeight: 'bold' }
                },
                gridLineWidth: 0
            },
            yAxis: {
                title: { text: 'Stock Quantity' },
                gridLineDashStyle: 'Dash'
            },
            plotOptions: {
                series: {
                    depth: 30, 
                    grouping: true,
                    pointPadding: 0,
                    groupPadding: 0.1,
                    borderWidth: 0
                }
            },
            colors: [
                { linearGradient: { x1:0, x2:0, y1:0, y2:1 }, stops: [[0, '#22d3ee'], [1, '#0891b2']] }, 
                { linearGradient: { x1:0, x2:0, y1:0, y2:1 }, stops: [[0, '#a78bfa'], [1, '#7c3aed']] } 
            ],
            series: [
                { name: 'OS WH', data: whValues },
                { name: 'OS PNE', data: pneValues }
            ],
            credits: { enabled: false }
        });
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        if(data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-slate-400">No data found.</td></tr>`;
            return;
        }

        let html = '';
        data.forEach((row, idx) => {
            const bgClass = idx % 2 === 0 ? 'bg-white' : 'bg-slate-50';
            html += `
            <tr class="${bgClass} hover:bg-blue-50 transition-colors border-b border-slate-100">
                <td class="px-3 py-3 font-bold text-slate-700">${row.model}</td>
                <td class="px-3 py-3 font-bold text-blue-600">${row.part_no_fg}</td>
                <td class="px-3 py-3 font-mono text-slate-500">${row.erp_code_fg}</td>
                <td class="px-3 py-3 text-xs text-slate-500 truncate max-w-[200px]" title="${row.description}">${row.description}</td>
                
                <td class="px-3 py-3 text-center text-slate-500">${row.wh_in}</td>
                <td class="px-3 py-3 text-center text-slate-500">${row.pne_in}</td>
                <td class="px-3 py-3 text-center text-slate-500">${row.pne_out}</td>
                <td class="px-3 py-3 text-center text-slate-500">${row.wh_out}</td>
                
                <td class="px-3 py-3 text-center font-bold text-cyan-600 bg-cyan-50 border-l border-cyan-100">${row.os_wh}</td>
                <td class="px-3 py-3 text-center font-bold text-purple-600 bg-purple-50 border-l border-purple-100">${row.os_pne}</td>
                <td class="px-3 py-3 text-center font-black text-indigo-700 bg-indigo-50 border-l border-indigo-100">${row.total_os}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function downloadTableCSV() {
        if (globalData.length === 0) return;
        const headers = ["Model", "Part No", "Description", "OS WH", "OS PNE", "Total OS"];
        let csv = "data:text/csv;charset=utf-8," + headers.join(",") + "\n";
        
        globalData.forEach(row => {
            const line = [row.model, row.part_no_fg, `"${row.description}"`, row.os_wh, row.os_pne, row.total_os];
            csv += line.join(",") + "\n";
        });
        
        const link = document.createElement("a");
        link.href = encodeURI(csv);
        link.download = "FG_Stock_" + new Date().toISOString().slice(0,10) + ".csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php include 'layout/footer.php'; ?>