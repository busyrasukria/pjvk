<?php
// stl.php - The Dashboard with Glassmorphism Modal
require_once 'db.php';
session_start();

// Initialize the variables
$selected_model = isset($_GET['model']) ? $_GET['model'] : null;
$selected_line  = isset($_GET['line']) ? $_GET['line'] : null;

// Clear session if requested
if(isset($_GET['clear'])) { 
    session_destroy(); 
    header("Location: stl.php"); 
    exit; 
}

include 'layout/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STL Form Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0fdfa; } 
        
        .glass-modal {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(13, 148, 136, 0.25);
        }
        
        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-slide-up { animation: slideUpFade 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

        .role-card { transition: all 0.2s ease; }
        .role-card:hover { border-color: #2dd4bf; background-color: #f0fdf4; }
        .role-card:has(input:checked) {
            border-color: #0d9488;
            background-color: #ccfbf1;
            box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.2);
            transform: scale(1.02);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-teal-50 min-h-screen text-slate-800 flex flex-col">

<div class="w-full px-4 md:px-8 py-8 pb-32 flex-grow">

    <div class="bg-gradient-to-r from-slate-800 via-teal-800 to-teal-600 rounded-xl shadow-md px-6 py-4 mb-8 text-white animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
          <div>
            <h2 class="text-lg font-semibold flex items-center space-x-2">
              <i class="fas fa-clipboard-list"></i>
              <span>Stock Transfer List</span>
            </h2>
            <p class="text-teal-100 text-xs md:text-sm mt-1">Select a model and line</p>
          </div>
          <a href="stl_dashboard.php"
            class="bg-white text-teal-700 px-4 py-2.5 rounded-lg font-medium flex items-center gap-2 shadow-sm hover:shadow-md hover:bg-blue-50 transition-all duration-300 text-sm">
            <i class="fas fa-arrow-left text-teal-700 text-base"></i>
            <span>Back to dashboard</span>
          </a>
        </div>
    </div>

    <div class="flex justify-center mt-10 px-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 w-full max-w-[1600px]">
            <?php
            try {
                // COMBINE LINE 1 AND LINE 2 SO NO LINES ARE MISSING
                $query = "
                    SELECT DISTINCT model, line1 AS line FROM master_stl WHERE line1 IS NOT NULL AND line1 != '' AND model IS NOT NULL
                    UNION
                    SELECT DISTINCT model, line2 AS line FROM master_stl WHERE line2 IS NOT NULL AND line2 != '' AND model IS NOT NULL
                ";
                $stmt = $pdo->query($query);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $structure = [];
                foreach($data as $row) {
                    $m = strtoupper(trim($row['model']));
                    $l = trim($row['line']);
                    if($m && $l) {
                        if(!isset($structure[$m])) $structure[$m] = [];
                        if(!in_array($l, $structure[$m])) $structure[$m][] = $l; 
                    }
                }

                ksort($structure, SORT_NATURAL);

                if (empty($structure)) {
                    echo "<div class='col-span-full text-center p-10 bg-white border border-dashed border-slate-300 rounded-2xl text-slate-500'>
                            <i class='fas fa-exclamation-circle text-2xl mb-2'></i><br>
                            No data found in master_stl.
                          </div>";
                }

                foreach($structure as $dbModel => $lines):
                    natsort($lines);
                    
                    // FIXED IMAGE MATCHER - Now matches the correct J-Codes from your database
                    $dbModelUpper = strtoupper(trim($dbModel));
                    $displayModel = $dbModelUpper;
                    $imagePath = 'uploads/model/default_car.jpg'; // Fallback
                    
                    if (strpos($dbModelUpper, 'J59K') !== false || strpos($dbModelUpper, 'CX30') !== false) {
                        $displayModel = 'J59K - CX30';
                        $imagePath = 'uploads/model/cx30.png';
                    } 
                    elseif (strpos($dbModelUpper, 'J72A') !== false || strpos($dbModelUpper, 'CX5') !== false) {
                        $displayModel = 'J72A - CX5';
                        $imagePath = 'uploads/model/cx5.png';
                    } 
                    elseif (strpos($dbModelUpper, 'J72K') !== false || strpos($dbModelUpper, 'CX8') !== false) {
                        $displayModel = 'J72K - CX8';
                        $imagePath = 'uploads/model/cx8.png';
                    }
            ?>
            <div class="group relative bg-white border-2 border-slate-100 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl hover:border-teal-400 transition-all duration-500 flex flex-col h-full">
                
                <div class="h-56 w-full bg-gradient-to-b from-slate-50 to-white flex items-center justify-center relative overflow-hidden">
                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($displayModel); ?>" class="object-contain h-full w-full opacity-90 group-hover:scale-110 transition-transform duration-700 ease-out">
                    <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-slate-900/80 to-transparent p-4 pt-12">
                        <h3 class="text-3xl font-bold text-white tracking-wide drop-shadow-md"><?php echo htmlspecialchars($displayModel); ?></h3>
                    </div>
                </div>

                <div class="p-6 flex-grow bg-white flex flex-col justify-start relative z-10">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="h-4 w-1 bg-teal-500 rounded-full"></span>
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-wider">Select Line</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <?php foreach($lines as $lineNum): ?>
                            <a href="stl.php?model=<?php echo urlencode($dbModel); ?>&line=<?php echo urlencode($lineNum); ?>" 
                               class="py-3 px-3 bg-teal-50 hover:bg-teal-600 hover:text-white text-teal-700 border border-teal-100 rounded-xl text-sm font-bold text-center transition-all duration-200 shadow-sm hover:shadow-md flex items-center justify-center gap-2 group/btn">
                                <span>Line <?php echo $lineNum; ?></span>
                                <i class="fas fa-chevron-right text-xs opacity-50 group-hover/btn:translate-x-1 transition-transform"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; 
            } catch (PDOException $e) {
                echo "<div class='col-span-full text-red-500'>Database Error: " . $e->getMessage() . "</div>";
            }
            ?>
        </div>
    </div>
    
    <?php if ($selected_model && $selected_line): ?>
    <div id="authModal" class="fixed inset-0 z-[999] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="window.location.href='stl.php'"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-3xl text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg glass-modal opacity-0 translate-y-4" id="modalPanel">
                    
                    <div class="absolute right-0 top-0 pr-4 pt-4 block">
                        <button type="button" onclick="window.location.href='stl.php'" class="rounded-md bg-transparent text-slate-400 hover:text-red-500 focus:outline-none transition-colors">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>

                    <div class="p-8">
                        <div class="text-center mb-8">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-teal-100 mb-4 shadow-inner">
                                <i class="fas fa-user-shield text-3xl text-teal-600"></i>
                            </div>
                            <h3 class="text-2xl font-bold leading-6 text-slate-800" id="modal-title">Security Check</h3>
                            <div class="mt-2">
                                <p class="text-sm text-slate-500">
                                    Accessing <span id="displayModelText" class="font-black text-teal-700"></span> 
                                    <span class="mx-1 text-slate-300">|</span> 
                                    Line <span id="displayLine" class="font-black text-teal-700"></span>
                                </p>
                            </div>
                        </div>

                        <form action="stl_form.php" method="POST" id="loginForm" class="space-y-6">
                            <input type="hidden" name="model" id="inputModel" value="<?php echo htmlspecialchars($selected_model); ?>">
                            <input type="hidden" name="line" id="inputLine" value="<?php echo htmlspecialchars($selected_line); ?>">

                            <div class="space-y-3">
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Select Your Role</label>
                                
                                <label class="role-card relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none">
                                    <input type="radio" name="role" value="PROD_REQ" class="sr-only" checked>
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-bold text-slate-900">Production (Ordering)</span>
                                            <span class="mt-1 flex items-center text-xs text-slate-500">Step 1: Create Request</span>
                                        </span>
                                    </span>
                                    <i class="fas fa-clipboard-list text-xl text-blue-500 self-center"></i>
                                </label>

                                <label class="role-card relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none">
                                    <input type="radio" name="role" value="MPL_SUP" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-bold text-slate-900">MPL (Supply)</span>
                                            <span class="mt-1 flex items-center text-xs text-slate-500">Step 2: Prepare Stock</span>
                                        </span>
                                    </span>
                                    <i class="fas fa-boxes text-xl text-amber-500 self-center"></i>
                                </label>

                                <label class="role-card relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none">
                                    <input type="radio" name="role" value="PROD_RECV" class="sr-only">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span class="block text-sm font-bold text-slate-900">Production (Receiving)</span>
                                            <span class="mt-1 flex items-center text-xs text-slate-500">Step 3: Confirm Receipt</span>
                                        </span>
                                    </span>
                                    <i class="fas fa-check-double text-xl text-green-500 self-center"></i>
                                </label>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Access PIN</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fas fa-lock text-slate-400"></i>
                                    </div>
                                    <input type="password" name="password" required 
                                           class="block w-full rounded-xl border-0 py-3.5 pl-10 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-teal-600 sm:text-sm sm:leading-6 tracking-[0.5em] font-bold text-center bg-white/50" 
                                           placeholder="••••">
                                </div>
                            </div>

                            <div>
                                <button type="submit" class="flex w-full justify-center rounded-xl bg-teal-600 px-3 py-4 text-sm font-bold text-white shadow-lg hover:bg-teal-500 transition-all transform active:scale-95">
                                    ENTER SYSTEM <i class="fas fa-arrow-right ml-2 self-center"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    const modal = document.getElementById('authModal');
    const backdrop = document.getElementById('modalBackdrop');
    const panel = document.getElementById('modalPanel');

    function openAccessModal(displayModel, dbModel, line) {
        if(!modal) return;
        document.getElementById('inputModel').value = dbModel; 
        document.getElementById('inputLine').value = line;
        
        document.getElementById('displayModelText').innerText = displayModel; 
        document.getElementById('displayLine').innerText = line;

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'translate-y-4');
            panel.classList.add('animate-slide-up');
        }, 10);
    }

    <?php if ($selected_model && $selected_line): 
        // Fixed Modal trigger mapping based on DB J-codes
        $triggerDisplay = strtoupper(trim($selected_model));
        if (strpos($triggerDisplay, 'J59K') !== false || strpos($triggerDisplay, 'CX30') !== false) $triggerDisplay = 'J59K - CX30';
        elseif (strpos($triggerDisplay, 'J72A') !== false || strpos($triggerDisplay, 'CX5') !== false) $triggerDisplay = 'J72A - CX5';
        elseif (strpos($triggerDisplay, 'J72K') !== false || strpos($triggerDisplay, 'CX8') !== false) $triggerDisplay = 'J72K - CX8';
    ?>
        document.addEventListener("DOMContentLoaded", function() {
            openAccessModal("<?php echo htmlspecialchars($triggerDisplay); ?>", "<?php echo htmlspecialchars($selected_model); ?>", "<?php echo htmlspecialchars($selected_line); ?>");
        });
    <?php endif; ?>
</script>

<?php include 'layout/footer.php'; ?>
</body>
</html>