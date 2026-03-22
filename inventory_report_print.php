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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Report (Print)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="js/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: white;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .report-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        h1, h2, h3 {
            margin: 0 0 8px 0;
            font-weight: 700;
        }
        .section-title {
            margin-top: 32px;
            margin-bottom: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 2px solid #eee;
            padding-bottom: 4px;
        }
        .summary-table td, .summary-table th {
            padding: 6px 12px;
            font-size: 15px;
        }
        .summary-table th {
            background: #f0f0f0;
        }
        .chart-section {
            text-align: center;
            margin-bottom: 24px;
        }
        .chart-section canvas {
            max-width: 300px;
            max-height: 200px;
            margin: 0 auto;
        }
        .table-container {
            margin-bottom: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th, td {
            border: 1px solid #888;
            padding: 6px 8px;
            font-size: 13px;
        }
        th {
            background: #f0f0f0;
        }
        .stock-status {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
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
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        .metric-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 4px;
        }
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .print-actions {
            margin-bottom: 20px;
            text-align: right;
        }
        .btn-print {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-print:hover {
            background: #5a6fd8;
        }
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-back:hover {
            background: #5a6268;
        }
        @media print {
            body, html {
                background: white !important;
                color: #222 !important;
            }
            .print-actions {
                display: none !important;
            }
            .report-container {
                box-shadow: none !important;
                background: white !important;
                margin: 0 !important;
                padding: 0.5cm !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .chart-section canvas {
                max-width: 250px !important;
                max-height: 150px !important;
            }
            @page {
                size: A4;
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="print-actions">
            <button class="btn-back" onclick="window.location.href='inventory_report.php'">
                <i class="fas fa-arrow-left"></i> Back to Report
            </button>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <div class="print-header">
            <div>
                <h1 class="mb-2">Inventory Report</h1>
                <div class="mb-2">Generated on: <strong><?php echo date('m/d/Y H:i:s'); ?></strong></div>
            </div>
            <div style="text-align: right;">
                <h3>DH AUTOCARE</h3>
                <small>Inventory Management System</small>
            </div>
        </div>
        
        <div class="section-title">Summary</div>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value"><?php echo $total_products; ?></div>
                <div class="metric-label">Total Products</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($total_value, 2); ?></div>
                <div class="metric-label">Total Inventory Value</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $critical_stock; ?></div>
                <div class="metric-label">Critical Stock Items</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $low_stock; ?></div>
                <div class="metric-label">Low Stock Items</div>
            </div>
        </div>
        
        <table class="summary-table mb-3">
            <tr><th>Normal Stock Items</th><td><?php echo $normal_stock; ?></td></tr>
            <tr><th>Average Stock Level</th><td><?php echo $total_products > 0 ? round(array_sum(array_column($inventory_data, 'stock_quantity')) / $total_products, 2) : 0; ?></td></tr>
            <tr><th>Average Unit Price</th><td>₱<?php echo $total_products > 0 ? number_format($total_value / array_sum(array_column($inventory_data, 'stock_quantity')), 2) : '0.00'; ?></td></tr>
        </table>

        <div class="section-title">Category Breakdown</div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Product Count</th>
                        <th>Total Stock</th>
                        <th>Category Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['product_category']); ?></td>
                            <td><?php echo $row['product_count']; ?></td>
                            <td><?php echo $row['total_stock']; ?></td>
                            <td>₱<?php echo number_format($row['category_value'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section-title">Inventory Status</div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Stock Quantity</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $detail_total_value = 0;
                    foreach ($inventory_data as $item): 
                        $item_value = $item['unit_price'] * $item['stock_quantity'];
                        $detail_total_value += $item_value;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['product_category']); ?></td>
                        <td><?php echo $item['stock_quantity']; ?></td>
                        <td><?php echo date('m/d/Y', strtotime($item['last_updated'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight:bold;background:#f8f9fa;">
                        <td colspan="4" class="text-end">Total Inventory Value</td>
                        <td style="text-align:right;">₱<?php echo number_format($detail_total_value, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (!empty($movement_data)): ?>
        <div class="section-title">Recent Stock Movements</div>
        <div class="table-container">
            <table>
                <thead>
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
                        <td><?php echo date('m/d/Y', strtotime($movement['transfer_date'])); ?></td>
                        <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($movement['transfer_type']); ?></td>
                        <td><?php echo htmlspecialchars($movement['from_location']); ?></td>
                        <td><?php echo htmlspecialchars($movement['to_location']); ?></td>
                        <td><?php echo $movement['quantity']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="text-align:center; color:#888; font-size:12px; margin-top: 32px;">
            Generated by Double Happiness Inventory System
        </div>
    </div>

    <script>
    window.onload = function() {
        // Stock Status Chart
        var stockCtx = document.getElementById('stockChart').getContext('2d');
        new Chart(stockCtx, {
            type: 'doughnut',
            data: {
                labels: ['Normal', 'Low', 'Critical'],
                datasets: [{
                    data: [<?php echo $normal_stock; ?>, <?php echo $low_stock; ?>, <?php echo $critical_stock; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    };
    </script>
</body>
</html> 