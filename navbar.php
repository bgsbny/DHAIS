<?php
include('mycon.php');


if (!isset($activePage)) $activePage = '';
$displayRole = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'Admin - Owner' : 'Cashier - Employee';
$displayRoleLabel = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'System Administrator' : 'Employee';

// Auto logout after inactivity (15 minutes)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $timeoutSeconds = 15 * 60; // 15 minutes
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeoutSeconds) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $_SESSION['last_activity'] = $now;
}
?>
    
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <img src="images/dh-logo.png" alt="DH AUTOCARE" class="img-fluid" style="width: 100px;">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'inventory') ? 'active' : ''; ?>" href="inventory.php">Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'supplier') ? 'active' : ''; ?>" href="supplier.php">Purchases</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'sales') ? 'active' : ''; ?>" href="sales.php">Sales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'transaction_history') ? 'active' : ''; ?>" href="transaction_history.php">Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'creditor_list') ? 'active' : ''; ?>" href="creditor_list.php">Credits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'reports') ? 'active' : ''; ?>" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'backup_management') ? 'active' : ''; ?>" href="backup_management.php">Backup & Restore</a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activePage === 'account_management') ? 'active' : ''; ?>" href="account_management.php">Account Management</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="d-flex align-items-center gap-3 text-white">
                <div class="dropdown">
                    <button class="btn btn-link text-white text-decoration-none dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user-circle me-2"></i>
                        <?php echo $displayRole; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><span class="dropdown-item-text text-muted"><?php echo $displayRoleLabel; ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            
        </div>
    </nav>