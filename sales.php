<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sale'])) {
    // Get customer information
    $customer_firstName = mysqli_real_escape_string($mysqli, $_POST['customer_firstName'] ?? '');
    $customer_middleName = mysqli_real_escape_string($mysqli, $_POST['customer_middleName'] ?? '');
    $customer_lastName = mysqli_real_escape_string($mysqli, $_POST['customer_lastName'] ?? '');
    $customer_suffix = mysqli_real_escape_string($mysqli, $_POST['customer_suffix'] ?? '');
    
    // Get transaction information
    $transaction_date = mysqli_real_escape_string($mysqli, $_POST['transaction_date'] ?? '');
    $payment_method = mysqli_real_escape_string($mysqli, $_POST['payment_method'] ?? '');
    $discount_percentage = (float)($_POST['discount_percentage'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $external_receipt_no = mysqli_real_escape_string($mysqli, $_POST['external_receipt_no'] ?? '');
    
    // Get credit information if payment method is credit
    $creditor_name = '';
    $due_date = '';
    $down_payment = 0;
    $interest = 0;
    if ($payment_method === 'Credit') {
        $creditor_name = mysqli_real_escape_string($mysqli, $_POST['creditor_name'] ?? '');
        $due_date = mysqli_real_escape_string($mysqli, $_POST['due_date'] ?? '');
        $down_payment = (float)($_POST['down_payment'] ?? 0);
        $interest = (float)($_POST['interest'] ?? 0); // Store as simple decimal
    }
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Generate invoice_no: INV-YYYY-00001
        $year = date('Y');
        $prefix = "INV-$year-";
        $sql = "SELECT invoice_no FROM purchase_transactions WHERE invoice_no LIKE ? ORDER BY invoice_no DESC LIMIT 1";
        $like = $prefix . '%';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $stmt->bind_result($last_invoice_no);
        $stmt->fetch();
        $stmt->close();
        if ($last_invoice_no) {
            $last_number = intval(substr($last_invoice_no, strlen($prefix)));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        $invoice_no = $prefix . str_pad($new_number, 5, '0', STR_PAD_LEFT);
        
        // Calculate subtotal from products
        $subtotal = 0;
        $product_ids = $_POST['product_id'] ?? [];
        $quantities = $_POST['purchased_quantity'] ?? [];
        $prices = $_POST['unit_price_at_purchase'] ?? [];
         $discount_amounts = $_POST['product_discount_amount'] ?? [];
         $discount_percents = $_POST['product_discount_percent'] ?? [];
        $markups = $_POST['product_markup'] ?? [];
        $subtotals = $_POST['product_subtotal'] ?? [];
        
        foreach ($product_ids as $index => $product_id) {
            if (!empty($product_id) && !empty($quantities[$index])) {
                $subtotal += (float)$subtotals[$index];
            }
        }
        
        // Determine customer_type based on payment method and creditor
        $customer_type = '';
        if ($payment_method === 'Cash') {
            $customer_type = 'Walk-in';
        } elseif ($payment_method === 'Credit' && !empty($creditor_name)) {
            // Check if creditor is an organization or individual
            $check_creditor_type = "SELECT org_name FROM tbl_creditors WHERE 
                                   (org_name = ?) OR 
                                   (CONCAT(creditor_fn, ' ', creditor_mn, ' ', creditor_ln, ' ', creditor_suffix) = ?)";
            $stmt = $mysqli->prepare($check_creditor_type);
            $stmt->bind_param('ss', $creditor_name, $creditor_name);
            $stmt->execute();
            $stmt->bind_result($org_name);
            $stmt->fetch();
            $stmt->close();
            
            if (!empty($org_name)) {
                $customer_type = 'Organization - Creditor';
            } else {
                $customer_type = 'Individual - Creditor';
            }
        }
        
        // Insert sale header into purchase_transactions table
        $insert_sale = "INSERT INTO purchase_transactions (transaction_date, payment_method, subtotal, discount_percentage, grand_total, invoice_no, customer_firstName, customer_middleName, customer_lastName, customer_suffix, customer_type, external_receipt_no, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $mysqli->prepare($insert_sale);
        $stmt->bind_param('ssddssssssss', $transaction_date, $payment_method, $subtotal, $discount_percentage, $grand_total, $invoice_no, $customer_firstName, $customer_middleName, $customer_lastName, $customer_suffix, $customer_type, $external_receipt_no);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating sale: " . $mysqli->error);
        }
        
        $transaction_id = $mysqli->insert_id;
        $stmt->close();
        
        // Insert sale items into purchase_transaction_details table
        foreach ($product_ids as $index => $product_id) {
            if (!empty($product_id) && !empty($quantities[$index])) {
                $quantity = (int)$quantities[$index];
                $price = (float)$prices[$index];
                 $discAmount = isset($discount_amounts[$index]) ? (float)$discount_amounts[$index] : 0;
                 $discPercent = isset($discount_percents[$index]) ? (float)$discount_percents[$index] : 0;
                 if ($discPercent < 0) { $discPercent = 0; }
                 if ($discPercent > 100) { $discPercent = 100; }
                 // Compute effective discount per unit: prefer amount; else percent of price
                 $discount = $discAmount > 0 ? $discAmount : (($discPercent > 0) ? ($price * ($discPercent / 100.0)) : 0);
                $markup = (float)$markups[$index];
                $subtotal = (float)$subtotals[$index];
                
                // Insert sale item
                 $insert_item = "INSERT INTO purchase_transaction_details (transaction_id, product_id, purchased_quantity, unit_price_at_purchase, product_discount, product_markup, product_subtotal) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                 $stmt = $mysqli->prepare($insert_item);
                 $stmt->bind_param('iiidddd', $transaction_id, $product_id, $quantity, $price, $discount, $markup, $subtotal);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting sale item: " . $mysqli->error);
                }
                $stmt->close();
                
                // Update inventory
                $update_inventory = "UPDATE tbl_inventory SET stock_level = stock_level - ? WHERE product_id = ?";
                $stmt = $mysqli->prepare($update_inventory);
                $stmt->bind_param('ii', $quantity, $product_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating inventory: " . $mysqli->error);
                }
                $stmt->close();
            }
        }
        
        // If payment method is credit, insert into tbl_credit_transactions
        if ($payment_method === 'Credit' && !empty($creditor_name)) {
            // Get creditor_id from creditor name
            $get_creditor_id = "SELECT creditor_id FROM tbl_creditors WHERE 
                               (org_name = ?) OR 
                               (CONCAT(creditor_fn, ' ', creditor_mn, ' ', creditor_ln, ' ', creditor_suffix) = ?)";
            $stmt = $mysqli->prepare($get_creditor_id);
            $stmt->bind_param('ss', $creditor_name, $creditor_name);
            $stmt->execute();
            $stmt->bind_result($creditor_id);
            $stmt->fetch();
            $stmt->close();
            
            if ($creditor_id) {
                // Calculate total with interest
                $total_with_interest = $grand_total + $interest;
                
                $insert_credit = "INSERT INTO tbl_credit_transactions (transaction_id, creditor_id, due_date, interest, total_with_interest, status) 
                                 VALUES (?, ?, ?, ?, ?, 'Not Paid')";
                $stmt = $mysqli->prepare($insert_credit);
                $stmt->bind_param('iisdd', $transaction_id, $creditor_id, $due_date, $interest, $total_with_interest);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creating credit transaction: " . $mysqli->error);
                }
                $credit_id = $mysqli->insert_id;
                $stmt->close();
                
                // If there's a down payment, save it to tbl_credit_payments
                if ($down_payment > 0) {
                    $insert_payment = "INSERT INTO tbl_credit_payments (credit_id, date_paid, amount_paid, payment_type, reference_no, recorded_by, remarks) 
                                     VALUES (?, ?, ?, 'Cash', ?, ?, 'Initial down payment')";
                    $stmt = $mysqli->prepare($insert_payment);
                    $stmt->bind_param('isdss', $credit_id, $transaction_date, $down_payment, $invoice_no, $_SESSION['username']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error creating credit payment: " . $mysqli->error);
                    }
                    $stmt->close();
                }
            }
        }
        
        // Commit transaction
        $mysqli->commit();
        $success_message = "Sale completed successfully! Invoice: " . $invoice_no;
        
    } catch (Exception $e) {
        // Rollback transaction
        $mysqli->rollback();
        $error_message = "Error processing sale: " . $e->getMessage();
    }
}

// Get products for dropdown from inventory with stock
$products_query = "SELECT 
                    m.product_id, 
                    m.item_name, 
                    m.oem_number, 
                    m.product_brand, 
                    m.unit, 
                    m.selling_price,
                    i.inventory_id,
                    i.stock_level as total_stock,
                    i.storage_location as locations
                   FROM tbl_inventory i
                   INNER JOIN tbl_masterlist m ON i.product_id = m.product_id
                   WHERE m.status = 'Active' AND i.stock_level > 0
                   ORDER BY m.item_name, i.storage_location";
$products_result = $mysqli->query($products_query);
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Icons -->
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">
    
    <!-- jQuery -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <script src="js/jquery-ui.min.js"></script>
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">
    <link rel="stylesheet" href="css/style.css">
    
    <title>Sales</title>
</head>
<body>
    <?php $activePage = 'sales'; include 'navbar.php'; ?>
    
    <main class="main-content" style='padding-top: 2rem;'>
        <div class="main-container">
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form id="salesForm" method="POST" action="">
                        <!-- Customer and Date Section -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label">Customer:</label>
                                <div class="d-flex gap-2">
                                <input type="text" class="form-control" id="customer_firstName" name="customer_firstName" placeholder='First Name'>
                                <input type="text" class="form-control" id="customer_middleName" name="customer_middleName" placeholder='Middle Name'>
                                <input type="text" class="form-control" id="customer_lastName" name="customer_lastName" placeholder='Last Name'>
                                <input type="text" class="form-control" id="customer_suffix" name="customer_suffix" placeholder='Suffix'>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="transaction_date" class="form-label">Date:</label>
                                <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash" value="Cash" checked>
                                    <label class="form-check-label" for="cash">Cash</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="credit" value="Credit">
                                    <label class="form-check-label" for="credit">Credit</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class='creditor-fields' style='display: none;'>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="creditor_name" class="form-label">Creditor Name:</label>
                                    <select name="creditor_name" id="creditor_name" class="form-select">
                                        <option value="">Select Creditor</option>
                                        <?php
                                            $creditor_query = "SELECT creditor_id, creditor_fn, creditor_mn, creditor_ln, creditor_suffix, org_name FROM tbl_creditors";
                                            $creditor_result = $mysqli->query($creditor_query);
                                            while ($creditor_row = $creditor_result->fetch_assoc()) {
                                                if(!empty($creditor_row['org_name'])){
                                                    echo "
                                                    <option value='" . $creditor_row['org_name']."'>" . $creditor_row['org_name'] . "</option>";
                                                }
                                                else{
                                                    echo "
                                                    <option value='" . $creditor_row['creditor_fn'] . " " . $creditor_row['creditor_mn'] . " " . $creditor_row['creditor_ln'] . " " . $creditor_row['creditor_suffix']."'>" . $creditor_row['creditor_fn'] . " " . $creditor_row['creditor_mn'] . " " . $creditor_row['creditor_ln'] . " " . $creditor_row['creditor_suffix'] . "</option>";
                                                }
                                            }                                    
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="interest" class="form-label">Interest Rate:</label>
                                    <input type="number" name="interest" id="interest" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label for="due_date" class="form-label">Due Date:</label>
                                    <input type="date" name="due_date" id="due_date" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label for="down_payment" class="form-label">Down Payment:</label>
                                    <input type="text" name="down_payment" id="down_payment" class="form-control" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Table -->
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered" id="productsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Stock</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th class="text-center">
                                            Discount
                                            <div style="font-size:0.65rem; color:#64748b; line-height:1; margin-top:2px; white-space:nowrap;">Amount | Percent</div>
                                        </th>
                                        <th>Mark Up</th>
                                        <th>Subtotal</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="productsTbody">
                                    <tr class="product-row" data-row="1">
                                        <td>
                                            <select name="product_id[]" class="form-select product-select" required style='width: 500px;'>
                                                <option value="">Select Product</option>
                                                <?php foreach ($products as $product): ?>
                                                <option value="<?php echo $product['product_id']; ?>" 
                                                        data-price="<?php echo $product['selling_price']; ?>"
                                                        data-brand="<?php echo htmlspecialchars($product['product_brand']); ?>"
                                                        data-unit="<?php echo htmlspecialchars($product['unit']); ?>"
                                                        data-stock="<?php echo $product['total_stock']; ?>"
                                                        data-locations="<?php echo htmlspecialchars($product['locations']); ?>"
                                                        data-inventory-id="<?php echo $product['inventory_id']; ?>">
                                                    <?php echo htmlspecialchars($product['item_name']); ?> 
                                                    <?php echo htmlspecialchars($product['oem_number']); ?> - 
                                                     <?php echo htmlspecialchars($product['product_brand']); ?>
                                                     [<?php echo htmlspecialchars($product['locations']); ?>]
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <span class="stock-display badge bg-secondary">-</span>
                                        </td>
                                        <td>
                                            <input type="number" name="purchased_quantity[]" class="form-control quantity-input" 
                                                   min="1" value="" placeholder="0" required>
                                        </td>
                                        <td>
                                            <input type="number" name="unit_price_at_purchase[]" class="form-control price-input" 
                                                   step="0.01" min="0" placeholder="0.00" readonly>
                                        </td>
                                        <td>
                                            <div class="row gx-2">
                                                <div class="col-6">
                                                    <input type="number" name="product_discount_amount[]" class="form-control discount-amount" step="0.01" min="0" placeholder="0.00">
                                                </div>
                                                <div class="col-6">
                                                    <input type="number" name="product_discount_percent[]" class="form-control discount-percent" step="0.1" min="0" max="100" placeholder="0.0">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="product_markup[]" class="form-control markup-input" 
                                                   step="0.01" min="0" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number" name="product_subtotal[]" class="form-control subtotal-input" 
                                                   step="0.01" placeholder="0.00" readonly>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Add Product Button -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" id="addProductBtn">
                                <i class="fas fa-plus me-2"></i>Add Another Product
                            </button>
                        </div>
                        
                        <!-- Summary Section -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Sub Total:</label>
                                <input type="text" class="form-control" id="subtotal" readonly placeholder='0.00'>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Global Discount: (%)</label>
                                <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" placeholder='0' min="0" max="100" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Total Amount:</label>
                                <input type="text" class="form-control" id="grand_total" readonly placeholder='0.00'>
                                <input type="hidden" name="grand_total" id="grand_total_hidden" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">External Receipt Number:</label>
                                <input type="text" class="form-control" id="external_receipt_no" name="external_receipt_no" placeholder='Enter receipt number'>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary" name="submit_sale">
                                <i class="fas fa-save me-2"></i>Submit Sale
                            </button>
                            <button type="button" class="btn btn-secondary" id="resetBtn">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

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
    
    // Show success popup if there's a success message
    <?php if (isset($success_message)): ?>
    $(document).ready(function() {
        showCreditorMessagePopup("<?php echo htmlspecialchars($success_message); ?>", true);
    });
    <?php endif; ?>
    </script>
    
    <script>
        $(document).ready(function() {
            console.log('Document ready - initializing sales page');
            
            let rowCounter = 1;
            
            // Initialize first row
            console.log('Initializing row 1');
            initializeRow(1);
            
            // Add product button
            $('#addProductBtn').click(function() {
                rowCounter++;
                addNewRow();
            });
            
            // Remove row button
            $(document).on('click', '.remove-row-btn', function() {
                if ($('.product-row').length > 1) {
                    $(this).closest('tr').remove();
                    updateRowNumbers();
                    calculateTotals();
                }
            });
        
        function addNewRow() {
            const newRow = `
                <tr class="product-row" data-row="${rowCounter}">
                    <td>
                        <select name="product_id[]" class="form-select product-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>" 
                                    data-price="<?php echo $product['selling_price']; ?>"
                                    data-brand="<?php echo htmlspecialchars($product['product_brand']); ?>"
                                    data-unit="<?php echo htmlspecialchars($product['unit']); ?>"
                                    data-stock="<?php echo $product['total_stock']; ?>"
                                    data-locations="<?php echo htmlspecialchars($product['locations']); ?>"
                                    data-inventory-id="<?php echo $product['inventory_id']; ?>">
                                <?php echo htmlspecialchars($product['item_name']); ?> 
                                <?php echo htmlspecialchars($product['oem_number']); ?> - 
                                 <?php echo htmlspecialchars($product['product_brand']); ?>
                                 
                                 [<?php echo htmlspecialchars($product['locations']); ?>]
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <span class="stock-display badge bg-secondary">-</span>
                    </td>
                    <td>
                        <input type="number" name="purchased_quantity[]" class="form-control quantity-input" 
                               min="1" value="" placeholder="0" required>
                    </td>
                    <td>
                        <input type="number" name="unit_price_at_purchase[]" class="form-control price-input" 
                               step="0.01" min="0" placeholder="0.00" readonly>
                    </td>
                    <td>
                        <div class="row gx-2">
                            <div class="col-6">
                                <input type="number" name="product_discount_amount[]" class="form-control discount-amount" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="col-6">
                                <input type="number" name="product_discount_percent[]" class="form-control discount-percent" step="0.1" min="0" max="100" placeholder="0.0">
                            </div>
                        </div>
                    </td>
                    <td>
                        <input type="number" name="product_markup[]" class="form-control markup-input" 
                               step="0.01" min="0" placeholder="0.00">
                    </td>
                    <td>
                        <input type="number" name="product_subtotal[]" class="form-control subtotal-input" 
                               step="0.01" readonly>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            $('#productsTbody').append(newRow);
            initializeRow(rowCounter);
        }
        
        function initializeRow(rowNum) {
            console.log('Initializing row:', rowNum);
            const $row = $(`.product-row[data-row="${rowNum}"]`);
            console.log('Found row element:', $row.length > 0);
            
            // Product selection change
            $row.find('.product-select').change(function() {
                console.log('Product selection changed');
                const selectedOption = $(this).find('option:selected');
                console.log('Selected option:', selectedOption.val());
                
                const price = parseFloat(selectedOption.data('price')) || 0;
                const stock = parseInt(selectedOption.data('stock')) || 0;
                const locations = selectedOption.data('locations') || '';
                const inventoryId = selectedOption.data('inventory-id') || '';
                
                console.log('Retrieved data - Price:', price, 'Stock:', stock, 'Locations:', locations);
                console.log('Setting price input to:', price.toFixed(2));
                $row.find('.price-input').val(price.toFixed(2));
                
                // Store inventory ID for later use in form submission
                $row.attr('data-inventory-id', inventoryId);
                
                // Update quantity max attribute based on stock
                const quantityInput = $row.find('.quantity-input');
                quantityInput.attr('max', stock);
                
                // Update stock display
                const stockDisplay = $row.find('.stock-display');
                console.log('Setting stock display to:', stock);
                if (stock > 0) {
                    stockDisplay.text(stock).removeClass('bg-secondary bg-danger').addClass('bg-success');
                    quantityInput.attr('title', `Available stock: ${stock} in ${locations}`);
                    quantityInput.removeClass('border-danger').addClass('border-success');
                } else {
                    stockDisplay.text('0').removeClass('bg-secondary bg-success').addClass('bg-danger');
                    quantityInput.attr('title', 'Out of stock');
                    quantityInput.removeClass('border-success').addClass('border-danger');
                }
                
                calculateRowTotal($row);
            });
            
            // Input changes
            $row.find('.quantity-input, .discount-amount, .discount-percent, .markup-input').on('input', function() {
                // Validate quantity against stock
                if ($(this).hasClass('quantity-input')) {
                    const quantity = parseInt($(this).val()) || 0;
                    const maxStock = parseInt($(this).attr('max')) || 0;
                    
                    if (quantity > maxStock && maxStock > 0) {
                        alert(`Cannot order more than available stock (${maxStock})`);
                        $(this).val(maxStock);
                    }
                }
                
                calculateRowTotal($row);
            });
        }
        
        function calculateRowTotal($row) {
            const quantity = parseFloat($row.find('.quantity-input').val()) || 0;
            const price = parseFloat($row.find('.price-input').val()) || 0;
            const dAmt = parseFloat($row.find('.discount-amount').val()) || 0;
            let dPct = parseFloat($row.find('.discount-percent').val()) || 0;
            if (dPct < 0) dPct = 0; if (dPct > 100) dPct = 100;
            const discount = dAmt > 0 ? dAmt : (dPct > 0 ? (price * (dPct / 100.0)) : 0);
            const markup = parseFloat($row.find('.markup-input').val()) || 0;
            
            // Apply discount and markup to each unit price
            const adjustedPrice = price - discount + markup;
            const subtotal = quantity * adjustedPrice;
            
            $row.find('.subtotal-input').val(subtotal.toFixed(2));
            
            calculateTotals();
        }
        
        function calculateTotals() {
            let totalItems = 0;
            let totalQty = 0;
            let subtotal = 0;
            
            $('.product-row').each(function() {
                const $row = $(this);
                const productId = $row.find('.product-select').val();
                
                if (productId) {
                    totalItems++;
                    totalQty += parseFloat($row.find('.quantity-input').val()) || 0;
                    subtotal += parseFloat($row.find('.subtotal-input').val()) || 0;
                }
            });
            
            // Apply global discount
            const globalDiscountPercent = parseFloat($('#discount_percentage').val()) || 0;
            const globalDiscountAmount = (subtotal * globalDiscountPercent) / 100;
            const grandTotal = subtotal - globalDiscountAmount;
            
            $('#subtotal').val('₱' + subtotal.toFixed(2));
            $('#grand_total').val('₱' + grandTotal.toFixed(2));
            $('#grand_total_hidden').val(grandTotal.toFixed(2));
            
            // Update discount label
            updateDiscountLabel();
        }
        
        function updateRowNumbers() {
            $('.product-row').each(function(index) {
                $(this).attr('data-row', index + 1);
            });
        }
        
        // Global discount change
        $('#discount_percentage').on('input', function() {
            calculateTotals();
            updateDiscountLabel();
        });
        
        // Update discount label with peso equivalent
        function updateDiscountLabel() {
            const discountPercent = parseFloat($('#discount_percentage').val()) || 0;
            let subtotal = 0;
            
            // Calculate current subtotal
            $('.product-row').each(function() {
                const $row = $(this);
                const productId = $row.find('.product-select').val();
                if (productId) {
                    subtotal += parseFloat($row.find('.subtotal-input').val()) || 0;
                }
            });
            
            const discountAmount = (subtotal * discountPercent) / 100;
            const discountLabel = `Global Discount: (₱${discountAmount.toFixed(2)})`;
            $('label[for="discount_percentage"]').text(discountLabel);
        }
        
        // Reset button
        $('#resetBtn').click(function() {
            if (confirm('Are you sure you want to reset the form?')) {
                $('#salesForm')[0].reset();
                $('#sale_date').val(new Date().toISOString().split('T')[0]);
                $('.product-row:not(:first)').remove();
                $('.product-row:first').find('input').val('');
                $('.product-row:first').find('select').val('');
                $('.product-row:first').find('.quantity-input').val('');
                $('.product-row:first').find('.discount-input').val('0');
                calculateTotals();
            }
        });
        
        // Payment method change handler
        $('input[name="payment_method"]').change(function() {
            if ($(this).val() === 'Credit') {
                $('.creditor-fields').show();
            } else {
                $('.creditor-fields').hide();
            }
        });
        
        // Form validation
        $('#salesForm').submit(function(e) {
            let hasProducts = false;
            $('.product-select').each(function() {
                if ($(this).val()) {
                    hasProducts = true;
                    return false;
                }
            });
            
            if (!hasProducts) {
                e.preventDefault();
                alert('Please add at least one product to the sale.');
                return false;
            }
        });
        
        // Show success modal if it exists
        <?php if (isset($success_message)): ?>
        $('#successModal').modal('show');
        <?php endif; ?>
        });
    </script>
    
    <style>
        /* Readonly Price Input Styling */
        .price-input[readonly] {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
            font-weight: 500;
        }
        
        .price-input[readonly]:focus {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            box-shadow: none;
        }
        
        /* Stock Display Badge Styling */
        .stock-display {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
        
        /* Product Select Styling */
        .product-select {
            font-size: 0.875rem;
        }
        
        .product-select option {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Popup Message Styling */
        #creditorMessagePopup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            padding: 20px 30px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            display: none;
            max-width: 400px;
            text-align: center;
        }
        
        .popup-success {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .popup-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
    </style>
</body>
</html>
