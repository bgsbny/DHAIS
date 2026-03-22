<?php
include('mycon.php');

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle create purchase order form submission
if (isset($_POST['save-purchase'])) {
    // Debug: Log the POST data
    error_log("POST data received: " . print_r($_POST, true));
    
    // Test database connection and table structure
    $test_query = "SHOW CREATE TABLE tbl_spo";
    $test_result = $mysqli->query($test_query);
    if ($test_result) {
        $table_info = $test_result->fetch_assoc();
        error_log("Table structure: " . print_r($table_info, true));
    }
    
    // Check MySQL mode
    $mode_result = $mysqli->query("SELECT @@sql_mode");
    if ($mode_result) {
        $mode = $mode_result->fetch_row()[0];
        error_log("MySQL mode: $mode");
    }
    
    $supplier_id = intval($_GET['id']);
    $date_ordered = mysqli_real_escape_string($mysqli, $_POST['date_ordered']);
    $transaction_method = mysqli_real_escape_string($mysqli, $_POST['transaction_type']);
    $invoice_number = mysqli_real_escape_string($mysqli, $_POST['invoice_number']);
    $global_discount_amount = floatval($_POST['global_discount_amount'] ?? 0);
    $global_discount_percent = floatval($_POST['global_discount_percent'] ?? 0);
    $spo_status = 'Not Delivered'; // Default status
    
    // Calculate totals
    $subtotal = 0;
    $grand_total = 0;
    
    // Calculate subtotal from form data
    if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
        $product_ids = $_POST['product_id'];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['price'] ?? [];
        $line_discounts = $_POST['line_discount'] ?? [];
        $line_subtotals = $_POST['line_subtotal'] ?? [];
        
        // Debug: Log the arrays
        error_log("Product IDs: " . print_r($product_ids, true));
        error_log("Quantities: " . print_r($quantities, true));
        error_log("Prices: " . print_r($prices, true));
        
        foreach ($product_ids as $index => $product_id) {
            if (!empty($product_id)) {
                $quantity = intval($quantities[$index] ?? 1);
                $price = floatval($prices[$index] ?? 0);
                $line_discount = floatval($line_discounts[$index] ?? 0);
                $line_discount_percent = floatval($line_discount_percents[$index] ?? 0);
                $line_subtotal = floatval($line_subtotals[$index] ?? 0);
                
                if ($line_subtotal == 0) {
                    // Calculate base amount
                    $base_amount = $quantity * $price;
                    
                    // Calculate percentage discount amount
                    $percent_discount_amount = ($base_amount * $line_discount_percent) / 100;
                    
                    // Total discount is amount + percentage
                    $total_line_discount = $line_discount + $percent_discount_amount;
                    
                    $line_subtotal = $base_amount - $total_line_discount;
                }
                $subtotal += $line_subtotal;
                
                error_log("Processing item $index: product_id=$product_id, quantity=$quantity, price=$price, line_discount=$line_discount, line_discount_percent=$line_discount_percent, line_subtotal=$line_subtotal");
            }
        }
    }
    
    // If no products found, set default values
    if ($subtotal == 0) {
        error_log("No products found in form data, setting default values");
        $subtotal = 0;
        $grand_total = 0;
    }
    
    $grand_total = $subtotal - $global_discount_amount - ($subtotal * $global_discount_percent / 100);
    
    // Insert into tbl_spo - include spo_id since it's not auto-increment
    $insert_spo = "INSERT INTO tbl_spo (spo_id, supplier_id, spo_status, date_ordered, invoice_number, 
                                        transaction_method, global_discount_amount, global_discount_percent, subtotal, grand_total) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($insert_spo);
    
    if (!$stmt) {
        $error_message = "Prepare failed: " . $mysqli->error;
        error_log("SPO prepare failed: " . $mysqli->error);
    } else {
        // Debug: Print the values being inserted
        error_log("SPO Insert - supplier_id: $supplier_id, status: $spo_status, date: $date_ordered, invoice: $invoice_number, method: $transaction_method, global_discount_amount: $global_discount_amount, global_discount_percent: $global_discount_percent, subtotal: $subtotal, grand_total: $grand_total");
        
        // Get the next available spo_id
        $max_id_query = "SELECT MAX(spo_id) as max_id FROM tbl_spo";
        $max_result = $mysqli->query($max_id_query);
        if ($max_result) {
            $max_row = $max_result->fetch_assoc();
            $next_spo_id = ($max_row['max_id'] ?? 0) + 1;
            error_log("Next available spo_id: $next_spo_id");
        } else {
            $next_spo_id = 1;
            error_log("Could not get max ID, using 1");
        }
        
        $stmt->bind_param("iisssssddd", $next_spo_id, $supplier_id, $spo_status, $date_ordered, $invoice_number, 
                         $transaction_method, $global_discount_amount, $global_discount_percent, $subtotal, $grand_total);
        
        if ($stmt->execute()) {
            $spo_id = $next_spo_id; // Use the ID we calculated
            error_log("SPO created successfully with ID: $spo_id");
            
            // Insert items into tbl_spo_items
            if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
                $product_ids = $_POST['product_id'];
                $quantities = $_POST['quantity'] ?? [];
                $prices = $_POST['price'] ?? [];
                $line_discounts = $_POST['line_discount'] ?? [];
                $line_discount_percents = $_POST['line_discount_percent'] ?? [];
                $line_subtotals = $_POST['line_subtotal'] ?? [];
                
                $insert_item = "INSERT INTO tbl_spo_items (spo_id, product_id, quantity, price, 
                               line_discount, line_subtotal) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_item = $mysqli->prepare($insert_item);
                
                if (!$stmt_item) {
                    $error_message = "Prepare failed for items: " . $mysqli->error;
                } else {
                    foreach ($product_ids as $index => $product_id) {
                        if (!empty($product_id)) {
                            $quantity = intval($quantities[$index] ?? 1);
                            $price = floatval($prices[$index] ?? 0);
                            $line_discount = floatval($line_discounts[$index] ?? 0);
                            $line_discount_percent = floatval($line_discount_percents[$index] ?? 0);
                            $line_subtotal = floatval($line_subtotals[$index] ?? 0);
                            
                            // Calculate total line discount (amount + percentage)
                            $base_amount = $quantity * $price;
                            $percent_discount_amount = ($base_amount * $line_discount_percent) / 100;
                            $total_line_discount = $line_discount + $percent_discount_amount;
                            
                            // Calculate line subtotal if not provided
                            if ($line_subtotal == 0) {
                                $line_subtotal = $base_amount - $total_line_discount;
                            }
                            
                            $stmt_item->bind_param("iiiddd", $spo_id, $product_id, $quantity, 
                                                  $price, $total_line_discount, $line_subtotal);
                            
                            if (!$stmt_item->execute()) {
                                $error_message = "Error saving item: " . $mysqli->error;
                                break;
                            }
                        }
                    }
                    $stmt_item->close();
                }
            }
            
            if (!isset($error_message)) {
                // If transaction type is Credit, save to tbl_spo_credits
                if ($transaction_method === 'Credit') {
                    $due_date = mysqli_real_escape_string($mysqli, $_POST['due_date'] ?? '');
                    $interest = floatval($_POST['interest'] ?? 0);
                    
                    // Calculate total with interest (interest is now a direct amount)
                    $total_with_interest = $grand_total + $interest;
                    
                    // Insert into tbl_spo_credits
                    $insert_credit = "INSERT INTO tbl_spo_credits (spo_id, supplier_id, due_date, interest, total_with_interest, status) 
                                     VALUES (?, ?, ?, ?, ?, 'Pending')";
                    $stmt_credit = $mysqli->prepare($insert_credit);
                    
                    if (!$stmt_credit) {
                        $error_message = "Prepare failed for credit: " . $mysqli->error;
                    } else {
                        $stmt_credit->bind_param("iisdd", $spo_id, $supplier_id, $due_date, $interest, $total_with_interest);
                        
                        if (!$stmt_credit->execute()) {
                            $error_message = "Error saving credit information: " . $mysqli->error;
                        } else {
                            $success_message = "Purchase order with credit terms created successfully!";
                        }
                        $stmt_credit->close();
                    }
                } else {
                    $success_message = "Purchase order created successfully!";
                }
                
                // Redirect to refresh the page and show updated data
                if (!isset($error_message)) {
                    echo "<script>setTimeout(function() { window.location.href='supplier_details.php?id=" . $supplier_id . "&tab=purchase'; }, 1500);</script>";
                }
            }
        } else {
            $error_message = "Error creating purchase order: " . $mysqli->error;
            error_log("SPO insertion failed: " . $mysqli->error);
        }
        $stmt->close();
    }
}

// Handle edit purchase order form submission
if (isset($_POST['edit-purchase-order'])) {
    $edit_spo_id = (int)$_POST['edit_spo_id'];
    $edit_invoice_number = mysqli_real_escape_string($mysqli, $_POST['edit_invoice_number']);
    $edit_status = mysqli_real_escape_string($mysqli, $_POST['edit_status']);
    $edit_date_delivered = mysqli_real_escape_string($mysqli, $_POST['edit_date_delivered']);
    
    // Update the purchase order
    $update_query = "UPDATE tbl_spo 
                     SET invoice_number = ?, spo_status = ?, date_delivered = ? 
                     WHERE spo_id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param('sssi', $edit_invoice_number, $edit_status, $edit_date_delivered, $edit_spo_id);
    
    if ($stmt->execute()) {
        $success_message = "Purchase order updated successfully!";
    } else {
        $error_message = "Error updating purchase order: " . $mysqli->error;
    }
    $stmt->close();
}

// Get supplier ID from URL
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($supplier_id <= 0) {
    header('Location: supplier.php');
    exit();
}

// Fetch supplier details
$supplier_query = "SELECT * FROM tbl_supplier WHERE supplier_id = ?";
$stmt = $mysqli->prepare($supplier_query);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: supplier.php');
    exit();
}

$supplier = $result->fetch_assoc();
$stmt->close();

// Fetch purchase history (SPO data)
$spo_query = "SELECT s.*, 
              COUNT(si.spo_item_id) as total_items,
              SUM(si.quantity) as total_quantity,
              SUM(si.bad_order_qty) as total_bad_orders,
              sc.total_with_interest,
              sc.spo_credit_id,
              COALESCE((SELECT SUM(amount_paid) FROM tbl_spo_payment WHERE spo_credit_id = sc.spo_credit_id), 0) as total_paid
              FROM tbl_spo s
              LEFT JOIN tbl_spo_items si ON s.spo_id = si.spo_id
              LEFT JOIN tbl_spo_credits sc ON s.spo_id = sc.spo_id
              WHERE s.supplier_id = ?
              GROUP BY s.spo_id
              ORDER BY s.date_ordered DESC";

$stmt = $mysqli->prepare($spo_query);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$spo_result = $stmt->get_result();

$purchase_history = [];
while ($row = $spo_result->fetch_assoc()) {
    $purchase_history[] = $row;
}
$stmt->close();
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

    <style>
        /* Autocomplete dropdown styling */
        .ui-autocomplete {
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 9999;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 14px;
        }
        
        .ui-menu-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .ui-menu-item:hover {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .ui-menu-item:last-child {
            border-bottom: none;
        }
        
        .ui-menu-item div {
            line-height: 1.4;
        }
        
        .ui-menu-item strong {
            color: #333;
            font-weight: 600;
        }
        
        .ui-menu-item small {
            color: #666;
            font-size: 12px;
        }
        
        /* Product row styling */
        .product-row td {
            vertical-align: middle;
            padding: 0.3rem 0.5rem !important;
        }
        
        .product-input {
            min-width: 200px;
        }
        
        /* Temporary calculation display */
        .temp-calculation {
            position: absolute;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .temp-calculation.show {
            opacity: 1;
        }
        
        /* Popup Message Styling */
        #supplierMessagePopup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2100;
            min-width: 250px;
            max-width: 90vw;
            background: #e6ffed;
            color: #155724;
            border: 1px solid #b7f5c2;
            border-radius: 8px;
            padding: 16px 32px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        
        #supplierMessagePopup.popup-success {
            background: #e6ffed;
            color: #155724;
            border: 1px solid #b7f5c2;
        }
        
        #supplierMessagePopup.popup-error {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>

    <title>Supplier Details</title>
</head>
<body>
    <?php $activePage = 'supplier'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Supplier Details</h1>
                    <p class="page-subtitle">View and manage supplier information and purchase history.</p>
                </div>
            </div>
        </header>
        <div class="main-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-0">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="supplier.php" class="text-decoration-none">Suppliers</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Supplier Details</li>
                    </ol>
                </nav>

                <ul class="nav nav-pills mb-0" id="creditorTabs">
                    <li class="">
                        <button class="btn active-tab" id="creditor-tab" type="button">Basic Information</button>
                    </li>
                    <li class="">
                        <button class="btn" id="credit-tab" type="button">Purchase Orders</button>
                    </li>
                </ul>
            </div>

            <div id="creditor-info-section">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light fw-semibold border-bottom">
                                <i class="fas fa-building me-2 text-primary"></i> Supplier Information
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Name</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($supplier['supplier_name']); ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Address</div>
                                    <div><?php 
                                        $address_parts = [];
                                        if (!empty($supplier['supplier_street'])) $address_parts[] = $supplier['supplier_street'];
                                        if (!empty($supplier['supplier_baranggay'])) $address_parts[] = $supplier['supplier_baranggay'];
                                        if (!empty($supplier['supplier_city'])) $address_parts[] = $supplier['supplier_city'];
                                        if (!empty($supplier['supplier_province'])) $address_parts[] = $supplier['supplier_province'];
                                        echo htmlspecialchars(implode(', ', $address_parts));
                                    ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Contact Number</div>
                                    <div><?php echo htmlspecialchars($supplier['supplier_number']); ?></div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Email Address</div>
                                    <div><?php echo htmlspecialchars($supplier['supplier_email']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light fw-semibold border-bottom">
                                <i class="fas fa-user me-2 text-primary"></i> Contact Person Information
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Contact Person Name</div>
                                    <div class="fw-semibold"><?php 
                                        $contact_parts = [];
                                        if (!empty($supplier['contact_fn'])) $contact_parts[] = $supplier['contact_fn'];
                                        if (!empty($supplier['contact_mn'])) $contact_parts[] = $supplier['contact_mn'];
                                        if (!empty($supplier['contact_ln'])) $contact_parts[] = $supplier['contact_ln'];
                                        if (!empty($supplier['contact_suffix'])) $contact_parts[] = $supplier['contact_suffix'];
                                        
                                        $full_name = trim(implode(' ', $contact_parts));
                                        if (!empty($full_name)) {
                                            echo htmlspecialchars($full_name);
                                        } elseif (!empty($supplier['contact_nickname'])) {
                                            echo htmlspecialchars($supplier['contact_nickname']);
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Address</div>
                                    <div><?php 
                                        $contact_address_parts = [];
                                        if (!empty($supplier['contact_street'])) $contact_address_parts[] = $supplier['contact_street'];
                                        if (!empty($supplier['contact_baranggay'])) $contact_address_parts[] = $supplier['contact_baranggay'];
                                        if (!empty($supplier['contact_city'])) $contact_address_parts[] = $supplier['contact_city'];
                                        if (!empty($supplier['contact_province'])) $contact_address_parts[] = $supplier['contact_province'];
                                        echo htmlspecialchars(implode(', ', $contact_address_parts));
                                    ?></div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Contact Number</div>
                                    <div><?php echo htmlspecialchars($supplier['contact_number']); ?></div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Email Address</div>
                                    <div><?php echo htmlspecialchars($supplier['contact_email']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="credit-info-section" style="display:none;">
                <div class="credit-information">
                    <!-- Results Summary -->
                    <div class="results-summary mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted mb-0">
                                    Showing <span id="showing-count">1-10</span> of <span id="total-count">50</span> Purchase Orders
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="createPurchaseOrder-btn">
                                    <i class="fas fa-plus me-2"></i>Create Purchase Order
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr class='align-middle'>
                                        <th>Invoice Number</th>
                                        <th>Date Ordered</th>
                                        <th class="text-center">Total Quantity</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="transaction-tbody">
                                    <!-- Table content will be populated by JavaScript -->
                                </tbody>
                            </table>

                        <!-- Pagination -->
                        <nav aria-label="Transaction pagination" class="mt-4">
                            <ul class="pagination justify-content-center" id="pagination">
                                <!-- Pagination will be generated by JavaScript -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Popup message function
        function showSupplierMessagePopup(message, isSuccess = true) {
            const $popup = $("#supplierMessagePopup");
            $popup.stop(true, true); // Stop any current animations
            $popup.text(message);
            $popup.removeClass("popup-success popup-error");
            $popup.addClass(isSuccess ? "popup-success" : "popup-error");
            $popup.fadeIn(200).delay(1800).fadeOut(400);
        }
        
        // Show success/error messages if they exist
        <?php if (isset($success_message)): ?>
            showSupplierMessagePopup('<?php echo addslashes($success_message); ?>', true);
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            showSupplierMessagePopup('<?php echo addslashes($error_message); ?>', false);
        <?php endif; ?>
        
        // Purchase history data from PHP
        const purchaseHistory = <?php echo json_encode($purchase_history); ?>;
        
        let currentPage = 1;
        const itemsPerPage = 5; // Show 5 SPOs per page

        // Tab switching functionality
        document.getElementById('creditor-tab').onclick = function() {
            document.getElementById('creditor-info-section').style.display = '';
            document.getElementById('credit-info-section').style.display = 'none';
            this.classList.add('active-tab');
            document.getElementById('credit-tab').classList.remove('active-tab');
        };

        document.getElementById('credit-tab').onclick = function() {
            document.getElementById('creditor-info-section').style.display = 'none';
            document.getElementById('credit-info-section').style.display = '';
            this.classList.add('active-tab');
            document.getElementById('creditor-tab').classList.remove('active-tab');
            
            // Load purchase history when switching to credit tab
            loadPurchaseHistory();
        };

        // Check URL parameter to automatically switch to purchase history tab
        function checkTabParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam === 'purchase') {
                // Switch to purchase history tab
                document.getElementById('creditor-info-section').style.display = 'none';
                document.getElementById('credit-info-section').style.display = '';
                document.getElementById('credit-tab').classList.add('active-tab');
                document.getElementById('creditor-tab').classList.remove('active-tab');
                
                // Load purchase history
                loadPurchaseHistory();
            }
        }

        // Call the function when page loads
        checkTabParameter();

        // Check URL parameter to automatically switch to purchase history tab
        function checkTabParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam === 'purchase') {
                // Switch to purchase history tab
                document.getElementById('creditor-info-section').style.display = 'none';
                document.getElementById('credit-info-section').style.display = '';
                document.getElementById('credit-tab').classList.add('active-tab');
                document.getElementById('creditor-tab').classList.remove('active-tab');
                
                // Load purchase history
                loadPurchaseHistory();
            }
        }

        // Call the function when page loads
        checkTabParameter();

        // Function to load purchase history with pagination
        function loadPurchaseHistory() {
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const currentSPOs = purchaseHistory.slice(startIndex, endIndex);
            
            const tbody = document.getElementById('transaction-tbody');
            tbody.innerHTML = '';
            
            if (currentSPOs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No purchase history found</td></tr>';
                return;
            }
            
            currentSPOs.forEach(spo => {
                const row = document.createElement('tr');
                row.className = 'align-middle';
                
                // Determine the amount to display based on transaction type
                let displayAmount = spo.grand_total || 0;
                if (spo.transaction_method === 'Credit' && spo.total_with_interest) {
                    displayAmount = spo.total_with_interest;
                }
                
                // Calculate balance for credit transactions
                let balance = 0;
                if (spo.transaction_method === 'Credit' && spo.total_with_interest) {
                    const totalPaid = parseFloat(spo.total_paid) || 0;
                    const totalAmount = parseFloat(spo.total_with_interest) || 0;
                    balance = totalAmount - totalPaid;
                }
                
                row.innerHTML = `
                    <td>${spo.invoice_number || '-'}</td>
                    <td>${formatDate(spo.date_ordered)}</td>
                    <td class="text-center">${spo.total_quantity || 0}</td>
                    <td class="text-end">₱${formatCurrency(displayAmount)}</td>
                    <td class="text-end">₱${formatCurrency(balance)}</td>
                    <td class="text-center"><span class="badge ${getStatusBadgeClass(spo.spo_status)}">${spo.spo_status || 'Pending'}</span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary edit-spo-btn" data-spo-id="${spo.spo_id}">
                            <i class="fas fa-edit"></i>
                        </button>
                    
                        ${spo.spo_status === 'Delivered' ? 
                            `<a href="spo_details.php?id=${spo.spo_id}">
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </a>` : 
                            `<button class="btn btn-sm btn-outline-secondary" disabled title="View details only available for delivered orders">
                                <i class="fas fa-eye"></i>
                            </button>`
                        }
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            
            // Update pagination info
            updatePaginationInfo();
            generatePagination();
        }

        // Function to get status badge class
        function getStatusBadgeClass(status) {
            switch(status?.toLowerCase()) {
                case 'delivered': return 'bg-success';
                case 'pending': return 'bg-warning';
                case 'cancelled': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }

        // Function to format currency with commas
        function formatCurrency(amount) {
            if (amount === null || amount === undefined) return '0.00';
            const num = parseFloat(amount);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Function to format date
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            const months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            const month = months[date.getMonth()];
            const day = date.getDate();
            const year = date.getFullYear();
            return `${month} ${day}, ${year}`;
        }

        // Function to update pagination info
        function updatePaginationInfo() {
            const totalItems = purchaseHistory.length;
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalItems);
            
            document.getElementById('showing-count').textContent = `${startItem}-${endItem}`;
            document.getElementById('total-count').textContent = totalItems;
        }

        // Function to generate pagination
        function generatePagination() {
            const totalPages = Math.ceil(purchaseHistory.length / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            if (totalPages <= 1) return;

            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;"><i class="fas fa-chevron-left"></i></a>`;
            pagination.appendChild(prevLi);
            
            // Page numbers
            const pageGroup = Math.ceil(currentPage / 5);
            const startPage = (pageGroup - 1) * 5 + 1;
            const endPage = Math.min(startPage + 4, totalPages);
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${currentPage === i ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>`;
                pagination.appendChild(li);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;"><i class="fas fa-chevron-right"></i></a>`;
            pagination.appendChild(nextLi);
        }

        // Function to change page
        function changePage(page) {
            const totalPages = Math.ceil(purchaseHistory.length / itemsPerPage);
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                loadPurchaseHistory();
            }
        }

        // Create Purchase Order button event handler
        document.getElementById('createPurchaseOrder-btn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('createPurchaseOrderModal'));
            modal.show();
        });
    </script>

    <!-- Edit Purchase Order Modal -->
    <div class="modal fade" id="editPurchaseOrderModal" tabindex="-1" aria-labelledby="editPurchaseOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPurchaseOrderModalLabel">Edit Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPurchaseOrderForm" method="POST" action="">
                        <input type="hidden" name="edit_spo_id" id="edit_spo_id">
                        <div class="mb-3">
                            <label for="edit_invoice_number" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="edit_invoice_number" name="edit_invoice_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="edit_status" required>
                                <option value="Not Delivered">Not Delivered</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_date_delivered" class="form-label">Date Delivered</label>
                            <input type="date" class="form-control" id="edit_date_delivered" name="edit_date_delivered">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="edit-purchase-order">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPurchaseOrderModal" tabindex="-1" aria-labelledby="createPurchaseOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPurchaseOrderModalLabel">
                        <i class="fas fa-plus me-2"></i>
                        Create Purchase Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createPurchaseOrderForm" method="POST" action="">
                        <div class='row g-3 mb-3'>
                            <div class='col-md-4'>
                                <label for="date_ordered" class="form-label">Date Ordered</label>
                                <input type="date" name="date_ordered" id="date_ordered" class="form-control" required>
                            </div>
                            <div class='col-md-4'>
                                <label for="transaction_type" class="form-label">Transaction Type</label>
                                <select name="transaction_type" id="transaction_type" class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>
                            <div class='col-md-4'>
                                <label for="invoice_number" class="form-label">Invoice Number</label>
                                <input type="text" name="invoice_number" id="invoice_number" class="form-control">
                            </div>
                        </div>

                        <div class='row g-3 mb-3' id="credit-fields" style="display: none;">
                            <div class='col-md-6'>
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control">
                            </div>
                            <div class='col-md-6'>
                                <label for="interest" class="form-label">Interest Amount</label>
                                <input type="number" name="interest" id="interest" class="form-control" step="1" min="0" placeholder="0">
                                <div id="interest_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                        </div>
                        


                        <hr>

                        <div class="table-responsive">
                            <table class='table table-hover'>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Brand</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th colspan="2" class="text-center">Discount</th>
                                        <th>Total</th>
                                        <th class='text-end'>
                                            <button type="button" class="btn btn-sm btn-outline-primary add-row-btn">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th class="text-center" style="font-size: 0.8rem; color: #6c757d;">Amount</th>
                                        <th class="text-center" style="font-size: 0.8rem; color: #6c757d;">Percent</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr class="product-row" data-row="1">
                                        <td>
                                            <input type="text" name="product_name_1" class="form-control product-input" placeholder="Enter product name">
                                        </td>

                                        <td>
                                            <input type="text" name="brand_1" class="form-control" readonly>
                                        </td>
                                        
                                        <td style='width: 100px;'><input type="text" name="unit_1" class="form-control" readonly></td>
                                        <td style='width: 100px;'><input type="number" name="quantity_1" class="form-control quantity-input" min="1" step="1" value="1"></td>
                                        <td><input type="number" name="price_1" class="form-control price-input" min="0" step="1" placeholder='0'></td>
                                        <td><input type="number" name="line_discount_1" class="form-control discount-input" step="1" placeholder='0'></td>
                                        <td style="position: relative;">
                                            <div class="temp-calculation" id="temp-calc-1"></div>
                                            <input type="number" name="line_discount_percent_1" class="form-control discount-percent-input" step="1" placeholder='0' min="0" max="100">
                                        </td>
                                        <td><input type="number" name="line_subtotal_1" class="form-control subtotal-input" readonly></td>
                                        <td class='text-end'>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </td>
                                        <input type="hidden" name="product_id[]" value="">
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="global_discount" class="form-label">Global Discount</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" name="global_discount_amount" id="global_discount_amount" class="form-control" step="1" value="0" placeholder="0">
                                        </div>
                                        <div id="global_discount_amount_error" class="text-danger small mt-1" style="display: none;"></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="number" name="global_discount_percent" id="global_discount_percent" class="form-control" step="1" value="0" placeholder="0" min="0" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div id="global_discount_percent_error" class="text-danger small mt-1" style="display: none;"></div>
                                        <small id="percent_calculation" class="text-muted" style="display: none;"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="history">
                            <div class='d-flex justify-content-between'>
                                <h6>Subtotal</h6>
                                <h6 id="subtotal-display">₱0.00</h6>
                            </div>

                            <div class='d-flex justify-content-between'>
                                <h6 id="discount-label">Global Discount</h6>
                                <h6 id="global-discount-display">₱0.00</h6>
                            </div>

                            <div class='d-flex justify-content-between' id="interest-row" style="display: none;">
                                <h6>Interest Amount</h6>
                                <h6 id="interest-amount-display">₱0.00</h6>
                            </div>

                            <div class='d-flex justify-content-between'>
                                <h5>Grand Total</h5>
                                <h5 id="grand-total-display">₱0.00</h5>
                            </div>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary" name='save-purchase'>Create Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <!-- Popup Message Container -->
    <div id="supplierMessagePopup"></div>

    <script>
        // Popup message function
        function showSupplierMessagePopup(message, isSuccess = true) {
            const $popup = $("#supplierMessagePopup");
            $popup.stop(true, true); // Stop any current animations
            $popup.text(message);
            $popup.removeClass("popup-success popup-error");
            $popup.addClass(isSuccess ? "popup-success" : "popup-error");
            $popup.fadeIn(200).delay(1800).fadeOut(400);
        }
        
        // Show success/error messages if they exist
        <?php if (isset($success_message)): ?>
            showSupplierMessagePopup('<?php echo addslashes($success_message); ?>', true);
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            showSupplierMessagePopup('<?php echo addslashes($error_message); ?>', false);
        <?php endif; ?>
        
        // Purchase history data from PHP
        const purchaseHistory = <?php echo json_encode($purchase_history); ?>;
        
        let currentPage = 1;
        const itemsPerPage = 5; // Show 5 SPOs per page

        // Tab switching functionality
        document.getElementById('creditor-tab').onclick = function() {
            document.getElementById('creditor-info-section').style.display = '';
            document.getElementById('credit-info-section').style.display = 'none';
            this.classList.add('active-tab');
            document.getElementById('credit-tab').classList.remove('active-tab');
        };

        document.getElementById('credit-tab').onclick = function() {
            document.getElementById('creditor-info-section').style.display = 'none';
            document.getElementById('credit-info-section').style.display = '';
            this.classList.add('active-tab');
            document.getElementById('creditor-tab').classList.remove('active-tab');
            
            // Load purchase history when switching to credit tab
            loadPurchaseHistory();
        };

        // Check URL parameter to automatically switch to purchase history tab
        function checkTabParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam === 'purchase') {
                // Switch to purchase history tab
                document.getElementById('creditor-info-section').style.display = 'none';
                document.getElementById('credit-info-section').style.display = '';
                document.getElementById('credit-tab').classList.add('active-tab');
                document.getElementById('creditor-tab').classList.remove('active-tab');
                
                // Load purchase history
                loadPurchaseHistory();
            }
        }

        // Call the function when page loads
        checkTabParameter();

        // Check URL parameter to automatically switch to purchase history tab
        function checkTabParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam === 'purchase') {
                // Switch to purchase history tab
                document.getElementById('creditor-info-section').style.display = 'none';
                document.getElementById('credit-info-section').style.display = '';
                document.getElementById('credit-tab').classList.add('active-tab');
                document.getElementById('creditor-tab').classList.remove('active-tab');
                
                // Load purchase history
                loadPurchaseHistory();
            }
        }

        // Call the function when page loads
        checkTabParameter();

        // Function to load purchase history with pagination
        function loadPurchaseHistory() {
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const currentSPOs = purchaseHistory.slice(startIndex, endIndex);
            
            const tbody = document.getElementById('transaction-tbody');
            tbody.innerHTML = '';
            
            if (currentSPOs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No purchase history found</td></tr>';
                return;
            }
            
            currentSPOs.forEach(spo => {
                const row = document.createElement('tr');
                row.className = 'align-middle';
                
                // Determine the amount to display based on transaction type
                let displayAmount = spo.grand_total || 0;
                if (spo.transaction_method === 'Credit' && spo.total_with_interest) {
                    displayAmount = spo.total_with_interest;
                }
                
                // Calculate balance for credit transactions
                let balance = 0;
                if (spo.transaction_method === 'Credit' && spo.total_with_interest) {
                    const totalPaid = parseFloat(spo.total_paid) || 0;
                    const totalAmount = parseFloat(spo.total_with_interest) || 0;
                    balance = totalAmount - totalPaid;
                }
                
                row.innerHTML = `
                    <td>${spo.invoice_number || '-'}</td>
                    <td class="text-center">${formatDate(spo.date_ordered)}</td>
                    <td class="text-center">${spo.total_quantity || 0}</td>
                    <td class="text-end">₱${formatCurrency(displayAmount)}</td>
                    <td class="text-end">₱${formatCurrency(balance)}</td>
                    <td class="text-center"><span class="badge ${getStatusBadgeClass(spo.spo_status)}">${spo.spo_status || 'Pending'}</span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary edit-spo-btn" data-spo-id="${spo.spo_id}">
                            <i class="fas fa-edit"></i>
                        </button>
                    
                        ${spo.spo_status === 'Delivered' ? 
                            `<a href="spo_details.php?id=${spo.spo_id}">
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </a>` : 
                            `<button class="btn btn-sm btn-outline-secondary" disabled title="View details only available for delivered orders">
                                <i class="fas fa-eye"></i>
                            </button>`
                        }
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            
            // Update pagination info
            updatePaginationInfo();
            generatePagination();
        }

        // Function to get status badge class
        function getStatusBadgeClass(status) {
            switch(status?.toLowerCase()) {
                case 'delivered': return 'bg-success';
                case 'pending': return 'bg-warning';
                case 'cancelled': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }

        // Function to format currency with commas
        function formatCurrency(amount) {
            if (amount === null || amount === undefined) return '0.00';
            const num = parseFloat(amount);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Function to format date
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            const months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            const month = months[date.getMonth()];
            const day = date.getDate();
            const year = date.getFullYear();
            return `${month} ${day}, ${year}`;
        }

        // Function to update pagination info
        function updatePaginationInfo() {
            const totalItems = purchaseHistory.length;
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalItems);
            
            document.getElementById('showing-count').textContent = `${startItem}-${endItem}`;
            document.getElementById('total-count').textContent = totalItems;
        }

        // Function to generate pagination
        function generatePagination() {
            const totalPages = Math.ceil(purchaseHistory.length / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            if (totalPages <= 1) return;

            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;"><i class="fas fa-chevron-left"></i></a>`;
            pagination.appendChild(prevLi);
            
            // Page numbers
            const pageGroup = Math.ceil(currentPage / 5);
            const startPage = (pageGroup - 1) * 5 + 1;
            const endPage = Math.min(startPage + 4, totalPages);
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${currentPage === i ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>`;
                pagination.appendChild(li);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;"><i class="fas fa-chevron-right"></i></a>`;
            pagination.appendChild(nextLi);
        }

        // Function to change page
        function changePage(page) {
            const totalPages = Math.ceil(purchaseHistory.length / itemsPerPage);
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                loadPurchaseHistory();
            }
        }

        // Create Purchase Order button event handler
        document.getElementById('createPurchaseOrder-btn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('createPurchaseOrderModal'));
            modal.show();
        });
    </script>

    <!-- Edit Purchase Order Modal -->
    <div class="modal fade" id="editPurchaseOrderModal" tabindex="-1" aria-labelledby="editPurchaseOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPurchaseOrderModalLabel">Edit Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPurchaseOrderForm" method="POST" action="">
                        <input type="hidden" name="edit_spo_id" id="edit_spo_id">
                        <div class="mb-3">
                            <label for="edit_invoice_number" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="edit_invoice_number" name="edit_invoice_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="edit_status" required>
                                <option value="Not Delivered">Not Delivered</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_date_delivered" class="form-label">Date Delivered</label>
                            <input type="date" class="form-control" id="edit_date_delivered" name="edit_date_delivered">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="edit-purchase-order">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPurchaseOrderModal" tabindex="-1" aria-labelledby="createPurchaseOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPurchaseOrderModalLabel">
                        <i class="fas fa-plus me-2"></i>
                        Create Purchase Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createPurchaseOrderForm" method="POST" action="">
                        <div class='row g-3 mb-3'>
                            <div class='col-md-4'>
                                <label for="date_ordered" class="form-label">Date Ordered</label>
                                <input type="date" name="date_ordered" id="date_ordered" class="form-control" required>
                            </div>
                            <div class='col-md-4'>
                                <label for="transaction_type" class="form-label">Transaction Type</label>
                                <select name="transaction_type" id="transaction_type" class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>
                            <div class='col-md-4'>
                                <label for="invoice_number" class="form-label">Invoice Number</label>
                                <input type="text" name="invoice_number" id="invoice_number" class="form-control">
                            </div>
                        </div>

                        <div class='row g-3 mb-3' id="credit-fields" style="display: none;">
                            <div class='col-md-6'>
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control">
                            </div>
                            <div class='col-md-6'>
                                <label for="interest" class="form-label">Interest Amount</label>
                                <input type="number" name="interest" id="interest" class="form-control" step="1" min="0" placeholder="0">
                                <div id="interest_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                        </div>
                        


                        <hr>

                        <div class="table-responsive">
                            <table class='table table-hover'>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Brand</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th colspan="2" class="text-center">Discount</th>
                                        <th>Total</th>
                                        <th class='text-end'>
                                            <button type="button" class="btn btn-sm btn-outline-primary add-row-btn">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th class="text-center" style="font-size: 0.8rem; color: #6c757d;">Amount</th>
                                        <th class="text-center" style="font-size: 0.8rem; color: #6c757d;">Percent</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr class="product-row" data-row="1">
                                        <td>
                                            <input type="text" name="product_name_1" class="form-control product-input" placeholder="Enter product name">
                                        </td>

                                        <td><input type="text" name="brand_1" class="form-control" readonly></td>
                                        <td style='width: 100px;'><input type="text" name="unit_1" class="form-control" readonly></td>
                                        <td style='width: 100px;'><input type="number" name="quantity_1" class="form-control quantity-input" min="1" step="1" value="1"></td>
                                        <td><input type="number" name="price_1" class="form-control price-input" min="0" step="1" placeholder='0'></td>
                                        <td><input type="number" name="line_discount_1" class="form-control discount-input" step="1" placeholder='0'></td>
                                        <td style="position: relative;">
                                            <div class="temp-calculation" id="temp-calc-1"></div>
                                            <input type="number" name="line_discount_percent_1" class="form-control discount-percent-input" step="1" placeholder='0' min="0" max="100">
                                        </td>
                                        <td><input type="number" name="line_subtotal_1" class="form-control subtotal-input" readonly></td>
                                        <td class='text-end'>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </td>
                                        <input type="hidden" name="product_id[]" value="">
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="global_discount" class="form-label">Global Discount</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" name="global_discount_amount" id="global_discount_amount" class="form-control" step="1" value="0" placeholder="0">
                                        </div>
                                        <div id="global_discount_amount_error" class="text-danger small mt-1" style="display: none;"></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="number" name="global_discount_percent" id="global_discount_percent" class="form-control" step="1" value="0" placeholder="0" min="0" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div id="global_discount_percent_error" class="text-danger small mt-1" style="display: none;"></div>
                                        <small id="percent_calculation" class="text-muted" style="display: none;"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="history">
                            <div class='d-flex justify-content-between'>
                                <h6>Subtotal</h6>
                                <h6 id="subtotal-display">₱0.00</h6>
                            </div>

                            <div class='d-flex justify-content-between'>
                                <h6 id="discount-label">Global Discount</h6>
                                <h6 id="global-discount-display">₱0.00</h6>
                            </div>

                            <div class='d-flex justify-content-between' id="interest-row" style="display: none;">
                                <h6>Interest Amount</h6>
                                <h6 id="interest-amount-display">₱0.00</h6>
                            </div>

                            <div class='d-flex justify-content-between'>
                                <h5>Grand Total</h5>
                                <h5 id="grand-total-display">₱0.00</h5>
                            </div>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary" name='save-purchase'>Create Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <!-- Popup Message Container -->
    <div id="supplierMessagePopup"></div>

    <script>
        // Function to open edit modal  
        function openEditModal(spo) {
            const modal = new bootstrap.Modal(document.getElementById('editPurchaseOrderModal'));
            modal.show();
            
            // Populate form fields with SPO data
            document.getElementById('edit_spo_id').value = spo.spo_id;
            document.getElementById('edit_invoice_number').value = spo.invoice_number || '';
            document.getElementById('edit_status').value = spo.spo_status || 'pending';
            document.getElementById('edit_date_delivered').value = spo.date_delivered || '';
        }

        // Helper function to format date for input field
        function formatDateForInput(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toISOString().split('T')[0];
        }



        // Add event listener for edit buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-spo-btn')) {
                const button = e.target.closest('.edit-spo-btn');
                const spoId = button.getAttribute('data-spo-id');
                
                // Find the SPO data from the purchase history
                const spo = purchaseHistory.find(item => item.spo_id == spoId);
                if (spo) {
                    openEditModal(spo);
                }
            }
        });



        // Positive whole number validation function
        function validatePositiveWholeNumber(input, errorDivId) {
            const value = input.value.trim();
            const errorDiv = $(`#${errorDivId}`);
            
            // Clear previous error
            errorDiv.hide();
            input.classList.remove('is-invalid');
            
            // If empty, don't show error (unless required)
            if (value === '') {
                return true;
            }
            
            // Check if it's a valid number
            if (isNaN(value) || value === '') {
                errorDiv.text('Please enter a valid number').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check if it's a whole number
            if (value % 1 !== 0) {
                errorDiv.text('Please enter a whole number only').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check if it's zero or negative
            if (parseInt(value) < 0) {
                errorDiv.text('Value must be zero or greater').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            return true;
        }
        
        // Purchase Order Modal Autocomplete and Row Management
        $(document).ready(function() {
            let rowCounter = 1;

            // Initialize autocomplete for the first row
            initializeAutocomplete('input[name="product_name_1"]');

            // Add new row button click handler
            $(document).on('click', '.add-row-btn', function(e) {
                e.preventDefault();
                addNewRow();
            });

            // Remove row button click handler
            $(document).on('click', '.remove-row-btn', function(e) {
                e.preventDefault();
                const $row = $(this).closest('tr');
                // Only remove if it's in the modal and not the last row
                if ($row.closest('#createPurchaseOrderModal').length > 0 && $('#createPurchaseOrderModal .product-row').length > 1) {
                    $row.remove();
                    updateRowNumbers();
                    calculateTotals();
                }
            });

            // Calculate totals when inputs change
            $(document).on('input change', '.quantity-input, .price-input, .discount-input, .discount-percent-input, #global_discount_amount, #global_discount_percent, #interest', function() {
                // Validate positive whole numbers for all numeric inputs
                const value = $(this).val().trim();
                
                if (value !== '') {
                    // Check if it's a valid number
                    if (isNaN(value)) {
                        $(this).addClass('is-invalid');
                        return;
                    }
                    // Check if it's a whole number
                    if (value % 1 !== 0) {
                        $(this).addClass('is-invalid');
                        return;
                    }
                    // Check if it's negative
                    if (parseInt(value) < 0) {
                        $(this).addClass('is-invalid');
                        return;
                    }
                }
                
                $(this).removeClass('is-invalid');
                calculateRowTotal($(this).closest('tr'));
                calculateTotals();
                updatePercentCalculation();
            });
            
            // Positive whole number validation event listeners
            $('#interest').on('input', function() {
                validatePositiveWholeNumber(this, 'interest_error');
            });
            
            $('#interest').on('blur', function() {
                validatePositiveWholeNumber(this, 'interest_error');
            });
            
            $('#global_discount_amount').on('input', function() {
                validatePositiveWholeNumber(this, 'global_discount_amount_error');
            });
            
            $('#global_discount_amount').on('blur', function() {
                validatePositiveWholeNumber(this, 'global_discount_amount_error');
            });
            
            $('#global_discount_percent').on('input', function() {
                validatePositiveWholeNumber(this, 'global_discount_percent_error');
            });
            
            $('#global_discount_percent').on('blur', function() {
                validatePositiveWholeNumber(this, 'global_discount_percent_error');
            });

            // Format price input with .00
            $(document).on('blur', '.price-input', function() {
                const value = parseFloat($(this).val()) || 0;
                $(this).val(value.toFixed(2));
            });

            // Handle percentage input for placeholder calculation
            $(document).on('input', '.discount-percent-input', function() {
                const $row = $(this).closest('tr');
                const rowNum = $row.data('row');
                const quantity = parseFloat($row.find('.quantity-input').val()) || 0;
                const price = parseFloat($row.find('.price-input').val()) || 0;
                const percentValue = parseFloat($(this).val()) || 0;
                const baseAmount = quantity * price;
                
                if (percentValue > 0 && baseAmount > 0) {
                    const percentAmount = (baseAmount * percentValue) / 100;
                    
                    // Show temporary calculation above the input
                    const $tempCalc = $(`#temp-calc-${rowNum}`);
                    $tempCalc.text(`₱${formatCurrency(percentAmount)} (${percentValue}% of ₱${formatCurrency(baseAmount)})`);
                    $tempCalc.css({
                        'top': '-25px',
                        'left': '0',
                        'right': '0'
                    }).addClass('show');
                    
                    // Hide after 3 seconds
                    setTimeout(() => {
                        $tempCalc.removeClass('show');
                    }, 3000);
                    
                    // Update placeholder
                    $(this).attr('placeholder', `₱${formatCurrency(percentAmount)} (${percentValue}% of ₱${formatCurrency(baseAmount)})`);
                } else {
                    $(this).attr('placeholder', '0.0');
                }
            });

            // Function to update percentage calculation display
            function updatePercentCalculation() {
                const subtotal = parseFloat($('#subtotal-display').text().replace('₱', '').replace(/,/g, '')) || 0;
                const percentValue = parseFloat($('#global_discount_percent').val()) || 0;
                
                if (percentValue > 0 && subtotal > 0) {
                    const percentAmount = (subtotal * percentValue) / 100;
                    $('#percent_calculation').text(`₱${formatCurrency(percentAmount)} (${percentValue}% of ₱${formatCurrency(subtotal)})`).show();
                } else {
                    $('#percent_calculation').hide();
                }
            }

            function initializeAutocomplete(selector) {
                $(selector).autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: "autocomplete_supplier_products.php",
                            dataType: "json",
                            data: { term: request.term },
                            success: function(data) {
                                response(data);
                            },
                            error: function(xhr, status, error) {
                                console.error('Autocomplete error:', error);
                                response([]);
                            }
                        });
                    },
                    minLength: 1, // Show suggestions from first letter
                    delay: 100, // Reduce delay for faster response
                    position: { my: "left bottom", at: "left top" },
                    appendTo: "#createPurchaseOrderModal", // Ensure dropdown appears in modal
                    select: function(event, ui) {
                        const $row = $(this).closest('tr');
                        const rowNum = $row.data('row');
                        
                        // Fill the product name input field with the selected item
                        $(this).val(ui.item.label);
                        
                        // Fill brand and unit fields
                        $row.find(`input[name="brand_${rowNum}"]`).val(ui.item.product_brand || '');
                        $row.find(`input[name="unit_${rowNum}"]`).val(ui.item.unit || '');
                        
                        // Store product data
                        $row.data('product-id', ui.item.product_id);
                        $row.data('product-name', ui.item.label);
                        
                        // Update hidden product_id field
                        $row.find('input[name="product_id[]"]').val(ui.item.product_id);
                        
                        // Trigger calculation after selection
                        calculateRowTotal($row);
                        calculateTotals();
                        
                        return false;
                    }
                }).autocomplete("instance")._renderItem = function(ul, item) {
                    // Custom rendering for better display
                    const parts = item.label.split(' | ');
                    const productName = parts[0];
                    const additionalInfo = parts.slice(1).join(' | ');
                    
                    return $("<li>")
                        .append("<div><strong>" + productName + "</strong><br>" +
                               "<small class='text-muted'>" + additionalInfo + "</small></div>")
                        .appendTo(ul);
                };
                
                // Add focus event to show suggestions immediately
                $(selector).on('focus', function() {
                    if ($(this).val().length >= 1) {
                        $(this).autocomplete("search");
                    }
                });
            }

            function addNewRow() {
                rowCounter++;
                const newRow = `
                    <tr class="product-row" data-row="${rowCounter}">
                        <td>
                            <input type="text" name="product_name_${rowCounter}" class="form-control product-input" placeholder="Enter product name">
                        </td>

                        <td><input type="text" name="brand_${rowCounter}" class="form-control" readonly></td>
                        <td style='width: 100px;'><input type="text" name="unit_${rowCounter}" class="form-control" readonly></td>
                        <td style='width: 100px;'><input type="number" name="quantity_${rowCounter}" class="form-control quantity-input" min="1" step="1" value="1"></td>
                        <td><input type="number" name="price_${rowCounter}" class="form-control price-input" min="0" step="1" placeholder='0'></td>
                        <td><input type="number" name="line_discount_${rowCounter}" class="form-control discount-input" step="1" placeholder='0'></td>
                        <td style="position: relative;">
                            <div class="temp-calculation" id="temp-calc-${rowCounter}"></div>
                            <input type="number" name="line_discount_percent_${rowCounter}" class="form-control discount-percent-input" step="1" placeholder='0' min="0" max="100">
                        </td>
                        <td><input type="number" name="line_subtotal_${rowCounter}" class="form-control subtotal-input" readonly></td>
                        <td class='text-end'>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                                <i class="fas fa-minus"></i>
                            </button>
                        </td>
                        <input type="hidden" name="product_id[]" value="">
                    </tr>
                `;
                
                $('#createPurchaseOrderModal .table tbody').append(newRow);
                initializeAutocomplete(`input[name="product_name_${rowCounter}"]`);
            }

            function updateRowNumbers() {
                $('#createPurchaseOrderModal .product-row').each(function(index) {
                    $(this).attr('data-row', index + 1);
                });
            }

            function calculateRowTotal($row) {
                const quantity = parseFloat($row.find('.quantity-input').val()) || 0;
                const price = parseFloat($row.find('.price-input').val()) || 0;
                const discount = parseFloat($row.find('.discount-input').val()) || 0;
                const discountPercent = parseFloat($row.find('.discount-percent-input').val()) || 0;
                
                // Calculate base amount
                const baseAmount = quantity * price;
                
                // Calculate percentage discount amount
                const percentDiscountAmount = (baseAmount * discountPercent) / 100;
                
                // Total discount is amount + percentage
                const totalDiscount = discount + percentDiscountAmount;
                
                // Calculate final subtotal
                const subtotal = baseAmount - totalDiscount;
                
                $row.find('.subtotal-input').val(subtotal.toFixed(2));
                
                // Update line item percentage calculation as placeholder
                updateLinePercentCalculation($row, baseAmount, discountPercent);
            }

            // Function to update line item percentage calculation as placeholder
            function updateLinePercentCalculation($row, baseAmount, percentValue) {
                const percentInput = $row.find('.discount-percent-input');
                const rowNum = $row.data('row');
                
                if (percentValue > 0 && baseAmount > 0) {
                    const percentAmount = (baseAmount * percentValue) / 100;
                    
                    // Show temporary calculation above the input
                    const $tempCalc = $(`#temp-calc-${rowNum}`);
                    $tempCalc.text(`₱${formatCurrency(percentAmount)} (${percentValue}% of ₱${formatCurrency(baseAmount)})`);
                    $tempCalc.css({
                        'top': '-25px',
                        'left': '0',
                        'right': '0'
                    }).addClass('show');
                    
                    // Hide after 3 seconds
                    setTimeout(() => {
                        $tempCalc.removeClass('show');
                    }, 3000);
                    
                    percentInput.attr('placeholder', `₱${formatCurrency(percentAmount)} (${percentValue}% of ₱${formatCurrency(baseAmount)})`);
                } else {
                    percentInput.attr('placeholder', '0.0');
                }
            }

            function calculateTotals() {
                let subtotal = 0;
                
                $('#createPurchaseOrderModal .product-row').each(function() {
                    const rowSubtotal = parseFloat($(this).find('.subtotal-input').val()) || 0;
                    subtotal += rowSubtotal;
                });
                
                const transactionType = $('#transaction_type').val();
                const globalDiscountAmount = parseFloat($('#global_discount_amount').val()) || 0;
                const globalDiscountPercent = parseFloat($('#global_discount_percent').val()) || 0;
                
                // Calculate percentage discount amount
                const percentDiscountAmount = (subtotal * globalDiscountPercent) / 100;
                const totalGlobalDiscount = globalDiscountAmount + percentDiscountAmount;
                
                let grandTotal = subtotal - totalGlobalDiscount;
                let interestAmount = 0;
                
                // If transaction type is Credit, add interest amount to grand total
                if (transactionType === 'Credit') {
                    interestAmount = parseFloat($('#interest').val()) || 0;
                    grandTotal += interestAmount;
                    
                    // Show interest row
                    $('#interest-row').show();
                    $('#interest-amount-display').text('₱' + formatCurrency(interestAmount));
                } else {
                    // Hide interest row
                    $('#interest-row').hide();
                }
                
                $('#subtotal-display').text('₱' + formatCurrency(subtotal));
                
                // Display global discount with both amount and percentage
                let discountDisplay = '₱0.00';
                if (globalDiscountAmount > 0 || globalDiscountPercent > 0) {
                    discountDisplay = `₱${formatCurrency(totalGlobalDiscount)}`;
                    if (globalDiscountAmount > 0 && globalDiscountPercent > 0) {
                        discountDisplay += ` (₱${formatCurrency(globalDiscountAmount)} + ${globalDiscountPercent}%)`;
                    } else if (globalDiscountAmount > 0) {
                        discountDisplay += ` (₱${formatCurrency(globalDiscountAmount)})`;
                    } else if (globalDiscountPercent > 0) {
                        discountDisplay += ` (${globalDiscountPercent}%)`;
                    }
                }
                $('#global-discount-display').text(discountDisplay);
                $('#grand-total-display').text('₱' + formatCurrency(grandTotal));
                
                // Update percentage calculation display
                updatePercentCalculation();
            }

            // Function to update percentage calculation display
            function updatePercentCalculation() {
                const subtotal = parseFloat($('#subtotal-display').text().replace('₱', '').replace(/,/g, '')) || 0;
                const percentValue = parseFloat($('#global_discount_percent').val()) || 0;
                
                if (percentValue > 0 && subtotal > 0) {
                    const percentAmount = (subtotal * percentValue) / 100;
                    $('#percent_calculation').text(`₱${formatCurrency(percentAmount)} (${percentValue}% of ₱${formatCurrency(subtotal)})`).show();
                } else {
                    $('#percent_calculation').hide();
                }
            }

            // Form submission handler
            $('#createPurchaseOrderForm').on('submit', function(e) {
                // Validate positive whole number fields
                const interestValid = validatePositiveWholeNumber(document.getElementById('interest'), 'interest_error');
                const globalDiscountAmountValid = validatePositiveWholeNumber(document.getElementById('global_discount_amount'), 'global_discount_amount_error');
                const globalDiscountPercentValid = validatePositiveWholeNumber(document.getElementById('global_discount_percent'), 'global_discount_percent_error');
                
                // Check for invalid inputs in product rows
                let hasInvalidInputs = false;
                $('#createPurchaseOrderModal .product-row').each(function() {
                    const $row = $(this);
                    const quantityInput = $row.find('.quantity-input');
                    const priceInput = $row.find('.price-input');
                    const discountInput = $row.find('.discount-input');
                    const discountPercentInput = $row.find('.discount-percent-input');
                    
                    // Check if any of these inputs have invalid class
                    if (quantityInput.hasClass('is-invalid') || priceInput.hasClass('is-invalid') || 
                        discountInput.hasClass('is-invalid') || discountPercentInput.hasClass('is-invalid')) {
                        hasInvalidInputs = true;
                    }
                });
                
                if (!interestValid || !globalDiscountAmountValid || !globalDiscountPercentValid || hasInvalidInputs) {
                    e.preventDefault();
                    alert('Please fix all validation errors before submitting.');
                    return false;
                }
                
                // Validate that at least one product is selected
                let hasValidProduct = false;
                $('#createPurchaseOrderModal .product-row').each(function() {
                    const $row = $(this);
                    const productInput = $row.find('.product-input').val();
                    const quantityInput = $row.find('.quantity-input').val();
                    
                    if (productInput && quantityInput && $row.data('product-id')) {
                        hasValidProduct = true;
                    }
                });
                
                if (!hasValidProduct) {
                    e.preventDefault();
                    alert('Please select at least one product with quantity.');
                    return false;
                }
                
                // Validate required fields
                if (!$('#date_ordered').val() || !$('#transaction_type').val()) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                // Validate credit fields if transaction type is Credit
                const currentTransactionType = $('#transaction_type').val();
                if (currentTransactionType === 'Credit') {
                    if (!$('#due_date').val()) {
                        e.preventDefault();
                        alert('Please enter the due date for credit transactions.');
                        return false;
                    }
                    if (!$('#interest').val() || parseFloat($('#interest').val()) < 0) {
                        e.preventDefault();
                        alert('Please enter a valid interest amount for credit transactions.');
                        return false;
                    }
                }
                
                // Prepare form data for submission
                $('#createPurchaseOrderModal .product-row').each(function() {
                    const $row = $(this);
                    const rowNum = $row.data('row');
                    const productId = $row.data('product-id');
                    const quantity = $row.find('.quantity-input').val();
                    const price = $row.find('.price-input').val();
                                            const lineDiscount = $row.find('.discount-input').val();
                        const lineDiscountPercent = $row.find('.discount-percent-input').val();
                        const lineSubtotal = $row.find('.subtotal-input').val();
                    
                    if (productId && quantity) {
                        // Update hidden fields for form submission
                        $row.find('input[name="product_id[]"]').val(productId);
                        
                        // Add quantity, price, discount, and subtotal to form
                        if (!$row.find('input[name="quantity[]"]').length) {
                            $row.append(`<input type="hidden" name="quantity[]" value="${quantity}">`);
                        } else {
                            $row.find('input[name="quantity[]"]').val(quantity);
                        }
                        
                        if (!$row.find('input[name="price[]"]').length) {
                            $row.append(`<input type="hidden" name="price[]" value="${price}">`);
                        } else {
                            $row.find('input[name="price[]"]').val(price);
                        }
                        
                        if (!$row.find('input[name="line_discount[]"]').length) {
                            $row.append(`<input type="hidden" name="line_discount[]" value="${lineDiscount}">`);
                        } else {
                            $row.find('input[name="line_discount[]"]').val(lineDiscount);
                        }
                        
                        if (!$row.find('input[name="line_discount_percent[]"]').length) {
                            $row.append(`<input type="hidden" name="line_discount_percent[]" value="${lineDiscountPercent}">`);
                        } else {
                            $row.find('input[name="line_discount_percent[]"]').val(lineDiscountPercent);
                        }
                        
                        if (!$row.find('input[name="line_subtotal[]"]').length) {
                            $row.append(`<input type="hidden" name="line_subtotal[]" value="${lineSubtotal}">`);
                        } else {
                            $row.find('input[name="line_subtotal[]"]').val(lineSubtotal);
                        }
                    }
                });
                
                // Ensure credit fields are included in form submission
                const formTransactionType = $('#transaction_type').val();
                if (formTransactionType === 'Credit') {
                    const dueDate = $('#due_date').val();
                    const interest = $('#interest').val();
                    
                    // Add credit fields to form if they don't exist
                    if (!$('input[name="due_date"]').length) {
                        $('#createPurchaseOrderForm').append(`<input type="hidden" name="due_date" value="${dueDate}">`);
                    }
                    if (!$('input[name="interest"]').length) {
                        $('#createPurchaseOrderForm').append(`<input type="hidden" name="interest" value="${interest}">`);
                    }
                }
                
                // Debug: Log the form data being submitted
                console.log('Form data being submitted:');
                const formData = new FormData(this);
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
            });

            // Set default date to today
            $('#date_ordered').val(new Date().toISOString().split('T')[0]);
            

            
            // Handle transaction type change to show/hide credit fields
            $('#transaction_type').on('change', function() {
                const transactionType = $(this).val();
                const creditFields = $('#credit-fields');
                const discountLabel = $('#discount-label');
                
                if (transactionType === 'Credit') {
                    creditFields.show();
                    $('#due_date').prop('required', true);
                    $('#interest').prop('required', false);
                    discountLabel.text('Global Discount');
                } else {
                    creditFields.hide();
                    $('#due_date, #interest').prop('required', false);
                    $('#due_date, #interest').val('');
                    discountLabel.text('Global Discount');
                }
            });
            
            // Also handle the change when modal opens
            $('#createPurchaseOrderModal').on('shown.bs.modal', function() {
                const transactionType = $('#transaction_type').val();
                const creditFields = $('#credit-fields');
                const discountLabel = $('#discount-label');
                
                if (transactionType === 'Credit') {
                    creditFields.show();
                    $('#due_date').prop('required', true);
                    $('#interest').prop('required', false);
                    discountLabel.text('Interest');
                } else {
                    creditFields.hide();
                    $('#due_date, #interest').prop('required', false);
                    discountLabel.text('Global Discount');
                }
            });
        });

        // Check URL parameter to automatically switch to purchase history tab
        function checkTabParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            if (tabParam === 'purchase') {
                // Switch to purchase history tab
                document.getElementById('creditor-info-section').style.display = 'none';
                document.getElementById('credit-info-section').style.display = '';
                document.getElementById('credit-tab').classList.add('active-tab');
                document.getElementById('creditor-tab').classList.remove('active-tab');
                
                // Load purchase history
                loadPurchaseHistory();
            }
        }

        // Call the function when page loads
        checkTabParameter();

    </script>

</body>
</html>