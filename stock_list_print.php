<?php
    include('mycon.php');
    session_start();

    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit();
    }

    // Get filters from URL parameters
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $brand = isset($_GET['brand']) ? $_GET['brand'] : '';
    $location = isset($_GET['location']) ? $_GET['location'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $direction = isset($_GET['direction']) ? $_GET['direction'] : 'asc';

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

    // Sorting
    $orderBy = "m.item_name $direction";
    if ($sort === 'price') {
        $orderBy = "m.selling_price $direction";
    } elseif ($sort === 'stock') {
        $orderBy = "i.stock_level $direction";
    }

    // Get all data for printing (no pagination)
    $sql = "SELECT i.*, m.item_name, m.oem_number, m.product_size, m.category, m.product_brand, m.selling_price, m.unit, m.date_added, m.status FROM tbl_inventory i JOIN tbl_masterlist m ON i.product_id = m.product_id $whereClause ORDER BY $orderBy";
    $stmt = $mysqli->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">

    <!-- for the icons -->  
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">

    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">

    <link rel="stylesheet" href="css/style.css">

    <title>Stock List - Print</title>
    
    <style>
        @media print {
            body {
                font-size: 12pt !important;
                line-height: 1.2 !important;
                margin: 0 !important;
                padding: 20px !important;
                background: white !important;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            
            .print-header h1 {
                font-size: 18pt !important;
                font-weight: bold;
                margin: 0;
                color: #333;
            }
            
            .print-header p {
                font-size: 12pt !important;
                margin: 5px 0 0 0;
                color: #666;
            }
            
            .table {
                font-size: 10pt !important;
                width: 100% !important;
                margin-bottom: 20px !important;
            }
            
                         .table th {
                 font-size: 10pt !important;
                 font-weight: bold !important;
                 background-color: #f8f9fa !important;
                 border: 1px solid #dee2e6 !important;
                 padding: 8px !important;
                 text-align: left !important;
             }
             
             .table td {
                 font-size: 10pt !important;
                 border: 1px solid #dee2e6 !important;
                 padding: 6px 8px !important;
                 vertical-align: middle !important;
             }
             
             /* Adjust column widths for stock level and price */
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
            
            .table-striped tbody tr:nth-of-type(odd) {
                background-color: #f8f9fa !important;
            }
            
            .text-center {
                text-align: center !important;
            }
            
            .text-end {
                text-align: right !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-summary {
                font-size: 10pt !important;
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #dee2e6;
            }
        }
        
        /* Screen styles */
        body {
            font-size: 12pt;
            line-height: 1.2;
            padding: 20px;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .print-header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0;
            color: #333;
        }
        
        .print-header p {
            font-size: 12pt;
            margin: 5px 0 0 0;
            color: #666;
        }
        
        .table {
            font-size: 10pt;
            width: 100%;
        }
        
                 .table th {
             font-size: 10pt;
             font-weight: bold;
             background-color: #f8f9fa;
             border: 1px solid #dee2e6;
             padding: 8px;
         }
         
         .table td {
             font-size: 10pt;
             border: 1px solid #dee2e6;
             padding: 6px 8px;
             vertical-align: middle;
         }
         
         /* Adjust column widths for stock level and price */
         .table th:nth-child(5),
         .table td:nth-child(5) {
             width: 80px;
             min-width: 80px;
             max-width: 80px;
             text-align: center;
         }
         
         .table th:nth-child(6),
         .table td:nth-child(6) {
             width: 100px;
             min-width: 100px;
             max-width: 100px;
             text-align: right;
         }
        
        .print-summary {
            font-size: 10pt;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h1>DOUBLE HAPPINESS AUTOCARE AND GENERAL MERCHANDISE</h1>
        <p>Stock List Report</p>
        <p>Generated on: <?php echo date('F j, Y'); ?></p>
    </div>

    <table class="table table-bordered table-striped">
        <thead>
            <tr class='text-center'>
                <th>Product</th>
                <th>Part No.</th>
                <th>Size</th>
                <th>Brand</th>
                <th>Stock Level</th>
                <th>Price (₱)</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                $totalItems = 0;
                $totalValue = 0;
                
                while($row = $result->fetch_assoc()) {
                    if ($row['stock_level'] == 0) {
                        continue;
                    }
                    $totalItems++;
                    $totalValue += ($row['stock_level'] * $row['selling_price']);
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['oem_number']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['product_size']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['product_brand']) . "</td>";
                    echo "<td class='text-center'>" . number_format($row['stock_level']) . "</td>";
                    echo "<td class='text-end'>" . number_format($row['selling_price'], 2) . "</td>";
                    echo "<td class='text-center'>" . htmlspecialchars($row['storage_location']) . "</td>";
                    echo "</tr>";
                }
                
                if ($totalItems == 0) {
                    echo "<tr><td colspan='7' class='text-center'>No items found</td></tr>";
                }
            } else {
                echo "<tr><td colspan='7' class='text-center'>No items found</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <div class="print-summary">
        <p><strong>Total Items:</strong> <?php echo $totalItems ?? 0; ?></p>
        <p><strong>Total Inventory Value:</strong> ₱<?php echo number_format($totalValue ?? 0, 2); ?></p>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>