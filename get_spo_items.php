<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$spo_id = isset($_GET['spo_id']) ? (int)$_GET['spo_id'] : 0;

error_log("get_spo_items.php called with spo_id: " . $spo_id);

if ($spo_id <= 0) {
    echo json_encode(['error' => 'Invalid SPO ID']);
    exit();
}

try {
    // Check which columns exist in the table
    $check_columns = $mysqli->query("SHOW COLUMNS FROM tbl_spo_items");
    $existing_columns = [];
    while ($row = $check_columns->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    // Build query based on available columns
    $select_fields = [
        'si.spo_item_id',
        'si.quantity',
        'si.bad_order_qty',
        'm.item_name',
        'm.product_brand',
        'm.unit',
        'm.oem_number',
        'm.product_size'
    ];
    
    // Add new bad order fields if they exist
    if (in_array('bad_order_remarks', $existing_columns)) {
        $select_fields[] = 'si.bad_order_remarks';
    } else {
        $select_fields[] = "'' as bad_order_remarks";
    }
    
    if (in_array('bad_order_type', $existing_columns)) {
        $select_fields[] = 'si.bad_order_type';
    } else {
        $select_fields[] = "'return' as bad_order_type";
    }
    
    if (in_array('bad_order_date', $existing_columns)) {
        $select_fields[] = 'si.bad_order_date';
    } else {
        $select_fields[] = 'NULL as bad_order_date';
    }
    
    if (in_array('replacement_invoice', $existing_columns)) {
        $select_fields[] = 'si.replacement_invoice';
    } else {
        $select_fields[] = 'NULL as replacement_invoice';
    }
    
    if (in_array('return_amount', $existing_columns)) {
        $select_fields[] = 'si.return_amount';
    } else {
        $select_fields[] = '0.00 as return_amount';
    }
    
    $query = "SELECT " . implode(', ', $select_fields) . "
              FROM tbl_spo_items si
              LEFT JOIN tbl_masterlist m ON si.product_id = m.product_id
              WHERE si.spo_id = ?
              ORDER BY si.spo_item_id";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param('i', $spo_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'spo_item_id' => $row['spo_item_id'],
            'quantity' => (int)$row['quantity'],
            'bad_order_qty' => (int)$row['bad_order_qty'],
            'bad_order_remarks' => $row['bad_order_remarks'] ?? '',
            'bad_order_type' => $row['bad_order_type'] ?? 'return',
            'bad_order_date' => $row['bad_order_date'],
            'replacement_invoice' => $row['replacement_invoice'],
            'return_amount' => (float)$row['return_amount'],
            'item_name' => $row['item_name'],
            'product_brand' => $row['product_brand'],
            'unit' => $row['unit'],
            'oem_number' => $row['oem_number'],
            'product_size' => $row['product_size']
        ];
    }
    
    $stmt->close();
    
    error_log("get_spo_items.php returning " . count($items) . " items");
    echo json_encode($items);
    
} catch (Exception $e) {
    error_log("get_spo_items.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 