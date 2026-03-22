<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No data received']);
    exit;
}

$cart = $data['cart'] ?? [];
$payment_method = $data['payment_method'] ?? '';
$creditor_id = $data['creditor_id'] ?? null;
$subtotal = $data['subtotal'] ?? 0;
$discount_percentage = $data['discount_percentage'] ?? 0;
$grand_total = $data['grand_total'] ?? 0;
$external_receipt_no = $data['external_receipt_no'] ?? '';
$customer_type = $data['customer_type'] ?? '';
$customer_firstName = $data['customer_firstName'] ?? '';
$customer_middleName = $data['customer_middleName'] ?? '';
$customer_lastName = $data['customer_lastName'] ?? '';
$customer_suffix = $data['customer_suffix'] ?? '';
$created_at = date('Y-m-d H:i:s');

// Determine transaction_date based on cut-off time (5:00 PM)
$current_time = date('H:i:s');
$cut_off_time = '17:00:00'; // 5:00 PM

if ($current_time <= $cut_off_time) {
    // Transaction before or at 5:00 PM - use today's date
    $transaction_date = date('Y-m-d');
} else {
    // Transaction after 5:00 PM - use tomorrow's date
    $transaction_date = date('Y-m-d', strtotime('+1 day'));
}

// Generate invoice_no: INV-YYYY-00001
$year = date('Y');
$prefix = "INV-$year-";
$sql = "SELECT invoice_no FROM purchase_transactions WHERE invoice_no LIKE ? ORDER BY invoice_no DESC LIMIT 1";
$like = $prefix . '%';
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $like);
$stmt->execute();
$stmt->bind_result($last_invoice_no);
$stmt->fetch();
$stmt->close();
if ($last_invoice_no) {
    $last_number = intval(substr($last_invoice_no, strlen($prefix)));
    $new_number = $last_number + 1;
} else {
    $new_number = 1;
}
$invoice_no = $prefix . str_pad($new_number, 5, '0', STR_PAD_LEFT);

// Credit fields
$due_date = $data['due_date'] ?? null;
$interest = $data['interest'] ?? null;
$down_payment = $data['down_payment'] ?? null;

$mysqli->begin_transaction();
try {
    // Insert into purchase_transactions
    $stmt = $mysqli->prepare("INSERT INTO purchase_transactions (transaction_date, payment_method, creditor_id, subtotal, discount_percentage, grand_total, external_receipt_no, invoice_no, customer_type, customer_firstName, customer_middleName, customer_lastName, customer_suffix, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('ssiddssssssss', $transaction_date, $payment_method, $creditor_id, $subtotal, $discount_percentage, $grand_total, $external_receipt_no, $invoice_no, $customer_type, $customer_firstName, $customer_middleName, $customer_lastName, $customer_suffix);
    $stmt->execute();
    $transaction_id = $stmt->insert_id;
    $stmt->close();

    // Insert cart items into purchase_transaction_details
    $stmt = $mysqli->prepare("INSERT INTO purchase_transaction_details (transaction_id, product_id, purchased_quantity, unit_price_at_purchase, product_discount, product_markup, product_subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($cart as $item) {
        $product_id = $item['product_id'];
        $qty = $item['qty'];
        $unit_price = $item['unit_price'];
        $discount = $item['discount'];
        $markup = $item['markup'] ?? 0;
        $subtotal = $item['subtotal'];
        $stmt->bind_param('iiidddd', $transaction_id, $product_id, $qty, $unit_price, $discount, $markup, $subtotal);
        $stmt->execute();
        
        // Note: Stock subtraction is now handled by the frontend via subtract_stock.php
        // based on the warehouse selection in the cart item modal
    }
    $stmt->close();

    // If credit, insert into tbl_credit_transactions
    if (strtolower($payment_method) === 'credit') {
        $stmt = $mysqli->prepare("INSERT INTO tbl_credit_transactions (transaction_id, creditor_id, due_date, interest, total_with_interest, status) VALUES (?, ?, ?, ?, ?, ?)");
        $status = 'pending';
        $total_with_interest = $subtotal + $interest;
        $stmt->bind_param('iisdss', $transaction_id, $creditor_id, $due_date, $interest, $total_with_interest, $status);
        $stmt->execute();
        $credit_transaction_id = $stmt->insert_id;
        $stmt->close();
        
        // Handle down payment if provided
        if (!empty($down_payment) && $down_payment > 0) {
            $stmt = $mysqli->prepare("INSERT INTO tbl_credit_payments (credit_id, date_paid, amount_paid, payment_type, reference_no, recorded_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $date_paid = date('Y-m-d');
            $payment_type = 'Cash';
            $reference_no = $invoice_no;
            $recorded_by = $_SESSION['username'] ?? 'System';
            $remarks = 'Down Payment';
            $stmt->bind_param('issssss', $credit_transaction_id, $date_paid, $down_payment, $payment_type, $reference_no, $recorded_by, $remarks);
            $stmt->execute();
            $stmt->close();
        }
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'invoice_no' => $invoice_no]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}