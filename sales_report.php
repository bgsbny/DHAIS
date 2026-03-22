<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get date range parameters
$start_date_input = isset($_GET['start_date']) ? $_GET['start_date'] : date('m/d/Y');
$end_date_input = isset($_GET['end_date']) ? $_GET['end_date'] : date('m/d/Y');

// Validation variables
$errors = [];
$current_date = new DateTime();
$form_submitted = isset($_GET['start_date']) || isset($_GET['end_date']);

// Only validate if form was submitted
if ($form_submitted) {
    // Validate start date
    if (!empty($start_date_input)) {
        $start_date_obj = DateTime::createFromFormat('m/d/Y', $start_date_input);
        if ($start_date_obj === false) {
            $errors[] = "Invalid start date format. Please use mm/dd/yyyy format.";
        } else {
            if ($start_date_obj > $current_date) {
                $errors[] = "Start date cannot be beyond the current day.";
            }
        }
    }

    // Validate end date
    if (!empty($end_date_input)) {
        $end_date_obj = DateTime::createFromFormat('m/d/Y', $end_date_input);
        if ($end_date_obj === false) {
            $errors[] = "Invalid end date format. Please use mm/dd/yyyy format.";
        } else {
            if ($end_date_obj > $current_date) {
                $errors[] = "End date cannot be beyond the current day.";
            }
            
            // Check if end date is less than start date
            if (!empty($start_date_input) && $start_date_obj && $end_date_obj < $start_date_obj) {
                $errors[] = "End date cannot be earlier than start date.";
            }
        }
    }
}

// Convert m/d/Y format to Y-m-d format for database queries (only if no errors)
if (empty($errors)) {
    $start_date = DateTime::createFromFormat('m/d/Y', $start_date_input) ? DateTime::createFromFormat('m/d/Y', $start_date_input)->format('Y-m-d') : date('Y-m-d');
    $end_date = DateTime::createFromFormat('m/d/Y', $end_date_input) ? DateTime::createFromFormat('m/d/Y', $end_date_input)->format('Y-m-d') : date('Y-m-d');
} else {
    // Use current date for both if there are validation errors
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
}

// Apply 5 PM cutoff using created_at: business day is from (start_date - 1) 17:00:00 to end_date 17:00:00
$startBoundary = (new DateTime($start_date . ' 17:00:00'))->modify('-1 day')->format('Y-m-d H:i:s');
$endBoundary = (new DateTime($end_date . ' 17:00:00'))->format('Y-m-d H:i:s');

// Get sales data from database
// $sales_query = "SELECT 
//     pt.transaction_id,
//     pt.transaction_date,
//     pt.invoice_no,
//     pt.grand_total,
//     pt.payment_method,
//     pt.customer_type,
//     c.creditor_fn,
//     c.creditor_mn,
//     c.creditor_ln,
//     c.org_name
// FROM purchase_transactions pt
// LEFT JOIN tbl_creditors c ON pt.creditor_id = c.creditor_id
// WHERE pt.transaction_date BETWEEN ? AND ?
// ORDER BY pt.transaction_date DESC";

$sales_query = "SELECT 
    pt.transaction_id,
    pt.created_at,
    pt.invoice_no,
    pt.transaction_type,
    pt.reference_transaction_id,
    pt.grand_total,
    pt.payment_method,
    pt.customer_type,
    c.creditor_fn,
    c.creditor_mn,
    c.creditor_ln,
    c.org_name
FROM purchase_transactions pt
LEFT JOIN tbl_creditors c ON pt.creditor_id = c.creditor_id
WHERE pt.created_at BETWEEN ? AND ?
  AND (pt.transaction_type IS NULL OR pt.transaction_type NOT IN ('refund','return'))
  AND pt.invoice_no NOT LIKE 'RET%'
ORDER BY pt.created_at DESC
";


$stmt = $mysqli->prepare($sales_query);
$stmt->bind_param("ss", $startBoundary, $endBoundary);
$stmt->execute();
$sales_result = $stmt->get_result();

// First, get all refund transactions to identify which original transactions should be excluded
$refunded_transaction_ids = [];
$refund_query = "SELECT reference_transaction_id FROM purchase_transactions 
                 WHERE transaction_type IN ('refund', 'return') 
                 AND reference_transaction_id IS NOT NULL 
                 AND created_at BETWEEN ? AND ?";
$refund_stmt = $mysqli->prepare($refund_query);
$refund_stmt->bind_param("ss", $startBoundary, $endBoundary);
$refund_stmt->execute();
$refund_result = $refund_stmt->get_result();

while ($refund_row = $refund_result->fetch_assoc()) {
    $refunded_transaction_ids[] = $refund_row['reference_transaction_id'];
}

// Calculate totals
$total_sales = 0;
$cash_sales = 0;
$credit_sales = 0;
$walk_in_sales = 0;
$creditor_sales = 0;

$sales_data = [];
while ($row = $sales_result->fetch_assoc()) {
    $sales_data[] = $row;
    
    // Skip original transactions that have been refunded/returned
    if (in_array($row['transaction_id'], $refunded_transaction_ids)) {
        continue; // Skip this transaction entirely
    }
    
    // Calculate total sales based on transaction type
    if ($row['transaction_type'] === 'sale' || empty($row['transaction_type'])) {
        $total_sales += $row['grand_total'];
    } 
    elseif ($row['transaction_type'] === 'exchange') {
        // Exchange grand_total is the delta (e.g., 5 additional). Include it.
        $total_sales += $row['grand_total'];
    }

    // Calculate cash/credit sales based on transaction type
    if ($row['transaction_type'] === 'sale' || empty($row['transaction_type'])) {
        if ($row['payment_method'] == 'Cash') {
            $cash_sales += $row['grand_total'];
        } else {
            $credit_sales += $row['grand_total'];
        }
    } 
    elseif ($row['transaction_type'] === 'exchange') {
        // Treat exchanges as cash unless you have specific method stored
        if ($row['payment_method'] == 'Cash') {
            $cash_sales += $row['grand_total'];
        } else {
            $credit_sales += $row['grand_total'];
        }
    }

    if ($row['customer_type'] == 'Walk-in') {
        $walk_in_sales += $row['grand_total'];
    } else {
        $creditor_sales += $row['grand_total'];
    }
}

// Pagination logic
$perPage = isset($_GET['per_page']) ? max(10, intval($_GET['per_page'])) : 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter sales_data by search
if ($search !== '') {
    $sales_data = array_filter($sales_data, function($row) use ($search) {
        $customer = ($row['customer_type'] == 'Walk-in') ? 'Walk-in Customer' : (empty($row['org_name']) ? trim($row['creditor_fn'] . ' ' . $row['creditor_mn'] . ' ' . $row['creditor_ln']) : $row['org_name']);
        return stripos($customer, $search) !== false || stripos($row['invoice_no'], $search) !== false;
    });
}

$totalRows = count($sales_data);
$totalPages = ceil($totalRows / $perPage);
$start = ($page - 1) * $perPage;
$paged_sales_data = array_slice($sales_data, $start, $perPage);

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - DH AUTOCARE</title>

    <!-- Bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <!-- Icons -->
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">

    <!-- jQuery -->
    <script src="assets/jquery.min.js"></script>

    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">
    <link rel="stylesheet" href="css/style.css">

    <!-- Bootstrap Datepicker -->
    <link rel="stylesheet" href="assets/bootstrap-datepicker.min.css">
    <script src="assets/bootstrap-datepicker.min.js"></script>

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

        .search-section {
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
        
        .is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .date-error {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .floating-back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }
        
        .floating-back-btn .btn {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
            border: none;
        }
        
        .floating-back-btn .btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3) !important;
        }
        
        .floating-back-btn .btn:active {
            transform: scale(0.95);
        }
        
        @media (max-width: 768px) {
            .floating-back-btn {
                bottom: 20px;
                right: 20px;
            }
            
            .floating-back-btn .btn {
                width: 50px;
                height: 50px;
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
<?php $activePage = 'reports'; include 'navbar.php'; ?>

    <main class="main-content text-start">
        <div class="main-container text-start">
            
            <!-- Report Header -->
            <div class="report-header" style='margin-top: 1rem;'>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2"><i class="fas fa-dollar-sign me-3"></i>Sales Report</h1>
                        <p class="mb-0">Sales transactions from <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        <small class="text-light opacity-75"><i class="fas fa-info-circle me-1"></i>Business day cutoff: 5:00 PM. Transactions after 5 PM are counted in the next day's report.</small>
                    </div>
                    <div class="text-end">
                        <a href="sales_report_print.php?start_date=<?php echo urlencode($start_date_input); ?>&end_date=<?php echo urlencode($end_date_input); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $page; ?>" class="btn btn-light">
                            <i class="fas fa-print me-2"></i>Print Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="filter-section">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="text" class="form-control datepicker <?php echo !empty($errors) ? 'is-invalid' : ''; ?>" name="start_date" id="start_date"
                               value="<?php echo $start_date_input; ?>" 
                               placeholder="mm/dd/yyyy" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="text" class="form-control datepicker <?php echo !empty($errors) ? 'is-invalid' : ''; ?>" name="end_date" id="end_date"
                               value="<?php echo $end_date_input; ?>" 
                               placeholder="mm/dd/yyyy" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-search me-2"></i>Filter Sales
                        </button>
                    </div>
                </form>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-card">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Date Validation Errors</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Search Section -->
            <div class="search-section">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="start_date" value="<?php echo $start_date_input; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date_input; ?>">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Search Customer or Invoice</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by customer name or invoice number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Items per Page</label>
                        <select class="form-select" name="per_page" onchange="this.form.submit()">
                            <option value="10" <?php if($perPage == 10) echo 'selected'; ?>>10</option>
                            <option value="20" <?php if($perPage == 20) echo 'selected'; ?>>20</option>
                            <option value="50" <?php if($perPage == 50) echo 'selected'; ?>>50</option>
                            <option value="100" <?php if($perPage == 100) echo 'selected'; ?>>100</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary d-block w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="row">
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($total_sales, 2); ?></div>
                        <div class="metric-label">Total Sales</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($cash_sales, 2); ?></div>
                        <div class="metric-label">Cash Sales</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-value">₱<?php echo number_format($credit_sales, 2); ?></div>
                        <div class="metric-label">Credit Sales</div>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Sales Transactions</h5>
                    <span class="text-muted">Showing <?php echo $start + 1; ?>-<?php echo min($start + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> transactions</span>
                </div>
                
                <?php if (empty($paged_sales_data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No sales transactions found</h5>
                        <p class="text-muted">Try adjusting your date range or search criteria</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice No</th>
                                    <th>Customer</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paged_sales_data as $sale): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $business_date = getBusinessDate($sale['created_at']);
                                            $actual_date = date('Y-m-d', strtotime($sale['created_at']));
                                            $is_after_cutoff = $business_date !== $actual_date;
                                            ?>
                                            <?php echo date('M d, Y', strtotime($business_date)); ?>
                                            <?php if ($is_after_cutoff): ?>
                                                <small class="text-muted d-block">(processed <?php echo date('M d, g:i A', strtotime($sale['created_at'])); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo $sale['invoice_no']; ?></span></td>
                                        <td>
                                            <?php
                                            if ($sale['customer_type'] == 'Walk-in') {
                                                echo '<span class="text-muted">Walk-in Customer</span>';
                                            } else {
                                                $customer_name = '';
                                                if (!empty($sale['org_name'])) {
                                                    $customer_name = $sale['org_name'];
                                                } else {
                                                    $customer_name = trim($sale['creditor_fn'] . ' ' . $sale['creditor_mn'] . ' ' . $sale['creditor_ln']);
                                                }
                                                echo $customer_name;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $sale['payment_method'] == 'Cash' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $sale['payment_method']; ?>
                                            </span>
                                        </td>
                                        <td><strong>₱<?php echo number_format($sale['grand_total'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalRows > 0): ?>
                        <nav aria-label="Sales pagination" class="mt-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Showing <?php echo $start + 1; ?>-<?php echo min($start + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> transactions
                                    <?php if ($totalPages > 1): ?>
                                        (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($totalPages > 1): ?>
                                    <ul class="pagination pagination-sm mb-0">
                                        <!-- First Page -->
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=1" title="First Page">
                                                    <i class="fas fa-angle-double-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Previous Page -->
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $page - 1; ?>" title="Previous Page">
                                                    <i class="fas fa-angle-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($totalPages, $page + 2);
                                        
                                        // Show first page if not in range
                                        if ($start_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=1">1</a>
                                            </li>
                                            <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Show last page if not in range -->
                                        <?php if ($end_page < $totalPages): ?>
                                            <?php if ($end_page < $totalPages - 1): ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Next Page -->
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $page + 1; ?>" title="Next Page">
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Last Page -->
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?start_date=<?php echo urlencode(date('m/d/Y', strtotime($start_date))); ?>&end_date=<?php echo urlencode(date('m/d/Y', strtotime($end_date))); ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $totalPages; ?>" title="Last Page">
                                                    <i class="fas fa-angle-double-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted">
                                        <small>All results shown on this page</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Floating Back Button -->
    <div class="floating-back-btn">
        <button onclick="window.history.back()" 
                class="btn btn-primary rounded-circle shadow-lg" 
                title="Go Back">
            <i class="fas fa-arrow-left"></i>
        </button>
    </div>

    <script>
        // Initialize datepicker with m/d/Y format
        $(document).ready(function() {
            $('.datepicker').datepicker({
                format: 'mm/dd/yyyy',
                autoclose: true,
                todayHighlight: true,
                endDate: '0d', // Prevent selecting future dates
                maxViewMode: 0 // Only show days view
            }).on('changeDate', function() {
                validateDates();
            });

            // Real-time validation on input
            $('.datepicker').on('input blur', function() {
                validateDates();
            });

            // Function to validate dates
            function validateDates() {
                const startDateInput = $('input[name="start_date"]');
                const endDateInput = $('input[name="end_date"]');
                const startDate = startDateInput.val();
                const endDate = endDateInput.val();
                
                // Clear previous error messages
                $('.date-error').remove();
                startDateInput.removeClass('is-invalid');
                endDateInput.removeClass('is-invalid');
                
                const errors = [];
                const currentDate = new Date();
                // Reset time to start of current day to ensure proper date comparison
                currentDate.setHours(0, 0, 0, 0);
                
                // Validate start date
                let startDateObj = null;
                if (startDate) {
                    startDateObj = parseDate(startDate);
                    if (!startDateObj) {
                        errors.push('Invalid start date format. Use mm/dd/yyyy');
                        startDateInput.addClass('is-invalid');
                    } else {
                        // Reset time to start of day for proper comparison
                        const startDateOnly = new Date(startDateObj.getFullYear(), startDateObj.getMonth(), startDateObj.getDate());
                        if (startDateOnly > currentDate) {
                            errors.push('Start date cannot be beyond the current day.');
                            startDateInput.addClass('is-invalid');
                        }
                    }
                }
                
                // Validate end date
                let endDateObj = null;
                if (endDate) {
                    endDateObj = parseDate(endDate);
                    if (!endDateObj) {
                        errors.push('Invalid end date format. Use mm/dd/yyyy');
                        endDateInput.addClass('is-invalid');
                    } else {
                        // Reset time to start of day for proper comparison
                        const endDateOnly = new Date(endDateObj.getFullYear(), endDateObj.getMonth(), endDateObj.getDate());
                        if (endDateOnly > currentDate) {
                            errors.push('End date cannot be beyond the current day.');
                            endDateInput.addClass('is-invalid');
                        }
                    }
                }
                
                // Validate date range
                if (startDate && endDate && startDateObj && endDateObj) {
                    // Reset time to start of day for proper comparison
                    const startDateOnly = new Date(startDateObj.getFullYear(), startDateObj.getMonth(), startDateObj.getDate());
                    const endDateOnly = new Date(endDateObj.getFullYear(), endDateObj.getMonth(), endDateObj.getDate());
                    if (endDateOnly < startDateOnly) {
                        errors.push('End date cannot be earlier than start date.');
                        endDateInput.addClass('is-invalid');
                    }
                }
                
                // Display errors
                if (errors.length > 0) {
                    const errorHtml = '<div class="date-error alert alert-danger mt-2"><ul class="mb-0"><li>' + errors.join('</li><li>') + '</li></ul></div>';
                    $('.filter-section').after(errorHtml);
                }
            }
            
            // Function to parse date in mm/dd/yyyy format
            function parseDate(dateString) {
                const parts = dateString.split('/');
                if (parts.length !== 3) return null;
                
                const month = parseInt(parts[0], 10);
                const day = parseInt(parts[1], 10);
                const year = parseInt(parts[2], 10);
                
                if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900 || year > 2100) {
                    return null;
                }
                
                const date = new Date(year, month - 1, day);
                if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                    return null;
                }
                
                return date;
            }
        });
    </script>
</body>

</html>