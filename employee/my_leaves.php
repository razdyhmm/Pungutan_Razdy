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

$leaves_query = "
    SELECT la.leave_id, lt.leave_name, la.date_from, la.date_to, 
        la.days_requested, la.reason, la.status, la.applied_date,
        la.approved_date, la.remarks, la.attachment,
        a.full_name as approver_name
    FROM leave_application la
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    LEFT JOIN admin a ON la.approved_by = a.admin_id
    WHERE la.employee_id = ?
    ORDER BY la.applied_date DESC
";
$stmt = $conn->prepare($leaves_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$leaves = $stmt->get_result();
$stmt->close();
include __DIR__ . '/../includes/header.php';

?>

<style>
    .leaves-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
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
    
    .btn-view {
        padding: 5px 15px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-size: 12px;
    }
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
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
    }
</style>

<h1>My Leave Applications</h1>
    <div style="margin-top:10px; margin-bottom:10px;">
    <a href="<?php echo BASE_URL; ?>/print/print.php?type=history" target="_blank" class="btn-print">🖨️ Print Full History</a>
</div>

<div class="leaves-container">
    <?php if ($leaves->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Leave Type</th>
                <th>Period</th>
                <th>Days</th>
                <th>Applied Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($leave = $leaves->fetch_assoc()): ?>
            <tr>
                <td><?php echo escape($leave['leave_name']); ?></td>
                <td>
                    <?php echo formatDate($leave['date_from']); ?> - 
                    <?php echo formatDate($leave['date_to']); ?>
                </td>
                <td><?php echo $leave['days_requested']; ?> days</td>
                <td><?php echo formatDateTime($leave['applied_date']); ?></td>
                <td>
                    <span class="badge badge-<?php echo getStatusBadge($leave['status']); ?>">
                        <?php echo escape($leave['status']); ?>
                    </span>
                </td>
                <td>
                    <a href="view_leave.php?id=<?php echo $leave['leave_id']; ?>" class="btn-view">View Details</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="no-data">
        <p>You haven't applied for any leave yet.</p>
        <a href="apply_leave.php" style="color: #667eea; text-decoration: none; font-weight: 600;">Apply for leave now →</a>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>


