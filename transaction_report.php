<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get transaction data
$transaction_query = "SELECT 
    pt.transaction_id,
    pt.transaction_date,
    pt.invoice_no,
    pt.payment_method,
    pt.customer_type,
    pt.subtotal,
    pt.discount_percentage,
    pt.grand_total,
    c.creditor_fn,
    c.creditor_mn,
    c.creditor_ln,
    c.org_name,
    COUNT(ptd.purchased_item_id) as items_count
FROM purchase_transactions pt
LEFT JOIN tbl_creditors c ON pt.creditor_id = c.creditor_id
LEFT JOIN purchase_transaction_details ptd ON pt.transaction_id = ptd.transaction_id
WHERE pt.transaction_date BETWEEN ? AND ?
GROUP BY pt.transaction_id
ORDER BY pt.transaction_date DESC";

$stmt = $mysqli->prepare($transaction_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$transaction_result = $stmt->get_result();

// Calculate statistics
$total_transactions = 0;
$total_revenue = 0;
$total_discount = 0;
$avg_transaction_value = 0;
$cash_transactions = 0;
$credit_transactions = 0;
$walk_in_transactions = 0;
$creditor_transactions = 0;

$transaction_data = [];
while ($row = $transaction_result->fetch_assoc()) {
    $transaction_data[] = $row;
    $total_transactions++;
    $total_revenue += $row['grand_total'];
    $total_discount += ($row['subtotal'] * $row['discount_percentage'] / 100);

    if ($row['payment_method'] == 'Cash') {
        $cash_transactions++;
    } else {
        $credit_transactions++;
    }

    if ($row['customer_type'] == 'Walk-in') {
        $walk_in_transactions++;
    } else {
        $creditor_transactions++;
    }
}

$avg_transaction_value = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;

// Get transaction trends by day
$trend_query = "SELECT 
    DATE(transaction_date) as date,
    COUNT(*) as transaction_count,
    SUM(grand_total) as daily_revenue,
    AVG(grand_total) as avg_transaction
FROM purchase_transactions 
WHERE transaction_date BETWEEN ? AND ?
GROUP BY DATE(transaction_date)
ORDER BY date";

$stmt = $mysqli->prepare($trend_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$trend_result = $stmt->get_result();

$trend_data = [];
while ($row = $trend_result->fetch_assoc()) {
    $trend_data[] = $row;
}

// Get top products sold
$top_products_query = "SELECT 
    p.item_name,
    p.oem_number,
    SUM(ptd.purchased_quantity) as total_quantity,
    SUM(ptd.product_subtotal) as total_revenue,
    COUNT(DISTINCT pt.transaction_id) as transaction_count
FROM purchase_transaction_details ptd
JOIN purchase_transactions pt ON ptd.transaction_id = pt.transaction_id
JOIN tbl_masterlist p ON ptd.product_id = p.product_id
WHERE pt.transaction_date BETWEEN ? AND ?
GROUP BY p.product_id
ORDER BY total_quantity DESC
LIMIT 10";

$stmt = $mysqli->prepare($top_products_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_products_result = $stmt->get_result();

$top_products = [];
while ($row = $top_products_result->fetch_assoc()) {
    $top_products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Report - DH AUTOCARE</title>

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

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <?php $activePage = 'reports'; include 'navbar.php'; ?>

    <main class="main-content text-start">
        
        <div class="main-container">
            <header class="page-header">
                <div class="header-content">
                    <div class="header-left" style="padding-top: 2rem; padding-left: 2rem;">
                        <h1 class="page-title">Transaction Report</h1>
                        <p class="page-subtitle">Generate and view detailed transaction reports.</p>
                    </div>
                </div>
            </header>

            <!-- Date Filter -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $total_transactions; ?></div>
                        <div class="metric-label">Total Transactions</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($total_revenue, 2); ?></div>
                        <div class="metric-label">Total Revenue</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($avg_transaction_value, 2); ?></div>
                        <div class="metric-label">Average Transaction</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($total_discount, 2); ?></div>
                        <div class="metric-label">Total Discounts</div>
                    </div>
                </div>
            </div>

            <!-- Transaction Trends -->
            <div class="chart-container">
                <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Transaction Trends</h5>
                <canvas id="trendChart" height="100"></canvas>
            </div>

            <!-- Payment and Customer Breakdown -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Payment Method Distribution</h5>
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Customer Type Distribution</h5>
                        <canvas id="customerChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <?php if (!empty($top_products)): ?>
                <div class="table-container">
                    <h5 class="mb-3"><i class="fas fa-star me-2"></i>Top Selling Products</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Rank</th>
                                    <th>OEM Number</th>
                                    <th>Item Name</th>
                                    <th>Quantity Sold</th>
                                    <th>Revenue Generated</th>
                                    <th>Transaction Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $index => $product): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $product['oem_number']; ?></span></td>
                                        <td><strong><?php echo $product['item_name']; ?></strong></td>
                                        <td><span class="fw-bold"><?php echo $product['total_quantity']; ?></span></td>
                                        <td><strong>₱<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                        <td><?php echo $product['transaction_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Transactions -->
            <div class="table-container">
                <h5 class="mb-3"><i class="fas fa-table me-2"></i>Detailed Transaction List</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>Customer</th>
                                <th>Payment Method</th>
                                <th>Customer Type</th>
                                <th>Items</th>
                                <th>Subtotal</th>
                                <th>Discount</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaction_data as $transaction): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $transaction['invoice_no']; ?></span></td>
                                    <td>
                                        <?php
                                        if ($transaction['customer_type'] == 'Walk-in') {
                                            echo '<span class="text-muted">Walk-in Customer</span>';
                                        } else {
                                            $customer_name = '';
                                            if (!empty($transaction['org_name'])) {
                                                $customer_name = $transaction['org_name'];
                                            } else {
                                                $customer_name = trim($transaction['creditor_fn'] . ' ' . $transaction['creditor_mn'] . ' ' . $transaction['creditor_ln']);
                                            }
                                            echo $customer_name;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php echo $transaction['payment_method'] == 'Cash' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $transaction['payment_method']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php echo $transaction['customer_type'] == 'Walk-in' ? 'bg-info' : 'bg-secondary'; ?>">
                                            <?php echo $transaction['customer_type']; ?>
                                        </span>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?php echo $transaction['items_count']; ?>
                                            items</span></td>
                                    <td>₱<?php echo number_format($transaction['subtotal'], 2); ?></td>
                                    <td>
                                        <?php if ($transaction['discount_percentage'] > 0): ?>
                                            <span
                                                class="text-success">-<?php echo $transaction['discount_percentage']; ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">0%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>₱<?php echo number_format($transaction['grand_total'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Transaction Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trend_data, 'date')); ?>,
                datasets: [{
                    label: 'Daily Transactions',
                    data: <?php echo json_encode(array_column($trend_data, 'transaction_count')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: 'Daily Revenue (₱)',
                    data: <?php echo json_encode(array_column($trend_data, 'daily_revenue')); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Transaction Count'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function (value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Method Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'Credit'],
                datasets: [{
                    data: [<?php echo $cash_transactions; ?>, <?php echo $credit_transactions; ?>],
                    backgroundColor: ['#28a745', '#ffc107'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Customer Type Chart
        const customerCtx = document.getElementById('customerChart').getContext('2d');
        const customerChart = new Chart(customerCtx, {
            type: 'doughnut',
            data: {
                labels: ['Walk-in', 'Creditor'],
                datasets: [{
                    data: [<?php echo $walk_in_transactions; ?>, <?php echo $creditor_transactions; ?>],
                    backgroundColor: ['#17a2b8', '#6c757d'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function () {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            const dropdownParent = document.querySelector('.nav-item.dropdown');

            dropdownToggle.addEventListener('click', function (e) {
                e.preventDefault();
                dropdownMenu.classList.toggle('show');
                dropdownParent.classList.toggle('active');
            });

            document.addEventListener('click', function (e) {
                if (!dropdownParent.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                    dropdownParent.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>