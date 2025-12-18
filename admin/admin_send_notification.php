<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once __DIR__ . '/../includes/notification_functions.php';
require_once __DIR__ . '/../includes/email_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_type = sanitize($_POST['recipient_type']);
    $title = sanitize($_POST['title']);
    $message = sanitize($_POST['message']);
    $notification_type = sanitize($_POST['notification_type']);
    $send_email = isset($_POST['send_email']);
    
    $count = 0;
    
    if ($recipient_type === 'all_employees') {
        // Send to all employees
        $query = "SELECT employee_id, email FROM employee WHERE status = 'active'";
        $result = $conn->query($query);
        
        while ($emp = $result->fetch_assoc()) {
            createNotification($conn, 'employee', $emp['employee_id'], $title, $message, $notification_type);
            if ($send_email) {
                sendEmail($emp['email'], $title, $message);
            }
            $count++;
        }
    } else if ($recipient_type === 'all_admins') {
        // Send to all admins
        $query = "SELECT admin_id, email FROM admin WHERE status = 'active'";
        $result = $conn->query($query);
        
        while ($admin = $result->fetch_assoc()) {
            createNotification($conn, 'admin', $admin['admin_id'], $title, $message, $notification_type);
            if ($send_email) {
                sendEmail($admin['email'], $title, $message);
            }
            $count++;
        }
    } else if (is_numeric($recipient_type)) {
        // Send to specific employee
        $employee_id = (int)$recipient_type;
        createNotification($conn, 'employee', $employee_id, $title, $message, $notification_type);
        
        if ($send_email) {
            $emp_query = "SELECT email FROM employee WHERE employee_id = ?";
            $stmt = $conn->prepare($emp_query);
            $stmt->bind_param("i", $employee_id);
            $stmt->execute();
            $emp = $stmt->get_result()->fetch_assoc();
            if ($emp) {
                sendEmail($emp['email'], $title, $message);
            }
        }
        $count = 1;
    }
    
    setFlashMessage('success', "Notification sent to $count recipient(s)!");
    header('Location: ' . BASE_URL . '/admin/admin_send_notification.php');
    exit();
}
// Get all employees for dropdown
$employees = $conn->query("SELECT employee_id, full_name, email FROM employee WHERE status = 'active' ORDER BY full_name");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
    .form-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 700px;
        margin: 0 auto;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 600;
    }
    
    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 120px;
    }
    
    .form-group input[type="checkbox"] {
        width: auto;
        margin-right: 8px;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
</style>

<h1>Send Notification</h1>

<div class="form-container">
    <form method="POST" action="">
        <div class="form-group">
            <label for="recipient_type">Send To *</label>
            <select name="recipient_type" id="recipient_type" required>
                <option value="">Select Recipients</option>
                <option value="all_employees">All Employees</option>
                <option value="all_admins">All Admins</option>
                <optgroup label="Individual Employees">
                    <?php while ($emp = $employees->fetch_assoc()): ?>
                    <option value="<?php echo $emp['employee_id']; ?>">
                        <?php echo escape($emp['full_name']); ?> (<?php echo escape($emp['email']); ?>)
                    </option>
                    <?php endwhile; ?>
                </optgroup>
            </select>
        </div>
        
        <div class="form-group">
            <label for="notification_type">Notification Type *</label>
            <select name="notification_type" id="notification_type" required>
                <option value="info">Info (Blue)</option>
                <option value="success">Success (Green)</option>
                <option value="warning">Warning (Orange)</option>
                <option value="danger">Important (Red)</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" required placeholder="e.g., System Maintenance Notice">
        </div>
        
        <div class="form-group">
            <label for="message">Message *</label>
            <textarea id="message" name="message" required placeholder="Enter your notification message here..."></textarea>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="send_email" value="1">
                Also send via email
            </label>
        </div>
        
        <button type="submit" class="btn-submit">📤 Send Notification</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>