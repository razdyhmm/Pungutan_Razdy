<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . BASE_URL . '/admin/admin_dashboard.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];

$stmt = $conn->prepare("SELECT total_credits, used_credits, remaining_credits, last_updated FROM employee_credit WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$credit = $stmt->get_result()->fetch_assoc();
$stmt->close();

$col_check = $conn->query("SHOW COLUMNS FROM leave_credit_log LIKE 'recorded_by'");
if ($col_check && $col_check->num_rows > 0) {
    $history_query = "
        SELECT lcl.credit_change, lcl.reason, lcl.date_recorded,
               lt.leave_name, e.full_name as recorded_by_name
        FROM leave_credit_log lcl
        JOIN leave_type lt ON lcl.leave_type_id = lt.leave_type_id
        LEFT JOIN employee e ON lcl.recorded_by = e.employee_id
        WHERE lcl.employee_id = ?
        ORDER BY lcl.date_recorded DESC
        LIMIT 20
    ";
} else {
    $history_query = "
        SELECT lcl.credit_change, lcl.reason, lcl.date_recorded,
               lt.leave_name, '' as recorded_by_name
        FROM leave_credit_log lcl
        JOIN leave_type lt ON lcl.leave_type_id = lt.leave_type_id
        WHERE lcl.employee_id = ?
        ORDER BY lcl.date_recorded DESC
        LIMIT 20
    ";
}

$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$history = $stmt->get_result();
$stmt->close();
include __DIR__ . '/../includes/header.php';

?>

<style>
    .balance-grid {
        max-width: 1000px;
        margin: 20px 0 30px;
        padding: 0 20px;
    }
    
    .balance-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #667eea;
        width: 50%;
        margin: 0 0 20px;
    }

    /* Print button styles removed from this page. */
    
    .balance-card h3 {
        color: #667eea;
        margin-bottom: 20px;
        font-size: 20px;
        text-align: center;
    }
    
    .balance-card p {
        color: #666;
        font-size: 14px;
        margin: 0;
    }
    
    .balance-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 10px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .balance-stat {
        text-align: center;
        flex: 1;
        padding: 0 15px;
    }
    
    .balance-stat label {
        display: block;
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .balance-stat .value {
        font-size: 20px;
        font-weight: bold;
        color: #333;
    }
    
    .history-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 1000px;
        margin: 0 auto;
    }

    .history-section .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #667eea;
    }

    .history-section .history-header h2 {
        color: #333;
        margin: 0;
        font-size: 18px;
    }

    /* Print button styles for balance print */
    .btn-print {
        display: inline-block;
        padding: 8px 12px;
        background: #34495e;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
        font-size: 13px;
    }
    .btn-print:hover { background: #2c3e50; }

    /* Small print icon to align with table header 'Recorded By' */
    .btn-print-small {
        display: inline-block;
        padding: 4px 8px;
        background: #667eea;
        color: #fff;
        text-decoration: none;
        border-radius: 4px;
        font-size: 11px;
        margin-left: 6px;
    }
    .btn-print-small:hover { background: #5a6ee0; }
    
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    
    table th {
        background: #f8f9fa;
        padding: 6px 10px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }
    
    table td {
        padding: 6px 10px;
        border-bottom: 1px solid #dee2e6;
    }
    
    table th:first-child,
    table td:first-child {
        width: 120px;
    }
    
    table th:nth-child(2),
    table td:nth-child(2) {
        width: 100px;
    }
    
    table th:nth-child(3),
    table td:nth-child(3) {
        width: 70px;
        text-align: center;
    }
    
    table th:nth-child(5),
    table td:nth-child(5) {
        width: 100px;
    }
    
    table th:nth-child(4),
    table td:nth-child(4) {
        white-space: normal;
    }
    
    .credit-positive {
        color: #27ae60;
        font-weight: 600;
    }
    
    .credit-negative {
        color: #e74c3c;
        font-weight: 600;
    }
    
    table tr:hover {
        background-color: #f8f9fa;
    }
    
    table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }
</style>

<div class="balance-grid">
    <h1>Leave Balance</h1>

    <div class="balance-card">
        <h3>Credits</h3>
        <div class="balance-stats">
            <div class="balance-stat">
                <label>Total</label>
                <div class="value"><?php echo isset($credit['total_credits']) ? $credit['total_credits'] : '0'; ?></div>
            </div>
            <div class="balance-stat">
                <label>Used</label>
                <div class="value" style="color: #e74c3c;"><?php echo isset($credit['used_credits']) ? $credit['used_credits'] : '0'; ?></div>
            </div>
            <div class="balance-stat">
                <label>Remaining</label>
                <div class="value" style="color: #27ae60;"><?php echo isset($credit['remaining_credits']) ? $credit['remaining_credits'] : '0'; ?></div>
            </div>
        </div>
        <p style="font-size: 12px; color: #999; margin-top: 15px;">
            Last updated: <?php echo isset($credit['last_updated']) ? formatDateTime($credit['last_updated']) : 'Never'; ?>
        </p>
    </div>
</div>

<div class="history-section">
    <div class="history-header">
        <h2>Credit History</h2>
        <a href="<?php echo BASE_URL; ?>/print/print.php?type=balance" target="_blank" class="btn-print">🖨️ Print Balance</a>
    </div>
    <?php if ($history->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Leave Type</th>
                <th>Change</th>
                <th>Reason</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($record = $history->fetch_assoc()): ?>
            <tr>
                <td><?php echo formatDateTime($record['date_recorded']); ?></td>
                <td><?php echo escape($record['leave_name']); ?></td>
                <td class="<?php echo $record['credit_change'] >= 0 ? 'credit-positive' : 'credit-negative'; ?>">
                    <?php echo ($record['credit_change'] >= 0 ? '+' : '') . $record['credit_change']; ?> days
                </td>
                <td><?php echo escape($record['reason']); ?></td>
                <td><?php echo escape($record['recorded_by_name']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="text-align: center; color: #666; padding: 20px;">No credit history available.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>