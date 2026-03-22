<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No data received or invalid JSON']);
    exit();
}

try {
    $transaction_id = isset($data['transaction_id']) ? (int)$data['transaction_id'] : 0;
    $original = isset($data['original']) ? $data['original'] : [];
    $replacement = isset($data['replacement']) ? $data['replacement'] : [];

    if ($transaction_id <= 0) {
        throw new Exception('Missing transaction id');
    }
    if (empty($original) || empty($replacement)) {
        throw new Exception('Missing exchange details');
    }

    $purchased_item_id = (int)($original['purchased_item_id'] ?? 0);
    $exchange_qty = (int)($original['quantity'] ?? 0);
    $return_condition = $original['return_condition'] ?? '';
    $return_reason = $original['return_reason'] ?? '';
    $replacement_qty = (int)($replacement['quantity'] ?? 0);
    $replacement_inventory_id = (int)($replacement['inventory_id'] ?? 0);
    $replacement_product_id = (int)($replacement['product_id'] ?? 0);
    $replacement_location = trim($replacement['storage_location'] ?? '');

    if ($purchased_item_id <= 0 || $exchange_qty <= 0 || $replacement_qty <= 0 || $replacement_inventory_id <= 0 || $replacement_product_id <= 0) {
        throw new Exception('Invalid payload values');
    }
    if (!in_array($return_condition, ['good','bad'], true)) {
        throw new Exception('Invalid or missing return condition');
    }
    if (trim($return_reason) === '') {
        throw new Exception('Missing return reason');
    }

    $mysqli->begin_transaction();

    // Fetch original transaction and purchased item
    $original_tx_stmt = $mysqli->prepare("SELECT invoice_no, customer_type, customer_firstName, customer_middleName, customer_lastName, customer_suffix FROM purchase_transactions WHERE transaction_id = ?");
    $original_tx_stmt->bind_param('i', $transaction_id);
    $original_tx_stmt->execute();
    $original_tx_result = $original_tx_stmt->get_result();
    if ($original_tx_result->num_rows === 0) {
        throw new Exception('Original transaction not found');
    }
    $original_tx = $original_tx_result->fetch_assoc();
    $original_tx_stmt->close();

    $purchased_stmt = $mysqli->prepare("SELECT product_id, unit_price_at_purchase, product_discount, product_markup, purchased_quantity FROM purchase_transaction_details WHERE purchased_item_id = ? LIMIT 1");
    $purchased_stmt->bind_param('i', $purchased_item_id);
    $purchased_stmt->execute();
    $purchased_res = $purchased_stmt->get_result();
    if ($purchased_res->num_rows === 0) {
        throw new Exception('Purchased item not found');
    }
    $purchased = $purchased_res->fetch_assoc();
    $purchased_stmt->close();

    if ($exchange_qty > (int)$purchased['purchased_quantity']) {
        throw new Exception('Exchange quantity exceeds original purchased quantity');
    }

    // Compute subtotals
    $original_subtotal = (($purchased['unit_price_at_purchase'] - $purchased['product_discount']) * $exchange_qty) + $purchased['product_markup'];
    $replacement_unit_price = (float)($replacement['unit_price'] ?? 0);
    $replacement_discount = (float)($replacement['discount'] ?? 0);
    $replacement_markup = (float)($replacement['markup'] ?? 0);
    $replacement_subtotal = (($replacement_unit_price - $replacement_discount) * $replacement_qty) + $replacement_markup;

    if ($replacement_subtotal < $original_subtotal) {
        throw new Exception('Replacement subtotal cannot be less than original subtotal');
    }

    // Create exchange transaction referencing original
    $invoice_no = 'EXC-' . $original_tx['invoice_no'] . '-' . date('YmdHis');
    $subtotal = $original_subtotal + $replacement_subtotal; // show both line subtotals
    $customer_pays = max($replacement_subtotal - $original_subtotal, 0); // grand_total should be the excess only

    $tx_stmt = $mysqli->prepare("INSERT INTO purchase_transactions (invoice_no, customer_type, subtotal, discount_percentage, grand_total, transaction_date, customer_firstName, customer_middleName, customer_lastName, customer_suffix, transaction_type, reference_transaction_id) VALUES (?, ?, ?, 0, ?, NOW(), ?, ?, ?, ?, 'exchange', ?)");
    $tx_stmt->bind_param('ssddssssi', $invoice_no, $original_tx['customer_type'], $subtotal, $customer_pays, $original_tx['customer_firstName'], $original_tx['customer_middleName'], $original_tx['customer_lastName'], $original_tx['customer_suffix'], $transaction_id);
    if (!$tx_stmt->execute()) {
        throw new Exception('Failed to create exchange transaction: ' . $mysqli->error);
    }
    $exchange_tx_id = $mysqli->insert_id;
    $tx_stmt->close();

    // Insert returned item detail (original product)
    $ret_detail_stmt = $mysqli->prepare("INSERT INTO purchase_transaction_details (transaction_id, product_id, purchased_quantity, unit_price_at_purchase, product_discount, product_markup, product_subtotal, return_condition, return_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ret_detail_stmt->bind_param('iiiddddss', $exchange_tx_id, $purchased['product_id'], $exchange_qty, $purchased['unit_price_at_purchase'], $purchased['product_discount'], $purchased['product_markup'], $original_subtotal, $return_condition, $return_reason);
    if (!$ret_detail_stmt->execute()) {
        throw new Exception('Failed to insert returned item detail: ' . $mysqli->error);
    }
    $returned_detail_id = $mysqli->insert_id;
    $ret_detail_stmt->close();

    // Insert replacement item detail
    $rep_detail_stmt = $mysqli->prepare("INSERT INTO purchase_transaction_details (transaction_id, product_id, purchased_quantity, unit_price_at_purchase, product_discount, product_markup, product_subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $rep_detail_stmt->bind_param('iiidddd', $exchange_tx_id, $replacement_product_id, $replacement_qty, $replacement_unit_price, $replacement_discount, $replacement_markup, $replacement_subtotal);
    if (!$rep_detail_stmt->execute()) {
        throw new Exception('Failed to insert replacement item detail: ' . $mysqli->error);
    }
    $replacement_detail_id = $mysqli->insert_id;
    $rep_detail_stmt->close();

    // Decrease inventory for replacement item
    $inv_check = $mysqli->prepare("SELECT stock_level FROM tbl_inventory WHERE inventory_id = ? AND product_id = ? LIMIT 1");
    $inv_check->bind_param('ii', $replacement_inventory_id, $replacement_product_id);
    $inv_check->execute();
    $inv_res = $inv_check->get_result();
    if ($inv_res->num_rows === 0) {
        throw new Exception('Replacement inventory not found');
    }
    $inv_row = $inv_res->fetch_assoc();
    $inv_check->close();

    if ((int)$inv_row['stock_level'] < $replacement_qty) {
        throw new Exception('Insufficient stock for replacement');
    }

    $inv_update = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level - ?, last_updated = NOW() WHERE inventory_id = ?");
    $inv_update->bind_param('ii', $replacement_qty, $replacement_inventory_id);
    if (!$inv_update->execute()) {
        throw new Exception('Failed to update inventory for replacement');
    }
    $inv_update->close();

    // Optionally log movement
    $check_table = $mysqli->query("SHOW TABLES LIKE 'tbl_inventory_movements'");
    if ($check_table && $check_table->num_rows > 0) {
        $movement_stmt = $mysqli->prepare("INSERT INTO tbl_inventory_movements (product_id, movement_type, quantity, from_location, to_location, reference_id, reference_type, notes) VALUES (?, 'exchange_out', ?, ?, '', ?, 'exchange', 'Replacement item issued for exchange')");
        $movement_stmt->bind_param('iisi', $replacement_product_id, $replacement_qty, $replacement_location, $exchange_tx_id);
        $movement_stmt->execute();
        $movement_stmt->close();
    }

    // If returned item is in good condition, add it back to inventory (same priority logic as refund)
    $return_location = null;
    if ($return_condition === 'good') {
        // Priority 1: Main Shop
        $check_main_shop = $mysqli->prepare("SELECT inventory_id, stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = 'Main Shop'");
        $check_main_shop->bind_param('i', $purchased['product_id']);
        $check_main_shop->execute();
        $main_res = $check_main_shop->get_result();
        $check_main_shop->close();

        if ($main_res->num_rows > 0) {
            $update_inv = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level + ?, last_updated = NOW() WHERE product_id = ? AND storage_location = 'Main Shop'");
            $update_inv->bind_param('ii', $exchange_qty, $purchased['product_id']);
            $return_location = 'Main Shop';
        } else {
            // Priority 2: Warehouse 1
            $check_wh1 = $mysqli->prepare("SELECT inventory_id, stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = 'Warehouse 1'");
            $check_wh1->bind_param('i', $purchased['product_id']);
            $check_wh1->execute();
            $wh1_res = $check_wh1->get_result();
            $check_wh1->close();

            if ($wh1_res->num_rows > 0) {
                $update_inv = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level + ?, last_updated = NOW() WHERE product_id = ? AND storage_location = 'Warehouse 1'");
                $update_inv->bind_param('ii', $exchange_qty, $purchased['product_id']);
                $return_location = 'Warehouse 1';
            } else {
                // Priority 3: Warehouse 2
                $check_wh2 = $mysqli->prepare("SELECT inventory_id, stock_level FROM tbl_inventory WHERE product_id = ? AND storage_location = 'Warehouse 2'");
                $check_wh2->bind_param('i', $purchased['product_id']);
                $check_wh2->execute();
                $wh2_res = $check_wh2->get_result();
                $check_wh2->close();

                if ($wh2_res->num_rows > 0) {
                    $update_inv = $mysqli->prepare("UPDATE tbl_inventory SET stock_level = stock_level + ?, last_updated = NOW() WHERE product_id = ? AND storage_location = 'Warehouse 2'");
                    $update_inv->bind_param('ii', $exchange_qty, $purchased['product_id']);
                    $return_location = 'Warehouse 2';
                } else {
                    // Create new Main Shop row if none exist
                    $insert_inv = $mysqli->prepare("INSERT INTO tbl_inventory (product_id, stock_level, storage_location, last_updated) VALUES (?, ?, 'Main Shop', NOW())");
                    $insert_inv->bind_param('ii', $purchased['product_id'], $exchange_qty);
                    if (!$insert_inv->execute()) {
                        throw new Exception('Failed to insert inventory for returned item');
                    }
                    $insert_inv->close();
                    $return_location = 'Main Shop';
                    $update_inv = null; // we've already inserted
                }
            }
        }

        if (isset($update_inv) && $update_inv) {
            if (!$update_inv->execute()) {
                throw new Exception('Failed to update inventory for returned item');
            }
            $update_inv->close();
        }

        // Log movement for returned good item
        $check_table = $mysqli->query("SHOW TABLES LIKE 'tbl_inventory_movements'");
        if ($check_table && $check_table->num_rows > 0) {
            $mv = $mysqli->prepare("INSERT INTO tbl_inventory_movements (product_id, movement_type, quantity, from_location, to_location, reference_id, reference_type, notes) VALUES (?, 'return_good', ?, '', ?, ?, 'exchange', 'Returned item in good condition (exchange)')");
            $mv->bind_param('iisi', $purchased['product_id'], $exchange_qty, $return_location, $exchange_tx_id);
            $mv->execute();
            $mv->close();
        }
    } else {
        // Bad condition - optionally log only
        $check_table = $mysqli->query("SHOW TABLES LIKE 'tbl_inventory_movements'");
        if ($check_table && $check_table->num_rows > 0) {
            $mv = $mysqli->prepare("INSERT INTO tbl_inventory_movements (product_id, movement_type, quantity, from_location, to_location, reference_id, reference_type, notes) VALUES (?, 'return_bad', ?, '', '', ?, 'exchange', ?)");
            $notes = 'Returned in bad condition - ' . $return_reason;
            $mv->bind_param('iiis', $purchased['product_id'], $exchange_qty, $exchange_tx_id, $notes);
            $mv->execute();
            $mv->close();
        }
    }

    // Create linking table if it doesn't exist
    $mysqli->query("CREATE TABLE IF NOT EXISTS exchange_item_links (exchange_id INT AUTO_INCREMENT PRIMARY KEY, return_transaction_id INT NOT NULL, exchange_transaction_detail_id INT NOT NULL)");

    // Link returned item to replacement detail
    $link_stmt = $mysqli->prepare("INSERT INTO exchange_item_links (return_transaction_id, exchange_transaction_detail_id) VALUES (?, ?)");
    $link_stmt->bind_param('ii', $returned_detail_id, $replacement_detail_id);
    $link_stmt->execute();
    $link_stmt->close();

    $mysqli->commit();

    // Return the amount customer needs to pay (non-negative)
    // Note: we already used $customer_pays above
    echo json_encode([
        'success' => true,
        'exchange_transaction_id' => $exchange_tx_id,
        'returned_detail_id' => $returned_detail_id,
        'replacement_detail_id' => $replacement_detail_id,
        'customer_pays' => $customer_pays
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$mysqli->close();
?>


