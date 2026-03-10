<?php
session_start();

/**
 * GAS TRACKING SYSTEM - FULL REAL-TIME EDITION
 * FEATURES: AJAX TABLE + INVENTORY UPDATES, PAGINATION, NO REFRESH
 */

// --- AUTHENTICATION CONFIGURATION ---
$valid_username = 'gas_pjvk';
$valid_password = 'Pjvk123$$';

// 1. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 2. Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
    if ($_POST['username'] === $valid_username && $_POST['password'] === $valid_password) {
        $_SESSION['logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error_msg = "Invalid Username or Password";
    }
}

// 3. Check Session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gas Tracking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #2b0000; background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.8)), url('https://upload.wikimedia.org/wikipedia/commons/thumb/6/64/Mazda_RX-8_Red.jpg/1280px-Mazda_RX-8_Red.jpg'); background-size: cover; background-position: center; overflow: hidden; }
        .login-card { background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); padding: 50px; border-radius: 10px; box-shadow: 0 0 40px rgba(185, 28, 28, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-bottom: 3px solid #dc2626; width: 100%; max-width: 400px; text-align: center; color: white; animation: zoomIn 0.5s ease-out; }
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .login-card h1 { margin-bottom: 5px; font-size: 28px; font-weight: 800; font-style: italic; text-shadow: 0 2px 10px rgba(0,0,0,0.8); text-transform: uppercase; }
        .login-card h1 span { color: #dc2626; } 
        .login-card p { color: #9ca3af; margin-bottom: 30px; font-size: 12px; font-weight: 400; letter-spacing: 3px; text-transform: uppercase; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #f3f4f6; margin-bottom: 8px; letter-spacing: 1px; text-transform: uppercase; }
        input { width: 100%; padding: 15px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 4px; box-sizing: border-box; font-size: 15px; color: white; outline: none; transition: all 0.3s; font-family: 'Poppins'; }
        input:focus { background: rgba(0, 0, 0, 0.8); border-color: #dc2626; box-shadow: 0 0 15px rgba(220, 38, 38, 0.3); }
        button { width: 100%; padding: 15px; background: linear-gradient(90deg, #991b1b 0%, #dc2626 100%); color: white; border: none; border-radius: 4px; font-weight: 800; font-size: 16px; font-style: italic; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; letter-spacing: 1px; margin-top: 10px; text-transform: uppercase; }
        button:hover { transform: skewX(-10deg); box-shadow: 0 0 20px rgba(220, 38, 38, 0.6); }
        .error { background: rgba(127, 29, 29, 0.8); color: #fca5a5; padding: 12px; border-radius: 4px; font-size: 13px; margin-bottom: 20px; text-align: left; border-left: 3px solid #ef4444; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>🏭 GAS <span>TRACKER</span></h1>
        <p>WELCOME TO GAS TRACKING PEPS-JV KEDAH</p>
        <?php if(isset($error_msg)): ?>
            <div class="error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="do_login" value="1">
            <div class="form-group"><label>Operator ID</label><input type="text" name="username" required autocomplete="off"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit">IGNITION START</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// --- SYSTEM LOGIC ---
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // --- FETCH TABLE DATA AND STATS (AJAX) ---
    if ($action === 'fetch_tracking') {
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = 100; 
        $offset = ($page - 1) * $limit;
        
        $search = isset($_POST['search']) ? $_POST['search'] : '';
        $dateRecv = isset($_POST['dateRecv']) ? $_POST['dateRecv'] : '';
        $dateRet = isset($_POST['dateRet']) ? $_POST['dateRet'] : '';

        // 1. Build Table Query
        $sql = "SELECT * FROM gas_tracking WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (serial_no LIKE :s OR id_ticket LIKE :s OR type LIKE :s OR line_name LIKE :s)";
            $params['s'] = "%$search%";
        }
        if (!empty($dateRecv)) {
            $sql .= " AND DATE(receiving_in) = :dr";
            $params['dr'] = $dateRecv;
        }
        if (!empty($dateRet)) {
            $sql .= " AND DATE(return_col) = :dret";
            $params['dret'] = $dateRet;
        }

        // Count Total for Pagination
        $countStmt = $pdo->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $sql));
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        // Fetch Data
        $sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Generate Table HTML
        $html = '';
        if (empty($rows)) {
            $html = '<tr><td colspan="14" style="text-align:center; padding: 20px;">NO DATA FOUND</td></tr>';
        } else {
            foreach ($rows as $row) {
                $days = 0; $statusText = 'ACTIVE'; $statusClass = 'badge-active';
                if($row['receiving_in']) {
                    $start = new DateTime($row['receiving_in']);
                    $end = ($row['return_col']) ? new DateTime($row['return_col']) : new DateTime();
                    $days = $start->diff($end)->days;
                    if ($row['return_col']) { $statusText = 'RETURNED'; $statusClass = 'badge-returned'; }
                    else {
                        if ($days < 20) { $statusText = 'ACTIVE'; $statusClass = 'badge-active'; } 
                        elseif ($days < 30) { $statusText = 'NEAR DELAY'; $statusClass = 'badge-warning'; } 
                        else { $statusText = 'DELAYED'; $statusClass = 'badge-danger'; }
                    }
                }
                
                $html .= '<tr>';
                $html .= '<td data-label="Ticket ID" style="color:#6366f1; font-weight:bold;">' . htmlspecialchars($row['id_ticket']) . '</td>';
                $html .= '<td data-label="Serial No">' . htmlspecialchars($row['serial_no']) . '</td>';
                $html .= '<td data-label="Line" style="font-weight:bold; color: #dc2626;">' . htmlspecialchars($row['line_name'] ?? '') . '</td>';
                $html .= '<td data-label="Gas Type" style="font-weight:600;">' . htmlspecialchars($row['type']) . '</td>';
                $html .= '<td data-label="UOM">' . htmlspecialchars($row['uom']) . '</td>';
                $html .= '<td data-label="Duration" style="font-weight:bold;">' . $days . ' DAYS</td>';
                $html .= '<td data-label="Status"><span class="status-badge ' . $statusClass . '">' . $statusText . '</span></td>';
                $html .= '<td data-label="Receiving">' . ($row['receiving_in'] ? date('d M H:i', strtotime($row['receiving_in'])) : '·') . '</td>';
                $html .= '<td data-label="Line 7">' . ($row['line_7'] ? date('d M H:i', strtotime($row['line_7'])) : '·') . '</td>';
                $html .= '<td data-label="Line 10">' . ($row['line_10'] ? date('d M H:i', strtotime($row['line_10'])) : '·') . '</td>';
                $html .= '<td data-label="Kaizen">' . ($row['kaizen'] ? date('d M H:i', strtotime($row['kaizen'])) : '·') . '</td>';
                $html .= '<td data-label="Gas Lvl 0">' . ($row['gas_level_0'] ? date('d M H:i', strtotime($row['gas_level_0'])) : '·') . '</td>';
                $html .= '<td data-label="Return Col">' . ($row['return_col'] ? date('d M H:i', strtotime($row['return_col'])) : '·') . '</td>';
                $html .= '<td data-label="Actions" style="text-align:center;">';
                $html .= '<button class="btn-sm btn-print" onclick="reprintSingle(\'' . $row['serial_no'] . '\', \'' . $row['id_ticket'] . '\', \'' . $row['type'] . '\', \'' . ($row['receiving_in'] ? date('d-m-y H:i', strtotime($row['receiving_in'])) : '') . '\', \'' . ($row['line_name'] ?? '') . '\')">🖨️</button>';
                $html .= '<button class="btn-sm btn-del" onclick="deleteTracking(\'' . $row['id'] . '\')">🗑️</button>';
                $html .= '</td></tr>';
            }
        }

        // 2. Build Inventory Stats (Real-Time Calculation)
        $stats = ['receiving_in' => 0, 'line_7' => 0, 'line_10' => 0, 'kaizen' => 0, 'gas_level_0' => 0, 'return_col' => 0];
        // Calculate stats based on ALL active items (not just the page/filter)
        $count_res = $pdo->query("SELECT current_location, COUNT(*) as count FROM gas_tracking WHERE status = 'Active' GROUP BY current_location")->fetchAll();
        foreach($count_res as $c) { 
            if(isset($stats[$c['current_location']])) {
                $stats[$c['current_location']] = $c['count']; 
            }
        }

        echo json_encode([
            'html' => $html, 
            'total_pages' => $totalPages, 
            'current_page' => $page,
            'stats' => $stats // Send updated stats to frontend
        ]);
        exit;
    }

    // ADD MASTER
    if ($action === 'add_master') {
        $serial = strtoupper($_POST['serial_no']); 
        $type = strtoupper($_POST['type']); 
        $uom = strtoupper($_POST['uom']);
        $line = isset($_POST['line_assigned']) ? strtoupper($_POST['line_assigned']) : null;
        try {
            $stmt = $pdo->prepare("INSERT INTO master_gas (serial_no, type, uom, line_assigned) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$serial, $type, $uom, $line])) echo json_encode(['status' => 'success', 'message' => 'Item Added!']);
            else echo json_encode(['status' => 'error', 'message' => 'Failed to add.']);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]); }
        exit;
    }
    // DELETE MASTER
    if ($action === 'delete_master') {
        $serial = $_POST['serial_no'];
        try {
            $stmt = $pdo->prepare("DELETE FROM master_gas WHERE serial_no = ?");
            if ($stmt->execute([$serial])) echo json_encode(['status' => 'success', 'message' => 'Item Deleted.']);
            else echo json_encode(['status' => 'error', 'message' => 'Failed to delete.']);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]); }
        exit;
    }
    // EDIT MASTER
    if ($action === 'edit_master') {
        $serial = $_POST['serial_no']; 
        $type = strtoupper($_POST['type']); 
        $uom = strtoupper($_POST['uom']);
        $line = isset($_POST['line_assigned']) ? strtoupper($_POST['line_assigned']) : null;
        try {
            $stmt = $pdo->prepare("UPDATE master_gas SET type = ?, uom = ?, line_assigned = ? WHERE serial_no = ?");
            if ($stmt->execute([$type, $uom, $line, $serial])) echo json_encode(['status' => 'success', 'message' => 'Item Updated.']);
            else echo json_encode(['status' => 'error', 'message' => 'Failed to update.']);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]); }
        exit;
    }
    // DELETE TRACKING
    if ($action === 'delete_tracking') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM gas_tracking WHERE id = ?");
            if ($stmt->execute([$id])) echo json_encode(['status' => 'success', 'message' => 'Record Deleted.']);
            else echo json_encode(['status' => 'error', 'message' => 'Failed to delete record.']);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]); }
        exit;
    }
    // RECEIVE
    if ($action === 'receive' && isset($_POST['scanned_barcode'])) {
        $barcode = strtoupper($_POST['scanned_barcode']);
        $line_name = isset($_POST['line_name']) ? strtoupper($_POST['line_name']) : null;
        try {
            $stmt = $pdo->prepare("SELECT * FROM master_gas WHERE serial_no = :barcode");
            $stmt->execute(['barcode' => $barcode]);
            $master_data = $stmt->fetch();
            if ($master_data) {
                $type = $master_data['type']; 
                $uom = $master_data['uom'];
                if (!$line_name && isset($master_data['line_assigned'])) {
                    $line_name = $master_data['line_assigned'];
                }
                $insertStmt = $pdo->prepare("INSERT INTO gas_tracking (serial_no, type, uom, current_location, status, receiving_in, line_name) VALUES (:barcode, :type, :uom, 'receiving_in', 'Active', NOW(), :line_name)");
                if ($insertStmt->execute(['barcode' => $barcode, 'type' => $type, 'uom' => $uom, 'line_name' => $line_name])) {
                    $last_id = $pdo->lastInsertId();
                    $id_ticket = "PJVK" . str_pad($last_id, 6, '0', STR_PAD_LEFT);
                    $pdo->prepare("UPDATE gas_tracking SET id_ticket = ? WHERE id = ?")->execute([$id_ticket, $last_id]);
                    echo json_encode(['status' => 'success', 'data' => ['id_ticket' => $id_ticket, 'serial_no' => $barcode, 'type' => $type, 'uom' => $uom, 'line_name' => $line_name, 'date_time' => date('d-m-y H:i')]]);
                } else echo json_encode(['status' => 'error', 'message' => 'Insert Failed']);
            } else echo json_encode(['status' => 'not_found', 'message' => 'Serial not in Master']);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]); }
        exit; 
    }
    // TRANSFER
    if ($action === 'transfer' && isset($_POST['ticket_id'])) {
        $ticket_id = strtoupper($_POST['ticket_id']); 
        $target_area = $_POST['target_area'];
        $valid_columns = ['receiving_in', 'line_7', 'line_10', 'kaizen', 'gas_level_0', 'return_col'];
        if (!in_array($target_area, $valid_columns)) { echo json_encode(['status' => 'error', 'message' => 'Invalid area']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT current_location FROM gas_tracking WHERE id_ticket = ?");
            $stmt->execute([$ticket_id]);
            $row = $stmt->fetch();
            if ($row) {
                $current = $row['current_location'];
                if ($current == $target_area) { echo json_encode(['status' => 'warning', 'message' => 'Item already in ' . strtoupper(str_replace('_', ' ', $target_area))]); exit; }
                $sql = "UPDATE gas_tracking SET $target_area = NOW(), current_location = ? WHERE id_ticket = ?";
                if ($pdo->prepare($sql)->execute([$target_area, $ticket_id])) echo json_encode(['status' => 'success', 'message' => "Moved to: " . strtoupper(str_replace('_', ' ', $target_area))]);
                else echo json_encode(['status' => 'error', 'message' => 'Update failed']);
            } else echo json_encode(['status' => 'error', 'message' => 'Ticket ID not found']);
        } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => 'DB Error']); }
        exit;
    }
}

// --- FETCH INITIAL MASTER LIST (Stats loaded via AJAX now) ---
$master_list = [];
try {
    $master_list = $pdo->query("SELECT * FROM master_gas ORDER BY serial_no ASC")->fetchAll();
} catch (PDOException $e) { }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Gas Tracking System</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --glass-bg: rgba(255, 255, 255, 0.95); 
            --glass-border: rgba(255, 255, 255, 0.6);
            --primary-grad: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --success-grad: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            --mazda-red: #dc2626;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #2b0000; background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.9)), url('https://upload.wikimedia.org/wikipedia/commons/thumb/6/64/Mazda_RX-8_Red.jpg/1280px-Mazda_RX-8_Red.jpg'); background-size: cover; background-position: center; background-attachment: fixed; color: #1f2937; margin: 0; padding-bottom: 50px; min-height: 100vh; overflow-x: hidden; width: 100%; text-transform: uppercase; }
        .navbar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 15px 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; position: sticky; top: 0; z-index: 50; border-bottom: 3px solid var(--mazda-red); width: 100%; box-sizing: border-box; }
        .navbar h1 { margin: 0; font-size: 24px; font-weight: 800; font-style: italic; color: #1e293b; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; text-transform: uppercase;}
        .navbar h1 span { color: var(--mazda-red); } 
        .btn-outline { background: white; border: 2px solid #1f2937; color: #1f2937; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: 700; text-decoration: none; display: inline-block; transition: all 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-transform: uppercase; }
        .btn-outline:hover { background: var(--mazda-red); border-color: var(--mazda-red); color: white; transform: translateY(-2px); }
        .container { max-width: 98%; margin: 0 auto; padding: 0 10px; animation: slideUp 0.6s ease-out; box-sizing: border-box; width: 100%; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); padding: 25px; margin-bottom: 25px; box-sizing: border-box; }
        .control-header { font-weight: 800; color: #4b5563; margin-bottom: 15px; text-transform: uppercase; font-size: 14px; letter-spacing: 2px; text-align: center; width:100%; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .control-header::before, .control-header::after { content: ""; height: 3px; width: 50px; background: var(--mazda-red); display: block; transform: skewX(-20deg); }
        .grid-buttons { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; margin-bottom: 20px; }
        .btn-area { flex: 1 1 150px; max-width: 200px; padding: 20px 15px; border: none; border-radius: 4px; font-weight: 800; font-family: 'Poppins'; cursor: pointer; color: white; text-transform: uppercase; transition: all 0.2s; opacity: 0.9; position: relative; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.2); letter-spacing: 1px; font-style: italic; }
        .btn-area:hover { opacity: 1; transform: translateY(-3px) skewX(-2deg); box-shadow: 0 8px 15px rgba(0,0,0,0.3); }
        .btn-area.selected { opacity: 1; transform: scale(1.05); box-shadow: 0 0 0 3px var(--mazda-red); z-index: 10; }
        .btn-blue { background: linear-gradient(135deg, #1e3a8a, #1e40af); } 
        .btn-green { background: linear-gradient(135deg, #064e3b, #065f46); } 
        .btn-purple { background: linear-gradient(135deg, #581c87, #6b21a8); }
        .btn-dark { background: linear-gradient(135deg, #1f2937, #111827); } 
        .scan-controls { display: none; flex-direction: column; align-items: center; gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 1px dashed #cbd5e1; }
        .scanning-active .card:first-child { border: 2px solid var(--mazda-red); animation: pulse-glow 2s infinite; }
        @keyframes pulse-glow { 0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); } }
        .btn-start { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px 50px; font-size: 18px; font-weight: 800; border: none; border-radius: 50px; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); text-transform: uppercase; }
        .btn-start:hover { transform: scale(1.05); }
        .btn-stop { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: white; padding: 15px 50px; font-size: 18px; font-weight: 800; border: none; border-radius: 50px; cursor: pointer; display: none; transition: all 0.3s; box-shadow: 0 4px 15px rgba(185, 28, 28, 0.4); text-transform: uppercase; }
        .btn-stop:hover { transform: scale(1.05); }
        .input-group { display:none; width: 100%; max-width: 600px; justify-content: center; gap: 10px; }
        .scan-input { flex-grow: 1; padding: 20px; font-size: 24px; text-align: center; font-weight: bold; border: 2px solid #10b981; border-radius: 12px; outline: none; background: #ecfdf5; box-shadow: inset 0 2px 5px rgba(0,0,0,0.05); }
        .btn-manual { padding: 0 25px; font-size: 14px; font-weight: 800; background: #374151; color: white; border: none; border-radius: 12px; cursor: pointer; text-transform: uppercase; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .btn-manual:hover { background: #111827; }
        #msg { margin-top: 10px; font-weight: 700; min-height: 20px; font-size: 16px; color: #1f2937;}
        .dashboard-split { display: flex; gap: 20px; align-items: flex-start; width: 100%; box-sizing: border-box; flex-wrap: wrap; }
        .table-side { flex: 1; min-width: 0; } 
        .stats-side { flex: 0 0 300px; display: flex; flex-direction: column; gap: 15px; }
        @media screen and (max-width: 900px) { .stats-side { flex: 1 1 100%; width: 100%; } }
        .inventory-header { background: transparent; display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 900; color: #1e293b; margin-bottom: 5px; padding-left: 5px; text-transform: uppercase; }
        .stat-card-new { background: #f8fafc; border-radius: 8px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: none; border: 1px solid #e2e8f0; transition: 0.3s; border-left-width: 6px; border-left-style: solid; }
        .stat-card-new:hover { transform: translateX(5px); background: #f1f5f9; }
        .stat-card-new h4 { margin: 0; font-size: 13px; color: #64748b; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;}
        .stat-card-new .count { font-size: 26px; font-weight: 900; color: #1e293b; }
        .b-recv { border-left-color: #1f2937; } .b-line7 { border-left-color: #3b82f6; } .b-line10 { border-left-color: #10b981; } .b-kaizen { border-left-color: #cbd5e1; } .b-gas { border-left-color: #cbd5e1; } .b-ret { border-left-color: #cbd5e1; } 
        .table-header-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .table-header-row h3 { color: #374151; font-size: 20px; font-weight: 800; text-transform: uppercase;}
        .search-input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Poppins'; }
        .btn-csv { background: #4b5563; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-family: 'Poppins'; transition: 0.2s; text-transform: uppercase; }
        .btn-csv:hover { background: #1f2937; }
        .table-responsive { overflow-x: auto; width: 100%; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; white-space: nowrap; background: white; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; color: #64748b; padding: 15px 12px; font-weight: 800; text-align: left; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; color: #334155; }
        tr:hover td { background: #f8fafc; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: white; letter-spacing: 0.5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .badge-active { background: linear-gradient(to right, #10b981, #059669); } 
        .badge-warning { background: linear-gradient(to right, #f59e0b, #d97706); } 
        .badge-danger { background: linear-gradient(to right, #ef4444, #dc2626); } 
        .badge-returned { background: linear-gradient(to right, #6366f1, #4f46e5); }
        .modal { display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 100;}
        #modalViewMaster { z-index: 150; } #modalEditMaster, #modalAddMaster { z-index: 200; } 
        .close-btn { cursor: pointer; font-size: 24px; color: #666; font-weight: bold; }
        .form-group { margin-bottom: 15px; } .form-group input, select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; font-family: 'Poppins'; }
        .btn-save { width: 100%; padding: 12px; background: var(--success-grad); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-family: 'Poppins'; text-transform: uppercase;}
        .modal-small { background-color: white; margin: 10% auto; padding: 30px; border-radius: 16px; width: 40%; max-width: 500px; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .modal-large { background-color: white; margin: 2% auto; border-radius: 16px; width: 80%; height: 85vh; position: relative; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background:white;}
        .modal-header h2 { margin:0; text-transform: uppercase; }
        .table-scroll-view { overflow-y: auto; flex-grow: 1; padding: 0 20px 20px 20px; }
        .master-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .master-table th { background-color: #6366f1; color: white; padding: 15px; text-align: left; font-size: 14px; position: sticky; top: 0; z-index: 5; }
        .master-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; background: white; text-transform: uppercase; }
        .btn-sm { padding: 8px 14px; font-size: 12px; border: none; border-radius: 6px; cursor: pointer; color: white; margin-left: 5px; font-weight: bold; }
        .btn-edit { background: #f59e0b; } .btn-del { background: #ef4444; } .btn-print { background: #6366f1; }
        
        /* Pagination Controls */
        #paginationControls { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; align-items: center; }
        .btn-page { background: #e2e8f0; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; color: #475569; }
        .btn-page:hover { background: #cbd5e1; }
        .btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
        #pageInfo { font-weight: bold; font-size: 14px; color: #64748b; }

        @media print {
            @page { size: 90mm 60mm; margin: 0; }
            html, body { width: 90mm !important; height: 60mm !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; background: white !important; text-transform: uppercase !important; }
            .navbar, .container, .modal { display: none !important; }
            #printContainer { display: block !important; position: absolute; left: 0; top: 0; width: 90mm; height: 60mm; margin: 0; padding: 0; background: white; z-index: 9999; }
            .printable-label { width: 90mm; height: 60mm; border: 2px solid black; display: flex; flex-direction: column; background: white; box-sizing: border-box; overflow: hidden; page-break-after: always; break-after: page; }
            .printable-label:last-child { page-break-after: avoid !important; break-after: avoid !important; }
            .row-top { height: 20%; border-bottom: 2px solid black; display: flex; }
            .row-mid { flex-grow: 1; display: flex; align-items: stretch; justify-content: space-between; }
            .row-bot { height: 18%; border-top: 2px solid black; display: flex; align-items: center; }
            .border-right { border-right: 2px solid black; }
            .box-half { width: 50%; height: 100%; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px; color: black; }
            .mid-left { width: 55%; display: flex; flex-direction: column; }
            .label-line { height: 40%; border-bottom: 2px solid black; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 900; color: black; text-transform: uppercase; }
            .label-type { flex-grow: 1; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 900; padding: 2mm; text-align: center; line-height: 1.1; color: black; }
            .qr-box { width: 50%; display: flex; align-items: center; justify-content: center; padding: 2mm; }
            .qr-box img { mix-blend-mode: multiply; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>🏭 GAS <span>TRACKER</span></h1>
    <div style="display:flex; gap:10px;">
        <button class="btn-outline" onclick="openMasterList()">⚙️ MASTER</button>
        <a href="?logout=1" class="btn-outline" style="border-color:#ef4444; color:#ef4444;">LOGOUT</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="control-header">1. SELECT TRACKING AREA</div>
        <div class="grid-buttons">
            <button class="btn-area btn-dark" id="btn_receiving_in" onclick="selectArea('receiving_in', this)">RECEIVING</button>
            <button class="btn-area btn-blue" id="btn_line_7" onclick="selectArea('line_7', this)">LINE 7</button>
            <button class="btn-area btn-green" id="btn_line_10" onclick="selectArea('line_10', this)">LINE 10</button>
            <button class="btn-area btn-purple" id="btn_kaizen" onclick="selectArea('kaizen', this)">KAIZEN</button>
            <button class="btn-area btn-green" id="btn_gas_level_0" onclick="selectArea('gas_level_0', this)">GAS LEVEL 0</button>
            <button class="btn-area btn-blue" id="btn_return_col" onclick="selectArea('return_col', this)">RETURN COL</button>
        </div>

        <div id="scanControls" class="scan-controls">
            <button class="btn-start" id="btnStart" onclick="startScanning()">START SCAN</button>
            <button class="btn-stop" id="btnStop" onclick="stopScanning()">STOP SCAN & PRINT</button>
            <div id="inputGroup" class="input-group">
                <input type="text" id="barcodeInput" class="scan-input" placeholder="SCAN BARCODE..." autocomplete="off">
                <button class="btn-manual" onclick="manualEnter()">KEY-IN</button>
            </div>
            <div id="msg">SELECT AN AREA TO BEGIN.</div>
            <div id="scanCounter" style="display:none; color: #666; font-size:14px; font-weight:bold; margin-top:5px;">ITEMS SCANNED: 0</div>
        </div>
    </div>

    <div class="dashboard-split">
        <div class="card table-side">
            <div class="table-header-row">
                <h3 style="margin:0; margin-right: auto;">📋 LIVE TRACKING</h3>
                <div style="display:flex; gap:5px; align-items:center;"><label style="font-size:12px; font-weight:bold;">RECV:</label><input type="date" id="dateRecv" class="search-input" style="width:130px; padding: 5px;"></div>
                <div style="display:flex; gap:5px; align-items:center;"><label style="font-size:12px; font-weight:bold;">RET:</label><input type="date" id="dateRet" class="search-input" style="width:130px; padding: 5px;"></div>
                <button class="btn-csv" onclick="loadTrackingData(1)" style="background:var(--primary-grad);">🔍 FILTER</button>
                <button class="btn-csv" onclick="downloadCSV()">📥 CSV</button>
                <input type="text" id="tableSearch" class="search-input" placeholder="SEARCH TEXT..." style="width: 150px;">
            </div>

            <div class="table-responsive">
                <table id="trackingTable">
                    <thead>
                        <tr>
                            <th>TICKET ID</th><th>SERIAL NO</th><th>LINE</th><th>GAS TYPE</th><th>UOM</th>
                            <th>DURATION</th><th>STATUS</th>
                            <th>RECEIVING</th><th>LINE 7</th><th>LINE 10</th><th>KAIZEN</th><th>GAS LVL 0</th><th>RETURN COL</th>
                            <th style="text-align:center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="14" style="text-align:center; padding:20px;">Loading Data...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="paginationControls">
                <button class="btn-page" id="btnPrev" onclick="changePage(-1)">PREVIOUS</button>
                <span id="pageInfo">PAGE 1</span>
                <button class="btn-page" id="btnNext" onclick="changePage(1)">NEXT</button>
            </div>
        </div>

        <div class="card stats-side">
            <div class="inventory-header">📊 INVENTORY</div>
            <div class="stat-card-new b-recv"><h4>RECEIVING</h4><div class="count" id="stat_receiving_in">0</div></div>
            <div class="stat-card-new b-line7"><h4>LINE 7</h4><div class="count" id="stat_line_7">0</div></div>
            <div class="stat-card-new b-line10"><h4>LINE 10</h4><div class="count" id="stat_line_10">0</div></div>
            <div class="stat-card-new b-kaizen"><h4>KAIZEN</h4><div class="count" id="stat_kaizen">0</div></div>
            <div class="stat-card-new b-gas"><h4>GAS LVL 0</h4><div class="count" id="stat_gas_level_0">0</div></div>
            <div class="stat-card-new b-ret"><h4>RETURN</h4><div class="count" id="stat_return_col">0</div></div>
        </div>
    </div>
</div>

<div id="modalAddMaster" class="modal">
    <div class="modal-small">
        <span class="close-btn" onclick="closeModal('modalAddMaster')">&times;</span>
        <h2>ADD NEW ITEM</h2>
        <div class="form-group"><label>SERIAL</label><input type="text" id="new_serial" readonly></div>
        <div class="form-group"><label>ASSIGNED LINE</label><select id="new_line_assigned" style="border:2px solid #dc2626;"><option value="" disabled selected>SELECT LINE...</option><?php for($i=1; $i<=10; $i++): ?><option value="LINE <?= $i ?>">LINE <?= $i ?></option><?php endfor; ?></select></div>
        <div class="form-group"><label>GAS TYPE</label><select id="new_type"><option value="" disabled selected>SELECT TYPE...</option><option value="PALLET 80/20">PALLET 80/20</option><option value="PALLET 95/5">PALLET 95/5</option><option value="CYLINDER O2">CYLINDER O2</option><option value="ACETYLENE">ACETYLENE</option><option value="CYLINDER 80/20">CYLINDER 80/20</option><option value="CYLINDER CO2">CYLINDER CO2</option><option value="PALLET CO2">PALLET CO2</option></select></div>
        <div class="form-group"><label>UOM</label><select id="new_uom"><option value="PLT">PLT</option><option value="CYL">CYL</option></select></div>
        <button class="btn-save" onclick="saveNewMaster()">SAVE & RECEIVE</button>
    </div>
</div>

<div id="modalEditMaster" class="modal">
    <div class="modal-small">
        <span class="close-btn" onclick="closeModal('modalEditMaster')">&times;</span>
        <h2>EDIT ITEM</h2>
        <div class="form-group"><label>SERIAL</label><input type="text" id="edit_serial" readonly style="background:#eee;"></div>
        <div class="form-group"><label>ASSIGNED LINE</label><select id="edit_line_assigned" style="border:2px solid #dc2626;"><option value="" disabled selected>SELECT LINE...</option><?php for($i=1; $i<=10; $i++): ?><option value="LINE <?= $i ?>">LINE <?= $i ?></option><?php endfor; ?></select></div>
        <div class="form-group"><label>GAS TYPE</label><select id="edit_type"><option value="PALLET 80/20">PALLET 80/20</option><option value="PALLET 95/5">PALLET 95/5</option><option value="CYLINDER O2">CYLINDER O2</option><option value="ACETYLENE">ACETYLENE</option><option value="CYLINDER 80/20">CYLINDER 80/20</option><option value="CYLINDER CO2">CYLINDER CO2</option><option value="PALLET CO2">PALLET CO2</option></select></div>
        <div class="form-group"><label>UOM</label><select id="edit_uom"><option value="PLT">PLT</option><option value="CYL">CYL</option></select></div>
        <button class="btn-save" onclick="saveEditMaster()">UPDATE</button>
    </div>
</div>

<div id="modalViewMaster" class="modal">
    <div class="modal-large">
        <div class="modal-header">
            <h2 style="margin:0;">MASTER LIST</h2>
            <div style="display:flex; align-items:center; gap:20px;">
                <input type="text" id="masterSearch" class="search-input" placeholder="SEARCH...">
                <span class="close-btn" onclick="closeModal('modalViewMaster')">&times;</span>
            </div>
        </div>
        <div class="table-scroll-view">
            <table class="master-table" id="masterTable">
                <thead><tr><th>SERIAL NUMBER</th><th>GAS TYPE / DESCRIPTION</th><th>DEFAULT LINE</th><th>UOM</th><th style="width:120px; text-align:center;">ACTIONS</th></tr></thead>
                <tbody>
                    <?php foreach($master_list as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['serial_no']) ?></td>
                        <td><?= htmlspecialchars($m['type']) ?></td>
                        <td><?= htmlspecialchars($m['line_assigned'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['uom']) ?></td>
                        <td style="text-align:center;">
                            <button class="btn-sm btn-edit" onclick="openEditMaster('<?= $m['serial_no'] ?>', '<?= $m['type'] ?>', '<?= $m['uom'] ?>', '<?= $m['line_assigned'] ?? '' ?>')">EDIT</button>
                            <button class="btn-sm btn-del" onclick="deleteMaster('<?= $m['serial_no'] ?>')">DEL</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="printContainer"></div>

<script>
    let selectedArea = null;
    let isScanning = false;
    let isModalOpen = false; 
    let printQueue = [];
    let currentPage = 1;
    let totalPages = 1;

    // --- NEW: AJAX DATA LOADING WITH INVENTORY UPDATES ---
    function loadTrackingData(page) {
        let search = $("#tableSearch").val();
        let dateRecv = $("#dateRecv").val();
        let dateRet = $("#dateRet").val();

        $.post('', { 
            action: 'fetch_tracking', 
            page: page, 
            search: search, 
            dateRecv: dateRecv, 
            dateRet: dateRet 
        }, function(res) {
            // Update Table
            $("#trackingTable tbody").html(res.html);
            
            // Update Stats (Real-Time Inventory)
            if(res.stats) {
                $("#stat_receiving_in").text(res.stats.receiving_in || 0);
                $("#stat_line_7").text(res.stats.line_7 || 0);
                $("#stat_line_10").text(res.stats.line_10 || 0);
                $("#stat_kaizen").text(res.stats.kaizen || 0);
                $("#stat_gas_level_0").text(res.stats.gas_level_0 || 0);
                $("#stat_return_col").text(res.stats.return_col || 0);
            }

            // Update Pagination
            currentPage = parseInt(res.current_page);
            totalPages = parseInt(res.total_pages);
            $("#pageInfo").text("PAGE " + currentPage + " OF " + totalPages);
            $("#btnPrev").prop('disabled', currentPage <= 1);
            $("#btnNext").prop('disabled', currentPage >= totalPages);
        }, 'json');
    }

    function changePage(direction) {
        let newPage = currentPage + direction;
        if(newPage > 0 && newPage <= totalPages) {
            loadTrackingData(newPage);
        }
    }

    // Load initial data on startup
    $(document).ready(function() { 
        loadTrackingData(1); 
        if(!isModalOpen && isScanning) $("#barcodeInput").focus(); 
        
        // Bind enter key on search input
        $("#tableSearch").on('keypress', function(e) {
            if(e.which === 13) loadTrackingData(1);
        });
    });

    function selectArea(area, btnElement) {
        selectedArea = area;
        $(".btn-area").removeClass("selected");
        $(btnElement).addClass("selected");
        $("#scanControls").css("display", "flex");
        $("#msg").text("MODE: " + area.replace('_', ' ').toUpperCase() + ". CLICK START TO BEGIN.");
        $("#scanCounter").hide();
    }

    function startScanning() {
        if(!selectedArea) { alert("PLEASE SELECT AN AREA FIRST."); return; }
        isScanning = true;
        printQueue = []; 
        $("#btnStart").hide(); 
        $("#btnStop").show().text(selectedArea === 'receiving_in' ? "STOP & PRINT" : "STOP SCAN");
        
        $("#inputGroup").css("display", "flex");
        $("#barcodeInput").val("").focus();
        $(".btn-area").css("pointer-events", "none"); 
        $("body").addClass("scanning-active"); 
        
        if(selectedArea === 'receiving_in') {
            $("#msg").text("RECEIVING MODE: SCAN ITEMS. CLICK STOP TO PRINT LABELS.");
            $("#scanCounter").text("ITEMS SCANNED: 0").show();
        } else {
            $("#msg").text("TRANSFER MODE: SCAN PJVK TICKET -> MOVES ITEM.");
            $("#scanCounter").hide();
        }
    }

    function stopScanning() {
        isScanning = false;
        $("#btnStart").show(); $("#btnStop").hide();
        $("#inputGroup").hide();
        $(".btn-area").css("pointer-events", "auto"); 
        $("body").removeClass("scanning-active"); 
        
        if(selectedArea === 'receiving_in' && printQueue.length > 0) {
            $("#msg").text("GENERATING LABELS...");
            printBatchLabels();
        } else {
            $("#msg").text("SCANNING STOPPED.");
        }
    }

    // Manual Entry Logic
    function manualEnter() {
        let val = $("#barcodeInput").val().trim();
        if(!val) { alert("PLEASE ENTER A VALUE FIRST!"); return; }
        
        if(selectedArea === 'receiving_in') {
            receive(val);
        } else {
            if(val.toUpperCase().startsWith('PJVK')) { 
                transfer(val, selectedArea); 
            } else {
                $("#msg").text("❌ ERROR: TICKET MUST START WITH PJVK").css("color", "red");
            }
        }
        $("#barcodeInput").val("").focus();
    }

    document.getElementById('barcodeInput').addEventListener('keypress', function (e) { 
        if (e.key === 'Enter') { 
            let val = this.value.trim(); 
            this.value = ''; 
            if(!val) return; 

            if(selectedArea === 'receiving_in') {
                receive(val);
            } else {
                if(val.toUpperCase().startsWith('PJVK')) { 
                    transfer(val, selectedArea); 
                } else {
                    $("#msg").text("❌ ERROR: SCAN A PJVK TICKET FOR TRANSFER!").css("color", "red");
                }
            }
        } 
    });

    // Updated Receive
    function receive(barcode, line_name = null) { 
        $.post('', { action: 'receive', scanned_barcode: barcode, line_name: line_name }, function(res) { 
            if(res.status === 'success') { 
                printQueue.push(res.data);
                $("#msg").text("✅ SCANNED: " + barcode).css("color", "green");
                $("#scanCounter").text("ITEMS SCANNED: " + printQueue.length);
                loadTrackingData(1); // REFRESH TABLE & STATS
            } 
            else if (res.status === 'not_found') { openAddMaster(barcode); } 
            else { $("#msg").text("❌ ERROR: " + res.message).css("color", "red"); } 
        }, 'json'); 
    }

    function transfer(ticket, area) {
        $.post('', { action: 'transfer', ticket_id: ticket, target_area: area }, function(res) {
            if(res.status === 'success') {
                $("#msg").text("✅ MOVED " + ticket + " TO " + area.replace('_',' ').toUpperCase()).css("color", "green");
                loadTrackingData(1); // REFRESH TABLE & STATS
            } else {
                $("#msg").text("❌ ERROR: " + res.message).css("color", "red");
            }
        }, 'json');
    }

    function printBatchLabels() {
        const container = document.getElementById("printContainer");
        container.innerHTML = ""; 

        printQueue.forEach((item, index) => {
            let qrId = "qrcode_" + index;
            let displayLine = item.line_name ? item.line_name : ""; 
            let labelHtml = `
                <div class="printable-label">
                    <div class="row-top"><div class="box-half border-right">${item.serial_no}</div><div class="box-half">${item.id_ticket}</div></div>
                    <div class="row-mid"><div class="mid-left border-right"><div class="label-line">${displayLine}</div><div class="label-type">${item.type}</div></div><div id="${qrId}" class="qr-box"></div></div>
                    <div class="row-bot"><div class="box-half border-right">RECEIVING IN</div><div class="box-half" style="font-size:12px;">${item.date_time}</div></div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', labelHtml);
            new QRCode(document.getElementById(qrId), { text: item.id_ticket, width: 90, height: 90, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H });
        });

        setTimeout(() => {
            window.print();
            container.innerHTML = ""; 
            printQueue = [];
            stopScanning(); 
        }, 500); 
    }
    
    function reprintSingle(serial, ticket, type, date, line) {
        printQueue = [{ serial_no: serial, id_ticket: ticket, type: type, date_time: date, line_name: line }];
        printBatchLabels();
    }

    function downloadCSV() {
        let csv = [];
        let rows = document.querySelectorAll("#trackingTable tr");
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll("td, th");
            for (let j = 0; j < cols.length - 1; j++) { 
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s)/gm, " ");
                row.push('"' + data + '"');
            }
            csv.push(row.join(","));
        }
        let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
        let downloadLink = document.createElement("a");
        downloadLink.download = "GAS_TRACKING_DATA.csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
    }

    $("#masterSearch").on("keyup", function() { var value = $(this).val().toLowerCase(); $("#masterTable tbody tr").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1) }); });
    
    function deleteTracking(id) { if(confirm("DELETE THIS RECORD?")) { $.post('', { action: 'delete_tracking', id: id }, function(res) { if(res.status === 'success') { loadTrackingData(currentPage); } else { alert(res.message); } }, 'json'); } }
    
    function openAddMaster(barcode) { isModalOpen = true; $("#barcodeInput").blur(); $("#new_serial").val(barcode); $("#new_type").val(''); $("#new_line_assigned").val(''); $("#modalAddMaster").show(); }
    function saveNewMaster() { 
        let s = $("#new_serial").val(); let t = $("#new_type").val(); let u = $("#new_uom").val(); let l = $("#new_line_assigned").val();
        if(!t || !l) { alert("PLEASE SELECT TYPE AND LINE!"); return; }
        $.post('', { action: 'add_master', serial_no: s, type: t, uom: u, line_assigned: l }, function(res) { 
            if(res.status === 'success') { closeModal('modalAddMaster'); receive(s, l); } else { alert(res.message); } 
        }, 'json'); 
    }
    
    function deleteMaster(serial) { if(confirm("DELETE " + serial + "?")) { $.post('', { action: 'delete_master', serial_no: serial }, function(res) { if(res.status === 'success') { location.reload(); } else { alert(res.message); } }, 'json'); } }
    function openEditMaster(serial, type, uom, line) { isModalOpen = true; $("#barcodeInput").blur(); $("#edit_serial").val(serial); $("#edit_type").val(type); $("#edit_uom").val(uom); $("#edit_line_assigned").val(line); $("#modalEditMaster").show(); }
    function saveEditMaster() { $.post('', { action: 'edit_master', serial_no: $("#edit_serial").val(), type: $("#edit_type").val(), uom: $("#edit_uom").val(), line_assigned: $("#edit_line_assigned").val() }, function(res) { if(res.status === 'success') { location.reload(); } else { alert(res.message); } }, 'json'); }
    function openMasterList() { isModalOpen = true; $("#barcodeInput").blur(); $("#modalViewMaster").show(); }
    function closeModal(id) { $("#" + id).hide(); isModalOpen = false; if(isScanning) $("#barcodeInput").focus(); }
</script>
</body>
</html>