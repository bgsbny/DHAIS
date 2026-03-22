<?php
include('mycon.php');

try {
    // Check if users already exist
    $query = "SELECT COUNT(*) as count FROM tbl_users";
    $result = mysqli_query($mysqli, $query);
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'];
    
    if ($count == 0) {
        // Create default users with proper password hashing
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $employee_password = password_hash('employee123', PASSWORD_DEFAULT);
        
        // Use prepared statements to avoid escaping issues
        $stmt1 = $mysqli->prepare("INSERT INTO tbl_users (username, password, role) VALUES (?, ?, ?)");
        $stmt1->bind_param("sss", $admin_username, $admin_password, $admin_role);
        $admin_username = 'admin';
        $admin_role = 'admin';
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $mysqli->prepare("INSERT INTO tbl_users (username, password, role) VALUES (?, ?, ?)");
        $stmt2->bind_param("sss", $employee_username, $employee_password, $employee_role);
        $employee_username = 'employee';
        $employee_role = 'employee';
        $stmt2->execute();
        $stmt2->close();
        
        echo "Default users created successfully!<br>";
        echo "Admin: admin / admin123<br>";
        echo "Employee: employee / employee123<br>";
        echo "<br><a href='login.php'>Go to Login Page</a>";
    } else {
        echo "Users already exist in the database.<br>";
        echo "<a href='login.php'>Go to Login Page</a>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>