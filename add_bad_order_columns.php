<?php
include 'mycon.php';

// Add new columns to tbl_spo_items for enhanced bad order management
$alter_queries = [
    "ALTER TABLE tbl_spo_items ADD COLUMN IF NOT EXISTS bad_order_remarks TEXT AFTER bad_order_qty",
    "ALTER TABLE tbl_spo_items ADD COLUMN IF NOT EXISTS bad_order_type ENUM('return', 'replacement') DEFAULT 'return' AFTER bad_order_remarks",
    "ALTER TABLE tbl_spo_items ADD COLUMN IF NOT EXISTS bad_order_date DATE AFTER bad_order_type",
    "ALTER TABLE tbl_spo_items ADD COLUMN IF NOT EXISTS replacement_invoice VARCHAR(50) NULL AFTER bad_order_date",
    "ALTER TABLE tbl_spo_items ADD COLUMN IF NOT EXISTS return_amount DECIMAL(10,2) DEFAULT 0.00 AFTER replacement_invoice"
];

// Create new table for bad order returns
$create_bad_order_returns = "
CREATE TABLE IF NOT EXISTS tbl_bad_order_returns (
    return_id INT AUTO_INCREMENT PRIMARY KEY,
    spo_id INT NOT NULL,
    spo_item_id INT NOT NULL,
    return_date DATE NOT NULL,
    return_amount DECIMAL(10,2) NOT NULL,
    return_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spo_id) REFERENCES tbl_spo(spo_id) ON DELETE CASCADE,
    FOREIGN KEY (spo_item_id) REFERENCES tbl_spo_items(spo_item_id) ON DELETE CASCADE
)";

// Create new table for replacement orders
$create_replacement_orders = "
CREATE TABLE IF NOT EXISTS tbl_replacement_orders (
    replacement_id INT AUTO_INCREMENT PRIMARY KEY,
    original_spo_id INT NOT NULL,
    original_spo_item_id INT NOT NULL,
    replacement_invoice VARCHAR(50) NOT NULL,
    replacement_date DATE NOT NULL,
    replacement_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (original_spo_id) REFERENCES tbl_spo(spo_id) ON DELETE CASCADE,
    FOREIGN KEY (original_spo_item_id) REFERENCES tbl_spo_items(spo_item_id) ON DELETE CASCADE
)";

try {
    // Execute alter queries
    foreach ($alter_queries as $query) {
        if (!$mysqli->query($query)) {
            echo "Error executing query: $query<br>";
            echo "MySQL Error: " . $mysqli->error . "<br>";
        } else {
            echo "Successfully executed: $query<br>";
        }
    }
    
    // Create bad order returns table
    if (!$mysqli->query($create_bad_order_returns)) {
        echo "Error creating bad order returns table: " . $mysqli->error . "<br>";
    } else {
        echo "Successfully created bad order returns table<br>";
    }
    
    // Create replacement orders table
    if (!$mysqli->query($create_replacement_orders)) {
        echo "Error creating replacement orders table: " . $mysqli->error . "<br>";
    } else {
        echo "Successfully created replacement orders table<br>";
    }
    
    echo "<br>Database structure updated successfully!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$mysqli->close();
?> 