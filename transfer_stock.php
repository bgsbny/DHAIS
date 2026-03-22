<?php
include('mycon.php');
session_start();

if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spo_item_id = intval($_POST['spo_item_id']);
    $product_id = intval($_POST['product_id']);
    $transfer_location = mysqli_real_escape_string($mysqli, $_POST['transfer_location']);
    $transfer_qty = intval($_POST['transfer_qty']);
    $transfer_date = mysqli_real_escape_string($mysqli, $_POST['transfer_date']);
    
    // Validate required fields
    if (!$spo_item_id || !$product_id || !$transfer_location || !$transfer_qty || !$transfer_date) {
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }
    
    // Get current item data to validate transfer quantity
    $query = "SELECT 
        si.quantity as total_qty,
        si.bad_order_qty,
        COALESCE(SUM(ts.transfer_qty), 0) as transferred_qty
    FROM tbl_spo_items si
    LEFT JOIN tbl_transfer_stock ts ON si.spo_item_id = ts.spo_item_id
    WHERE si.spo_item_id = ?
    GROUP BY si.spo_item_id, si.quantity, si.bad_order_qty";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $spo_item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item_data) {
        echo json_encode(['error' => 'Item not found']);
        exit;
    }
    
    // Calculate remaining quantity
    $remaining_qty = $item_data['total_qty'] - $item_data['bad_order_qty'] - $item_data['transferred_qty'];
    
    // Validate transfer quantity
    if ($transfer_qty > $remaining_qty) {
        echo json_encode(['error' => 'Transfer quantity cannot exceed remaining quantity']);
        exit;
    }
    
    if ($transfer_qty <= 0) {
        echo json_encode(['error' => 'Transfer quantity must be greater than 0']);
        exit;
    }
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Insert transfer record
        $insert_transfer = "INSERT INTO tbl_transfer_stock 
            (spo_item_id, product_id, transfer_location, transfer_qty, transfer_date) 
            VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insert_transfer);
        $stmt->bind_param('iisis', $spo_item_id, $product_id, $transfer_location, $transfer_qty, $transfer_date);
        
        if (!$stmt->execute()) {
            throw new Exception('Error inserting transfer record: ' . $mysqli->error);
        }
        $transfer_id = $mysqli->insert_id;
        $stmt->close();
        
        // Update inventory
        $update_inventory = "INSERT INTO tbl_inventory (product_id, storage_location, stock_level) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE stock_level = stock_level + ?";
        $stmt = $mysqli->prepare($update_inventory);
        $stmt->bind_param('isii', $product_id, $transfer_location, $transfer_qty, $transfer_qty);
        
        if (!$stmt->execute()) {
            throw new Exception('Error updating inventory: ' . $mysqli->error);
        }
        $stmt->close();
        
        // Commit transaction
        $mysqli->commit();
        
        echo json_encode(['success' => true, 'message' => 'Stock transferred successfully']);
        
    } catch (Exception $e) {
        // Rollback transaction
        $mysqli->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?> 