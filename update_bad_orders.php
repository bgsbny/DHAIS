<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: spo_details.php');
    exit();
}

$spo_id = isset($_POST['spo_id']) ? (int)$_POST['spo_id'] : 0;
$spo_item_ids = $_POST['spo_item_id'] ?? [];
$bad_order_qtys = $_POST['bad_order_qty'] ?? [];
$bad_order_remarks = $_POST['bad_order_remarks'] ?? [];

if ($spo_id <= 0 || empty($spo_item_ids)) {
    header('Location: spo_details.php?id=' . $spo_id . '&error=Invalid data provided');
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Update each item's bad order quantity and remarks
    for ($i = 0; $i < count($spo_item_ids); $i++) {
        $item_id = (int)$spo_item_ids[$i];
        $bad_qty = isset($bad_order_qtys[$i]) ? (int)$bad_order_qtys[$i] : 0;
        $remarks = isset($bad_order_remarks[$i]) ? mysqli_real_escape_string($mysqli, $bad_order_remarks[$i]) : '';
        
        // Validate bad order quantity
        if ($bad_qty < 0) {
            throw new Exception("Bad order quantity cannot be negative");
        }
        
        // Get the original quantity to validate
        $check_query = "SELECT quantity FROM tbl_spo_items WHERE spo_item_id = ? AND spo_id = ?";
        $stmt = $mysqli->prepare($check_query);
        $stmt->bind_param('ii', $item_id, $spo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Item not found");
        }
        
        $item_data = $result->fetch_assoc();
        $original_qty = $item_data['quantity'];
        
        if ($bad_qty > $original_qty) {
            throw new Exception("Bad order quantity cannot exceed original quantity");
        }
        
        $stmt->close();
        
        // Check if bad_order_remarks column exists
        $check_column = $mysqli->query("SHOW COLUMNS FROM tbl_spo_items LIKE 'bad_order_remarks'");
        $has_remarks_column = $check_column->num_rows > 0;
        
        // Update the item
        if ($has_remarks_column) {
            $update_query = "UPDATE tbl_spo_items SET bad_order_qty = ?, bad_order_remarks = ? WHERE spo_item_id = ? AND spo_id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param('isis', $bad_qty, $remarks, $item_id, $spo_id);
        } else {
            $update_query = "UPDATE tbl_spo_items SET bad_order_qty = ? WHERE spo_item_id = ? AND spo_id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param('iii', $bad_qty, $item_id, $spo_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating item: " . $mysqli->error);
        }
        $stmt->close();
    }
    
    // Commit transaction
    $mysqli->commit();
    
    header('Location: spo_details.php?id=' . $spo_id . '&success=Bad orders updated successfully');
    
} catch (Exception $e) {
    // Rollback transaction
    $mysqli->rollback();
    
    header('Location: spo_details.php?id=' . $spo_id . '&error=' . urlencode($e->getMessage()));
}

$mysqli->close();
?> 