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
$leave_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$leave_query = "
    SELECT la.*, lt.leave_name, lt.description,
        a.full_name as approver_name
    FROM leave_application la
    JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
    LEFT JOIN admin a ON la.approved_by = a.admin_id
    WHERE la.leave_id = ? AND la.employee_id = ?
";
$stmt = $conn->prepare($leave_query);
$stmt->bind_param("ii", $leave_id, $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('error', 'Leave application not found.');
    header('Location: ' . BASE_URL . '/employee/my_leaves.php');
    exit();
}

$leave = $result->fetch_assoc();
$stmt->close();

include __DIR__ . '/../includes/header.php';

?>

<style>
    .leave-details {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 800px;
        margin: 0 auto;
    }
    
    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #667eea;
    }
    
    .detail-header h2 {
        color: #333;
        margin: 0;
    }
    
    .status-badge {
        padding: 10px 20px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .status-badge.success { background: #d4edda; color: #155724; }
    .status-badge.warning { background: #fff3cd; color: #856404; }
    .status-badge.danger { background: #f8d7da; color: #721c24; }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .detail-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }
    
    .detail-item label {
        display: block;
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
        text-transform: uppercase;
    }
    
    .detail-item .value {
        font-size: 16px;
        color: #333;
        font-weight: 600;
    }
    
    .detail-full {
        grid-column: 1 / -1;
    }
    
    .btn-back {
        display: inline-block;
        padding: 10px 20px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 20px;
    }
    
    .btn-back:hover {
        background: #5a6268;
    }
    .btn-print {
        display: inline-block;
        padding: 10px 18px;
        background: #34495e;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 20px;
        font-weight: 600;
    }
    .btn-print:hover { background: #2c3e50; }
    
    .attachment-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }
    
    .attachment-link:hover {
        text-decoration: underline;
    }
</style>

<h1>Leave Application Details</h1>

<div class="leave-details">
    <div class="detail-header">
        <h2>Application #<?php echo $leave['leave_id']; ?></h2>
        <span class="status-badge <?php echo getStatusBadge($leave['status']); ?>">
            <?php echo escape($leave['status']); ?>
        </span>
    </div>
    
    <div class="detail-grid">
        <div class="detail-item">
            <label>Leave Type</label>
            <div class="value"><?php echo escape($leave['leave_name']); ?></div>
        </div>
        
        <div class="detail-item">
            <label>Days Requested</label>
            <div class="value"><?php echo $leave['days_requested']; ?> days</div>
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
            <label>Applied Date</label>
            <div class="value"><?php echo formatDateTime($leave['applied_date']); ?></div>
        </div>
        
        <?php if ($leave['approved_date']): ?>
        <div class="detail-item">
            <label><?php echo (strtolower($leave['status']) === 'approved') ? 'Approved' : 'Rejected'; ?> Date</label>
            <div class="value"><?php echo formatDateTime($leave['approved_date']); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($leave['approver_name']): ?>
        <div class="detail-item <?php echo !$leave['approved_date'] ? 'detail-full' : ''; ?>">
            <label><?php echo (strtolower($leave['status']) === 'approved') ? 'Approved' : 'Rejected'; ?> By</label>
            <div class="value"><?php echo escape($leave['approver_name']); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="detail-item detail-full">
            <label>Reason</label>
            <div class="value" style="font-weight: normal; white-space: pre-wrap;">
                <?php echo escape($leave['reason']); ?>
            </div>
        </div>
        
        <?php if ($leave['remarks']): ?>
        <div class="detail-item detail-full">
            <label>Admin Remarks</label>
            <div class="value" style="font-weight: normal; white-space: pre-wrap; color: #e74c3c;">
                <?php echo escape($leave['remarks']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($leave['attachment']): ?>
        <div class="detail-item detail-full">
            <label>Attachment</label>
            <div class="value">
                             <a href="<?php echo BASE_URL; ?>/assets/uploads/<?php echo escape($leave['attachment']); ?>" 
                                 target="_blank" 
                                 class="attachment-link">
                    📎 View Attachment
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div style="display:flex; gap:10px; align-items:center; margin-top:20px;">
        <a href="<?php echo BASE_URL; ?>/employee/my_leaves.php" class="btn-back">← Back to My Leaves</a>
        <a href="<?php echo BASE_URL; ?>/print/print.php?type=leave&id=<?php echo $leave['leave_id']; ?>" target="_blank" class="btn-print">🖨️ Print</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

