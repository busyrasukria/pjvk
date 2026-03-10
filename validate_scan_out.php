<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$step = $data['step'] ?? '';

// --- STEP 1: VALIDATE PALLET ---
if ($step === 'pallet') {
    $pallet_no = $data['pallet'] ?? '';
    if (empty($pallet_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Pallet number is empty.']);
        exit();
    }

    // Check if pallet exists in warehouse_in and is ready to be shipped out
    // Make sure your 'warehouse_in' table has a 'status' column ('in', 'out')
    $stmt = $conn->prepare("SELECT 1 FROM warehouse_in WHERE pallet_no = ? AND status = 'in'");
    $stmt->bind_param("s", $pallet_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Pallet OK.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Pallet or Pallet not "IN".']);
    }
    $stmt->close();
    exit();
}

// --- STEP 2: VALIDATE TRANSFER TICKET (against Pallet) ---
if ($step === 'transfer') {
    $pallet_no = $data['pallet'] ?? '';
    $transfer_ticket = $data['transfer_ticket'] ?? '';

    if (empty($pallet_no) || empty($transfer_ticket)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing Pallet or Transfer Ticket.']);
        exit();
    }

    // Check if this Transfer Ticket is associated with this Pallet
    // Assumes 'transfer_tickets' table has 'pallet_no' and 'ticket_no' columns
    $stmt = $conn->prepare("SELECT 1 FROM transfer_tickets WHERE pallet_no = ? AND ticket_no = ?");
    $stmt->bind_param("ss", $pallet_no, $transfer_ticket);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Transfer Ticket OK.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Transfer Ticket does not match Pallet.']);
    }
    $stmt->close();
    exit();
}

// --- STEP 3: VALIDATE MAZDA TICKET (Final Match & Process Out) ---
if ($step === 'mazda') {
    $pallet_no = $data['pallet'] ?? '';
    $transfer_ticket = $data['transfer_ticket'] ?? '';
    $mazda_ticket = $data['mazda_ticket'] ?? '';
    $shipment_to = $data['shipment_to'] ?? '';
    $truck_no = $data['truck_no'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (empty($pallet_no) || empty($transfer_ticket) || empty($mazda_ticket) || empty($shipment_to) || empty($truck_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Incomplete data for final scan.']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1. Get Part No and Qty from Transfer Ticket
        $stmt_tt = $conn->prepare("SELECT part_no, quantity FROM transfer_tickets WHERE pallet_no = ? AND ticket_no = ?");
        $stmt_tt->bind_param("ss", $pallet_no, $transfer_ticket);
        $stmt_tt->execute();
        $result_tt = $stmt_tt->get_result();
        if ($result_tt->num_rows === 0) {
            throw new Exception('Transfer Ticket mismatch (Final Check).');
        }
        $tt_data = $result_tt->fetch_assoc();
        $part_no = $tt_data['part_no'];
        $quantity = $tt_data['quantity'];
        $stmt_tt->close();

        // 2. Get the log_id from warehouse_in using Mazda Ticket and other data
        //    We also verify this is the correct part and it's still 'in'
        //    Assumes 'warehouse_in' has 'ticket_no' for the Mazda ticket
        $stmt_wi = $conn->prepare("SELECT log_id FROM warehouse_in 
                                  WHERE pallet_no = ? 
                                    AND ticket_no = ? 
                                    AND part_no = ? 
                                    AND status = 'in'");
        $stmt_wi->bind_param("sss", $pallet_no, $mazda_ticket, $part_no);
        $stmt_wi->execute();
        $result_wi = $stmt_wi->get_result();
        if ($result_wi->num_rows === 0) {
            throw new Exception('Mazda Ticket mismatch or part already scanned.');
        }
        $wi_data = $result_wi->fetch_assoc();
        $warehouse_in_log_id = $wi_data['log_id'];
        $stmt_wi->close();

        // 3. All checks passed! Log the item in warehouse_out
        $stmt_out = $conn->prepare("INSERT INTO warehouse_out 
                                    (part_no, quantity, pallet_no, ticket_no_transfer, ticket_no_mazda, shipment_to, truck_no, scanned_by_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_out->bind_param("sisssssi", $part_no, $quantity, $pallet_no, $transfer_ticket, $mazda_ticket, $shipment_to, $truck_no, $user_id);
        $stmt_out->execute();
        $stmt_out->close();

        // 4. Update the item in warehouse_in to 'out'
        $stmt_update = $conn->prepare("UPDATE warehouse_in SET status = 'out' WHERE log_id = ?");
        $stmt_update->bind_param("i", $warehouse_in_log_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Commit the transaction
        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Scan Complete! Part logged as OUT.',
            'part_no' => $part_no,
            'quantity' => $quantity
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    $conn->close();
    exit();
}

// Fallback for unknown step
echo json_encode(['status' => 'error', 'message' => 'Invalid scan step.']);
?>