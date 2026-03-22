<?php
// Include database connection
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle form submissions for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creditorId = isset($_POST['creditor_id']) ? (int)$_POST['creditor_id'] : 0;
    
    if ($creditorId > 0) {
        if (isset($_POST['edit_type'])) {
            if ($_POST['edit_type'] === 'organization') {
                // Update organization information
                $orgName = $_POST['org_name'];
                $orgStreet = $_POST['org_street'];
                $orgBaranggay = $_POST['org_baranggay'];
                $orgCity = $_POST['org_city'];
                $orgProvince = $_POST['org_province'];
                $orgContactNumber = $_POST['org_contactNumber'];
                $orgEmail = $_POST['org_email'];
                
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
                
            } elseif ($_POST['edit_type'] === 'contact_person') {
                // Update contact person information
                $contactFirstName = $_POST['org_contactPerson_firstName'];
                $contactMiddleName = $_POST['org_contactPerson_middleName'];
                $contactLastName = $_POST['org_contactPerson_lastName'];
                $contactSuffix = $_POST['org_contactPerson_suffix'];
                $contactStreet = $_POST['org_contactPerson_street'];
                $contactBaranggay = $_POST['org_contactPerson_baranggay'];
                $contactCity = $_POST['org_contactPerson_city'];
                $contactProvince = $_POST['org_contactPerson_province'];
                $contactContactNumber = $_POST['org_contactPerson_contactNumber'];
                $contactEmail = $_POST['org_contactPerson_email'];
                
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
        }
    }
    
    // Redirect to refresh the page and show the message
    if (isset($successMessage) || isset($errorMessage)) {
        $redirectUrl = "creditor_details.php?id=" . $creditorId;
        if (isset($successMessage)) {
            $redirectUrl .= "&success=" . urlencode($successMessage);
        }
        if (isset($errorMessage)) {
            $redirectUrl .= "&error=" . urlencode($errorMessage);
        }
        header('Location: ' . $redirectUrl);
        exit();
    }
}

// Get creditor ID from URL
$creditorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($creditorId <= 0) {
    // Redirect back to creditor list if no valid ID
    header('Location: creditor_list.php');
    exit();
}

// Get creditor details
$query = "SELECT * FROM tbl_creditors WHERE creditor_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $creditorId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect back to creditor list if creditor not found
    header('Location: creditor_list.php');
    exit();
}

$creditor = $result->fetch_assoc();

// Determine if it's an organization or individual
$isOrganization = !empty($creditor['org_name']);

// Fetch credit transactions for this creditor
$creditTransactions = [];
$creditQuery = "SELECT 
    ct.credit_id,
    pt.invoice_no, 
    pt.transaction_date, 
    ct.due_date, 
    ct.interest, 
    ct.total_with_interest, 
    ct.remarks
FROM tbl_credit_transactions ct
JOIN purchase_transactions pt ON ct.transaction_id = pt.transaction_id
WHERE ct.creditor_id = ?
ORDER BY pt.transaction_date DESC";
$creditStmt = $mysqli->prepare($creditQuery);
$creditStmt->bind_param('i', $creditorId);
$creditStmt->execute();
$creditResult = $creditStmt->get_result();
while ($row = $creditResult->fetch_assoc()) {
    // Fetch total paid for this credit transaction
    $credit_id = $row['credit_id'];
    $totalPaid = 0;
    $payQuery = "SELECT SUM(amount_paid) as total_paid FROM tbl_credit_payments WHERE credit_id = ?";
    $payStmt = $mysqli->prepare($payQuery);
    $payStmt->bind_param('i', $credit_id);
    $payStmt->execute();
    $payResult = $payStmt->get_result();
    if ($payRow = $payResult->fetch_assoc()) {
        $totalPaid = floatval($payRow['total_paid']);
    }
    $payStmt->close();
    $balance = floatval($row['total_with_interest']) - $totalPaid;
    $row['balance'] = $balance;
    $creditTransactions[] = $row;
}
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

    <title>Credit Details</title>
</head>
<body>
    <?php $activePage = 'creditor_list'; include 'navbar.php'; ?>

    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div id="save-notification" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="min-width: 200px;">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    </div>
    <script>
        setTimeout(function() {
            const notification = document.getElementById('save-notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 2000);
    </script>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div id="error-notification" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="min-width: 200px;">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    </div>
    <script>
        setTimeout(function() {
            const notification = document.getElementById('error-notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000);
    </script>
    <?php endif; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Creditor Details</h1>
                    <p class="page-subtitle">View and manage creditor information and credit transactions.</p>
                </div>
            </div>
        </header>
        <div class="main-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-0">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="creditor_list.php" class="text-decoration-none">Creditor List</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Creditor Information</li>
                    </ol>
                </nav>

                
                <ul class="nav nav-pills mb-0" id="creditorTabs">
                    <li class="">
                        <button class="btn active-tab" id="creditor-tab" type="button">Basic Information</button>
                    </li>
                    <li class="">
                        <button class="btn" id="credit-tab" type="button">Credit Information</button>
                    </li>
                </ul>
            </div>

            <div id="creditor-info-section">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light fw-semibold border-bottom">    
                                <i class="fas fa-<?php echo $isOrganization ? 'building' : 'user'; ?> me-2 text-primary"></i> 

                                <div>
                                    <?php if ($isOrganization): ?>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editOrganizationModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editIndividualModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php echo $isOrganization ? 'Organization Information' : 'Individual Information'; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Name</div>
                                    <div class="fw-semibold">
                                        <?php 
                                        if ($isOrganization) {
                                            echo htmlspecialchars($creditor['org_name']);
                                        } else {
                                            $firstName = trim($creditor['creditor_fn'] ?? '');
                                            $middleName = trim($creditor['creditor_mn'] ?? '');
                                            $lastName = trim($creditor['creditor_ln'] ?? '');
                                            $suffix = trim($creditor['creditor_suffix'] ?? '');
                                            echo htmlspecialchars(trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix));
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Address</div>
                                    <div>
                                        <?php 
                                        if ($isOrganization) {
                                            $address = trim($creditor['org_street'] ?? '') . ', ' . 
                                                     trim($creditor['org_baranggay'] ?? '') . ', ' . 
                                                     trim($creditor['org_city'] ?? '') . ', ' . 
                                                     trim($creditor['org_province'] ?? '');
                                            echo htmlspecialchars($address);
                                        } else {
                                            $address = trim($creditor['creditor_street'] ?? '') . ', ' . 
                                                     trim($creditor['creditor_baranggay'] ?? '') . ', ' . 
                                                     trim($creditor['creditor_city'] ?? '') . ', ' . 
                                                     trim($creditor['creditor_province'] ?? '');
                                            echo htmlspecialchars($address);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Contact Number</div>
                                    <div>
                                        <?php 
                                        if ($isOrganization) {
                                            echo htmlspecialchars($creditor['org_contactNumber'] ?? '');
                                        } else {
                                            echo htmlspecialchars($creditor['creditor_contactNo'] ?? '');
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Email Address</div>
                                    <div>
                                        <?php 
                                        if ($isOrganization) {
                                            echo htmlspecialchars($creditor['org_email'] ?? '');
                                        } else {
                                            echo htmlspecialchars($creditor['creditor_email'] ?? '');
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($isOrganization): ?>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light fw-semibold border-bottom">
                                <i class="fas fa-user me-2 text-primary"></i> 
                                
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editContactPersonModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    Contact Person Information
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Contact Person Name</div>
                                    <div class="fw-semibold">
                                        <?php 
                                        $contactFirstName = trim($creditor['org_contactPerson_firstName'] ?? '');
                                        $contactMiddleName = trim($creditor['org_contactPerson_middleName'] ?? '');
                                        $contactLastName = trim($creditor['org_contactPerson_lastName'] ?? '');
                                        $contactSuffix = trim($creditor['org_contactPerson_suffix'] ?? '');
                                        echo htmlspecialchars(trim($contactFirstName . ' ' . $contactMiddleName . ' ' . $contactLastName . ' ' . $contactSuffix));
                                        ?>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Address</div>
                                    <div>
                                        <?php 
                                        $contactAddress = trim($creditor['org_contactPerson_street'] ?? '') . ', ' . 
                                                       trim($creditor['org_contactPerson_baranggay'] ?? '') . ', ' . 
                                                       trim($creditor['org_contactPerson_city'] ?? '') . ', ' . 
                                                       trim($creditor['org_contactPerson_province'] ?? '');
                                        echo htmlspecialchars($contactAddress);
                                        ?>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-muted small mb-1">Contact Number</div>
                                    <div><?php echo htmlspecialchars($creditor['org_contactPerson_contactNumber'] ?? ''); ?></div>
                                </div>
                                <div>
                                    <div class="text-muted small mb-1">Email Address</div>
                                    <div><?php echo htmlspecialchars($creditor['org_contactPerson_email'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div id="credit-info-section" style="display:none;">
                <div class="credit-information">
                <div class="align-items-center d-flex justify-content-between mb-3">
                    <div class='d-flex justify-content-between align-items-center' style='width: 570px;'>
                        <div class="search-container">
                            <input type="text" name="search" id="search" placeholder="Search by Invoice Number" autocomplete="off">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                    </div>

                    <div>
                        <p class="text-muted mb-0">
                            Showing <span id="showing-count"><?php echo $startIndex + 1; ?>-<?php echo min($startIndex + $itemsPerPage, $totalSuppliers); ?></span>
                            of <span id="total-count"><?php echo $totalSuppliers; ?></span> suppliers
                        </p>
                    </div>
                </div>

                <!-- Table -->
                <div class="history">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr class='align-middle'>
                                <th>Invoice No.</th>
                                <th>Credit Date</th>
                                <th>Due Date</th>
                                <th>Days</th>
                                <th class="text-end">Interest (₱)</th>
                                <th class="text-end">Total Amount (₱)</th>
                                <th class="text-end">Balance (₱)</th>
                            </tr>
                        </thead>
                        <tbody id="transaction-tbody">
                            <!-- Sample data - will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Transaction pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Pagination will be generated by JavaScript -->
                    </ul>
                </nav>
                </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Organization Modal -->
    <?php if ($isOrganization): ?>
    <div class="modal fade" id="editOrganizationModal" tabindex="-1" aria-labelledby="editOrganizationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editOrganizationModalLabel">Edit Organization Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="update_creditor.php">
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo $creditorId; ?>">
                        <input type="hidden" name="edit_type" value="organization">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="org_name" class="form-label">Organization Name</label>
                                <input type="text" class="form-control" id="org_name" name="org_name" value="<?php echo htmlspecialchars($creditor['org_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="org_contactNumber" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="org_contactNumber" name="org_contactNumber" value="<?php echo htmlspecialchars($creditor['org_contactNumber'] ?? ''); ?>" placeholder="e.g., 09123456789 or 123-4567">
                                <div id="org_contactNumber_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="org_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="org_email" name="org_email" value="<?php echo htmlspecialchars($creditor['org_email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="org_province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="org_province" name="org_province" value="<?php echo htmlspecialchars($creditor['org_province'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="org_city" class="form-label">Municipality/City</label>
                                <input type="text" class="form-control" id="org_city" name="org_city" value="<?php echo htmlspecialchars($creditor['org_city'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="org_baranggay" class="form-label">Baranggay</label>
                                <input type="text" class="form-control" id="org_baranggay" name="org_baranggay" value="<?php echo htmlspecialchars($creditor['org_baranggay'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="org_street" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="org_street" name="org_street" value="<?php echo htmlspecialchars($creditor['org_street'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Contact Person Modal -->
    <div class="modal fade" id="editContactPersonModal" tabindex="-1" aria-labelledby="editContactPersonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editContactPersonModalLabel">Edit Contact Person Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="update_creditor.php">
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo $creditorId; ?>">
                        <input type="hidden" name="edit_type" value="contact_person">
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="org_contactPerson_firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="org_contactPerson_firstName" name="org_contactPerson_firstName" value="<?php echo htmlspecialchars($creditor['org_contactPerson_firstName'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="org_contactPerson_middleName" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="org_contactPerson_middleName" name="org_contactPerson_middleName" value="<?php echo htmlspecialchars($creditor['org_contactPerson_middleName'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="org_contactPerson_lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="org_contactPerson_lastName" name="org_contactPerson_lastName" value="<?php echo htmlspecialchars($creditor['org_contactPerson_lastName'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="org_contactPerson_suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="org_contactPerson_suffix" name="org_contactPerson_suffix" value="<?php echo htmlspecialchars($creditor['org_contactPerson_suffix'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <div id="edit_contact_person_name_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="org_contactPerson_contactNumber" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="org_contactPerson_contactNumber" name="org_contactPerson_contactNumber" value="<?php echo htmlspecialchars($creditor['org_contactPerson_contactNumber'] ?? ''); ?>" placeholder="e.g., 09123456789 or 123-4567">
                                <div id="org_contactPerson_contactNumber_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="org_contactPerson_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="org_contactPerson_email" name="org_contactPerson_email" value="<?php echo htmlspecialchars($creditor['org_contactPerson_email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="org_contactPerson_province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="org_contactPerson_province" name="org_contactPerson_province" value="<?php echo htmlspecialchars($creditor['org_contactPerson_province'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="org_contactPerson_city" class="form-label">Municipality/City</label>
                                <input type="text" class="form-control" id="org_contactPerson_city" name="org_contactPerson_city" value="<?php echo htmlspecialchars($creditor['org_contactPerson_city'] ?? ''); ?>">
                            </div>
                            
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="org_contactPerson_baranggay" class="form-label">Baranggay</label>
                                <input type="text" class="form-control" id="org_contactPerson_baranggay" name="org_contactPerson_baranggay" value="<?php echo htmlspecialchars($creditor['org_contactPerson_baranggay'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="org_contactPerson_street" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="org_contactPerson_street" name="org_contactPerson_street" value="<?php echo htmlspecialchars($creditor['org_contactPerson_street'] ?? ''); ?>">
                            </div>
                            
                        </div>
                        
                        
                        
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Individual Modal -->
    <?php if (!$isOrganization): ?>
    <div class="modal fade" id="editIndividualModal" tabindex="-1" aria-labelledby="editIndividualModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editIndividualModalLabel">Edit Individual Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="update_creditor.php">
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo $creditorId; ?>">
                        <input type="hidden" name="edit_type" value="individual">
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="creditor_fn" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="creditor_fn" name="creditor_fn" value="<?php echo htmlspecialchars($creditor['creditor_fn'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="creditor_mn" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="creditor_mn" name="creditor_mn" value="<?php echo htmlspecialchars($creditor['creditor_mn'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="creditor_ln" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="creditor_ln" name="creditor_ln" value="<?php echo htmlspecialchars($creditor['creditor_ln'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="creditor_suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="creditor_suffix" name="creditor_suffix" value="<?php echo htmlspecialchars($creditor['creditor_suffix'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <div id="edit_individual_name_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="creditor_contactNo" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="creditor_contactNo" name="creditor_contactNo" value="<?php echo htmlspecialchars($creditor['creditor_contactNo'] ?? ''); ?>" placeholder="e.g., 09123456789 or 123-4567">
                                <div id="creditor_contactNo_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="creditor_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="creditor_email" name="creditor_email" value="<?php echo htmlspecialchars($creditor['creditor_email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="creditor_province" class="form-label">Province</label>
                                <input type="text" class="form-control" id="creditor_province" name="creditor_province" value="<?php echo htmlspecialchars($creditor['creditor_province'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="creditor_city" class="form-label">Municipality/City</label>
                                <input type="text" class="form-control" id="creditor_city" name="creditor_city" value="<?php echo htmlspecialchars($creditor['creditor_city'] ?? ''); ?>">
                            </div>
                            
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="creditor_baranggay" class="form-label">Baranggay</label>
                                <input type="text" class="form-control" id="creditor_baranggay" name="creditor_baranggay" value="<?php echo htmlspecialchars($creditor['creditor_baranggay'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="creditor_street" class="form-label">Street Address</label>
                                <input type="text" class="form-control" id="creditor_street" name="creditor_street" value="<?php echo htmlspecialchars($creditor['creditor_street'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Phone number validation function
        function validatePhoneNumber(input, errorDivId) {
            const value = input.value.trim();
            const errorDiv = $(`#${errorDivId}`);
            
            // Clear previous error
            errorDiv.hide();
            input.classList.remove('is-invalid');
            
            // If empty, don't show error (unless required)
            if (value === '') {
                return true;
            }
            
            // Remove all non-digit and non-dash characters
            const cleanValue = value.replace(/[^\d-]/g, '');
            
            // Check if original value contains letters or invalid characters
            if (cleanValue !== value) {
                errorDiv.text('Contact number can only contain numbers and dashes').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check for consecutive dashes
            if (cleanValue.includes('--')) {
                errorDiv.text('Contact number cannot have consecutive dashes').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check if starts or ends with dash
            if (cleanValue.startsWith('-') || cleanValue.endsWith('-')) {
                errorDiv.text('Contact number cannot start or end with a dash').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            // Remove dashes for length check
            const digitsOnly = cleanValue.replace(/-/g, '');
            
            // Check if it's a valid phone number (7-11 digits)
            if (digitsOnly.length < 7 || digitsOnly.length > 11) {
                errorDiv.text('Contact number must be 7-11 digits').show();
                input.classList.add('is-invalid');
                return false;
            }
            
            // Check if it's all digits (for mobile numbers) or has proper format (for landlines)
            if (digitsOnly.length === 11) {
                // Mobile number should start with 09
                if (!digitsOnly.startsWith('09')) {
                    errorDiv.text('Mobile numbers should start with 09').show();
                    input.classList.add('is-invalid');
                    return false;
                }
            }
            
            return true;
        }
        
        // Name validation function - at least one of first name, last name, or nickname should be filled
        function validateNameFields(firstNameId, lastNameId, nicknameId, errorDivId) {
            const firstName = $(`#${firstNameId}`).val().trim();
            const lastName = $(`#${lastNameId}`).val().trim();
            const nickname = $(`#${nicknameId}`).val().trim();
            const errorDiv = $(`#${errorDivId}`);
            
            // Clear previous error
            errorDiv.hide();
            $(`#${firstNameId}`).removeClass('is-invalid');
            $(`#${lastNameId}`).removeClass('is-invalid');
            $(`#${nicknameId}`).removeClass('is-invalid');
            
            // Check if at least one field is filled
            if (firstName === '' && lastName === '' && nickname === '') {
                errorDiv.text('At least one of First Name, Last Name, or Nickname must be filled').show();
                $(`#${firstNameId}`).addClass('is-invalid');
                $(`#${lastNameId}`).addClass('is-invalid');
                $(`#${nicknameId}`).addClass('is-invalid');
                return false;
            }
            
            return true;
        }
        
        // Phone number validation event listeners
        $(document).ready(function() {
            // Organization contact number validation
            $('#org_contactNumber').on('input', function() {
                validatePhoneNumber(this, 'org_contactNumber_error');
            });
            
            $('#org_contactNumber').on('blur', function() {
                validatePhoneNumber(this, 'org_contactNumber_error');
            });
            
            // Organization contact person contact number validation
            $('#org_contactPerson_contactNumber').on('input', function() {
                validatePhoneNumber(this, 'org_contactPerson_contactNumber_error');
            });
            
            $('#org_contactPerson_contactNumber').on('blur', function() {
                validatePhoneNumber(this, 'org_contactPerson_contactNumber_error');
            });
            
            // Individual creditor contact number validation
            $('#creditor_contactNo').on('input', function() {
                validatePhoneNumber(this, 'creditor_contactNo_error');
            });
            
            $('#creditor_contactNo').on('blur', function() {
                validatePhoneNumber(this, 'creditor_contactNo_error');
            });
            
            // Name validation event listeners for edit modals
            $('#org_contactPerson_firstName, #org_contactPerson_lastName, #org_contactPerson_nickname').on('input', function() {
                validateNameFields('org_contactPerson_firstName', 'org_contactPerson_lastName', 'org_contactPerson_nickname', 'edit_contact_person_name_error');
            });
            
            $('#creditor_fn, #creditor_ln, #creditor_nickname').on('input', function() {
                validateNameFields('creditor_fn', 'creditor_ln', 'creditor_nickname', 'edit_individual_name_error');
            });
            
            // Form submission validation
            $('form[action="update_creditor.php"]').on('submit', function(e) {
                const form = $(this);
                let isValid = true;
                
                // Check if organization contact number exists and validate
                if (form.find('#org_contactNumber').length > 0) {
                    if (!validatePhoneNumber(document.getElementById('org_contactNumber'), 'org_contactNumber_error')) {
                        isValid = false;
                    }
                }
                
                // Check if organization contact person contact number exists and validate
                if (form.find('#org_contactPerson_contactNumber').length > 0) {
                    if (!validatePhoneNumber(document.getElementById('org_contactPerson_contactNumber'), 'org_contactPerson_contactNumber_error')) {
                        isValid = false;
                    }
                }
                
                // Check if individual creditor contact number exists and validate
                if (form.find('#creditor_contactNo').length > 0) {
                    if (!validatePhoneNumber(document.getElementById('creditor_contactNo'), 'creditor_contactNo_error')) {
                        isValid = false;
                    }
                }
                
                // Validate name fields for contact person
                if (form.find('#org_contactPerson_firstName').length > 0) {
                    if (!validateNameFields('org_contactPerson_firstName', 'org_contactPerson_lastName', 'org_contactPerson_nickname', 'edit_contact_person_name_error')) {
                        isValid = false;
                    }
                }
                
                // Validate name fields for individual creditor
                if (form.find('#creditor_fn').length > 0) {
                    if (!validateNameFields('creditor_fn', 'creditor_ln', 'creditor_nickname', 'edit_individual_name_error')) {
                        isValid = false;
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        
        document.getElementById('creditor-tab').onclick = function() {
            document.getElementById('creditor-info-section').style.display = '';
            document.getElementById('credit-info-section').style.display = 'none';
            this.classList.add('active-tab');
            document.getElementById('credit-tab').classList.remove('active-tab');
        };

        document.getElementById('credit-tab').onclick = function() {
            document.getElementById('creditor-info-section').style.display = 'none';
            document.getElementById('credit-info-section').style.display = '';
            this.classList.add('active-tab');
            document.getElementById('creditor-tab').classList.remove('active-tab');
        };

// --- Credit Table Pagination Logic ---
const creditData = <?php echo json_encode(array_map(function($row) {
    // Calculate days
    $creditDate = $row['transaction_date'];
    $dueDate = $row['due_date'];
    $days = '';
    if ($creditDate && $dueDate) {
        $dt1 = new DateTime($creditDate);
        $dt2 = new DateTime($dueDate);
        $days = $dt1->diff($dt2)->days;
    }
    return [
        'invoice' => $row['invoice_no'],
        'creditDate' => $row['transaction_date'],
        'dueDate' => $row['due_date'],
        'days' => $days,
        'interest' => (float)$row['interest'],
        'total' => (float)$row['total_with_interest'],
        'balance' => (float)$row['balance'],
    ];
}, $creditTransactions)); ?>;
let creditCurrentPage = 1;
const creditItemsPerPage = 3;
let filteredCreditData = [...creditData];

function renderCreditTable() {
  const tbody = document.getElementById('transaction-tbody');
  const startIndex = (creditCurrentPage - 1) * creditItemsPerPage;
  const endIndex = startIndex + creditItemsPerPage;
  const pageData = filteredCreditData.slice(startIndex, endIndex);
  tbody.innerHTML = '';
  
  function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
  }
  
  pageData.forEach(row => {
    const tr = document.createElement('tr');
    tr.className = 'align-middle';
    tr.style.cursor = 'pointer';
    tr.onclick = () => viewInvoiceDetails(row.invoice);
    tr.innerHTML = `
      <td>${row.invoice}</td>
      <td>${formatDate(row.creditDate)}</td>
      <td>${formatDate(row.dueDate)}</td>
      <td>${row.days}</td>
      <td class="text-end">${row.interest.toFixed(2)}</td>
      <td class="text-end">${parseFloat(row.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
      <td class="text-end">${parseFloat(row.balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
    `;
    tbody.appendChild(tr);
  });
  updateCreditResultsSummary();
}

function renderCreditPagination() {
  const pagination = document.getElementById('pagination');
  const totalPages = Math.ceil(filteredCreditData.length / creditItemsPerPage);
  pagination.innerHTML = '';
  if (totalPages <= 1) return;
  // Previous button
  const prevLi = document.createElement('li');
  prevLi.className = `page-item ${creditCurrentPage === 1 ? 'disabled' : ''}`;
  prevLi.innerHTML = `
    <a class="page-link" href="#" onclick="changeCreditPage(${creditCurrentPage - 1}); return false;">
      <i class="fas fa-chevron-left"></i>
    </a>
  `;
  pagination.appendChild(prevLi);
  // Page numbers
    const pageGroup = Math.ceil(creditCurrentPage / 5);
    const startPage = (pageGroup - 1) * 5 + 1;
    const endPage = Math.min(startPage + 4, totalPages);
    for (let i = startPage; i <= endPage; i++) {
    const li = document.createElement('li');
    li.className = `page-item ${i === creditCurrentPage ? 'active' : ''}`;
    li.innerHTML = `<a class="page-link" href="#" onclick="changeCreditPage(${i}); return false;">${i}</a>`;
    pagination.appendChild(li);
  }
  // Next button
  const nextLi = document.createElement('li');
  nextLi.className = `page-item ${creditCurrentPage === totalPages ? 'disabled' : ''}`;
  nextLi.innerHTML = `
    <a class="page-link" href="#" onclick="changeCreditPage(${creditCurrentPage + 1}); return false;">
      <i class="fas fa-chevron-right"></i>
    </a>
  `;
  pagination.appendChild(nextLi);
}

function changeCreditPage(page) {
  const totalPages = Math.ceil(filteredCreditData.length / creditItemsPerPage);
  if (page >= 1 && page <= totalPages) {
    creditCurrentPage = page;
    renderCreditTable();
    renderCreditPagination();
  }
}

function updateCreditResultsSummary() {
  const startIndex = (creditCurrentPage - 1) * creditItemsPerPage + 1;
  const endIndex = Math.min(creditCurrentPage * creditItemsPerPage, filteredCreditData.length);
  const totalCount = filteredCreditData.length;
  document.getElementById('showing-count').textContent = `${startIndex}-${endIndex}`;
  document.getElementById('total-count').textContent = totalCount;
}

function viewInvoiceDetails(invoiceNumber) {
  // Pass creditor_id for context
  window.location.href = `creditor_invoice_details.php?invoice=${invoiceNumber}&creditor_id=<?php echo $creditorId; ?>`;
}

// Initial render
renderCreditTable();
renderCreditPagination();
</script>
</body>
</html>