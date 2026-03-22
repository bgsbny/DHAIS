<?php
// Include database connection
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filtering
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(org_name LIKE ? OR CONCAT(creditor_fn, ' ', creditor_mn, ' ', creditor_ln) LIKE ? OR CONCAT(creditor_fn, ' ', creditor_ln) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM tbl_creditors $whereClause";
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

// Get creditors with pagination
$query = "SELECT 
    c.creditor_id, 
    c.org_name, 
    c.creditor_fn, 
    c.creditor_mn, 
    c.creditor_ln, 
    c.creditor_suffix,
    COALESCE((
        SELECT SUM(total_with_interest) 
        FROM tbl_credit_transactions 
        WHERE creditor_id = c.creditor_id
    ), 0) AS total_receivable,
    COALESCE((
        SELECT SUM(amount_paid) 
        FROM tbl_credit_payments 
        WHERE credit_id IN (
            SELECT credit_id FROM tbl_credit_transactions WHERE creditor_id = c.creditor_id
        )
    ), 0) AS total_collected
FROM tbl_creditors c
$whereClause
GROUP BY c.creditor_id, c.org_name, c.creditor_fn, c.creditor_mn, c.creditor_ln, c.creditor_suffix
ORDER BY CASE WHEN c.org_name IS NOT NULL AND c.org_name != '' THEN c.org_name ELSE CONCAT(TRIM(c.creditor_fn), ' ', TRIM(c.creditor_mn), ' ', TRIM(c.creditor_ln)) END ASC
LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($query);
// Add pagination parameters
$allParams = array_merge($params, [$itemsPerPage, $offset]);
$bindTypes = str_repeat('s', count($params)) . 'ii';
$stmt->bind_param($bindTypes, ...$allParams);
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
    
    <style>
        /* Search styling */
        .search-container {
            position: relative;
        }
        
        .search-container input {
            padding-right: 40px;
        }
        
        .search-container i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }
    </style>

    <title>Credit & Collection</title>
</head>
<body>
    <?php $activePage = 'creditor_list'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Credit & Collection</h1>
                    <p class="page-subtitle">Manage credit transactions, track payments, and monitor outstanding balances.</p>
                </div>
            </div>
        </header>

        <div class="main-container">

        <div class="align-items-center d-flex justify-content-between mb-3">
                    <div class='d-flex justify-content-between align-items-center' style='width: 570px;'>
                        <div class="search-container">
                            <input type="text" name="search" id="search" placeholder="Search by Organization Name and Creditor Names" autocomplete="off">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <div>
                            <button type='button' class='btn btn-outline-primary btn-sm' data-bs-toggle="modal" data-bs-target="#addCreditorModal">
                                <i class="fa-solid fa-plus"></i>
                                Add New Creditor
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-muted mb-0">
                            Showing <span id="showing-count"><?php echo $startItem; ?>-<?php echo $endItem; ?></span> of <span id="total-count"><?php echo $totalCount; ?></span> creditors
                        </p>
                    </div>
                </div>

            <!-- Table -->
            <div class="history">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr class='align-middle'>
                                <th>Creditor</th>
                                <th class='text-end'>Total Receivable (₱)</th>
                                <th class='text-end'>Collected (₱)</th>
                                <th class='text-end'>Balance (₱)</th>
                            </tr>
                        </thead>
                        <tbody id="creditor-tbody">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                    // Determine the name to display
                                    $displayName = '';
                                    if (!empty($row['org_name'])) {
                                        // Organization creditor
                                        $displayName = htmlspecialchars($row['org_name']);
                                    } else {
                                        // Individual creditor
                                        $firstName = trim($row['creditor_fn'] ?? '');
                                        $middleName = trim($row['creditor_mn'] ?? '');
                                        $lastName = trim($row['creditor_ln'] ?? '');
                                        $suffix = trim($row['creditor_suffix'] ?? '');
                                        
                                        $displayName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
                                        $displayName = htmlspecialchars($displayName);
                                    }
                                    $totalReceivable = (float)$row['total_receivable'];
                                    $totalCollected = (float)$row['total_collected'];
                                    $balance = $totalReceivable - $totalCollected;
                                    ?>
                                    <tr class="align-middle" style="cursor: pointer;" onclick="viewCreditorDetails(<?php echo htmlspecialchars($row['creditor_id']); ?>)">
                                        <td><span class="customer-name"><?php echo $displayName; ?></span></td>
                                        <td class="text-end"><span class="amount"><?php echo number_format($totalReceivable, 2); ?></span></td>
                                        <td class="text-end"><span class="amount"><?php echo number_format($totalCollected, 2); ?></span></td>
                                        <td class="text-end"><span class="amount"><?php echo number_format($balance, 2); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No creditors found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Creditor pagination" class="mt-4">
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

    <!-- Add New Creditor Modal -->
    <div class="modal fade" id="addCreditorModal" tabindex="-1" aria-labelledby="addCreditorModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCreditorModalLabel"><i class="fas fa-user-plus me-2"></i>Add New Creditor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addCreditorForm" autocomplete="off">
                        <div class="modal-body">
                            <div class='mb-3'>
                                <label for="creditorType" class='form-label'>Creditor Type</label>
                                <select id="creditorType" class='form-select'>
                                    <option value="Individual">Individual</option>
                                    <option value="Organization">Organization</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Taxpayer Identification Number (TIN)</label>
                                <input type="text" name='tin' id='tin' class="form-control">
                            </div>

                            <!-- Individual Fields -->
                            <div id="individualFields" class='row g-3'>
                                <div class="col-md-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" id='creditor_fn' name='creditor_fn' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" id='creditor_mn' name='creditor_mn' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" id='creditor_ln' name='creditor_ln' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Suffix</label>
                                    <input type="text" id='creditor_suffix' name='creditor_suffix' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Nickname</label>
                                    <input type="text" id='creditor_nickname' name='creditor_nickname' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" id='creditor_contactNo' name='creditor_contactNo' class="form-control" placeholder="e.g., 09123456789 or 123-4567">
                                    <div id="creditor_contactNo_error" class="text-danger small mt-1" style="display: none;"></div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" id='creditor_email' name='creditor_email' class="form-control">
                                </div>
                                
                                <div class="col-md-12">
                                    <div id="individual_name_error" class="text-danger small mt-1" style="display: none;"></div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Province</label>
                                    <select name="creditor_province" id="creditor_province" class='form-select'>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">City/Municipality</label>
                                    <select name="creditor_city" id="creditor_city" class='form-select'>
                                        <option value="">Select City/Municipality</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Baranggay</label>
                                    <select name="creditor_baranggay" id="creditor_baranggay" class='form-select'>
                                        <option value="">Select Baranggay</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Street</label>
                                    <input type="text" id='creditor_street' name='creditor_street' class="form-control">
                                </div>
                            </div>

                            <!-- Organization Fields -->
                            <div id="organizationFields" class='row g-3' style="display:none;">
                                <div class="col-md-12">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" id='org_name' name='org_name' class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" id='org_contactNumber' name='org_contactNumber' class="form-control" placeholder="e.g., 09123456789 or 123-4567">
                                    <div id="org_contactNumber_error" class="text-danger small mt-1" style="display: none;"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" id='org_email' name='org_email' class="form-control">
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Province</label>
                                    <select name="org_province" id="org_province" class='form-select'>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">City/Municipality</label>
                                    <select name="org_city" id="org_city" class='form-select'>
                                        <option value="">Select City/Municipality</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Baranggay</label>
                                    <select name="org_baranggay" id="org_baranggay" class='form-select'>
                                        <option value="">Select Baranggay</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Street</label>
                                    <input type="text" id='org_street' name='org_street' class="form-control">
                                </div>

                                <hr>

                                <div class="col-md-12 fw-bold">Contact Person</div>
                                <div class="col-md-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" id='org_contactPerson_firstName' name='org_contactPerson_firstName' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" id='org_contactPerson_middleName' name='org_contactPerson_middleName' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" id='org_contactPerson_lastName' name='org_contactPerson_lastName' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Suffix</label>
                                    <input type="text" id='org_contactPerson_suffix' name='org_contactPerson_suffix' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Nickname</label>
                                    <input type="text" id='org_contactPerson_nickname' name='org_contactPerson_nickname' class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" id='org_contactPerson_contactNumber' name='org_contactPerson_contactNumber' class="form-control" placeholder="e.g., 09123456789 or 123-4567">
                                    <div id="org_contactPerson_contactNumber_error" class="text-danger small mt-1" style="display: none;"></div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" id='org_contactPerson_email' name='org_contactPerson_email' class="form-control">
                                </div>
                                
                                <div class="col-md-12">
                                    <div id="contact_person_name_error" class="text-danger small mt-1" style="display: none;"></div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Province</label>
                                    <select name="org_contactPerson_province" id="org_contactPerson_province" class='form-select'>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">City/Municipality</label>
                                    <select name="org_contactPerson_city" id="org_contactPerson_city" class='form-select'>
                                        <option value="">Select City/Municipality</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Baranggay</label>
                                    <select name="org_contactPerson_baranggay" id="org_contactPerson_baranggay" class='form-select'>
                                        <option value="">Select Baranggay</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Street</label>
                                    <input type="text" id='org_contactPerson_street' name='org_contactPerson_street' class="form-control">
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Creditor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="qtyWarningPopup"></div>
        <div id="creditorMessagePopup"></div>

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
    
    // Make this function globally accessible
    function showCreditorMessagePopup(message, isSuccess = true) {
        const $popup = $("#creditorMessagePopup");
        $popup.stop(true, true); // Stop any current animations
        $popup.text(message);
        $popup.removeClass("popup-success popup-error");
        $popup.addClass(isSuccess ? "popup-success" : "popup-error");
        $popup.fadeIn(200).delay(1800).fadeOut(400);
    }

            document.addEventListener('DOMContentLoaded', function() {
                const typeSelect = document.getElementById('creditorType');
                const individualFields = document.getElementById('individualFields');
                const organizationFields = document.getElementById('organizationFields');
                function toggleCreditorFields() {
                    if (typeSelect.value === 'Individual') {
                        individualFields.style.display = '';
                        organizationFields.style.display = 'none';
                    } else {
                        individualFields.style.display = 'none';
                        organizationFields.style.display = '';
                    }
                }
                typeSelect.addEventListener('change', toggleCreditorFields);
                toggleCreditorFields();
            });
        </script>

        <!-- Add Creditor Form Submission -->
        <script>
            $(function() {
                // Phone number validation event listeners
                $('#creditor_contactNo').on('input', function() {
                    validatePhoneNumber(this, 'creditor_contactNo_error');
                });
                
                $('#creditor_contactNo').on('blur', function() {
                    validatePhoneNumber(this, 'creditor_contactNo_error');
                });
                
                $('#org_contactNumber').on('input', function() {
                    validatePhoneNumber(this, 'org_contactNumber_error');
                });
                
                $('#org_contactNumber').on('blur', function() {
                    validatePhoneNumber(this, 'org_contactNumber_error');
                });
                
                $('#org_contactPerson_contactNumber').on('input', function() {
                    validatePhoneNumber(this, 'org_contactPerson_contactNumber_error');
                });
                
                $('#org_contactPerson_contactNumber').on('blur', function() {
                    validatePhoneNumber(this, 'org_contactPerson_contactNumber_error');
                });
                
                // Name validation event listeners for individual fields
                $('#creditor_fn, #creditor_ln, #creditor_nickname').on('input', function() {
                    validateNameFields('creditor_fn', 'creditor_ln', 'creditor_nickname', 'individual_name_error');
                });
                
                $('#org_contactPerson_firstName, #org_contactPerson_lastName, #org_contactPerson_nickname').on('input', function() {
                    validateNameFields('org_contactPerson_firstName', 'org_contactPerson_lastName', 'org_contactPerson_nickname', 'contact_person_name_error');
                });
                
                $('#addCreditorForm').on('submit', function(e) {
                    e.preventDefault();
                    var $form = $(this);
                    
                    // Validate phone numbers before submission
                    const creditorContactValid = validatePhoneNumber(document.getElementById('creditor_contactNo'), 'creditor_contactNo_error');
                    const orgContactValid = validatePhoneNumber(document.getElementById('org_contactNumber'), 'org_contactNumber_error');
                    const orgContactPersonValid = validatePhoneNumber(document.getElementById('org_contactPerson_contactNumber'), 'org_contactPerson_contactNumber_error');
                    
                    // Validate name fields
                    const individualNameValid = validateNameFields('creditor_fn', 'creditor_ln', 'creditor_nickname', 'individual_name_error');
                    const contactPersonNameValid = validateNameFields('org_contactPerson_firstName', 'org_contactPerson_lastName', 'org_contactPerson_nickname', 'contact_person_name_error');
                    
                    if (!creditorContactValid || !orgContactValid || !orgContactPersonValid || !individualNameValid || !contactPersonNameValid) {
                        return; // Stop submission if validation fails
                    }
                    
                    var formData = $form.serialize();
                    $.ajax({
                        url: 'add_creditor.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $form[0].reset();
                                $('#addCreditorModal').modal('hide');
                                showCreditorMessagePopup('Creditor added successfully!', true);
                                // Reload the page after a short delay to show the new creditor
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                showCreditorMessagePopup('Error: ' + (response.error || 'Unknown error'), false);
                            }
                        },
                        error: function() {
                            showCreditorMessagePopup('An error occurred while adding the creditor.', false);
                        }
                    });
                });
            });

            // Philippine address data (reuse from supplier.php)
    const phAddressData = {
    'Abra': {
        'Bangued': ['Agtangao', 'Angad', 'Bangbangar', 'Banacao', 'Cabuloan', 'Calaba', 'Cosili East', 'Cosili West', 'Dangdangla', 'Lingtan', 'Lipcan', 'Lubong', 'Macarcarmay', 'Macray', 'Malita', 'Maoay', 'Palao', 'Patucannay', 'Sagap', 'San Antonio', 'Santa Rosa', 'Sao-atan', 'Sappaac', 'Tablac', 'Zone 1 Poblacion', 'Zone 2 Poblacion', 'Zone 3 Poblacion', 'Zone 4 Poblacion', 'Zone 5 Poblacion', 'Zone 6 Poblacion', 'Zone 7 Poblacion'],

        'Bolinay': ['Amti', 'Bao-yan', 'Danac East', 'Danac West', 'Dao-angan', 'Dumagas', 'Kilong-Olao', 'Poblacion'],
        'Bucay': ['Abang', 'Bangbangcag', 'Bangcagan', 'Banglolao', 'Bugbog', 'Calao', 'Dugong', 'Labon', 'Layugan', 'Madalipay', 'North Poblacion', 'Pagala', 'Pakiling', 'Palaquio', 'Patoc', 'Quimloong', 'Salnec', 'San Miguel', 'Siblong', 'South Poblacion', 'Tabiog', 'Duclingan', 'Labaan', 'Lamao', 'Lingay'],

        'Daguioma': ['Ableg', 'Cabaruyan', 'Pikek', 'Tui'],

        'Danglas': [], 
        'Dolores': [], 
        'La Paz': [], 'Lacub': [], 
        'Lagangilag': [], 
        'Lagayan': [], 
        'Langiden': [], 
        'Licuan-Baay': [], 
        'Luba': [], 
        'Malibcong': [], 
        'Manabo': [], 
        'Penarrubia': [], 
        'Pidigan': [], 
        'Sailapadan': [], 
        'San Isidro': [], 
        'San Juan': [], 
        'San Quintin': [], 
        'Tayum': [], 
        'Tineg': [], 
        'Tubo': [], 
        'Villaviciosa': []
    },

    'Metro Manila': {
        'Quezon City': ['Bagong Pag-asa', 'Batasan Hills', 'Commonwealth'],
        'Manila': ['Ermita', 'Malate', 'Paco'],
        'Makati': ['Bel-Air', 'Poblacion', 'San Lorenzo']
    },

    'Cebu': {
        'Cebu City': ['Lahug', 'Mabolo', 'Banilad'],
        'Mandaue City': ['Alang-Alang', 'Bakilid', 'Banilad']
    },

    'Davao del Sur': {
        'Davao City': ['Buhangin', 'Talomo', 'Agdao']
    },

    'Surigao Del Sur': {
        'Barobo': ['Amaga', 'Bahi', 'Cabacungan', 'Cambagang', 'Causwagan', 'Dapdap', 'Dughan', 'Gamut', 'Javier', 'Kinayan', 'Mamis', 'Poblacion', 'Rizal', 'San Jose', 'San Roque', 'San Vicente', 'Sua', 'Sudlon', 'Tambis', 'Unidad', 'Wakat'],

        'Bayabas': ['Amag', 'Balete', 'Cabugo', 'Cagbaoto', 'La Paz', 'Magobawok', 'Panaosawon'],

        'Bislig City': ['Bucto', 'Burboanan', 'Caguyao', 'Coleto', 'Comawas', 'Kahayag', 'Labisma', 'Lawigan', 'Maharlika', 'Mangagoy', 'Mone', 'Pamanlinan', 'Pamaypayan', 'Poblacion', 'San Antonio', 'San Fernando', 'San Isidro', 'San Jose', 'San Roque', 'San Vicente', 'San Cruz', 'Sibaroy', 'Tabon', 'Tumanan'],

        'Cagwait': [], 
        'Cantilan': [], 
        'Carmen': [], 
        'Carrascal': [], 
        'Cortes': [], 
        'Hinatuan': [], 
        'Lanuza': [], 
        'Lianga': [], 
        'Lingig': [], 
        'Madrid': [], 
        'San Agustin': [], 
        'San Miguel': [], 
        'Tagbina': [], 
        'Tago': [], 
        'Tandag': []
    }
};

function populateProvinces(provinceSelect) {
    provinceSelect.innerHTML = '<option value="">Select Province</option>';
    Object.keys(phAddressData).forEach(province => {
        provinceSelect.innerHTML += `<option value="${province}">${province}</option>`;
    });
}

function populateCities(provinceSelect, citySelect, barangaySelect) {
    const province = provinceSelect.value;
    citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    if (province && phAddressData[province]) {
        Object.keys(phAddressData[province]).forEach(city => {
            citySelect.innerHTML += `<option value="${city}">${city}</option>`;
        });
    }
}

function populateBarangays(provinceSelect, citySelect, barangaySelect) {
    const province = provinceSelect.value;
    const city = citySelect.value;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    if (province && city && phAddressData[province] && phAddressData[province][city]) {
        phAddressData[province][city].forEach(brgy => {
            barangaySelect.innerHTML += `<option value="${brgy}">${brgy}</option>`;
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Individual address
    const creditorProvince = document.getElementById('creditor_province');
    const creditorCity = document.getElementById('creditor_city');
    const creditorBarangay = document.getElementById('creditor_baranggay');
    if (creditorProvince && creditorCity && creditorBarangay) {
        populateProvinces(creditorProvince);
        creditorProvince.addEventListener('change', function() {
            populateCities(creditorProvince, creditorCity, creditorBarangay);
        });
        creditorCity.addEventListener('change', function() {
            populateBarangays(creditorProvince, creditorCity, creditorBarangay);
        });
    }
    // Organization address
    const orgProvince = document.getElementById('org_province');
    const orgCity = document.getElementById('org_city');
    const orgBarangay = document.getElementById('org_baranggay');
    if (orgProvince && orgCity && orgBarangay) {
        populateProvinces(orgProvince);
        orgProvince.addEventListener('change', function() {
            populateCities(orgProvince, orgCity, orgBarangay);
        });
        orgCity.addEventListener('change', function() {
            populateBarangays(orgProvince, orgCity, orgBarangay);
        });
    }
    // Organization contact person address
    const orgCPProvince = document.getElementById('org_contactPerson_province');
    const orgCPCity = document.getElementById('org_contactPerson_city');
    const orgCPBarangay = document.getElementById('org_contactPerson_baranggay');
    if (orgCPProvince && orgCPCity && orgCPBarangay) {
        populateProvinces(orgCPProvince);
        orgCPProvince.addEventListener('change', function() {
            populateCities(orgCPProvince, orgCPCity, orgCPBarangay);
        });
        orgCPCity.addEventListener('change', function() {
            populateBarangays(orgCPProvince, orgCPCity, orgCPBarangay);
        });
    }
});
        </script>

        

    <script>
    // JavaScript for pagination functionality
    function changePage(page) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('page', page);
        window.location.search = urlParams.toString();
    }

    function addCustomer() {
        // Implement add customer functionality
        alert('Add Customer functionality will be implemented here');
    }

    function viewCreditorDetails(id) {
        // Navigate to creditor details page
        window.location.href = `creditor_details.php?id=${id}`;
    }
    
    // Search functionality
    $(document).ready(function() {
        let searchTimeout;
        
        $('#search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // If search is empty, show original pagination
            if (searchTerm === '') {
                location.reload();
                return;
            }
            
            // Set timeout to avoid too many requests
            searchTimeout = setTimeout(() => {
                performSearch(searchTerm);
            }, 300);
        });
        
        // Auto-focus on search input when page loads
        $('#search').focus();
        
        // Keep focus on search input after search results are displayed
        function keepSearchFocus() {
            setTimeout(() => {
                $('#search').focus();
            }, 100);
        }
        
        // Ensure search input maintains focus
        $('#search').on('blur', function() {
            // Only refocus if there's text in the search box
            if ($(this).val().trim() !== '') {
                setTimeout(() => {
                    $(this).focus();
                }, 50);
            }
        });
        
        function performSearch(searchTerm) {
            console.log('Performing search for:', searchTerm);
            
            // Show loading state
            const $tbody = $('#creditor-tbody');
            $tbody.html('<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</td></tr>');
            
            // Fetch search results from server
            $.ajax({
                url: 'search_creditors.php',
                type: 'GET',
                data: { term: searchTerm },
                dataType: 'json',
                success: function(data) {
                    console.log('Search results:', data);
                    displaySearchResults(data, searchTerm);
                },
                error: function(xhr, status, error) {
                    console.error('Search error:', error);
                    console.error('Response text:', xhr.responseText);
                    console.error('Status:', xhr.status);
                    $tbody.html(`
                        <tr class="no-results-row">
                            <td colspan="4" class="no-results">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error loading search results
                            </td>
                        </tr>
                    `);
                    updateCountDisplay(0, 0);
                }
            });
        }
        
        function displaySearchResults(creditors, searchTerm) {
            const $tbody = $('#creditor-tbody');
            $tbody.empty();
            
            if (creditors.length === 0) {
                $tbody.html(`
                    <tr class="no-results-row">
                        <td colspan="4" class="no-results">
                            <i class="fas fa-search me-2"></i>
                            No creditors found matching "${searchTerm}"
                        </td>
                    </tr>
                `);
                updateCountDisplay(0, 0);
                return;
            }
            
            // Sort creditors alphabetically
            creditors.sort((a, b) => a.display_name.localeCompare(b.display_name));
            
            // Display search results
            creditors.forEach(creditor => {
                const row = `
                    <tr class="align-middle" style="cursor:pointer;" onclick="viewCreditorDetails(${creditor.creditor_id})">
                        <td><span class="customer-name">${creditor.display_name}</span></td>
                        <td class="text-end"><span class="amount">₱${parseFloat(creditor.total_receivable).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span></td>
                        <td class="text-end"><span class="amount">₱${parseFloat(creditor.total_collected).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span></td>
                        <td class="text-end"><span class="amount">₱${parseFloat(creditor.balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span></td>
                    </tr>
                `;
                $tbody.append(row);
            });
            
            updateCountDisplay(creditors.length, creditors.length);
            
            // Keep focus on search input after displaying results
            keepSearchFocus();
        }
        
        function updateCountDisplay(visibleCount, totalCount) {
            const searchTerm = $('#search').val().trim();
            
            if (searchTerm === '') {
                // Show original pagination info when no search
                $('#showing-count').text('<?php echo $startItem; ?>-<?php echo $endItem; ?>');
                $('#total-count').text('<?php echo $totalCount; ?>');
            } else {
                // Show filtered results count
                if (visibleCount === 0) {
                    $('#showing-count').text('0');
                    $('#total-count').text('0');
                } else {
                    $('#showing-count').text(`1-${visibleCount}`);
                    $('#total-count').text(visibleCount);
                }
            }
        }
    });
    </script>
</body>
</html>