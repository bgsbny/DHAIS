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

    <title>DH AUTOCARE - Reports</title>
</head>
<body>
    <?php $activePage = 'reports'; include 'navbar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Reports & Analytics</h1>
                    <p class="page-subtitle">Generate comprehensive reports and analyze business performance.</p>
                </div>
            </div>
        </header>

        <div class="main-container">
            <!-- Reports Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <a href='sales_report.php' style='text-decoration:none;'>
                        <div class="card stat-card h-100">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4e54c8, #8f94fb);">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label odyssey-text">Sales Report</div>
                                <div class="text-muted small">View daily, monthly, and yearly sales performance.</div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 mb-3">
                    <a href='inventory_report.php' style='text-decoration:none;'>
                        <div class="card stat-card h-100">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label odyssey-text">Inventory Report</div>
                                <div class="text-muted small">Analyze current inventory levels and trends.</div>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-3">
                    <a href='credit_report.php' style='text-decoration:none;'>
                        <div class="card stat-card h-100">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #00c6ff, #0072ff);">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label odyssey-text">Credit & Collection Report</div>
                                <div class="text-muted small">Track outstanding credits and collections.</div>
                            </div>
                        </div>
                    </a>
                </div>

            </div>
        </div>
    </main>
</body>
</html>
