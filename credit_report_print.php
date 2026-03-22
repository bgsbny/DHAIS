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

// Get credit transactions
$credit_query = "SELECT 
    ct.credit_id,
    pt.transaction_date as credit_date,
    pt.grand_total as total_amount,
    ct.total_with_interest,
    ct.status,
    ct.due_date,
    c.creditor_id,
    COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_mn, ' ', c.creditor_ln)) as customer_name,
    c.creditor_contactNo,
    c.creditor_email
FROM tbl_credit_transactions ct
JOIN purchase_transactions pt ON ct.transaction_id = pt.transaction_id
JOIN tbl_creditors c ON ct.creditor_id = c.creditor_id
WHERE pt.transaction_date BETWEEN ? AND ?
ORDER BY pt.transaction_date DESC";

$stmt = $mysqli->prepare($credit_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$credit_result = $stmt->get_result();

// Calculate statistics
$total_credits = 0;
$total_amount = 0;
$total_with_interest = 0;
$pending_credits = 0;
$completed_credits = 0;
$overdue_credits = 0;

$credit_data = [];
while ($row = $credit_result->fetch_assoc()) {
    $credit_data[] = $row;
    $total_credits++;
    $total_amount += $row['total_amount'];
    $total_with_interest += $row['total_with_interest'];
    
    if ($row['status'] == 'Pending') {
        $pending_credits++;
        // Check if overdue
        $due_date = strtotime($row['due_date']);
        $today = time();
        if ($today > $due_date) {
            $overdue_credits++;
        }
    } else {
        $completed_credits++;
    }
}

// Get payment data
$payment_query = "SELECT 
    cp.payment_id,
    cp.date_paid as payment_date,
    cp.amount_paid,
    cp.payment_type as payment_method,
    ct.credit_id,
    ct.total_with_interest,
    c.creditor_id,
    COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_mn, ' ', c.creditor_ln)) as customer_name
FROM tbl_credit_payments cp
JOIN tbl_credit_transactions ct ON cp.credit_id = ct.credit_id
JOIN tbl_creditors c ON ct.creditor_id = c.creditor_id
WHERE cp.date_paid BETWEEN ? AND ?
ORDER BY cp.date_paid DESC";

$stmt = $mysqli->prepare($payment_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$payment_result = $stmt->get_result();

$total_payments = 0;
$total_paid = 0;
$payment_data = [];
while ($row = $payment_result->fetch_assoc()) {
    $payment_data[] = $row;
    $total_payments++;
    $total_paid += $row['amount_paid'];
}

// Get outstanding balances
$outstanding_query = "SELECT 
    ct.credit_id,
    pt.transaction_date as credit_date,
    ct.total_with_interest,
    ct.due_date,
    ct.status,
    c.creditor_id,
    COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_mn, ' ', c.creditor_ln)) as customer_name,
    c.creditor_contactNo,
    (SELECT COALESCE(SUM(amount_paid), 0) FROM tbl_credit_payments cp WHERE cp.credit_id = ct.credit_id) as total_paid
FROM tbl_credit_transactions ct
JOIN purchase_transactions pt ON ct.transaction_id = pt.transaction_id
JOIN tbl_creditors c ON ct.creditor_id = c.creditor_id
WHERE ct.status = 'Pending'
ORDER BY ct.due_date ASC";

$outstanding_result = $mysqli->query($outstanding_query);
$outstanding_data = [];
$total_outstanding = 0;
while ($row = $outstanding_result->fetch_assoc()) {
    $row['outstanding_amount'] = $row['total_with_interest'] - $row['total_paid'];
    $total_outstanding += $row['outstanding_amount'];
    $outstanding_data[] = $row;
}

// Get monthly credit trends
$monthly_trend_query = "SELECT 
    DATE_FORMAT(pt.transaction_date, '%Y-%m') as month,
    COUNT(*) as credit_count,
    SUM(pt.grand_total) as total_credit,
    SUM(ct.total_with_interest) as total_with_interest
FROM tbl_credit_transactions ct
JOIN purchase_transactions pt ON ct.transaction_id = pt.transaction_id
WHERE pt.transaction_date BETWEEN ? AND ?
GROUP BY DATE_FORMAT(pt.transaction_date, '%Y-%m')
ORDER BY month";

$stmt = $mysqli->prepare($monthly_trend_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$monthly_trend_result = $stmt->get_result();

$monthly_trends = [];
while ($row = $monthly_trend_result->fetch_assoc()) {
    $monthly_trends[] = $row;
}

// Get top debtors
$top_debtors_query = "SELECT 
    c.creditor_id,
    COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_mn, ' ', c.creditor_ln)) as customer_name,
    COUNT(ct.credit_id) as credit_count,
    SUM(ct.total_with_interest) as total_credit,
    SUM(COALESCE((SELECT SUM(amount_paid) FROM tbl_credit_payments cp WHERE cp.credit_id = ct.credit_id), 0)) as total_paid,
    SUM(ct.total_with_interest - COALESCE((SELECT SUM(amount_paid) FROM tbl_credit_payments cp WHERE cp.credit_id = ct.credit_id), 0)) as outstanding_balance
FROM tbl_creditors c
JOIN tbl_credit_transactions ct ON c.creditor_id = ct.creditor_id
WHERE ct.status = 'Pending'
GROUP BY c.creditor_id
ORDER BY outstanding_balance DESC
LIMIT 10";

$top_debtors_result = $mysqli->query($top_debtors_query);
$top_debtors = [];
while ($row = $top_debtors_result->fetch_assoc()) {
    $top_debtors[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Credit Report (Print)</title>
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
        .status-badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-badge.pending {
            background-color: #ffc107;
            color: #212529;
        }
        .status-badge.completed {
            background-color: #28a745;
            color: white;
        }
        .status-badge.overdue {
            background-color: #dc3545;
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
            <button class="btn-back" onclick="window.location.href='credit_report.php'">
                <i class="fas fa-arrow-left"></i> Back to Report
            </button>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <div class="print-header">
            <div>
                <h1 class="mb-2">Credit & Collection Report</h1>
                <div class="mb-2">Period: <strong><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></strong></div>
                <div class="mb-2">Generated on: <strong><?php echo date('m/d/Y H:i:s'); ?></strong></div>
            </div>
            <div style="text-align: right;">
                <h3>DH AUTOCARE</h3>
                <small>Credit Management System</small>
            </div>
        </div>
        
        <div class="section-title">Summary</div>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($total_credits); ?></div>
                <div class="metric-label">Total Credits</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($total_with_interest, 2); ?></div>
                <div class="metric-label">Total Credit Amount</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($total_paid, 2); ?></div>
                <div class="metric-label">Total Payments</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">₱<?php echo number_format($total_outstanding, 2); ?></div>
                <div class="metric-label">Outstanding Balance</div>
            </div>
        </div>
        
        <table class="summary-table mb-3">
            <tr><th>Pending Credits</th><td><?php echo $pending_credits; ?></td></tr>
            <tr><th>Completed Credits</th><td><?php echo $completed_credits; ?></td></tr>
            <tr><th>Overdue Credits</th><td><?php echo $overdue_credits; ?></td></tr>
            <tr><th>Total Payments Made</th><td><?php echo $total_payments; ?></td></tr>
            <tr><th>Collection Rate</th><td><?php echo $total_with_interest > 0 ? round(($total_paid / $total_with_interest) * 100, 2) : 0; ?>%</td></tr>
        </table>



        <div class="section-title">Outstanding Balances</div>
        <div class="table-container">
            <?php if (empty($outstanding_data)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <h4>No outstanding balances</h4>
                    <p>All credits have been fully paid.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Credit Date</th>
                            <th>Due Date</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outstanding_data as $outstanding): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($outstanding['customer_name']); ?></td>
                            <td><?php echo date('m/d/Y', strtotime($outstanding['credit_date'])); ?></td>
                            <td><?php echo date('m/d/Y', strtotime($outstanding['due_date'])); ?></td>
                            <td>₱<?php echo number_format($outstanding['total_with_interest'], 2); ?></td>
                            <td>₱<?php echo number_format($outstanding['total_paid'], 2); ?></td>
                            <td>₱<?php echo number_format($outstanding['outstanding_amount'], 2); ?></td>
                            <td>
                                <?php 
                                $due_date = strtotime($outstanding['due_date']);
                                $today = time();
                                $status_class = $today > $due_date ? 'overdue' : 'pending';
                                $status_text = $today > $due_date ? 'Overdue' : 'Pending';
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight:bold;background:#f8f9fa;">
                            <td colspan="5" class="text-end">Total Outstanding</td>
                            <td style="text-align:right;">₱<?php echo number_format($total_outstanding, 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="section-title">Recent Credit Transactions</div>
        <div class="table-container">
            <?php if (empty($credit_data)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <h4>No credit transactions found</h4>
                    <p>No credit transactions in the selected period.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Original Amount</th>
                            <th>With Interest</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credit_data as $credit): ?>
                        <tr>
                            <td><?php echo date('m/d/Y', strtotime($credit['credit_date'])); ?></td>
                            <td><?php echo htmlspecialchars($credit['customer_name']); ?></td>
                            <td>₱<?php echo number_format($credit['total_amount'], 2); ?></td>
                            <td>₱<?php echo number_format($credit['total_with_interest'], 2); ?></td>
                            <td><?php echo date('m/d/Y', strtotime($credit['due_date'])); ?></td>
                            <td>
                                <?php 
                                $status_class = strtolower($credit['status']);
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($credit['status']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($payment_data)): ?>
        <div class="section-title">Recent Payments</div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Customer</th>
                        <th>Amount Paid</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_data as $payment): ?>
                    <tr>
                        <td><?php echo date('m/d/Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                        <td>₱<?php echo number_format($payment['amount_paid'], 2); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="text-align:center; color:#888; font-size:12px; margin-top: 32px;">
            Generated by Double Happiness Credit Management System
        </div>
    </div>


</body>
</html> 