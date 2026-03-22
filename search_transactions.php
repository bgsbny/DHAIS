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
    // Search transactions by invoice number or customer name (progressive search)
    $query = "SELECT 
                transaction_id,
                invoice_no,
                external_receipt_no,
                customer_type,
                grand_total,
                transaction_date,
                customer_firstName,
                customer_middleName,
                customer_lastName,
                customer_suffix
              FROM purchase_transactions 
              WHERE invoice_no LIKE ? 
                 OR CONCAT(customer_firstName, ' ', customer_middleName, ' ', customer_lastName, ' ', customer_suffix) LIKE ?
              ORDER BY transaction_date DESC, transaction_id DESC";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $search_pattern = $search_term . '%'; // Starts with search term
    $stmt->bind_param('ss', $search_pattern, $search_pattern);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Build full customer name
        $customerName = trim($row['customer_firstName'] . ' ' . $row['customer_middleName'] . ' ' . $row['customer_lastName'] . ' ' . $row['customer_suffix']);
        
        $transactions[] = [
            'transaction_id' => $row['transaction_id'],
            'invoice_no' => $row['invoice_no'],
            'external_receipt_no' => $row['external_receipt_no'],
            'customer_type' => $row['customer_type'],
            'grand_total' => $row['grand_total'],
            'transaction_date' => $row['transaction_date'],
            'customer_name' => $customerName,
            'customer_firstName' => $row['customer_firstName'],
            'customer_middleName' => $row['customer_middleName'],
            'customer_lastName' => $row['customer_lastName'],
            'customer_suffix' => $row['customer_suffix']
        ];
    }
    
    $stmt->close();
    
    echo json_encode($transactions);
    
} catch (Exception $e) {
    error_log("search_transactions.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 