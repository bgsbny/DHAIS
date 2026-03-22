<?php
include('mycon.php');

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No data received']);
    exit;
}

$product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 0;
$warehouse = isset($data['warehouse']) ? trim($data['warehouse']) : '';

if ($product_id <= 0 || $quantity <= 0 || empty($warehouse)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$mysqli->begin_transaction();

try {
    // Check if enough stock is available
    $checkStmt = $mysqli->prepare("SELECT stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = ? LIMIT 1");
    $checkStmt->bind_param('is', $product_id, $warehouse);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if (!$row = $result->fetch_assoc()) {
        throw new Exception('Product not found in specified warehouse.');
    }
    
    if ($row['stock_level'] < $quantity) {
        throw new Exception('Insufficient stock in warehouse.');
    }
    
    $checkStmt->close();
    
    // Subtract stock from the warehouse
    $updateStmt = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level - ? WHERE product_id = ? AND storage_location = ?");
    $updateStmt->bind_param('iis', $quantity, $product_id, $warehouse);
    $updateStmt->execute();
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception('Failed to update stock.');
    }
    
    $updateStmt->close();
    
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock updated successfully',
        'remaining_stock' => $row['stock_level'] - $quantity
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 