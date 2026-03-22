<?php
// Include database connection
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get invoice number and creditor_id from URL
$invoiceNo = isset($_GET['invoice']) ? $_GET['invoice'] : '';
$creditorId = isset($_GET['creditor_id']) ? (int)$_GET['creditor_id'] : 0;

if (!$invoiceNo) {
    // Redirect if no invoice number
    header('Location: creditor_list.php');
    exit();
}

// Get credit transaction info (interest, total_with_interest, etc.)
$creditQuery = "SELECT total_with_interest, interest 
                FROM tbl_credit_transactions ct 
                JOIN purchase_transactions pt 
                ON ct.transaction_id = pt.transaction_id 
                WHERE pt.invoice_no = ? 
                LIMIT 1";

$creditStmt = $mysqli->prepare($creditQuery);
$creditStmt->bind_param('s', $invoiceNo);
$creditStmt->execute();
$creditResult = $creditStmt->get_result();
$creditData = $creditResult->fetch_assoc();

$totalWithInterest = $creditData['total_with_interest'] ?? 0;
$interest = $creditData['interest'] ?? 0; // treat as fixed amount

// Get subtotal from purchase_transactions
$subtotalQuery = "SELECT subtotal FROM purchase_transactions WHERE invoice_no = ? LIMIT 1";
$subtotalStmt = $mysqli->prepare($subtotalQuery);
$subtotalStmt->bind_param('s', $invoiceNo);
$subtotalStmt->execute();
$subtotalResult = $subtotalStmt->get_result();
$subtotalRow = $subtotalResult->fetch_assoc();
$subtotal = $subtotalRow['subtotal'] ?? 0;
$subtotalStmt->close();

// Get product/service details for this invoice
$productQuery = "SELECT m.item_name, 
                        m.oem_number, 
                        m.category, 
                        m.product_brand, 
                        m.unit, 
                        d.purchased_quantity, 
                        d.unit_price_at_purchase, 
                        d.product_subtotal 
                        FROM purchase_transaction_details d 
                        LEFT JOIN tbl_masterlist m ON d.product_id = m.product_id 
                        JOIN purchase_transactions pt 
                        ON d.transaction_id = pt.transaction_id 
                        WHERE pt.invoice_no = ?";
$productStmt = $mysqli->prepare($productQuery);
$productStmt->bind_param('s', $invoiceNo);
$productStmt->execute();
$productResult = $productStmt->get_result();
$products = [];
while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
}

// Get invoice, credit, and contact info
$headerQuery = "SELECT pt.invoice_no, 
                        pt.transaction_date, 
                        ct.due_date, 
                        c.org_name, 
                        c.creditor_fn, 
                        c.org_contactPerson_firstName, 
                        c.org_contactPerson_lastName, 
                        c.org_contactNumber, 
                        c.creditor_contactNo 
                        FROM tbl_credit_transactions ct 
                        JOIN purchase_transactions pt 
                        ON ct.transaction_id = pt.transaction_id 
                        JOIN tbl_creditors c 
                        ON ct.creditor_id = c.creditor_id 
                        WHERE pt.invoice_no = ? 
                        LIMIT 1";

$headerStmt = $mysqli->prepare($headerQuery);
$headerStmt->bind_param('s', $invoiceNo);
$headerStmt->execute();
$headerResult = $headerStmt->get_result();
$headerData = $headerResult->fetch_assoc();

$invoiceNoHeader = $headerData['invoice_no'] ?? '';
$creditDate = $headerData['transaction_date'] ?? '';
$dueDate = $headerData['due_date'] ?? '';
// Contact person logic
if (!empty($headerData['org_name'])) {
    $contactPerson = trim(($headerData['org_contactPerson_firstName'] ?? '') . ' ' . ($headerData['org_contactPerson_lastName'] ?? ''));
    $contactNumber = $headerData['org_contactNumber'] ?? '';
    if ($contactPerson === '') $contactPerson = $headerData['org_name'];
} else {
    $contactPerson = $headerData['creditor_fn'] ?? '';
    $contactNumber = $headerData['creditor_contactNo'] ?? '';
}

// Fetch payment history for this invoice
$paymentQuery = "SELECT cp.date_paid, 
                        cp.amount_paid, 
                        cp.payment_type, 
                        cp.reference_no, 
                        cp.recorded_by, 
                        cp.remarks 
                        FROM tbl_credit_payments cp 
                        JOIN tbl_credit_transactions ct 
                        ON cp.credit_id = ct.credit_id 
                        JOIN purchase_transactions pt 
                        ON ct.transaction_id = pt.transaction_id 
                        WHERE pt.invoice_no = ? 
                        ORDER BY cp.date_paid ASC";
                        
$paymentStmt = $mysqli->prepare($paymentQuery);
$paymentStmt->bind_param('s', $invoiceNo);
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();
$paymentHistory = [];
$totalPaid = 0;
while ($row = $paymentResult->fetch_assoc()) {
    $paymentHistory[] = $row;
    $totalPaid += floatval($row['amount_paid']);
}
$balance = $totalWithInterest - $totalPaid;
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

    <title>Creditor Invoice Details</title>
</head>
<body>
    <?php $activePage = 'creditor_list'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Invoice Details</h1>
                    <p class="page-subtitle">View detailed information about this credit invoice.</p>
                </div>
            </div>
        </header>

        <div class="main-container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="creditor_list.php" class="text-decoration-none">Creditor List</a></li>
                    <li class="breadcrumb-item"><a href="creditor_details.php" class="text-decoration-none">Creditor Information</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Credit Invoice Details</li>
                </ol>
            </nav>

            <!-- Invoice Header Card -->
            <div class="content-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-receipt me-3 text-primary"></i>
                            <h5 class="card-title mb-0">Invoice Details</h5>
                        </div>
                    </div>
                    
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="invoice-info">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Invoice Number:</span>
                                    <span class="fw-bold invoice-number"><?php echo htmlspecialchars($invoiceNoHeader); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Credit Date:</span>
                                    <span class="fw-semibold"><?php echo $creditDate ? date('F j, Y', strtotime($creditDate)) : ''; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Due Date:</span>
                                    <span class="fw-semibold"><?php echo $dueDate ? date('F j, Y', strtotime($dueDate)) : ''; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="customer-info">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Contact Person:</span>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($contactPerson); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Contact Number:</span>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($contactNumber); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table Card -->
            <div class="content-card mb-4">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-boxes me-3 text-primary"></i>
                        <h5 class="card-title mb-0">Products</h5>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product</th>
                                    <th class="text-center">Unit</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Price (₱)</th>
                                    <th class="text-end">Subtotal (₱)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $prod): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold"><?php echo htmlspecialchars($prod['item_name'] ?? ''); ?></div>
                                            <?php
                                            // Display part number or size if available
                                            $partNumberOrSize = '';
                                            if (!empty($prod['oem_number'])) {
                                                $partNumberOrSize = $prod['oem_number'];
                                            } elseif (!empty($prod['product_size'])) {
                                                $partNumberOrSize = $prod['product_size'];
                                            }
                                            
                                            if (!empty($partNumberOrSize)) {
                                                echo '<small class="text-muted">' . htmlspecialchars($partNumberOrSize) . '</small><br>';
                                            }
                                            ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($prod['product_brand'] ?? ''); ?><?php if (!empty($prod['product_brand']) && !empty($prod['category'])): ?> - <?php endif; ?><?php echo htmlspecialchars($prod['category'] ?? ''); ?></small>
                                        </td>
                                        <td class="text-center"><?php echo htmlspecialchars($prod['unit'] ?? ''); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($prod['purchased_quantity']); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['unit_price_at_purchase'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['product_subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No products/services found for this invoice.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="content-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calculator me-3 text-primary"></i>
                        <h5 class="card-title mb-0">Payment Summary</h5>
                    </div>
                    
                    <div class="mt-3 d-flex justify-content-end">
                        <button class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#paymentHistoryModal">
                            <i class="fas fa-history me-1"></i>Payment History
                        </button>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#processPaymentModal">
                            <i class="fas fa-cash-register me-1"></i>Process Payment
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="summary-details">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold">₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Interest:</span>
                                    <span class="fw-semibold text-success">₱<?php echo number_format($interest, 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold fs-5">Grand Total:</span>
                                    <span class="fw-bold fs-5 text-primary">₱<?php echo number_format($totalWithInterest, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Total Paid:</span>
                                    <span class="fw-semibold text-success">₱<?php echo number_format($totalPaid, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold">Balance:</span>
                                    <span class="fw-bold text-danger">₱<?php echo number_format($balance, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History Modal -->
            <div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="paymentHistoryModalLabel"><i class="fas fa-history me-2"></i>Payment History</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr class='align-middle'>
                                            <th>Date Paid</th>
                                            <th>Amount Paid</th>
                                            <th>Payment Method</th>
                                            <th>Reference No</th>
                                            <th>Recorded By</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payment-history-tbody">
                                        <!-- Payment history rows will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Process Payment Modal -->
            <div class="modal fade" id="processPaymentModal" tabindex="-1" aria-labelledby="processPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="processPaymentModalLabel"><i class="fas fa-cash-register me-2"></i>Process Payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="process-payment-form">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="date_paid" class="form-label">Date Paid</label>
                                        <input type="date" class="form-control" id="date_paid" name="date_paid" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="amount_paid" class="form-label">Amount Paid</label>
                                        <input type="text" class="form-control text-end" id="amount_paid" name="amount_paid" placeholder="0.00" required>
                                        <small class="text-muted">Maximum payment: ₱<?php echo number_format($balance, 2); ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="payment_type" class="form-label">Payment Method</label>
                                        <select class="form-select" id="payment_type" name="payment_type" required>
                                            <option value="Cash">Cash</option>
                                            <option value="GCash">GCash</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="reference_no" class="form-label">Reference No</label>
                                        <input type="text" class="form-control" id="reference_no" name="reference_no">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="recorded_by" class="form-label">Recorded By</label>
                                        <select name="recorded_by" id="recorded_by" class='form-select'>
                                            <option value="Admin - Owner">Admin - Owner</option>
                                            <option value="Employee - Staff">Employee - Staff</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="remarks" class="form-label">Remarks</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="1"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Record Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
// Render payment history from PHP array
function renderPaymentHistory() {
    const tbody = document.getElementById('payment-history-tbody');
    tbody.innerHTML = '';
    const paymentHistory = <?php echo json_encode($paymentHistory); ?>;
    if (paymentHistory.length === 0) {
        tbody.innerHTML = `<tr><td colspan='6' class='text-center text-muted'>No payment history found for this invoice.</td></tr>`;
        return;
    }
    paymentHistory.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(row.date_paid).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
            <td>₱${parseFloat(row.amount_paid).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
            <td>${row.payment_type}</td>
            <td>${row.reference_no}</td>
            <td>${row.recorded_by}</td>
            <td>${row.remarks}</td>
        `;
        tbody.appendChild(tr);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    renderPaymentHistory();
    // Re-render payment history when modal is shown
    const paymentHistoryModal = document.getElementById('paymentHistoryModal');
    paymentHistoryModal.addEventListener('show.bs.modal', renderPaymentHistory);

    // Reference No. required logic
    const paymentMethodSelect = document.getElementById('payment_type'); // Changed from paymentMethod to payment_type
    const referenceNoInput = document.getElementById('reference_no'); // Changed from referenceNo to reference_no
    const referenceNoLabel = document.querySelector('label[for="reference_no"]'); // Changed from referenceNoLabel to reference_noLabel
    function updateReferenceNoRequired() {
        const method = paymentMethodSelect.value;
        if (method === 'Cash') {
            referenceNoInput.required = false;
            referenceNoLabel.innerHTML = 'Reference No';
        } else {
            referenceNoInput.required = true;
            referenceNoLabel.innerHTML = 'Reference No <span style="color:red">*</span>';
        }
    }
    paymentMethodSelect.addEventListener('change', updateReferenceNoRequired);
    updateReferenceNoRequired();

    // Real-time validation for payment amount
    const amountPaidInput = document.getElementById('amount_paid');
    const balance = <?php echo $balance; ?>;
    
    // Format amount with commas and handle right-to-left typing
    amountPaidInput.addEventListener('input', function() {
        let value = this.value.replace(/[^\d.]/g, ''); // Remove non-numeric characters except decimal
        
        // Handle decimal points
        const decimalIndex = value.indexOf('.');
        if (decimalIndex !== -1) {
            const beforeDecimal = value.substring(0, decimalIndex);
            const afterDecimal = value.substring(decimalIndex + 1);
            
            // Limit decimal places to 2
            if (afterDecimal.length > 2) {
                value = beforeDecimal + '.' + afterDecimal.substring(0, 2);
            }
        }
        
        // Convert to number for validation
        const amount = parseFloat(value) || 0;
        const smallElement = this.parentNode.querySelector('small');
        
        // Format with commas for display
        if (value !== '') {
            const parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            this.value = parts.join('.');
        }
        
        // Validation
        if (amount > balance) {
            this.classList.add('is-invalid');
            smallElement.innerHTML = `<span class="text-danger">Amount exceeds balance of ₱${balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>`;
        } else if (amount <= 0 && this.value !== '') {
            this.classList.add('is-invalid');
            smallElement.innerHTML = `<span class="text-danger">Amount must be greater than 0</span>`;
        } else {
            this.classList.remove('is-invalid');
            smallElement.innerHTML = `Maximum payment: ₱${balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
        }
    });
    
    // Handle focus to position cursor at the end
    amountPaidInput.addEventListener('focus', function() {
        this.setSelectionRange(this.value.length, this.value.length);
    });

    // Handle process payment form submission
    document.getElementById('process-payment-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        
        // Validate payment amount against balance
        const amountPaid = parseFloat(form.amount_paid.value.replace(/,/g, '')) || 0;
        const balance = <?php echo $balance; ?>;
        
        if (amountPaid > balance) {
            alert('Payment amount cannot exceed the remaining balance of ₱' + balance.toLocaleString('en-US', { minimumFractionDigits: 2 }));
            form.amount_paid.focus();
            return;
        }
        
        if (amountPaid <= 0) {
            alert('Payment amount must be greater than 0.');
            form.amount_paid.focus();
            return;
        }
        
        // If reference no is required but empty, show error and do not submit
        if (referenceNoInput.required && !referenceNoInput.value.trim()) {
            referenceNoInput.classList.add('is-invalid');
            referenceNoInput.focus();
            return;
        } else {
            referenceNoInput.classList.remove('is-invalid');
        }

        // Gather payment data
        const paymentData = {
            invoice_no: '<?php echo htmlspecialchars($invoiceNo); ?>',
            date_paid: form.date_paid.value,
            amount_paid: form.amount_paid.value,
            payment_type: form.payment_type.value,
            reference_no: form.reference_no.value,
            recorded_by: form.recorded_by.value,
            remarks: form.remarks.value
        };

        // Send data to process_payment.php via AJAX
        $.ajax({
            url: 'process_payment.php', // URL of the PHP script to handle payment
            type: 'POST',
            data: paymentData,
            success: function(response) {
                // On success, reload the page to show updated payment history
                location.reload();
            },
            error: function(xhr, error) {
                alert('Error processing payment: ' + error);
                console.error(xhr.responseText);
            }
        });
    });
});
</script>
</body>
</html>
