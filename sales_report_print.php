<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('m/d/Y', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('m/d/Y');
$search = isset($_GET['search']) ? $_GET['search'] : '';
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Convert dates to MySQL format and compute 5PM cutoff boundaries
$start_date_mysql = date('Y-m-d', strtotime($start_date));
$end_date_mysql = date('Y-m-d', strtotime($end_date));
$startBoundary = (new DateTime($start_date_mysql . ' 17:00:00'))->modify('-1 day')->format('Y-m-d H:i:s');
$endBoundary = (new DateTime($end_date_mysql . ' 17:00:00'))->format('Y-m-d H:i:s');

// Build the main WHERE clause using created_at and excluding returns (RET and return/refund types)
$where_conditions = [
    "pt.created_at BETWEEN '$startBoundary' AND '$endBoundary'",
    "(pt.transaction_type IS NULL OR pt.transaction_type NOT IN ('refund','return'))",
    "pt.invoice_no NOT LIKE 'RET%'"
];

if (!empty($search)) {
    $search_term = $mysqli->real_escape_string($search);
    $where_conditions[] = "(COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_mn, ' ', c.creditor_ln), 'Walk-in Customer') LIKE '%$search_term%' OR pt.invoice_no LIKE '%$search_term%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM purchase_transactions pt 
LEFT JOIN tbl_creditors c ON pt.creditor_id = c.creditor_id
WHERE $where_clause";
$count_result = $mysqli->query($count_query);
if (!$count_result) {
    die("Error in count query: " . $mysqli->error);
}
$totalRows = $count_result->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Calculate start offset
$start = ($page - 1) * $perPage;

// Get sales data with pagination
$sales_query = "SELECT 
    pt.transaction_id,
    pt.invoice_no as invoice_number,
    COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_mn, ' ', c.creditor_ln), 'Walk-in Customer') as customer_name,
    pt.created_at,
    pt.grand_total as total_amount,
    pt.payment_method,
    pt.transaction_type,
    pt.grand_total as subtotal,
    0 as discount_amount,
    pt.grand_total as grand_total,
    '' as remarks
FROM purchase_transactions pt 
LEFT JOIN tbl_creditors c ON pt.creditor_id = c.creditor_id
WHERE $where_clause
ORDER BY pt.created_at DESC, pt.transaction_id DESC
LIMIT $start, $perPage";

$sales_result = $mysqli->query($sales_query);
if (!$sales_result) {
    die("Error in sales query: " . $mysqli->error);
}

// Calculate summary statistics
$summary_query = "SELECT 
    COUNT(*) as total_transactions,
    SUM(pt.grand_total) as total_sales,
    SUM(CASE WHEN pt.payment_method = 'Cash' THEN pt.grand_total ELSE 0 END) as cash_sales,
    SUM(CASE WHEN pt.payment_method = 'Credit' THEN pt.grand_total ELSE 0 END) as credit_sales,
    SUM(CASE WHEN pt.payment_method != 'Cash' AND pt.payment_method != 'Credit' THEN pt.grand_total ELSE 0 END) as other_payments,
    AVG(pt.grand_total) as average_transaction,
    0 as total_discounts
FROM purchase_transactions pt 
LEFT JOIN tbl_creditors c ON pt.creditor_id = c.creditor_id
WHERE $where_clause";

$summary_result = $mysqli->query($summary_query);
if (!$summary_result) {
    die("Error in summary query: " . $mysqli->error);
}
$summary_data = $summary_result->fetch_assoc();

// Function to calculate business date based on 5 PM cutoff
function getBusinessDate($created_at) {
    $created_datetime = new DateTime($created_at);
    $cutoff_time = new DateTime($created_datetime->format('Y-m-d') . ' 17:00:00');
    
    // If transaction was after 5 PM, it belongs to the next business day
    if ($created_datetime > $cutoff_time) {
        return $created_datetime->modify('+1 day')->format('Y-m-d');
    }
    
    return $created_datetime->format('Y-m-d');
}

$sales_data = [];
while ($row = $sales_result->fetch_assoc()) {
    $sales_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report (Print)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: white;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .report-container {
            max-width: 1200px;
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
        .payment-method {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .payment-method.cash {
            background-color: #28a745;
            color: white;
        }
        .payment-method.credit {
            background-color: #ffc107;
            color: #212529;
        }
        .payment-method.card {
            background-color: #17a2b8;
            color: white;
        }
        .payment-method.gcash {
            background-color: #6f42c1;
            color: white;
        }
        .payment-method.maya {
            background-color: #fd7e14;
            color: white;
        }
        .payment-method.bank {
            background-color: #20c997;
            color: white;
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
            <button class="btn-back" onclick="window.location.href='sales_report.php'">
                <i class="fas fa-arrow-left"></i> Back to Report
            </button>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <div class="print-header">
            <div>
                <h1 class="mb-2">Sales Report</h1>
                <div class="mb-2">Period: <strong><?php echo $start_date; ?> to <?php echo $end_date; ?></strong></div>
                <div class="mb-2">Generated on: <strong><?php echo date('m/d/Y H:i:s'); ?></strong></div>
                <div class="mb-2"><small><i class="fas fa-info-circle me-1"></i>Business day cutoff: 5:00 PM. Transactions after 5 PM are counted in the next day's report.</small></div>
            </div>
            <div style="text-align: right;">
                <h3>DH AUTOCARE</h3>
                <small>Sales Management System</small>
            </div>
        </div>
        
        <div class="section-title">Summary</div>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($summary_data['total_transactions']); ?></div>
                <div class="metric-label">Total Transactions</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($summary_data['total_sales'], 2); ?></div>
                <div class="metric-label">Total Sales</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($summary_data['cash_sales'], 2); ?></div>
                <div class="metric-label">Cash Sales</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($summary_data['credit_sales'], 2); ?></div>
                <div class="metric-label">Credit Sales</div>
            </div>
        </div>
        
        <table class="summary-table mb-3">
            <tr><th>Other Payment Methods</th><td>₱<?php echo number_format($summary_data['other_payments'], 2); ?></td></tr>
            <tr><th>Average Transaction Value</th><td>₱<?php echo number_format($summary_data['average_transaction'], 2); ?></td></tr>
            <tr><th>Total Discounts Given</th><td>₱<?php echo number_format($summary_data['total_discounts'], 2); ?></td></tr>
        </table>

        <div class="section-title">Sales Transactions</div>
        <div class="table-container">
            <?php if (empty($sales_data)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <h4>No sales transactions found</h4>
                    <p>No transactions match the selected criteria.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Payment Method</th>
                            <th>Subtotal</th>
                            <th>Discount</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $detail_total = 0;
                        foreach ($sales_data as $transaction): 
                            $detail_total += $transaction['total_amount'];
                            $payment_class = strtolower($transaction['payment_method']);
                        ?>
                        <tr>
                            <td><?php echo date('m/d/Y', strtotime(getBusinessDate($transaction['created_at']))); ?></td>
                            <td><?php echo htmlspecialchars($transaction['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                            <td><span class="payment-method <?php echo $payment_class; ?>"><?php echo htmlspecialchars($transaction['payment_method']); ?></span></td>
                            <td>₱<?php echo number_format($transaction['subtotal'], 2); ?></td>
                            <td>₱<?php echo number_format($transaction['discount_amount'], 2); ?></td>
                            <td>₱<?php echo number_format($transaction['total_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight:bold;background:#f8f9fa;">
                            <td colspan="6" class="text-end">Total Sales</td>
                            <td style="text-align:right;">₱<?php echo number_format($detail_total, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="section-title">Pagination Information</div>
        <div style="text-align: center; color: #6c757d; margin-bottom: 20px;">
            Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> 
            (<?php echo $start + 1; ?>-<?php echo min($start + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> transactions)
        </div>
        <?php endif; ?>

        <div style="text-align:center; color:#888; font-size:12px; margin-top: 32px;">
            Generated by Double Happiness Sales Management System
        </div>
    </div>
</body>
</html> 