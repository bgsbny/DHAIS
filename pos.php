<?php
    include('mycon.php');
    session_start();

    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit();
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

    <title>POS</title>
</head>
<body>
    <div class="pos-container w-100 min-vh-100 d-flex flex-column flex-lg-row justify-content-between align-items-stretch gap-0">
        <!-- Left: Product Table -->
        <div class="first-section flex-grow-1 p-3 d-flex flex-column">
            <div class="card shadow-sm mb-3 flex-grow-1 d-flex flex-column">
                <div class="card-header bg-white border-bottom-0 pb-2">
                    <h5 class="mb-0 fw-bold">Cart</h5>
                </div>
                <div class="card-body p-0 flex-grow-1 d-flex flex-column">
                    <div class="table-responsive product-list-table">
                        <table class='table table-hover mb-0'>
                            <thead>
                                <tr class='align-middle'>
                                    <th>Product</th>
                                    <th class='text-center'>Unit</th>
                                    <th class='text-center'>Stock</th>
                                    <th class='text-center'>Qty</th>
                                    <th class='text-center'>Price (&#8369;)</th>
                                    <th class='text-center'>Discount</th>
                                    <th class='text-center'>Markup (&#8369;)</th>
                                    <th class='text-center'>Total (&#8369;)</th>
                                    <th class='text-center'>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class='align-middle text-center text-muted'>
                                    <td colspan="9"><i class="fas fa-shopping-cart me-2"></i>No products in cart</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="add-product d-flex justify-content-between w-100 mb-3">
                <!-- <select name="" id="" class='form-select'>
                    <option value="">Select Product</option>
                </select> -->

                <input type="text" name="" id="enterProduct" placeholder='Enter Product' class='enter-product w-100'>
            </div>

            <div class="d-flex justify-content-between modals gap-2">
                <button data-bs-toggle="modal" data-bs-target="#addCreditorModal" id='addCreditorBtn'>
                    <i class="fas fa-user-plus me-1"></i>
                    Add New Creditor (F2)
                </button>

                <button data-bs-toggle="modal" data-bs-target="#paymentMethodModal" id='paymentMethodBtn'>
                    <i class="fas fa-money-check-alt me-1"></i>
                    Payment Method (F3)
                </button>

                <button data-bs-toggle="modal" data-bs-target="#productListModal" id='priceLookupBtn'>
                    <i class="fas fa-search-dollar me-1"></i>
                    Price Look Up (F4)
                </button>

                <a href="dashboard.php" style='text-decoration:none;' id='leaveBtn'>
                    <button>
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Leave (F12)
                    </button>
                </a>
            </div>
        </div>

        <script>
            document.addEventListener('keydown', function(event) {
                if (event.code === 'F2') {
                    event.preventDefault();
                    document.getElementById('addCreditorBtn').click();
                }
                if (event.code === 'F3') {
                    event.preventDefault();
                    document.getElementById('paymentMethodBtn').click();
                }
                if (event.code === 'F4') {
                    event.preventDefault();
                    document.getElementById('priceLookupBtn').click();
                }
                if (event.code === 'F12' || event.code === 'Escape') {
                    event.preventDefault();
                    document.getElementById('leaveBtn').click();
                }
            });
        </script>

        <!-- Right: Summary Panel -->
        <div class="second-section p-4 d-flex flex-column justify-content-between">
            <div class="card shadow-sm mb-3">
                <div class="card-body">

                    <div class='d-flex justify-content-between mb-2'>
                        <span class="fw-semibold">Subtotal:</span>
                        <span class="fw-bold" name="subtotal" id="subtotal">₱0.00</span>
                    </div>

                    <div class='d-flex justify-content-between mb-2'>
                        <span class="fw-semibold" id="globalDiscountLabel">Discount: (0%)</span>
                        <span class="fw-bold text-danger" name='discount_percentage' id="discount_percentage">-₱0.00</span>
                    </div>

                    <div class="discount-section mt-3">
                        <label for="" class="form-label">Apply Global Discount</label>
                        <div class='w-100 d-flex justify-content-between'>
                            <input type="number" name="" id="globalDiscountInput" class='p-1' min="0" max="100" placeholder="0.00">
                            <button>Apply</button>
                        </div>
                    </div>

                    <hr class='mt-4'>

                    <div class='d-flex justify-content-between mt-3'>
                        <h5 class="fw-bold">Grand Total</h5>
                        <h5 class="fw-bold" name='grand_total' id="grand_total">₱0.00</h5>
                    </div>

                    <div class="receipt-section mt-3">
                        <label for="" class="form-label">External Receipt No.</label>
                        <input type="text" name="external_receipt_no" id="external_receipt_no" class='p-1 w-100'>
                    </div>
                    
                </div>
            </div>
            <div class='confirm-btn-section mt-3'>
                <button class='w-100 p-3 fs-5'><i class="fas fa-check-circle me-2"></i>Confirm Purchase</button>
            </div>
        </div>

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

                                <div class="col-md-4">
                                    <label class="form-label">Nickname</label>
                                    <input type="text" id='creditor_nickname' name='creditor_nickname' class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" id='creditor_contactNo' name='creditor_contactNo' class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" id='creditor_email' name='creditor_email' class="form-control">
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
                                    <input type="text" id='org_contactNumber' name='org_contactNumber' class="form-control">
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

                                <div class="col-md-4">
                                    <label class="form-label">Nickname</label>
                                    <input type="text" id='org_contactPerson_nickname' name='org_contactPerson_nickname' class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" id='org_contactPerson_contactNumber' name='org_contactPerson_contactNumber' class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" id='org_contactPerson_email' name='org_contactPerson_email' class="form-control">
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

        <script>
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

        <script>
            $(function() {
                $('#addCreditorForm').on('submit', function(e) {
                    e.preventDefault();
                    var $form = $(this);
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
        </script>

        <!-- Payment Method Modal -->
        <div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-labelledby="paymentMethodModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentMethodModalLabel"><i class="fas fa-money-check-alt me-2"></i>Payment Method</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for='payment_method' class="form-label">Select Payment Method</label>
                                <select id='payment_method' class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>

                            <div id='cashfields' class='row g-3'>
                                <div class="col-md-12">
                                    <label for='customer_firstName' class="form-label">Customer First Name</label>
                                    <input type="text" id='customer_firstName' name='customer_firstName' class="form-control">
                                </div>

                                <div class="col-md-12">
                                    <label for='customer_middleName' class="form-label">Customer Middle Name</label>
                                    <input type="text" id='customer_middleName' name='customer_middleName' class="form-control">
                                </div>

                                <div class="col-md-12">
                                    <label for='customer_lastName' class="form-label">Customer Last Name</label>
                                    <input type="text" id='customer_lastName' name='customer_lastName' class="form-control">
                                </div>

                                <div class="col-md-12">
                                    <label for='customer_suffix' class="form-label">Customer Suffix</label>
                                    <input type="text" id='customer_suffix' name='customer_suffix' class="form-control">
                                </div>
                            </div>

                            <div id='creditfields' class='row g-3' style='display:none;'>
                                <div class='col-md-6'>
                                    <label for="" class='form-label'>Creditor Name</label>
                                    <input type="text" name="creditorNameInput" id="creditorNameInput" class='form-control' autocomplete="off">
                                </div>

                                <div class='col-md-6'>
                                    <label for="" class='form-label'>Due Date</label>
                                    <input type="date" name="" id="" class='form-control'>
                                </div>

                                <div class='col-md-6'>
                                    <label for="" class='form-label'>Interest</label>
                                    <input type="number" name="interest" id="interest" class='form-control' placeholder='0.00'>
                                </div>

                                <div class='col-md-6'>
                                    <label for="" class='form-label'>Down Payment</label>
                                    <input type="number" name="down_payment" id="down_payment" class='form-control' placeholder='0.00'>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const paymentMethodSelect = document.getElementById('payment_method');
                const cashfields = document.getElementById('cashfields');
                const creditfields = document.getElementById('creditfields');
                function togglePaymentFields() {
                if (paymentMethodSelect.value === 'Cash') {
                cashfields.style.display = '';
                creditfields.style.display = 'none';
                } else {
                cashfields.style.display = 'none';
                creditfields.style.display = '';
                }
                }
                paymentMethodSelect.addEventListener('change', togglePaymentFields);
                togglePaymentFields();
            });
        </script>

        <!-- Price Look Up Modal -->
        <div class="modal fade" id="productListModal" tabindex="-1" aria-labelledby="productListModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productListModalLabel"><i class="fas fa-search-dollar me-2"></i>Price Look Up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="lookupSearch" placeholder="Search for a product...">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class='text-center'>Unit</th>
                                        <th class='text-center'>Price (&#8369;)</th>
                                        <th class='text-center'>Stock</th>
                                    </tr>
                                </thead>
                                <tbody id="lookupResults">
                                    <tr class="text-muted text-center">
                                        <td colspan="4"><i class="fas fa-box-open me-2"></i>No products found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        

    </div>

    <div id="qtyWarningPopup"></div>
    <div id="creditorMessagePopup"></div>

    <script>
    // Make this function globally accessible
    function showCreditorMessagePopup(message, isSuccess = true) {
        const $popup = $("#creditorMessagePopup");
        $popup.stop(true, true); // Stop any current animations
        $popup.text(message);
        $popup.removeClass("popup-success popup-error");
        $popup.addClass(isSuccess ? "popup-success" : "popup-error");
        $popup.fadeIn(200).delay(1800).fadeOut(400);
    }
    </script>

    <script>
let selectedProduct = null;
let selectedCreditorType = null;
let selectedCreditorName = null;
let selectedCreditorId = null;

$(function() {
    $("#enterProduct").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "autocomplete_products.php",
                dataType: "json",
                data: { term: request.term },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2,
        position: { my: "left bottom", at: "left top" },
        select: function(event, ui) {
            selectedProduct = ui.item;
            $("#enterProduct").val(ui.item.value);
            return false;
        }
    });

    function formatPeso(val) {
        return '₱' + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    // New: format number without peso sign, with commas
    function formatNumber(val) {
        return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Calculate and update total for a row
    function updateRowTotal($row) {
        let qty = parseInt($row.find('.cart-qty-input').val(), 10) || 1;
        let unitPrice = parseFloat($row.find('td:eq(4)').text().replace(/[^\d.]/g, '')) || 0;
        let $discountInput = $row.find('.cart-discount-input');
        let discount = parseFloat($discountInput.val()) || 0;
        let markup = parseFloat($row.find('.cart-markup-input').val()) || 0;
        // Set max discount to unit price
        $discountInput.attr('max', unitPrice);
        if (discount > unitPrice) discount = unitPrice;
        let total = (unitPrice - discount + markup) * qty;
        if (total < 0) total = 0;
        // Use formatNumber for table total (no peso sign)
        $row.find('td:eq(7)').text(formatNumber(total));
    }

    function updateSummaryTotals() {
        let subtotal = 0;
        $(".product-list-table tbody tr").each(function() {
            // Only sum rows with a valid total (skip empty row)
            let totalText = $(this).find("td:eq(7)").text().replace(/[₱,]/g, '').trim();
            let total = parseFloat(totalText) || 0;
            subtotal += total;
        });

        // Get global discount percent
        let globalDiscountPercent = parseFloat($("#globalDiscountInput").val()) || 0;
        if (globalDiscountPercent < 0) globalDiscountPercent = 0;
        if (globalDiscountPercent > 100) globalDiscountPercent = 100;

        // Update the label
        $("#globalDiscountLabel").text(`Discount: (${globalDiscountPercent}%)`);

        // Calculate global discount amount
        let globalDiscountAmount = subtotal * (globalDiscountPercent / 100);

        // Calculate grand total
        let grandTotal = subtotal - globalDiscountAmount;
        if (grandTotal < 0) grandTotal = 0;

        // Update UI
        $("#subtotal").text(formatPeso(subtotal));
        $("#discount_percentage").text('-' + formatPeso(globalDiscountAmount));
        $("#grand_total").text(formatPeso(grandTotal));
    }

    // When qty or discount changes, update total and summary
    $(".product-list-table").on("input change", ".cart-qty-input, .cart-discount-input, .cart-markup-input", function() {
        let $row = $(this).closest("tr");
        updateRowTotal($row);
        updateSummaryTotals();
    });

    function addProductToCart() {
        if (selectedProduct) {
            let stock_level = selectedProduct.stock_level !== undefined ? selectedProduct.stock_level : '-';
            let unit = selectedProduct.unit !== undefined ? selectedProduct.unit : '-';
            let unit_price = selectedProduct.selling_price !== undefined ? selectedProduct.selling_price : '-';
            let default_warehouse = selectedProduct.default_warehouse || 'Main Shop';
            
            // Check if product already exists in cart
            let $existingRow = $(".product-list-table tbody tr[data-product-id='" + selectedProduct.product_id + "']");
            if ($existingRow.length > 0) {
                // Product exists, increase quantity
                let $qtyInput = $existingRow.find('.cart-qty-input');
                let currentQty = parseInt($qtyInput.val(), 10) || 1;
                let maxQty = parseInt($qtyInput.attr('max'), 10) || 9999;
                if (currentQty < maxQty) {
                    $qtyInput.val(currentQty + 1).trigger('change');
                } else {
                    showQtyWarningPopup(maxQty);
                }
                // Clear input and selectedProduct
                $("#enterProduct").val('');
                selectedProduct = null;
                return;
            }
            
            // Determine max stock based on default warehouse
            let maxStock = stock_level;
            if (selectedProduct.warehouses) {
                for (let warehouse of selectedProduct.warehouses) {
                    if (warehouse.storage_location === default_warehouse) {
                        maxStock = warehouse.stock_level;
                        break;
                    }
                }
            }
            
            let qtySpinner = `
                <div class="input-group input-group-sm quantity-spinner">
                    <button class="btn btn-outline-secondary btn-qty-minus" type="button">-</button>
                    <input type="number" class="form-control text-center cart-qty-input" value="1" min="1" max="${maxStock}" style="max-width: 45px;">
                    <button class="btn btn-outline-secondary btn-qty-plus" type="button">+</button>
                </div>
            `;
            let discountInput = `<input type="number" class="form-control form-control-sm text-center cart-discount-input" min="0" max="${unit_price}" placeholder='0.00'>`;
            let markupInput = `<input type="number" class="form-control form-control-sm text-center cart-markup-input" min="0" placeholder='0.00'>`;
            let newRow = `
                <tr class='align-middle' data-product-id="${selectedProduct.product_id}" data-default-warehouse="${default_warehouse}">
                    <td>${selectedProduct.label}</td>
                    <td class='text-center'>${unit}</td>
                    <td class='text-center'>${Number(maxStock).toLocaleString('en-US')}</td>
                    <td class='text-center'>${qtySpinner}</td>
                    <td class='text-end'>${formatNumber(unit_price)}</td>
                    <td class='text-center'>${discountInput}</td>
                    <td class='text-center'>${markupInput}</td>
                    <td class='text-end'>-</td>
                    <td class='text-center'>
                        <button class="btn btn-danger btn-sm remove-product">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            // Remove 'No products in cart' row if present
            $(".product-list-table tbody .text-muted").remove();
            // Append the new row
            $(".product-list-table tbody").append(newRow);
            // Initialize total for the new row
            let $newRow = $(".product-list-table tbody tr").last();
            updateRowTotal($newRow);
            updateSummaryTotals();
            // Clear input and selectedProduct
            $("#enterProduct").val('');
            selectedProduct = null;
        }
    }

    // Add button click
    $(".add-product-btn").on("click", function() {
        addProductToCart();
    });

    // Enter key in input
    $("#enterProduct").on("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            addProductToCart();
        }
    });

    // Remove product from cart
    $(".product-list-table").on("click", ".remove-product", function() {
        $(this).closest("tr").remove();
        // If cart is empty, show the empty row again
        if ($(".product-list-table tbody tr").length === 0) {
            $(".product-list-table tbody").append(
                `<tr class='align-middle text-center text-muted'>
                    <td colspan="8"><i class="fas fa-shopping-cart me-2"></i>No products in cart</td>
                </tr>`
            );
        }
        updateSummaryTotals();
    });

    // Quantity spinner logic
    $(".product-list-table").on("click", ".btn-qty-minus", function() {
        let $input = $(this).siblings(".cart-qty-input");
        let val = parseInt($input.val(), 10) || 1;
        if (val > 1) $input.val(val - 1).trigger('change');
    });

    function showQtyWarningPopup(stock_level) {
        const $popup = $("#qtyWarningPopup");
        $popup.stop(true, true); // Stop any current animations
        $popup.text(`Cannot exceed stock (${stock_level})`);
        $popup.fadeIn(200).delay(1800).fadeOut(400);
    }

    // Plus button
    $(".product-list-table").on("click", ".btn-qty-plus", function() {
        let $input = $(this).siblings(".cart-qty-input");
        let val = parseInt($input.val(), 10) || 1;
        let max = parseInt($input.attr("max"), 10) || 9999;
        if (val < max) {
            $input.val(val + 1).trigger('change');
        } else {
            showQtyWarningPopup(max);
        }
    });

    // Manual input
    $(".product-list-table").on("input", ".cart-qty-input", function() {
        let $input = $(this);
        let val = parseInt($input.val(), 10) || 1;
        let max = parseInt($input.attr("max"), 10) || 9999;
        if (val > max) {
            $input.val(max);
            showQtyWarningPopup(max);
        } else if (val < 1) {
            $input.val(1);
        }
    });

    // When global discount changes
    $("#globalDiscountInput").on("input change", function() {
        let val = parseFloat($(this).val()) || 0;
        if (val > 100) {
            $(this).val(100);
            val = 100;
        }
        if (val < 0) {
            $(this).val(0);
            val = 0;
        }
        updateSummaryTotals();
    });

    // Confirm Purchase button click handler
    $('.confirm-btn-section button').on('click', function(e) {
        e.preventDefault();
        // Gather cart data
        var cart = [];
        $('.product-list-table tbody tr').each(function() {
            var $row = $(this);
            // Skip empty row
            if ($row.find('td').length < 9 || $row.hasClass('text-muted')) return;
            cart.push({
                product_id: $row.data('product-id') || null, // You need to set this when adding row
                qty: parseInt($row.find('.cart-qty-input').val(), 10) || 1,
                unit_price: parseFloat($row.find('td:eq(4)').text().replace(/,/g, '')) || 0,
                discount: parseFloat($row.find('.cart-discount-input').val()) || 0,
                markup: parseFloat($row.find('.cart-markup-input').val()) || 0,
                subtotal: parseFloat($row.find('td:eq(7)').text().replace(/,/g, '')) || 0
            });
        });
        // Gather payment and summary data
        var payment_method = $('#payment_method').val();
        var creditor_id = selectedCreditorId || null; // You need to resolve creditor_id from name, or add a hidden field
        var subtotal = parseFloat($('#subtotal').text().replace(/[^\d.]/g, '')) || 0;
        var discount_percentage = parseFloat($('#globalDiscountInput').val()) || 0;
        var grand_total = parseFloat($('#grand_total').text().replace(/[^\d.]/g, '')) || 0;
        var external_receipt_no = $('#external_receipt_no').val();
        var customer_firstName = $('#customer_firstName').val() || '';
        var customer_middleName = $('#customer_middleName').val() || '';
        var customer_lastName = $('#customer_lastName').val() || '';
        var customer_suffix = $('#customer_suffix').val() || '';
        // Set customer_type for credit/cash
        var customer_type = '';
        if (payment_method && payment_method.toLowerCase() === 'credit') {
            if (selectedCreditorType === 'organization') {
                customer_type = 'organization - creditor';
            } else if (selectedCreditorType === 'individual') {
                customer_type = 'individual - creditor';
            }
        } else if (payment_method && payment_method.toLowerCase() === 'cash') {
            customer_type = 'walk-in';
        }
        // Declare and assign due_date, interest, down_payment before using them
        var due_date = $('#creditfields input[type="date"]').val() || null;
        var interest = $('#interest').val() || null;
        var down_payment = $('#down_payment').val() || null;
        // Compose data
        var data = {
            cart: cart,
            payment_method: payment_method,
            creditor_id: creditor_id, // Needs to be resolved
            subtotal: subtotal,
            discount_percentage: discount_percentage,
            grand_total: grand_total,
            external_receipt_no: external_receipt_no,
            customer_type: customer_type,
            due_date: due_date,
            interest: interest,
            down_payment: down_payment,
            customer_firstName: customer_firstName,
            customer_middleName: customer_middleName,
            customer_lastName: customer_lastName,
            customer_suffix: customer_suffix
        };
        // Send AJAX
        $.ajax({
            url: 'save_transaction.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Subtract stock from warehouses for each cart item
                    let stockSubtractionPromises = [];
                    
                    $('.product-list-table tbody tr').each(function() {
                        var $row = $(this);
                        // Skip empty row
                        if ($row.find('td').length < 9 || $row.hasClass('text-muted')) return;
                        
                        let productId = $row.data('product-id');
                        let quantity = parseInt($row.find('.cart-qty-input').val(), 10) || 1;
                        let selectedWarehouse = $row.data('selected-warehouse') || $row.data('default-warehouse') || 'Main Shop'; // Use default warehouse if no specific selection
                        
                        if (productId && quantity > 0) {
                            let stockPromise = $.ajax({
                                url: 'subtract_stock.php',
                                type: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({
                                    product_id: productId,
                                    quantity: quantity,
                                    warehouse: selectedWarehouse
                                }),
                                dataType: 'json'
                            });
                            stockSubtractionPromises.push(stockPromise);
                        }
                    });
                    
                    // Wait for all stock subtractions to complete
                    Promise.all(stockSubtractionPromises).then(function() {
                        let msg = 'Transaction saved successfully!';
                        if (response.invoice_no) {
                            msg += ' Invoice No: ' + response.invoice_no;
                        }
                        showCreditorMessagePopup(msg, true);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }).catch(function(error) {
                        showCreditorMessagePopup('Transaction saved but there was an issue with stock updates.', false);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    });
                } else {
                    showCreditorMessagePopup('Error: ' + (response.error || 'Unknown error'), false);
                }
            },
            error: function() {
                showCreditorMessagePopup('An error occurred while saving the transaction.', false);
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
            $(function() {
                // Define formatNumber function for PLU modal
                function formatNumber(val) {
                    return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                
                // --- Price Look Up Modal Autocomplete Fix ---
                $('#productListModal').on('shown.bs.modal', function () {
                    console.log('PLU Modal shown'); // Debug log
                    
                    // Destroy any previous autocomplete to avoid duplicates
                    if ($("#lookupSearch").data("ui-autocomplete")) {
                        $("#lookupSearch").autocomplete("destroy");
                    }
                    
                    // Ensure the search input is ready
                    $("#lookupSearch").val('').focus();
                    
                    $("#lookupSearch").autocomplete({
                        source: function(request, response) {
                            console.log('PLU searching for:', request.term); // Debug log
                            $.ajax({
                                url: "autocomplete_products.php",
                                dataType: "json",
                                data: { term: request.term },
                                success: function(data) {
                                    console.log('PLU autocomplete results:', data); // Debug log
                                    response(data);
                                },
                                error: function(xhr, status, error) {
                                    console.log('PLU autocomplete error:', error);
                                    console.log('Status:', status);
                                    console.log('Response:', xhr.responseText);
                                    response([]);
                                }
                            });
                        },
                        minLength: 2,
                        select: function(event, ui) {
                            console.log('PLU selected item:', ui.item); // Debug log
                            let row = `
                                <tr class='align-middle'>
                                    <td>${ui.item.label}</td>
                                    <td class='text-center'>${ui.item.unit || '-'}</td>
                                    <td class='text-end'>${formatNumber(ui.item.selling_price)}</td>
                                    <td class='text-center'>${Number(ui.item.stock_level || 0).toLocaleString('en-US')}</td>
                                </tr>
                            `;
                            $("#lookupResults").html(row);
                            return false;
                        },
                        appendTo: "#productListModal",
                        position: { my: "left top", at: "left bottom", collision: "flip" }
                    });
                });

                $('#productListModal').on('hidden.bs.modal', function () {
                    $("#lookupSearch").val('');
                    $("#lookupResults").html(`
                        <tr class="text-muted text-center">
                            <td colspan="4"><i class="fas fa-box-open me-2"></i>No products found</td>
                        </tr>
                    `);
                });
            });
        </script>

        <script>
            $(function() {
                // Autocomplete for Creditor Name in Payment Method modal
                $("#creditorNameInput").autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: "autocomplete_creditors.php",
                            dataType: "json",
                            data: { term: request.term },
                            success: function(data) {
                                console.log('Creditor autocomplete results:', data); // Debug log
                                response(data);
                            }
                        });
                    },
                    minLength: 2,
                    select: function(event, ui) {
                        $("#creditorNameInput").val(ui.item.value);
                        selectedCreditorName = ui.item.value;
                        selectedCreditorId = ui.item.creditor_id; // Store the ID
                        // Fetch creditor type
                        $.get('get_creditor_type.php', { name: ui.item.value }, function(res) {
                            if (res && res.success) {
                                selectedCreditorType = res.type;
                            } else {
                                selectedCreditorType = null;
                            }
                        }, 'json');
                        return false;
                    },
                    appendTo: "#paymentMethodModal"
                });
            });
        </script>

        <!-- Cart Item Details Modal -->
<div class="modal fade" id="cartItemModal" tabindex="-1" aria-labelledby="cartItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartItemModalLabel">
                    <i class="fas fa-info-circle me-2"></i>Cart Item Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <h6 class="fw-bold text-primary">Product Information</h6>
                        <hr class="mt-1 mb-3">
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" id="modalProductName" class="form-control" readonly>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Unit</label>
                        <input type="text" id="modalUnit" class="form-control" readonly>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Available Stock</label>
                        <input type="text" id="modalStock" class="form-control" readonly>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Retrieve from</label>
                        <select id="modalRetrieveFrom" class="form-select">
                            <option value="">Loading warehouses...</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Unit Price (₱)</label>
                        <input type="text" id="modalUnitPrice" class="form-control" readonly>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Current Quantity</label>
                        <div class="input-group">
                            <button class="btn btn-outline-secondary" type="button" id="modalQtyMinus">-</button>
                            <input type="number" id="modalQuantity" class="form-control text-center" min="1">
                            <button class="btn btn-outline-secondary" type="button" id="modalQtyPlus">+</button>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <h6 class="fw-bold text-primary mt-3">Pricing Adjustments</h6>
                        <hr class="mt-1 mb-3">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Discount per Unit (₱)</label>
                        <input type="number" id="modalDiscount" class="form-control" min="0" step="0.01" placeholder="0.00">
                        <small class="text-muted">Maximum discount: ₱<span id="maxDiscountAmount">0.00</span></small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Markup per Unit (₱)</label>
                        <input type="number" id="modalMarkup" class="form-control" min="0" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="col-md-12">
                        <h6 class="fw-bold text-primary mt-3">Summary</h6>
                        <hr class="mt-1 mb-3">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Price per Unit After Adjustments</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="text" id="modalAdjustedPrice" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Total Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="text" id="modalTotalAmount" class="form-control fw-bold" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="removeFromCartBtn">
                    <i class="fas fa-trash me-1"></i>Remove from Cart
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateCartItemBtn">
                    <i class="fas fa-check me-1"></i>Update Item
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    let currentEditingRow = null;
    
    // Function to format numbers
    function formatNumber(val) {
        return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    // Function to calculate modal totals
    function updateModalTotals() {
        let unitPrice = parseFloat($('#modalUnitPrice').val().replace(/[^\d.]/g, '')) || 0;
        let quantity = parseInt($('#modalQuantity').val()) || 1;
        let discount = parseFloat($('#modalDiscount').val()) || 0;
        let markup = parseFloat($('#modalMarkup').val()) || 0;
        
        // Limit discount to unit price
        if (discount > unitPrice) {
            discount = unitPrice;
            $('#modalDiscount').val(discount.toFixed(2));
        }
        
        let adjustedPrice = unitPrice - discount + markup;
        if (adjustedPrice < 0) adjustedPrice = 0;
        
        let totalAmount = adjustedPrice * quantity;
        
        $('#modalAdjustedPrice').val(formatNumber(adjustedPrice));
        $('#modalTotalAmount').val(formatNumber(totalAmount));
        $('#maxDiscountAmount').text(formatNumber(unitPrice));
    }
    
    // Event handlers for modal inputs
    $('#modalQuantity, #modalDiscount, #modalMarkup').on('input change', function() {
        updateModalTotals();
    });
    
    // Quantity buttons
    $('#modalQtyMinus').on('click', function() {
        let $input = $('#modalQuantity');
        let val = parseInt($input.val()) || 1;
        if (val > 1) {
            $input.val(val - 1);
            updateModalTotals();
        }
    });
    
    $('#modalQtyPlus').on('click', function() {
        let $input = $('#modalQuantity');
        let val = parseInt($input.val()) || 1;
        let maxStock = parseInt($input.attr('max')) || 9999;
        if (val < maxStock) {
            $input.val(val + 1);
            updateModalTotals();
        } else {
            showQtyWarningPopup(maxStock);
        }
    });
    
    // Click handler for cart rows (excluding action buttons)
    $('.product-list-table').on('click', 'tbody tr:not(.text-muted)', function(e) {
        // Don't trigger if clicking on buttons, inputs, or action column
        if ($(e.target).closest('button, input, .btn, td:last-child').length > 0) {
            return;
        }
        
        let $row = $(this);
        currentEditingRow = $row;
        
        // Get product ID and default warehouse from the row
        let productId = $row.data('product-id');
        let defaultWarehouse = $row.data('default-warehouse') || 'Main Shop';
        
        // Extract data from the row
        let productName = $row.find('td:eq(0)').text();
        let unit = $row.find('td:eq(1)').text();
        let stock = $row.find('td:eq(2)').text();
        let quantity = parseInt($row.find('.cart-qty-input').val()) || 1;
        let unitPrice = parseFloat($row.find('td:eq(4)').text().replace(/[^\d.]/g, '')) || 0;
        let discount = parseFloat($row.find('.cart-discount-input').val()) || 0;
        let markup = parseFloat($row.find('.cart-markup-input').val()) || 0;
        let maxStock = parseInt($row.find('.cart-qty-input').attr('max')) || 9999;
        
        // Populate modal fields
        $('#modalProductName').val(productName);
        $('#modalUnit').val(unit);
        $('#modalStock').val(stock);
        $('#modalQuantity').val(quantity).attr('max', maxStock);
        $('#modalUnitPrice').val(formatNumber(unitPrice));
        $('#modalDiscount').val(discount || '').attr('max', unitPrice);
        $('#modalMarkup').val(markup || '');
        
        // Load available warehouses for this product
        if (productId) {
            $.ajax({
                url: 'get_product_warehouses.php',
                type: 'GET',
                data: { product_id: productId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.warehouses.length > 0) {
                        let $dropdown = $('#modalRetrieveFrom');
                        $dropdown.empty();
                        
                        response.warehouses.forEach(function(warehouse) {
                            let option = `<option value="${warehouse.location}" data-stock="${warehouse.stock}">${warehouse.location} (${warehouse.stock} available)</option>`;
                            $dropdown.append(option);
                        });
                        
                        // Set the default warehouse from the row data
                        let defaultOption = $dropdown.find(`option[value="${defaultWarehouse}"]`);
                        if (defaultOption.length > 0) {
                            $dropdown.val(defaultWarehouse);
                        } else {
                            // Fallback to Main Shop if default not available
                            let mainShopOption = $dropdown.find('option[value="Main Shop"]');
                            if (mainShopOption.length > 0) {
                                $dropdown.val('Main Shop');
                            } else {
                                // If Main Shop not available, select first option
                                $dropdown.val($dropdown.find('option:first').val());
                            }
                        }
                        
                        // Update max quantity based on selected warehouse
                        updateMaxQuantityFromWarehouse();
                    } else {
                        $('#modalRetrieveFrom').html('<option value="">No warehouses available</option>');
                    }
                },
                error: function() {
                    $('#modalRetrieveFrom').html('<option value="">Error loading warehouses</option>');
                }
            });
        }
        
        // Update totals
        updateModalTotals();
        
        // Show modal
        $('#cartItemModal').modal('show');
    });
    
    // Function to update max quantity based on selected warehouse
    function updateMaxQuantityFromWarehouse() {
        let selectedOption = $('#modalRetrieveFrom option:selected');
        let maxStock = parseInt(selectedOption.data('stock')) || 0;
        let currentQty = parseInt($('#modalQuantity').val()) || 1;
        
        $('#modalQuantity').attr('max', maxStock);
        
        // If current quantity exceeds max stock, adjust it
        if (currentQty > maxStock) {
            $('#modalQuantity').val(maxStock);
            updateModalTotals();
        }
        
        // Update stock display
        $('#modalStock').val(maxStock);
    }
    
    // Handle warehouse selection change
    $('#modalRetrieveFrom').on('change', function() {
        updateMaxQuantityFromWarehouse();
    });
    
    // Update cart item
    $('#updateCartItemBtn').on('click', function() {
        if (!currentEditingRow) return;

        let quantity = parseInt($('#modalQuantity').val()) || 1;
        let discount = parseFloat($('#modalDiscount').val()) || 0;
        let markup = parseFloat($('#modalMarkup').val()) || 0;
        let selectedWarehouse = $('#modalRetrieveFrom').val();
        let selectedWarehouseStock = parseInt($('#modalRetrieveFrom option:selected').data('stock')) || 0;

        // Store the selected warehouse in the row data
        currentEditingRow.data('selected-warehouse', selectedWarehouse);
        currentEditingRow.data('default-warehouse', selectedWarehouse); // update default warehouse

        // Update the original row
        currentEditingRow.find('.cart-qty-input').val(quantity);
        currentEditingRow.find('.cart-discount-input').val(discount || '');
        currentEditingRow.find('.cart-markup-input').val(markup || '');

        // Update the stock cell in the cart row to match the selected warehouse
        currentEditingRow.find('td:eq(2)').text(selectedWarehouseStock.toLocaleString('en-US'));

        // Trigger change to update totals
        currentEditingRow.find('.cart-qty-input').trigger('change');

        // Close modal
        $('#cartItemModal').modal('hide');

        showCreditorMessagePopup('Cart item updated successfully!', true);
    });
    
    // Remove from cart
    $('#removeFromCartBtn').on('click', function() {
        if (!currentEditingRow) return;
        
        // Trigger the remove button click
        currentEditingRow.find('.remove-product').trigger('click');
        
        // Close modal
        $('#cartItemModal').modal('hide');
        
        showCreditorMessagePopup('Item removed from cart!', true);
    });
    
    // Clear current editing row when modal is hidden
    $('#cartItemModal').on('hidden.bs.modal', function() {
        currentEditingRow = null;
    });
});
</script>

<style>
/* Add some visual feedback for clickable rows */
.product-list-table tbody tr:not(.text-muted) {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.product-list-table tbody tr:not(.text-muted):hover {
    background-color: #f8f9fa;
}

.product-list-table tbody tr:not(.text-muted) td:last-child {
    cursor: default;
}

/* Modal styling enhancements */
#cartItemModal .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

#cartItemModal .form-control[readonly] {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

#cartItemModal .text-primary {
    color: #0d6efd !important;
}

#cartItemModal hr {
    margin: 0.5rem 0 1rem 0;
    opacity: 0.3;
}

#modalTotalAmount {
    background-color: #e7f3ff;
    font-weight: bold;
    color: #0d6efd;
}

/* PLU Modal Autocomplete Fix */
#productListModal .ui-autocomplete {
    z-index: 9999 !important;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#productListModal .ui-menu-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

#productListModal .ui-menu-item:hover {
    background-color: #f8f9fa;
}

#productListModal .ui-menu-item:last-child {
    border-bottom: none;
}
</style>

</body>
</html>
