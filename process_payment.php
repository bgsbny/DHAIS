<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

// Get POST data (use $_POST for form-encoded, or decode JSON if sent as JSON)
$data = $_POST;
if (empty($data) && file_get_contents('php://input')) {
    $data = json_decode(file_get_contents('php://input'), true);
}

$invoice_no = $data['invoice_no'] ?? '';
$date_paid = $data['date_paid'] ?? '';
$amount_paid = $data['amount_paid'] ?? 0;
$payment_type = $data['payment_type'] ?? '';
$reference_no = $data['reference_no'] ?? '';
$recorded_by = $data['recorded_by'] ?? '';
$remarks = $data['remarks'] ?? '';

if (!$invoice_no || !$date_paid || !$amount_paid || !$payment_type || !$recorded_by) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Find credit_id for this invoice
$sql = "SELECT ct.credit_id FROM tbl_credit_transactions ct JOIN purchase_transactions pt ON ct.transaction_id = pt.transaction_id WHERE pt.invoice_no = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $invoice_no);
$stmt->execute();
$stmt->bind_result($credit_id);
$stmt->fetch();
$stmt->close();

if (!$credit_id) {
    echo json_encode(['success' => false, 'error' => 'Credit transaction not found for this invoice.']);
    exit;
}

// Insert payment
$stmt = $mysqli->prepare("INSERT INTO tbl_credit_payments (credit_id, date_paid, amount_paid, payment_type, reference_no, recorded_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('isdssss', $credit_id, $date_paid, $amount_paid, $payment_type, $reference_no, $recorded_by, $remarks);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close(); 