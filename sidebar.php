<?php
include('mycon.php');


if (!isset($activePage)) $activePage = '';
$displayRole = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'Admin - Owner' : 'Cashier - Employee';
$displayRoleLabel = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'System Administrator' : 'Employee';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-container p-0 m-0 text-center">
            <img src="images/dh-logo.png" alt="logo" class="logo-img" height="50px">
            <!-- <div class="logo-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="logo-text">
                <h4>DH AUTOCARE</h4>
                <span>Information System</span>
            </div> -->
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item<?php if ($activePage === 'dashboard') echo ' active'; ?>">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item<?php if ($activePage === 'pos') echo ' active'; ?>">
                <a href="pos.php" class="nav-link">
                    <i class="fas fa-cash-register"></i>
                    <span>Point of Sale</span>
                </a>
            </li>
            <li class="nav-item<?php if ($activePage === 'transaction_history') echo ' active'; ?>">
                <a href="transaction_history.php" class="nav-link">
                    <i class="fas fa-history"></i>
                    <span>Transaction History</span>
                </a>
            </li>
            <li class="nav-item<?php if ($activePage === 'inventory') echo ' active'; ?>">
                <a href="inventory.php" class="nav-link">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item<?php if ($activePage === 'creditor_list') echo ' active'; ?>">
                <a href="creditor_list.php" class="nav-link">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit & Collection</span>
                </a>
            </li>
            <li class="nav-item<?php if ($activePage === 'supplier') echo ' active'; ?>">
                <a href="supplier.php" class="nav-link">
                    <i class="fas fa-people-arrows"></i>
                    <span>Purchases</span>
                </a>
            </li>

            <li class="nav-item<?php if ($activePage === 'reports') echo ' active'; ?>">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>


            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li class="nav-item<?php if ($activePage === 'account_management') echo ' active'; ?>">
                <a href="account_management.php" class="nav-link">
                    <i class="fas fa-users-cog"></i>
                    <span>Account Management</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <!-- Profile Section -->
    <div class="profile-section">
        <div class="profile-info">
            <div class="profile-avatar">
                <img src="images/default_icon.jpg" alt="Profile Picture">
                <div class="status-indicator"></div>
            </div>
            <div class="profile-details">
                <h6 class="profile-name"><?php echo $displayRole; ?></h6>
                <span class="profile-role"><?php echo $displayRoleLabel; ?></span>
            </div>
            <div class="profile-actions">
                <button class="btn-profile" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to logout?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div> 