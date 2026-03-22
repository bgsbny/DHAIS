<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// backup.php: Create MySQL backup and manage retention
// For 30-minute intervals, use this script with Task Scheduler set to run every 30 minutes
$backups_dir = __DIR__ . '/backups/';
if (!is_dir($backups_dir)) {
    mkdir($backups_dir, 0755, true);
}

// DB credentials (match mycon.php)
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'dhautocare';

// Backup file name with timestamp
$backup_name = 'dh_autocare_backup_' . date('Y-m-d_H-i-s') . '.sql';
$backup_path = $backups_dir . $backup_name;

// Use full path to mysqldump in XAMPP
$mysqldump_path = 'C:/xampp/mysql/bin/mysqldump.exe';

// Run mysqldump
$command = "\"$mysqldump_path\" --user=$dbuser --password=$dbpass --host=$dbhost $dbname > \"$backup_path\"";
$output = null;
$return_var = null;
exec($command, $output, $return_var);

if (file_exists($backup_path) && filesize($backup_path) > 0) {
    echo "✅ Backup created: $backup_name\n";
} else {
    echo "❌ Backup failed.\n";
    exit(1);
}

// Retention: keep only the 20 most recent backups (for 30-minute intervals)
// This keeps 10 hours of backups (20 * 30 minutes = 10 hours)
$files = glob($backups_dir . 'dh_autocare_backup_*.sql');
if ($files && count($files) > 20) {
    // Sort by filemtime ascending (oldest first)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    $to_delete = array_slice($files, 0, count($files) - 20);
    foreach ($to_delete as $old_file) {
        @unlink($old_file);
    }
}
?>