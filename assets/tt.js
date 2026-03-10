// This is the complete, correct code for assets/tt.js
document.addEventListener("DOMContentLoaded", () => {
  const $ = id => document.getElementById(id);

  // --- Elements ---
  const addPartBtn = $("addPartBtn");
  const addPartModal = $("addPartModal");
  const closeModal = $("closeModal");
  const cancelAdd = $("cancelAdd");
  const stdQtyRadio = $("stdQty");
  const customQtyRadio = $("customQty");
  const customQuantityInput = $("customQuantity");
  const currentDateRadio = $("currentDate");
  const customDateRadio = $("customDate");
  const customDateInput = $("customDateInput");
  const printButton = $("printButton");
   const manpowerSearchInput = $("manpowerSearch"); 
  
  const stdQtyValueSpan = $("stdQtyValue"); 

  // --- Get BOTH display sections ---
  const singlePartInfo = $("singlePartInfo");
  const multiPartInfo = $("multiPartInfo");
  const selectedPartsContainer = $("selectedPartsContainer"); // The table area
  
  // --- *** NEW: Elements for SINGLE part display *** ---
  const selectedPartImage = $("selectedPartImage");
  const selectedPartName = $("selectedPartName");
  const selectedPartNoB = $("selectedPartNoB");
  const selectedPartNoFG = $("selectedPartNoFG");
  const selectedErpB = $("selectedErpB");
  const selectedErpFG = $("selectedErpFG");
  const selectedModelName = $("selectedModelName");
  const selectedStdQty = $("selectedStdQty");
  const selectedQuantity = $("selectedQuantity");
  const selectedDate = $("selectedDate");
  
  // --- Common Elements ---
  const selectedManpowerList = $("selectedManpowerList");
  const manpowerCount = $("manpowerCount");

  // --- Batch-ready hidden inputs ---
  const hiddenRunnerIds = $("selectedRunnerIds");
  const hiddenQuantity = $("finalQuantity"); // This is the DISPLAY quantity
  const hiddenDate = $("finalDate");
  const hiddenReleasedBy = $("releasedBy");
  const hiddenSelectedPartsJson = $("selectedPartsJson");

  const partCards = document.querySelectorAll(".part-card");
  const runnerCards = document.querySelectorAll(".runner-card");
  const groupCard = $("cx5-group-card");

  const tableBody = $("ticketTableBody");
  const pagination = $("pagination");
  const searchInput = $("searchInput");
  const filterSelect = $("filterSelect");

  const MAX_RUNNERS = 5;
  let selectedParts = []; // Array to hold 1 or many parts
  let selectedRunners = [];

  // -------------------------
  // Modal handling
  // -------------------------
  const toggleModal = show => {
    if (!addPartModal) return;
    if (show) {
      addPartModal.classList.remove("opacity-0", "invisible");
      addPartModal.querySelector('div').classList.add("scale-100"); // Target inner div for scale
    } else {
      addPartModal.classList.add("opacity-0", "invisible");
      addPartModal.querySelector('div').classList.remove("scale-100"); // Target inner div for scale
    }
  };
  if (addPartBtn) addPartBtn.addEventListener("click", () => toggleModal(true));
  if (closeModal) closeModal.addEventListener("click", () => toggleModal(false));
  if (cancelAdd) cancelAdd.addEventListener("click", () => toggleModal(false));
  if (addPartModal) {
      addPartModal.addEventListener('click', function(e) {
          if (e.target === this) {
              toggleModal(false);
          }
      });
  }

    // --- START: Manpower Search Logic ---
  if (manpowerSearchInput) {
      manpowerSearchInput.addEventListener("input", () => {
          const searchTerm = manpowerSearchInput.value.toLowerCase().trim();
          
          runnerCards.forEach(card => {
              // NOTE: Based on tt.php, data-emp-id actually holds the NICKNAME.
              const nickname = (card.dataset.empId || "").toLowerCase();
              
              if (nickname.includes(searchTerm)) {
                  card.style.display = ""; // Show card
              } else {
                  card.style.display = "none"; // Hide card
              }
          });
      });
  }

  // -------------------------
  // Date logic (FUNCTION MOVED TO TOP LEVEL)
  // -------------------------
  const updateDateDisplay = () => {
    if (!currentDateRadio || !customDateInput || !hiddenDate) return; // Add guard clause
    const today = new Date().toISOString().split("T")[0];
    let finalDateValue = currentDateRadio.checked
      ? today
      : (customDateInput.value || today); // Default to today if custom is blank
      
    if(selectedDate) selectedDate.textContent = finalDateValue.split('-').reverse().join('/'); // Format as D/M/Y
    hiddenDate.value = finalDateValue;
  };

  if (currentDateRadio && customDateInput && hiddenDate) {
    currentDateRadio.addEventListener("change", updateDateDisplay);
    customDateRadio.addEventListener("change", () => {
        customDateInput.disabled = false;
        if(!customDateInput.value) {
            customDateInput.value = new Date().toISOString().split("T")[0];
        }
        customDateInput.focus();
        updateDateDisplay();
    });
    customDateInput.addEventListener("input", updateDateDisplay);
    
    // Initialize
    updateDateDisplay(); 
  }

  // -------------------------
  // Quantity logic
  // -------------------------
  const updateQuantityDisplay = () => {
    let totalStdQty = 0;
    selectedParts.forEach(part => {
        totalStdQty += parseInt(part.stdQty) || 0;
    });

    if (stdQtyValueSpan) {
        if (selectedParts.length === 0) {
            stdQtyValueSpan.textContent = '(Select part)';
            stdQtyValueSpan.className = 'text-gray-500 bg-gray-100 px-2 py-0.5 rounded-lg';
        } else if (selectedParts.length === 1) {
             stdQtyValueSpan.textContent = `${totalStdQty} pcs`; // Show single STD
             stdQtyValueSpan.className = 'font-bold text-success-600 bg-success-50 px-2 py-0.5 rounded-lg';
        } else {
            stdQtyValueSpan.textContent = `${totalStdQty} pcs (Total)`; // Show total for batch
            stdQtyValueSpan.className = 'font-bold text-success-600 bg-success-50 px-2 py-0.5 rounded-lg';
        }
    }
    
    // Update the "Selected Qty" field in the SINGLE view
    if (selectedQuantity) {
        if (stdQtyRadio.checked) {
            // Use the first part's stdQty for single view, or total for multi-view (if logic desired)
            selectedQuantity.textContent = (selectedParts.length === 1 ? selectedParts[0].stdQty : totalStdQty) || '-';
        } else {
            selectedQuantity.textContent = customQuantityInput.value || "-";
        }
    }
  };

  if (stdQtyRadio) {
    stdQtyRadio.addEventListener("change", () => {
      customQuantityInput.disabled = true;
      updateQuantityDisplay();
    });
  }

  if (customQtyRadio) {
    customQtyRadio.addEventListener("change", () => {
      customQuantityInput.disabled = false;
      if (!customQuantityInput.disabled) {
        customQuantityInput.focus();
      }
      updateQuantityDisplay();
    });
  }
  
  if (customQuantityInput) {
    customQuantityInput.addEventListener("input", () => {
      if (customQtyRadio.checked) {
        updateQuantityDisplay();
      }
    });
  }

  // -------------------------
  // *** UPDATED: Part Display Functions (Single vs Multi) ***
  // -------------------------
  const updateSinglePartDisplay = () => {
    if (!singlePartInfo) return;
    singlePartInfo.classList.remove('hidden');
    if (multiPartInfo) multiPartInfo.classList.add('hidden');
    const part = selectedParts[0];
    
    if (!part) {
        selectedPartImage.src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        selectedPartName.textContent = 'No part selected';
        selectedModelName.textContent = '-';
        selectedPartNoB.textContent = '-';
        selectedPartNoFG.textContent = '-';
        selectedErpB.textContent = '-';
        selectedErpFG.textContent = '-';
        selectedStdQty.textContent = '-';
    } else {
        selectedPartImage.src = part.imgPath || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        selectedPartName.textContent = part.name;
        selectedModelName.textContent = part.model;
        selectedPartNoB.textContent = part.partNoB || '-';
        selectedPartNoFG.textContent = part.partNo || '-';
        selectedErpB.textContent = part.erpB || '-';
        selectedErpFG.textContent = part.erp || '-';
        selectedStdQty.textContent = part.stdQty;
    }
    updateQuantityDisplay();
    updateDateDisplay(); // This was the line that was breaking
  };

  const updateMultiPartDisplay = () => {
    if (!multiPartInfo || !selectedPartsContainer) return;
    multiPartInfo.classList.remove('hidden');
    if (singlePartInfo) singlePartInfo.classList.add('hidden');
    if (selectedParts.length === 0) {
        selectedPartsContainer.innerHTML = `<p class="text-gray-500 text-center">No parts selected.</p>`;
        return;
    }
    let tableHtml = `
      <div class="overflow-x-auto max-h-64 custom-scrollbar border rounded-lg">
      <table class="w-full text-sm">
          <thead class="bg-gray-100">
              <tr>
                  <th class="px-3 py-2 text-left font-semibold text-gray-600">Part Name</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-600">Part No (B)</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-600">Part No (FG)</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-600">ERP (FG)</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-600">Std Qty</th>
              </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
    `;
    selectedParts.forEach(part => {
        tableHtml += `
            <tr>
                <td class="px-3 py-2 font-medium text-gray-800" title="${part.name}">${part.name}</td>
                <td class="px-3 py-2 text-gray-700">${part.partNoB || '-'}</td>
                <td class="px-3 py-2 text-gray-700">${part.partNo}</td>
                <td class="px-3 py-2 text-indigo-600 font-mono">${part.erp}</td>
                <td class="px-3 py-2 text-green-600 font-bold">${part.stdQty}</td>
            </tr>
        `;
    });
    tableHtml += `</tbody></table></div>`;
    selectedPartsContainer.innerHTML = tableHtml;
    updateQuantityDisplay();
  };

  // -------------------------
  // *** UPDATED: Part selection (Single-select) ***
  // -------------------------
  partCards.forEach(card => {
    card.addEventListener("click", () => {
      partCards.forEach(c => c.classList.remove("selected"));
      if (groupCard) groupCard.classList.remove("selected");
      card.classList.add("selected");
      selectedParts = [{
          id: card.dataset.partId,
          erp: card.dataset.erpCode,
          partNo: card.dataset.partNo,
          erpB: card.dataset.erpCodeB, // NEW
          partNoB: card.dataset.partNoB, // NEW
          name: card.dataset.partName,
          stdQty: card.dataset.stdQty,
          model: card.dataset.model,
          line: card.dataset.line || "",
          imgPath: card.querySelector("img")?.src || ""
      }];
      updateSinglePartDisplay();
    });
  });

  // -------------------------
  // *** UPDATED: Group Card selection (Multi-select) ***
  // -------------------------
  if (groupCard) {
      groupCard.addEventListener("click", () => {
          partCards.forEach(c => c.classList.remove("selected"));
          groupCard.classList.add("selected");
          selectedParts = []; // Clear array first
          const partNumbersToSelect = JSON.parse(groupCard.dataset.partNumbers);
          partCards.forEach(card => {
              const partNo = card.dataset.partNo;
              if (partNumbersToSelect.includes(partNo)) {
                  selectedParts.push({
                      id: card.dataset.partId,
                      erp: card.dataset.erpCode,
                      partNo: card.dataset.partNo,
                      erpB: card.dataset.erpCodeB, // NEW
                      partNoB: card.dataset.partNoB, // NEW
                      name: card.dataset.partName,
                      stdQty: card.dataset.stdQty,
                      model: card.dataset.model,
                      line: card.dataset.line || "",
                      imgPath: card.querySelector("img")?.src || ""
                  });
              }
          });
          updateMultiPartDisplay();
          const infoCard = $("multiPartInfo");
          if(infoCard) {
            infoCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
      });
  }

  // -------------------------
  // Manpower selection
  // -------------------------
  runnerCards.forEach(card => {
    card.addEventListener("click", () => {
      const runnerId = card.dataset.empId;
      const runnerName = card.dataset.runnerName || card.textContent.trim();
      const index = selectedRunners.findIndex(r => r.id === runnerId);
      if (index > -1) {
        selectedRunners.splice(index, 1);
        card.classList.remove("selected");
      } else {
        if (selectedRunners.length >= MAX_RUNNERS) {
          // Use our notification system
          showNotification(`Maximum ${MAX_RUNNERS} manpower allowed`, 'warning');
          return;
        }
        selectedRunners.push({ id: runnerId, name: runnerName });
        card.classList.add("selected");
      }
      if (selectedManpowerList) {
          if (selectedRunners.length > 0) {
            selectedManpowerList.innerHTML = selectedRunners
              .map(runner => `<li class="truncate" title="${runner.name}">${runner.name}</li>`) // Added truncate
              .join("");
          } else {
            selectedManpowerList.innerHTML = "<li>-</li>";
          }
      }
      hiddenRunnerIds.value = selectedRunners.map(r => r.id).join(",");
      hiddenReleasedBy.value = selectedRunners.map(r => r.id).join(",");
      if (manpowerCount) manpowerCount.textContent = `${selectedRunners.length}/${MAX_RUNNERS}`;
    });
  });

  // -------------------------
  // Print Button Logic (AJAX)
  // -------------------------
  if (printButton) {
    printButton.addEventListener("click", () => {
      
      // 1. VALIDATE FORM
      if (selectedParts.length === 0) {
        showNotification("Please select a part (or part group).", 'error');
        return;
      }
      if (selectedRunners.length === 0) {
        showNotification("Please select at least one manpower.", 'error');
        return;
      }
      let quantityType = stdQtyRadio.checked ? 'std' : 'custom';
      let customQty = parseInt(customQuantityInput.value, 10) || 0;
      if (quantityType === 'custom' && customQty <= 0) {
        showNotification("Enter a valid custom quantity.", 'error');
        customQuantityInput.focus();
        return;
      }
      
      // 2. ASK FOR NUMBER OF COPIES
      let numCopiesInput = prompt("How many copies to print?", "1");
      
      if (numCopiesInput === null) {
          return; // User cancelled
      }
      
      let numCopies = parseInt(numCopiesInput, 10);
      
      if (isNaN(numCopies) || numCopies <= 0) {
          showNotification("Please enter a valid number of copies (1 or more).", 'error');
          return;
      }
      
      printButton.disabled = true;
      printButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

      // 3. SET HIDDEN FIELDS
      if (quantityType === 'custom') {
          hiddenQuantity.value = customQty;
      } else {
          // Send 0 or empty for "std"
          hiddenQuantity.value = "0"; 
      }
      const today = new Date().toISOString().split("T")[0];
      hiddenDate.value = currentDateRadio.checked ? today : (customDateInput.value || today);
      hiddenReleasedBy.value = selectedRunners.map(r => r.id).join(",");
      hiddenSelectedPartsJson.value = JSON.stringify(selectedParts);
      
      // 4. PREPARE FORM DATA FOR AJAX
      const formData = new FormData();
      formData.append('selected_parts_json', hiddenSelectedPartsJson.value);
      formData.append('released_by', hiddenReleasedBy.value);
      formData.append('quantity', hiddenQuantity.value); // This is the CUSTOM quantity
      formData.append('custom_date', hiddenDate.value);
      formData.append('model', document.querySelector("input[name='model']").value);
      formData.append('num_copies', numCopies);

      // 5. SEND TO print_ticket.php IN BACKGROUND
      fetch('print_ticket.php', {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // 6. ON SUCCESS, OPEN A SMALL POPUP TO PRINT
              showNotification('Tickets saved. Sending to printer...', 'success');
              
              const printUrl = `print_preview.php?ticket_ids=${data.ticket_ids}&print=1&model=${data.model}`;
              
              // Open a small, barely visible popup
              const printWindow = window.open(printUrl, '_blank', 'width=100,height=100,left=9999,top=9999,scrollbars=no,status=no,toolbar=no,menubar=no');

              if (!printWindow) {
                  showNotification('Print failed. Please allow popups for this site.', 'error');
                  printButton.disabled = false;
                  printButton.innerHTML = '<i class="fas fa-print mr-2"></i> Print Selected';
                  return;
              }
              
              setTimeout(() => {
                  if (printWindow) {
                      printWindow.close();
                  }
              }, 3000); // Close popup after 3 seconds
              
              printButton.disabled = false;
              printButton.innerHTML = '<i class="fas fa-print mr-2"></i> Print Selected';
              
          } else {
              // 7. ON FAILURE, SHOW ERROR
              throw new Error(data.message || 'Unknown error occurred.');
          }
      })
      .catch(error => {
          console.error('Error:', error);
          showNotification(`Error: ${error.message}`, 'error');
          printButton.disabled = false;
          printButton.innerHTML = '<i class="fas fa-print mr-2"></i> Print Selected';
      });
    });
  }

  // -------------------------
  // Table search/filter/pagination
  // -------------------------
  if (tableBody) {
    const allRows = Array.from(tableBody.getElementsByTagName("tr"));
    let currentPage = 1;
    let rowsPerPage = 10; // You can change this
    
    const renderTable = () => {
      const searchValue = searchInput?.value.toLowerCase() || "";
      const filterValue = filterSelect?.value.toLowerCase() || "";
      
      const filteredRows = allRows.filter(row => {
        const text = row.innerText.toLowerCase();
        const matchesSearch = text.includes(searchValue);
        
        // Column index for model was 7 (0-indexed).
        // Since we added Duration at index 2, Model is now at index 8.
        const modelCell = row.cells[8]; 
        const modelText = modelCell ? modelCell.innerText.toLowerCase().trim() : '';
        const matchesFilter = filterValue ? modelText === filterValue : true;
        
        return matchesSearch && matchesFilter;
      });
      
      const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
      if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;
      
      tableBody.innerHTML = "";
      const start = (currentPage - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      filteredRows.slice(start, end).forEach(r => tableBody.appendChild(r));
      
      if (pagination) {
        pagination.innerHTML = "";
        if (totalPages <= 1) return;
        
        const container = document.createElement("div");
        container.className = "flex items-center space-x-2";
        
        const prev = document.createElement("button");
        prev.innerHTML = "&laquo; Prev";
        prev.disabled = currentPage === 1;
        prev.className = "px-3 py-1.5 bg-white border rounded-lg shadow-sm hover:bg-blue-50 text-gray-700 disabled:opacity-50";
        prev.addEventListener("click", () => { if (currentPage > 1) { currentPage--; renderTable(); } });
        container.appendChild(prev);
        
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        if (currentPage <= 3) endPage = Math.min(5, totalPages);
        if (currentPage > totalPages - 3) startPage = Math.max(1, totalPages - 4);
        
        if (startPage > 1) {
            const first = document.createElement("button");
            first.textContent = "1";
            first.className = "px-3 py-1.5 border rounded-lg shadow-sm bg-white text-gray-700 hover:bg-blue-50";
            first.addEventListener("click", () => { currentPage = 1; renderTable(); });
            container.appendChild(first);
            if (startPage > 2) {
                const dots = document.createElement("span");
                dots.textContent = "...";
                dots.className = "px-2 py-1.5 text-gray-500";
                container.appendChild(dots);
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
          const btn = document.createElement("button");
          btn.textContent = i;
          btn.className = `px-3 py-1.5 border rounded-lg shadow-sm ${i === currentPage ? "bg-blue-600 text-white" : "bg-white text-gray-700 hover:bg-blue-50"}`;
          btn.addEventListener("click", () => { currentPage = i; renderTable(); });
          container.appendChild(btn);
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const dots = document.createElement("span");
                dots.textContent = "...";
                dots.className = "px-2 py-1.5 text-gray-500";
                container.appendChild(dots);
            }
            const last = document.createElement("button");
            last.textContent = totalPages;
            last.className = "px-3 py-1.5 border rounded-lg shadow-sm bg-white text-gray-700 hover:bg-blue-50";
            last.addEventListener("click", () => { currentPage = totalPages; renderTable(); });
            container.appendChild(last);
        }
        
        const next = document.createElement("button");
        next.innerHTML = "Next &raquo;";
        next.disabled = currentPage === totalPages;
        next.className = "px-3 py-1.5 bg-white border rounded-lg shadow-sm hover:bg-blue-50 text-gray-700 disabled:opacity-50";
        next.addEventListener("click", () => { if (currentPage < totalPages) { currentPage++; renderTable(); } });
        container.appendChild(next);
        pagination.appendChild(container);
      }
    };
    
    searchInput?.addEventListener("input", () => { currentPage = 1; renderTable(); });
    filterSelect?.addEventListener("change", () => { currentPage = 1; renderTable(); });
    renderTable();
  }
});

// --- Notification System ---
function showNotification(message, type = 'info') {
    document.querySelectorAll('.notification').forEach(n => n.remove());
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${getNotificationIcon(type)} mr-3"></i>
            <span>${message}</span>
            <button class="ml-4 text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}
function getNotificationIcon(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-circle';
        case 'warning': return 'fa-exclamation-triangle';
        default: return 'fa-info-circle';
    }
}

// --- Reprint/Delete Functions ---
/**
 * Opens the auto-print popup for a specific ticket ID.
 * @param {string} ticketId - The ID of the ticket to reprint.
 * @param {string} model - The model, for the redirect URL.
 */
function reprintTicket(ticketId, model) {
    if (!confirm('Are you sure you want to reprint this ticket?')) {
        return;
    }

    showNotification('Sending reprint to printer...', 'info');

    const printUrl = `print_preview.php?ticket_ids=${ticketId}&print=1&model=${model}`;
    
    // Open a small, barely visible popup
    const printWindow = window.open(printUrl, '_blank', 'width=100,height=100,left=9999,top=9999');

    if (!printWindow) {
        showNotification('Print failed. Please allow popups for this site.', 'error');
        return;
    }

    setTimeout(() => {
        if (printWindow) {
            printWindow.close();
        }
    }, 3000); 
}

/**
 * Asks for a password and attempts to delete a ticket.
 * @param {string} ticketId - The ID of the ticket to delete.
 * @param {HTMLElement} buttonElement - The delete button that was clicked.
 */
function deleteTicket(ticketId, buttonElement) {
    const password = prompt("To delete this ticket, please enter the admin password:");

    if (password === null) {
        return; // User cancelled
    }

    if (password === "") {
        showNotification("Password cannot be empty.", "warning");
        return;
    }

    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';

    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('password', password);

    fetch('delete_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Ticket deleted successfully.', 'success');
            
            const row = buttonElement.closest('tr');
            if (row) {
                row.style.transition = 'opacity 0.3s ease-out';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
        } else {
            showNotification(data.message, 'error');
            buttonElement.disabled = false;
            buttonElement.innerHTML = '<i class="fa-solid fa-trash-can mr-1"></i> Delete';
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showNotification('An error occurred. Please try again.', 'error');
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i class="fa-solid fa-trash-can mr-1"></i> Delete';
    });
}
 tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        },
                        success: {
                            50: '#f0fdf4',
                            500: '#22c55e',
                            600: '#16a34a'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out'
                    }
                }
            }
        }