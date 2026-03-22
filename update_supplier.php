<?php
include('mycon.php');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle supplier update if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supplier') {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Set JSON header for AJAX response
    header('Content-Type: application/json');
    
    try {
        // Get form data
        $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
        $supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
        $supplier_number = isset($_POST['supplier_number']) ? trim($_POST['supplier_number']) : '';
        $supplier_email = isset($_POST['supplier_email']) ? trim($_POST['supplier_email']) : '';
        $supplier_province = isset($_POST['supplier_province']) ? trim($_POST['supplier_province']) : '';
        $supplier_city = isset($_POST['supplier_city']) ? trim($_POST['supplier_city']) : '';
        $supplier_baranggay = isset($_POST['supplier_baranggay']) ? trim($_POST['supplier_baranggay']) : '';
        $supplier_street = isset($_POST['supplier_street']) ? trim($_POST['supplier_street']) : '';
        
        // Contact person fields
        $contact_fn = isset($_POST['contact_fn']) ? trim($_POST['contact_fn']) : '';
        $contact_mn = isset($_POST['contact_mn']) ? trim($_POST['contact_mn']) : '';
        $contact_ln = isset($_POST['contact_ln']) ? trim($_POST['contact_ln']) : '';
        $contact_suffix = isset($_POST['contact_suffix']) ? trim($_POST['contact_suffix']) : '';
        $contact_nickname = isset($_POST['contact_nickname']) ? trim($_POST['contact_nickname']) : '';
        $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
        $contact_email = isset($_POST['contact_email']) ? trim($_POST['contact_email']) : '';
        $contact_province = isset($_POST['contact_province']) ? trim($_POST['contact_province']) : '';
        $contact_city = isset($_POST['contact_city']) ? trim($_POST['contact_city']) : '';
        $contact_baranggay = isset($_POST['contact_baranggay']) ? trim($_POST['contact_baranggay']) : '';
        $contact_street = isset($_POST['contact_street']) ? trim($_POST['contact_street']) : '';

        // Validation
        if ($supplier_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
            exit();
        }

        if (empty($supplier_name)) {
            echo json_encode(['success' => false, 'error' => 'Supplier name is required']);
            exit();
        }

        if (empty($supplier_number)) {
            echo json_encode(['success' => false, 'error' => 'Contact number is required']);
            exit();
        }

        // Check if supplier exists
        $check_query = "SELECT supplier_id FROM tbl_supplier WHERE supplier_id = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param('i', $supplier_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Supplier not found']);
            exit();
        }
        $check_stmt->close();

        // Update supplier information
        $update_query = "UPDATE tbl_supplier SET 
            supplier_name = ?, 
            supplier_number = ?, 
            supplier_email = ?, 
            supplier_province = ?, 
            supplier_city = ?, 
            supplier_baranggay = ?, 
            supplier_street = ?,
            contact_fn = ?,
            contact_mn = ?,
            contact_ln = ?,
            contact_suffix = ?,
            contact_nickname = ?,
            contact_number = ?,
            contact_email = ?,
            contact_province = ?,
            contact_city = ?,
            contact_baranggay = ?,
            contact_street = ?
            WHERE supplier_id = ?";

        $update_stmt = $mysqli->prepare($update_query);
        $update_stmt->bind_param('ssssssssssssssssssi', 
            $supplier_name, 
            $supplier_number, 
            $supplier_email, 
            $supplier_province, 
            $supplier_city, 
            $supplier_baranggay, 
            $supplier_street,
            $contact_fn,
            $contact_mn,
            $contact_ln,
            $contact_suffix,
            $contact_nickname,
            $contact_number,
            $contact_email,
            $contact_province,
            $contact_city,
            $contact_baranggay,
            $contact_street,
            $supplier_id
        );

        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update supplier: ' . $mysqli->error]);
        }

        $update_stmt->close();
        exit();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}

// If not a valid request, redirect back
header('Location: supplier.php');
exit();
?> 