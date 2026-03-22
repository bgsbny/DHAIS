<?php
// Start output buffering to prevent any unwanted output
ob_start();

include('mycon.php');
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle supplier update if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supplier') {
    // Clear any previous output
    ob_clean();
    
    // Set JSON header for AJAX response
    header('Content-Type: application/json');
    
    // Debug: Test if basic JSON response works
    if (isset($_POST['test']) && $_POST['test'] === 'true') {
        echo json_encode(['success' => true, 'message' => 'Test response works']);
        exit();
    }
    
    try {
        // Get form data
        $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
        $supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
        $supplier_number = isset($_POST['supplier_number']) ? trim($_POST['supplier_number']) : '';
        $supplier_email = isset($_POST['supplier_email']) ? trim($_POST['supplier_email']) : '';
        $supplier_province = isset($_POST['supplier_province']) ? trim($_POST['supplier_province']) : '';
        $supplier_city = isset($_POST['supplier_city']) ? trim($_POST['supplier_city']) : '';
        $supplier_baranggay = isset($_POST['supplier_baranggay']) ? trim($_POST['supplier_baranggay']) : '';
        $supplier_street = isset($_POST['supplier_street']) ? trim($_POST['supplier_street']) : '';
        
        // Contact person fields
        $contact_fn = isset($_POST['contact_fn']) ? trim($_POST['contact_fn']) : '';
        $contact_mn = isset($_POST['contact_mn']) ? trim($_POST['contact_mn']) : '';
        $contact_ln = isset($_POST['contact_ln']) ? trim($_POST['contact_ln']) : '';
        $contact_suffix = isset($_POST['contact_suffix']) ? trim($_POST['contact_suffix']) : '';
        $contact_nickname = isset($_POST['contact_nickname']) ? trim($_POST['contact_nickname']) : '';
        $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
        $contact_email = isset($_POST['contact_email']) ? trim($_POST['contact_email']) : '';
        $contact_province = isset($_POST['contact_province']) ? trim($_POST['contact_province']) : '';
        $contact_city = isset($_POST['contact_city']) ? trim($_POST['contact_city']) : '';
        $contact_baranggay = isset($_POST['contact_baranggay']) ? trim($_POST['contact_baranggay']) : '';
        $contact_street = isset($_POST['contact_street']) ? trim($_POST['contact_street']) : '';

        // Debug: Log the received data
        error_log("Update supplier request - ID: $supplier_id, Name: $supplier_name, Number: $supplier_number");

        // Validation
        if ($supplier_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
            exit();
        }

        if (empty($supplier_name)) {
            echo json_encode(['success' => false, 'error' => 'Supplier name is required']);
            exit();
        }

        if (empty($supplier_number)) {
            echo json_encode(['success' => false, 'error' => 'Contact number is required']);
            exit();
        }

        // Check if supplier exists
        $check_query = "SELECT supplier_id FROM tbl_supplier WHERE supplier_id = ?";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param('i', $supplier_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Supplier not found']);
            exit();
        }
        $check_stmt->close();

        // Update supplier information
        $update_query = "UPDATE tbl_supplier SET 
            supplier_name = ?, 
            supplier_number = ?, 
            supplier_email = ?, 
            supplier_province = ?, 
            supplier_city = ?, 
            supplier_baranggay = ?, 
            supplier_street = ?,
            contact_fn = ?,
            contact_mn = ?,
            contact_ln = ?,
            contact_suffix = ?,
            contact_nickname = ?,
            contact_number = ?,
            contact_email = ?,
            contact_province = ?,
            contact_city = ?,
            contact_baranggay = ?,
            contact_street = ?
            WHERE supplier_id = ?";

        $update_stmt = $mysqli->prepare($update_query);
        $update_stmt->bind_param('ssssssssssssssssssi', 
            $supplier_name, 
            $supplier_number, 
            $supplier_email, 
            $supplier_province, 
            $supplier_city, 
            $supplier_baranggay, 
            $supplier_street,
            $contact_fn,
            $contact_mn,
            $contact_ln,
            $contact_suffix,
            $contact_nickname,
            $contact_number,
            $contact_email,
            $contact_province,
            $contact_city,
            $contact_baranggay,
            $contact_street,
            $supplier_id
        );

        if ($update_stmt->execute()) {
            error_log("Supplier updated successfully - ID: $supplier_id");
            echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
        } else {
            error_log("Failed to update supplier - Error: " . $mysqli->error);
            echo json_encode(['success' => false, 'error' => 'Failed to update supplier: ' . $mysqli->error]);
        }

        $update_stmt->close();
        exit();

    } catch (Exception $e) {
        error_log("Exception in supplier update: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}

// Fetch all suppliers from tbl_supplier
$supplier_query = "SELECT * FROM tbl_supplier ORDER BY supplier_name ASC";
$supplier_result = $mysqli->query($supplier_query);
$suppliers = [];
while ($row = $supplier_result->fetch_assoc()) {
    $suppliers[] = $row;
}
// Pagination setup
$itemsPerPage = 5;
$totalSuppliers = count($suppliers);
$totalPages = ceil($totalSuppliers / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$startIndex = ($currentPage - 1) * $itemsPerPage;
$displayedSuppliers = array_slice($suppliers, $startIndex, $itemsPerPage);
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
        #supplierMessagePopup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2100;
            min-width: 250px;
            max-width: 90vw;
            background: #e6ffed;
            color: #155724;
            border: 1px solid #b7f5c2;
            border-radius: 8px;
            padding: 16px 32px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }

        #supplierMessagePopup.popup-success {
            background: #e6ffed;
            color: #155724;
            border: 1px solid #b7f5c2;
        }
        #supplierMessagePopup.popup-error {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
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

    <title>DH AUTOCARE - Suppliers</title>
</head>
<body>
    <?php $activePage = 'supplier'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Purchase Management</h1>
                    <p class="page-subtitle">Manage suppliers, purchase orders, and track inventory purchases.</p>
                </div>
            </div>
        </header>

        <div class="main-container">
            <!-- Results Summary -->
            <div class="results-summary mb-3">

                <div class="align-items-center d-flex justify-content-between">
                    <div class='d-flex justify-content-between align-items-center' style='width: 570px;'>
                        <div class="search-container">
                            <input type="text" name="search" id="search" placeholder="Search by Supplier Name" autocomplete="off">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <div>
                            <button type='button' class='btn btn-outline-primary btn-sm' data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                <i class="fa-solid fa-plus"></i>
                                Add New Supplier
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-muted mb-0">
                            Showing <span id="showing-count"><?php echo $startIndex + 1; ?>-<?php echo min($startIndex + $itemsPerPage, $totalSuppliers); ?></span>
                            of <span id="total-count"><?php echo $totalSuppliers; ?></span> suppliers
                        </p>
                    </div>
                </div>

                
            </div>

            <!-- Table -->
            <div class="history">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr class='align-middle'>
                                <th>Supplier</th>
                                <th>Address</th>
                                <th>Contact No.</th>
                                <th class='text-center'>Action</th>
                            </tr>
                        </thead>
                        <tbody id="supplier-tbody">
<?php
foreach ($displayedSuppliers as $supplier):
    // Build address array with only non-empty components
    $addressParts = [];
    if (!empty(trim($supplier['supplier_street']))) {
        $addressParts[] = trim($supplier['supplier_street']);
    }
    if (!empty(trim($supplier['supplier_baranggay']))) {
        $addressParts[] = trim($supplier['supplier_baranggay']);
    }
    if (!empty(trim($supplier['supplier_city']))) {
        $addressParts[] = trim($supplier['supplier_city']);
    }
    if (!empty(trim($supplier['supplier_province']))) {
        $addressParts[] = trim($supplier['supplier_province']);
    }
    
    // Join address parts with commas, or show "No address" if empty
    $address = !empty($addressParts) ? implode(', ', $addressParts) : 'No address';
?>
    <tr class="align-middle" style="cursor:pointer;" onclick="viewSupplierDetails(<?php echo $supplier['supplier_id']; ?>)">
        <td><span class="customer-name"><?php echo htmlspecialchars($supplier['supplier_name']); ?></span></td>
        <td class='text-muted'><span class="amount"><?php echo htmlspecialchars($address); ?></span></td>
        <td><span class="amount"><?php echo htmlspecialchars($supplier['supplier_number']); ?></span></td>
        <td class='text-center'>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();openEditSupplierModal(<?php echo htmlspecialchars(json_encode($supplier)); ?>)"><i class="fas fa-edit"></i></button>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Supplier pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <li class="page-item <?php if ($currentPage <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>">‹</a>
                        </li>
                        <?php
                            $pageGroup = ceil($currentPage / 5);
                            $startPage = ($pageGroup - 1) * 5 + 1;
                            $endPage = min($startPage + 4, $totalPages);
                            for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php if ($i == $currentPage) echo 'active'; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if ($currentPage >= $totalPages) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>">›</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </main>

    <!-- Add New Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel"><i class="fas fa-user-plus me-2"></i>Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addSupplierForm">
                    <div class="modal-body">
                        <h6>SUPPLIER INFORMATION</h6>
                        <div class='row g-3'>
                            <div class="col-md-12">
                                <label for="supplier_name" class="form-label">Supplier Name</label>
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" autocomplete="off" required>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="supplier_number" name="supplier_number" autocomplete="off" required placeholder="e.g., 09123456789 or 123-4567">
                                <div id="supplier_number_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="supplier_email" name="supplier_email" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_province" class="form-label">Province</label>
                                <select id="supplier_province" name="supplier_province" class='form-select' autocomplete="off">
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_city" class="form-label">Municipality/City</label>
                                <select id="supplier_city" name="supplier_city" class='form-select' autocomplete="off">
                                    <option value="">Select City/Municipality</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_baranggay" class="form-label">Baranggay</label>
                                <select id="supplier_baranggay" name="supplier_baranggay" class='form-select' autocomplete="off">
                                    <option value="">Select Barangay</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_street" class="form-label">Street</label>
                                <input type="text" name="supplier_street" id="supplier_street" class='form-control' autocomplete="off">
                            </div>
                        </div>

                        <br><br>
                        <h6>CONTACT PERSON INFORMATION</h6>
                        <div class='row g-3'>
                            <div class="col-md-3">
                                <label for="contact_fn" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="contact_fn" name="contact_fn" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="contact_mn" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="contact_mn" name="contact_mn" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="contact_ln" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="contact_ln" name="contact_ln" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="contact_suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="contact_suffix" name="contact_suffix" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="contact_nickname" class="form-label">Nickname</label>
                                <input type="text" class="form-control" id="contact_nickname" name="contact_nickname" autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" autocomplete="off" placeholder="e.g., 09123456789 or 123-4567">
                                <div id="contact_number_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label for="contact_province" class="form-label">Province</label>
                                <select id="contact_province" name="contact_province" class='form-select' autocomplete="off">
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_city" class="form-label">Municipality/City</label>
                                <select id="contact_city" name="contact_city" class='form-select' autocomplete="off">
                                    <option value="">Select City/Municipality</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_baranggay" class="form-label">Baranggay</label>
                                <select id="contact_baranggay" name="contact_baranggay" class='form-select' autocomplete="off">
                                    <option value="">Select Barangay</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_street" class="form-label">Street</label>
                                <input type="text" name="contact_street" id="contact_street" class='form-control' autocomplete="off">
                            </div>
                        </div>
                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary">Add Supplier</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSupplierModalLabel"><i class="fas fa-user-edit me-2"></i>Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editSupplierForm">
                    <div class="modal-body">
                        <!-- Same fields as Add Supplier, but with different IDs and names (edit_*) -->
                        <input type="hidden" id="edit_supplier_id" name="supplier_id">
                        <h6>SUPPLIER INFORMATION</h6>
                        <div class='row g-3'>
                            <div class="col-md-12">
                                <label for="edit_supplier_name" class="form-label">Supplier Name</label>
                                <input type="text" class="form-control" id="edit_supplier_name" name="supplier_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="edit_supplier_number" name="supplier_number" required placeholder="e.g., 09123456789 or 123-4567">
                                <div id="edit_supplier_number_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_supplier_email" name="supplier_email">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier_province" class="form-label">Province</label>
                                <select id="edit_supplier_province" name="supplier_province" class='form-select'>
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier_city" class="form-label">Municipality/City</label>
                                <select id="edit_supplier_city" name="supplier_city" class='form-select'>
                                    <option value="">Select City/Municipality</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier_baranggay" class="form-label">Baranggay</label>
                                <select id="edit_supplier_baranggay" name="supplier_baranggay" class='form-select'>
                                    <option value="">Select Barangay</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_supplier_street" class="form-label">Street</label>
                                <input type="text" name="supplier_street" id="edit_supplier_street" class='form-control'>
                            </div>
                        </div>
                        <br><br>
                        <h6>CONTACT PERSON INFORMATION</h6>
                        <div class='row g-3'>
                            <div class="col-md-3">
                                <label for="edit_contact_fn" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="edit_contact_fn" name="contact_fn">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_contact_mn" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="edit_contact_mn" name="contact_mn">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_contact_ln" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="edit_contact_ln" name="contact_ln">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_contact_suffix" class="form-label">Suffix</label>
                                <input type="text" class="form-control" id="edit_contact_suffix" name="contact_suffix">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_contact_nickname" class="form-label">Nickname</label>
                                <input type="text" class="form-control" id="edit_contact_nickname" name="contact_nickname">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="edit_contact_number" name="contact_number" placeholder="e.g., 09123456789 or 123-4567">
                                <div id="edit_contact_number_error" class="text-danger small mt-1" style="display: none;"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_contact_email" name="contact_email">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_province" class="form-label">Province</label>
                                <select id="edit_contact_province" name="contact_province" class='form-select'>
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_city" class="form-label">Municipality/City</label>
                                <select id="edit_contact_city" name="contact_city" class='form-select'>
                                    <option value="">Select City/Municipality</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_baranggay" class="form-label">Baranggay</label>
                                <select id="edit_contact_baranggay" name="contact_baranggay" class='form-select'>
                                    <option value="">Select Barangay</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_street" class="form-label">Street</label>
                                <input type="text" name="contact_street" id="edit_contact_street" class='form-control'>
                            </div>
                        </div>
                        <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>

    <div id="supplierMessagePopup"></div>

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
        
        // Message popup function
        function showSupplierMessagePopup(message, isSuccess = true) {
            const $popup = $("#supplierMessagePopup");
            $popup.stop(true, true); // Stop any current animations
            $popup.text(message);
            $popup.removeClass("popup-success popup-error");
            $popup.addClass(isSuccess ? "popup-success" : "popup-error");
            $popup.fadeIn(200).delay(1800).fadeOut(400);
        }

        function viewSupplierDetails(id) {
            window.location.href = `supplier_details.php?id=${id}`;
        }

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
    // Supplier address
    const supplierProvince = document.getElementById('supplier_province');
    const supplierCity = document.getElementById('supplier_city');
    const supplierBarangay = document.getElementById('supplier_baranggay');
    if (supplierProvince && supplierCity && supplierBarangay) {
        populateProvinces(supplierProvince);
        supplierProvince.addEventListener('change', function() {
            populateCities(supplierProvince, supplierCity, supplierBarangay);
        });
        supplierCity.addEventListener('change', function() {
            populateBarangays(supplierProvince, supplierCity, supplierBarangay);
        });
    }

    // Contact person address
    const contactProvince = document.getElementById('contact_province');
    const contactCity = document.getElementById('contact_city');
    const contactBarangay = document.getElementById('contact_baranggay');
    if (contactProvince && contactCity && contactBarangay) {
        populateProvinces(contactProvince);
        contactProvince.addEventListener('change', function() {
            populateCities(contactProvince, contactCity, contactBarangay);
        });
        contactCity.addEventListener('change', function() {
            populateBarangays(contactProvince, contactCity, contactBarangay);
        });
    }

    // Edit Supplier address dropdowns
    const editSupplierProvince = document.getElementById('edit_supplier_province');
    const editSupplierCity = document.getElementById('edit_supplier_city');
    const editSupplierBarangay = document.getElementById('edit_supplier_baranggay');
    if (editSupplierProvince && editSupplierCity && editSupplierBarangay) {
        populateProvinces(editSupplierProvince);
        editSupplierProvince.addEventListener('change', function() {
            populateCities(editSupplierProvince, editSupplierCity, editSupplierBarangay);
        });
        editSupplierCity.addEventListener('change', function() {
            populateBarangays(editSupplierProvince, editSupplierCity, editSupplierBarangay);
        });
    }

    // Edit Contact person address dropdowns
    const editContactProvince = document.getElementById('edit_contact_province');
    const editContactCity = document.getElementById('edit_contact_city');
    const editContactBarangay = document.getElementById('edit_contact_baranggay');
    if (editContactProvince && editContactCity && editContactBarangay) {
        populateProvinces(editContactProvince);
        editContactProvince.addEventListener('change', function() {
            populateCities(editContactProvince, editContactCity, editContactBarangay);
        });
        editContactCity.addEventListener('change', function() {
            populateBarangays(editContactProvince, editContactCity, editContactBarangay);
        });
    }

    // Phone number validation event listeners for Add Supplier form
    $('#supplier_number').on('input', function() {
        validatePhoneNumber(this, 'supplier_number_error');
    });
    
    $('#supplier_number').on('blur', function() {
        validatePhoneNumber(this, 'supplier_number_error');
    });
    
    $('#contact_number').on('input', function() {
        validatePhoneNumber(this, 'contact_number_error');
    });
    
    $('#contact_number').on('blur', function() {
        validatePhoneNumber(this, 'contact_number_error');
    });
    
    // Phone number validation event listeners for Edit Supplier form
    $('#edit_supplier_number').on('input', function() {
        validatePhoneNumber(this, 'edit_supplier_number_error');
    });
    
    $('#edit_supplier_number').on('blur', function() {
        validatePhoneNumber(this, 'edit_supplier_number_error');
    });
    
    $('#edit_contact_number').on('input', function() {
        validatePhoneNumber(this, 'edit_contact_number_error');
    });
    
    $('#edit_contact_number').on('blur', function() {
        validatePhoneNumber(this, 'edit_contact_number_error');
    });
    
    // Add Supplier Form Submission
    $('#addSupplierForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Add supplier form submitted');
        console.log('Modal element exists:', $('#addSupplierModal').length);
        console.log('Form element exists:', $('#addSupplierForm').length);
        
        // Validate phone numbers before submission
        const supplierNumberValid = validatePhoneNumber(document.getElementById('supplier_number'), 'supplier_number_error');
        const contactNumberValid = validatePhoneNumber(document.getElementById('contact_number'), 'contact_number_error');
        
        if (!supplierNumberValid || !contactNumberValid) {
            return; // Stop submission if validation fails
        }
        
        const formData = $(this).serialize();
        console.log('Form data:', formData);
        
        $.ajax({
            url: 'add_supplier.php', // PHP script to handle form submission
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Response received:', response);
                console.log('Response type:', typeof response);
                console.log('Response length:', response.length);
                
                // jQuery automatically parses JSON responses, so response is already an object
                const data = response;
                console.log('Response data:', data);
                if (data.success) {
                    showSupplierMessagePopup('Supplier added successfully!', true);
                    console.log('Closing modal...');
                    // Try multiple methods to close the modal
                    try {
                        $('#addSupplierModal').modal('hide');
                        // Also try to trigger the close button
                        $('#addSupplierModal .btn-close').click();
                    } catch (e) {
                        console.log('Modal hide failed, trying alternative method');
                        $('#addSupplierModal').modal('dispose');
                    }
                    // Force close by removing modal backdrop and classes
                    setTimeout(function() {
                        $('.modal-backdrop').remove();
                        $('#addSupplierModal').removeClass('show').attr('style', 'display: none !important');
                        $('body').removeClass('modal-open');
                    }, 100);
                    setTimeout(function() {
                        location.reload(); // Reload page to show new supplier
                    }, 1200);
                } else {
                    showSupplierMessagePopup('Error adding supplier: ' + (data.message || data.error), false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.log('Response text:', xhr.responseText);
                showSupplierMessagePopup('An error occurred: ' + error, false);
            }
        });
    });
});

        function setupEventListeners() {
            document.getElementById('add-customer-btn').addEventListener('click', addCustomer);
        }

        function addCustomer() {
            // Implement add customer functionality
            alert('Add Customer functionality will be implemented here');
        }
    </script>
    <script>
        // Open Edit Supplier Modal
    function openEditSupplierModal(supplier) {
        console.log('Opening edit modal for supplier:', supplier);
        
        // Test the JSON response first
        $.ajax({
            url: 'supplier.php',
            type: 'POST',
            data: 'action=update_supplier&test=true',
            dataType: 'json',
            success: function(response) {
                console.log('Test response successful:', response);
            },
            error: function(xhr, status, error) {
                console.error('Test response failed:', status, error);
                console.log('Test response text:', xhr.responseText);
            }
        });
        
        // Fill modal fields
        $('#edit_supplier_id').val(supplier.supplier_id);
        $('#edit_supplier_name').val(supplier.supplier_name);
        $('#edit_supplier_number').val(supplier.supplier_number);
        $('#edit_supplier_email').val(supplier.supplier_email);
        $('#edit_supplier_street').val(supplier.supplier_street);
        $('#edit_contact_fn').val(supplier.contact_fn);
        $('#edit_contact_mn').val(supplier.contact_mn);
        $('#edit_contact_ln').val(supplier.contact_ln);
        $('#edit_contact_suffix').val(supplier.contact_suffix);
        $('#edit_contact_nickname').val(supplier.contact_nickname);
        $('#edit_contact_number').val(supplier.contact_number);
        $('#edit_contact_email').val(supplier.contact_email);
        $('#edit_contact_street').val(supplier.contact_street);
        
        // Handle supplier address dropdowns
        const editSupplierProvince = document.getElementById('edit_supplier_province');
        const editSupplierCity = document.getElementById('edit_supplier_city');
        const editSupplierBarangay = document.getElementById('edit_supplier_baranggay');
        
        if (editSupplierProvince && editSupplierCity && editSupplierBarangay) {
            // Set province first
            editSupplierProvince.value = supplier.supplier_province || '';
            
            // Populate cities for the selected province
            populateCities(editSupplierProvince, editSupplierCity, editSupplierBarangay);
            
            // Set city after cities are populated
            setTimeout(() => {
                editSupplierCity.value = supplier.supplier_city || '';
                
                // Populate barangays for the selected city
                populateBarangays(editSupplierProvince, editSupplierCity, editSupplierBarangay);
                
                // Set barangay after barangays are populated
                setTimeout(() => {
                    editSupplierBarangay.value = supplier.supplier_baranggay || '';
                }, 100);
            }, 100);
        }
        
        // Handle contact person address dropdowns
        const editContactProvince = document.getElementById('edit_contact_province');
        const editContactCity = document.getElementById('edit_contact_city');
        const editContactBarangay = document.getElementById('edit_contact_baranggay');
        
        if (editContactProvince && editContactCity && editContactBarangay) {
            // Set province first
            editContactProvince.value = supplier.contact_province || '';
            
            // Populate cities for the selected province
            populateCities(editContactProvince, editContactCity, editContactBarangay);
            
            // Set city after cities are populated
            setTimeout(() => {
                editContactCity.value = supplier.contact_city || '';
                
                // Populate barangays for the selected city
                populateBarangays(editContactProvince, editContactCity, editContactBarangay);
                
                // Set barangay after barangays are populated
                setTimeout(() => {
                    editContactBarangay.value = supplier.contact_baranggay || '';
                }, 100);
            }, 100);
        }
        
        $('#editSupplierModal').modal('show');
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
        $(document).ready(function() {
            $('#search').focus();
        });
        
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
            // Show loading state
            const $tbody = $('#supplier-tbody');
            $tbody.html('<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</td></tr>');
            
            // Fetch search results from server
            $.ajax({
                url: 'search_suppliers.php',
                type: 'GET',
                data: { term: searchTerm },
                dataType: 'json',
                success: function(data) {
                    displaySearchResults(data, searchTerm);
                },
                error: function(xhr, status, error) {
                    console.error('Search error:', error);
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
        
        function displaySearchResults(suppliers, searchTerm) {
            const $tbody = $('#supplier-tbody');
            $tbody.empty();
            
            if (suppliers.length === 0) {
                $tbody.html(`
                    <tr class="no-results-row">
                        <td colspan="4" class="no-results">
                            <i class="fas fa-search me-2"></i>
                            No suppliers found matching "${searchTerm}"
                        </td>
                    </tr>
                `);
                updateCountDisplay(0, 0);
                return;
            }
            
            // Sort suppliers alphabetically
            suppliers.sort((a, b) => a.supplier_name.localeCompare(b.supplier_name));
            
            // Display search results
            suppliers.forEach(supplier => {
                // Build address array with only non-empty components
                const addressParts = [];
                if (supplier.supplier_street && supplier.supplier_street.trim()) {
                    addressParts.push(supplier.supplier_street.trim());
                }
                if (supplier.supplier_baranggay && supplier.supplier_baranggay.trim()) {
                    addressParts.push(supplier.supplier_baranggay.trim());
                }
                if (supplier.supplier_city && supplier.supplier_city.trim()) {
                    addressParts.push(supplier.supplier_city.trim());
                }
                if (supplier.supplier_province && supplier.supplier_province.trim()) {
                    addressParts.push(supplier.supplier_province.trim());
                }
                
                // Join address parts with commas, or show "No address" if empty
                const address = addressParts.length > 0 ? addressParts.join(', ') : 'No address';
                
                const row = `
                    <tr class="align-middle" style="cursor:pointer;" onclick="viewSupplierDetails(${supplier.supplier_id})">
                        <td><span class="customer-name">${supplier.supplier_name}</span></td>
                        <td class='text-muted'><span class="amount">${address}</span></td>
                        <td><span class="amount">${supplier.supplier_number}</span></td>
                        <td class='text-center'>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();openEditSupplierModal(${JSON.stringify(supplier).replace(/"/g, '&quot;')})"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                `;
                $tbody.append(row);
            });
            
            updateCountDisplay(suppliers.length, suppliers.length);
            
            // Keep focus on search input after displaying results
            keepSearchFocus();
        }
        
        function updateCountDisplay(visibleCount, totalCount) {
            const searchTerm = $('#search').val().trim();
            
            if (searchTerm === '') {
                // Show original pagination info when no search
                $('#showing-count').text('<?php echo $startIndex + 1; ?>-<?php echo min($startIndex + $itemsPerPage, $totalSuppliers); ?>');
                $('#total-count').text('<?php echo $totalSuppliers; ?>');
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
        
        // Clear search when clicking the search icon
        $('.search-container i').on('click', function() {
            $('#search').val('').trigger('input');
        });
    });
    //end of search functionality
    
    $(function() {
        $('#editSupplierForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Edit supplier form submitted');
            
            // Validate phone numbers before submission
            const editSupplierNumberValid = validatePhoneNumber(document.getElementById('edit_supplier_number'), 'edit_supplier_number_error');
            const editContactNumberValid = validatePhoneNumber(document.getElementById('edit_contact_number'), 'edit_contact_number_error');
            
            if (!editSupplierNumberValid || !editContactNumberValid) {
                return; // Stop submission if validation fails
            }
            
            const formData = $(this).serialize();
            console.log('Edit form data:', formData);
            
            // Add action parameter to identify this as an update request
            const updateData = formData + '&action=update_supplier';
            console.log('Update data with action:', updateData);
            
            $.ajax({
                url: 'supplier.php', // Send to the same file
                type: 'POST',
                data: updateData,
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    console.log('Edit response received:', response);
                    console.log('Response type:', typeof response);
                    
                    if (response.success) {
                        showSupplierMessagePopup('Supplier updated successfully!', true);
                        // Close the modal
                        $('#editSupplierModal').modal('hide');
                        // Remove modal backdrop
                        setTimeout(function() {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                        }, 100);
                        // Reload page after delay
                        setTimeout(function(){ 
                            location.reload(); 
                        }, 1200);
                    } else {
                        showSupplierMessagePopup('Error: ' + (response.error || 'Unknown error'), false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Edit AJAX error:', status, error);
                    console.log('Response status:', xhr.status);
                    console.log('Response text:', xhr.responseText);
                    console.log('Response headers:', xhr.getAllResponseHeaders());
                    
                    // Try to parse the response text to see what we're getting
                    try {
                        const responseText = xhr.responseText;
                        console.log('Raw response text:', responseText);
                        
                        if (responseText.trim() === '') {
                            showSupplierMessagePopup('Empty response from server', false);
                        } else {
                            const parsedResponse = JSON.parse(responseText);
                            console.log('Parsed response:', parsedResponse);
                            if (parsedResponse.success) {
                                showSupplierMessagePopup('Supplier updated successfully!', true);
                                $('#editSupplierModal').modal('hide');
                                setTimeout(function() {
                                    $('.modal-backdrop').remove();
                                    $('body').removeClass('modal-open');
                                }, 100);
                                setTimeout(function(){ 
                                    location.reload(); 
                                }, 1200);
                            } else {
                                showSupplierMessagePopup('Error: ' + (parsedResponse.error || 'Unknown error'), false);
                            }
                        }
                    } catch (parseError) {
                        console.error('Failed to parse response:', parseError);
                        showSupplierMessagePopup('Invalid response from server: ' + xhr.responseText.substring(0, 100), false);
                    }
                }
            });
        });
    });
    </script>
</body>
</html>