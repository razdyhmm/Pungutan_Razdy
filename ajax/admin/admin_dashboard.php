<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$total_employees_query = "SELECT COUNT(*) as count FROM employee WHERE status = 'active'";
$total_employees = $conn->query($total_employees_query)->fetch_assoc()['count'];

$pending_leaves_query = "SELECT COUNT(*) as count FROM leave_application WHERE TRIM(LOWER(status)) = 'pending'";
$pending_leaves = $conn->query($pending_leaves_query)->fetch_assoc()['count'];

$approved_today_query = "SELECT COUNT(*) as count FROM leave_application WHERE TRIM(LOWER(status)) = 'approved' AND DATE(approved_date) = CURDATE()";
$approved_today = $conn->query($approved_today_query)->fetch_assoc()['count'];

$recent_leaves_query = "
    SELECT la.leave_id, e.full_name, lt.leave_name, 
           la.date_from, la.date_to, la.days_requested, 
           la.status, la.applied_date
    FROM leave_application la
    JOIN employee e ON la.employee_id = e.employee_id
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    ORDER BY la.applied_date DESC
    LIMIT 10
";
$recent_leaves = $conn->query($recent_leaves_query);

$on_leave_today_query = "
    SELECT e.full_name, lt.leave_name, la.date_from, la.date_to
    FROM leave_application la
    JOIN employee e ON la.employee_id = e.employee_id
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    WHERE la.status = 'approved' 
    AND CURDATE() BETWEEN la.date_from AND la.date_to
    ORDER BY e.full_name
";
 $on_leave_today = $conn->query($on_leave_today_query);

include __DIR__ . '/../includes/header.php';
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
        border-top: 4px solid;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        will-change: transform;
    }

    .stat-card:hover {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 0 14px 40px rgba(0,0,0,0.15);
        z-index: 5;
    }
    
    .stat-card:nth-child(1) { border-top-color: #667eea; }
    .stat-card:nth-child(2) { border-top-color: #f39c12; }
    .stat-card:nth-child(3) { border-top-color: #27ae60; }
    .stat-card:nth-child(4) { border-top-color: #e74c3c; }
    
    .stat-card h3 {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
        text-transform: uppercase;
    }
    
    .stat-card .number {
        font-size: 48px;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .stat-card:nth-child(1) .number { color: #667eea; }
    .stat-card:nth-child(2) .number { color: #f39c12; }
    .stat-card:nth-child(3) .number { color: #27ae60; }
    .stat-card:nth-child(4) .number { color: #e74c3c; }
    
    .section {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .section h2 {
        color: #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #dee2e6;
    }
    
    table td {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }
    
    .badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    
    .quick-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    /* Compact quick-action buttons to fit more items */
    .quick-actions .btn {
        padding: 6px 10px;
        font-size: 13px;
        border-radius: 6px;
    }
    
    .btn {
        display: inline-block;
        padding: 10px 20px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background 0.3s;
    }
    
    .btn:hover {
        background: #5568d3;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }
    
    .btn-success {
        background: #27ae60;
    }
    
    .btn-success:hover {
        background: #219a52;
    }
    
    .btn-danger {
        background: #e74c3c;
    }
    
    .btn-danger:hover {
        background: #c0392b;
    }
    
    .mb-3 {
        margin-bottom: 1rem;
    }
</style>

<h1>Admin Dashboard</h1>

<div class="quick-actions">
    <a href="admin_manage_leave.php" class="btn">📋 Manage Leaves</a>
    <a href="admin_manage_employees.php" class="btn">👥 Manage Employees</a>
    <a href="admin_send_notification.php" class="btn">✉️ Send Notification</a>
    <a href="admin_credit_management.php" class="btn">💳 Credit Management</a>
    <a href="admin_manage_holidays.php" class="btn">🗓️ Manage Holidays</a>
</div>

<div class="dashboard-grid">
    <div class="stat-card">
        <h3>Active Employees</h3>
        <div class="number"><?php echo $total_employees; ?></div>
    </div>
    <div class="stat-card">
        <h3>Pending Leaves</h3>
        <div class="number"><?php echo $pending_leaves; ?></div>
    </div>
    <div class="stat-card">
        <h3>Approved Today</h3>
        <div class="number"><?php echo $approved_today; ?></div>
    </div>
</div>

<div class="section">
    <h2>Employees on Leave</h2>
    <?php if ($on_leave_today->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Leave Type</th>
                <th>Leave Period</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($leave = $on_leave_today->fetch_assoc()): ?>
            <tr>
                <td><?php echo escape($leave['full_name']); ?></td>
                <td><?php echo escape($leave['leave_name']); ?></td>
                <td><?php echo formatDate($leave['date_from']); ?> - <?php echo formatDate($leave['date_to']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="text-align: center; color: #666; padding: 20px;">No employees on leave today.</p>
    <?php endif; ?>
</div>

<div class="section">
    <h2>Recent Leave Applications</h2>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Period</th>
                <th>Days</th>
                <th>Applied Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($leave = $recent_leaves->fetch_assoc()): ?>
            <tr>
                <td><?php echo escape($leave['full_name']); ?></td>
                <td><?php echo escape($leave['leave_name']); ?></td>
                <td><?php echo formatDate($leave['date_from']); ?> - <?php echo formatDate($leave['date_to']); ?></td>
                <td><?php echo $leave['days_requested']; ?></td>
                <td><?php echo formatDateTime($leave['applied_date']); ?></td>
                <td>
                    <span class="badge badge-<?php echo getStatusBadge($leave['status']); ?>">
                        <?php echo escape($leave['status']); ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>