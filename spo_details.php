<?php
include('mycon.php');
session_start();

// Handle success/error messages from bad orders update
$success_message = isset($_GET['success']) ? $_GET['success'] : null;
$error_message = isset($_GET['error']) ? $_GET['error'] : null;

// Handle stock transfer form submission
if (isset($_POST['transfer-stock'])) {
    $transfer_spo_id = (int)$_POST['transfer_spo_id'];
    $transfer_product_id = (int)$_POST['transfer_product_id'];
    $transfer_location = mysqli_real_escape_string($mysqli, $_POST['transfer_location']);
    $transfer_qty = (int)$_POST['transfer_qty'];
    $transfer_date = mysqli_real_escape_string($mysqli, $_POST['transfer_date']);
    
    // Validate transfer quantity
    if ($transfer_qty <= 0) {
        $error_message = "Transfer quantity must be greater than 0.";
    } else {
        // Check remaining quantity
        $check_query = "SELECT i.quantity, COALESCE(SUM(st.quantity), 0) as transferred_qty, i.bad_order_qty
                        FROM tbl_spo_items i
                        LEFT JOIN tbl_stock_transfers st ON i.spo_id = st.spo_id AND i.product_id = st.product_id
                        WHERE i.spo_id = ? AND i.product_id = ?
                        GROUP BY i.quantity, i.bad_order_qty";
        
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("ii", $transfer_spo_id, $transfer_product_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        $check_data = $check_result->fetch_assoc();
        
        if ($check_data) {
            $remaining_qty = $check_data['quantity'] - $check_data['transferred_qty'] - $check_data['bad_order_qty'];
            
            if ($transfer_qty > $remaining_qty) {
                $error_message = "Transfer quantity ($transfer_qty) exceeds remaining quantity ($remaining_qty).";
            } else {
                // Begin transaction
                $mysqli->begin_transaction();
                
                try {
                    // 1. Record the transfer
                    $insert_transfer = "INSERT INTO tbl_stock_transfers 
                                       (spo_id, product_id, quantity, location, transfer_date) 
                                       VALUES (?, ?, ?, ?, ?)";
                    $stmt_transfer = $mysqli->prepare($insert_transfer);
                    $stmt_transfer->bind_param("iiiss", $transfer_spo_id, $transfer_product_id, $transfer_qty, $transfer_location, $transfer_date);
                    
                    if (!$stmt_transfer->execute()) {
                        throw new Exception("Error inserting transfer record");
                    }
                    
                    // 2. Update inventory at destination location
                    // First check if product exists at this location
                    $check_inventory = "SELECT * FROM tbl_inventory 
                                       WHERE product_id = ? AND storage_location = ?";
                    $stmt_check_inv = $mysqli->prepare($check_inventory);
                    $stmt_check_inv->bind_param("is", $transfer_product_id, $transfer_location);
                    $stmt_check_inv->execute();
                    $result_check_inv = $stmt_check_inv->get_result();
                    
                    if($result_check_inv->num_rows > 0) {
                        // Update existing inventory
                        $update_inventory = "UPDATE tbl_inventory 
                                            SET stock_level = stock_level + ? 
                                            WHERE product_id = ? AND storage_location = ?";
                        $stmt_update = $mysqli->prepare($update_inventory);
                        $stmt_update->bind_param("iis", $transfer_qty, $transfer_product_id, $transfer_location);
                        
                        if (!$stmt_update->execute()) {
                            throw new Exception("Error updating inventory");
                        }
                    } else {
                        // Insert new inventory entry
                        $insert_inventory = "INSERT INTO tbl_inventory 
                                            (product_id, stock_level, storage_location) 
                                            VALUES (?, ?, ?)";
                        $stmt_insert = $mysqli->prepare($insert_inventory);
                        $stmt_insert->bind_param("iis", $transfer_product_id, $transfer_qty, $transfer_location);
                        
                        if (!$stmt_insert->execute()) {
                            throw new Exception("Error inserting inventory");
                        }
                    }
                    
                    // Commit the transaction
                    $mysqli->commit();
                    $success_message = "Stock transfer completed successfully!";
                    
                } catch (Exception $e) {
                    // Roll back the transaction
                    $mysqli->rollback();
                    $error_message = "Error transferring stock: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "Item not found in purchase order.";
        }
    }
}

// Handle edit item form submission
if (isset($_POST['edit-item'])) {
    $edit_spo_item_id = (int)$_POST['edit_spo_item_id'];
    $edit_quantity = (int)$_POST['edit_quantity'];
    $edit_bad_order_qty = (int)$_POST['edit_bad_order_qty'];
    
    // Validate quantities
    if ($edit_quantity < 0) {
        $error_message = "Quantity cannot be negative.";
    } elseif ($edit_bad_order_qty < 0) {
        $error_message = "Bad order quantity cannot be negative.";
    } elseif ($edit_bad_order_qty > $edit_quantity) {
        $error_message = "Bad order quantity cannot exceed total quantity.";
    } else {
        // Check if item has been transferred
        $check_transfers_query = "SELECT COALESCE(SUM(quantity), 0) as transferred_qty 
                                  FROM tbl_stock_transfers 
                                  WHERE spo_id = (SELECT spo_id FROM tbl_spo_items WHERE spo_item_id = ?) 
                                  AND product_id = (SELECT product_id FROM tbl_spo_items WHERE spo_item_id = ?)";
        $stmt = $mysqli->prepare($check_transfers_query);
        $stmt->bind_param('ii', $edit_spo_item_id, $edit_spo_item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transferred_data = $result->fetch_assoc();
        $stmt->close();
        
        $transferred_qty = $transferred_data['transferred_qty'];
        
        // Check if new quantity is sufficient for transferred amount
        if ($edit_quantity < $transferred_qty) {
            $error_message = "Cannot reduce quantity below transferred amount ($transferred_qty).";
        } else {
            // Update the item
            $update_query = "UPDATE tbl_spo_items 
                            SET quantity = ?, bad_order_qty = ? 
                            WHERE spo_item_id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param('iii', $edit_quantity, $edit_bad_order_qty, $edit_spo_item_id);
            
            if ($stmt->execute()) {
                $success_message = "Item updated successfully!";
            } else {
                $error_message = "Error updating item: " . $mysqli->error;
            }
            $stmt->close();
        }
    }
}

// Handle AJAX request for item data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_item_data') {
    if(isset($_GET['spo_id']) && isset($_GET['product_id'])) {
        $spo_id = mysqli_real_escape_string($mysqli, $_GET['spo_id']);
        $product_id = mysqli_real_escape_string($mysqli, $_GET['product_id']);
        
        // Join query to get all the needed information
        $query = "SELECT i.*, p.item_name, p.product_brand, p.category, p.oem_number, p.product_size, p.unit 
                  FROM tbl_spo_items i
                  LEFT JOIN tbl_masterlist p ON i.product_id = p.product_id
                  WHERE i.spo_id = '$spo_id' AND i.product_id = '$product_id'";
        
        $result = mysqli_query($mysqli, $query);
        
        if($result && mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            
            // Get the total quantity already transferred
            $query_transferred = "SELECT COALESCE(SUM(quantity), 0) as transferred_qty 
                                  FROM tbl_stock_transfers 
                                  WHERE spo_id = '$spo_id' AND product_id = '$product_id'";
            $result_transferred = mysqli_query($mysqli, $query_transferred);
            $transferred_data = mysqli_fetch_assoc($result_transferred);
            
            // Calculate remaining quantity
            $total_qty = $data['quantity'] ?? 0;
            $transferred_qty = $transferred_data['transferred_qty'] ?? 0;
            $bad_order_qty = $data['bad_order_qty'] ?? 0;
            $remaining_qty = $total_qty - $transferred_qty - $bad_order_qty;
            
            // Debug logging
            error_log("Transfer calculation - Total: $total_qty, Transferred: $transferred_qty, Bad Order: $bad_order_qty, Remaining: $remaining_qty");
            
            // Add the transfer data to the response
            $data['total_qty'] = $total_qty;
            $data['transferred_qty'] = $transferred_qty;
            $data['remaining_qty'] = $remaining_qty;
            $data['bad_order_qty'] = $bad_order_qty;
            
            // Return the data as JSON
            header('Content-Type: application/json');
            echo json_encode($data);
        } else {
            // Return empty object if no data found
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No item data found']);
        }
    } else {
        // Return error if no spo_id provided
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No SPO ID or Product ID provided']);
    }
    exit(); // Important: Exit after handling AJAX request
}

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get SPO ID from URL parameter
$spo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$spo_id) {
    header('Location: supplier.php');
    exit();
}

// Fetch SPO items with product details
$query = "SELECT 
    si.spo_item_id,
    si.spo_id,
    si.product_id,
    si.quantity,
    si.bad_order_qty,
    si.price,
    si.line_discount,
    si.line_subtotal,
    ml.item_name,
    ml.oem_number,
    ml.product_size,
    ml.product_brand,
    ml.category,
    ml.unit
FROM tbl_spo_items si
LEFT JOIN tbl_masterlist ml ON si.product_id = ml.product_id
WHERE si.spo_id = ?
ORDER BY si.spo_item_id ASC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $spo_id);
$stmt->execute();
$result = $stmt->get_result();
$spo_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get SPO header information
$spo_header_query = "SELECT 
    spo.spo_id,
    spo.invoice_number,
    spo.transaction_method,
    s.supplier_name
FROM tbl_spo spo
LEFT JOIN tbl_supplier s ON spo.supplier_id = s.supplier_id
WHERE spo.spo_id = ?";

$stmt = $mysqli->prepare($spo_header_query);
$stmt->bind_param('i', $spo_id);
$stmt->execute();
$spo_result = $stmt->get_result();
$spo_header = $spo_result->fetch_assoc();
$stmt->close();

if (!$spo_header) {
    header('Location: supplier.php');
    exit();
}

// Get credit information if this is a credit transaction
$credit_info = null;
if ($spo_header['transaction_method'] === 'Credit') {
    $credit_query = "SELECT spo_credit_id, total_with_interest, interest FROM tbl_spo_credits WHERE spo_id = ?";
    $stmt = $mysqli->prepare($credit_query);
    $stmt->bind_param('i', $spo_id);
    $stmt->execute();
    $credit_result = $stmt->get_result();
    $credit_info = $credit_result->fetch_assoc();
    $stmt->close();
    
    // Calculate remaining balance for payments
    if ($credit_info) {
        $total_paid_query = "SELECT COALESCE(SUM(amount_paid), 0) as total_paid 
                             FROM tbl_spo_payment 
                             WHERE spo_credit_id = ?";
        $stmt = $mysqli->prepare($total_paid_query);
        $stmt->bind_param('i', $credit_info['spo_credit_id']);
        $stmt->execute();
        $total_paid_result = $stmt->get_result();
        $total_paid_data = $total_paid_result->fetch_assoc();
        $stmt->close();
        
        $total_paid = $total_paid_data['total_paid'];
        $balance = $credit_info['total_with_interest'] - $total_paid;
    } else {
        $balance = 0;
    }
}

// Handle payment processing
if (isset($_POST['process-payment'])) {
    $date_paid = mysqli_real_escape_string($mysqli, $_POST['date_paid']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_method = mysqli_real_escape_string($mysqli, $_POST['payment_type']);
    $reference_no = mysqli_real_escape_string($mysqli, $_POST['reference_no'] ?? '');
    $remarks = mysqli_real_escape_string($mysqli, $_POST['remarks'] ?? '');
    
    // Validate that this is a credit transaction
    if ($spo_header['transaction_method'] !== 'Credit' || !$credit_info) {
        $error_message = "Payments can only be processed for credit transactions.";
    } else {
        // Insert payment record
        $insert_payment = "INSERT INTO tbl_spo_payment (spo_credit_id, date_paid, amount_paid, payment_method, reference_no, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $mysqli->prepare($insert_payment);
        
        if (!$stmt) {
            $error_message = "Prepare failed for payment: " . $mysqli->error;
        } else {
            $stmt->bind_param("isdss", $credit_info['spo_credit_id'], $date_paid, $amount_paid, $payment_method, $reference_no);
            
            if ($stmt->execute()) {
                // Redirect back to the same page with success message
                header("Location: spo_details.php?id=$spo_id&success=" . urlencode("Payment recorded successfully!"));
                exit();
            } else {
                $error_message = "Error recording payment: " . $mysqli->error;
            }
            $stmt->close();
        }
    }
}

// Handle AJAX request for payment history
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_payment_history') {
    // Prevent any output before JSON response
    ob_clean();
    
    error_log("Payment history AJAX request received");
    error_log("Transaction method: " . ($spo_header['transaction_method'] ?? 'null'));
    error_log("Credit info: " . ($credit_info ? 'exists' : 'null'));
    
    try {
        if ($spo_header['transaction_method'] === 'Credit' && $credit_info) {
            error_log("Processing credit payment history for spo_credit_id: " . $credit_info['spo_credit_id']);
            
            $payment_query = "SELECT date_paid, amount_paid, payment_method, reference_no, remarks, created_at 
                             FROM tbl_spo_payment 
                             WHERE spo_credit_id = ? 
                             ORDER BY date_paid DESC";
            $stmt = $mysqli->prepare($payment_query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $mysqli->error);
            }
            
            $stmt->bind_param('i', $credit_info['spo_credit_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $payment_result = $stmt->get_result();
            
            $payments = [];
            while ($row = $payment_result->fetch_assoc()) {
                $payments[] = $row;
            }
            $stmt->close();
            
            error_log("Found " . count($payments) . " payments");
            
            header('Content-Type: application/json');
            echo json_encode($payments);
        } else {
            error_log("No credit info found, returning empty array");
            header('Content-Type: application/json');
            echo json_encode([]);
        }
    } catch (Exception $e) {
        error_log("Payment history error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <!-- for the icons -->  
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">

    <!-- Local jQuery files -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <script src="js/jquery-ui.min.js"></script>

    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">

    <link rel="stylesheet" href="css/style.css">

    <title>SPO Details</title>
</head>
<body>
    <?php $activePage = 'supplier'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Purchase Order Details</h1>
                    <p class="page-subtitle">View and manage purchase order information and items.</p>
                </div>
            </div>
        </header>
        
        <div class="main-container">
            <div class="history">
                <div class="table-responsive">
                    <div class="card-header">
                    <h6 class="mb-0"><?php echo htmlspecialchars($spo_header['supplier_name']); ?></h6>
                        <h6 class="mb-0">Invoice Number: <span class="badge bg-primary"><?php echo htmlspecialchars($spo_header['invoice_number']); ?></span></h6>
                    </div>
                    <table class="table table-bordered">
                        <thead>
                            <tr class="align-middle">
                                <th>Product</th>
                                <th>Part Number/Size</th>
                                <th>Brand</th>
                                <th>Unit</th>
                                <th>QTY</th>
                                <th class="text-end">Price (₱)</th>
                                <th class="text-end">Discount (₱)</th>
                                <th class="text-end">Total (₱)</th>
                                <th class="text-center">Bad Order Qty</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($spo_items)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-box-open me-2"></i>No items found for this purchase order
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($spo_items as $item): ?>
                                    <?php
                                    // Calculate transfer status for this item
                                    $spo_id = $item['spo_id'];
                                    $product_id = $item['product_id'];
                                    $total_qty = $item['quantity'];
                                    
                                    // Get transferred quantity
                                    $transferred_query = "SELECT COALESCE(SUM(quantity), 0) as transferred_qty 
                                                          FROM tbl_stock_transfers 
                                                          WHERE spo_id = ? AND product_id = ?";
                                    $stmt = $mysqli->prepare($transferred_query);
                                    $stmt->bind_param('ii', $spo_id, $product_id);
                                    $stmt->execute();
                                    $transferred_result = $stmt->get_result();
                                    $transferred_data = $transferred_result->fetch_assoc();
                                    $stmt->close();
                                    
                                    $transferred_qty = $transferred_data['transferred_qty'];
                                    $remaining_qty = $total_qty - $transferred_qty - $item['bad_order_qty'];
                                    
                                    // Determine status
                                    if ($transferred_qty == 0) {
                                        $status = 'Not Transferred';
                                        $status_class = 'bg-secondary';
                                    } elseif ($remaining_qty > 0) {
                                        $status = 'Partially Transferred';
                                        $status_class = 'bg-warning';
                                    } else {
                                        $status = 'Transferred';
                                        $status_class = 'bg-success';
                                    }
                                    ?>
                                    <tr class='align-middle'>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td>
                                            <?php
                                            // Display part number or size if available
                                            $partNumberOrSize = '';
                                            if (!empty($item['oem_number'])) {
                                                $partNumberOrSize = $item['oem_number'];
                                            } elseif (!empty($item['product_size'])) {
                                                $partNumberOrSize = $item['product_size'];
                                            }
                                            
                                            if (!empty($partNumberOrSize)) {
                                                echo htmlspecialchars($partNumberOrSize);
                                            } else {
                                                echo '<span class="text-muted">N/A</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['product_brand']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="text-end"><?php echo number_format($item['price'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($item['line_discount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($item['line_subtotal'], 2); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($item['bad_order_qty']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                        </td>
                                        <td class="text-center">
                                            
                                        
                                            <button class="btn btn-sm btn-outline-primary open-modal-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#transferStockModal"
                                                data-spo-id="<?php echo $item['spo_id']; ?>"
                                                data-product-id="<?php echo $item['product_id']; ?>">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($spo_header['transaction_method'] === 'Credit' && $credit_info): ?>
                <div class="mt-3 d-flex justify-content-end">
                    <button class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#paymentHistoryModal">
                        <i class="fas fa-history me-1"></i>Payment History
                    </button>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processPaymentModal">
                        <i class="fas fa-cash-register me-1"></i>Process Payment
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Summary Section -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Purchase Order Summary</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Calculate totals
                                $total_items = count($spo_items);
                                $total_quantity = 0;
                                $total_amount = 0;
                                $total_discount = 0;
                                $total_bad_orders = 0;
                                
                                foreach ($spo_items as $item) {
                                    $total_quantity += $item['quantity'];
                                    $total_amount += $item['line_subtotal'];
                                    $total_discount += $item['line_discount'];
                                    $total_bad_orders += $item['bad_order_qty'];
                                }
                                ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Total Items:</strong></span>
                                    <span><?php echo $total_items; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Total Quantity:</strong></span>
                                    <span><?php echo $total_quantity; ?></span>
                                </div>
                                <?php if ($spo_header['transaction_method'] === 'Credit' && $credit_info): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>Total Interest:</strong></span>
                                        <span>₱<?php echo number_format($credit_info['interest'], 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>Total Amount:</strong></span>
                                        <span>₱<?php echo number_format($credit_info['total_with_interest'], 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>Total Amount Paid:</strong></span>
                                        <span class="text-success">₱<?php echo number_format($total_paid, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>Remaining Balance:</strong></span>
                                        <span class="text-danger">₱<?php echo number_format($balance, 2); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>Total Discount:</strong></span>
                                        <span>₱<?php echo number_format($total_discount, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><strong>Total Amount:</strong></span>
                                        <span>₱<?php echo number_format($total_amount, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Bad Orders:</strong></span>
                                    <button class="btn btn-sm btn-outline-warning bad-orders-btn" 
                                            data-spo-id="<?php echo $spo_id; ?>" 
                                            data-invoice="<?php echo htmlspecialchars($spo_header['invoice_number'] ?? 'N/A'); ?>"
                                            style="min-width: 60px;">
                                        <?php echo $total_bad_orders; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Transfer Status</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Calculate transfer statistics
                                $not_transferred = 0;
                                $partially_transferred = 0;
                                $fully_transferred = 0;
                                
                                foreach ($spo_items as $item) {
                                    $spo_id = $item['spo_id'];
                                    $product_id = $item['product_id'];
                                    $total_qty = $item['quantity'];
                                    
                                    // Get transferred quantity
                                    $transferred_query = "SELECT COALESCE(SUM(quantity), 0) as transferred_qty 
                                                          FROM tbl_stock_transfers 
                                                          WHERE spo_id = ? AND product_id = ?";
                                    $stmt = $mysqli->prepare($transferred_query);
                                    $stmt->bind_param('ii', $spo_id, $product_id);
                                    $stmt->execute();
                                    $transferred_result = $stmt->get_result();
                                    $transferred_data = $transferred_result->fetch_assoc();
                                    $stmt->close();
                                    
                                    $transferred_qty = $transferred_data['transferred_qty'];
                                    $remaining_qty = $total_qty - $transferred_qty - $item['bad_order_qty'];
                                    
                                    if ($transferred_qty == 0) {
                                        $not_transferred++;
                                    } elseif ($remaining_qty > 0) {
                                        $partially_transferred++;
                                    } else {
                                        $fully_transferred++;
                                    }
                                }
                                ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Not Transferred:</strong></span>
                                    <span class="badge bg-secondary"><?php echo $not_transferred; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Partially Transferred:</strong></span>
                                    <span class="badge bg-warning"><?php echo $partially_transferred; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Fully Transferred:</strong></span>
                                    <span class="badge bg-success"><?php echo $fully_transferred; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <!-- TRANSFER STOCK MODAL SECTION -->
    <div class='modal' id='transferStockModal'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h6 class='modal-title'>Transfer Stocks</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class='modal-body'>
                        <form method="POST" action="">
                            <!-- <div class="mb-3"> -->
                                <!-- <strong>Purchase ID: <span id='spo-item-id'></span></strong> -->
                                <input type="hidden" name="transfer_spo_id" id="transfer_spo_id">
                                <input type="hidden" name="transfer_product_id" id="transfer_product_id">
                            <!-- </div> -->
                            
                            <div id="item-details" class="mb-3">
                                <p><strong>Item Name:</strong> <span id="item_name"></span></p>
                                <p><strong>Part Number/Size:</strong> <span id="part_number_or_size"></span></p>
                                <p><strong>Brand:</strong> <span id="product_brand"></span></p>
                                <p><strong>Category:</strong> <span id="category"></span></p>
                            </div>

                            <!-- Transfer tracking information -->
                            <div class="mb-3 p-2 bg-light border rounded">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Total Quantity Ordered:</strong></span>
                                    <span id="total_qty">0</span>
                                        </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Bad Order Quantity:</strong></span>
                                    <span id="bad_order_qty">0</span>
                                    </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Already Transferred:</strong></span>
                                    <span id="transferred_qty">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><strong>Remaining to Transfer:</strong></span>
                                    <span id="remaining_qty" class="text-primary">0</span>
                                </div>
                            </div>

                            <div class='mt-3 p-1'>
                                <label for="transfer_location">Transfer to Location</label>
                                <select name="transfer_location" id="transfer_location" class='form-select'>
                                    <option value="Main Shop">Main Shop</option>
                                    <option value="Warehouse 1">Warehouse 1</option>
                                    <option value="Warehouse 2">Warehouse 2</option>
                                </select>
                                <br>
                                <label for="transfer_qty">Quantity to Transfer</label>
                                <input type="number" name="transfer_qty" id="transfer_qty" class='form-control'>
                                <div id="qty-error" class="text-danger" style="display:none;">
                                    Quantity to transfer cannot exceed remaining quantity.
                                </div>
                                <br>
                                <label for="transfer_date">Date Transferred</label>
                                <input type="date" name="transfer_date" id="transfer_date" class='form-control' value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button class="btn btn-primary mt-3" type="submit" name="transfer-stock">Transfer Stock</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- EDIT ITEM MODAL SECTION -->
        <div class='modal' id='editItemModal'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h6 class='modal-title'>Edit Item</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class='modal-body'>
                        <form method="POST" action="">
                            <input type="hidden" name="edit_spo_item_id" id="edit_spo_item_id">
                            
                            <div id="edit-item-details" class="mb-3">
                                <p><strong>Item Name:</strong> <span id="edit_item_name"></span></p>
                            </div>

                            <div class='mt-3 p-1'>
                                <label for="edit_quantity">Quantity</label>
                                <input type="number" name="edit_quantity" id="edit_quantity" class='form-control' min="0">
                                <br>
                                <label for="edit_bad_order_qty">Bad Order Quantity</label>
                                <input type="number" name="edit_bad_order_qty" id="edit_bad_order_qty" class='form-control' min="0">
                                <div id="edit-qty-error" class="text-danger" style="display:none;">
                                    Bad order quantity cannot exceed total quantity.
                                </div>
                            </div>
                            <button class="btn btn-primary mt-3" type="submit" name="edit-item">Update Item</button>
                        </form>
                    </div>
                </div>
            </div>
     </div>

     <!-- Payment History Modal -->
     <div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentHistoryModalLabel"><i class="fas fa-history me-2"></i>Payment History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class='align-middle'>
                                    <th>Date Paid</th>
                                    <th>Amount Paid</th>
                                    <th>Payment Method</th>
                                    <th>Reference No</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="payment-history-tbody">
                                <!-- Payment history rows will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Process Payment Modal -->
    <div class="modal fade" id="processPaymentModal" tabindex="-1" aria-labelledby="processPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="processPaymentModalLabel"><i class="fas fa-cash-register me-2"></i>Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="process-payment-form" method="POST" action="">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="date_paid" class="form-label">Date Paid</label>
                                <input type="date" class="form-control" id="date_paid" name="date_paid" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="amount_paid" class="form-label">Amount Paid</label>
                                <input type="text" class="form-control text-end" id="amount_paid" name="amount_paid" min="0" max="<?php echo $balance; ?>" step="0.01" required placeholder="0.00" autocomplete="off">
                                <small class="text-muted">Maximum payment: ₱<?php echo number_format($balance, 2); ?></small>
                            </div>
                            <div class="col-md-6">
                                <label for="payment_type" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_type" name="payment_type" required>
                                    <option value="Cash">Cash</option>
                                    <option value="GCash">GCash</option>
                                    <option value="Check">Check</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_no" class="form-label">Reference No</label>
                                <input type="text" class="form-control" id="reference_no" name="reference_no">
                            </div>
                            <div class="col-md-6">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="1"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" name="process-payment">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



        <div id="qtyWarningPopup"></div>
        <div id="creditorMessagePopup"></div>

        <script>
        // Make this function globally accessible
        function showCreditorMessagePopup(message, isSuccess = true) {
            const $popup = $("#creditorMessagePopup");
            $popup.stop(true, true); // Stop any current animations
            $popup.text(message);
            $popup.removeClass("popup-success popup-error");
            $popup.addClass(isSuccess ? "popup-success" : "popup-error");
            $popup.fadeIn(200).delay(1800).fadeOut(400);
        }
        </script>

     <script>
document.addEventListener('DOMContentLoaded', function () {
    // Show success/error messages using the popup system
    <?php if (isset($success_message)): ?>
        showCreditorMessagePopup('<?php echo addslashes($success_message); ?>', true);
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        showCreditorMessagePopup('<?php echo addslashes($error_message); ?>', false);
    <?php endif; ?>
    
    // Format amount input with commas and validate maximum
    const amountInput = document.getElementById('amount_paid');
    if (amountInput) {
        const maxAmount = parseFloat(amountInput.getAttribute('max')) || 0;
        
        amountInput.addEventListener('input', function(e) {
            // Remove all non-digit characters except decimal point
            let value = e.target.value.replace(/[^\d.]/g, '');
            
            // Ensure only one decimal point
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Convert to number for validation
            const numericValue = parseFloat(value) || 0;
            
            // Check if amount exceeds maximum
            if (numericValue > maxAmount) {
                // Reset to maximum amount
                value = maxAmount.toString();
                showCreditorMessagePopup(`Amount cannot exceed ₱${maxAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`, false);
            }
            
            // Format the whole number part with commas
            if (parts.length > 0) {
                const wholePart = value.split('.')[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                value = value.includes('.') ? wholePart + '.' + value.split('.')[1] : wholePart;
            }
            
            e.target.value = value;
        });
        
        // Handle form submission - remove commas before submitting
        const form = amountInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const amountValue = amountInput.value.replace(/,/g, '');
                amountInput.value = amountValue;
            });
        }
    }
    
    // Add event listener to buttons that open the transfer modal
    document.querySelectorAll('.open-modal-btn[data-bs-target="#transferStockModal"]').forEach(button => {
        button.addEventListener('click', function () {
            const spoId = this.getAttribute('data-spo-id');
            const productId = this.getAttribute('data-product-id');
            
            // Set the hidden form values
            document.querySelector('#transfer_spo_id').value = spoId;
            document.querySelector('#transfer_product_id').value = productId;
            
            // Load the item data using AJAX - now calling the same file with ajax parameter
            fetch(`spo_details.php?ajax=get_item_data&spo_id=${spoId}&product_id=${productId}`)
                .then(response => response.json())
                 .then(data => {
                     if (data.error) {
                         console.error('Error:', data.error);
                         return;
                     }
                     
                     // Populate the form with the retrieved data
                    document.querySelector('#item_name').textContent = data.item_name || 'N/A';
                    
                    // Display either part number or size
                    let partNumberOrSize = 'N/A';
                    if (data.oem_number && data.oem_number.trim() !== '') {
                        partNumberOrSize = data.oem_number;
                    } else if (data.product_size && data.product_size.trim() !== '') {
                        partNumberOrSize = data.product_size;
                    }
                    document.querySelector('#part_number_or_size').textContent = partNumberOrSize;
                    
                    document.querySelector('#product_brand').textContent = data.product_brand || 'N/A';
                    document.querySelector('#category').textContent = data.category || 'N/A';
                     
                     // Add transfer tracking information
                    document.querySelector('#total_qty').textContent = data.total_qty || '0';
                    document.querySelector('#transferred_qty').textContent = data.transferred_qty || '0';
                    document.querySelector('#remaining_qty').textContent = data.remaining_qty || '0';
                    document.querySelector('#bad_order_qty').textContent = data.bad_order_qty || '0';
                     
                     console.log('Transfer modal data:', {
                         total_qty: data.total_qty,
                         transferred_qty: data.transferred_qty,
                         bad_order_qty: data.bad_order_qty,
                         remaining_qty: data.remaining_qty
                     });
                     
                     // Set max value for transfer quantity
                    const transferQty = document.querySelector('#transfer_qty');
                     transferQty.max = data.remaining_qty;
                     
                     // Add event listener to validate quantity
                    transferQty.addEventListener('input', function() {
                        const maxQty = parseFloat(document.querySelector('#remaining_qty').textContent);
                         const enteredQty = parseFloat(this.value);
                         
                         console.log('Transfer validation - Max Qty:', maxQty, 'Entered Qty:', enteredQty);
                         
                         if (enteredQty > maxQty) {
                            document.querySelector('#qty-error').style.display = 'block';
                            document.querySelector('button[name="transfer-stock"]').disabled = true;
                         } else {
                            document.querySelector('#qty-error').style.display = 'none';
                            document.querySelector('button[name="transfer-stock"]').disabled = false;
                         }
                     });
                 })
                 .catch(error => {
                     console.error('Error loading item data:', error);
                });
        });
    });
    
    // Add event listener to buttons that open the edit modal
    document.querySelectorAll('.edit-item-btn').forEach(button => {
        button.addEventListener('click', function () {
            const spoItemId = this.getAttribute('data-spo-item-id');
            const itemName = this.getAttribute('data-item-name');
            const quantity = this.getAttribute('data-quantity');
            const badOrderQty = this.getAttribute('data-bad-order-qty');
            
            // Set the hidden form values
            document.querySelector('#edit_spo_item_id').value = spoItemId;
            document.querySelector('#edit_item_name').textContent = itemName;
            document.querySelector('#edit_quantity').value = quantity;
            document.querySelector('#edit_bad_order_qty').value = badOrderQty;
            
            // Add event listener to validate bad order quantity
            const editQuantity = document.querySelector('#edit_quantity');
            const editBadOrderQty = document.querySelector('#edit_bad_order_qty');
            const editQtyError = document.querySelector('#edit-qty-error');
            const editSubmitBtn = document.querySelector('button[name="edit-item"]');
            
            function validateEditQuantities() {
                const totalQty = parseInt(editQuantity.value) || 0;
                const badOrderQty = parseInt(editBadOrderQty.value) || 0;
                
                if (badOrderQty > totalQty) {
                    editQtyError.style.display = 'block';
                    editSubmitBtn.disabled = true;
                } else {
                    editQtyError.style.display = 'none';
                    editSubmitBtn.disabled = false;
                }
            }
            
            editQuantity.addEventListener('input', validateEditQuantities);
            editBadOrderQty.addEventListener('input', validateEditQuantities);
                     });
                 });
         });
        
        // Payment functionality
        // Load payment history when modal opens
        $('#paymentHistoryModal').on('shown.bs.modal', function() {
            loadPaymentHistory();
        });
        
        // Helper function to format currency with commas
        function formatCurrency(amount) {
            if (amount === null || amount === undefined) return '0.00';
            const num = parseFloat(amount);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Helper function to format dates
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString; // Return original if invalid date
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        function loadPaymentHistory() {
            console.log('Loading payment history...');
            fetch('spo_details.php?ajax=get_payment_history&id=<?php echo $spo_id; ?>')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log('Payment history data:', data);
                    const tbody = document.getElementById('payment-history-tbody');
                    tbody.innerHTML = '';
                    
                    // Check if there's an error in the response
                    if (data.error) {
                        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error: ${data.error}</td></tr>`;
                        return;
                    }
                    
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payment history found</td></tr>';
                        return;
                    }
                    
                    data.forEach(payment => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${formatDate(payment.date_paid)}</td>
                            <td class="text-end">₱${formatCurrency(payment.amount_paid)}</td>
                            <td>${payment.payment_method}</td>
                            <td>${payment.reference_no || '-'}</td>
                            <td>${payment.remarks || '-'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading payment history:', error);
                    const tbody = document.getElementById('payment-history-tbody');
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error loading payment history: ${error.message}</td></tr>`;
                });
        }
        
        // Handle payment form submission
        $('#process-payment-form').on('submit', function(e) {
            const amountPaid = parseFloat($('#amount_paid').val());
            const maxAmount = parseFloat($('#amount_paid').attr('max'));
            const paymentMethod = $('#payment_type').val();
            const referenceNo = $('#reference_no').val().trim();
            
            if (amountPaid > maxAmount) {
                e.preventDefault();
                alert('Payment amount cannot exceed the remaining balance.');
                return false;
            }
            
            if (amountPaid <= 0) {
                e.preventDefault();
                alert('Payment amount must be greater than 0.');
                return false;
            }
            
            // Check if reference number is required
            if ((paymentMethod === 'GCash' || paymentMethod === 'Check' || paymentMethod === 'Bank Transfer') && !referenceNo) {
                e.preventDefault();
                alert('Reference number is required for ' + paymentMethod + ' payments.');
                return false;
            }
            
            // If validation passes, allow form submission and page will reload
            // The PHP backend will redirect back to this page with success message
        });
        
        // Update payment form when modal opens
        $('#processPaymentModal').on('shown.bs.modal', function() {
            // Reset form
            $('#process-payment-form')[0].reset();
            $('#date_paid').val(new Date().toISOString().split('T')[0]);
            
            // Update max amount and display remaining balance
            const balance = <?php echo $balance ?? 0; ?>;
            $('#amount_paid').attr('max', balance);
            $('#amount_paid').next('small').text(`Maximum payment: ₱${formatCurrency(balance)}`);
            
            // Set initial reference number requirement
            updateReferenceNumberRequirement();
        });
        
        // Handle payment method change
        $('#payment_type').on('change', function() {
            updateReferenceNumberRequirement();
        });
        
        function updateReferenceNumberRequirement() {
            const paymentMethod = $('#payment_type').val();
            const referenceNoField = $('#reference_no');
            const referenceNoLabel = $('label[for="reference_no"]');
            
            if (paymentMethod === 'GCash' || paymentMethod === 'Check' || paymentMethod === 'Bank Transfer') {
                referenceNoField.prop('required', true);
                referenceNoLabel.html('Reference No <span class="text-danger">*</span>');
            } else {
                referenceNoField.prop('required', false);
                referenceNoLabel.text('Reference No');
            }
        }

        // Add event listener for bad orders button
        document.addEventListener('click', function(e) {
            if (e.target.closest('.bad-orders-btn')) {
                const button = e.target.closest('.bad-orders-btn');
                const spoId = button.getAttribute('data-spo-id');
                const invoice = button.getAttribute('data-invoice');
                
                openBadOrdersModal(spoId, invoice);
            }
        });

        // Function to open bad orders modal
        function openBadOrdersModal(spoId, invoice) {
            // Set modal title
            document.getElementById('bad-orders-invoice').textContent = invoice;
            document.getElementById('bad-orders-spo-id').value = spoId;
            
            // Load bad orders data
            loadBadOrdersData(spoId);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('badOrdersModal'));
            modal.show();
        }

        // Function to load bad orders data
        function loadBadOrdersData(spoId) {
            const tbody = document.getElementById('bad-orders-tbody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>';
            
            // Fetch bad orders data from server
            fetch(`get_spo_items.php?spo_id=${spoId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log('Bad orders data:', data);
                    tbody.innerHTML = '';
                    
                    // Check if there's an error in the response
                    if (data.error) {
                        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error: ${data.error}</td></tr>`;
                        return;
                    }
                    
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No items found</td></tr>';
                        return;
                    }
                    
                    data.forEach((item, index) => {
                        // Determine part number or size to display
                        let partNumberOrSize = '';
                        if (item.oem_number && item.oem_number.trim() !== '') {
                            partNumberOrSize = item.oem_number;
                        } else if (item.product_size && item.product_size.trim() !== '') {
                            partNumberOrSize = item.product_size;
                        } else {
                            partNumberOrSize = 'N/A';
                        }
                        
                        // Create product name with part number/size
                        const productDisplay = item.item_name || 'N/A';
                        const productWithIdentifier = partNumberOrSize !== 'N/A' 
                            ? `${productDisplay}<br><small class="text-muted">${partNumberOrSize}</small>`
                            : productDisplay;
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${productWithIdentifier}</td>
                            <td>${item.product_brand || ''}</td>
                            <td>${item.unit || 'N/A'}</td>
                            <td class="text-center">${item.quantity || 0}</td>
                            <td class="text-center">${item.bad_order_qty || 0}</td>
                            <td>
                                <input type="number" name="bad_order_qty[]" class="form-control form-control-sm" 
                                       value="${item.bad_order_qty > 0 ? item.bad_order_qty : ''}" 
                                       placeholder="0" min="0" max="${item.quantity || 0}"
                                       data-item-id="${item.spo_item_id}">
                                <input type="hidden" name="spo_item_id[]" value="${item.spo_item_id}">
                            </td>
                            <td>
                                <input type="text" name="bad_order_remarks[]" class="form-control form-control-sm" 
                                       value="${item.bad_order_remarks || ''}" placeholder="Enter remarks"
                                       data-item-id="${item.spo_item_id}">
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading bad orders data:', error);
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error loading data: ${error.message}</td></tr>`;
                });
        }
     </script>

    <!-- Bad Orders Modal -->
    <div class="modal fade" id="badOrdersModal" tabindex="-1" aria-labelledby="badOrdersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="badOrdersModalLabel">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                        Manage Bad Orders
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="text-muted">Purchase Order: <span id="bad-orders-invoice" class="fw-bold"></span></h6>
                    </div>
                    
                    <form id="badOrdersForm" method="POST" action="update_bad_orders.php">
                        <input type="hidden" name="spo_id" id="bad-orders-spo-id">
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Brand</th>
                                        <th>Unit</th>
                                        <th>Ordered Qty</th>
                                        <th>Current Bad Orders</th>
                                        <th>Bad Order Qty</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="bad-orders-tbody">
                                    <!-- Items will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning" name="update-bad-orders">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </body>
  </html>       