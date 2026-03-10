<?php
require_once 'db.php';
header('Content-Type: application/json');

// 1. HANDLE POST REQUESTS (ADD / EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        if ($action === 'add') {
            $sql = "INSERT INTO master_incoming (part_no, erp_code, stock_desc, seq_number, std_packing, supplier) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                strtoupper(trim($input['part_no'])),
                strtoupper(trim($input['erp_code'])),
                strtoupper(trim($input['part_name'])),
                (int)$input['seq_no'],
                (int)$input['std_packing'],
                strtoupper(trim($input['supplier']))
            ]);
            echo json_encode(['success' => true, 'message' => 'New Part Added Successfully']);
            exit;

        } elseif ($action === 'edit') {
            // We use the OLD part_no to identify the row to update
            $sql = "UPDATE master_incoming SET part_no=?, erp_code=?, stock_desc=?, seq_number=?, std_packing=?, supplier=? WHERE part_no=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                strtoupper(trim($input['part_no'])),
                strtoupper(trim($input['erp_code'])),
                strtoupper(trim($input['part_name'])),
                (int)$input['seq_no'],
                (int)$input['std_packing'],
                strtoupper(trim($input['supplier'])),
                trim($input['original_part_no']) // The reference to update
            ]);
            echo json_encode(['success' => true, 'message' => 'Part Updated Successfully']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        exit;
    }
}

// 2. HANDLE GET REQUESTS (SEARCH)
// 2. HANDLE GET REQUESTS (SEARCH)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $sql = "SELECT * FROM master_incoming";
    $params = [];

    if ($search !== '') {
        // --- SMART SEARCH LOGIC ---
        
        // CASE A: The user typed a Number (e.g., "1", "105")
        // We assume they want the Sequence Number exactly.
        // We use '=' instead of 'LIKE' to filter out Part Numbers that just happen to contain "1".
        if (is_numeric($search)) {
             $sql .= " WHERE seq_number = ? OR part_no = ?";
             $params = [$search, $search];
        } 
        
        // CASE B: The user typed Text (e.g., "KD", "Cable")
        // We use normal wildcard search (%) to find partial matches.
        else {
             $sql .= " WHERE part_no LIKE ? 
                       OR erp_code LIKE ? 
                       OR stock_desc LIKE ?";
             $term = "%$search%";
             $params = [$term, $term, $term];
        }
    }

    $sql .= " ORDER BY seq_number ASC LIMIT 100"; 

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>