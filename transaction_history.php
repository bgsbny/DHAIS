<?php
include ('mycon.php');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$customerType = isset($_GET['customer_type']) ? $_GET['customer_type'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filtering
$whereConditions = [];
$params = [];

if (!empty($fromDate)) {
    $whereConditions[] = "pt.transaction_date >= ?";
    $params[] = $fromDate;
}

if (!empty($toDate)) {
    $whereConditions[] = "pt.transaction_date <= ?";
    $params[] = $toDate;
}

if (!empty($customerType)) {
    $whereConditions[] = "pt.customer_type = ?";
    $params[] = $customerType;
}

if (!empty($search)) {
    $whereConditions[] = "(pt.invoice_no LIKE ? OR pt.external_receipt_no LIKE ? OR pt.customer_firstName LIKE ? OR pt.customer_middleName LIKE ? OR pt.customer_lastName LIKE ? OR pt.customer_suffix LIKE ? OR EXISTS (
        SELECT 1 FROM tbl_creditors c 
        WHERE c.creditor_id = ct.creditor_id 
        AND (c.org_name LIKE ? OR c.creditor_fn LIKE ? OR c.creditor_mn LIKE ? OR c.creditor_ln LIKE ? OR c.creditor_suffix LIKE ? OR c.creditor_nickname LIKE ?)
    ))";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM purchase_transactions pt LEFT JOIN tbl_credit_transactions ct ON pt.transaction_id = ct.transaction_id $whereClause";
$countStmt = $mysqli->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalCount = $totalResult->fetch_assoc()['total'];

// Pagination
$itemsPerPage = 5;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$totalPages = ceil($totalCount / $itemsPerPage);

// Get transactions with pagination
// Also fetch creditor_id for credit transactions
$query = "SELECT pt.transaction_id, pt.invoice_no, pt.external_receipt_no, pt.customer_type, pt.grand_total, pt.transaction_date, pt.customer_firstName, pt.customer_middleName, pt.customer_lastName, pt.customer_suffix, ct.creditor_id 
          FROM purchase_transactions pt 
          LEFT JOIN tbl_credit_transactions ct ON pt.transaction_id = ct.transaction_id 
          $whereClause 
          ORDER BY pt.transaction_date DESC, pt.transaction_id DESC 
          LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($query);

// Add pagination parameters
$allParams = array_merge($params, [$itemsPerPage, $offset]);
$stmt->bind_param(str_repeat('s', count($allParams)), ...$allParams);
$stmt->execute();
$result = $stmt->get_result();

// Calculate showing count
$startItem = $offset + 1;
$endItem = min($offset + $itemsPerPage, $totalCount);
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
    
    <title>Transaction History</title>
</head>
<body>
    <?php $activePage = 'transaction_history'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Transaction History</h1>
                    <p class="page-subtitle">View and manage all sales transactions and customer records.</p>
                </div>
            </div>
        </header>
        
        <div class="main-container">
            <div class="align-items-center d-flex justify-content-between">
                <div class='d-flex justify-content-between align-items-center' style='width: 570px;'>
                    <div class="search-container">
                        <input type="text" name="search" id="search" placeholder="Search by Invoice Number, Receipt Number, Customer Name" autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                </div>

                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label for="from-date" class="form-label fw-semibold">From Date</label>
                        <input type="date" class="form-control" id="from-date" name="from-date">
                    </div>
                    <div class="col-md-3">
                        <label for="to-date" class="form-label fw-semibold">To Date</label>
                        <input type="date" class="form-control" id="to-date" name="to-date">
                    </div>
                    <div class="col-md-3">
                        <label for="customer-type" class="form-label fw-semibold">Customer Type</label>
                        <select class="form-select" id="customer-type" name="customer-type">
                            <option value="">All Types</option>
                            <option value="walk-in">Walk-In</option>
                            <option value="individual-creditor">Individual - Creditor</option>
                            <option value="organization-creditor">Organization - Creditor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary w-100" id="filter-btn">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
            <div class='mb-3'>
                <p class="text-muted mb-0">
                    Showing <span id="showing-count"><?php echo $startItem; ?>-<?php echo $endItem; ?></span> of <span id="total-count"><?php echo $totalCount; ?></span> transactions
                </p>
            </div>

            <div class="history">
                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr class='align-middle'>
                                <th>Invoice No.</th>
                                <th>Receipt No.</th>
                                <th>Customer</th>
                                <th>Customer Type</th>
                                <th class='text-end'>Total Amount (₱)</th>
                                <th>Transaction Date</th>
                            </tr>
                        </thead>
                        <tbody id="transaction-tbody">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="align-middle" style="cursor: pointer;" onclick="viewTransactionDetails(<?php echo htmlspecialchars($row['transaction_id']); ?>, '<?php echo htmlspecialchars($row['customer_type']); ?>', <?php echo !empty($row['creditor_id']) ? $row['creditor_id'] : 'null'; ?>, '<?php echo htmlspecialchars($row['invoice_no']); ?>')">
                                        <td><span class="invoice-number"><?php echo htmlspecialchars($row['invoice_no']); ?></span></td>
                                        <td><span class="receipt-number"><?php echo htmlspecialchars($row['external_receipt_no']); ?></span></td>
                                        <td>
                                <?php
                                // If credit, fetch creditor name
                                if (stripos($row['customer_type'], 'credit') !== false && !empty($row['creditor_id'])) {
                                    $creditorId = (int)$row['creditor_id'];
                                    $creditorName = '';
                                    $creditorQuery = "SELECT org_name, creditor_fn, creditor_mn, creditor_ln, creditor_suffix, creditor_nickname FROM tbl_creditors WHERE creditor_id = ? LIMIT 1";
                                    $creditorStmt = $mysqli->prepare($creditorQuery);
                                    $creditorStmt->bind_param('i', $creditorId);
                                    $creditorStmt->execute();
                                    $creditorResult = $creditorStmt->get_result();
                                    if ($cred = $creditorResult->fetch_assoc()) {
                                        if (!empty($cred['org_name'])) {
                                            $creditorName = $cred['org_name'];
                                        } else {
                                            $fullName = trim(($cred['creditor_fn'] ?? '') . ' ' . ($cred['creditor_mn'] ?? '') . ' ' . ($cred['creditor_ln'] ?? '') . ' ' . ($cred['creditor_suffix'] ?? ''));
                                            if (!empty(trim($fullName))) {
                                                $creditorName = $fullName;
                                            } elseif (!empty($cred['creditor_nickname'])) {
                                                $creditorName = $cred['creditor_nickname'];
                                            } else {
                                                $creditorName = 'N/A';
                                            }
                                        }
                                    }
                                    $creditorStmt->close();
                                    echo htmlspecialchars($creditorName);
                                } elseif (stripos($row['customer_type'], 'credit') !== false) {
                                    // Credit transaction but no creditor_id found
                                    echo '<span class="text-muted">Creditor info not found</span>';
                                } else {
                                    // Regular customer (walk-in)
                                    $customerName = trim($row['customer_firstName'] . ' ' . $row['customer_middleName'] . ' ' . $row['customer_lastName'] . ' ' . $row['customer_suffix']);
                                    echo htmlspecialchars($customerName ?: '');
                                }
                            ?>
                        </td>

                        <td>
                            <?php 
                                $typeClass = strtolower(str_replace([' ', '-'], ['-', ''], $row['customer_type']));
                            ?>
                            <span class="customer-type-badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($row['customer_type']); ?></span>
                        </td>
                        <td class="text-end"><span class="amount"><?php echo number_format($row['grand_total'], 2); ?></span></td>
                        <td><span class="date"><?php echo date('F j, Y', strtotime($row['transaction_date'])); ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No transactions found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Transaction pagination" class="mt-4">
                <ul class="pagination justify-content-center" id="pagination">
                <!-- Previous button -->
                    <li class="page-item <?php echo ($currentPage == 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <!-- Page numbers (show only 5 at a time) -->
                        <?php
                            $pageGroup = ceil($currentPage / 5);
                            $startPage = ($pageGroup - 1) * 5 + 1;
                            $endPage = min($startPage + 4, $totalPages);
                            for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <!-- Next button -->
                        <li class="page-item <?php echo ($currentPage == $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    // JavaScript for pagination and filter functionality
    function changePage(page) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('page', page);
        window.location.search = urlParams.toString();
    }

    function filterTransactions() {
        const fromDate = document.getElementById('from-date').value;
        const toDate = document.getElementById('to-date').value;
        const customerType = document.getElementById('customer-type').value;

        const urlParams = new URLSearchParams();
        if (fromDate) urlParams.set('from_date', fromDate);
        if (toDate) urlParams.set('to_date', toDate);
        if (customerType) urlParams.set('customer_type', customerType);

        window.location.search = urlParams.toString();
    }

    // Add event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Set current filter values
        const urlParams = new URLSearchParams(window.location.search);
        document.getElementById('from-date').value = urlParams.get('from_date') || '';
        document.getElementById('to-date').value = urlParams.get('to_date') || '';
        document.getElementById('customer-type').value = urlParams.get('customer_type') || '';
        document.getElementById('search').value = urlParams.get('search') || '';

        // Add event listeners
        document.getElementById('filter-btn').addEventListener('click', filterTransactions);
        
        // Initialize search functionality
        initializeSearch();
    });

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Real-time search functionality
    let searchTimeout;
    
    function initializeSearch() {
        $('#search').on('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val();
            
            // Add loading indicator
            $('#transaction-tbody').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Searching...</td></tr>');
            
            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 300); // 300ms delay to avoid too many requests
        });
    }
    
    function performSearch(searchTerm) {
        const fromDate = $('#from-date').val();
        const toDate = $('#to-date').val();
        const customerType = $('#customer-type').val();
        
        $.ajax({
            url: 'transaction_history.php',
            method: 'GET',
            data: {
                search: searchTerm,
                from_date: fromDate,
                to_date: toDate,
                customer_type: customerType,
                page: 1 // Reset to first page when searching
            },
            success: function(response) {
                // Extract the table content from the response
                const tempDiv = $('<div>').html(response);
                const newTableContent = tempDiv.find('#transaction-tbody').html();
                
                if (newTableContent) {
                    $('#transaction-tbody').html(newTableContent);
                    
                    // Update showing count
                    const showingCount = tempDiv.find('#showing-count').text();
                    const totalCount = tempDiv.find('#total-count').text();
                    $('#showing-count').text(showingCount);
                    $('#total-count').text(totalCount);
                    
                    // Update pagination
                    const newPagination = tempDiv.find('#pagination').html();
                    if (newPagination) {
                        $('#pagination').html(newPagination);
                    }
                }
            },
            error: function() {
                $('#transaction-tbody').html('<tr><td colspan="6" class="text-center py-4 text-danger">Error loading search results</td></tr>');
            }
        });
    }
    
    function viewTransactionDetails(transactionId, customerType, creditorId, invoiceNo) {
        // Check if this is a credit transaction
        if (customerType && customerType.toLowerCase().includes('credit') && creditorId) {
            // Navigate to creditor invoice details page
            window.location.href = `creditor_invoice_details.php?invoice=${encodeURIComponent(invoiceNo)}&creditor_id=${creditorId}`;
        } else {
            // Navigate to regular transaction details page
            window.location.href = `transaction_history_details.php?id=${transactionId}`;
        }
    }
    </script>
</body>
</html>
