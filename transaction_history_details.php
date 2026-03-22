<?php
// Include database connection
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get transaction ID from URL
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($transactionId <= 0) {
    // Redirect back to transaction history if no valid ID
    header('Location: transaction_history.php');
    exit();
}

// Get transaction details
$query = "SELECT transaction_id, invoice_no, customer_type, subtotal, discount_percentage, grand_total, transaction_date, customer_firstName, customer_middleName, customer_lastName, customer_suffix FROM purchase_transactions WHERE transaction_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $transactionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect back to transaction history if transaction not found
    header('Location: transaction_history.php');
    exit();
}

$transaction = $result->fetch_assoc();

// If credit, fetch interest and total_with_interest from tbl_credit_transactions
$interest = null;
$total_with_interest = null;
if (stripos($transaction['customer_type'], 'credit') !== false) {
    $interestQuery = "SELECT interest, total_with_interest FROM tbl_credit_transactions WHERE transaction_id = ? LIMIT 1";
    $interestStmt = $mysqli->prepare($interestQuery);
    $interestStmt->bind_param('i', $transactionId);
    $interestStmt->execute();
    $interestResult = $interestStmt->get_result();
    if ($row = $interestResult->fetch_assoc()) {
        $interest = $row['interest'];
        $total_with_interest = $row['total_with_interest'];
    }
    $interestStmt->close();
}

// Get transaction details (purchased items)
$detailsQuery = "SELECT ptd.purchased_item_id, 
ptd.transaction_id, 
ptd.product_id, 
ptd.purchased_quantity, 
ptd.unit_price_at_purchase, 
ptd.product_discount,
ptd.product_markup,
ptd.product_subtotal, 
ml.item_name, 
ml.oem_number,
ml.product_size,
ml.unit, 
ml.category, 
ml.product_brand 
FROM purchase_transaction_details ptd 
LEFT JOIN tbl_masterlist ml 
ON ptd.product_id = ml.product_id 
WHERE ptd.transaction_id = ?";

$detailsStmt = $mysqli->prepare($detailsQuery);
$detailsStmt->bind_param('i', $transactionId);
$detailsStmt->execute();
$detailsResult = $detailsStmt->get_result();
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

    <title>DH AUTOCARE - Transaction Details</title>
</head>
<body>
    <?php $activePage = 'transaction_history'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Transaction Details</h1>
                    <p class="page-subtitle">View detailed information about this transaction.</p>
                </div>
            </div>
        </header>
        
        <div class="main-container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="transaction_history.php" class="text-decoration-none">Transaction History</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Transaction Details</li>
                </ol>
            </nav>

            <!-- Invoice Header Card -->
            <div class="content-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-receipt me-3 text-primary"></i>
                            <!-- <div> -->
                                <h5 class="card-title mb-0">Invoice Details</h5>
                                <!-- <small class="text-muted">Transaction Information</small> -->
                            <!-- </div> -->
                        </div>
                        
                    </div>
                    
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="invoice-info">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Invoice Number:</span>
                                    <span class="fw-bold invoice-number"><?php echo htmlspecialchars($transaction['invoice_no']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Transaction Date:</span>
                                    <span class="fw-semibold"><?php echo date('F j, Y', strtotime($transaction['transaction_date'])); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Customer Type:</span>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($transaction['customer_type']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="customer-info">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Customer Name:</span>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($transaction['customer_firstName'] . ' ' . $transaction['customer_middleName'] . ' ' . $transaction['customer_lastName'] . ' ' . $transaction['customer_suffix']);?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Contact Number:</span>
                                    <span class="fw-semibold">-</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Payment Method:</span>
                                    <span class="fw-semibold">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table Card -->
            <div class="content-card mb-4">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <i class="fas fa-boxes me-3 text-primary"></i>
                        <h5 class="card-title mb-0">Products</h5>
                    </div>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#refundProductsModal">
                            <i class="fas fa-history me-1"></i>Refund Product
                        </button>

                        <button class="btn btn-outline-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#exchangeModal">
                            <i class="fas fa-exchange-alt me-1"></i>Exchange Product
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr class='align-middle'>
                                    <th class="ps-4">Product</th>
                                    <th class="text-center">Unit</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Price (₱)</th>
                                    <th class="text-end">Discount (₱)</th>
                                    <th class="text-end">Markup (₱)</th>
                                    <th class="text-end">Subtotal (₱)</th>
                                    <th class="text-end pe-4">Total (₱)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($detailsResult->num_rows > 0): ?>
                                    <?php while ($item = $detailsResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 product-cell">
                                        <div class="product-title"><?php echo strtoupper(htmlspecialchars($item['item_name'] ?? 'Product #' . $item['product_id'])); ?></div>
                                        <div class="product-badges mt-1">
                                            <?php if (!empty($item['oem_number'])): ?>
                                                <span class="badge badge-pill badge-part bg-secondary">#<?php echo htmlspecialchars($item['oem_number']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['product_brand'])): ?>
                                                <span class="badge badge-pill badge-brand bg-light text-dark border"><?php echo strtoupper(htmlspecialchars($item['product_brand'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                            <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?? 'PCS'); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($item['purchased_quantity']); ?></td>
                                            <td class="text-end"><?php echo number_format($item['unit_price_at_purchase'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['product_discount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['product_markup'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['product_subtotal'], 2); ?></td>
                                            <td class="text-end pe-4 fw-bold">₱<?php echo number_format($item['product_subtotal'], 2); ?></td>
                                </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                        <td colspan="7" class="text-center text-muted">No items found for this transaction</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Returned Products Card -->
            <div class="content-card mb-4">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-history me-3 text-primary"></i>
                        <h5 class="card-title mb-0">Returned Products</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Unit Price (₱)</th>
                                    <th>Discount (₱)</th>
                                    <th>Markup (₱)</th>
                                    <th>Subtotal (₱)</th>
                                    <th>Condition</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get returned products for this transaction
                                $returned_query = "SELECT ptd.purchased_item_id, 
                                    ptd.product_id, 
                                    ptd.purchased_quantity, 
                                    ptd.unit_price_at_purchase, 
                                    ptd.product_discount,
                                    ptd.product_markup,
                                    ptd.product_subtotal,
                                    ptd.return_condition,
                                    ptd.return_reason,
                                    ml.item_name, 
                                    ml.oem_number,
                                    ml.product_size,
                                    ml.unit, 
                                    ml.category, 
                                    ml.product_brand 
                                    FROM purchase_transaction_details ptd 
                                    LEFT JOIN tbl_masterlist ml ON ptd.product_id = ml.product_id 
                                    WHERE ptd.transaction_id IN (
                                        SELECT transaction_id FROM purchase_transactions 
                                        WHERE reference_transaction_id = ? AND transaction_type IN ('refund', 'exchange')
                                    )";
                                
                                $returned_stmt = $mysqli->prepare($returned_query);
                                $returned_stmt->bind_param('i', $transactionId);
                                $returned_stmt->execute();
                                $returned_result = $returned_stmt->get_result();
                                
                                if ($returned_result->num_rows > 0):
                                    while ($returned_item = $returned_result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="product-cell">
                                        <div class="product-title"><?php echo strtoupper(htmlspecialchars($returned_item['item_name'] ?? 'Product #' . $returned_item['product_id'])); ?></div>
                                        <div class="product-badges mt-1">
                                            <?php if (!empty($returned_item['oem_number'])): ?>
                                                <span class="badge badge-pill badge-part bg-secondary">#<?php echo htmlspecialchars($returned_item['oem_number']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($returned_item['product_brand'])): ?>
                                                <span class="badge badge-pill badge-brand bg-light text-dark border"><?php echo strtoupper(htmlspecialchars($returned_item['product_brand'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($returned_item['unit'] ?? 'PCS'); ?></td>
                                    <td class="text-center"><?php echo $returned_item['purchased_quantity']; ?></td>
                                    <td class="text-end"><?php echo number_format($returned_item['unit_price_at_purchase'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($returned_item['product_discount'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($returned_item['product_markup'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($returned_item['product_subtotal'], 2); ?></td>
                                    <td class="text-center">
                                        <?php if ($returned_item['return_condition'] === 'good'): ?>
                                            <span class="badge bg-success">Good</span>
                                        <?php elseif ($returned_item['return_condition'] === 'bad'): ?>
                                            <span class="badge bg-danger">Bad</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($returned_item['return_reason'] ?? ''); ?></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No returned products found for this transaction</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="content-card">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calculator me-3 text-primary"></i>
                        <h5 class="card-title mb-0">Payment Summary</h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="summary-details">
                                <?php
                                // Calculate total refunded amount
                                $total_refunded = 0;
                                if ($returned_result && $returned_result->num_rows > 0) {
                                    $returned_result->data_seek(0); // Reset pointer to beginning
                                    while ($returned_item = $returned_result->fetch_assoc()) {
                                        $total_refunded += $returned_item['product_subtotal'];
                                    }
                                }
                                ?>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold">₱<?php echo number_format($transaction['subtotal'], 2); ?></span>
                                </div>
                                <?php if ($total_refunded > 0): ?>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Refunded Amount:</span>
                                    <span class="fw-semibold text-danger">-₱<?php echo number_format($total_refunded, 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-3">

                                    <?php if (stripos($transaction['customer_type'], 'credit') !== false && $interest !== null): ?>
                                        <span class="text-muted">Interest:</span>
                                        <span class="fw-semibold text-danger">₱<?php echo number_format($interest, 2); ?></span>
                                    <?php else: ?>

                                        <span class="text-muted">Discount (<?php echo $transaction['discount_percentage']; ?>%):</span>
                                        <span class="fw-semibold text-success">-₱<?php echo number_format($transaction['subtotal'] * ($transaction['discount_percentage'] / 100), 2); ?></span>

                                    <?php endif; ?>
                                    
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold fs-5">Grand Total:</span>
                                    <?php if (stripos($transaction['customer_type'], 'credit') !== false && $total_with_interest !== null): ?>
                                        <?php 
                                        $final_total = $total_with_interest - $total_refunded;
                                        ?>
                                        <span class="fw-bold fs-5 text-primary">₱<?php echo number_format($final_total, 2); ?></span>
                                    <?php else: ?>
                                        <?php 
                                        $final_total = $transaction['grand_total'] - $total_refunded;
                                        ?>
                                        <span class="fw-bold fs-5 text-primary">₱<?php echo number_format($final_total, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Exchange Modal -->
    <div class="modal fade" id="exchangeModal" tabindex="-1" aria-labelledby="exchangeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exchangeModalLabel">Exchange Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Select Product to Exchange:</label>
                            <?php
                            $exchangeQuery = "SELECT ptd.purchased_item_id, ptd.product_id, ptd.purchased_quantity, ptd.unit_price_at_purchase, ptd.product_discount, ptd.product_markup, ml.item_name, ml.oem_number, ml.product_brand, ml.category
                                              FROM purchase_transaction_details ptd
                                              LEFT JOIN tbl_masterlist ml ON ptd.product_id = ml.product_id
                                              WHERE ptd.transaction_id = ?";
                            $exchangeStmt = $mysqli->prepare($exchangeQuery);
                            $exchangeStmt->bind_param('i', $transactionId);
                            $exchangeStmt->execute();
                            $exchangeResult = $exchangeStmt->get_result();
                            ?>
                            <select class="form-select" id="exchangeItemSelect">
                                <option value="">Choose a product...</option>
                                <?php while ($item = $exchangeResult->fetch_assoc()): ?>
                                    <?php
                                        $display = htmlspecialchars($item['item_name']);
                                        if (!empty($item['oem_number'])) $display .= ' - ' . htmlspecialchars($item['oem_number']);
                                        if (!empty($item['product_brand'])) $display .= ' (' . htmlspecialchars($item['product_brand']) . ')';
                                    ?>
                                    <option value="<?php echo $item['purchased_item_id']; ?>"
                                        data-unit-price="<?php echo isset($item['unit_price_at_purchase']) ? htmlspecialchars($item['unit_price_at_purchase']) : 0; ?>"
                                        data-discount="<?php echo isset($item['product_discount']) ? htmlspecialchars($item['product_discount']) : 0; ?>"
                                        data-markup="<?php echo isset($item['product_markup']) ? htmlspecialchars($item['product_markup']) : 0; ?>"
                                        data-quantity="<?php echo isset($item['purchased_quantity']) ? htmlspecialchars($item['purchased_quantity']) : 0; ?>">
                                        <?php echo $display; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="exchangeQuantity">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Unit Price (₱)</label>
                            <input type="number" class="form-control" id="exchangeUnitPrice" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Discount (₱)</label>
                            <input type="number" class="form-control" id="exchangeDiscount" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Markup (₱)</label>
                            <input type="number" class="form-control" id="exchangeMarkup">
                        </div>    
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Subtotal (₱)</label>
                            <input type="number" class="form-control" id="exchangeSubtotal" readonly>
                        </div>
                        <!-- <div class="col-md-2 mb-3">
                            <label class="form-label">Total (₱)</label>
                            <input type="number" class="form-control" id="exchangeTotal" readonly>
                        </div> -->

                        <div class="col-md-6 mb-3">
                            <label for="exchangeReturnCondition" class='form-label'>Product Condition</label>
                            <select id="exchangeReturnCondition" class='form-select'>
                                <option value="">Select condition..</option>
                                <option value="good">Good Condition</option>
                                <option value="bad">Bad Condition</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="exchangeReturnReason" class='form-label'>Reason for Return</label>
                            <input type="text" id="exchangeReturnReason" class='form-control' placeholder="Enter reason for exchange">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Select Replacement Product:</label>
                            <select id="replacementProductSelect" class="form-select">
                                <option value="">Choose a product...</option>
                                <?php
                                $inventoryQuery = "SELECT i.inventory_id, i.product_id, i.stock_level, i.storage_location, ml.item_name, ml.oem_number, ml.selling_price
                                                  FROM tbl_inventory i
                                                  LEFT JOIN tbl_masterlist ml ON i.product_id = ml.product_id
                                                  WHERE i.stock_level > 0
                                                  ORDER BY ml.item_name ASC";
                                $inventoryResult = $mysqli->query($inventoryQuery);
                                if ($inventoryResult && $inventoryResult->num_rows > 0):
                                    while ($inv = $inventoryResult->fetch_assoc()):
                                        $display = htmlspecialchars($inv['item_name']);
                                        if (!empty($inv['oem_number'])) $display .= ' - ' . htmlspecialchars($inv['oem_number']);
                                        $display .= ' (Stock: ' . $inv['stock_level'] . ', Loc: ' . htmlspecialchars($inv['storage_location']) . ')';
                                ?>
                                    <option value="<?php echo $inv['inventory_id']; ?>"
                                        data-product-id="<?php echo $inv['product_id']; ?>"
                                        data-stock-level="<?php echo $inv['stock_level']; ?>"
                                        data-storage-location="<?php echo htmlspecialchars($inv['storage_location']); ?>"
                                        data-selling-price="<?php echo isset($inv['selling_price']) ? htmlspecialchars($inv['selling_price']) : 0; ?>">
                                        <?php echo $display; ?>
                                    </option>
                                <?php
                                    endwhile;
                                endif;
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="replacementQuantity">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Unit Price (₱)</label>
                            <input type="number" class="form-control" id="replacementUnitPrice" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Discount (₱)</label>
                            <input type="number" class="form-control" id="replacementDiscount">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Markup (₱)</label>
                            <input type="number" class="form-control" id="replacementMarkup">
                        </div>    
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Subtotal (₱)</label>
                            <input type="number" class="form-control" id="replacementSubtotal" readonly>
                        </div>
                        <!-- <div class="col-md-2 mb-3">
                            <label class="form-label">Total (₱)</label>
                            <input type="number" class="form-control" id="replacementTotal" readonly>
                        </div> -->
                    </div>

                    <div class='alert alert-info'>
                        <div class="d-flex justify-content-between">
                            <label for="newProductAmount">New Product Amount</label>
                            <span id="newProductAmount">₱0.00</span>
                        </div>

                        <div class="d-flex justify-content-between">
                            <label for="returnedProductAmount">Returned Product Amount</label>
                            <span id="returnedProductAmount">₱0.00</span>
                        </div>

                        <div class="d-flex justify-content-between">
                            <label for=""><strong>Customer Needs to Pay:</strong></label>
                            <strong><span id="customerNeedsToPay">₱0.00</span></strong>
                        </div>
                    </div>

                    <div class="d-grid">
                            <button class='btn btn-primary' id="confirmExchangeBtn">Confirm Exchange</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Refund Products Modal -->
    <div class="modal fade" id="refundProductsModal" tabindex="-1" aria-labelledby="refundProductsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="refundProductsModalLabel">Refund/Exchange Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Return Policy Notice -->
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Return Policy:</strong> Items can be returned or exchanged within 3 days of purchase. 
                        Returns are accepted in good or damaged condition. Refunds are given in cash or exchange for items of equal or higher value.
                    </div>

                    <!-- Return Details Form -->
                    <div id="returnDetailsForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Product to Refund:</label>
                            <select class="form-select" id="returnItemSelect">
                                <option value="">Choose a product...</option>
                                <?php 
                                // Reset the result pointer to the beginning
                                $detailsResult->data_seek(0);
                                
                                // Group items by unique product combination
                                $uniqueItems = [];
                                while ($item = $detailsResult->fetch_assoc()) {
                                    $key = $item['product_id'] . '_' . $item['unit_price_at_purchase'] . '_' . $item['product_discount'] . '_' . $item['product_markup'];
                                    
                                    if (!isset($uniqueItems[$key])) {
                                        $uniqueItems[$key] = [
                                            'item' => $item,
                                            'total_quantity' => $item['purchased_quantity'],
                                            'purchased_item_ids' => [$item['purchased_item_id']]
                                        ];
                                    } else {
                                        $uniqueItems[$key]['total_quantity'] += $item['purchased_quantity'];
                                        $uniqueItems[$key]['purchased_item_ids'][] = $item['purchased_item_id'];
                                    }
                                }
                                
                                foreach ($uniqueItems as $key => $groupedItem): 
                                    $item = $groupedItem['item'];
                                    // Build display text with product name, part number/size, and brand
                                    $displayText = htmlspecialchars($item['item_name'] ?? 'Product #' . $item['product_id']);
                                    
                                    // Add part number or size if available
                                    if (!empty($item['oem_number'])) {
                                        $displayText .= ' - ' . htmlspecialchars($item['oem_number']);
                                    } elseif (!empty($item['product_size'])) {
                                        $displayText .= ' - ' . htmlspecialchars($item['product_size']);
                                    }
                                    
                                    // Add brand if available
                                    if (!empty($item['product_brand'])) {
                                        $displayText .= ' (' . htmlspecialchars($item['product_brand']) . ')';
                                    }
                                    
                                    // Add quantity to display text
                                    $displayText .= ' (Qty: ' . $groupedItem['total_quantity'] . ')';
                                ?>
                                    <option value="<?php echo implode(',', $groupedItem['purchased_item_ids']); ?>" 
                                            data-product-id="<?php echo $item['product_id']; ?>"
                                            data-quantity="<?php echo $groupedItem['total_quantity']; ?>"
                                            data-unit-price="<?php echo $item['unit_price_at_purchase']; ?>"
                                            data-discount="<?php echo $item['product_discount']; ?>"
                                            data-markup="<?php echo $item['product_markup']; ?>"
                                            data-subtotal="<?php echo $item['product_subtotal'] * ($groupedItem['total_quantity'] / $item['purchased_quantity']); ?>">
                                        <?php echo $displayText; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Return Quantity:</label>
                                    <input type="number" class="form-control" id="returnQuantity">
                                </div>                                
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Product Condition:</label>
                                <select class="form-select" id="returnCondition">
                                    <option value="">Select condition...</option>
                                    <option value="good">Good Condition</option>
                                    <option value="bad">Bad Condition</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Reason for Return:</label>
                                <input type="text" name="return_reason" id="return_reason" class="form-control" placeholder="Enter reason for return">
                            </div>
                        </div>

                        <!-- Refund Summary -->
                        <div class='mt-3' id="refundSummary">
                            <hr>
                            <div class="alert alert-success">
                                <h6>Refund Summary:</h6>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <p>Original Price:</p>
                                    <span id="refundOriginalPrice">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <p>Discount Applied:</p>
                                    <span id="refundDiscount">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <p>Markup Applied:</p>
                                    <span id="refundMarkup">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <p><strong>Refund Amount:</strong></p>
                                    <span id="refundAmount"><strong>₱0.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class='d-grid mt-3'>
                        <button id="confirmRefundBtn" class="btn btn-primary">Confirm Refund</button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Exchange Product Modal -->
    <div class="modal fade" id="exchangeProductModal" tabindex="-1" aria-labelledby="exchangeProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exchangeProductModalLabel">Exchange Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    hello world
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Handle return item selection
            $('#returnItemSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                if (selectedOption.val()) {
                    const quantity = parseInt(selectedOption.data('quantity'));
                    const unitPrice = parseFloat(selectedOption.data('unit-price'));
                    const discount = parseFloat(selectedOption.data('discount'));
                    const markup = parseFloat(selectedOption.data('markup'));
                    const subtotal = parseFloat(selectedOption.data('subtotal'));
                    
                    // Set max quantity for return
                    $('#returnQuantity').attr('max', quantity);
                    $('#returnQuantity').val(1);
                    
                    // Update refund summary
                    updateRefundSummary(unitPrice, discount, markup, 1);
                }
            });

            // Handle return quantity change
            $('#returnQuantity').on('input', function() {
                const selectedOption = $('#returnItemSelect').find('option:selected');
                if (selectedOption.val()) {
                    const unitPrice = parseFloat(selectedOption.data('unit-price'));
                    const discount = parseFloat(selectedOption.data('discount'));
                    const markup = parseFloat(selectedOption.data('markup'));
                    const quantity = parseInt($(this).val()) || 0;
                    
                    updateRefundSummary(unitPrice, discount, markup, quantity);
                }
            });

            // Handle new item selection for exchange
            $('#newItemSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                if (selectedOption.val()) {
                    const unitPrice = parseFloat(selectedOption.data('unit-price'));
                    const stockLevel = parseInt(selectedOption.data('stock-level'));
                    
                    // Set unit price
                    $('#newItemUnitPrice').val(unitPrice.toFixed(2));
                    
                    // Set max quantity for new item
                    $('#newItemQuantity').attr('max', stockLevel);
                    $('#newItemQuantity').val(1);
                    
                    // Update exchange summary
                    updateExchangeSummary(unitPrice, 0, 0, 1);
                }
            });

            // Handle new item quantity change
            $('#newItemQuantity').on('input', function() {
                const selectedOption = $('#newItemSelect').find('option:selected');
                if (selectedOption.val()) {
                    const unitPrice = parseFloat(selectedOption.data('unit-price'));
                    const discount = parseFloat($('#newItemDiscount').val()) || 0;
                    const markup = parseFloat($('#newItemMarkup').val()) || 0;
                    const quantity = parseInt($(this).val()) || 0;
                    
                    updateExchangeSummary(unitPrice, discount, markup, quantity);
                }
            });

            // Handle new item discount/markup changes
            $('#newItemDiscount, #newItemMarkup').on('input', function() {
                const selectedOption = $('#newItemSelect').find('option:selected');
                if (selectedOption.val()) {
                    const unitPrice = parseFloat(selectedOption.data('unit-price'));
                    const discount = parseFloat($('#newItemDiscount').val()) || 0;
                    const markup = parseFloat($('#newItemMarkup').val()) || 0;
                    const quantity = parseInt($('#newItemQuantity').val()) || 0;
                    
                    updateExchangeSummary(unitPrice, discount, markup, quantity);
                }
            });

            // Handle exchange item selection
            $('#exchangeItemSelect').on('change', function() {
                var selected = $(this).find('option:selected');
                var unitPrice = parseFloat(selected.data('unit-price')) || 0;
                var discount = parseFloat(selected.data('discount')) || 0;
                var markup = parseFloat(selected.data('markup')) || 0;
                var quantity = parseInt(selected.data('quantity')) || 1;

                $('#exchangeUnitPrice').val(unitPrice);
                $('#exchangeDiscount').val(discount);
                $('#exchangeMarkup').val(markup).attr('max', markup);
                $('#exchangeQuantity').val(1).attr('max', quantity);

                // Calculate subtotal for 1 quantity by default, markup only once
                var subtotal = (unitPrice - discount) * 1 + markup;
                $('#exchangeSubtotal').val(subtotal);
                // $('#exchangeTotal').val(subtotal);
            });

            // Update subtotal/total when quantity or markup changes
            $('#exchangeQuantity, #exchangeMarkup').on('input', function() {
                var selected = $('#exchangeItemSelect').find('option:selected');
                var unitPrice = parseFloat(selected.data('unit-price')) || 0;
                var discount = parseFloat(selected.data('discount')) || 0;
                var markup = parseFloat($('#exchangeMarkup').val()) || 0;
                var maxMarkup = parseFloat($('#exchangeMarkup').attr('max')) || 0;
                if (markup > maxMarkup) { $('#exchangeMarkup').val(maxMarkup); markup = maxMarkup; }
                var qty = parseInt($('#exchangeQuantity').val()) || 1;
                var maxQty = parseInt(selected.data('quantity')) || 1;
                if (qty > maxQty) { $('#exchangeQuantity').val(maxQty); qty = maxQty; }
                var subtotal = (unitPrice - discount) * qty + markup;
                $('#exchangeSubtotal').val(subtotal);
                // $('#exchangeTotal').val(subtotal);
            });

            // Handle replacement product selection
            $('#replacementProductSelect').on('change', function() {
                var selected = $(this).find('option:selected');
                var sellingPrice = parseFloat(selected.data('selling-price')) || 0;
                var stockLevel = parseInt(selected.data('stock-level')) || 1;
                $('#replacementUnitPrice').val(sellingPrice);
                $('#replacementQuantity').val(1).attr('max', stockLevel);
                $('#replacementDiscount').val(0);
                $('#replacementMarkup').val(0);
                var qty = 1;
                var discount = 0;
                var markup = 0;
                var subtotal = (sellingPrice - discount) * qty + markup;
                $('#replacementSubtotal').val(subtotal);
                // $('#replacementTotal').val(subtotal);
            });
            $('#replacementQuantity, #replacementDiscount, #replacementMarkup').on('input', function() {
                var selected = $('#replacementProductSelect').find('option:selected');
                var sellingPrice = parseFloat(selected.data('selling-price')) || 0;
                var stockLevel = parseInt(selected.data('stock-level')) || 1;
                var qty = parseInt($('#replacementQuantity').val()) || 1;
                if (qty > stockLevel) { $('#replacementQuantity').val(stockLevel); qty = stockLevel; }
                var discount = parseFloat($('#replacementDiscount').val()) || 0;
                var markup = parseFloat($('#replacementMarkup').val()) || 0;
                var subtotal = (sellingPrice - discount) * qty + markup;
                $('#replacementSubtotal').val(subtotal);
                // $('#replacementTotal').val(subtotal);
            });

            function updateRefundSummary(unitPrice, discount, markup, quantity) {
                const originalPrice = unitPrice * quantity;
                const totalDiscount = discount * quantity;
                const totalMarkup = markup * quantity;
                const refundAmount = originalPrice - totalDiscount + totalMarkup;
                
                $('#refundOriginalPrice').text('₱' + originalPrice.toFixed(2));
                $('#refundDiscount').text('₱' + totalDiscount.toFixed(2));
                $('#refundMarkup').text('₱' + totalMarkup.toFixed(2));
                $('#refundAmount').html('<strong>₱' + refundAmount.toFixed(2) + '</strong>');
            }

            function updateExchangeSummary(unitPrice, discount, markup, quantity) {
                const originalPrice = unitPrice * quantity;
                const totalDiscount = discount * quantity;
                const totalMarkup = markup * quantity;
                const exchangeAmount = originalPrice - totalDiscount + totalMarkup;
                
                $('#exchangeOriginalPrice').text('₱' + originalPrice.toFixed(2));
                $('#exchangeDiscount').text('₱' + totalDiscount.toFixed(2));
                $('#exchangeMarkup').text('₱' + totalMarkup.toFixed(2));
                $('#exchangeAmount').html('<strong>₱' + exchangeAmount.toFixed(2) + '</strong>');
            }

            function updateExchangeAmounts() {
                // Get values
                var originalSubtotal = parseFloat($('#exchangeSubtotal').val()) || 0;
                var replacementSubtotal = parseFloat($('#replacementSubtotal').val()) || 0;
                var customerNeedsToPay = replacementSubtotal - originalSubtotal;
                // Update display
                $('#newProductAmount').text('₱' + replacementSubtotal.toFixed(2));
                $('#returnedProductAmount').text('₱' + originalSubtotal.toFixed(2));
                $('#customerNeedsToPay').text('₱' + customerNeedsToPay.toFixed(2));
            }

            // Call updateExchangeAmounts on relevant field changes
            $('#exchangeItemSelect, #exchangeQuantity, #exchangeMarkup, #replacementProductSelect, #replacementQuantity, #replacementDiscount, #replacementMarkup').on('change input', function() {
                updateExchangeAmounts();
            });

            // Also call once on page load to initialize
            updateExchangeAmounts();

            // Wire up the confirm refund button
            $(document).on('click', '#confirmRefundBtn', function(e) {
                e.preventDefault();
                processReturn();
            });

            function processReturn() {
                const selectedOption = $('#returnItemSelect').find('option:selected');
                if (!selectedOption.val()) {
                    alert('Please select a product to return.');
                    return;
                }

                const returnQuantity = parseInt($('#returnQuantity').val());
                const returnCondition = $('#returnCondition').val();
                const returnReason = $('#return_reason').val();

                if (!returnQuantity || returnQuantity <= 0) {
                    alert('Please enter a valid return quantity.');
                    return;
                }

                if (!returnCondition) {
                    alert('Please select the product condition.');
                    return;
                }

                if (!returnReason.trim()) {
                    alert('Please enter a reason for the return.');
                    return;
                }

                const maxQuantity = parseInt(selectedOption.data('quantity'));
                if (returnQuantity > maxQuantity) {
                    alert('Return quantity cannot exceed the original purchase quantity.');
                    return;
                }

                // Prepare data for processing
                const returnData = {
                    transaction_id: <?php echo $transactionId; ?>,
                    transaction_type: 'refund', // Use 'refund' for returns
                    purchased_item_ids: selectedOption.val().split(','),
                    return_quantity: returnQuantity,
                    return_condition: returnCondition,
                    return_reason: returnReason,
                    product_id: parseInt(selectedOption.data('product-id')),
                    unit_price: parseFloat(selectedOption.data('unit-price')),
                    discount: parseFloat(selectedOption.data('discount')),
                    markup: parseFloat(selectedOption.data('markup'))
                };

                // Show loading state on this specific button
                const saveBtn = $('#confirmRefundBtn');
                const originalText = saveBtn.text();
                saveBtn.text('Processing...').prop('disabled', true);

                // Send AJAX request
                $.ajax({
                    url: 'process_return.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(returnData),
                                    success: function(response) {
                    if (response.success) {
                        let message = 'Return processed successfully! ';
                        if (response.inventory_updated) {
                            message += 'Item returned to inventory at ' + response.return_location + '.';
                        } else {
                            message += 'Item not returned to inventory due to bad condition.';
                        }
                        alert(message);
                        
                        // Close modal and refresh page
                        $('#refundProductsModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error processing return: ' + response.error);
                    }
                },
                    error: function(xhr, status, error) {
                        alert('Error processing return: ' + error);
                    },
                    complete: function() {
                        saveBtn.text(originalText).prop('disabled', false);
                    }
                });
            }

            function processExchange() {
                const exchangeOption = $('#exchangeItemSelect').find('option:selected');
                const replacementOption = $('#replacementProductSelect').find('option:selected');

                if (!exchangeOption.val()) {
                    alert('Please select the product to exchange.');
                    return;
                }
                if (!replacementOption.val()) {
                    alert('Please select a replacement product.');
                    return;
                }

                const exchangeQuantity = parseInt($('#exchangeQuantity').val()) || 0;
                const maxExchangeQty = parseInt(exchangeOption.data('quantity')) || 0;
                if (exchangeQuantity <= 0) {
                    alert('Please enter a valid exchange quantity.');
                    return;
                }
                if (exchangeQuantity > maxExchangeQty) {
                    alert('Exchange quantity cannot exceed the original purchase quantity.');
                    return;
                }

                const replacementQty = parseInt($('#replacementQuantity').val()) || 0;
                const replacementStock = parseInt(replacementOption.data('stock-level')) || 0;
                if (replacementQty <= 0) {
                    alert('Please enter a valid replacement quantity.');
                    return;
                }
                if (replacementQty > replacementStock) {
                    alert('Replacement quantity cannot exceed available stock.');
                    return;
                }

                const originalUnitPrice = parseFloat($('#exchangeUnitPrice').val()) || 0;
                const originalDiscount = parseFloat($('#exchangeDiscount').val()) || 0;
                const originalMarkup = parseFloat($('#exchangeMarkup').val()) || 0;
                const replacementUnitPrice = parseFloat($('#replacementUnitPrice').val()) || 0;
                const replacementDiscount = parseFloat($('#replacementDiscount').val()) || 0;
                const replacementMarkup = parseFloat($('#replacementMarkup').val()) || 0;

                const originalSubtotal = parseFloat($('#exchangeSubtotal').val()) || 0;
                const replacementSubtotal = parseFloat($('#replacementSubtotal').val()) || 0;

                if (replacementSubtotal < originalSubtotal) {
                    alert('The replacement product\'s subtotal cannot be less than the original product.');
                    return;
                }

                const returnCondition = ($('#exchangeReturnCondition').val() || '').trim();
                const returnReason = ($('#exchangeReturnReason').val() || '').trim();
                if (!returnCondition) {
                    alert('Please select the product condition for the item being returned.');
                    return;
                }
                if (!returnReason) {
                    alert('Please provide a reason for the exchange.');
                    return;
                }

                const payload = {
                    transaction_id: <?php echo $transactionId; ?>,
                    original: {
                        purchased_item_id: parseInt(exchangeOption.val()),
                        product_id: null, // server will resolve from purchased_item_id
                        quantity: exchangeQuantity,
                        unit_price: originalUnitPrice,
                        discount: originalDiscount,
                        markup: originalMarkup,
                        return_condition: returnCondition,
                        return_reason: returnReason
                    },
                    replacement: {
                        inventory_id: parseInt(replacementOption.val()),
                        product_id: parseInt(replacementOption.data('product-id')),
                        quantity: replacementQty,
                        unit_price: replacementUnitPrice,
                        discount: replacementDiscount,
                        markup: replacementMarkup,
                        storage_location: replacementOption.data('storage-location') || ''
                    }
                };

                const btn = $('#confirmExchangeBtn');
                const originalText = btn.text();
                btn.text('Processing...').prop('disabled', true);

                $.ajax({
                    url: 'process_exchange.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: function(response) {
                        if (response && response.success) {
                            alert('Exchange processed successfully! Customer pays: ₱' + (response.customer_pays || 0).toFixed(2));
                            $('#exchangeModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Error processing exchange: ' + (response && response.error ? response.error : 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error processing exchange: ' + error);
                    },
                    complete: function() {
                        btn.text(originalText).prop('disabled', false);
                    }
                });
            }

            // Intercept Confirm Exchange button click
            $(document).on('click', '#confirmExchangeBtn', function(e) {
                e.preventDefault();
                // Get the original and replacement subtotals
                var originalSubtotal = parseFloat($('#exchangeSubtotal').val()) || 0;
                var replacementSubtotal = parseFloat($('#replacementSubtotal').val()) || 0;

                // Check if replacement subtotal is less than original
                if (replacementSubtotal < originalSubtotal) {
                    alert('The replacement product\'s subtotal cannot be less than the original product.');
                    return false;
                }

                // Proceed to process exchange
                processExchange();
            });
        });
    </script>

</body>
</html>