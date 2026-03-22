<?php
// Prevent any output before JSON
ob_start();

include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Get POST data
$data = $_POST;

// Debug: Log the received data
error_log("Received POST data: " . print_r($data, true));

// Validate required fields
$required_fields = ['supplier_name', 'supplier_number'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
        exit;
    }
}

// Extract supplier data
$supplier_name = trim($data['supplier_name']);
$supplier_number = trim($data['supplier_number']);
$supplier_email = trim($data['supplier_email'] ?? '');
$supplier_province = trim($data['supplier_province'] ?? '');
$supplier_city = trim($data['supplier_city'] ?? '');
$supplier_baranggay = trim($data['supplier_baranggay'] ?? '');
$supplier_street = trim($data['supplier_street'] ?? '');

// Extract contact person data
$contact_fn = trim($data['contact_fn'] ?? '');
$contact_mn = trim($data['contact_mn'] ?? '');
$contact_ln = trim($data['contact_ln'] ?? '');
$contact_suffix = trim($data['contact_suffix'] ?? '');
$contact_nickname = trim($data['contact_nickname'] ?? '');
$contact_number = trim($data['contact_number'] ?? '');
$contact_email = trim($data['contact_email'] ?? '');
$contact_province = trim($data['contact_province'] ?? '');
$contact_city = trim($data['contact_city'] ?? '');
$contact_baranggay = trim($data['contact_baranggay'] ?? '');
$contact_street = trim($data['contact_street'] ?? '');

// Debug: Log the extracted data
error_log("Extracted supplier data: " . print_r([
    'supplier_name' => $supplier_name,
    'supplier_number' => $supplier_number,
    'supplier_email' => $supplier_email
], true));

$mysqli->begin_transaction();

try {
    // Insert supplier
    $stmt = $mysqli->prepare("INSERT INTO tbl_supplier (
        supplier_name, supplier_number, supplier_email, 
        supplier_province, supplier_city, supplier_baranggay, supplier_street,
        contact_fn, contact_mn, contact_ln, contact_suffix, contact_nickname,
        contact_number, contact_email, contact_province, contact_city, 
        contact_baranggay, contact_street
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }
    
    $stmt->bind_param('ssssssssssssssssss', 
        $supplier_name, $supplier_number, $supplier_email,
        $supplier_province, $supplier_city, $supplier_baranggay, $supplier_street,
        $contact_fn, $contact_mn, $contact_ln, $contact_suffix, $contact_nickname,
        $contact_number, $contact_email, $contact_province, $contact_city,
        $contact_baranggay, $contact_street
    );
    
    $result = $stmt->execute();
    if (!$result) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
    $supplier_id = $stmt->insert_id;
    $stmt->close();
    
    if (!$supplier_id) {
        throw new Exception('Failed to insert supplier - no insert ID returned.');
    }
    
    $mysqli->commit();
    
    $response = [
        'success' => true, 
        'message' => 'Supplier added successfully!',
        'supplier_id' => $supplier_id
    ];
    
    // Debug: Log the response
    error_log("Sending response: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Error in add_supplier.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// End output buffering and flush
ob_end_flush();
?> 