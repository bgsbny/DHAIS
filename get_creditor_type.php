<?php
include 'mycon.php';
header('Content-Type: application/json');
$name = $_GET['name'] ?? '';
if (!$name) {
    echo json_encode(['success' => false]);
    exit;
}
$sql = "SELECT org_name FROM tbl_creditors WHERE org_name = ? OR CONCAT_WS(' ', creditor_fn, creditor_mn, creditor_ln, creditor_suffix) = ? OR creditor_nickname = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('sss', $name, $name, $name);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
if ($row && !empty($row['org_name'])) {
    echo json_encode(['success' => true, 'type' => 'organization']);
} else {
    echo json_encode(['success' => true, 'type' => 'individual']);
}
$stmt->close(); 