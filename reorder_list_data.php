<?php
include('mycon.php');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get filters and sort from GET
$category = isset($_GET['category']) ? $_GET['category'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$direction = (isset($_GET['direction']) && strtolower($_GET['direction']) === 'desc') ? 'DESC' : 'ASC';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 5;
$offset = ($page - 1) * $itemsPerPage;

// Build WHERE clause (stock_level <= 5 is considered low)
$where = ['i.stock_level <= 5'];
$params = [];
if ($category !== '') {
    $where[] = 'm.category = ?';
    $params[] = $category;
}
if ($brand !== '') {
    $where[] = 'm.product_brand = ?';
    $params[] = $brand;
}
// Only filter by location if a specific location is selected, otherwise show all locations
if ($location !== '' && $location !== null) {
    $where[] = 'i.storage_location = ?';
    $params[] = $location;
}
if ($search !== '') {
    $where[] = '(m.item_name LIKE ? OR m.oem_number LIKE ? OR m.product_brand LIKE ? OR m.category LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Sorting
$orderBy = "m.item_name $direction";
if ($sort === 'price') {
    $orderBy = "m.selling_price $direction";
} elseif ($sort === 'stock') {
    $orderBy = "i.stock_level $direction";
}

// Count total items
$countSql = "SELECT COUNT(*) as total FROM tbl_inventory i JOIN tbl_masterlist m ON i.product_id = m.product_id $whereClause";
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
$sql = "SELECT i.*, m.item_name, m.oem_number, m.category, m.product_brand, m.selling_price, m.unit, m.date_added, m.status FROM tbl_inventory i JOIN tbl_masterlist m ON i.product_id = m.product_id $whereClause ORDER BY $orderBy LIMIT $itemsPerPage OFFSET $offset";
$stmt = $mysqli->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="results-summary mb-3">
    <div class="row">
        <div class="col-md-6">
            <p class="text-muted mb-0">
                Showing <span id="showing-count"><?php echo $startItem; ?>-<?php echo $endItem; ?></span> of <span id="total-count"><?php echo $totalItems; ?></span> items
            </p>
        </div>

        <div class="col-md-6">
            <div class="d-flex justify-content-end">
                <button class="btn btn-outline-secondary" onclick="printReorderList()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>
<div class="history">
<div class="table-responsive">
    <style>
        .table th:nth-child(5),
        .table td:nth-child(5) {
            width: 80px !important;
            min-width: 80px !important;
            max-width: 80px !important;
            text-align: center !important;
        }
        
        .table th:nth-child(6),
        .table td:nth-child(6) {
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
                <th>Brand</th>
                <th>Category</th>
                <th>Stock Level</th>
                <th>Price (&#8369;)</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $stock = (int)$row['stock_level'];
                    $stockClass = '';
                    if ($stock == 0) {
                        $stockClass = 'text-danger fw-bold';
                    } elseif ($stock <= 5) {
                        $stockClass = 'text-warning fw-bold';
                    } else {
                        $stockClass = 'text-success fw-bold';
                    }
                    echo "<tr class='align-middle'>";
                    echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['oem_number']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['product_brand']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                    echo "<td class='text-center $stockClass'>" . htmlspecialchars($row['stock_level']) . "</td>";
                    echo "<td class='text-end'>" . number_format($row['selling_price'], 2) . "</td>";
                    echo "<td>" . htmlspecialchars($row['storage_location']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7' class='text-center text-muted'>No data found</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav aria-label="Stock pagination" class="mt-4">
    <ul class="pagination justify-content-center" id="pagination">
        <!-- Previous button -->
        <li class="page-item <?php echo ($page == 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="#" data-page="<?php echo $page - 1; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
        <!-- Page numbers -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
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

        </div>
<?php endif; ?>
<?php
$mysqli->close();
?>
