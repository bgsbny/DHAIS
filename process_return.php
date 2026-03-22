<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get POST data
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No data received or invalid JSON']);
    exit();
}

try {
    $transaction_id = isset($data['transaction_id']) ? (int)$data['transaction_id'] : 0;
    $transaction_type = isset($data['transaction_type']) ? $data['transaction_type'] : '';
    $purchased_item_ids = isset($data['purchased_item_ids']) ? $data['purchased_item_ids'] : [];
    $return_quantity = isset($data['return_quantity']) ? (int)$data['return_quantity'] : 0;
    $return_condition = isset($data['return_condition']) ? $data['return_condition'] : '';
    $return_reason = isset($data['return_reason']) ? $data['return_reason'] : '';
    $product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
    $unit_price = isset($data['unit_price']) ? (float)$data['unit_price'] : 0;
    $discount = isset($data['discount']) ? (float)$data['discount'] : 0;
    $markup = isset($data['markup']) ? (float)$data['markup'] : 0;

    // Validate required fields
    if ($transaction_id <= 0 || empty($transaction_type) || empty($purchased_item_ids) || $return_quantity <= 0 || empty($return_condition)) {
        throw new Exception('Missing required fields');
    }

    // Validate transaction type
    if (!in_array($transaction_type, ['sale', 'refund', 'exchange'])) {
        throw new Exception('Invalid transaction type specified');
    }

    // Validate condition
    if (!in_array($return_condition, ['good', 'bad'])) {
        throw new Exception('Invalid condition specified');
    }

    $mysqli->begin_transaction();

try {
    // Get original transaction details
    $original_transaction_query = "SELECT invoice_no, customer_type, customer_firstName, customer_middleName, customer_lastName, customer_suffix FROM purchase_transactions WHERE transaction_id = ?";
    $stmt = $mysqli->prepare($original_transaction_query);
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $original_result = $stmt->get_result();
    
    if ($original_result->num_rows === 0) {
        throw new Exception('Original transaction not found');
    }
    
    $original_transaction = $original_result->fetch_assoc();
    $stmt->close();
    
    // Calculate refund amount (positive for return)
    $refund_amount = $unit_price * $return_quantity;
    
    // Create return transaction
    $return_transaction_query = "INSERT INTO purchase_transactions 
        (invoice_no, customer_type, subtotal, discount_percentage, grand_total, transaction_date, 
         customer_firstName, customer_middleName, customer_lastName, customer_suffix, 
         transaction_type, reference_transaction_id) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
    
    $return_invoice_no = 'RET-' . $original_transaction['invoice_no'] . '-' . date('YmdHis');
    $discount_percentage = 0; // Create variable for literal value
    
    $stmt = $mysqli->prepare($return_transaction_query);
    $stmt->bind_param('ssddssssssi', 
        $return_invoice_no,
        $original_transaction['customer_type'],
        $refund_amount,
        $discount_percentage,
        $refund_amount,
        $original_transaction['customer_firstName'],
        $original_transaction['customer_middleName'],
        $original_transaction['customer_lastName'],
        $original_transaction['customer_suffix'],
        $transaction_type,
        $transaction_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Error creating return transaction: ' . $mysqli->error);
    }
    
    $return_transaction_id = $mysqli->insert_id;
    $stmt->close();
    
    // Create return transaction details with positive quantity but negative subtotal
    $return_details_query = "INSERT INTO purchase_transaction_details 
        (transaction_id, product_id, purchased_quantity, unit_price_at_purchase, 
         product_discount, product_markup, product_subtotal, return_condition, return_reason) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // Use positive quantity and positive subtotal for return
    $return_subtotal = $refund_amount;
    
    $stmt = $mysqli->prepare($return_details_query);
    $stmt->bind_param('iiddddsss', 
        $return_transaction_id,
        $product_id,
        $return_quantity, // Use positive quantity
        $unit_price,
        $discount,
        $markup,
        $return_subtotal, // Negative subtotal indicates return
        $return_condition,
        $return_reason
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Error creating return transaction details: ' . $mysqli->error);
    }
    
    $return_detail_id = $mysqli->insert_id;
    $stmt->close();
    
    // Handle inventory based on condition
    if ($return_condition === 'good') {
        // Implement priority-based return logic
        $return_location = null;
        
        // Priority 1: Check if product exists in Main Shop
        $check_main_shop = "SELECT inventory_id, stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = 'Main Shop'";
        $stmt = $mysqli->prepare($check_main_shop);
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $main_shop_result = $stmt->get_result();
        $stmt->close();
        
        if ($main_shop_result->num_rows > 0) {
            // Product exists in Main Shop - update it
            $update_inventory = "UPDATE tbl_inventory 
                               SET stock_level = stock_level + ?, last_updated = NOW() 
                               WHERE product_id = ? AND storage_location = 'Main Shop'";
            $stmt = $mysqli->prepare($update_inventory);
            $stmt->bind_param('ii', $return_quantity, $product_id);
            $return_location = 'Main Shop';
        } else {
            // Priority 2: Check if product exists in Warehouse 1
            $check_warehouse1 = "SELECT inventory_id, stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = 'Warehouse 1'";
            $stmt = $mysqli->prepare($check_warehouse1);
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $warehouse1_result = $stmt->get_result();
            $stmt->close();
            
            if ($warehouse1_result->num_rows > 0) {
                // Product exists in Warehouse 1 - update it
                $update_inventory = "UPDATE tbl_inventory 
                                   SET stock_level = stock_level + ?, last_updated = NOW() 
                                   WHERE product_id = ? AND storage_location = 'Warehouse 1'";
                $stmt = $mysqli->prepare($update_inventory);
                $stmt->bind_param('ii', $return_quantity, $product_id);
                $return_location = 'Warehouse 1';
            } else {
                // Priority 3: Check if product exists in Warehouse 2
                $check_warehouse2 = "SELECT inventory_id, stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = 'Warehouse 2'";
                $stmt = $mysqli->prepare($check_warehouse2);
                $stmt->bind_param('i', $product_id);
                $stmt->execute();
                $warehouse2_result = $stmt->get_result();
                $stmt->close();
                
                if ($warehouse2_result->num_rows > 0) {
                    // Product exists in Warehouse 2 - update it
                    $update_inventory = "UPDATE tbl_inventory 
                                       SET stock_level = stock_level + ?, last_updated = NOW() 
                                       WHERE product_id = ? AND storage_location = 'Warehouse 2'";
                    $stmt = $mysqli->prepare($update_inventory);
                    $stmt->bind_param('ii', $return_quantity, $product_id);
                    $return_location = 'Warehouse 2';
                } else {
                    // Product doesn't exist in any location - create new row in Main Shop
                    $insert_inventory = "INSERT INTO tbl_inventory (product_id, stock_level, storage_location, last_updated) 
                                       VALUES (?, ?, 'Main Shop', NOW())";
                    $stmt = $mysqli->prepare($insert_inventory);
                    $stmt->bind_param('ii', $product_id, $return_quantity);
                    $return_location = 'Main Shop';
                }
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Error updating inventory: ' . $mysqli->error);
        }
        $stmt->close();
        
        // Check if inventory movements table exists before trying to log
        $check_table = "SHOW TABLES LIKE 'tbl_inventory_movements'";
        $table_result = $mysqli->query($check_table);
        
        if ($table_result && $table_result->num_rows > 0) {
            // Log inventory movement with the actual return location
            $movement_query = "INSERT INTO tbl_inventory_movements 
                              (product_id, movement_type, quantity, from_location, to_location, reference_id, reference_type, notes) 
                              VALUES (?, 'return_good', ?, '', ?, ?, 'return', ?)";
            
            $notes = "Return in good condition to " . $return_location . " - " . $return_reason;
            $stmt = $mysqli->prepare($movement_query);
            $stmt->bind_param('iissi', $product_id, $return_quantity, $return_location, $return_transaction_id, $notes);
            
            if (!$stmt->execute()) {
                // Don't throw exception for movement logging, just continue
                error_log('Error logging inventory movement: ' . $mysqli->error);
            }
            $stmt->close();
        }
        
    } else {
        // Bad condition - don't return to inventory, just log the movement
        $check_table = "SHOW TABLES LIKE 'tbl_inventory_movements'";
        $table_result = $mysqli->query($check_table);
        
        if ($table_result && $table_result->num_rows > 0) {
            $movement_query = "INSERT INTO tbl_inventory_movements 
                              (product_id, movement_type, quantity, from_location, to_location, reference_id, reference_type, notes) 
                              VALUES (?, 'return_bad', ?, '', '', ?, 'return', ?)";
            
            $notes = "Return in bad condition - " . $return_reason;
            $stmt = $mysqli->prepare($movement_query);
            $stmt->bind_param('iiis', $product_id, $return_quantity, $return_transaction_id, $notes);
            
            if (!$stmt->execute()) {
                // Don't throw exception for movement logging, just continue
                error_log('Error logging inventory movement: ' . $mysqli->error);
            }
            $stmt->close();
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Return processed successfully',
        'return_transaction_id' => $return_transaction_id,
        'inventory_updated' => ($return_condition === 'good'),
        'return_location' => ($return_condition === 'good') ? $return_location : null
    ]);
    
    } catch (Exception $e) {
        // Rollback transaction
        $mysqli->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    $mysqli->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Processing error: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'error' => 'System error: ' . $e->getMessage()]);
}
?> 