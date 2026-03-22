<?php
    session_start();
    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit();
    }

    // Include database connection
    include 'mycon.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- bootstrap -->
    <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/docs.css" rel="stylesheet">
    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <!-- for the icons -->  
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/fontawesome.min.css">

    <!-- Local jQuery files -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/jquery-ui.css">
    <script src="js/jquery-ui.min.js"></script>

    <!-- Google Fonts -->
    <link rel="stylesheet" href="css/interfont.css">

    <link rel="stylesheet" href="css/style.css">

    <title>Dashboard</title>
</head>
<body>
    <?php $activePage = 'dashboard'; include 'navbar.php'; ?>
        <!-- Header -->
        <header class="page-header" style='background-color:rgb(245, 250, 255) !important;'>
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">Dashboard Overview</h1>
                    <p class="page-subtitle">Welcome back, Admin! Here's what's happening at DH Autocare today.</p>
                </div>
                <div class="header-right">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt"></i>
                        <span id="current-date"></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-content" style='background-color:rgb(245, 250, 255) !important;'>
            <div class="main-container">
                <!-- Main Content Grid -->
                <div class="content-grid">
                    <!-- Recent Transactions -->
                    <div class="content-card transactions-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-clock"></i>
                            Recent Transactions
                        </h5>
                        <a href="transaction_history.php" class="view-all">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="transactions-list-container">
                            <div class="transactions-list" id="transactionsList">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr class='align-middle'>
                                                <th>Invoice</th>
                                                <th>Customer</th>
                                                <th>₱ Amount</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get recent transactions (last 30 days)
                                            $recent_transactions_query = "SELECT 
                                                pt.invoice_no,
                                                pt.transaction_date,
                                                pt.grand_total,
                                                CASE 
                                                    WHEN pt.customer_type = 'Walk-in' THEN 'Walk-In'
                                                    ELSE COALESCE(c.org_name, CONCAT(c.creditor_fn, ' ', c.creditor_ln))
                                                END as customer_name,
                                                pt.customer_type,
                                                CASE 
                                                    WHEN pt.customer_type = 'Walk-in' THEN 'Completed'
                                                    WHEN ct.status IS NULL THEN 'Completed'
                                                    ELSE ct.status
                                                END as status
                                            FROM purchase_transactions pt
                                            LEFT JOIN tbl_credit_transactions ct ON pt.transaction_id = ct.transaction_id
                                            LEFT JOIN tbl_creditors c ON ct.creditor_id = c.creditor_id
                                            WHERE pt.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                            AND pt.transaction_type = 'sale'
                                            ORDER BY pt.transaction_date DESC";
                                            $recent_transactions_result = $mysqli->query($recent_transactions_query);
                                            
                                            if ($recent_transactions_result && $recent_transactions_result->num_rows > 0) {
                                                $transaction_count = 0;
                                                while ($transaction = $recent_transactions_result->fetch_assoc()) {
                                                    $status_class = $transaction['status'] == 'Completed' ? 'completed' : 'pending';
                                                    $date_display = date('M j', strtotime($transaction['transaction_date']));
                                                    ?>
                                                    <tr class="transaction-row" data-index="<?php echo $transaction_count; ?>">
                                                        <td><span class="invoice-number"><?php echo htmlspecialchars($transaction['invoice_no']); ?></span></td>
                                                        <td>
                                                            <div class="customer-info">
                                                                <span class="customer-name"><?php echo htmlspecialchars($transaction['customer_name']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><span class="amount"><?php echo number_format($transaction['grand_total'], 2); ?></span></td>
                                                        <td><span class="date"><?php echo $date_display; ?></span></td>
                                                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $transaction['status']; ?></span></td>
                                                    </tr>
                                                    <?php
                                                    $transaction_count++;
                                                }
                                            } else {
                                                echo '<tr><td colspan="5" class="text-center text-muted">No recent transactions</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="transactions-pagination" id="transactionsPagination" style="display: none;">
                                <button class="pagination-btn" id="prevTransactionBtn">‹ Previous</button>
                                <span class="pagination-info" id="transactionPaginationInfo">1-5 of 0</span>
                                <button class="pagination-btn" id="nextTransactionBtn">Next ›</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="content-card stock-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Low Stock Alert
                        </h5>
                        <a href="reorder_list.php" class="view-all">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="stock-list-container">
                            <div class="stock-list" id="stockList">
                                <?php
                                // Get low stock and out of stock items with buffering based on movement tags
                                $low_stock_items_query = "SELECT 
                                    p.item_name, 
                                    p.oem_number, 
                                    p.movement_tag,
                                    i.stock_level as total_stock,
                                    p.selling_price,
                                    i.storage_location
                                FROM tbl_masterlist p 
                                LEFT JOIN tbl_inventory i ON p.product_id = i.product_id 
                                WHERE (
                                    (p.movement_tag = 'fast' AND i.stock_level <= 20) OR
                                    (p.movement_tag = 'slow' AND i.stock_level <= 5) OR
                                    (p.movement_tag = 'clearance' AND i.stock_level <= 3) OR
                                    (p.movement_tag = 'normal' AND i.stock_level <= 10) OR
                                    (p.movement_tag IS NULL AND i.stock_level <= 10) OR
                                    i.stock_level = 0
                                )
                                ORDER BY i.stock_level ASC";
                                
                                $low_stock_items_result = $mysqli->query($low_stock_items_query);
                                
                                if ($low_stock_items_result && $low_stock_items_result->num_rows > 0) {
                                    $item_count = 0;
                                    while ($item = $low_stock_items_result->fetch_assoc()) {
                                        $stock_percentage = 0;
                                        $stock_class = 'normal';
                                        $stock_fill_class = 'normal';
                                        $movement_badge_class = 'badge-secondary';
                                        
                                        // Determine stock class and percentage based on movement tag and stock level
                                        if ($item['total_stock'] == 0) {
                                            $stock_class = 'critical';
                                            $stock_percentage = 0;
                                            $stock_fill_class = 'out-of-stock';
                                        } elseif ($item['movement_tag'] == 'fast') {
                                            $movement_badge_class = 'badge-danger';
                                            if ($item['total_stock'] <= 10) {
                                                $stock_class = 'critical';
                                                $stock_percentage = 15;
                                                $stock_fill_class = 'critical';
                                            } elseif ($item['total_stock'] <= 20) {
                                                $stock_class = 'warning';
                                                $stock_percentage = 40;
                                                $stock_fill_class = 'warning';
                                            }
                                        } elseif ($item['movement_tag'] == 'slow') {
                                            $movement_badge_class = 'badge-warning';
                                            if ($item['total_stock'] <= 3) {
                                                $stock_class = 'critical';
                                                $stock_percentage = 15;
                                                $stock_fill_class = 'critical';
                                            } elseif ($item['total_stock'] <= 5) {
                                                $stock_class = 'warning';
                                                $stock_percentage = 40;
                                                $stock_fill_class = 'warning';
                                            }
                                        } elseif ($item['movement_tag'] == 'clearance') {
                                            $movement_badge_class = 'badge-info';
                                            if ($item['total_stock'] <= 2) {
                                                $stock_class = 'critical';
                                                $stock_percentage = 15;
                                                $stock_fill_class = 'critical';
                                            } elseif ($item['total_stock'] <= 3) {
                                                $stock_class = 'warning';
                                                $stock_percentage = 40;
                                                $stock_fill_class = 'warning';
                                            }
                                        } else {
                                            // Normal movement
                                            $movement_badge_class = 'badge-primary';
                                            if ($item['total_stock'] <= 5) {
                                                $stock_class = 'critical';
                                                $stock_percentage = 15;
                                                $stock_fill_class = 'critical';
                                            } elseif ($item['total_stock'] <= 10) {
                                                $stock_class = 'warning';
                                                $stock_percentage = 40;
                                                $stock_fill_class = 'warning';
                                            }
                                        }
                                        
                                        // Get buffer level based on movement tag
                                        $buffer_level = 0;
                                        switch ($item['movement_tag']) {
                                            case 'fast':
                                                $buffer_level = 20;
                                                break;
                                            case 'slow':
                                                $buffer_level = 5;
                                                break;
                                            case 'clearance':
                                                $buffer_level = 3;
                                                break;
                                            default:
                                                $buffer_level = 10;
                                                break;
                                        }
                                        
                                        $display_class = 'stock-item';
                                        ?>
                                        <div class="<?php echo $display_class; ?>" data-index="<?php echo $item_count; ?>">
                                            <div class="stock-info">
                                                <h6 class="product-name"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                <p class="product-sku">SKU: <?php echo htmlspecialchars($item['oem_number']); ?></p>
                                                <div class="movement-info">
                                                    <span class="badge <?php echo $movement_badge_class; ?> movement-badge">
                                                        <?php echo ucfirst($item['movement_tag'] ?: 'normal'); ?> Movement
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="stock-status">
                                                <span class="stock-count <?php echo $stock_class; ?>"><?php echo $item['total_stock']; ?> units</span>
                                                <div class="stock-bar">
                                                    <div class="stock-fill <?php echo $stock_fill_class; ?>" style="width: <?php echo $stock_percentage; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $item_count++;
                                    }
                                } else {
                                    echo '<div class="text-center text-muted py-4">No low stock items found</div>';
                                }
                                ?>
                            </div>
                            <div class="stock-pagination" id="stockPagination" style="display: none;">
                                <button class="pagination-btn" id="prevStockBtn">‹ Previous</button>
                                <span class="pagination-info" id="stockPaginationInfo">1-5 of 0</span>
                                <button class="pagination-btn" id="nextStockBtn">Next ›</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar Section -->
            <div class="content-card calendar-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-calendar-alt"></i>
                        Upcoming Events
                    </h5>
                </div>
                <div class="card-body">
                    <div id="calendar-container"></div>
                    <div id="calendar-event-details" class="mt-4"></div>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentDate = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-date').textContent = currentDate.toLocaleDateString('en-US', options);

        // Fetch events for the current month from the database
        const events = <?php
            include 'mycon.php';
            $year = date('Y');
            $month = date('m');
            $start = "$year-$month-01";
            $end = date('Y-m-t');
            
            // Debug: Check what credit transactions exist
            $debug_sql = "SELECT COUNT(*) as total_credits FROM tbl_credit_transactions";
            $debug_result = $mysqli->query($debug_sql);
            $debug_row = $debug_result->fetch_assoc();
            $total_credits = $debug_row['total_credits'];
            
            // Debug: Check credit transactions with status
            $status_sql = "SELECT status, COUNT(*) as count FROM tbl_credit_transactions GROUP BY status";
            $status_result = $mysqli->query($status_sql);
            $status_counts = [];
            while ($row = $status_result->fetch_assoc()) {
                $status_counts[$row['status']] = $row['count'];
            }
            
            // Debug: Check for any credit transactions with due dates
            $due_date_sql = "SELECT ct.due_date, ct.status, c.org_name, c.creditor_fn, c.creditor_ln 
                            FROM tbl_credit_transactions ct 
                            JOIN tbl_creditors c ON ct.creditor_id = c.creditor_id 
                            WHERE ct.due_date IS NOT NULL 
                            ORDER BY ct.due_date ASC 
                            LIMIT 10";
            $due_date_result = $mysqli->query($due_date_sql);
            $due_date_data = [];
            while ($row = $due_date_result->fetch_assoc()) {
                $due_date_data[] = $row;
            }
            
            // Get all credit transactions with due dates across all months
            $sql = "SELECT ct.due_date as date, ct.creditor_id, c.org_name, c.creditor_fn, c.creditor_ln, ct.total_with_interest, ct.status, pt.invoice_no 
                    FROM tbl_credit_transactions ct 
                    JOIN tbl_creditors c ON ct.creditor_id = c.creditor_id 
                    JOIN purchase_transactions pt ON ct.transaction_id = pt.transaction_id 
                    WHERE ct.status IN ('Pending', 'Partial', 'Not Paid') AND ct.due_date IS NOT NULL
                    ORDER BY ct.due_date ASC";
            $result = $mysqli->query($sql);
            $events = [];
            while ($row = $result->fetch_assoc()) {
                $creditor = $row['org_name'] ? $row['org_name'] : $row['creditor_fn'] . ' ' . $row['creditor_ln'];
                $statusClass = $row['status'] === 'Pending' ? 'text-warning' : 'text-info';
                $events[] = [
                    'date' => $row['date'],
                    'type' => 'credit_due',
                    'details' => 'Credit due for ' . $creditor . ' (₱' . number_format($row['total_with_interest'],2) . ')',
                    'creditor_id' => $row['creditor_id'],
                    'creditor_name' => $creditor,
                    'amount' => $row['total_with_interest'],
                    'status' => $row['status'],
                    'invoice_no' => $row['invoice_no']
                ];
            }
            
            // Add national holidays for the current month
            $holidays = [
                '2024-01-01' => 'New Year\'s Day',
                '2024-04-09' => 'Day of Valor (Araw ng Kagitingan)',
                '2024-04-10' => 'Maundy Thursday',
                '2024-04-11' => 'Good Friday',
                '2024-05-01' => 'Labor Day',
                '2024-06-12' => 'Independence Day',
                '2024-08-21' => 'Ninoy Aquino Day',
                '2024-08-26' => 'National Heroes Day',
                '2024-11-01' => 'All Saints\' Day',
                '2024-11-30' => 'Bonifacio Day',
                '2024-12-25' => 'Christmas Day',
                '2024-12-30' => 'Rizal Day',
                '2025-01-01' => 'New Year\'s Day',
                '2025-04-09' => 'Day of Valor (Araw ng Kagitingan)',
                '2025-05-01' => 'Labor Day',
                '2025-06-12' => 'Independence Day',
                '2025-08-21' => 'Ninoy Aquino Day',
                '2025-08-25' => 'National Heroes Day',
                '2025-11-01' => 'All Saints\' Day',
                '2025-11-30' => 'Bonifacio Day',
                '2025-12-25' => 'Christmas Day',
                '2025-12-30' => 'Rizal Day'
            ];
            
            foreach ($holidays as $date => $holiday) {
                // Add all holidays regardless of month
                $events[] = [
                    'date' => $date,
                    'type' => 'holiday',
                    'details' => $holiday . ' (National Holiday)'
                ];
            }
            
            echo json_encode($events);
        ?>;

        // Filter events to only show credit due and holidays
        let allEvents = events.filter(ev => ev.type === 'credit_due' || ev.type === 'holiday');

        // Event type to color class mapping
        const eventTypeClass = {
            'credit_due': 'credit-due',
            'holiday': 'holiday',
            'today': 'today'
        };
        const eventTypeLabel = {
            'credit_due': 'Credit Due',
            'holiday': 'National Holiday',
            'today': 'Today'
        };

        function groupEventsByDate(events) {
            const map = {};
            events.forEach(e => {
                if (!map[e.date]) map[e.date] = [];
                map[e.date].push(e);
            });
            return map;
        }

        function getEventCellClass(eventsForDay, isToday) {
            let classes = ['calendar-day'];
            if (isToday) classes.push('today');
            if (eventsForDay.length > 1) classes.push('multiple-events');
            else if (eventsForDay.length === 1) classes.push(eventTypeClass[eventsForDay[0].type] || '');
            return classes.join(' ');
        }

        function renderCalendar(containerId, events, year, month) {
            const today = new Date();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const eventsByDate = groupEventsByDate(events);
            
            // Ensure we're working with the correct month
            const monthStart = new Date(year, month, 1);
            const monthEnd = new Date(year, month + 1, 0);

            let html = `<table class="table table-bordered calendar-table shadow-sm rounded-3 overflow-hidden">
                <thead>
                    <tr class="bg-light">
                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                    </tr>
                </thead>
                <tbody><tr>`;

            let dayOfWeek = firstDay.getDay();
            for (let i = 0; i < dayOfWeek; i++) html += `<td class="empty-day"></td>`;

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                const isToday = (today.getFullYear() === year && today.getMonth() === month && today.getDate() === day);
                const eventsForDay = eventsByDate[dateStr] || [];
                let cellClass = getEventCellClass(eventsForDay, isToday);
                html += `<td class="${cellClass}" data-date="${dateStr}" style="cursor:pointer;">
                    <div class="day-content">
                        <span class="day-number">${day}</span>
                        ${eventsForDay.map(ev => `<span class="event-dot ${eventTypeClass[ev.type]}"></span>`).join('')}
                    </div>
                </td>`;
                dayOfWeek++;
                if (dayOfWeek === 7 && day !== daysInMonth) {
                    html += `</tr><tr>`;
                    dayOfWeek = 0;
                }
            }
            for (let i = dayOfWeek; i < 7 && dayOfWeek !== 0; i++) html += `<td class="empty-day"></td>`;
            html += `</tr></tbody></table>
            <div class="calendar-legend mt-3 d-flex flex-wrap gap-3">
                <span class="legend-item"><span class="legend-color today"></span> Today</span>
                <span class="legend-item"><span class="legend-color credit-due"></span> Credit Due</span>
                <span class="legend-item"><span class="legend-color holiday"></span> National Holiday</span>
            </div>`;

            document.getElementById(containerId).innerHTML = html;

            // Always keep today highlighted
            const todayCell = document.querySelector('.calendar-day.today');
            if (todayCell) todayCell.classList.add('legend-highlight-today');

            // Add click event for days
            document.querySelectorAll('.calendar-day').forEach(function(td) {
                td.addEventListener('click', function() {
                    const date = this.getAttribute('data-date');
                    const eventsForDay = eventsByDate[date] || [];
                    // If only one event and it's credit_due, redirect
                    if (eventsForDay.length === 1 && eventsForDay[0].type === 'credit_due' && eventsForDay[0].creditor_id) {
                        window.location.href = `creditor_details.php?id=${eventsForDay[0].creditor_id}`;
                        return;
                    }
                    let detailsHtml = '';
                    if (eventsForDay.length === 0) {
                        detailsHtml = `<div class='alert alert-info'>No events for this day.</div>`;
                    } else {
                        eventsForDay.forEach(event => {
                            detailsHtml += `<div class='event-detail-card mb-3 border rounded p-3 shadow-sm'>`;
                            detailsHtml += `<div class='event-header mb-2'><span class='event-title fw-bold ${eventTypeClass[event.type]}'>${eventTypeLabel[event.type] || event.type}</span></div>`;
                            detailsHtml += `<div class='event-description mb-1'>${event.details}</div>`;
                            if (event.type === 'credit_due' && event.creditor_id) {
                                detailsHtml += `<div><strong>Creditor:</strong> ${event.creditor_name}</div>`;
                                detailsHtml += `<div><strong>Amount:</strong> ₱${parseFloat(event.amount).toLocaleString()}</div>`;
                                detailsHtml += `<div><strong>Status:</strong> ${event.status}</div>`;
                                detailsHtml += `<div class='mt-2'><a href='creditor_details.php?id=${event.creditor_id}' class='btn btn-primary btn-sm'>View Creditor Details</a></div>`;
                            }
                            if (event.type === 'holiday') {
                                detailsHtml += `<div><strong>Type:</strong> National Holiday</div>`;
                            }
                            detailsHtml += `</div>`;
                        });
                    }
                    document.getElementById('calendar-event-details').innerHTML = detailsHtml;
                });
            });

            // Legend interactivity: highlight days and scroll
            document.querySelectorAll('.calendar-legend .legend-item').forEach(function(item) {
                item.addEventListener('mouseenter', function() {
                    const type = this.querySelector('.legend-color').classList[1];
                    document.querySelectorAll('.calendar-day').forEach(function(day) {
                        if (day.classList.contains(type)) {
                            day.classList.add('legend-highlight');
                        }
                    });
                    // Always keep today highlighted
                    if (todayCell) todayCell.classList.add('legend-highlight-today');
                });
                item.addEventListener('mouseleave', function() {
                    document.querySelectorAll('.calendar-day.legend-highlight').forEach(function(day) {
                        day.classList.remove('legend-highlight');
                    });
                    // Always keep today highlighted
                    if (todayCell) todayCell.classList.add('legend-highlight-today');
                });
                item.addEventListener('click', function() {
                    document.querySelectorAll('.calendar-legend .legend-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    const type = this.querySelector('.legend-color').classList[1];
                    document.querySelectorAll('.calendar-day.legend-highlight').forEach(function(day) {
                        day.classList.remove('legend-highlight');
                    });
                    let first = null;
                    document.querySelectorAll('.calendar-day').forEach(function(day) {
                        if (day.classList.contains(type)) {
                            day.classList.add('legend-highlight');
                            if (!first) first = day;
                        }
                    });
                    if (first) {
                        first.scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                    // Always keep today highlighted
                    if (todayCell) todayCell.classList.add('legend-highlight-today');
                });
            });
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.calendar-legend')) {
                    document.querySelectorAll('.calendar-legend .legend-item').forEach(i => i.classList.remove('active'));
                    document.querySelectorAll('.calendar-day.legend-highlight').forEach(function(day) {
                        day.classList.remove('legend-highlight');
                    });
                    // Always keep today highlighted
                    if (todayCell) todayCell.classList.add('legend-highlight-today');
                }
            });
        }

        // Month navigation state
        let currentYear = new Date().getFullYear();
        let currentMonth = new Date().getMonth();

        function filterEventsForMonth(events, year, month) {
            // Show all credit due events and holidays across all months
            return events.filter(ev => {
                if (ev.type === 'credit_due' || ev.type === 'holiday') {
                    // Show all credit due events and holidays regardless of month
                    return true;
                }
                return false;
            });
        }

        function renderCalendarWithNav(containerId, events, year, month) {
            // Month navigation header
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            let navHtml = `<div class="d-flex justify-content-between align-items-center mb-2">
                <button class="btn btn-sm btn-outline-primary" id="prevMonthBtn">◀</button>
                <span class="fw-bold">${monthNames[month]} ${year}</span>
                <button class="btn btn-sm btn-outline-primary" id="nextMonthBtn">▶</button>
            </div>`;
            document.getElementById(containerId).innerHTML = navHtml + '<div id="calendar-inner"></div>';
            // Filter events for this month
            const monthEvents = filterEventsForMonth(events, year, month);
            // Render calendar as before, but into #calendar-inner
            renderCalendar('calendar-inner', monthEvents, year, month);
            // Add event listeners
            document.getElementById('prevMonthBtn').onclick = function() {
                if (month === 0) { currentMonth = 11; currentYear--; } else { currentMonth--; }
                renderCalendarWithNav(containerId, events, currentYear, currentMonth);
            };
            document.getElementById('nextMonthBtn').onclick = function() {
                if (month === 11) { currentMonth = 0; currentYear++; } else { currentMonth++; }
                renderCalendarWithNav(containerId, events, currentYear, currentMonth);
            };
        }

        // Initial render
        renderCalendarWithNav('calendar-container', allEvents, currentYear, currentMonth);



        // Recent Transactions Pagination
        function setupTransactionsPagination() {
            const transactionsItems = document.querySelectorAll('.transaction-row');
            const paginationContainer = document.getElementById('transactionsPagination');
            const prevBtn = document.getElementById('prevTransactionBtn');
            const nextBtn = document.getElementById('nextTransactionBtn');
            const infoSpan = document.getElementById('transactionPaginationInfo');
            
            if (transactionsItems.length <= 5) {
                return; // No pagination needed
            }
            
            
            let currentPage = 0;
            const itemsPerPage = 5;
            const totalPages = Math.ceil(transactionsItems.length / itemsPerPage);
            
            // Show pagination container
            paginationContainer.style.display = 'flex';
            
            function showPage(page) {
                const start = page * itemsPerPage;
                const end = start + itemsPerPage;
                
                // Hide all items first
                transactionsItems.forEach(item => {
                    item.style.display = 'none';
                });
                
                // Show only items for current page
                for (let i = start; i < end && i < transactionsItems.length; i++) {
                    transactionsItems[i].style.display = '';
                }
                
                // Update pagination info
                const startItem = start + 1;
                const endItem = Math.min(end, transactionsItems.length);
                infoSpan.textContent = `${startItem}-${endItem} of ${transactionsItems.length}`;
                
                // Update button states
                prevBtn.disabled = page === 0;
                nextBtn.disabled = page === totalPages - 1;
            }
            
            // Event listeners
            prevBtn.addEventListener('click', () => {
                if (currentPage > 0) {
                    currentPage--;
                    showPage(currentPage);
                }
            });
            
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages - 1) {
                    currentPage++;
                    showPage(currentPage);
                }
            });
            
            // Initialize first page
            showPage(0);
        }

        // Low Stock Alert Pagination
        function setupStockPagination() {
            const stockItems = document.querySelectorAll('.stock-item');
            const paginationContainer = document.getElementById('stockPagination');
            const prevBtn = document.getElementById('prevStockBtn');
            const nextBtn = document.getElementById('nextStockBtn');
            const infoSpan = document.getElementById('stockPaginationInfo');
            
            if (stockItems.length <= 5) {
                return; // No pagination needed
            }
            
            let currentPage = 0;
            const itemsPerPage = 5;
            const totalPages = Math.ceil(stockItems.length / itemsPerPage);
            
            // Show pagination container
            paginationContainer.style.display = 'flex';
            
            function showPage(page) {
                const start = page * itemsPerPage;
                const end = start + itemsPerPage;
                
                // Hide all items first
                stockItems.forEach(item => {
                    item.style.display = 'none';
                });
                
                // Show only items for current page
                for (let i = start; i < end && i < stockItems.length; i++) {
                    stockItems[i].style.display = '';
                }
                
                // Update pagination info
                const startItem = start + 1;
                const endItem = Math.min(end, stockItems.length);
                infoSpan.textContent = `${startItem}-${endItem} of ${stockItems.length}`;
                
                // Update button states
                prevBtn.disabled = page === 0;
                nextBtn.disabled = page === totalPages - 1;
            }
            
            // Event listeners
            prevBtn.addEventListener('click', () => {
                if (currentPage > 0) {
                    currentPage--;
                    showPage(currentPage);
                }
            });
            
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages - 1) {
                    currentPage++;
                    showPage(currentPage);
                }
            });
            
            // Initialize first page
            showPage(0);
        }

        // Initialize pagination
        setupTransactionsPagination();
        setupStockPagination();
    });
    </script>
</body>
</html>