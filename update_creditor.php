<?php
include('mycon.php');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle creditor update if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_type'])) {
    $creditorId = isset($_POST['creditor_id']) ? (int)$_POST['creditor_id'] : 0;
    
    if ($creditorId <= 0) {
        header('Location: creditor_list.php');
        exit();
    }
    
    try {
        if ($_POST['edit_type'] === 'organization') {
            // Update organization information
            $orgName = isset($_POST['org_name']) ? trim($_POST['org_name']) : '';
            $orgStreet = isset($_POST['org_street']) ? trim($_POST['org_street']) : '';
            $orgBaranggay = isset($_POST['org_baranggay']) ? trim($_POST['org_baranggay']) : '';
            $orgCity = isset($_POST['org_city']) ? trim($_POST['org_city']) : '';
            $orgProvince = isset($_POST['org_province']) ? trim($_POST['org_province']) : '';
            $orgContactNumber = isset($_POST['org_contactNumber']) ? trim($_POST['org_contactNumber']) : '';
            $orgEmail = isset($_POST['org_email']) ? trim($_POST['org_email']) : '';
            
            // Validation
            if (empty($orgName)) {
                $errorMessage = "Organization name is required";
            } else {
                $updateQuery = "UPDATE tbl_creditors SET 
                    org_name = ?, 
                    org_street = ?, 
                    org_baranggay = ?, 
                    org_city = ?, 
                    org_province = ?, 
                    org_contactNumber = ?, 
                    org_email = ? 
                    WHERE creditor_id = ?";
                
                $updateStmt = $mysqli->prepare($updateQuery);
                $updateStmt->bind_param('sssssssi', $orgName, $orgStreet, $orgBaranggay, $orgCity, $orgProvince, $orgContactNumber, $orgEmail, $creditorId);
                
                if ($updateStmt->execute()) {
                    $successMessage = "Organization information updated successfully!";
                } else {
                    $errorMessage = "Error updating organization information: " . $mysqli->error;
                }
                $updateStmt->close();
            }
            
        } elseif ($_POST['edit_type'] === 'contact_person') {
            // Update contact person information
            $contactFirstName = isset($_POST['org_contactPerson_firstName']) ? trim($_POST['org_contactPerson_firstName']) : '';
            $contactMiddleName = isset($_POST['org_contactPerson_middleName']) ? trim($_POST['org_contactPerson_middleName']) : '';
            $contactLastName = isset($_POST['org_contactPerson_lastName']) ? trim($_POST['org_contactPerson_lastName']) : '';
            $contactSuffix = isset($_POST['org_contactPerson_suffix']) ? trim($_POST['org_contactPerson_suffix']) : '';
            $contactStreet = isset($_POST['org_contactPerson_street']) ? trim($_POST['org_contactPerson_street']) : '';
            $contactBaranggay = isset($_POST['org_contactPerson_baranggay']) ? trim($_POST['org_contactPerson_baranggay']) : '';
            $contactCity = isset($_POST['org_contactPerson_city']) ? trim($_POST['org_contactPerson_city']) : '';
            $contactProvince = isset($_POST['org_contactPerson_province']) ? trim($_POST['org_contactPerson_province']) : '';
            $contactContactNumber = isset($_POST['org_contactPerson_contactNumber']) ? trim($_POST['org_contactPerson_contactNumber']) : '';
            $contactEmail = isset($_POST['org_contactPerson_email']) ? trim($_POST['org_contactPerson_email']) : '';
            
            $updateQuery = "UPDATE tbl_creditors SET 
                org_contactPerson_firstName = ?, 
                org_contactPerson_middleName = ?, 
                org_contactPerson_lastName = ?, 
                org_contactPerson_suffix = ?, 
                org_contactPerson_street = ?, 
                org_contactPerson_baranggay = ?, 
                org_contactPerson_city = ?, 
                org_contactPerson_province = ?, 
                org_contactPerson_contactNumber = ?, 
                org_contactPerson_email = ? 
                WHERE creditor_id = ?";
            
            $updateStmt = $mysqli->prepare($updateQuery);
            $updateStmt->bind_param('ssssssssssi', $contactFirstName, $contactMiddleName, $contactLastName, $contactSuffix, $contactStreet, $contactBaranggay, $contactCity, $contactProvince, $contactContactNumber, $contactEmail, $creditorId);
            
            if ($updateStmt->execute()) {
                $successMessage = "Contact person information updated successfully!";
            } else {
                $errorMessage = "Error updating contact person information: " . $mysqli->error;
            }
            $updateStmt->close();
        }
        
    } catch (Exception $e) {
        $errorMessage = "An error occurred: " . $e->getMessage();
    }
    
    // Redirect back to the same page without parameters
    header('Location: creditor_details.php?id=' . $creditorId);
    exit();
}

// If not a valid request, redirect back
header('Location: creditor_list.php');
exit();
?> 