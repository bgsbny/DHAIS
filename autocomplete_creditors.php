<?php
include 'mycon.php';

header('Content-Type: application/json');
$term = isset($_GET['term']) ? $mysqli->real_escape_string($_GET['term']) : '';

$results = [];
if ($term !== '') {
    $sql = "SELECT creditor_id, org_name, creditor_fn, creditor_mn, creditor_ln, creditor_suffix, creditor_nickname FROM tbl_creditors WHERE 
        org_name LIKE '%$term%' OR 
        creditor_fn LIKE '%$term%' OR 
        creditor_mn LIKE '%$term%' OR 
        creditor_ln LIKE '%$term%' OR 
        creditor_nickname LIKE '%$term%' 
        LIMIT 15";
    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['org_name'])) {
                $label = $row['org_name'];
            } else if (!empty($row['creditor_fn'])) {
                $label = $row['creditor_fn'];
                if (!empty($row['creditor_mn'])) $label .= ' ' . $row['creditor_mn'];
                if (!empty($row['creditor_ln'])) $label .= ' ' . $row['creditor_ln'];
                if (!empty($row['creditor_suffix'])) $label .= ' ' . $row['creditor_suffix'];
            } else if (!empty($row['creditor_nickname'])) {
                $label = $row['creditor_nickname'];
            } else {
                $label = '';
            }
            if ($label !== '') {
                $results[] = [
                    'label' => $label,
                    'value' => $label,
                    'creditor_id' => $row['creditor_id']
                ];
            }
        }
    }
}
echo json_encode($results);
