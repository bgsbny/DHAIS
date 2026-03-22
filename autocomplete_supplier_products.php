<?php
include 'mycon.php';

header('Content-Type: application/json');
$term = isset($_GET['term']) ? $_GET['term'] : '';

$suggestions = [];

if ($term !== '') {
    // Get products from masterlist that are active
    $sql = "SELECT DISTINCT 
                m.product_id, 
                m.item_name, 
                m.oem_number, 
                m.product_size, 
                m.product_brand, 
                m.unit, 
                m.selling_price
            FROM tbl_masterlist m
            WHERE m.status != 'Inactive'
              AND (m.item_name LIKE ? OR m.oem_number LIKE ? OR m.product_brand LIKE ? OR m.product_size LIKE ?)
            ORDER BY m.item_name
            LIMIT 10";

    $like = "%$term%";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Build part number/size information
        $partInfo = '';
        if (!empty($row['oem_number'])) {
            $partInfo = $row['oem_number'];
        } elseif (!empty($row['product_size'])) {
            $partInfo = $row['product_size'];
        }
        
        // Build the label with product name, part number/size, and brand
        $label = $row['item_name'];
        if (!empty($partInfo)) {
            $label .= " | " . $partInfo;
        }
        if (!empty($row['product_brand'])) {
            $label .= " | " . $row['product_brand'];
        }
        
        $suggestions[] = [
            'label' => $label,
            'value' => $label,
            'product_id' => $row['product_id'],
            'product_brand' => $row['product_brand'],
            'unit' => $row['unit'],
            'selling_price' => $row['selling_price'],
            'oem_number' => $row['oem_number'],
            'product_size' => $row['product_size']
        ];
    }
    
    $stmt->close();
}

echo json_encode($suggestions);
$mysqli->close();
?> 