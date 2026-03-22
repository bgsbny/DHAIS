<?php
include 'mycon.php';

// Check current constraint on purchased_quantity column
$check_constraint = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = 'dhautocare' 
                    AND TABLE_NAME = 'purchase_transaction_details' 
                    AND COLUMN_NAME = 'purchased_quantity'";

$result = $mysqli->query($check_constraint);

if ($result && $result->num_rows > 0) {
    $column_info = $result->fetch_assoc();
    echo "Current purchased_quantity column info:<br>";
    echo "Data Type: " . $column_info['DATA_TYPE'] . "<br>";
    echo "Nullable: " . $column_info['IS_NULLABLE'] . "<br>";
    echo "Default: " . $column_info['COLUMN_DEFAULT'] . "<br>";
    echo "Extra: " . $column_info['EXTRA'] . "<br><br>";
}

// Check for any constraints on the column
$check_constraints = "SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE 
                     FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                     WHERE TABLE_SCHEMA = 'dhautocare' 
                     AND TABLE_NAME = 'purchase_transaction_details'";

$constraints = $mysqli->query($check_constraints);

if ($constraints && $constraints->num_rows > 0) {
    echo "Table constraints:<br>";
    while ($constraint = $constraints->fetch_assoc()) {
        echo "- " . $constraint['CONSTRAINT_NAME'] . " (" . $constraint['CONSTRAINT_TYPE'] . ")<br>";
    }
    echo "<br>";
}

// Check for check constraints specifically
$check_checks = "SELECT CONSTRAINT_NAME, CHECK_CLAUSE 
                FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = 'dhautocare' 
                AND TABLE_NAME = 'purchase_transaction_details'";

$checks = $mysqli->query($check_checks);

if ($checks && $checks->num_rows > 0) {
    echo "Check constraints:<br>";
    while ($check = $checks->fetch_assoc()) {
        echo "- " . $check['CONSTRAINT_NAME'] . ": " . $check['CHECK_CLAUSE'] . "<br>";
    }
    echo "<br>";
}

// Try to drop any check constraints that might prevent negative values
$drop_constraints = "ALTER TABLE purchase_transaction_details 
                     DROP CONSTRAINT IF EXISTS chk_purchased_quantity_positive";

if ($mysqli->query($drop_constraints)) {
    echo "Attempted to drop check constraint (if it existed)<br>";
} else {
    echo "No check constraint to drop or error: " . $mysqli->error . "<br>";
}

// Modify the column to ensure it can accept negative values
$modify_column = "ALTER TABLE purchase_transaction_details 
                  MODIFY COLUMN purchased_quantity INT NOT NULL";

if ($mysqli->query($modify_column)) {
    echo "Successfully modified purchased_quantity column to allow negative values<br>";
} else {
    echo "Error modifying column: " . $mysqli->error . "<br>";
}

echo "Database constraint fix completed!";
?> 