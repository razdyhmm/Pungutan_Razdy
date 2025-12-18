<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_id = (int)$_POST['leave_id'];
    $action = sanitize($_POST['action']);
    $remarks = sanitize($_POST['remarks']);
    $admin_id = $_SESSION['user_id']; 
    if ($action === 'approve' || $action === 'reject') {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        
        $leave_query = $conn->prepare("SELECT employee_id, leave_type_id, days_requested, status FROM leave_application WHERE leave_id = ?");
        $leave_query->bind_param("i", $leave_id);
        $leave_query->execute();
        $leave_data = $leave_query->get_result()->fetch_assoc();
        $leave_query->close();
        
    if ($leave_data && strtolower($leave_data['status']) === 'pending') {
            $stmt = $conn->prepare("UPDATE leave_application SET status = ?, approved_by = ?, approved_date = NOW(), remarks = ? WHERE leave_id = ?");
            $stmt->bind_param("sisi", $new_status, $admin_id, $remarks, $leave_id);
            
            if ($stmt->execute()) {
                if ($action === 'approve') {
                    $update_credit = $conn->prepare("UPDATE employee_credit SET used_credits = used_credits + ?, remaining_credits = remaining_credits - ?, last_updated = NOW() WHERE employee_id = ?");
                    $days = $leave_data['days_requested'];
                    $update_credit->bind_param("ddi", $days, $days, $leave_data['employee_id']);
                    $update_credit->execute();
                    $update_credit->close();

                    $negative_days = -$days;
                    $reason = "Leave approved (Application #$leave_id)";
                    $lt_id = (int)$leave_data['leave_type_id'];
                    $credit_log = $conn->prepare("INSERT INTO leave_credit_log (employee_id, leave_type_id, credit_change, reason, recorded_by_admin) VALUES (?, ?, ?, ?, ?)");
                    if ($credit_log) {
                        $credit_log->bind_param("iidsi", $leave_data['employee_id'], $lt_id, $negative_days, $reason, $admin_id);
                        $credit_log->execute();
                        $credit_log->close();
                    }
                }
                
                logAudit($conn, $admin_id, ucfirst($action) . ' Leave Application', 'leave_application', $leave_id, 
                    ['status' => $leave_data['status']], 
                    ['status' => $new_status, 'remarks' => $remarks]
                );
                
                setFlashMessage('success', 'Leave application ' . strtolower($new_status) . ' successfully!');
                // send notification and email to employee about status change
                require_once __DIR__ . '/../includes/notification_functions.php';
                require_once __DIR__ . '/../includes/email_functions.php';

                // fetch employee user id and name (employee table uses full_name)
                $e_stmt = $conn->prepare("SELECT l.employee_id, e.full_name, e.email, lt.leave_name FROM leave_application l JOIN employee e ON l.employee_id = e.employee_id JOIN leave_type lt ON l.leave_type_id = lt.leave_type_id WHERE l.leave_id = ?");
                $e_stmt->bind_param('i', $leave_id);
                $e_stmt->execute();
                $e_row = $e_stmt->get_result()->fetch_assoc();
                $e_stmt->close();

                $employee_id = $e_row ? $e_row['employee_id'] : null;
                $employee_name = $e_row ? $e_row['full_name'] : '';
                $leave_type_name = $e_row ? $e_row['leave_name'] : '';

                if ($employee_id) {
                    notifyEmployeeLeaveStatus($conn, $employee_id, $leave_id, $new_status, $leave_type_name);
                    emailLeaveStatusToEmployee($conn, $leave_id);
                }
            } else {
                setFlashMessage('error', 'Failed to update leave application.');
            }
            $stmt->close();
        } else {
            setFlashMessage('error', 'Leave application not found or already processed.');
        }
    }
    
    header('Location: ' . BASE_URL . '/admin/admin_manage_leave.php');
    exit();
}

$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$where_conditions = [];
$params = [];
$types = '';

if ($status_filter && $status_filter !== 'All') {
    $where_conditions[] = "LOWER(la.status) = ?";
    $params[] = strtolower($status_filter);
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(e.full_name LIKE ? OR lt.leave_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$leaves_query = "
    SELECT la.leave_id, e.full_name, e.email, lt.leave_name,
           la.date_from, la.date_to, la.days_requested, 
           la.reason, la.status, la.applied_date, la.remarks,
           approver.full_name as approver_name
    FROM leave_application la
    JOIN employee e ON la.employee_id = e.employee_id
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    LEFT JOIN admin approver ON la.approved_by = approver.admin_id
    $where_clause
    ORDER BY la.applied_date DESC
";

if ($params) {
    $stmt = $conn->prepare($leaves_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $leaves = $stmt->get_result();
} else {
    $leaves = $conn->query($leaves_query);
}

                include __DIR__ . '/../includes/header.php';

?>

<style>
    .filters {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .filters form {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 600;
        font-size: 14px;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .btn-filter {
        padding: 10px 30px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        height: 42px;
    }
    
    .leaves-container {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .leave-card {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        transition: border-color 0.3s;
    }
    
    .leave-card:hover {
        border-color: #667eea;
    }
    
    .leave-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .leave-info h3 {
        color: #333;
        margin-bottom: 5px;
    }
    
    .leave-info p {
        color: #666;
        font-size: 14px;
        margin: 3px 0;
    }
    
    .badge {
        padding: 8px 15px;
        border-radius: 15px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    
    .leave-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .detail-item {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
    }
    
    .detail-item label {
        display: block;
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .detail-item .value {
        color: #333;
        font-weight: 600;
    }
    
    .leave-reason {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    
    .leave-reason label {
        display: block;
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .leave-actions {
        display: flex;
        gap: 10px;
    }

    .btn-print-admin {
        padding: 8px 12px;
        background: #34495e;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
    }
    .btn-print-admin:hover { background: #2c3e50; }
    
    .action-form {
        flex: 1;
    }
    
    .remarks-input {
        width: 100%;
        padding: 8px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 13px;
    }
    
    .btn-approve {
        width: 100%;
        padding: 10px;
        background: #27ae60;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .btn-reject {
        width: 100%;
        padding: 10px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .btn-approve:hover { background: #229954; }
    .btn-reject:hover { background: #c0392b; }
</style>

<h1>Manage Leave Applications</h1>

<div class="filters">
    <form method="GET" action="">
        <div class="filter-group">
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="All" <?php echo $status_filter === 'All' ? 'selected' : ''; ?>>All</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="search">Search</label>
            <input type="text" name="search" id="search" placeholder="Employee name or leave type" value="<?php echo escape($search); ?>">
        </div>
        
        <button type="submit" class="btn-filter">Filter</button>
    </form>
</div>

<div class="leaves-container">
    <?php if ($leaves->num_rows > 0): ?>
        <?php while ($leave = $leaves->fetch_assoc()): ?>
        <div class="leave-card">
            <div class="leave-header">
                <div class="leave-info">
                    <h3><?php echo escape($leave['full_name']); ?></h3>
                    <p>📧 <?php echo escape($leave['email']); ?></p>
                    <p>📅 Applied: <?php echo formatDateTime($leave['applied_date']); ?></p>
                </div>
                <span class="badge badge-<?php echo getStatusBadge($leave['status']); ?>">
                    <?php echo escape($leave['status']); ?>
                </span>
            </div>
            <div style="margin-top:12px;">
                <a href="<?php echo BASE_URL; ?>/print/print.php?type=leave&id=<?php echo $leave['leave_id']; ?>" target="_blank" class="btn-print-admin">🖨️ Print</a>
            </div>
            
            <div class="leave-details">
                <div class="detail-item">
                    <label>Leave Type</label>
                    <div class="value"><?php echo escape($leave['leave_name']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Start Date</label>
                    <div class="value"><?php echo formatDate($leave['date_from']); ?></div>
                </div>
                <div class="detail-item">
                    <label>End Date</label>
                    <div class="value"><?php echo formatDate($leave['date_to']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Days Requested</label>
                    <div class="value"><?php echo $leave['days_requested']; ?> days</div>
                </div>
            </div>
            
            <div class="leave-reason">
                <label>Reason</label>
                <div><?php echo nl2br(escape($leave['reason'])); ?></div>
            </div>
            
            <?php if ($leave['remarks']): ?>
            <div class="leave-reason" style="background: #fff3cd;">
                <label>Admin Remarks</label>
                <div><?php echo nl2br(escape($leave['remarks'])); ?></div>
                <?php if ($leave['approver_name']): ?>
                <p style="font-size: 12px; color: #666; margin-top: 5px;">By: <?php echo escape($leave['approver_name']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (strtolower($leave['status']) === 'pending'): ?>
            <div class="leave-actions">
                <form method="POST" action="" class="action-form">
                    <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="text" name="remarks" class="remarks-input" placeholder="Optional remarks (visible to employee)">
                    <button type="submit" class="btn-approve">✓ Approve</button>
                </form>
                
                <form method="POST" action="" class="action-form">
                    <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="text" name="remarks" class="remarks-input" placeholder="Reason for rejection (required)" required>
                    <button type="submit" class="btn-reject">✗ Reject</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
    <p style="text-align: center; color: #666; padding: 40px;">No leave applications found.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>