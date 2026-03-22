<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get inventory data
$inventory_query = "SELECT 
    p.product_id,
    p.item_name as product_name,
    p.oem_number as product_code,
    p.category as product_category,
    p.selling_price as unit_price,
    i.stock_level as stock_quantity,
    10 as reorder_level,
    50 as max_stock,
    i.last_updated,
    CASE 
        WHEN i.stock_level <= 10 THEN 'Critical'
        WHEN i.stock_level <= 15 THEN 'Low'
        ELSE 'Normal'
    END as stock_status
FROM tbl_masterlist p
LEFT JOIN tbl_inventory i ON p.product_id = i.product_id
ORDER BY i.stock_level ASC";

$inventory_result = $mysqli->query($inventory_query);

// Calculate statistics
$total_products = 0;
$critical_stock = 0;
$low_stock = 0;
$normal_stock = 0;
$total_value = 0;

$inventory_data = [];
while ($row = $inventory_result->fetch_assoc()) {
    $inventory_data[] = $row;
    $total_products++;
    $total_value += ($row['unit_price'] * $row['stock_quantity']);
    
    if ($row['stock_status'] == 'Critical') {
        $critical_stock++;
    } elseif ($row['stock_status'] == 'Low') {
        $low_stock++;
    } else {
        $normal_stock++;
    }
}

// Get category breakdown
$category_query = "SELECT 
    p.category as product_category,
    COUNT(*) as product_count,
    SUM(i.stock_level) as total_stock,
    SUM(p.selling_price * i.stock_level) as category_value
FROM tbl_masterlist p
LEFT JOIN tbl_inventory i ON p.product_id = i.product_id
GROUP BY p.category";

$category_result = $mysqli->query($category_query);
$category_data = [];
while ($row = $category_result->fetch_assoc()) {
    $category_data[] = $row;
}

// Get recent stock movements
$movement_query = "SELECT 
    st.transfer_id,
    st.transfer_date,
    'Main Shop' as from_location,
    st.location as to_location,
    st.quantity,
    p.item_name as product_name,
    'Transfer' as transfer_type
FROM tbl_stock_transfers st
JOIN tbl_masterlist p ON st.product_id = p.product_id
ORDER BY st.transfer_date DESC
LIMIT 10";

$movement_result = $mysqli->query($movement_query);
$movement_data = [];
while ($row = $movement_result->fetch_assoc()) {
    $movement_data[] = $row;
}

// Pagination logic for Inventory Status
$perPageOptions = [5, 10, 20, 50];
$perPage = isset($_GET['per_page']) && in_array(intval($_GET['per_page']), $perPageOptions) ? intval($_GET['per_page']) : 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$totalRows = count($inventory_data);
$totalPages = ceil($totalRows / $perPage);
$start = ($page - 1) * $perPage;
$paged_inventory_data = array_slice($inventory_data, $start, $perPage);

// Pagination group logic (show 5 page links at a time)
$pagesPerGroup = 5;
$currentGroup = ceil($page / $pagesPerGroup);
$startPage = ($currentGroup - 1) * $pagesPerGroup + 1;
$endPage = min($startPage + $pagesPerGroup - 1, $totalPages);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - DH AUTOCARE</title>
    
    <!-- Bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Icons -->
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">
    
    <!-- jQuery -->
    <script src="assets/jquery.min.js"></script>
    
    <!-- Chart.js -->
    <script src="js/chart.js"></script>
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">
    
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .metric-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .stock-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stock-status.critical {
            background-color: #dc3545;
            color: white;
        }
        
        .stock-status.low {
            background-color: #ffc107;
            color: #212529;
        }
        
        .stock-status.normal {
            background-color: #28a745;
            color: white;
        }
        
        .alert-card {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
 
    </style>
</head>
<body>
<?php $activePage = 'reports'; include 'navbar.php'; ?>

    <main class="main-content">
        <div class="main-container">
            <!-- Report Header -->
            <div class="report-header" style='margin-top: 1rem;'>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2"><i class="fas fa-boxes me-3"></i>Inventory Report</h1>
                        <p class="mb-0">Comprehensive analysis of stock levels, product performance, and inventory management</p>
                    </div>
                    <div class="text-end">
                        <a href="inventory_report_print.php" class="btn btn-light">
                            <i class="fas fa-print me-2"></i>Print Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $total_products; ?></div>
                        <div class="metric-label">Total Products</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($total_value, 2); ?></div>
                        <div class="metric-label">Total Inventory Value</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $critical_stock; ?></div>
                        <div class="metric-label">Critical Stock Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $low_stock; ?></div>
                        <div class="metric-label">Low Stock Items</div>
                    </div>
                </div>
            </div>

            <!-- Category Breakdown Chart -->
            <div class="row">
                <div class="col-md-12">
                    <div class="chart-container">
                        <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Category Breakdown</h5>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Critical Stock Alert -->
            <?php if ($critical_stock > 0): ?>
            <div class="alert alert-danger alert-card">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Critical Stock Alert</h6>
                <p class="mb-0">You have <?php echo $critical_stock; ?> products with critical stock levels that require immediate attention.</p>
            </div>
            <?php endif; ?>

            <!-- Inventory Table -->
            <div class="table-container" id="inventory-table">
                <h5 class="mb-3"><i class="fas fa-table me-2"></i>Inventory Status</h5>
                <form method="get" class="mb-2 d-flex align-items-center" action="#inventory-table">
                    <label class="me-2 mb-0">Rows per page:</label>
                    <select name="per_page" class="form-select form-select-sm w-auto me-2" onchange="this.form.submit()">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?php echo $option; ?>"<?php if ($perPage == $option) echo ' selected'; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="page" value="1">
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Stock Quantity</th>
                                <th>Reorder Level</th>
                                <th>Unit Price</th>
                                <th>Stock Value</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_inventory_data as $item): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo $item['product_code']; ?></span></td>
                                <td><strong><?php echo $item['product_name']; ?></strong></td>
                                <td><?php echo $item['product_category']; ?></td>
                                <td><span class="fw-bold"><?php echo $item['stock_quantity']; ?></span></td>
                                <td><?php echo $item['reorder_level']; ?></td>
                                <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>₱<?php echo number_format($item['unit_price'] * $item['stock_quantity'], 2); ?></td>
                                <td><span class="stock-status <?php echo strtolower($item['stock_status']); ?>"><?php echo $item['stock_status']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($item['last_updated'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Inventory pagination">
                    <ul class="pagination justify-content-center mt-3">
                        <?php if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $startPage - 1; ?>&per_page=<?php echo $perPage; ?>#inventory-table">&laquo;</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item<?php if ($i == $page) echo ' active'; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>#inventory-table"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($endPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $endPage + 1; ?>&per_page=<?php echo $perPage; ?>#inventory-table">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>

            <!-- Recent Stock Movements -->
            <?php if (!empty($movement_data)): ?>
            <div class="table-container">
                <h5 class="mb-3"><i class="fas fa-exchange-alt me-2"></i>Recent Stock Movements</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Transfer Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movement_data as $movement): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($movement['transfer_date'])); ?></td>
                                <td><strong><?php echo $movement['product_name']; ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $movement['transfer_type']; ?></span>
                                </td>
                                <td><?php echo $movement['from_location']; ?></td>
                                <td><?php echo $movement['to_location']; ?></td>
                                <td><span class="fw-bold"><?php echo $movement['quantity']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($category_data, 'product_category')); ?>,
                datasets: [{
                    label: 'Product Count',
                    data: <?php echo json_encode(array_column($category_data, 'product_count')); ?>,
                    backgroundColor: '#667eea',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            const dropdownParent = document.querySelector('.nav-item.dropdown');
            
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdownMenu.classList.toggle('show');
                dropdownParent.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!dropdownParent.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                    dropdownParent.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html> 