<?php
include('mycon.php');

// Get product ID from request
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

// Get warehouses where this product is available
$query = "SELECT storage_location, stock_level FROM tbl_inventory 
          WHERE product_id = ? AND stock_level > 0 
          ORDER BY CASE 
              WHEN storage_location = 'Main Shop' THEN 1 
              WHEN storage_location = 'Warehouse 1' THEN 2 
              WHEN storage_location = 'Warehouse 2' THEN 3 
              ELSE 4 
          END";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();

$warehouses = [];
while ($row = $result->fetch_assoc()) {
    $warehouses[] = [
        'location' => $row['storage_location'],
        'stock' => intval($row['stock_level'])
    ];
}

$stmt->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'warehouses' => $warehouses
]);
?> 