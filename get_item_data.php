<?php
include('mycon.php');

if(isset($_GET['spo_id']) && isset($_GET['product_id'])) {
    $spo_id = mysqli_real_escape_string($mysqli, $_GET['spo_id']);
    $product_id = mysqli_real_escape_string($mysqli, $_GET['product_id']);
    
    // Join query to get all the needed information
    $query = "SELECT i.*, p.item_name, p.product_brand, p.category, p.oem_number 
              FROM tbl_spo_items i
              LEFT JOIN tbl_masterlist p ON i.product_id = p.product_id
              WHERE i.spo_id = '$spo_id' AND i.product_id = '$product_id'";
    
    $result = mysqli_query($mysqli, $query);
    
    if($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        
        // Get the total quantity already transferred
        $query_transferred = "SELECT COALESCE(SUM(quantity), 0) as transferred_qty 
                              FROM tbl_stock_transfers 
                              WHERE spo_id = '$spo_id' AND product_id = '$product_id'";
        $result_transferred = mysqli_query($mysqli, $query_transferred);
        $transferred_data = mysqli_fetch_assoc($result_transferred);
        
        // Calculate remaining quantity
        $total_qty = $data['quantity'] ?? 0;
        $transferred_qty = $transferred_data['transferred_qty'] ?? 0;
        $remaining_qty = $total_qty - $transferred_qty;
        
        // Add the transfer data to the response
        $data['total_qty'] = $total_qty;
        $data['transferred_qty'] = $transferred_qty;
        $data['remaining_qty'] = $remaining_qty;
        
        // Return the data as JSON
        header('Content-Type: application/json');
        echo json_encode($data);
    } else {
        // Return empty object if no data found
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No item data found']);
    }
} else {
    // Return error if no spo_id provided
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No SPO ID or Product ID provided']);
}
?>