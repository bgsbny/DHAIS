<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Allow all logged-in users to access backup management
// Admin users have full access, others can view and download backups

$activePage = 'backup_management';

$backups_dir = 'backups/';
if (!is_dir($backups_dir)) {
    mkdir($backups_dir, 0755, true);
}

if (isset($_POST['create_backup'])) {
    $backup_name = 'dh_autocare_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = $backups_dir . $backup_name;
    
    $dbhost = "localhost";
    $dbuser = "root";
    $dbpass = "";
    $dbname = "dhautocare";
    
    $command = "mysqldump --user=$dbuser --password=$dbpass --host=$dbhost $dbname > $backup_path";
    $output = shell_exec($command . ' 2>&1');
    
    if (file_exists($backup_path) && filesize($backup_path) > 0) {
        $_SESSION['success'] = "Backup created successfully: $backup_name";
    } else {
        $_SESSION['error'] = "Backup failed. Please check if mysqldump is available.";
    }
    
    header('Location: backup_management.php');
    exit();
}

if (isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
    $backup_file = $_POST['backup_file'];
    $file_path = $backups_dir . $backup_file;
    
    if (file_exists($file_path) && unlink($file_path)) {
        $_SESSION['success'] = "Backup deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete backup.";
    }
    
    header('Location: backup_management.php');
    exit();
}

if (isset($_GET['download']) && !empty($_GET['download'])) {
    $backup_file = $_GET['download'];
    $file_path = $backups_dir . $backup_file;
    
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup_file . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit();
    }
}

$backups = [];
if (is_dir($backups_dir)) {
    $files = scandir($backups_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backups_dir . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => filemtime($file_path),
                'path' => $file_path
            ];
        }
    }
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

$auto_backup_interval_hours = 24;
$show_backup_warning = false;
if (!empty($backups)) {
    $last_backup_time = $backups[0]['date'];
    $hours_since_last_backup = (time() - $last_backup_time) / 3600;
    if ($hours_since_last_backup > $auto_backup_interval_hours) {
        $show_backup_warning = true;
    }
} else {
    $show_backup_warning = true;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">
    <script src="js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <script src="js/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/interfont.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php $activePage = 'backup_management'; include 'navbar.php'; ?>

    <main class="main-content">
        <?php if ($show_backup_warning): ?>
            <div class="alert alert-danger text-center fw-bold">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Warning: The last backup is older than 24 hours! Please create a backup or check your auto-backup schedule.
            </div>
        <?php endif; ?>
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Backup & Restore</h1>
                    <p class="page-subtitle">Manage database backups and system restoration.</p>
                </div>
                <div class="header-right">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="create_backup" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Create Backup
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="main-container">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Backups</h6>
                                    <h3 class="mb-0"><?php echo count($backups); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-database fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Latest Backup</h6>
                                    <h6 class="mb-0">
                                        <?php 
                                        if (!empty($backups)) {
                                            echo date('M d, Y H:i', $backups[0]['date']);
                                        } else {
                                            echo 'No backups';
                                        }
                                        ?>
                                    </h6>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Size</h6>
                                    <h6 class="mb-0">
                                        <?php 
                                        $total_size = 0;
                                        foreach ($backups as $backup) {
                                            $total_size += $backup['size'];
                                        }
                                        echo formatFileSize($total_size);
                                        ?>
                                    </h6>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-hdd fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Database</h6>
                                    <h6 class="mb-0">dhautocare</h6>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-server fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history">
                <div class="table-responsive">
                    <h6 class="mb-3">Backup History</h6>
                    <?php if (empty($backups)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No backups found</h5>
                            <p class="text-muted">Create your first backup to get started.</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Backup Name</th>
                                    <th>Date Created</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-database text-primary me-2"></i>
                                            <?php echo htmlspecialchars($backup['name']); ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i:s', $backup['date']); ?></td>
                                        <td><?php echo formatFileSize($backup['size']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?download=<?php echo urlencode($backup['name']); ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#restoreModal"
                                                        data-backup-name="<?php echo htmlspecialchars($backup['name']); ?>"
                                                        title="Restore">
                                                    <i class="fas fa-upload"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal"
                                                        data-backup-name="<?php echo htmlspecialchars($backup['name']); ?>"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore Database</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will overwrite the current database. 
                        All existing data will be replaced with the backup data.
                    </div>
                    <p>Are you sure you want to restore the database from:</p>
                    <p class="fw-bold" id="restore-backup-name"></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirm-restore" class="btn btn-danger">
                        <i class="fas fa-upload me-2"></i>Restore Database
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup?</p>
                    <p class="fw-bold" id="delete-backup-name"></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="backup_file" id="delete-backup-file">
                        <button type="submit" name="delete_backup" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Backup
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="messagePopup"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success'])): ?>
                showMessagePopup('<?php echo addslashes($_SESSION['success']); ?>', true);
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                showMessagePopup('<?php echo addslashes($_SESSION['error']); ?>', false);
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            const restoreModal = document.getElementById('restoreModal');
            if (restoreModal) {
                restoreModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const backupName = button.getAttribute('data-backup-name');
                    const modal = this;
                    modal.querySelector('#restore-backup-name').textContent = backupName;
                    modal.querySelector('#confirm-restore').href = 'restore_database.php?backup=' + encodeURIComponent(backupName);
                });
            }

            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const backupName = button.getAttribute('data-backup-name');
                    const modal = this;
                    modal.querySelector('#delete-backup-name').textContent = backupName;
                    modal.querySelector('#delete-backup-file').value = backupName;
                });
            }
        });

        function showMessagePopup(message, isSuccess = true) {
            const $popup = $("#messagePopup");
            $popup.stop(true, true);
            $popup.text(message);
            $popup.removeClass("popup-success popup-error");
            $popup.addClass(isSuccess ? "popup-success" : "popup-error");
            $popup.fadeIn(200).delay(1800).fadeOut(400);
        }
    </script>
</body>
</html> 