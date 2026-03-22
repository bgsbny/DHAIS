<?php
include('mycon.php');
session_start();

// Get filters and sort from GET
$category = isset($_GET['category']) ? $_GET['category'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$direction = (isset($_GET['direction']) && strtolower($_GET['direction']) === 'desc') ? 'DESC' : 'ASC';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 5;
$offset = ($page - 1) * $itemsPerPage;

// Build WHERE clause
$where = [];
$params = [];
if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
}
if ($brand !== '') {
    $where[] = 'product_brand = ?';
    $params[] = $brand;
}
if ($search !== '') {
    $where[] = '(item_name LIKE ? OR oem_number LIKE ? OR product_brand LIKE ? OR category LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Sorting
$orderBy = "item_name $direction";
if ($sort === 'price') {
    $orderBy = "selling_price $direction";
} elseif ($sort === 'stock') {
    $orderBy = "product_id $direction"; // No stock_level, so use product_id as fallback
}

// Count total items
$countSql = "SELECT COUNT(*) as total FROM tbl_masterlist $whereClause";
$countStmt = $mysqli->prepare($countSql);
if ($params) {
    $types = str_repeat('s', count($params));
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalItems = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);
$startItem = $totalItems > 0 ? $offset + 1 : 0;
$endItem = min($offset + $itemsPerPage, $totalItems);

// Get paginated data
$sql = "SELECT m.*, COALESCE(SUM(i.stock_level),0) AS total_stock
        FROM tbl_masterlist m
        LEFT JOIN tbl_inventory i ON i.product_id = m.product_id
        $whereClause
        GROUP BY m.product_id
        ORDER BY $orderBy
        LIMIT $itemsPerPage OFFSET $offset";
$stmt = $mysqli->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    // Sanitize and get fields
    $item_name = trim($_POST['item_name'] ?? '');
    $oem_number = trim($_POST['oem_number'] ?? '');
    $product_size = trim($_POST['product_size'] ?? '');
    $product_brand = trim($_POST['product_brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $movement_tag = trim($_POST['movement_tag'] ?? 'normal');
    $date_added = trim($_POST['date_added'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'Active');

    // Validate required fields
    if (empty($item_name)) {
        echo json_encode(['success' => false, 'error' => 'Product name is required']);
        exit;
    }
    if ($selling_price <= 0) {
        echo json_encode(['success' => false, 'error' => 'Selling price must be greater than 0']);
        exit;
    }
    if (empty($unit)) {
        echo json_encode(['success' => false, 'error' => 'Unit of measurement is required']);
        exit;
    }

    // Insert into tbl_masterlist
    $stmt = $mysqli->prepare("INSERT INTO tbl_masterlist (item_name, oem_number, product_size, product_brand, category, selling_price, unit, movement_tag, date_added, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssdssss', $item_name, $oem_number, $product_size, $product_brand, $category, $selling_price, $unit, $movement_tag, $date_added, $status);
    
    if ($stmt->execute()) {
        $product_id = $stmt->insert_id;
        $stmt->close();
        
        // Insert into tbl_inventory with default values
        $stmt2 = $mysqli->prepare("INSERT INTO tbl_inventory (product_id, stock_level, storage_location, last_updated) VALUES (?, 0, 'Main Shop', NOW())");
        $stmt2->bind_param('i', $product_id);
        
        if ($stmt2->execute()) {
            $stmt2->close();
            echo json_encode(['success' => true, 'product_id' => $product_id]);
            exit;
        } else {
            // If inventory insert fails, we should rollback the masterlist insert
            // For now, just return error
            echo json_encode(['success' => false, 'error' => 'Failed to create inventory record: ' . $stmt2->error]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add product: ' . $stmt->error]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_movement_tag') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $movement_tag = $_POST['movement_tag'] ?? '';
    if ($product_id > 0) {
        $stmt = $mysqli->prepare("UPDATE tbl_masterlist SET movement_tag = ? WHERE product_id = ?");
        $stmt->bind_param('si', $movement_tag, $product_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $item_name = trim($_POST['item_name'] ?? '');
    $oem_number = trim($_POST['oem_number'] ?? '');
    $product_size = trim($_POST['product_size'] ?? '');
    $product_brand = trim($_POST['product_brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $movement_tag = trim($_POST['movement_tag'] ?? '');
    $date_added = trim($_POST['date_added'] ?? date('Y-m-d'));
    $status = trim($_POST['status'] ?? 'Active');
    
    // Validate required fields
    if (empty($item_name)) {
        echo json_encode(['success' => false, 'error' => 'Product name is required']);
        exit;
    }
    if ($selling_price <= 0) {
        echo json_encode(['success' => false, 'error' => 'Selling price must be greater than 0']);
        exit;
    }
    if (empty($unit)) {
        echo json_encode(['success' => false, 'error' => 'Unit of measurement is required']);
        exit;
    }
    
    if ($product_id > 0) {
        $stmt = $mysqli->prepare("UPDATE tbl_masterlist SET item_name=?, oem_number=?, product_size=?, product_brand=?, category=?, selling_price=?, unit=?, movement_tag=?, date_added=?, status=? WHERE product_id=?");
        $stmt->bind_param('sssssdssssi', $item_name, $oem_number, $product_size, $product_brand, $category, $selling_price, $unit, $movement_tag, $date_added, $status, $product_id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}


?>
<div class="results-summary mb-3">

    <div class="align-items-center d-flex justify-content-between">
                    <div class='d-flex justify-content-between align-items-center' style='width: 650px;'>
                        <div class="search-container">
                            <input type="text" name="search" id="search" placeholder="Search by Product Name" autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <div>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <button type='button' class='btn btn-outline-primary btn-sm' data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fa-solid fa-plus"></i>
                                Add New Product
                            </button>
                        <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-outline-secondary" onclick="printMasterList()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>

                    <div>
                    <p class="text-muted mb-0">
                        Showing <span id="showing-count"><?php echo $startItem; ?>-<?php echo $endItem; ?></span> of <span id="total-count"><?php echo $totalItems; ?></span> items
                    </p>
                    </div>
                </div>


    <div class="row">
        <div class="col-md-9">
            
        </div>

        <div class="col-md-3 d-flex justify-content-between">
            



        </div>
    </div>
</div>

<div class='history'>
    <div class="table-responsive">
    <style>
        .table th:nth-child(5),
        .table td:nth-child(5) {
            width: 100px !important;
            min-width: 100px !important;
            max-width: 100px !important;
            text-align: right !important;
        }
    </style>
    <table class='table table-hover'>
            <thead>
                <tr class='align-middle'>
                    <th>Product</th>
                    <th>Part No.</th>
                    <th>Size</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Price (&#8369;)</th>
                    <th>Total Stock</th>
                    <th class="text-center" colspan="2">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr class='align-middle'>";
                        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['oem_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['product_size']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['product_brand']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                        echo "<td class='text-end'>" . number_format($row['selling_price'], 2) . "</td>";
                        echo "<td>" . (int)$row['total_stock'] . "</td>";
                        echo '<td>
                            <a href="stock_card.php?product_id=' . urlencode($row['product_id']) . '" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-clipboard-list"></i></a>
                            </td>';
                        echo '<td class="text-center"><button class="btn btn-outline-primary btn-sm edit-product-btn" data-bs-toggle="modal" data-bs-target="#editProductModal" data-product=\'' . json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) . '\'><i class="fas fa-edit"></i></button></td>';
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' class='text-center text-muted'>No data found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Master list pagination" class="mt-4">
        <ul class="pagination justify-content-center" id="pagination">
            <!-- Previous button -->
            <li class="page-item <?php echo ($page == 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="#" data-page="<?php echo $page - 1; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <!-- Page numbers (show only 5 at a time) -->
            <?php
                $pageGroup = ceil($page / 5);
                $startPage = ($pageGroup - 1) * 5 + 1;
                $endPage = min($startPage + 4, $totalPages);
                for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <!-- Next button -->
            <li class="page-item <?php echo ($page == $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="#" data-page="<?php echo $page + 1; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php
    $mysqli->close();
    ?>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body custom-modal-body">
        <form id="editProductForm">
          <input type="hidden" id="edit_product_id" name="product_id">
          <div class="mb-3">
            <label for="edit_item_name" class="form-label">Product Name</label>
            <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
          </div>
          <div class="mb-3">
            <label for="edit_oem_number" class="form-label">Part Number <span class='text-muted'>(if applicable)</span></label>
            <input type="text" class="form-control" id="edit_oem_number" name="oem_number">
          </div>
          <div class="mb-3">
            <label for="edit_product_size" class="form-label">Size <span class='text-muted'>(if applicable)</span></label>
            <input type="text" class="form-control" id="edit_product_size" name="product_size">
          </div>
          <div class="mb-3">
            <label for="edit_product_brand" class="form-label">Brand</label>
            <input type="text" class="form-control" id="edit_product_brand" name="product_brand">
          </div>
          <div class="mb-3">
            <label for="edit_category" class="form-label">Category</label>
            <input type="text" class="form-control" id="edit_category" name="category">
          </div>
          <div class="mb-3">
            <label for="edit_selling_price" class="form-label">Selling Price</label>
            <input type="number" class="form-control" id="edit_selling_price" name="selling_price" required>
          </div>
          <div class="mb-3">
            <label for="edit_unit" class="form-label">Unit of Measurement</label>
            <input type="text" class="form-control" id="edit_unit" name="unit">
          </div>
          <div class="mb-3">
            <label for="edit_movement_tag" class="form-label">Movement Tag</label>
            <select name="movement_tag" id="edit_movement_tag" class='form-select'>
                <option value="fast">Fast Moving</option>
                <option value="slow">Slow Moving</option>
                <option value="normal">Normal</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="edit_date_added" class="form-label">Date Added</label>
            <input type="date" class="form-control" id="edit_date_added" name="date_added" required>
          </div>
          <div class="mb-3">
            <label for="edit_status" class="form-label">Status</label>
            <select name="status" id="edit_status" class='form-select'>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
$(document).on('click', '.edit-product-btn', function() {
    var product = $(this).attr('data-product');
    if (typeof product === 'string') {
        product = JSON.parse(product);
    }
    $('#edit_product_id').val(product.product_id);
    $('#edit_item_name').val(product.item_name);
    $('#edit_oem_number').val(product.oem_number);
    $('#edit_product_size').val(product.product_size);
    $('#edit_product_brand').val(product.product_brand);
    $('#edit_category').val(product.category);
    $('#edit_selling_price').val(product.selling_price);
    $('#edit_unit').val(product.unit);
    $('#edit_movement_tag').val(product.movement_tag);
    $('#edit_date_added').val(product.date_added);
    $('#edit_status').val(product.status);
});

$('#editProductForm').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'edit_product'});
    
    // Show loading state
    var submitBtn = $(this).find('button[type="submit"]');
    var originalText = submitBtn.text();
    submitBtn.prop('disabled', true).text('Saving...');
    
    $.ajax({
        url: 'master_list_data.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#editProductModal').modal('hide');
                alert('Product updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.error || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('Error: ' + error);
            console.error(xhr.responseText);
        },
        complete: function() {
            // Reset button state
            submitBtn.prop('disabled', false).text(originalText);
        }
    });
});



// Real-time search functionality
let searchTimeout;
$('#search').on('input', function() {
    clearTimeout(searchTimeout);
    const searchTerm = $(this).val();
    
    // Add loading indicator
    $('.history').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Searching...</div>');
    
    searchTimeout = setTimeout(function() {
        performSearch(searchTerm);
    }, 300); // 300ms delay to avoid too many requests
});

function performSearch(searchTerm) {
    $.ajax({
        url: 'master_list_data.php',
        method: 'GET',
        data: {
            search: searchTerm,
            page: 1, // Reset to first page when searching
            sort: '<?php echo $sort; ?>',
            direction: '<?php echo $direction; ?>',
            category: '<?php echo $category; ?>',
            brand: '<?php echo $brand; ?>'
        },
        success: function(response) {
            // Extract the table content from the response
            const tempDiv = $('<div>').html(response);
            const newTableContent = tempDiv.find('.history').html();
            
            if (newTableContent) {
                $('.history').html(newTableContent);
                
                // Update pagination event handlers
                updatePaginationHandlers();
                
                // Update showing count
                const showingCount = tempDiv.find('#showing-count').text();
                const totalCount = tempDiv.find('#total-count').text();
                $('#showing-count').text(showingCount);
                $('#total-count').text(totalCount);
            }
        },
        error: function() {
            $('.history').html('<div class="text-center py-4 text-danger">Error loading search results</div>');
        }
    });
}

function updatePaginationHandlers() {
    // Remove existing pagination handlers
    $(document).off('click', '.page-link');
    
    // Add new pagination handlers
    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        const searchTerm = $('#search').val();
        
        $.ajax({
            url: 'master_list_data.php',
            method: 'GET',
            data: {
                search: searchTerm,
                page: page,
                sort: '<?php echo $sort; ?>',
                direction: '<?php echo $direction; ?>',
                category: '<?php echo $category; ?>',
                brand: '<?php echo $brand; ?>'
            },
            success: function(response) {
                const tempDiv = $('<div>').html(response);
                const newTableContent = tempDiv.find('.history').html();
                
                if (newTableContent) {
                    $('.history').html(newTableContent);
                    updatePaginationHandlers();
                    
                    // Update showing count
                    const showingCount = tempDiv.find('#showing-count').text();
                    const totalCount = tempDiv.find('#total-count').text();
                    $('#showing-count').text(showingCount);
                    $('#total-count').text(totalCount);
                }
            },
            error: function() {
                $('.history').html('<div class="text-center py-4 text-danger">Error loading page</div>');
            }
        });
    });
}

// Initialize pagination handlers on page load
$(document).ready(function() {
    updatePaginationHandlers();
});
</script>
