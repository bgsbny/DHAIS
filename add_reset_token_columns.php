<?php
include('mycon.php');

try {
    // Add reset_token column if it doesn't exist
    $check_token_query = "SHOW COLUMNS FROM tbl_users LIKE 'reset_token'";
    $token_result = mysqli_query($mysqli, $check_token_query);
    
    if (mysqli_num_rows($token_result) == 0) {
        $add_token_query = "ALTER TABLE tbl_users ADD COLUMN reset_token VARCHAR(255) NULL";
        if (mysqli_query($mysqli, $add_token_query)) {
            echo "✓ reset_token column added successfully<br>";
        } else {
            echo "✗ Error adding reset_token column: " . mysqli_error($mysqli) . "<br>";
        }
    } else {
        echo "✓ reset_token column already exists<br>";
    }
    
    // Add reset_token_expiry column if it doesn't exist
    $check_expiry_query = "SHOW COLUMNS FROM tbl_users LIKE 'reset_token_expiry'";
    $expiry_result = mysqli_query($mysqli, $check_expiry_query);
    
    if (mysqli_num_rows($expiry_result) == 0) {
        $add_expiry_query = "ALTER TABLE tbl_users ADD COLUMN reset_token_expiry DATETIME NULL";
        if (mysqli_query($mysqli, $add_expiry_query)) {
            echo "✓ reset_token_expiry column added successfully<br>";
        } else {
            echo "✗ Error adding reset_token_expiry column: " . mysqli_error($mysqli) . "<br>";
        }
    } else {
        echo "✓ reset_token_expiry column already exists<br>";
    }
    
    // Add security_question column if it doesn't exist
    $check_question_query = "SHOW COLUMNS FROM tbl_users LIKE 'security_question'";
    $question_result = mysqli_query($mysqli, $check_question_query);
    
    if (mysqli_num_rows($question_result) == 0) {
        $add_question_query = "ALTER TABLE tbl_users ADD COLUMN security_question VARCHAR(255) DEFAULT 'What is the company code for DH Autocare?'";
        if (mysqli_query($mysqli, $add_question_query)) {
            echo "✓ security_question column added successfully<br>";
        } else {
            echo "✗ Error adding security_question column: " . mysqli_error($mysqli) . "<br>";
        }
    } else {
        echo "✓ security_question column already exists<br>";
    }
    
    // Add security_answer column if it doesn't exist
    $check_answer_query = "SHOW COLUMNS FROM tbl_users LIKE 'security_answer'";
    $answer_result = mysqli_query($mysqli, $check_answer_query);
    
    if (mysqli_num_rows($answer_result) == 0) {
        $add_answer_query = "ALTER TABLE tbl_users ADD COLUMN security_answer VARCHAR(255) DEFAULT 'dhautocare2024'";
        if (mysqli_query($mysqli, $add_answer_query)) {
            echo "✓ security_answer column added successfully<br>";
        } else {
            echo "✗ Error adding security_answer column: " . mysqli_error($mysqli) . "<br>";
        }
    } else {
        echo "✓ security_answer column already exists<br>";
    }
    
    echo "<br>Database setup completed!<br>";
    echo "<a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 