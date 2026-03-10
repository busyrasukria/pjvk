<?php
require_once 'db.php';
include 'layout/header.php';

// -------------------- MESSAGES --------------------
$message = '';
$message_type = ''; // 'success' or 'error'

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = 'Ticket printed successfully!';
        $message_type = 'success';
    } elseif ($_GET['status'] === 'part_added') {
        $message = 'New part added successfully!';
        $message_type = 'success';
    } elseif ($_GET['status'] === 'error' && isset($_GET['message'])) {
        $message = htmlspecialchars($_GET['message']);
        $message_type = 'error';
    }
}
// -------------------- END MESSAGES --------------------


// -------------------- FETCH MODELS --------------------
try {
    $stmt = $pdo->prepare("SELECT DISTINCT model FROM master ORDER BY model");
    $stmt->execute();
    $models = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $models = [];
    $message = "Error fetching models: " . $e->getMessage();
    $message_type = 'error';
}

// -------------------- PAGE-SPECIFIC DATA --------------------
$selectedModel = $_GET['model'] ?? null;
$parts = [];
$runners = [];
$ticketHistory = [];

// --- Fetch all manpower into a lookup map ---
$manpowerMap = [];
try {
    $stmt_mp = $pdo->prepare("SELECT emp_id, name, nickname FROM manpower");
    $stmt_mp->execute();
    foreach ($stmt_mp->fetchAll() as $mp) {
        $manpowerMap[$mp['emp_id']] = $mp;
    }
} catch (PDOException $e) {
    $message = "Error fetching manpower: " . $e->getMessage();
    $message_type = 'error';
}

// --- Helper function to get display names ---
function getManpowerDisplayNames($released_by_string, $manpowerMap) {
    $emp_ids = array_map('trim', explode(',', $released_by_string));
    $names = [];
    if(empty($released_by_string)) return '-';
    
    foreach ($emp_ids as $id) {
        if (isset($manpowerMap[$id])) {
            $mp = $manpowerMap[$id];
            if (!empty($mp['nickname'])) {
                $names[] = $mp['nickname']; // Use Nickname
            } else {
                $first_name = explode(' ', $mp['name'])[0]; // Use First Name
                $names[] = $first_name;
            }
        } else {
            $names[] = $id; // Fallback if ID not in map
        }
    }
    return implode(' / ', $names);
}


if ($selectedModel) {
    // --- Logic for when a model IS selected ---
    try {
        // Query master table for all fields
        $stmt = $pdo->prepare("SELECT * FROM master WHERE model = ? ORDER BY part_no_FG");
        $stmt->execute([$selectedModel]);
        $parts = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error fetching parts: " . $e->getMessage();
        $message_type = 'error';
    }
    try {
        // Runners are already fetched in $manpowerMap, just need to re-format for the cards
        $runners = $pdo->query("SELECT * FROM manpower ORDER BY name")->fetchAll();
    } catch (PDOException $e) {
        $runners = [];
        $message = "Error fetching runners: " . $e->getMessage();
        $message_type = 'error';
    }
} else {
    // --- Logic for when NO model is selected (main page) ---
    // --- *** UPDATED SQL QUERY FOR FILTERS *** ---
    try {
        // 1. Check for Filters
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $modelFilter = $_GET['model_filter'] ?? null; // <--- NEW: Capture Model
        $params = [];

        // 2. Build Base Query
        $sql = "
            SELECT 
                tt.ticket_id, tt.unique_no, tt.part_name, tt.model, tt.prod_area, 
                tt.quantity, tt.released_by, tt.created_at, tt.shift,
                m.part_no_B, m.erp_code_B, m.part_no_FG, m.erp_code_FG
            FROM transfer_tickets tt
            LEFT JOIN master m ON tt.erp_code_FG = m.erp_code_FG
            WHERE 1=1
        ";

        // 3. Apply Date Filter
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(tt.created_at) BETWEEN :dateFrom AND :dateTo";
            $params[':dateFrom'] = $dateFrom;
            $params[':dateTo'] = $dateTo;
        }

        // 4. Apply Model Filter (NEW)
        if ($modelFilter) {
            $sql .= " AND tt.model = :modelFilter";
            $params[':modelFilter'] = $modelFilter;
        }

        // 5. Ordering and Limits
        $sql .= " ORDER BY tt.ticket_id DESC";
        
        // Only limit if NO filters are applied (to keep page fast)
        if (!$dateFrom && !$modelFilter) {
            $sql .= " LIMIT 100"; 
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ticketHistory = $stmt->fetchAll();

    } catch (PDOException $e) {
        $message = "Error fetching ticket history: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FG Ticket Printer - Warehouse Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css"> 
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen text-gray-800">

  <?php if ($message): ?>
    <div class="notification <?php echo $message_type; ?> show" id="statusNotification">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3"></i>
            <span><?php echo $message; ?></span>
            <button class="ml-4 text-gray-400 hover:text-gray-600" onclick="document.getElementById('statusNotification').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <script>
      setTimeout(() => {
          const el = document.getElementById('statusNotification');
          if (el) {
              el.classList.remove('show');
              setTimeout(() => el.remove(), 300);
          }
      }, 5000);
    </script>
  <?php endif; ?>

  <div class="container mx-auto px-6 py-10">

    <?php if (!$selectedModel): ?>
      
      <div class="bg-gradient-to-r from-blue-800 via-blue-700 to-sky-500 rounded-xl shadow-md px-6 py-4 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
          <div>
            <h2 class="text-lg font-semibold flex items-center space-x-2">
              <span>Transfer Ticket System</span>
            </h2>
            <p class="text-blue-100 text-xs md:text-sm mt-1">
              Select a model to begin creating transfer tickets
            </p>
          </div>
          <button id="addPartBtn"
            class="bg-white text-blue-700 px-4 py-2.5 rounded-lg font-medium flex items-center gap-2 shadow-sm hover:shadow-md hover:bg-blue-50 transition-all duration-300 text-sm">
            <i class="fas fa-plus-circle text-blue-700 text-base"></i>
            <span>Add New Part</span>
          </button>
        </div>
      </div>
      <div class="flex justify-center mt-10 px-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 w-full max-w-[1600px]">
          <?php foreach ($models as $model): ?>
            <?php 
              $fileBase = strtolower(str_replace(' ', '', $model));
              $imageDir = 'uploads/model/';
              $possibleExtensions = ['png', 'jpg', 'jpeg', 'webp'];
              $imagePath = 'uploads/model/default_car.jpg';
              foreach ($possibleExtensions as $ext) {
                if (file_exists($imageDir . $fileBase . '.' . $ext)) {
                  $imagePath = $imageDir . $fileBase . '.' . $ext;
                  break;
                }
              }
            ?>
            <a href="tt.php?model=<?php echo urlencode($model); ?>"
              class="group relative bg-white border-2 border-gray-300 rounded-3xl overflow-hidden shadow-md 
                      hover:shadow-2xl transition-all duration-500 w-full
                      hover:border-blue-400 hover:shadow-[0_0_25px_4px_rgba(59,130,246,0.5)]">
              <div class="h-56 w-full bg-gradient-to-b from-black-50 to-gray-100 flex items-center justify-center overflow-hidden">
                <img src="<?php echo $imagePath; ?>" 
                    alt="<?php echo htmlspecialchars($model); ?>" 
                    class="object-contain h-full w-auto opacity-0 group-hover:scale-105 transition-all duration-700 ease-out"
                    onload="this.style.opacity='1'">
              </div>
              <div class="p-6 text-center">
                <h3 class="text-xl font-bold text-gray-800 mb-2 group-hover:text-blue-600 transition-colors duration-300">
                  <?php echo htmlspecialchars($model); ?>
                </h3>
                <p class="text-gray-500 text-sm">Click to view transfer tickets</p>
              </div>
              <div class="absolute inset-0 bg-blue-800 bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-500 flex items-center justify-center rounded-3xl">
                <span class="text-white text-base font-semibold opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                  View Transfer Tickets <i class="fas fa-arrow-right ml-2"></i>
                </span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      
      <?php if (!empty($ticketHistory) || (isset($_GET['date_from'])) || (isset($_GET['model_filter']))): ?>
        <div class="bg-white/90 backdrop-blur-md rounded-2xl shadow-2xl border border-gray-200 mt-12 overflow-hidden transition-all duration-300 hover:shadow-[0_0_40px_-10px_rgba(37,99,235,0.3)]">
          <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-sky-500 px-6 py-5 flex flex-col xl:flex-row justify-between xl:items-center gap-4 shadow-inner rounded-xl">
            <h3 class="text-xl font-bold text-white flex items-center tracking-wide drop-shadow-sm">
                <i class="fa-solid fa-ticket-simple mr-3 text-yellow-300 text-2xl animate-pulse hover:animate-none"></i>
                <span>Recent Transfer Tickets</span>
            </h3>
            
            <div class="flex flex-wrap items-end gap-3">
                
                <form method="GET" action="tt.php" class="flex items-center gap-2 bg-white/10 p-1.5 rounded-lg border border-white/20">
                    
                    <div class="flex flex-col">
                        <label class="text-[10px] text-blue-50 pl-1 font-semibold uppercase">From</label>
                        <input type="date" name="date_from" 
                               value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                               class="px-2 py-1.5 rounded-md text-xs border border-gray-300 outline-none focus:border-blue-500 text-gray-700 shadow-sm">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-[10px] text-blue-50 pl-1 font-semibold uppercase">To</label>
                        <input type="date" name="date_to" 
                               value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                               class="px-2 py-1.5 rounded-md text-xs border border-gray-300 outline-none focus:border-blue-500 text-gray-700 shadow-sm">
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[10px] text-blue-50 pl-1 font-semibold uppercase">Model</label>
                        <select name="model_filter"
                                class="px-2 py-1.5 rounded-md text-xs border border-gray-300 bg-white outline-none focus:border-blue-500 text-gray-700 shadow-sm min-w-[100px]">
                            <option value="">All Models</option>
                            <?php foreach ($models as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>" 
                                    <?php echo (isset($_GET['model_filter']) && $_GET['model_filter'] === $m) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" 
                            class="h-[34px] mt-4 px-3 bg-blue-800 hover:bg-blue-900 text-white text-xs font-bold rounded-md transition-colors shadow-sm flex items-center">
                        <i class="fas fa-filter mr-1"></i> FILTER
                    </button>
                    
                    <?php if(isset($_GET['date_from']) || isset($_GET['model_filter'])): ?>
                        <a href="tt.php" class="h-[34px] mt-4 flex items-center justify-center px-3 bg-red-500/90 hover:bg-red-600 text-white rounded-md transition-colors shadow-sm" title="Clear Filter">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>

              <div class="relative ml-2">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                <input type="text" id="searchInput" placeholder="Search visible rows..."
                       class="pl-9 pr-4 py-2 rounded-lg text-sm border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none shadow-sm w-40" />
              </div>

              <?php
                // Preserve GET parameters for the export link
                $exportParams = [];
                if(isset($_GET['date_from'])) $exportParams['date_from'] = $_GET['date_from'];
                if(isset($_GET['date_to'])) $exportParams['date_to'] = $_GET['date_to'];
                if(isset($_GET['model_filter'])) $exportParams['model_filter'] = $_GET['model_filter']; // <--- Included Model
                $queryString = http_build_query($exportParams);
              ?>
              <a href="export/ticket_history.php?<?php echo $queryString; ?>" id="exportCsvBtn"
                 class="px-4 py-2 rounded-lg text-sm border border-gray-300 bg-white text-gray-700
                        focus:ring-2 focus:ring-green-500 focus:border-green-500
                        transition-all duration-200 outline-none shadow-sm hover:shadow-md
                        flex items-center gap-2 h-[38px] ml-auto">
                <i class="fa-solid fa-file-csv text-green-600"></i>
                <span>CSV</span>
              </a>
            </div>
            </div>

          <div class="overflow-x-auto">
      
                <table id="scanHistoryTable" class="w-full text-sm">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-4 py-3 text-left">Ticket ID</th>
                  <th class="px-4 py-3 text-left">Date</th>
                  <th class="px-4 py-3 text-left">ERP No (B)</th>
                  <th class="px-4 py-3 text-left">Part No (B)</th>
                  <th class="px-4 py-3 text-left">ERP No (FG)</th>
                  <th class="px-4 py-3 text-left">Part No (FG)</th>
                  <th class="px-4 py-3 text-left">Part Name</th>
                  <th class="px-4 py-3 text-left">Model</th>
                  <th class="px-4 py-3 text-left">Prod Area</th>
                  <th class="px-4 py-3 text-left">Qty</th>
                  <th class="px-4 py-3 text-left">Man Power</th>
                  <th class="px-4 py-3 text-left">Shift</th>
                  <th class="px-4 py-3 text-left">Action</th>
                </tr>
              </thead>

              <tbody id="ticketTableBody" class="bg-white divide-y divide-gray-200">
                <?php if (empty($ticketHistory)): ?>
                    <tr>
                        <td colspan="13" class="px-4 py-8 text-center text-gray-500">
                            No records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ticketHistory as $ticket): ?>
                      <?php
                        $manpower_display = getManpowerDisplayNames($ticket['released_by'], $manpowerMap);
                      ?>
                      <tr>
                        <td class="px-4 py-4 whitespace-nowrap">
                          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 shadow-sm">
                            <i class="fa-solid fa-hashtag mr-1 text-blue-500"></i>
                            <?php echo htmlspecialchars($ticket['unique_no']); ?>
                          </span>
                        </td>
                        <td class="px-4 py-4 text-gray-500 whitespace-nowrap">
                          <i class="fa-regular fa-calendar-days mr-2 text-blue-400"></i>
                          <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                        </td>
                        <td class="px-4 py-4 text-sm font-mono text-gray-600">
                          <?php echo htmlspecialchars($ticket['erp_code_B'] ?? '-'); ?>
                        </td>
                        <td class="px-4 py-4 text-gray-800 font-medium">
                          <?php echo htmlspecialchars($ticket['part_no_B'] ?? '-'); ?> 
                        </td>
                        <td class="px-4 py-4 text-sm font-mono text-indigo-700">
                          <?php echo htmlspecialchars($ticket['erp_code_FG'] ?? '-'); ?>
                        </td>
                        <td class="px-4 py-4 text-gray-800 font-medium">
                          <?php echo htmlspecialchars($ticket['part_no_FG'] ?? '-'); ?> 
                        </td>
                        <td class="px-4 py-4 text-gray-800 font-medium">
                          <?php echo htmlspecialchars($ticket['part_name']); ?>
                        </td>
                        <td class="px-4 py-4 text-gray-800 font-medium">
                          <?php echo htmlspecialchars($ticket['model'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-4 py-4 text-gray-700">
                          <?php echo htmlspecialchars($ticket['prod_area']); ?>
                        </td>
                        <td class="px-4 py-4">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                            <i class="fa-solid fa-boxes-stacked mr-1"></i>
                            <?php echo $ticket['quantity']; ?> pcs
                          </span>
                        </td>
                        <td class="px-4 py-4 text-gray-700">
                          <i class="fa-solid fa-user-helmet-safety text-amber-500 mr-2"></i>
                          <?php echo htmlspecialchars($manpower_display); ?>
                        </td>
                        <td class="px-4 py-4 text-gray-700">
                          <?php echo htmlspecialchars($ticket['shift']); ?>
                        </td>
                        <td class="px-4 py-4 flex items-center gap-2">
                          <button 
                            onclick="reprintTicket('<?php echo $ticket['ticket_id']; ?>', '<?php echo htmlspecialchars($ticket['model']); ?>')"
                            class="bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200">
                            <i class="fa-solid fa-print mr-1"></i> Reprint
                          </button>
                          <button 
                            onclick="deleteTicket('<?php echo $ticket['ticket_id']; ?>', this)"
                            class="bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 rounded-lg shadow-sm transition-all duration-200">
                            <i class="fa-solid fa-trash-can mr-1"></i> Delete
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div id="pagination" class="flex justify-center items-center py-5 bg-gray-50 border-t border-gray-100"></div>
        </div>
      <?php endif; ?>
      
    <?php else: ?>
      
      <div class="bg-gradient-to-r from-blue-800 via-blue-700 to-sky-500 rounded-xl shadow-md px-6 py-5 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
          <div>
            <h2 class="text-lg md:text-xl font-semibold flex items-center space-x-2">
              <span>Create Transfer Ticket</span>
            </h2>
            <p class="text-blue-100 text-xs md:text-sm mt-1">
              Model: <span class="font-semibold text-white"><?php echo htmlspecialchars($selectedModel); ?></span>
            </p>
          </div>
          <a href="tt.php"
            class="bg-white text-blue-700 px-4 py-2.5 rounded-lg font-medium flex items-center gap-2 shadow-sm hover:shadow-md hover:bg-blue-50 transition-all duration-300 text-sm">
            <i class="fas fa-arrow-left text-blue-700 text-base"></i>
            <span>Back to Models</span>
          </a>
        </div>
      </div>
      
      <form id="ticketForm" method="POST" action="print_preview.php" class="grid grid-cols-1 xl:grid-cols-2 gap-8 min-h-screen">
        
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden flex flex-col h-[1250px]">
            <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 flex-shrink-0">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-boxes mr-3"></i> Parts (<?php echo htmlspecialchars($selectedModel); ?>)
                </h3>
            </div>
          <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 overflow-y-auto flex-grow custom-scrollbar">
              
             <?php if ($selectedModel === 'CX5'): ?>
                  <div class="part-group-card md:col-span-2 group bg-gradient-to-br from-blue-50 to-white border-2 border-gray-200 rounded-2xl p-4 cursor-pointer transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:border-purple-500"
                       id="cx5-group-card"
                       data-part-numbers='["KT17-5381X", "KT17-5481X", "KT17-5381Y", "KT17-5481Y"]'>
                      
                      <div class="w-full h-64 bg-white-100 rounded-2xl mb-3 flex items-center justify-center overflow-hidden border">
                          <img src="uploads/parts/AUTO PRINT SET.png" 
                               alt="CX5 Group Set" 
                               class="object-contain w-full h-full group-hover:scale-110 transition-transform duration-500">
                      </div>
                      <h5 class="font-semibold text-purple-700 mb-2 text-center truncate">
                          Select all 4 CX5 Parts
                      </h5>
                      <div class="space-y-1 text-xs text-purple-600 text-center">
                          <p>KT17-5381X, KT17-5481X, KT17-5381Y, KT17-5481Y</p>
                      </div>
                  </div>
              <?php endif; ?>
              <?php foreach ($parts as $part): ?>
                      <div class="part-card group bg-gradient-to-br from-blue-50 to-white border-2 border-gray-200 rounded-2xl p-4 cursor-pointer transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:border-primary-300"
                          data-part-id="<?php echo $part['id']; ?>"
                          data-erp-code-b="<?php echo htmlspecialchars($part['erp_code_B']); ?>"
                          data-part-no-b="<?php echo htmlspecialchars($part['part_no_B']); ?>"
                          data-erp-code="<?php echo htmlspecialchars($part['erp_code_FG']); ?>"
                          data-part-no="<?php echo htmlspecialchars($part['part_no_FG']); ?>"
                          data-part-name="<?php echo htmlspecialchars($part['part_description']); ?>"
                          data-model="<?php echo htmlspecialchars($part['model']); ?>"
                          data-line="<?php echo htmlspecialchars($part['line']); ?>"
                          data-std-qty="<?php echo $part['std_packing']; ?>">
                      <div class="w-full h-64 bg-white-100 rounded-2xl mb-3 flex items-center justify-center overflow-hidden border">
                          <?php if (!empty($part['img_path'])): ?>
                                      <img src="<?php echo htmlspecialchars($part['img_path']); ?>" 
                                          alt="<?php echo htmlspecialchars($part['part_description']); ?>" 
                                          class="object-contain w-full h-full group-hover:scale-110 transition-transform duration-500">
                                  <?php else: ?>
                                      <div class="text-gray-400 text-sm text-center">
                                          <i class="fas fa-image text-2xl mb-1"></i><br>No Image
                                      </div>
                                  <?php endif; ?>
                              </div>
                              <h5 class="font-semibold text-black-700 mb-2 text-center truncate" title="<?php echo htmlspecialchars($part['part_description']); ?>">
                                  <?php echo htmlspecialchars($part['part_description']); ?>
                              </h5>
                              <div class="space-y-1 text-xs text-black-600">
                                  <div class="flex items-center justify-between">
                                      <span class="font-medium text-black-500">Part No (B):</span>
                                      <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($part['part_no_B'] ?? '-'); ?></span>
                                  </div>
                                   <div class="flex items-center justify-between">
                                      <span class="font-medium text-black-500">Part No (FG):</span>
                                      <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($part['part_no_FG']); ?></span>
                                  </div>
                                  <div class="flex items-center justify-between">
                                      <span class="font-medium text-black-500">ERP No (FG):</span>
                                      <span class="font-semibold text-primary-600"><?php echo htmlspecialchars($part['erp_code_FG']); ?></span>
                                  </div>
                                  <div class="flex items-center justify-between">
                                      <span class="font-medium text-black-500">Std Qty:</span>
                                      <span class="font-bold text-success-600 bg-success-50 px-2 py-0.5 rounded-lg"><?php echo $part['std_packing']; ?></span>
                                  </div>
                              </div>
                          </div>
              <?php endforeach; ?>
          </div>
        </div>
        
 <div class="flex flex-col gap-6">
          
          <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden flex flex-col h-[500px]">
              <div class="bg-gradient-to-r from-orange-600 to-red-500 px-6 py-4 text-white font-bold text-lg flex items-center gap-4">
                  <div class="flex items-center flex-shrink-0">
                      <i class="fas fa-users mr-3"></i>
                      <span>Manpower</span>
                  </div>
                  
<div class="relative flex-grow">
                      <input type="text" id="manpowerSearch" 
                             placeholder="Search nickname..."
                             class="w-full px-4 py-1.5 rounded-lg text-sm font-normal text-gray-800 bg-white/90 focus:bg-white
                                    placeholder-gray-500 shadow-inner
                                    focus:outline-none focus:ring-2 focus:ring-yellow-300 transition-all">
                      <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                  </div>
                  
                  <span id="manpowerCount" class="ml-auto bg-white/20 px-3 py-1 rounded-full text-sm flex-shrink-0">0/5</span>
              </div>
<div class="p-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 overflow-y-auto flex-grow custom-scrollbar align-content-start">    <?php foreach ($runners as $runner): ?>
    
<div class="runner-card group relative bg-gradient-to-br from-yellow-400 to-orange-500 rounded-2xl p-3 cursor-pointer text-center text-white font-bold
                border-2 border-yellow-300 overflow-hidden flex flex-col items-center justify-start h-36 sm:h-40"
         data-emp-id="<?php echo htmlspecialchars($runner['nickname']); ?>"data-emp-id="<?php echo htmlspecialchars($runner['nickname']); ?>"
         data-runner-name="<?php echo htmlspecialchars($runner['name']); ?>">
        
        <div class="w-20 h-20 rounded-full mx-auto mb-2 flex items-center justify-center overflow-hidden
                    bg-white/25 group-hover:bg-white/50 transition-colors duration-300 shadow-inner">
            <?php if (!empty($runner['img_path'])): ?>
                <img src="<?php echo htmlspecialchars($runner['img_path']); ?>" 
                     alt="<?php echo htmlspecialchars($runner['name']); ?>" 
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <?php else: ?>
                <i class="fas fa-user text-yellow-600 group-hover:text-white text-xl transition-colors duration-300"></i>
            <?php endif; ?>
        </div>
        
        <div class="text-xs sm:text-[8px] font-bold w-full text-center whitespace-normal leading-tight" 
             title="<?php echo htmlspecialchars($runner['name']); ?>">
            <?php echo htmlspecialchars($runner['name']); ?>
        </div>
        
        <div class="text-[9px] sm:text-[10px] font-bold w-full opacity-80 truncate w-full text-center text-black" 
     title="<?php echo htmlspecialchars($runner['nickname']); ?>">
    <?php echo htmlspecialchars($runner['nickname']); ?>
</div>
        
        <div class="text-[9px] sm:text-[10px] opacity-80 truncate w-full text-center" 
             title="<?php echo htmlspecialchars($runner['emp_id']); ?>">
            <?php echo htmlspecialchars($runner['emp_id']); ?>
        </div>
        
    </div>
    <?php endforeach; ?>
</div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                  <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-4 py-3 text-white font-bold text-base flex items-center">
                      <i class="fas fa-calculator mr-2"></i> Quantity (For Batch)
                  </div>
                  <div class="p-4 space-y-3 text-sm">
                      <div class="flex items-center space-x-2 p-2 rounded-xl border-2 border-gray-200 hover:border-indigo-400 transition-colors duration-300">
                          <input type="radio" id="stdQty" name="quantity_type" value="std" class="w-4 h-4 text-indigo-600 peer" checked>
                          <label for="stdQty" class="font-medium text-gray-700 flex-1 peer-checked:text-indigo-600">Use Individual STD Qty</label>
                          <span id="stdQtyValue" class="text-gray-500 bg-gray-100 px-2 py-0.5 rounded-lg">(Select part)</span>
                      </div>
                      <div class="flex items-center space-x-2 p-2 rounded-xl border-2 border-gray-200 hover:border-indigo-400 transition-colors duration-300">
                          <input type="radio" id="customQty" name="quantity_type" value="custom" class="w-4 h-4 text-indigo-600 peer">
                          <label for="customQty" class="font-medium text-gray-700 peer-checked:text-indigo-600">Set Custom Qty (for all)</label>
                      </div>
                      <input type="number" id="customQuantity" name="custom_quantity" min="1" max="999" 
                          class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-100 transition-all duration-300 text-sm" 
                          placeholder="Enter custom quantity" disabled>
                  </div>
              </div>
              <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                  <div class="bg-gradient-to-r from-teal-600 to-emerald-600 px-4 py-3 text-white font-bold text-base flex items-center">
                      <i class="fas fa-calendar-alt mr-2"></i> Date
                  </div>
                  <div class="p-4 space-y-3 text-sm">
                      <div class="flex items-center space-x-2 p-2 rounded-xl border-2 border-gray-200 hover:border-teal-400 transition-colors duration-300">
                          <input type="radio" id="currentDate" name="date_type" value="current" class="w-4 h-4 text-teal-600 peer" checked>
                          <label for="currentDate" class="font-medium text-gray-700 flex-1 peer-checked:text-teal-600">Current Date</label>
                          <span id="currentDateValue" class="font-bold text-teal-600 bg-teal-50 px-2 py-0.5 rounded-lg">
                              <?php echo date('d/m/Y'); ?>
                          </span>
                          </div>
                      <div class="flex items-center space-x-2 p-2 rounded-xl border-2 border-gray-200 hover:border-teal-400 transition-colors duration-300">
                          <input type="radio" id="customDate" name="date_type" value="custom" class="w-4 h-4 text-teal-600 peer">
                          <label for="customDate" class="font-medium text-gray-700 peer-checked:text-teal-600">Custom Date</label>
                      </div>
                      <input type="date" id="customDateInput" name="custom_date_display_only"
                          class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 disabled:bg-gray-100 transition-all duration-300 text-sm"
                          disabled>
                  </div>
              </div>
          </div>
          
          <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-600 to-slate-700 px-6 py-4 text-white font-bold text-lg flex items-center">
                <i class="fas fa-box-open mr-3"></i> Selected Part & Info
            </div>
            
            <div id="singlePartInfo">
                <div class="p-6 flex flex-col md:flex-row gap-6 items-start">
                    <div class="flex flex-col items-center md:items-start md:w-1/3">
                      <div class="w-32 h-32 rounded-2xl overflow-hidden shadow-lg mb-3 bg-gray-100 flex items-center justify-center transition-transform duration-300 hover:scale-105">
                          <img id="selectedPartImage" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="Part Image" class="object-contain w-full h-full">
                      </div>
                      <h4 id="selectedPartName" class="text-center md:text-left text-gray-800 font-bold text-lg max-w-[150px] break-words">
                          No part selected
                      </h4>
                      <span id="selectedModelName" class="text-center md:text-left text-gray-500 text-sm">-</span>
                    </div>
                    
                    <div class="flex-1 grid grid-cols-1 gap-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                                <span class="text-gray-500 font-medium text-xs">Part No (B):</span>
                                <span id="selectedPartNoB" class="block font-semibold text-gray-900">-</span>
                            </div>
                             <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                                <span class="text-gray-500 font-medium text-xs">Part No (FG):</span>
                                <span id="selectedPartNoFG" class="block font-semibold text-gray-900">-</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                                <span class="text-gray-500 font-medium text-xs">ERP Code (B):</span>
                                <span id="selectedErpB" class="block font-semibold text-gray-900">-</span>
                            </div>
                            <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                                <span class="text-gray-500 font-medium text-xs">ERP Code (FG):</span>
                                <span id="selectedErpFG" class="block font-semibold text-indigo-600">-</span>
                            </div>
                        </div>
                         <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                                <span class="text-gray-500 font-medium text-xs">STD Qty:</span>
                                <span id="selectedStdQty" class="block font-bold text-green-600">-</span>
                            </div>
                            <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                                <span class="text-gray-500 font-medium text-xs">Selected Qty:</span>
                                <span id="selectedQuantity" class="block font-semibold text-indigo-600">-</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-3 py-2 rounded-xl shadow-inner">
                            <span class="text-gray-500 font-medium text-xs">Selected Date:</span>
                            <span id="selectedDate" class="block font-semibold text-teal-600">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="multiPartInfo" class="hidden">
                <div id="selectedPartsContainer" class="p-6">
                    <p class="text-gray-500 text-center">No parts selected.</p>
                </div>
            </div>
            
            <div class="px-6 pb-6 border-t border-gray-100">
                <h5 class="text-gray-500 font-medium mt-4">Selected Manpower:</h5>
                <ul id="selectedManpowerList" class="mt-2 text-gray-700 list-disc list-inside max-h-24 overflow-y-auto">
                    <li>-</li>
                </ul>
            </div>
            
            <div class="px-6 pb-6 flex justify-end bg-gray-50 pt-4 rounded-b-2xl">
              <button type="button" id="printButton"
                  class="flex items-center bg-gradient-to-r from-slate-600 to-slate-700 text-white font-bold text-lg py-3 px-6 rounded-2xl shadow-xl hover:scale-105 hover:shadow-2xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                  <i class="fas fa-print mr-2 animate-pulse hover:animate-none"></i> Print Selected
              </button>
            </div>
          </div>

          <input type="hidden" name="runner_ids" id="selectedRunnerIds">
          <input type="hidden" name="released_by" id="releasedBy">
          <input type="hidden" name="quantity" id="finalQuantity">
          <input type="hidden" name="custom_date" id="finalDate">
          <input type="hidden" name="selected_parts_json" id="selectedPartsJson">
          <input type="hidden" name="model" value="<?php echo htmlspecialchars($selectedModel); ?>">
        
        </div>
      </form>
      <?php endif; ?>
  </div>

</div>

 <div id="addPartModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-start justify-center z-50 opacity-0 invisible transition-opacity duration-300 py-10">
    
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl mx-4 transform scale-95 transition-transform duration-300 hover:scale-100 flex flex-col max-h-full">
        
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6 rounded-t-3xl flex items-center justify-between flex-shrink-0">
            <h3 class="text-2xl font-bold text-white flex items-center space-x-3">
                <i class="fas fa-plus-circle text-lg"></i>
                <span>Add New Part</span>
            </h3>
            <button type="button" id="closeModal" class="text-white/80 hover:text-red-600 text-2xl transition-colors duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addPartForm" method="POST" action="add_part.php" class="p-8 overflow-y-auto" enctype="multipart/form-data">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-1 flex flex-col items-center">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 text-center">Item Image</label>
                    <div id="imageContainer" 
                         class="relative w-48 h-36 border-2 border-gray-300 rounded-md shadow-sm flex items-center justify-center cursor-pointer overflow-hidden group hover:shadow-lg transition-all duration-300">
                        <img id="preview" src="#" alt="Preview"
                             class="w-full h-full object-contain transition-transform duration-300" style="display: none;">
                        <div id="uploadPlaceholder" class="text-center text-gray-400 p-4">
                            <i class="fas fa-upload text-3xl mb-2"></i>
                            <p>Click to upload</p>
                        </div>
                        <input type="file" id="imgUpload" name="img_path" accept="image/*"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                               onchange="previewImage(event)">
                    </div>
                    <p class="text-gray-500 text-xs mt-2 text-center">Max 2MB. JPG, PNG, WEBP.</p>
                </div>
                
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Part Number (B)</label>
                      <input type="text" name="part_no_B"
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">ERP Code (B)</label>
                      <input type="text" name="erp_code_B"
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                     <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Part Number (FG) *</label>
                      <input type="text" name="part_no_FG" required
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">ERP Code (FG) *</label>
                      <input type="text" name="erp_code_FG" required
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                    <div class="md:col-span-2">
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Part Description *</label>
                      <input type="text" name="part_description" required
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Type *</label>
                      <select name="stock_type" required
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md bg-white">
                        <option value="">Select Type</option>
                        <option value="FG" selected>FG (Finished Goods)</option>
                        <option value="WIP">WIP (Work In Progress)</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Model *</label>
                      <select name="model" required
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md bg-white">
                        <option value="">Select Model</option>
                        <?php foreach ($models as $modelOption): ?>
                          <option value="<?php echo htmlspecialchars($modelOption); ?>"
                            <?php if ($modelOption === ($selectedModel ?? null)) echo 'selected'; ?> >
                            <?php echo htmlspecialchars($modelOption); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Production Line</label>
                      <input type="text" name="line"
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Location</label>
                      <input type="text" name="location"
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                    <div class="md:col-span-2">
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Standard Packing *</label>
                      <input type="number" name="std_packing" min="1" required
                             class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 shadow-sm hover:shadow-md">
                    </div>
                </div>
            </div>
            
            <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                <button type="button" id="cancelAdd"
                    class="px-6 py-3 bg-red-500 text-white font-semibold rounded-xl hover:bg-red-600 transition-all duration-200 shadow-sm hover:shadow-md">
                    Cancel
                </button>
                <button type="submit"
                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold rounded-xl transition-all duration-200 flex items-center space-x-2 shadow-md hover:shadow-lg">
                    <i class="fas fa-save"></i>
                    <span>Add Part</span>
                </button>
            </div>
        </form>
    </div>
</div>
<script src="assets/tt.js"></script>
<script>
// This supplemental script handles the image preview in the modal
function previewImage(event) {
    const reader = new FileReader();
    const preview = document.getElementById('preview');
    const placeholder = document.getElementById('uploadPlaceholder');
    
    reader.onload = function() {
        if (preview) {
            preview.src = reader.result;
            preview.style.display = 'block';
        }
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }
    
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    } else {
        if (preview) {
            preview.src = '#';
            preview.style.display = 'none';
        }
        if (placeholder) {
            placeholder.style.display = 'block';
        }
    }
}
</script>
<?php include 'layout/footer.php'; ?>
</body>
</html>