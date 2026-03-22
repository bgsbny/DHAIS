<?php
    include('mycon.php');
    session_start();

    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit();
    }

    // Pagination setup
    $itemsPerPage = 5;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $itemsPerPage;

    // Get unique categories and brands from tbl_masterlist
    $categoryOptions = [];
    $brandOptions = [];
    $catResult = $mysqli->query("SELECT DISTINCT category FROM tbl_masterlist WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    while ($row = $catResult->fetch_assoc()) {
        $categoryOptions[] = $row['category'];
    }
    $brandResult = $mysqli->query("SELECT DISTINCT product_brand FROM tbl_masterlist WHERE product_brand IS NOT NULL AND product_brand != '' ORDER BY product_brand ASC");
    while ($row = $brandResult->fetch_assoc()) {
        $brandOptions[] = $row['product_brand'];
    }

    // Count total items
    $countSql = "SELECT COUNT(*) as total FROM tbl_inventory i JOIN tbl_masterlist m ON i.product_id = m.product_id";
    $countResult = $mysqli->query($countSql);
    $totalItems = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    $startItem = $totalItems > 0 ? $offset + 1 : 0;
    $endItem = min($offset + $itemsPerPage, $totalItems);
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
        .sort-indicator {
            opacity: 0.55;
            font-size: 0.9em;
            margin-left: 6px;
        }
        #creditorTabs .btn {
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
    </style>

    <title>DH AUTOCARE - Reorder List</title>
</head>
<body>
    <?php $activePage = 'inventory'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Reorder List</h1>
                    <p class="page-subtitle">Track items that need to be reordered and manage stock levels.</p>
                </div>
            </div>
        </header>

        <div class="main-container">
            <div class="row mb-3 align-items-end gx-2">
                <div class="col-lg-2 col-md-3 col-6 mb-2">
                    <label for="filterCategory" class="form-label mb-1">Category</label>
                    <select id="filterCategory" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categoryOptions as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-2">
                    <label for="filterBrand" class="form-label mb-1">Brand</label>
                    <select id="filterBrand" class="form-select form-select-sm">
                        <option value="">All Brands</option>
                        <?php foreach ($brandOptions as $brand): ?>
                            <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-2">
                    <label for="filterLocation" class="form-label mb-1">Location</label>
                    <select id="filterLocation" class="form-select form-select-sm">
                        <option value="">All Locations</option>
                        <option value="Main Shop" selected>Main Shop</option>
                        <option value="Warehouse 1">Warehouse 1</option>
                        <option value="Warehouse 2">Warehouse 2</option>
                    </select>
                </div>
                <div class="col-lg-6 col-md-3 col-12 mb-2 d-flex align-items-end justify-content-end">
                    <ul class="nav nav-pills mb-0" id="creditorTabs">
                        <li>
                            <button class="btn btn-sm px-3" id="product-tab" type="button" data-sort="name">Product Name<span class="sort-indicator"></span></button>
                        </li>
                        <li>
                            <button class="btn btn-sm px-3" id="price-tab" type="button" data-sort="price">Price<span class="sort-indicator"></span></button>
                        </li>
                        <li>
                            <button class="btn active-tab btn-sm px-3" id="stock-tab" type="button" data-sort="stock">Stock Level<span class="sort-indicator"></span></button>
                        </li>
                    </ul>
                </div>
            </div>
            <div id="stock-list-content"></div>
        </div>
        <script>
$(document).ready(function() {
    let currentSort = 'stock';
    let currentDirection = 'asc';

    function updateSortIndicators() {
        $('#creditorTabs .sort-indicator').text('');
        const arrow = currentDirection === 'asc' ? '▲' : '▼';
        const $btn = $(`#creditorTabs button[data-sort="${currentSort}"]`);
        $btn.find('.sort-indicator').text(` ${arrow}`);
    }

    function getFilters() {
        return {
            category: $('#filterCategory').val(),
            brand: $('#filterBrand').val(),
            location: $('#filterLocation').val(),
            sort: currentSort,
            direction: currentDirection,
            page: $('#stock-list-content').data('page') || 1
        };
    }
    function loadStockList(params) {
        $('#stock-list-content').html('<div class="text-center py-5"><span class="spinner-border"></span></div>');
        $.get('reorder_list_data.php', params, function(data) {
            $('#stock-list-content').html(data);
        });
    }
    function reloadStockList(page) {
        var params = getFilters();
        if (page) params.page = page;
        loadStockList(params);
    }
    // Initial load
    reloadStockList(1);
    updateSortIndicators();
    // Filter change
    $('#filterCategory, #filterBrand, #filterLocation').on('change', function() {
        reloadStockList(1);
    });
    // Tab click
    $('#creditorTabs button').on('click', function() {
        let sortType = $(this).data('sort');
        if (currentSort === sortType) {
            // Toggle direction
            currentDirection = (currentDirection === 'asc') ? 'desc' : 'asc';
        } else {
            currentSort = sortType;
            currentDirection = 'asc';
        }
        $('#creditorTabs button').removeClass('active-tab');
        $(this).addClass('active-tab');
        reloadStockList(1);
        updateSortIndicators();
    });
    // Pagination click (delegated)
    $('#stock-list-content').on('click', '.pagination .page-link', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page && !$(this).parent().hasClass('active') && !$(this).parent().hasClass('disabled')) {
            reloadStockList(page);
        }
    });
    
    // Print function
    window.printReorderList = function() {
        var params = getFilters();
        var queryString = Object.keys(params)
            .filter(key => params[key] !== '' && params[key] !== null)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
            .join('&');
        
        var printUrl = 'reorder_list_print.php';
        if (queryString) {
            printUrl += '?' + queryString;
        }
        
        window.open(printUrl, '_blank');
    };
});
</script>
    </main>
</body>
</html>
