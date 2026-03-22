<?php
include 'mycon.php';
session_start();

// Basic auth check
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Helper redirect
function redirect_with_message($spo_id, $message, $is_success = true) {
    $param = $is_success ? 'success' : 'error';
    header("Location: spo_details.php?id=" . urlencode($spo_id) . "&$param=" . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['process-bad-orders'])) {
    redirect_with_message($_POST['spo_id'] ?? 0, 'Invalid request', false);
}

$spo_id = (int)($_POST['spo_id'] ?? 0);
if ($spo_id <= 0) {
    redirect_with_message(0, 'Missing or invalid SPO ID', false);
}

// Extract arrays from POST
$spo_item_ids         = $_POST['spo_item_id'] ?? [];
$bad_order_qtys       = $_POST['bad_order_qty'] ?? [];
$bad_order_types      = $_POST['bad_order_type'] ?? [];
$bad_order_dates      = $_POST['bad_order_date'] ?? [];
$return_amounts       = $_POST['return_amount'] ?? [];
$replacement_invoices = $_POST['replacement_invoice'] ?? [];
$bad_order_remarks    = $_POST['bad_order_remarks'] ?? [];

// Normalize to arrays
if (!is_array($spo_item_ids)) $spo_item_ids = [];

// Row count based on provided item IDs only
$num_rows = count($spo_item_ids);

// Begin transaction
$mysqli->begin_transaction();

try {
    // Prepare statements we'll reuse
    $stmt_fetch_item = $mysqli->prepare(
        "SELECT spo_id, product_id, quantity, COALESCE(bad_order_qty,0) AS bad_order_qty, price 
         FROM tbl_spo_items WHERE spo_item_id = ?"
    );
    if (!$stmt_fetch_item) throw new Exception('Prepare fetch item failed: ' . $mysqli->error);

    $stmt_sum_transfers = $mysqli->prepare(
        "SELECT COALESCE(SUM(quantity),0) AS transferred_qty
         FROM tbl_stock_transfers WHERE spo_id = ? AND product_id = ?"
    );
    if (!$stmt_sum_transfers) throw new Exception('Prepare sum transfers failed: ' . $mysqli->error);

    $stmt_update_item = $mysqli->prepare(
        "UPDATE tbl_spo_items 
            SET bad_order_qty = ?,
                bad_order_type = ?,
                bad_order_date = ?,
                return_amount = ?,
                replacement_invoice = ?,
                bad_order_remarks = ?
          WHERE spo_item_id = ?"
    );
    if (!$stmt_update_item) throw new Exception('Prepare update item failed: ' . $mysqli->error);

    for ($i = 0; $i < $num_rows; $i++) {
        $spo_item_id = (int)$spo_item_ids[$i];
        $new_bad_qty = (int)($bad_order_qtys[$i] !== '' ? $bad_order_qtys[$i] : 0);
        // Since type/date/return_amount/replacement_invoice were removed from the form,
        // default to 'return' with today's date and no amounts/invoices
        $type        = 'return';
        $date        = date('Y-m-d');
        $ret_amount  = 0.0;
        $repl_inv    = NULL;
        $remarks     = trim($bad_order_remarks[$i] ?? '');

        if ($spo_item_id <= 0) continue; // skip invalid rows
        if ($new_bad_qty < 0) throw new Exception('Bad order quantity cannot be negative');

        // Fetch item details
        $stmt_fetch_item->bind_param('i', $spo_item_id);
        if (!$stmt_fetch_item->execute()) throw new Exception('Execute fetch item failed');
        $item_res = $stmt_fetch_item->get_result();
        $item = $item_res->fetch_assoc();
        if (!$item) throw new Exception('SPO item not found');

        // Validate belongs to this SPO
        if ((int)$item['spo_id'] !== $spo_id) throw new Exception('Item/SPO mismatch');

        $product_id      = (int)$item['product_id'];
        $ordered_qty     = (int)$item['quantity'];
        $existing_badqty = (int)$item['bad_order_qty'];

        // Sum transferred
        $stmt_sum_transfers->bind_param('ii', $spo_id, $product_id);
        if (!$stmt_sum_transfers->execute()) throw new Exception('Execute sum transfers failed');
        $transfer_res = $stmt_sum_transfers->get_result();
        $transfer_row = $transfer_res->fetch_assoc();
        $transferred_qty = (int)($transfer_row['transferred_qty'] ?? 0);

        // Validation: total bad order qty cannot exceed remaining (ordered - transferred)
        if ($new_bad_qty + $transferred_qty > $ordered_qty) {
            throw new Exception('Bad order qty exceeds remaining for one or more items');
        }

        // If type is return and no explicit return_amount provided, default to price * qty
        if ($type === 'return') {
            $unit_price = (float)($item['price'] ?? 0);
            $ret_amount = $unit_price * $new_bad_qty;
        }

        // Allow empty replacement invoice to support pending replacements
        if ($repl_inv === '') {
            $repl_inv = NULL; // store as NULL
        }

        // Normalize date
        // Date already set above

        // Update item
        $stmt_update_item->bind_param(
            'issdssi',
            $new_bad_qty,
            $type,
            $date,
            $ret_amount,
            $repl_inv,
            $remarks,
            $spo_item_id
        );
        if (!$stmt_update_item->execute()) throw new Exception('Update failed for an item');
    }

    $mysqli->commit();
    redirect_with_message($spo_id, 'Bad orders processed successfully!', true);
} catch (Exception $e) {
    $mysqli->rollback();
    redirect_with_message($spo_id, 'Error processing bad orders: ' . $e->getMessage(), false);
}

?>

<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: spo_details.php');
    exit();
}

$spo_id = isset($_POST['spo_id']) ? (int)$_POST['spo_id'] : 0;
$spo_item_ids = $_POST['spo_item_id'] ?? [];
$bad_order_qtys = $_POST['bad_order_qty'] ?? [];
$bad_order_types = $_POST['bad_order_type'] ?? [];
$bad_order_dates = $_POST['bad_order_date'] ?? [];
$bad_order_remarks = $_POST['bad_order_remarks'] ?? [];
$return_amounts = $_POST['return_amount'] ?? [];
$replacement_invoices = $_POST['replacement_invoice'] ?? [];

if ($spo_id <= 0 || empty($spo_item_ids)) {
    header('Location: spo_details.php?id=' . $spo_id . '&error=Invalid data provided');
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Process each bad order item
    for ($i = 0; $i < count($spo_item_ids); $i++) {
        $item_id = (int)$spo_item_ids[$i];
        $bad_qty = isset($bad_order_qtys[$i]) ? (int)$bad_order_qtys[$i] : 0;
        $bad_type = isset($bad_order_types[$i]) ? mysqli_real_escape_string($mysqli, $bad_order_types[$i]) : 'return';
        $bad_date = isset($bad_order_dates[$i]) ? mysqli_real_escape_string($mysqli, $bad_order_dates[$i]) : date('Y-m-d');
        $remarks = isset($bad_order_remarks[$i]) ? mysqli_real_escape_string($mysqli, $bad_order_remarks[$i]) : '';
        $return_amount = isset($return_amounts[$i]) ? floatval($return_amounts[$i]) : 0.00;
        $replacement_invoice = isset($replacement_invoices[$i]) ? mysqli_real_escape_string($mysqli, $replacement_invoices[$i]) : '';
        
        // Validate data types
        if (!is_int($bad_qty)) $bad_qty = 0;
        if (!is_string($bad_type)) $bad_type = 'return';
        if (!is_string($bad_date)) $bad_date = date('Y-m-d');
        if (!is_string($remarks)) $remarks = '';
        if (!is_float($return_amount)) $return_amount = 0.00;
        if (!is_string($replacement_invoice)) $replacement_invoice = '';
        
        // Skip if no bad order quantity
        if ($bad_qty <= 0) {
            continue;
        }
        
        // Validate bad order quantity
        if ($bad_qty < 0) {
            throw new Exception("Bad order quantity cannot be negative");
        }
        
        // Get the original item data and SPO information
        $check_query = "SELECT si.*, ml.item_name, ml.product_brand, ml.unit, ml.oem_number, ml.product_size,
                               spo.transaction_method
                        FROM tbl_spo_items si
                        LEFT JOIN tbl_masterlist ml ON si.product_id = ml.product_id
                        LEFT JOIN tbl_spo spo ON si.spo_id = spo.spo_id
                        WHERE si.spo_item_id = ? AND si.spo_id = ?";
        $stmt = $mysqli->prepare($check_query);
        $stmt->bind_param('ii', $item_id, $spo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Item not found");
        }
        
        $item_data = $result->fetch_assoc();
        $original_qty = $item_data['quantity'];
        $original_price = $item_data['price'];
        $transaction_method = $item_data['transaction_method'];
        
        if ($bad_qty > $original_qty) {
            throw new Exception("Bad order quantity cannot exceed original quantity");
        }
        
        $stmt->close();
        
        // Calculate return amount if not provided
        // Always compute return amount based on unit price and qty since fields are removed
        $return_amount = $original_price * $bad_qty;
        
        // Update the item with bad order information
        $update_fields = [];
        $update_params = [];
        $update_types = '';
        
        // Add basic fields
        $update_fields[] = 'bad_order_qty = ?';
        $update_params[] = $bad_qty;
        $update_types .= 'i';
        
        $update_fields[] = 'bad_order_type = ?';
        $update_params[] = 'return';
        $update_types .= 's';
        
        $update_fields[] = 'bad_order_date = ?';
        $update_params[] = date('Y-m-d');
        $update_types .= 's';
        
        $update_fields[] = 'bad_order_remarks = ?';
        $update_params[] = $remarks;
        $update_types .= 's';
        
        // Add replacement or return specific fields
        // Only return flow is supported in the simplified form
        $update_fields[] = 'return_amount = ?';
        $update_params[] = $return_amount;
        $update_types .= 'd';
        
        $update_query = "UPDATE tbl_spo_items SET " . implode(', ', $update_fields) . 
                       " WHERE spo_item_id = ? AND spo_id = ?";
        
        // Add WHERE clause parameters
        $update_params[] = $item_id;
        $update_params[] = $spo_id;
        $update_types .= 'ii';
        
        $stmt = $mysqli->prepare($update_query);
        
        // Validate parameters before binding
        if (count($update_params) !== strlen($update_types)) {
            throw new Exception("Parameter count mismatch: " . count($update_params) . " params vs " . strlen($update_types) . " types");
        }
        
        $stmt->bind_param($update_types, ...$update_params);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating item: " . $mysqli->error);
        }
        $stmt->close();
        
        // Handle return processing
        if ($return_amount > 0) {
            // Insert into return tracking table (optimized)
            $insert_return = "INSERT INTO tbl_return_tracking 
                             (spo_item_id, return_amount) 
                             VALUES (?, ?)";
            $stmt = $mysqli->prepare($insert_return);
            $stmt->bind_param('id', $item_id, $return_amount);
            
            if (!$stmt->execute()) {
                throw new Exception("Error recording return: " . $mysqli->error);
            }
            $stmt->close();
            
            // Update supplier payment (reduce amount owed)
            if ($transaction_method === 'Credit') {
                // Get credit information
                $credit_query = "SELECT spo_credit_id FROM tbl_spo_credits WHERE spo_id = ?";
                $stmt = $mysqli->prepare($credit_query);
                $stmt->bind_param('i', $spo_id);
                $stmt->execute();
                $credit_result = $stmt->get_result();
                
                if ($credit_result->num_rows > 0) {
                    $credit_data = $credit_result->fetch_assoc();
                    $spo_credit_id = $credit_data['spo_credit_id'];
                    
                    // Insert a negative payment (credit) to reduce the amount owed
                    $insert_credit = "INSERT INTO tbl_spo_payment 
                                     (spo_credit_id, date_paid, amount_paid, payment_method, reference_no, remarks) 
                                     VALUES (?, ?, ?, 'Return Credit', 'RETURN-{$item_id}', ?)";
                    $stmt = $mysqli->prepare($insert_credit);
                    $negative_amount = -$return_amount; // Negative amount to reduce debt
                    $stmt->bind_param('ids', $spo_credit_id, $bad_date, $negative_amount, $remarks);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error recording return credit: " . $mysqli->error);
                    }
                    $stmt->close();
                }
            }
        }
        
        // Handle replacement processing
        // Replacement flow removed in simplified modal
    }
    
    // Commit transaction
    $mysqli->commit();
    
    header('Location: spo_details.php?id=' . $spo_id . '&success=Bad orders processed successfully');
    
} catch (Exception $e) {
    // Rollback transaction
    $mysqli->rollback();
    
    header('Location: spo_details.php?id=' . $spo_id . '&error=' . urlencode($e->getMessage()));
}

$mysqli->close();
?> 