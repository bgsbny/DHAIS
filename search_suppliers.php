<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$search_term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($search_term)) {
    echo json_encode([]);
    exit();
}

try {
    // Search suppliers by name only (progressive search)
    $query = "SELECT 
                supplier_id,
                supplier_name,
                supplier_number,
                supplier_email,
                supplier_street,
                supplier_baranggay,
                supplier_city,
                supplier_province
              FROM tbl_supplier 
              WHERE supplier_name LIKE ?
              ORDER BY supplier_name";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $search_pattern = $search_term . '%'; // Starts with search term
    $stmt->bind_param('s', $search_pattern);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = [
            'supplier_id' => $row['supplier_id'],
            'supplier_name' => $row['supplier_name'],
            'supplier_number' => $row['supplier_number'],
            'supplier_email' => $row['supplier_email'],
            'supplier_street' => $row['supplier_street'],
            'supplier_baranggay' => $row['supplier_baranggay'],
            'supplier_city' => $row['supplier_city'],
            'supplier_province' => $row['supplier_province']
        ];
    }
    
    $stmt->close();
    
    echo json_encode($suppliers);
    
} catch (Exception $e) {
    error_log("search_suppliers.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 