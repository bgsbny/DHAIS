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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit & Collection Report - DH AUTOCARE</title>
    
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
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .metric-value.credit {
            color: #dc3545;
        }
        
        .metric-value.payment {
            color: #28a745;
        }
        
        .metric-value.outstanding {
            color: #ffc107;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
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
            <div class="report-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2"><i class="fas fa-credit-card me-3"></i>Credit & Collection Report</h1>
                        <p class="mb-0">Comprehensive analysis of credit transactions, outstanding balances, and collection performance</p>
                    </div>
                    <div class="text-end">
                        <a href="credit_report_print.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-light">
                            <i class="fas fa-print me-2"></i>Print Report
                        </a>
                    </div>
                </div>
            </div>

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
                        <div class="metric-value credit">₱<?php echo number_format($total_with_interest, 2); ?></div>
                        <div class="metric-label">Total Credit Extended</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value payment">₱<?php echo number_format($total_paid, 2); ?></div>
                        <div class="metric-label">Total Payments Received</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value outstanding">₱<?php echo number_format($total_outstanding, 2); ?></div>
                        <div class="metric-label">Outstanding Balance</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $overdue_credits; ?></div>
                        <div class="metric-label">Overdue Credits</div>
                    </div>
                </div>
            </div>

            <!-- Overdue Alert -->
            <?php if ($overdue_credits > 0): ?>
            <div class="alert alert-danger alert-card">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Overdue Credits Alert</h6>
                <p class="mb-0">You have <?php echo $overdue_credits; ?> overdue credit transactions that require immediate attention.</p>
            </div>
            <?php endif; ?>





            <!-- Top Debtors -->
            <?php if (!empty($top_debtors)): ?>
            <div class="table-container">
                <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Top Debtors by Outstanding Balance</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Rank</th>
                                <th>Customer Name</th>
                                <th>Credit Count</th>
                                <th>Total Credit</th>
                                <th>Total Paid</th>
                                <th>Outstanding Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_debtors as $index => $debtor): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $index < 3 ? 'danger' : 'warning'; ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $debtor['customer_name']; ?></strong></td>
                                <td><span class="badge bg-info"><?php echo $debtor['credit_count']; ?></span></td>
                                <td>₱<?php echo number_format($debtor['total_credit'], 2); ?></td>
                                <td>₱<?php echo number_format($debtor['total_paid'], 2); ?></td>
                                <td><strong class="text-danger">₱<?php echo number_format($debtor['outstanding_balance'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Outstanding Balances -->
            <?php if (!empty($outstanding_data)): ?>
            <div class="table-container">
                <h5 class="mb-3"><i class="fas fa-clock me-2"></i>Outstanding Credit Balances</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Credit ID</th>
                                <th>Customer Name</th>
                                <th>Contact Number</th>
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
                            <?php 
                            $due_date = strtotime($outstanding['due_date']);
                            $today = time();
                            $days_overdue = floor(($today - $due_date) / (60 * 60 * 24));
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo $outstanding['credit_id']; ?></span></td>
                                <td><strong><?php echo $outstanding['customer_name']; ?></strong></td>
                                <td><?php echo $outstanding['creditor_contactNo']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($outstanding['credit_date'])); ?></td>
                                <td>
                                    <?php if ($days_overdue > 0): ?>
                                        <span class="badge bg-danger"><?php echo date('M d, Y', $due_date); ?> (<?php echo $days_overdue; ?> days overdue)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><?php echo date('M d, Y', $due_date); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?php echo number_format($outstanding['total_with_interest'], 2); ?></td>
                                <td>₱<?php echo number_format($outstanding['total_paid'], 2); ?></td>
                                <td><strong class="text-danger">₱<?php echo number_format($outstanding['outstanding_amount'], 2); ?></strong></td>
                                <td>
                                    <span class="badge bg-warning"><?php echo $outstanding['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Payments -->
            <?php if (!empty($payment_data)): ?>
            <div class="table-container">
                <h5 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i>Recent Payments</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Payment ID</th>
                                <th>Payment Date</th>
                                <th>Customer Name</th>
                                <th>Credit ID</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_data as $payment): ?>
                            <tr>
                                <td><span class="badge bg-success"><?php echo $payment['payment_id']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><strong><?php echo $payment['customer_name']; ?></strong></td>
                                <td><span class="badge bg-secondary"><?php echo $payment['credit_id']; ?></span></td>
                                <td><strong class="text-success">₱<?php echo number_format($payment['amount_paid'], 2); ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $payment['payment_method']; ?></span>
                                </td>
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