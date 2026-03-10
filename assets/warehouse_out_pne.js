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
  const tableBody = $("scanHistoryTableBody");
  const searchInput = $("historySearchInput");
  const modelFilter = $("modelFilter");
  const refreshBtn = $("refreshBtn");
  const dateFilterInput = $("dateFilterInput");

  // --- NEW: Manual Entry Modal Elements ---
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

  // This will store the fetched data
  let fetchedManualData = null; 
  // --- END: New Elements ---


  // 1. --- Start Scan Button ---
  if (startScanBtn) {
    startScanBtn.addEventListener("click", () => {
      scanFormContainer.classList.remove("hidden");
      scanInput.disabled = false;
      scanInput.focus();
      
      startScanBtn.disabled = true;
      stopScanBtn.disabled = false;
      
      scanCount.textContent = "0"; // Reset count
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
      
      scanCount.textContent = "0"; // Reset count
      showScanResult("Scan Standby", "info");
    });
  }

  // 3. --- Handle Scan Form Submission ---
  if (scanForm) {
    scanForm.addEventListener("submit", function (e) {
      e.preventDefault(); // Stop normal form submission
      const qrData = scanInput.value.trim();

      if (qrData === "") {
        showScanResult("Please scan or enter a QR code.", "error");
        return;
      }
      
      // Call the new processing function
      processScan(qrData);
    });
  }

  // --- NEW: Function to process scan data (from scanner OR manual) ---
  function processScan(qrData) {
      // Disable input while processing
      if (scanInput) {
          scanInput.disabled = true;
          scanInput.value = "Processing...";
      }
      showScanResult("Processing scan...", "info");

      // === FILE PATH UPDATED to match your structure ===
      fetch("scan_handler_pne.php", {
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
            // Add the new scan to the top of the table
            handleNewScanRow(data.scanData); // Use the new handler function
            updateScanCount(true); // Increment count
            
            // If the modal was open, close it
            toggleManualModal(false);
          } else {
            // Use the showNotification function from tt.js for persistent errors
            if (typeof showNotification === "function") {
                showNotification(data.message || "An unknown error occurred.", "error");
            }
            showScanResult(data.message, "error"); // Also show in local message bar
            // If modal was used, show error there
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
          // Re-enable and clear the scan input
          if (scanInput) {
              scanInput.disabled = stopScanBtn.disabled; // Only enable if scanner is "on"
              scanInput.value = ""; 
              if (!stopScanBtn.disabled) scanInput.focus();
          }
          // Re-enable modal buttons
          if (fetchDetailsBtn) fetchDetailsBtn.disabled = false;
          if (submitManualEntry) submitManualEntry.disabled = (fetchedManualData === null); // Only enable if data is loaded
        });
  }


  // 4. --- Function to add a new row ---
  let allTableRows = []; // Keep a reference to all original rows loaded
  let currentPage = 1;
  const rowsPerPage = 10; // Show 10 rows per page

  function handleNewScanRow(scanData) {
    
    // 1. Find and remove the 'latest-scan-row' class from the current latest row(s) in the master list
    allTableRows.forEach(row => row.classList.remove('latest-scan-row'));
    
    // Find and remove from currently displayed rows too, just in case
    const currentLatestDOM = tableBody.querySelector('.latest-scan-row');
    if (currentLatestDOM) {
        currentLatestDOM.classList.remove('latest-scan-row');
    }

    // Create the new row element
    const newRow = document.createElement("tr");
    newRow.setAttribute("data-log-id", scanData.log_id);
    
    // 2. Add the class to the new row.
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
        <td class="px-4 py-4"><button onclick="deleteScan(${scanData.log_id}, this)" class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200 text-xs font-semibold"><i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1" style="vertical-align: middle;"></i> Delete</button></td>
    `;

    // Add the new row to our master list
    allTableRows.unshift(newRow); // Add to the beginning

    // Go to page 1 to show the new row
    currentPage = 1;
    filterTable(); // Re-render table & pagination

    // Ensure icons render if needed (Lucide specific)
    const addedRowInDOM = tableBody.querySelector(`tr[data-log-id="${scanData.log_id}"]`);
    if (addedRowInDOM) {
        // === THIS IS THE FIX ===
        // Re-apply the class *after* filterTable() has redrawn the DOM
        addedRowInDOM.classList.add('latest-scan-row'); 
        // === END FIX ===
        if (typeof lucide !== 'undefined') {
            lucide.createIcons(); 
        }
    }
  }


  // 5. --- Function to show temporary scan result ---
  function showScanResult(message, type = "info") {
    if (!scanResult) return;

    let colorClass = "text-gray-500";
    if (type === "success") colorClass = "text-green-600";
    if (type === "error") colorClass = "text-red-600";

    scanResult.textContent = message;
    scanResult.className = `mt-4 text-center h-5 font-semibold ${colorClass} transition-all duration-300`;
  }

  // 6. --- Function to update scan count ---
  function updateScanCount(increment = false) {
    if (!scanCount) return;
    let currentCount = parseInt(scanCount.textContent, 10);
    if (increment) {
      scanCount.textContent = currentCount + 1;
    } else if (currentCount > 0) {
      scanCount.textContent = currentCount - 1;
    }
  }

  // 7. --- History Table Search, Filter & Pagination Function ---
  
  // Initialize allTableRows when the page loads
  if (tableBody) {
      allTableRows = Array.from(tableBody.getElementsByTagName("tr"));
      // Remove the "no scans" row from this array if it exists
      allTableRows = allTableRows.filter(row => !row.querySelector('td[colspan="13"]'));
  }


  function filterTable() {
      if (!tableBody || !searchInput || !modelFilter || !dateFilterInput) return;

      const searchTerm = searchInput.value.toLowerCase().trim();
      const modelTerm = modelFilter.value.toLowerCase();
      const dateTerm = dateFilterInput.value; // Format: "YYYY-MM-DD"

      // *** This line is important. It removes the highlight from all rows *before* filtering
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

          // --- DATE FILTER LOGIC (using Prod Date) ---
          const dateCell = row.cells[2]; // "Prod Date" is the 3rd cell (index 2)
          const dateCellText = dateCell ? dateCell.innerText.trim() : ""; // Gets "30/10/2025"
          
          // Convert "DD/MM/YYYY" to "YYYY-MM-DD" for comparison
          let rowDate_YYYYMMDD = "";
          if (dateCellText.length === 10) { // e.g., "30/10/2025"
               const parts = dateCellText.split('/');
               if (parts.length === 3) {
                   rowDate_YYYYMMDD = `${parts[2]}-${parts[1]}-${parts[0]}`; // "2025-10-30"
               }
          }
          const matchesDate = (dateTerm === "") ? true : rowDate_YYYYMMDD === dateTerm;
          // --- END DATE FILTER LOGIC ---

          return matchesSearch && matchesModel && matchesDate;
      });

      // --- Pagination Logic ---
      const totalRows = filteredRows.length;
      const totalPages = Math.ceil(totalRows / rowsPerPage);

      // Adjust current page if it's out of bounds
      if (currentPage > totalPages) {
          currentPage = totalPages > 0 ? totalPages : 1;
      }
      if (currentPage < 1) {
          currentPage = 1;
      }

      const start = (currentPage - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      const rowsToShow = filteredRows.slice(start, end);

      // --- Render Table Rows ---
      tableBody.innerHTML = ""; // Clear existing rows
      if (rowsToShow.length === 0) {
           // Show "No results" or "No scans" message
           const message = allTableRows.length > 0 ? "No scans match your search/filter." : `<i data-lucide="inbox" class="w-12 h-12 mx-auto text-gray-300"></i>No recent scans found.`;
           tableBody.innerHTML = `<tr><td colspan="13" class="text-center py-10 text-gray-500">${message}</td></tr>`;
           if (allTableRows.length === 0 && typeof lucide !== 'undefined') lucide.createIcons(); // Render icon if needed
      } else {
          rowsToShow.forEach(row => tableBody.appendChild(row));
          if (typeof lucide !== 'undefined') lucide.createIcons(); // Re-render icons after adding rows
      }


      // --- Render Pagination Controls ---
      renderPaginationControls(totalPages);
  }

  function renderPaginationControls(totalPages) {
      const paginationContainer = $("paginationControls");
      if (!paginationContainer) return;

      paginationContainer.innerHTML = ""; // Clear old controls

      if (totalPages <= 1) return; // No controls needed for 1 page

      const createButton = (text, pageNum, isDisabled = false, isActive = false) => {
          const button = document.createElement("button");
          button.innerHTML = text; // Use innerHTML to allow icons like &laquo;
          button.disabled = isDisabled;
          button.className = `px-3 py-1.5 border rounded-lg shadow-sm text-sm transition-colors duration-150 ${
              isActive
                  ? "bg-orange-600 text-white border-orange-600 cursor-default" // THEME
                  : isDisabled
                      ? "bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                      : "bg-white text-gray-700 border-gray-300 hover:bg-orange-50 hover:border-orange-300" // THEME
          }`;
          if (!isDisabled && !isActive) {
              button.onclick = () => {
                  currentPage = pageNum;
                  filterTable(); // Re-render table and pagination
              };
          }
          return button;
      };

      const controlsWrapper = document.createElement("div");
      controlsWrapper.className = "flex items-center space-x-1";

      // Previous Button
      controlsWrapper.appendChild(createButton('&laquo; Prev', currentPage - 1, currentPage === 1));

      // Page Number Buttons
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


      // Next Button
      controlsWrapper.appendChild(createButton('Next &raquo;', currentPage + 1, currentPage === totalPages));

      paginationContainer.appendChild(controlsWrapper);
  }

  // --- Initial render when page loads ---
  filterTable();

  // Add listeners for search and filter
  if (searchInput) searchInput.addEventListener("input", () => { currentPage = 1; filterTable(); }); 
  if (modelFilter) modelFilter.addEventListener("change", () => { currentPage = 1; filterTable(); });
  if (dateFilterInput) dateFilterInput.addEventListener("change", () => { currentPage = 1; filterTable(); });

  // Refresh Button Logic
  if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
          if (searchInput) searchInput.value = "";
          if (modelFilter) modelFilter.selectedIndex = 0;
          if (dateFilterInput) dateFilterInput.value = ""; 
          currentPage = 1; // Reset to page 1
          filterTable();
          if (typeof showNotification === "function") {
              showNotification("Filters cleared.", "info");
          }
      });
  }


  // 8. --- NEW: Manual Entry Modal Logic ---
  
  // Toggle Modal Visibility
  const toggleManualModal = (show) => {
    if (!manualEntryModal) return;
    if (show) {
      manualEntryModal.classList.remove("opacity-0", "invisible");
      manualEntryModal.querySelector('div').classList.add("scale-100");
      manualTicketId.focus();
    } else {
      manualEntryModal.classList.add("opacity-0", "invisible");
      manualEntryModal.querySelector('div').classList.remove("scale-100");
      
      // Reset form on close
      manualTicketId.value = "";
      manualErpCodeFg.value = "";
      manualDetailsContainer.classList.add("hidden");
      submitManualEntry.disabled = true;
      fetchedManualData = null;
      showManualStatus("Enter Ticket ID and ERP Code to fetch details.", "info");
    }
  };

  // Show status message inside the modal
  const showManualStatus = (message, type = "info") => {
      if (!manualStatusMessage) return;
      
      let colorClass = "text-gray-500";
      if (type === "success") colorClass = "text-green-600";
      if (type === "error") colorClass = "text-red-600";
      
      manualStatusMessage.textContent = message;
      manualStatusMessage.className = `mt-4 text-center h-5 font-semibold ${colorClass}`;
  };

  // --- Add Event Listeners for Modal ---
  if (manualEntryBtn) manualEntryBtn.addEventListener("click", () => toggleManualModal(true));
  if (closeManualModal) closeManualModal.addEventListener("click", () => toggleManualModal(false));
  if (cancelManualEntry) cancelManualEntry.addEventListener("click", () => toggleManualModal(false));
  if (manualEntryModal) {
      manualEntryModal.addEventListener('click', function(e) {
          // If user clicks on the dark background, close modal
          if (e.target === this) {
              toggleManualModal(false);
          }
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

        // === FILE PATH UPDATED to match your structure ===
        fetch("fetch_ticket_pne.php", {
            method: "POST",
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ unique_no: unique_no, erp_code_fg: erp_code_fg })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchedManualData = data.data; // Store the data
                
                // Populate the fields
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
          
          // --- THIS IS THE KEY ---
          // We build the *exact* QR string that scan_handler.php expects.
          // Format: Date|TT ID|ERP CODE FG|RELEASE BY|QTY
          // We use the *raw IDs* for "Released By"
          
          const qrString = [
              fetchedManualData.prod_date,
              fetchedManualData.unique_no,
              fetchedManualData.erp_code_FG,
              fetchedManualData.released_by_ids, // Use the raw IDs (e.g., 'SHS')
              fetchedManualData.quantity
          ].join('|');
          
          showManualStatus("Submitting...", "info");
          submitManualEntry.disabled = true;
          submitManualEntry.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
          
          // Call the *same* function as the scanner
          processScan(qrString);

          // Re-enable button in case processScan fails
          setTimeout(() => {
              if (submitManualEntry) {
                 submitManualEntry.disabled = (fetchedManualData === null);
                 submitManualEntry.innerHTML = '<i data-lucide="save" class="w-5 h-5"></i> <span>Submit Scan Out</span>';
                 if(typeof lucide !== 'undefined') lucide.createIcons();
              }
          }, 1500); // Re-enable after 1.5s
      });
  }


}); // End DOMContentLoaded


// 9. --- Delete Scan Function (Global) ---
function deleteScan(logId, buttonElement) {
  // We must check for showNotification, as it comes from tt.js
  const notify = (message, type) => {
      if (typeof showNotification === "function") {
          showNotification(message, type);
      } else {
          alert(message);
      }
  };

  const password = prompt("To delete this scan record, please enter the admin password:");

  if (password === null) {
    return; // User cancelled
  }

  if (password === "") {
    notify("Password cannot be empty.", "warning");
    return;
  }

  buttonElement.disabled = true;
  buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';

  const formData = new FormData();
  formData.append("log_id", logId);
  formData.append("password", password);

  // === FILE PATH UPDATED to match your structure ===
  fetch("delete_pne_scan.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        notify("Scan record deleted successfully.", "success");
        
        // --- START: PAGINATION UPDATE FOR DELETE ---
        // Remove the row from our master list
        // Note: We defined `allTableRows` in the DOMContentLoaded scope, so we access it here.
        if (typeof allTableRows !== 'undefined' && typeof filterTable === 'function') {
            const rowIndex = allTableRows.findIndex(r => r.getAttribute('data-log-id') == logId);
            if (rowIndex > -1) {
                allTableRows.splice(rowIndex, 1);
            }
            // Re-render the current page
            filterTable();
        } else {
            // Fallback if allTableRows isn't accessible
             buttonElement.closest('tr').remove();
        }
        // --- END: PAGINATION UPDATE FOR DELETE ---
        
      } else {
        notify(data.message, "error");
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete';
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
      }
    })
    .catch((error) => {
      console.error("Delete error:", error);
      notify("An error occurred. Please try again.", "error");
      buttonElement.disabled = false;
      buttonElement.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4 inline-block mr-1"></i> Delete';
      if (typeof lucide !== 'undefined') {
            lucide.createIcons();
      }
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
        
        window.location.href = `export/warehouse_out_pne.php?${params.toString()}`;
    });
}