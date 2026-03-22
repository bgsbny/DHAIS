<?php
include 'mycon.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$search_term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($search_term)) {
    echo json_encode([]);
    exit();
}

try {
    // First, let's test if we can get any creditors at all
    $test_query = "SELECT COUNT(*) as total FROM tbl_creditors";
    $test_stmt = $mysqli->prepare($test_query);
    $test_stmt->execute();
    $test_result = $test_stmt->get_result();
    $total_creditors = $test_result->fetch_assoc()['total'];
    error_log("Total creditors in database: $total_creditors");
    $test_stmt->close();
    
    // Simple search first - just get basic creditor info
    $query = "SELECT 
                creditor_id,
                org_name,
                creditor_fn,
                creditor_mn,
                creditor_ln,
                creditor_suffix,
                creditor_nickname
              FROM tbl_creditors 
              WHERE org_name LIKE ? 
                 OR creditor_fn LIKE ?
                 OR creditor_ln LIKE ?
                 OR creditor_nickname LIKE ?
              ORDER BY 
                CASE 
                    WHEN org_name IS NOT NULL AND org_name != '' THEN org_name 
                    ELSE CONCAT(creditor_fn, ' ', creditor_mn, ' ', creditor_ln, ' ', creditor_suffix)
                END";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $search_pattern = $search_term . '%'; // Starts with search term
    $stmt->bind_param('ssss', $search_pattern, $search_pattern, $search_pattern, $search_pattern);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    // Debug: Log the search term and result count
    error_log("Search term: '$search_term', Found rows: " . $result->num_rows);
    
    // If no results, try a simpler search
    if ($result->num_rows === 0) {
        error_log("No results found, trying simpler search...");
        $simple_query = "SELECT creditor_id, org_name, creditor_fn, creditor_mn, creditor_ln, creditor_suffix, creditor_nickname FROM tbl_creditors WHERE org_name LIKE ? OR creditor_fn LIKE ? OR creditor_ln LIKE ?";
        $simple_stmt = $mysqli->prepare($simple_query);
        $simple_stmt->bind_param('sss', $search_pattern, $search_pattern, $search_pattern);
        $simple_stmt->execute();
        $simple_result = $simple_stmt->get_result();
        error_log("Simple search found: " . $simple_result->num_rows . " rows");
        $simple_stmt->close();
    }
    
    $creditors = [];
    while ($row = $result->fetch_assoc()) {
        // Determine the name to display
        $displayName = '';
        if (!empty($row['org_name'])) {
            // Organization creditor
            $displayName = $row['org_name'];
        } else {
            // Individual creditor
            $firstName = trim($row['creditor_fn'] ?? '');
            $middleName = trim($row['creditor_mn'] ?? '');
            $lastName = trim($row['creditor_ln'] ?? '');
            $suffix = trim($row['creditor_suffix'] ?? '');
            
            $displayName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
        }
        
        // For now, set default values for financial data
        $totalReceivable = 0;
        $totalCollected = 0;
        $balance = 0;
        
        $creditors[] = [
            'creditor_id' => $row['creditor_id'],
            'display_name' => $displayName,
            'org_name' => $row['org_name'],
            'creditor_fn' => $row['creditor_fn'],
            'creditor_mn' => $row['creditor_mn'],
            'creditor_ln' => $row['creditor_ln'],
            'creditor_suffix' => $row['creditor_suffix'],
            'creditor_nickname' => $row['creditor_nickname'],
            'total_receivable' => $totalReceivable,
            'total_collected' => $totalCollected,
            'balance' => $balance
        ];
    }
    
    $stmt->close();
    
    error_log("Returning " . count($creditors) . " creditors");
    echo json_encode($creditors);
    
} catch (Exception $e) {
    error_log("search_creditors.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?> 