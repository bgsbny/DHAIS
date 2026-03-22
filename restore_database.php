<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

if (isset($_GET['backup']) && !empty($_GET['backup'])) {
    $backup_file = $_GET['backup'];
    $backups_dir = 'backups/';
    $file_path = $backups_dir . $backup_file;
    
    if (file_exists($file_path)) {
        // Database credentials
        $dbhost = "localhost";
        $dbuser = "root";
        $dbpass = "";
        $dbname = "dhautocare";
        
        // Use full path to mysql in XAMPP
        $mysql_path = 'C:/xampp/mysql/bin/mysql.exe';
        
        // Restore command
        $command = "\"$mysql_path\" --user=$dbuser --password=$dbpass --host=$dbhost $dbname < \"$file_path\"";
        $output = null;
        $return_var = null;
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $_SESSION['success'] = "Database restored successfully from: $backup_file";
        } else {
            $_SESSION['error'] = "Database restore failed. Please check the backup file.";
        }
    } else {
        $_SESSION['error'] = "Backup file not found.";
    }
} else {
    $_SESSION['error'] = "No backup file specified.";
}

header('Location: backup_management.php');
exit();
?> 