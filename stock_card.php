<?php
    include('mycon.php');
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    if ($product_id <= 0) {
        echo '<div class="alert alert-danger">Invalid product ID.</div>';
        exit;
    }
    // Fetch product info
    $product = $mysqli->query("SELECT * FROM tbl_masterlist WHERE product_id = $product_id")->fetch_assoc();
    if (!$product) {
        echo '<div class="alert alert-danger">Product not found.</div>';
        exit;
    }
    // Get all stock movements for this product
    $movements = [];
    
    // 1. Stock In (from tbl_stock_transfers)
    $stock_in_sql = "
    SELECT 
        st.created_at AS date, 
        CONCAT('STK-', LPAD(st.transfer_id, 3, '0')) AS reference, 
        'Stock in' AS description,
        '' as from_location,
        st.location as to_location,
        st.quantity AS stock_in, 
        0 AS stock_out, 
        st.location AS warehouse, 
        'N/A' AS user, 
        CONCAT('Stocked in to ', st.location) AS remarks,
        COALESCE(sup.supplier_name, 'N/A') AS supplier_name
    FROM tbl_stock_transfers st
    LEFT JOIN tbl_spo spo ON st.spo_id = spo.spo_id
    LEFT JOIN tbl_supplier sup ON spo.supplier_id = sup.supplier_id
    WHERE st.product_id = $product_id
    ORDER BY st.created_at, st.transfer_id";
    
    $stock_in_result = $mysqli->query($stock_in_sql);
    $stock_in = $stock_in_result ? $stock_in_result->fetch_all(MYSQLI_ASSOC) : [];

    // 2. Customer Purchase / Exchange Replacement (stock out)
    // - Count SALE rows as stock out
    // - Count EXCHANGE rows as stock out ONLY when return_condition is NULL/empty (replacement item)
    // - Exclude REFUND rows
    $stock_out_sql = "
    SELECT 
        pt.created_at AS date,
        pt.invoice_no AS reference,
        CASE WHEN pt.transaction_type = 'sale' THEN 'Customer Purchase' ELSE 'Exchange Replacement' END AS description,
        'Main Shop' as from_location,
        '' as to_location,
        0 AS stock_in,
        d.purchased_quantity AS stock_out,
        'Main Shop' AS warehouse,
        'N/A' AS user,
        CASE 
            WHEN pt.transaction_type = 'sale' THEN CONCAT('Sold ', d.purchased_quantity, ' units to customer')
            ELSE CONCAT('Issued ', d.purchased_quantity, ' units as replacement in exchange')
        END AS remarks,
        '' AS supplier_name,
        -- Pairing info for exchange replacements
        CASE WHEN pt.transaction_type = 'exchange' THEN COALESCE(UPPER(ret_ml.item_name), '') ELSE '' END AS exchange_pair,
        CASE WHEN pt.transaction_type = 'exchange' THEN 'Replacement for' ELSE '' END AS pair_label
    FROM purchase_transaction_details d
    JOIN purchase_transactions pt ON d.transaction_id = pt.transaction_id
    LEFT JOIN exchange_item_links eil ON eil.exchange_transaction_detail_id = d.purchased_item_id
    LEFT JOIN purchase_transaction_details ret ON ret.purchased_item_id = eil.return_transaction_id
    LEFT JOIN tbl_masterlist ret_ml ON ret.product_id = ret_ml.product_id
    WHERE d.product_id = $product_id
      AND (
            pt.transaction_type = 'sale'
         OR (pt.transaction_type = 'exchange' AND (d.return_condition IS NULL OR d.return_condition = ''))
      )
    ORDER BY pt.created_at, pt.transaction_id";
    
    $stock_out_result = $mysqli->query($stock_out_sql);
    $stock_out = $stock_out_result ? $stock_out_result->fetch_all(MYSQLI_ASSOC) : [];

    // 3. Returned Products (Stock In from refunds/exchanges with good condition)
    // This captures refund or exchange transactions where items are returned in good condition and added back to inventory
    $returned_products_sql = "
    SELECT 
        pt.created_at AS date, 
        pt.invoice_no AS reference, 
        'Returned Product' AS description,
        '' as from_location,
        'Main Shop' as to_location,
        d.purchased_quantity AS stock_in, 
        0 AS stock_out, 
        'Main Shop' AS warehouse, 
        'N/A' AS user, 
        CONCAT('Returned ', d.purchased_quantity, ' units in good condition') AS remarks,
        '' AS supplier_name,
        -- Pairing info for exchange returns
        COALESCE(UPPER(rep_ml.item_name), '') AS exchange_pair,
        'Exchanged with' AS pair_label
    FROM purchase_transaction_details d
    JOIN purchase_transactions pt ON d.transaction_id = pt.transaction_id
    LEFT JOIN exchange_item_links eil ON eil.return_transaction_id = d.purchased_item_id
    LEFT JOIN purchase_transaction_details repd ON repd.purchased_item_id = eil.exchange_transaction_detail_id
    LEFT JOIN tbl_masterlist rep_ml ON repd.product_id = rep_ml.product_id
    WHERE d.product_id = $product_id 
    AND pt.transaction_type IN ('refund','exchange')
    AND d.return_condition = 'good'
    ORDER BY pt.created_at, pt.transaction_id";
    
    $returned_products_result = $mysqli->query($returned_products_sql);
    $returned_products = $returned_products_result ? $returned_products_result->fetch_all(MYSQLI_ASSOC) : [];

    // Merge and sort
    $movements = array_merge($stock_in, $stock_out, $returned_products);
    usort($movements, function($a, $b) {
        $cmp = strcmp($a['date'], $b['date']);
        if ($cmp === 0) {
            // If same date, sort by reference number
            return strcmp($a['reference'], $b['reference']);
        }
        return $cmp;
    });

    // Get current stock level to understand the starting point
    $current_stock_sql = "SELECT SUM(stock_level) as total_stock FROM tbl_inventory WHERE product_id = $product_id";
    $current_stock_result = $mysqli->query($current_stock_sql);
    $current_stock = $current_stock_result ? $current_stock_result->fetch_assoc()['total_stock'] : 0;

    // Calculate running balance - start from 0 and accumulate
    $balance = 0;
    foreach ($movements as $k => $row) {
        $balance += ($row['stock_in'] ?? 0) - ($row['stock_out'] ?? 0);
        $movements[$k]['balance'] = $balance;
    }

    // Fetch actual stock per warehouse for this product
    $warehouse_sql = "SELECT storage_location, stock_level FROM tbl_inventory WHERE product_id = $product_id";
    $warehouse_result = $mysqli->query($warehouse_sql);
    $warehouses = $warehouse_result ? $warehouse_result->fetch_all(MYSQLI_ASSOC) : [];
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Stock Card</title>
        <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/all.min.css">

        <link rel="stylesheet" href="css/interfont.css">
        <link rel="stylesheet" href="css/style.css">
        <style>
            body {
                background-color: #f4f6fb;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .stock-card-header {
                background: #fff;
                border-radius: 14px;
                box-shadow: 0 4px 16px rgba(37,99,235,0.07);
                padding: 2rem 2.5rem 1.5rem 2.5rem;
                margin-bottom: 2rem;
            }
            .stock-card-summary-label {
                color: #64748b;
                font-size: 1rem;
            }
            .stock-card-summary-value {
                color: #22223b;
                font-weight: 600;
                font-size: 1.08rem;
            }
            .table-container {
                background: #fff;
                border-radius: 14px;
                box-shadow: 0 4px 16px rgba(37,99,235,0.07);
                overflow: hidden;
                margin-bottom: 2rem;
            }
            .table {
                margin: 0;
                font-size: 1.01rem;
            }
            .table thead th {
                background-color: #e9ecf4 !important;
                color: #22223b !important;
                font-weight: 700;
                border: none;
                padding: 0.85rem;
                font-size: 0.97rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .table-striped > tbody > tr:nth-of-type(odd) {
                background-color: #f4f6fb;
            }
            .table tbody td {
                padding: 0.85rem;
                border-color: #e5e7eb;
                vertical-align: middle;
            }
            .table tbody tr:hover {
                background-color: #e0e7ff;
            }
            .btn-actions {
                display: flex;
                gap: 0.7rem;
                margin-bottom: 2rem;
            }
            .btn-custom {
                border-radius: 8px;
                font-weight: 600;
                padding: 0.6rem 1.3rem;
                font-size: 1.01rem;
                box-shadow: 0 2px 8px rgba(37,99,235,0.08);
                transition: background 0.15s, box-shadow 0.15s;
                border: 1.5px solid #2563eb;
                background: #fff;
                color: #2563eb;
            }
            .btn-custom:hover {
                background: #2563eb;
                color: #fff;
                box-shadow: 0 4px 16px rgba(37,99,235,0.13);
            }
            .stock-in {
                color: #22c55e;
                font-weight: 600;
            }
            .stock-out {
                color: #ef4444;
                font-weight: 600;
            }
            .returned-product {
            }
            .balance {
                font-weight: 700;
                color: #22223b;
            }
            .no-data {
                text-align: center;
                color: #64748b;
                padding: 2.2rem;
                font-style: italic;
                font-size: 1.08rem;
            }
            @media print {
                body, html {
                    background: #fff !important;
                }
                .btn-actions, .btn-custom, .btn, .no-print, .navbar, .sidebar, .sidebar-wrapper, .sidebar-menu, .sidebar-footer, .sidebar-header, .sidebar-brand, .sidebar-content, .sidebar-bg, .sidebar-logo, .sidebar-toggle, .sidebar-user, .sidebar-nav, .sidebar-link, .sidebar-dropdown, .sidebar-submenu, .sidebar-footer, .sidebar-search, .sidebar-search input, .sidebar-search .input-group, .sidebar-search .input-group-append, .sidebar-search .input-group-text, .sidebar-search .form-control, .sidebar-search .form-control:focus, .sidebar-search .form-control:active, .sidebar-search .form-control:disabled, .sidebar-search .form-control[readonly], .sidebar-search .form-control[readonly]:focus, .sidebar-search .form-control[readonly]:active, .sidebar-search .form-control[readonly]:disabled, .sidebar-search .form-control[readonly]:not([readonly]), .sidebar-search .form-control[readonly]:not([readonly]):focus, .sidebar-search .form-control[readonly]:not([readonly]):active, .sidebar-search .form-control[readonly]:not([readonly]):disabled {
                    display: none !important;
                }
                .container, .stock-card-header, .table-container {
                    box-shadow: none !important;
                    background: #fff !important;
                }
                .table thead th {
                    background: #e9ecf4 !important;
                    color: #22223b !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>
    <body>
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 style="font-weight:700;color:#22223b;"><i class="fas fa-file-alt me-2"></i>Stock Card</h3>
                <div class="btn-actions no-print">
                    <button onclick="window.print()" class="btn btn-custom">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <a href="master_list.php" class="btn btn-custom">
                        <i class="fas fa-arrow-left me-1"></i> Back to Stock List
                    </a>
                </div>
            </div>
            <div class="stock-card-header mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="stock-card-summary-label">Product Name</div>
                        <div class="stock-card-summary-value"><?php echo htmlspecialchars($product['item_name']); ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Part No./Size</div>
                        <div class="stock-card-summary-value">
                            <?php
                            // Display either part number, size, or blank space
                            $partNumberOrSize = '';
                            if (!empty($product['oem_number'])) {
                                $partNumberOrSize = $product['oem_number'];
                            } elseif (!empty($product['product_size'])) {
                                $partNumberOrSize = $product['product_size'];
                            }
                            echo htmlspecialchars($partNumberOrSize);
                            ?>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Brand</div>
                        <div class="stock-card-summary-value">
                            <?php echo htmlspecialchars($product['product_brand'])?>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Category</div>
                        <div class="stock-card-summary-value"><?php echo htmlspecialchars($product['category']); ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Unit</div>
                        <div class="stock-card-summary-value"><?php echo htmlspecialchars($product['unit']); ?></div>
                    </div>

                    
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Price</div>
                        <div class="stock-card-summary-value"><?php echo htmlspecialchars($product['selling_price']); ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Status</div>
                        <div class="stock-card-summary-value"><?php echo htmlspecialchars($product['status']); ?></div>
                    </div>
                    <div class="col-md-2">
                        <div class="stock-card-summary-label">Date Added</div>
                        <div class="stock-card-summary-value"><?php echo htmlspecialchars($product['date_added']); ?></div>
                    </div>
                </div>
            </div>
            <?php if (!empty($warehouses)): ?>
            <div class="table-container mb-4">
                <h5 class="mb-2 p-3"><i class="fas fa-warehouse me-2"></i>Stock by Storage Location</h5>
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr class='align-middle'>
                            <th>Location</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouses as $wh): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($wh['storage_location']); ?></td>
                            <td class="text-center fw-bold"><?php echo number_format($wh['stock_level']); ?></td>
                            <td class='text-end'>₱<?php echo number_format($wh['stock_level'] * $product['selling_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="table-container">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr class='align-middle text-center'>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Supplier</th>
                            <th>Description</th>
                            <th>Stock In</th>
                            <th>Stock Out</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($movements) === 0): ?>
                            <tr><td colspan="6" class="no-data">No stock movement data found for this product.</td></tr>
                        <?php else: ?>
                        <?php foreach ($movements as $row): ?>
                        <tr class='align-middle'>
                            <td><?php echo date('F j, Y', strtotime($row['date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['reference']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td>
                                <?php if ($row['description'] === 'Returned Product'): ?>
                                    <span class="returned-product"><?php echo htmlspecialchars($row['description']); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($row['description']); ?>
                                <?php endif; ?>
                                <?php if (!empty($row['exchange_pair'])): ?>
                                    <div class="small text-muted mt-1">
                                        <?php echo htmlspecialchars($row['pair_label']); ?>: <strong><?php echo htmlspecialchars($row['exchange_pair']); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($row['stock_in']): ?>
                                    <?php if ($row['description'] === 'Returned Product'): ?>
                                        <span class="returned-product"><?php echo number_format($row['stock_in']); ?></span>
                                    <?php else: ?>
                                        <span class="stock-in"><?php echo number_format($row['stock_in']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $row['stock_out'] ? '<span class="stock-out">' . number_format($row['stock_out']) . '</span>' : ''; ?></td>
                            <td class="text-center balance"><?php echo number_format($row['balance']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </body>
    </html> 
    
