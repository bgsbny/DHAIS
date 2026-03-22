<?php
include('mycon.php');
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Endpoint: Get products by location (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get_products_by_location' && isset($_GET['location'])) {
    $location = $_GET['location'];
    $stmt = $mysqli->prepare("SELECT i.product_id, m.item_name FROM tbl_inventory i JOIN tbl_masterlist m ON i.product_id = m.product_id WHERE i.storage_location = ? AND i.stock_level > 0");
    $stmt->bind_param('s', $location);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

// Endpoint: Get stock level for specific product and location (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get_stock_level' && isset($_GET['product_id']) && isset($_GET['location'])) {
    $product_id = intval($_GET['product_id']);
    $location = $_GET['location'];
    $stmt = $mysqli->prepare("SELECT stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = ?");
    $stmt->bind_param('is', $product_id, $location);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row) {
        echo json_encode(['success' => true, 'stock_level' => $row['stock_level']]);
    } else {
        echo json_encode(['success' => false, 'stock_level' => 0]);
    }
    exit;
}

// Endpoint: Process transfer (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_stock') {
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    $qty = intval($_POST['qty'] ?? 0);
    if ($from && $to && $product_id > 0 && $qty > 0 && $from !== $to) {
        $mysqli->begin_transaction();
        try {
            // Subtract from 'from' location
            $stmt = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level - ? WHERE product_id = ? AND storage_location = ? AND stock_level >= ?");
            $stmt->bind_param('iisi', $qty, $product_id, $from, $qty);
            $stmt->execute();
            if ($stmt->affected_rows === 0) throw new Exception('Not enough stock or invalid product.');
            $stmt->close();
            // Add to 'to' location (check if exists first)
            $stmt = $mysqli->prepare("SELECT stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = ?");
            $stmt->bind_param('is', $product_id, $to);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                // Update existing inventory
                $stmt = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level + ?, last_updated = NOW() WHERE product_id = ? AND storage_location = ?");
                $stmt->bind_param('iis', $qty, $product_id, $to);
                $stmt->execute();
                $stmt->close();
            } else {
                // Insert new inventory entry
                $stmt = $mysqli->prepare("INSERT INTO tbl_inventory (product_id, stock_level, storage_location, last_updated) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param('iis', $product_id, $qty, $to);
                $stmt->execute();
                $stmt->close();
            }
            $mysqli->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $mysqli->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
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

    <title>Inventory</title>
</head>
<body>
    <?php $activePage = 'inventory'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Inventory Management</h1>
                    <p class="page-subtitle">Manage your inventory, track stock levels, and monitor product movement.</p>
                </div>
            </div>
        </header>

        <div class="main-container">
            <!-- Inventory Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <a href='stock_list.php' style='text-decoration:none;'>
                        <div class="card stat-card h-100">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #0F1939, #1a2b5a);">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label odyssey-text">Current Stock</div>
                                <div class="text-muted small">All items currently in stock and tracked in inventory.</div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4 mb-3">
                    <a href="reorder_list.php" style='text-decoration:none;'>
                        <div class="card stat-card h-100">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label odyssey-text">Reorder List</div>
                                <div class="text-muted small">Items that have reached or fallen below their reorder level.</div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4 mb-3">
                    <a href="master_list.php" style='text-decoration:none;'>
                        <div class="card stat-card h-100">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #516AC8, #6b7fd8);">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label odyssey-text">Master List</div>
                                <div class="text-muted small">All products and services registered in the system.</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="history">
                <div class="mb-3">
                    <h5>Transfer Stock between Locations</h5>
                </div>
                <div class='mb-3'>
                    <label for="transfer-from" class='form-label'>Transfer From</label>
                    <select id="transfer-from" class='form-select'>
                        <option value="Main Shop">Main Shop</option>
                        <option value="Warehouse 1">Warehouse 1</option>
                        <option value="Warehouse 2">Warehouse 2</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="transfer-product" class='form-label'>Product</label>
                    <select id="transfer-product" class='form-select'>
                        <option value="">Select a product</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="transfer-to" class='form-label'>Transfer To</label>
                    <select id="transfer-to" class='form-select'>
                        <option value="Main Shop">Main Shop</option>
                        <option value="Warehouse 1">Warehouse 1</option>
                        <option value="Warehouse 2">Warehouse 2</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="transfer-qty" class='form-label'>Quantity</label>
                    <input type="number" id="transfer-qty" class='form-control' min="1" step="1" placeholder="Enter quantity">
                    <div id="qty-error" class="text-danger small mt-1" style="display: none;"></div>
                </div>
                <div class="mb-3 d-grid">
                    <button id="transfer-btn" class='btn btn-primary'>Transfer</button>
                </div>
            </div>

        </div>
    </main>
</body>
</html>

<script>
$(document).ready(function() {
    // Quantity validation function
    function validateQuantity(input) {
        var value = input.value;
        var errorDiv = $('#qty-error');
        
        // Clear previous error
        errorDiv.hide();
        input.classList.remove('is-invalid');
        
        // Check if empty
        if (value === '') {
            errorDiv.text('Quantity is required').show();
            input.classList.add('is-invalid');
            return false;
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
        if (parseInt(value) <= 0) {
            errorDiv.text('Quantity must be greater than zero').show();
            input.classList.add('is-invalid');
            return false;
        }
        
        return true;
    }
    
    // Stock level validation function
    function validateStockLevel(qty, productId, fromLocation) {
        if (!productId || !fromLocation) {
            return true; // Skip validation if product or location not selected
        }
        
        // Get current stock level for the selected product and location
        $.get('inventory.php', { 
            action: 'get_stock_level', 
            product_id: productId, 
            location: fromLocation 
        }, function(response) {
            if (response.success) {
                var currentStock = response.stock_level;
                var requestedQty = parseInt(qty);
                
                if (requestedQty > currentStock) {
                    $('#qty-error').text('Transfer quantity (' + requestedQty + ') exceeds available stock (' + currentStock + ')').show();
                    $('#transfer-qty').addClass('is-invalid');
                    return false;
                } else {
                    $('#qty-error').hide();
                    $('#transfer-qty').removeClass('is-invalid');
                    return true;
                }
            }
        }, 'json');
        
        return true; // Default to true if we can't check
    }
    
    // Quantity input validation
    $('#transfer-qty').on('input', function() {
        validateQuantity(this);
        // Also check stock level if product is selected
        var productId = $('#transfer-product').val();
        var fromLocation = $('#transfer-from').val();
        if (productId && fromLocation && this.value) {
            validateStockLevel(this.value, productId, fromLocation);
        }
    });
    
    // Quantity input on blur validation
    $('#transfer-qty').on('blur', function() {
        validateQuantity(this);
        // Also check stock level if product is selected
        var productId = $('#transfer-product').val();
        var fromLocation = $('#transfer-from').val();
        if (productId && fromLocation && this.value) {
            validateStockLevel(this.value, productId, fromLocation);
        }
    });
    
    // Populate products when transfer-from changes
    $('#transfer-from').on('change', function() {
        var location = $(this).val();
        $('#transfer-product').html('<option value="">Loading...</option>');
        $.get('inventory.php', { action: 'get_products_by_location', location: location }, function(response) {
            if (response.success) {
                var options = '<option value="">Select a product</option>';
                response.products.forEach(function(prod) {
                    options += '<option value="' + prod.product_id + '">' + prod.item_name + '</option>';
                });
                $('#transfer-product').html(options);
            } else {
                $('#transfer-product').html('<option value="">No products found</option>');
            }
        }, 'json');
    });
    
    // Validate stock level when product selection changes
    $('#transfer-product').on('change', function() {
        var productId = $(this).val();
        var fromLocation = $('#transfer-from').val();
        var qty = $('#transfer-qty').val();
        
        if (productId && fromLocation && qty) {
            validateStockLevel(qty, productId, fromLocation);
        } else {
            // Clear any stock level errors if product is deselected
            $('#qty-error').hide();
            $('#transfer-qty').removeClass('is-invalid');
        }
    });
    
    // Handle transfer
    $('#transfer-btn').on('click', function(e) {
        e.preventDefault();
        var from = $('#transfer-from').val();
        var to = $('#transfer-to').val();
        var product_id = $('#transfer-product').val();
        var qtyInput = $('#transfer-qty');
        var qty = parseInt(qtyInput.val(), 10);
        
        // Validate quantity before proceeding
        if (!validateQuantity(qtyInput[0])) {
            return;
        }
        
        if (!from || !to || !product_id || from === to) {
            alert('Please fill all fields and select different locations.');
            return;
        }
        
        // Check stock level before proceeding with transfer
        $.get('inventory.php', { 
            action: 'get_stock_level', 
            product_id: product_id, 
            location: from 
        }, function(response) {
            if (response.success) {
                var currentStock = response.stock_level;
                if (qty > currentStock) {
                    alert('Transfer quantity (' + qty + ') exceeds available stock (' + currentStock + ') in ' + from);
                    return;
                }
                
                // Proceed with transfer if stock level is sufficient
                $.post('inventory.php', {
                    action: 'transfer_stock',
                    from: from,
                    to: to,
                    product_id: product_id,
                    qty: qty
                }, function(response) {
                    if (response.success) {
                        alert('Transfer successful!');
                        $('#transfer-product').val('');
                        $('#transfer-qty').val('');
                        $('#qty-error').hide();
                        qtyInput.removeClass('is-invalid');
                    } else {
                        alert('Error: ' + (response.error || 'Unknown error'));
                    }
                }, 'json');
            } else {
                alert('Error: Unable to check stock level');
            }
        }, 'json');
    });
    
    // Trigger initial product load
    $('#transfer-from').trigger('change');
});
</script>
