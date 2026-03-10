// --- Globally Scoped Variables ---
// We define these here so they are accessible by all functions,
// including the global deletePneInScan function.
let allTableRows = [];
let currentPage = 1;
const rowsPerPage = 10;
let tableBody = null;

/**
 * Filters and re-renders the history table based on current filter values and page.
 */
function filterTable() {
    if (!tableBody) tableBody = document.getElementById("scanHistoryTableBody");
    const searchInput = document.getElementById("historySearchInput");
    const modelFilter = document.getElementById("modelFilter");
    const dateFilterInput = document.getElementById("dateFilterInput");

    if (!tableBody || !searchInput || !modelFilter || !dateFilterInput) return;

    const searchTerm = searchInput.value.toLowerCase().trim();
    const modelTerm = modelFilter.value.toLowerCase();
    const dateTerm = dateFilterInput.value; // Format: "YYYY-MM-DD"

    // Remove highlight from all rows *before* filtering
    allTableRows.forEach(row => row.classList.remove('latest-scan-row'));

    // Filter the original rows
    const filteredRows = allTableRows.filter(row => {
        const rowText = row.innerText.toLowerCase();
        
        // Model Filter
        const modelCell = row.cells[8]; // Column 8 is Model
        const modelText = modelCell ? modelCell.innerText.toLowerCase() : "";
        const matchesModel = (modelTerm === "") ? true : modelText === modelTerm;

        // Search Filter
        const matchesSearch = rowText.includes(searchTerm);

        // Date Filter (using Prod Date)
        const dateCell = row.cells[2]; // "Prod Date" is the 3rd cell (index 2)
        const dateCellText = dateCell ? dateCell.innerText.trim() : ""; // Gets "30/10/2025"
        
        let rowDate_YYYYMMDD = "";
        if (dateCellText.length === 10) { // e.g., "30/10/2025"
             const parts = dateCellText.split('/');
             if (parts.length === 3) {
                 rowDate_YYYYMMDD = `${parts[2]}-${parts[1]}-${parts[0]}`; // "2025-10-30"
             }
        }
        const matchesDate = (dateTerm === "") ? true : rowDate_YYYYMMDD === dateTerm;

        return matchesSearch && matchesModel && matchesDate;
    });

    // --- Pagination Logic ---
    const totalRows = filteredRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);

    if (currentPage > totalPages) currentPage = totalPages > 0 ? totalPages : 1;
    if (currentPage < 1) currentPage = 1;

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const rowsToShow = filteredRows.slice(start, end);

    // --- Render Table Rows ---
    tableBody.innerHTML = ""; // Clear existing rows
    if (rowsToShow.length === 0) {
         const message = allTableRows.length > 0 ? "No scans match your search/filter." : `<i data-lucide="inbox" class="w-12 h-12 mx-auto text-gray-300"></i>No recent scans found.`;
         tableBody.innerHTML = `<tr><td colspan="13" class="text-center py-10 text-gray-500">${message}</td></tr>`;
         if (allTableRows.length === 0 && typeof lucide !== 'undefined') lucide.createIcons();
    } else {
        rowsToShow.forEach(row => tableBody.appendChild(row));
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    renderPaginationControls(totalPages);
}

/**
 * Renders the pagination buttons.
 * @param {number} totalPages - The total number of pages.
 */
function renderPaginationControls(totalPages) {
    const paginationContainer = document.getElementById("paginationControls");
    if (!paginationContainer) return;

    paginationContainer.innerHTML = "";
    if (totalPages <= 1) return;

    const createButton = (text, pageNum, isDisabled = false, isActive = false) => {
        const button = document.createElement("button");
        button.innerHTML = text;
        button.disabled = isDisabled;
        // === THEME CHANGED (CYAN) ===
        button.className = `px-3 py-1.5 border rounded-lg shadow-sm text-sm transition-colors duration-150 ${
            isActive
                ? "bg-cyan-600 text-white border-cyan-600 cursor-default"
                : isDisabled
                    ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                    : "bg-white text-gray-700 border-gray-300 hover:bg-cyan-50 hover:border-cyan-300"
        }`;
        if (!isDisabled && !isActive) {
            button.onclick = () => {
                currentPage = pageNum;
                filterTable();
            };
        }
        return button;
    };

    const controlsWrapper = document.createElement("div");
    controlsWrapper.className = "flex items-center space-x-1";

    controlsWrapper.appendChild(createButton('&laquo; Prev', currentPage - 1, currentPage === 1));

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (currentPage <= 3) endPage = Math.min(5, totalPages);
    if (currentPage > totalPages - 3) startPage = Math.max(1, totalPages - 4);

    if (startPage > 1) {
         controlsWrapper.appendChild(createButton('1', 1));
         if (startPage > 2) {
             const dots = document.createElement('span');
             dots.textContent = '...';
             dots.className = 'px-2 py-1.5 text-gray-500 text-sm';
             controlsWrapper.appendChild(dots);
         }
    }

    for (let i = startPage; i <= endPage; i++) {
        controlsWrapper.appendChild(createButton(i, i, false, i === currentPage));
    }

     if (endPage < totalPages) {
         if (endPage < totalPages - 1) {
             const dots = document.createElement('span');
             dots.textContent = '...';
             dots.className = 'px-2 py-1.5 text-gray-500 text-sm';
             controlsWrapper.appendChild(dots);
         }
         controlsWrapper.appendChild(createButton(totalPages, totalPages));
    }

    controlsWrapper.appendChild(createButton('Next &raquo;', currentPage + 1, currentPage === totalPages));
    paginationContainer.appendChild(controlsWrapper);
}


// --- Main DOMContentLoaded Event Listener ---
document.addEventListener("DOMContentLoaded", () => {
  const $ = (id) => document.getElementById(id);

  // --- Main Elements ---
  const startScanBtn = $("startScanBtn");
  const stopScanBtn = $("stopScanBtn");
  const scanCount = $("scanCount");
  const scanFormContainer = $("scanFormContainer");
  const scanForm = $("scanForm");
  const scanInput = $("scanInput");
  const scanResult = $("scanResult");
  
  // --- Table & Filter Elements ---
  tableBody = $("scanHistoryTableBody"); // Initialize global var
  const searchInput = $("historySearchInput");
  const modelFilter = $("modelFilter");
  const refreshBtn = $("refreshBtn");
  const dateFilterInput = $("dateFilterInput");

  // --- Manual Entry Modal Elements ---
  const manualEntryBtn = $("manualEntryBtn");
  const manualEntryModal = $("manualEntryModal");
  const closeManualModal = $("closeManualModal");
  const cancelManualEntry = $("cancelManualEntry");
  const fetchDetailsBtn = $("fetchDetailsBtn");
  const submitManualEntry = $("submitManualEntry");
  const manualDetailsContainer = $("manualDetailsContainer");
  const manualStatusMessage = $("manualStatusMessage");
  const manualTicketId = $("manual_ticket_id");
  const manualErpCodeFg = $("manual_erp_code_fg");

  let fetchedManualData = null; 

  // 1. --- Start Scan Button ---
  if (startScanBtn) {
    startScanBtn.addEventListener("click", () => {
      scanFormContainer.classList.remove("hidden");
      scanInput.disabled = false;
      scanInput.focus();
      startScanBtn.disabled = true;
      stopScanBtn.disabled = false;
      scanCount.textContent = "0";
      showScanResult("Scanner activated. Ready to scan.", "info");
    });
  }

  // 2. --- Stop Scan Button ---
  if (stopScanBtn) {
    stopScanBtn.addEventListener("click", () => {
      scanFormContainer.classList.add("hidden");
      scanInput.disabled = true;
      startScanBtn.disabled = false;
      stopScanBtn.disabled = true;
      scanCount.textContent = "0";
      showScanResult("Scan Standby", "info");
    });
  }

  // 3. --- Handle Scan Form Submission ---
  if (scanForm) {
    scanForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const qrData = scanInput.value.trim();
      if (qrData === "") {
        showScanResult("Please scan or enter a QR code.", "error");
        return;
      }
      processScan(qrData);
    });
  }

  // 4. --- Function to process scan data (from scanner OR manual) ---
  function processScan(qrData) {
      if (scanInput) {
          scanInput.disabled = true;
          scanInput.value = "Processing...";
      }
      showScanResult("Processing scan...", "info");

      // === FILE PATH UPDATED ===
      fetch("scan_handler_pne_in.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ qr_data: qrData }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showScanResult(data.message, "success");
            handleNewScanRow(data.scanData);
            updateScanCount(true);
            toggleManualModal(false);
          } else {
            if (typeof showNotification === "function") {
                showNotification(data.message || "An unknown error occurred.", "error");
            }
            showScanResult(data.message, "error");
            if (manualStatusMessage) {
                showManualStatus(data.message, "error");
            }
          }
        })
        .catch((error) => {
          console.error("Fetch Error:", error);
           if (typeof showNotification === "function") {
               showNotification("Connection error. Could not process scan.", "error");
           }
          showScanResult("Connection error.", "error");
          if (manualStatusMessage) {
             showManualStatus("Connection error.", "error");
          }
        })
        .finally(() => {
          if (scanInput) {
              scanInput.disabled = stopScanBtn.disabled;
              scanInput.value = ""; 
              if (!stopScanBtn.disabled) scanInput.focus();
          }
          if (fetchDetailsBtn) fetchDetailsBtn.disabled = false;
          if (submitManualEntry) submitManualEntry.disabled = (fetchedManualData === null);
        });
  }

  // 5. --- Function to add a new row ---
  function handleNewScanRow(scanData) {
    allTableRows.forEach(row => row.classList.remove('latest-scan-row'));
    const currentLatestDOM = tableBody.querySelector('.latest-scan-row');
    if (currentLatestDOM) {
        currentLatestDOM.classList.remove('latest-scan-row');
    }

    const newRow = document.createElement("tr");
    newRow.setAttribute("data-log-id", scanData.log_id);
    newRow.classList.add('latest-scan-row'); 
    
    newRow.innerHTML = `
        <td class="px-4 py-4 text-gray-600 whitespace-nowrap">${scanData.scan_time}</td>
        <td class="px-4 py-4 whitespace-nowrap"><span class="font-semibold text-blue-700">${scanData.unique_no}</span></td>
        <td class="px-4 py-4 text-gray-500 whitespace-nowrap">${scanData.prod_date}</td>
        <td class="px-4 py-4 text-gray-800 font-medium">${scanData.part_name}</td>
        <td class="px-4 py-4 text-gray-800 font-medium">${scanData.part_no_FG}</td>
        <td class="px-4 py-4 text-sm font-mono text-indigo-700">${scanData.erp_code_FG}</td>
        <td class="px-4 py-4 text-gray-800 font-medium">${scanData.part_no_B}</td>
        <td class="px-4 py-4 text-sm font-mono text-gray-600">${scanData.erp_code_B}</td>
        <td class="px-4 py-4 text-gray-800 font-medium">${scanData.model}</td>
        <td class="px-4 py-4 text-gray-700">${scanData.prod_area}</td>
        <td class="px-4 py-4"><span class="font-bold text-emerald-700">${scanData.quantity}</span></td>
        <td class="px-4 py-4 text-gray-700">${scanData.released_by_display}</td>
        <td class="px-4 py-4"><button onclick="deletePneInScan(${scanData.log_id}, this)" class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold"><i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1" style="vertical-align: middle;"></i> Delete</button></td>
    `;

    allTableRows.unshift(newRow); // Add to the beginning of master list
    currentPage = 1; // Go to page 1
    filterTable(); // Re-render table

    // Re-apply class *after* filterTable() has redrawn the DOM
    const addedRowInDOM = tableBody.querySelector(`tr[data-log-id="${scanData.log_id}"]`);
    if (addedRowInDOM) {
        addedRowInDOM.classList.add('latest-scan-row'); 
        if (typeof lucide !== 'undefined') {
            lucide.createIcons(); 
        }
    }
  }

  // 6. --- Function to show temporary scan result ---
  function showScanResult(message, type = "info") {
    if (!scanResult) return;
    let colorClass = "text-gray-500";
    if (type === "success") colorClass = "text-green-600";
    if (type === "error") colorClass = "text-red-600";
    scanResult.textContent = message;
    scanResult.className = `mt-4 text-center h-5 font-semibold ${colorClass} transition-all duration-300`;
  }

  // 7. --- Function to update scan count ---
  function updateScanCount(increment = false) {
    if (!scanCount) return;
    let currentCount = parseInt(scanCount.textContent, 10);
    if (increment) {
      scanCount.textContent = currentCount + 1;
    } else if (currentCount > 0) {
      scanCount.textContent = currentCount - 1;
    }
  }

  // 8. --- History Table Search & Filter Init ---
  if (tableBody) {
      allTableRows = Array.from(tableBody.getElementsByTagName("tr"));
      allTableRows = allTableRows.filter(row => !row.querySelector('td[colspan="13"]'));
  }
  
  // Initial render
  filterTable();

  // Add listeners
  if (searchInput) searchInput.addEventListener("input", () => { currentPage = 1; filterTable(); }); 
  if (modelFilter) modelFilter.addEventListener("change", () => { currentPage = 1; filterTable(); });
  if (dateFilterInput) dateFilterInput.addEventListener("change", () => { currentPage = 1; filterTable(); });

  if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
          if (searchInput) searchInput.value = "";
          if (modelFilter) modelFilter.selectedIndex = 0;
          if (dateFilterInput) dateFilterInput.value = ""; 
          currentPage = 1;
          filterTable();
          if (typeof showNotification === "function") {
              showNotification("Filters cleared.", "info");
          }
      });
  }

  // 9. --- Manual Entry Modal Logic ---
  const toggleManualModal = (show) => {
    if (!manualEntryModal) return;
    if (show) {
      manualEntryModal.classList.remove("opacity-0", "invisible");
      manualEntryModal.querySelector('div').classList.add("scale-100");
      manualTicketId.focus();
    } else {
      manualEntryModal.classList.add("opacity-0", "invisible");
      manualEntryModal.querySelector('div').classList.remove("scale-100");
      manualTicketId.value = "";
      manualErpCodeFg.value = "";
      manualDetailsContainer.classList.add("hidden");
      submitManualEntry.disabled = true;
      fetchedManualData = null;
      showManualStatus("Enter Ticket ID and ERP Code to fetch details.", "info");
    }
  };

  const showManualStatus = (message, type = "info") => {
      if (!manualStatusMessage) return;
      let colorClass = "text-gray-500";
      if (type === "success") colorClass = "text-green-600";
      if (type === "error") colorClass = "text-red-600";
      manualStatusMessage.textContent = message;
      manualStatusMessage.className = `mt-4 text-center h-5 font-semibold ${colorClass}`;
  };

  if (manualEntryBtn) manualEntryBtn.addEventListener("click", () => toggleManualModal(true));
  if (closeManualModal) closeManualModal.addEventListener("click", () => toggleManualModal(false));
  if (cancelManualEntry) cancelManualEntry.addEventListener("click", () => toggleManualModal(false));
  if (manualEntryModal) {
      manualEntryModal.addEventListener('click', function(e) {
          if (e.target === this) toggleManualModal(false);
      });
  }

  // --- Fetch Details Button Click ---
  if (fetchDetailsBtn) {
    fetchDetailsBtn.addEventListener("click", () => {
        const unique_no = manualTicketId.value.trim();
        const erp_code_fg = manualErpCodeFg.value.trim();

        if (!unique_no || !erp_code_fg) {
            showManualStatus("Please enter both Ticket ID and ERP Code.", "error");
            return;
        }

        fetchDetailsBtn.disabled = true;
        fetchDetailsBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Fetching...';
        showManualStatus("Fetching details...", "info");
        fetchedManualData = null;
        submitManualEntry.disabled = true;
        manualDetailsContainer.classList.add("hidden");

        // === FILE PATH UPDATED ===
        fetch("fetch_ticket_pne_in.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ unique_no: unique_no, erp_code_fg: erp_code_fg })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchedManualData = data.data;
                $("manual_prod_date").textContent = fetchedManualData.prod_date;
                $("manual_part_name").textContent = fetchedManualData.part_name;
                $("manual_part_no_fg").textContent = fetchedManualData.part_no_FG;
                $("manual_part_no_b").textContent = fetchedManualData.part_no_B;
                $("manual_erp_code_b").textContent = fetchedManualData.erp_code_B;
                $("manual_model").textContent = fetchedManualData.model;
                $("manual_prod_area").textContent = fetchedManualData.prod_area;
                $("manual_quantity").textContent = fetchedManualData.quantity + " pcs";
                $("manual_released_by").textContent = fetchedManualData.released_by_display;
                manualDetailsContainer.classList.remove("hidden");
                submitManualEntry.disabled = false;
                showManualStatus("Details fetched successfully. Ready to submit.", "success");
            } else {
                showManualStatus(data.message, "error");
            }
        })
        .catch(err => {
            console.error(err);
            showManualStatus("A connection error occurred.", "error");
        })
        .finally(() => {
            fetchDetailsBtn.disabled = false;
            fetchDetailsBtn.innerHTML = '<i data-lucide="search" class="w-5 h-5"></i> <span>Fetch Ticket Details</span>';
            if(typeof lucide !== 'undefined') lucide.createIcons();
        });
    });
  }
  
  // --- Submit Manual Entry Button Click ---
  if (submitManualEntry) {
      submitManualEntry.addEventListener("click", () => {
          if (!fetchedManualData) {
              showManualStatus("No data fetched. Please fetch details first.", "error");
              return;
          }
          
          // Build the *exact* QR string
          const qrString = [
              fetchedManualData.prod_date,
              fetchedManualData.unique_no,
              fetchedManualData.erp_code_FG,
              fetchedManualData.released_by_ids,
              fetchedManualData.quantity
          ].join('|');
          
          showManualStatus("Submitting...", "info");
          submitManualEntry.disabled = true;
          submitManualEntry.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
          
          processScan(qrString); // Call the same function as the scanner

          setTimeout(() => {
              if (submitManualEntry) {
                 submitManualEntry.disabled = (fetchedManualData === null);
                 // === TEXT CHANGED ===
                 submitManualEntry.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> <span>Submit Scan In</span>';
                 if(typeof lucide !== 'undefined') lucide.createIcons();
              }
          }, 1500);
      });
  }

}); // End DOMContentLoaded


/**
 * 10. --- Delete Scan Function (Global) ---
 * This is global so it can be called by onclick.
 */
function deletePneInScan(logId, buttonElement) {
  const notify = (message, type) => {
      if (typeof showNotification === "function") {
          showNotification(message, type);
      } else {
          alert(message);
      }
  };

  const password = prompt("To delete this scan record, please enter the admin password:");

  if (password === null) return; // User cancelled
  if (password === "") {
    notify("Password cannot be empty.", "warning");
    return;
  }

  buttonElement.disabled = true;
  buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';

  const formData = new FormData();
  formData.append("log_id", logId);
  formData.append("password", password);

  // === FILE PATH UPDATED ===
  fetch("delete_pne_in_scan.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        notify("Scan record deleted successfully.", "success");
        
        // --- PAGINATION UPDATE FOR DELETE ---
        // This now works because allTableRows and filterTable are global
        const rowIndex = allTableRows.findIndex(r => r.getAttribute('data-log-id') == logId);
        if (rowIndex > -1) {
            allTableRows.splice(rowIndex, 1);
        }
        filterTable(); // Re-render the current page
        // --- END PAGINATION UPDATE ---
        
      } else {
        notify(data.message, "error");
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete';
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    })
    .catch((error) => {
      console.error("Delete error:", error);
      notify("An error occurred. Please try again.", "error");
      buttonElement.disabled = false;
      buttonElement.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete';
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}

// Add inside DOMContentLoaded
const downloadCsvBtn = $("downloadCsvBtn");

if (downloadCsvBtn) {
    downloadCsvBtn.addEventListener("click", () => {
        const search = $("historySearchInput").value;
        const model = $("modelFilter").value;
        const date = $("dateFilterInput").value;
        
        const params = new URLSearchParams({
            search: search,
            model: model,
            date: date
        });
        
        window.location.href = `export/pne_in.php?${params.toString()}`;
    });
}
