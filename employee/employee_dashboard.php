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

$stmt = $conn->prepare("SELECT total_credits, used_credits, remaining_credits FROM employee_credit WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$balances = $stmt->get_result();
$stmt->close();

$recent_leaves_query = "
    SELECT la.leave_id, lt.leave_name, la.date_from, la.date_to, 
           la.days_requested, la.status, la.applied_date
    FROM leave_application la
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    WHERE la.employee_id = ?
    ORDER BY la.applied_date DESC
    LIMIT 5
";
$stmt = $conn->prepare($recent_leaves_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$recent_leaves = $stmt->get_result();
$stmt->close();

$stats_query = "
    SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN TRIM(LOWER(status)) = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN TRIM(LOWER(status)) = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN TRIM(LOWER(status)) = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM leave_application
    WHERE employee_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(remaining_credits,0) as total_remaining FROM employee_credit WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$total_remaining = $stmt->get_result()->fetch_assoc()['total_remaining'];
$stmt->close();
include __DIR__ . '/../includes/header.php';

?>

<style>
    .dashboard-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
        justify-content: center;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
        border-top: 4px solid;
    }

        .stat-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            will-change: transform;
        }

    .stat-card.stat-blue { border-top-color: #667eea; }
    .stat-card.stat-yellow { border-top-color: #f39c12; }
    .stat-card.stat-green { border-top-color: #27ae60; }
    .stat-card.stat-red { border-top-color: #e74c3c; }
    
    .stat-row-top {
        display: flex;
        gap: 20px;
        justify-content: center;
        width: 100%;
        margin-bottom: 20px;
    }
    
    .stat-row-bottom {
        display: flex;
        gap: 20px;
        justify-content: center;
        width: 100%;
    }
    
    .stat-card.large {
        flex: 0 1 calc(40% - 10px);
    }
    
        .stat-card:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 14px 40px rgba(0,0,0,0.15);
            z-index: 5;
        }
    
    .stat-card.small {
        flex: 0 1 calc(30% - 14px);
    }
    
    .stat-card h3 {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
        text-transform: uppercase;
    }
    
    .stat-card .number {
        font-size: 36px;
        font-weight: bold;
    }

    .stat-card.stat-blue .number { color: #667eea; }
    .stat-card.stat-yellow .number { color: #f39c12; }
    .stat-card.stat-green .number { color: #27ae60; }
    .stat-card.stat-red .number { color: #e74c3c; }
    
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
    
    .quick-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
    }
</style>

<h1>Welcome, <?php echo escape($_SESSION['full_name']); ?>!</h1>

<div class="quick-actions">
    <a href="apply_leave.php" class="btn">📝 Apply for Leave</a>
    <a href="my_leaves.php" class="btn">📋 View All My Leaves</a>
    <a href="leave_balance.php" class="btn">💳 Check Leave Balance</a>
</div>

<div class="dashboard-grid">
    <div class="stat-row-top">
        <div class="stat-card large stat-blue">
            <h3>Total Applications</h3>
            <div class="number"><?php echo $stats['total_applications']; ?></div>
        </div>
        <div class="stat-card large stat-green">
            <h3>Total Remaining Credits</h3>
            <div class="number"><?php echo $total_remaining; ?></div>
        </div>
    </div>
    <div class="stat-row-bottom">
        <div class="stat-card small stat-yellow">
            <h3>Pending</h3>
            <div class="number"><?php echo $stats['pending_count']; ?></div>
        </div>
        <div class="stat-card small stat-green">
            <h3>Approved</h3>
            <div class="number"><?php echo $stats['approved_count']; ?></div>
        </div>
        <div class="stat-card small stat-red">
            <h3>Rejected</h3>
            <div class="number"><?php echo $stats['rejected_count']; ?></div>
        </div>
    </div>
</div>

<div class="section">
    <h2>Leave Balance Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Total Credits</th>
                <th>Used</th>
                <th>Remaining</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($balances && $balances->num_rows > 0):
                $bal = $balances->fetch_assoc(); ?>
            <tr>
                <td><?php echo $bal['total_credits']; ?> </td>
                <td><?php echo $bal['used_credits']; ?> </td>
                <td><strong><?php echo $bal['remaining_credits']; ?> </strong></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="3">No credit balance found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="section">
    <h2>Recent Leave Applications</h2>
    <?php if ($recent_leaves->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Days</th>
                <th>Applied Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($leave = $recent_leaves->fetch_assoc()): ?>
            <tr>
                <td><?php echo escape($leave['leave_name']); ?></td>
                <td><?php echo formatDate($leave['date_from']); ?></td>
                <td><?php echo formatDate($leave['date_to']); ?></td>
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
    <?php else: ?>
    <p style="text-align: center; color: #666; padding: 20px;">No leave applications yet. <a href="apply_leave.php">Apply for leave now</a></p>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
