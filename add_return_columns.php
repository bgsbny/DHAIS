<?php
include 'mycon.php';

// Check if transaction_type column exists in purchase_transactions
$check_transaction_type = "SHOW COLUMNS FROM purchase_transactions LIKE 'transaction_type'";
$result = $mysqli->query($check_transaction_type);

if ($result->num_rows == 0) {
    // Add transaction_type column
    $alter_transaction_type = "ALTER TABLE purchase_transactions 
                              ADD COLUMN transaction_type ENUM('sale', 'refund', 'exchange') DEFAULT 'sale' AFTER customer_suffix";
    
    if ($mysqli->query($alter_transaction_type)) {
        echo "Added transaction_type column to purchase_transactions table<br>";
    } else {
        echo "Error adding transaction_type column: " . $mysqli->error . "<br>";
    }
} else {
    echo "transaction_type column already exists in purchase_transactions table<br>";
}

// Check if reference_transaction_id column exists in purchase_transactions
$check_reference_id = "SHOW COLUMNS FROM purchase_transactions LIKE 'reference_transaction_id'";
$result = $mysqli->query($check_reference_id);

if ($result->num_rows == 0) {
    // Add reference_transaction_id column
    $alter_reference_id = "ALTER TABLE purchase_transactions 
                           ADD COLUMN reference_transaction_id INT NULL AFTER transaction_type";
    
    if ($mysqli->query($alter_reference_id)) {
        echo "Added reference_transaction_id column to purchase_transactions table<br>";
    } else {
        echo "Error adding reference_transaction_id column: " . $mysqli->error . "<br>";
    }
} else {
    echo "reference_transaction_id column already exists in purchase_transactions table<br>";
}

// Check if return_condition column exists in purchase_transaction_details
$check_return_condition = "SHOW COLUMNS FROM purchase_transaction_details LIKE 'return_condition'";
$result = $mysqli->query($check_return_condition);

if ($result->num_rows == 0) {
    // Add return_condition column
    $alter_return_condition = "ALTER TABLE purchase_transaction_details 
                              ADD COLUMN return_condition ENUM('good', 'bad') NULL AFTER product_subtotal";
    
    if ($mysqli->query($alter_return_condition)) {
        echo "Added return_condition column to purchase_transaction_details table<br>";
    } else {
        echo "Error adding return_condition column: " . $mysqli->error . "<br>";
    }
} else {
    echo "return_condition column already exists in purchase_transaction_details table<br>";
}

// Check if return_reason column exists in purchase_transaction_details
$check_return_reason = "SHOW COLUMNS FROM purchase_transaction_details LIKE 'return_reason'";
$result = $mysqli->query($check_return_reason);

if ($result->num_rows == 0) {
    // Add return_reason column
    $alter_return_reason = "ALTER TABLE purchase_transaction_details 
                           ADD COLUMN return_reason VARCHAR(255) NULL AFTER return_condition";
    
    if ($mysqli->query($alter_return_reason)) {
        echo "Added return_reason column to purchase_transaction_details table<br>";
    } else {
        echo "Error adding return_reason column: " . $mysqli->error . "<br>";
    }
} else {
    echo "return_reason column already exists in purchase_transaction_details table<br>";
}

// Check if tbl_inventory_movements table exists
$check_movements_table = "SHOW TABLES LIKE 'tbl_inventory_movements'";
$result = $mysqli->query($check_movements_table);

if ($result->num_rows == 0) {
    // Create tbl_inventory_movements table
    $create_movements = "CREATE TABLE tbl_inventory_movements (
        movement_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        movement_type ENUM('stock_in', 'stock_out', 'transfer', 'return_good', 'return_bad', 'adjustment') NOT NULL,
        quantity INT NOT NULL,
        from_location VARCHAR(100) NULL,
        to_location VARCHAR(100) NULL,
        reference_id INT NULL,
        reference_type VARCHAR(50) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        INDEX idx_movement_type (movement_type),
        INDEX idx_reference (reference_id, reference_type),
        INDEX idx_created_at (created_at)
    )";

    if ($mysqli->query($create_movements)) {
        echo "Created tbl_inventory_movements table<br>";
    } else {
        echo "Error creating tbl_inventory_movements table: " . $mysqli->error . "<br>";
    }
} else {
    echo "tbl_inventory_movements table already exists<br>";
}

echo "Database setup completed!";
?> 