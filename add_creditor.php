<?php
include 'mycon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize all fields
    $tin = $_POST['tin'] ?? '';
    $org_name = $_POST['org_name'] ?? '';
    $org_contactNumber = $_POST['org_contactNumber'] ?? '';
    $org_email = $_POST['org_email'] ?? '';
    $org_street = $_POST['org_street'] ?? '';
    $org_baranggay = $_POST['org_baranggay'] ?? '';
    $org_city = $_POST['org_city'] ?? '';
    $org_province = $_POST['org_province'] ?? '';
    $org_contactPerson_firstName = $_POST['org_contactPerson_firstName'] ?? '';
    $org_contactPerson_middleName = $_POST['org_contactPerson_middleName'] ?? '';
    $org_contactPerson_lastName = $_POST['org_contactPerson_lastName'] ?? '';
    $org_contactPerson_suffix = $_POST['org_contactPerson_suffix'] ?? '';
    $org_contactPerson_nickname = $_POST['org_contactPerson_nickname'] ?? '';
    $org_contactPerson_contactNumber = $_POST['org_contactPerson_contactNumber'] ?? '';
    $org_contactPerson_email = $_POST['org_contactPerson_email'] ?? '';
    $org_contactPerson_street = $_POST['org_contactPerson_street'] ?? '';
    $org_contactPerson_baranggay = $_POST['org_contactPerson_baranggay'] ?? '';
    $org_contactPerson_city = $_POST['org_contactPerson_city'] ?? '';
    $org_contactPerson_province = $_POST['org_contactPerson_province'] ?? '';
    $creditor_fn = $_POST['creditor_fn'] ?? '';
    $creditor_mn = $_POST['creditor_mn'] ?? '';
    $creditor_ln = $_POST['creditor_ln'] ?? '';
    $creditor_suffix = $_POST['creditor_suffix'] ?? '';
    $creditor_nickname = $_POST['creditor_nickname'] ?? '';
    $creditor_contactNo = $_POST['creditor_contactNo'] ?? '';
    $creditor_email = $_POST['creditor_email'] ?? '';
    $creditor_street = $_POST['creditor_street'] ?? '';
    $creditor_baranggay = $_POST['creditor_baranggay'] ?? '';
    $creditor_city = $_POST['creditor_city'] ?? '';
    $creditor_province = $_POST['creditor_province'] ?? '';

    $sql = "INSERT INTO tbl_creditors (
        tin, org_name, org_contactNumber, org_email, org_street, org_baranggay, org_city, org_province,
        org_contactPerson_firstName, org_contactPerson_middleName, org_contactPerson_lastName, org_contactPerson_suffix, org_contactPerson_nickname, org_contactPerson_contactNumber, org_contactPerson_email, org_contactPerson_street, org_contactPerson_baranggay, org_contactPerson_city, org_contactPerson_province,
        creditor_fn, creditor_mn, creditor_ln, creditor_suffix, creditor_nickname, creditor_contactNo, creditor_email, creditor_street, creditor_baranggay, creditor_city, creditor_province
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "ssssssssssssssssssssssssssssss",
        $tin, $org_name, $org_contactNumber, $org_email, $org_street, $org_baranggay, $org_city, $org_province,
        $org_contactPerson_firstName, $org_contactPerson_middleName, $org_contactPerson_lastName, $org_contactPerson_suffix, $org_contactPerson_nickname, $org_contactPerson_contactNumber, $org_contactPerson_email, $org_contactPerson_street, $org_contactPerson_baranggay, $org_contactPerson_city, $org_contactPerson_province,
        $creditor_fn, $creditor_mn, $creditor_ln, $creditor_suffix, $creditor_nickname, $creditor_contactNo, $creditor_email, $creditor_street, $creditor_baranggay, $creditor_city, $creditor_province
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request']);