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
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 5;
$offset = ($page - 1) * $itemsPerPage;

// Build WHERE clause
$where = [];
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

// Get direction for sorting
$direction = (isset($_GET['direction']) && strtolower($_GET['direction']) === 'desc') ? 'DESC' : 'ASC';
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
$sql = "SELECT i.*, m.item_name, m.oem_number, m.product_size, m.category, m.product_brand, m.selling_price, m.unit, m.date_added, m.status FROM tbl_inventory i JOIN tbl_masterlist m ON i.product_id = m.product_id $whereClause ORDER BY $orderBy LIMIT $itemsPerPage OFFSET $offset";
$stmt = $mysqli->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Results summary
?>
<div class="results-summary mb-3">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <div class="search-container" style="max-width: 420px;">
                <input type="text" name="search" id="search" placeholder="Search by Product Name" autocomplete="off">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <button class="btn btn-outline-secondary" onclick="printStockList()">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
        <p class="text-muted mb-0">
            Showing <span id="showing-count"><?php echo $startItem; ?>-<?php echo $endItem; ?></span> of <span id="total-count"><?php echo $totalItems; ?></span> items
        </p>
    </div>
</div>
<div class="history">

<div class="table-responsive">
    <table class='table table-hover'>
        <thead>
            <tr class='align-middle'>
                <th>Product</th>
                <th>Part Number/Size</th>
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
                    if ($row['stock_level'] == 0) {
                        continue;
                    }
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                    // Display either part number, size, or blank space
                    $partNumberOrSize = '';
                    if (!empty($row['oem_number'])) {
                        $partNumberOrSize = $row['oem_number'];
                    } elseif (!empty($row['product_size'])) {
                        $partNumberOrSize = $row['product_size'];
                    }
                    echo "<td>" . htmlspecialchars($partNumberOrSize) . "</td>";
                    echo "<td>" . htmlspecialchars($row['product_brand']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                    echo "<td class='text-center'>" . htmlspecialchars($row['stock_level']) . "</td>";
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
