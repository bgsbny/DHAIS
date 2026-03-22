<?php
include 'mycon.php';

$term = isset($_GET['term']) ? $_GET['term'] : '';

// Get products from masterlist that are active and have stock in any warehouse
$sql = "SELECT DISTINCT 
            m.product_id, 
            m.item_name, 
            m.oem_number, 
            m.product_brand, 
            m.unit, 
            m.selling_price,
            COALESCE(SUM(i.stock_level), 0) as total_stock
        FROM tbl_masterlist m
        LEFT JOIN tbl_inventory i ON m.product_id = i.product_id
        WHERE m.status != 'Inactive'
          AND (m.item_name LIKE ? OR m.oem_number LIKE ? OR m.product_brand LIKE ?)
        GROUP BY m.product_id, m.item_name, m.oem_number, m.product_brand, m.unit, m.selling_price
        ORDER BY m.item_name
        LIMIT 10";

$like = "%$term%";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    // Get stock levels for each warehouse (including zero stock for display)
    $stockQuery = "SELECT storage_location, stock_level FROM tbl_inventory 
                   WHERE product_id = ? 
                   ORDER BY CASE 
                       WHEN storage_location = 'Main Shop' THEN 1 
                       WHEN storage_location = 'Warehouse 1' THEN 2 
                       WHEN storage_location = 'Warehouse 2' THEN 3 
                       ELSE 4 
                   END";
    $stockStmt = $mysqli->prepare($stockQuery);
    $stockStmt->bind_param('i', $row['product_id']);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    
    $warehouses = [];
    $mainShopStock = 0;
    $hasStock = false;
    
    while ($stockRow = $stockResult->fetch_assoc()) {
        $warehouses[] = $stockRow;
        if ($stockRow['storage_location'] === 'Main Shop') {
            $mainShopStock = $stockRow['stock_level'];
        }
        if ($stockRow['stock_level'] > 0) {
            $hasStock = true;
        }
    }
    $stockStmt->close();
    
    // Only include products that have stock in at least one warehouse
    if (!$hasStock) {
        continue;
    }
    
    // Determine default warehouse (Main Shop first, then first available with stock)
    $defaultWarehouse = 'Main Shop';
    $hasMainShop = false;
    foreach ($warehouses as $warehouse) {
        if ($warehouse['storage_location'] === 'Main Shop') {
            $hasMainShop = true;
            break;
        }
    }
    
    if (!$hasMainShop) {
        // Use first warehouse with stock
        foreach ($warehouses as $warehouse) {
            if ($warehouse['stock_level'] > 0) {
                $defaultWarehouse = $warehouse['storage_location'];
                break;
            }
        }
    }
    
    $suggestions[] = [
        'label' => "{$row['item_name']} | {$row['oem_number']} | {$row['product_brand']}",
        'value' => "{$row['item_name']} | {$row['oem_number']} | {$row['product_brand']}",
        'product_id' => $row['product_id'],
        'product_brand' => $row['product_brand'],
        'stock_level' => $mainShopStock, // Default to Main Shop stock for display
        'unit' => $row['unit'],
        'selling_price' => $row['selling_price'],
        'default_warehouse' => $defaultWarehouse,
        'warehouses' => $warehouses,
        'total_stock' => $row['total_stock']
    ];
}

echo json_encode($suggestions);
$mysqli->close();
?> 