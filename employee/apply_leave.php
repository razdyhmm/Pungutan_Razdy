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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type_id = 0;
    if (isset($_POST['leave_type_id'])) {
        $leave_type_id = (int)$_POST['leave_type_id'];
    } elseif (isset($_POST['leave_type'])) {
        $leave_type_id = (int)$_POST['leave_type'];
    }

    $start_date = sanitize(isset($_POST['date_from']) ? $_POST['date_from'] : $_POST['start_date']);
    $days_requested = (int)$_POST['days_requested'];
    $reason = sanitize($_POST['reason']);
    
    if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        setFlashMessage('error', 'Start date cannot be in the past.');
    } else if ($days_requested < 1) {
        setFlashMessage('error', 'Days requested must be at least 1.');
    } else {
        $end_date = calculateEndDate($conn, $start_date, $days_requested);
        
    $balance_check = $conn->prepare("SELECT remaining_credits FROM employee_credit WHERE employee_id = ?");
    $balance_check->bind_param("i", $employee_id);
    $balance_check->execute();
    $balance_result = $balance_check->get_result();
    $balance = $balance_result->fetch_assoc();
    $balance_check->close();

    if ($balance && $balance['remaining_credits'] >= $days_requested) {
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleFileUpload($_FILES['attachment']);
                if (isset($upload_result['success'])) {
                    $attachment = $upload_result['filename'];
                } else {
                    setFlashMessage('error', $upload_result['error']);
                    header('Location: ' . BASE_URL . '/employee/apply_leave.php');
                    exit();
                }
            }

            $stmt = $conn->prepare("INSERT INTO leave_application (employee_id, leave_type_id, date_from, date_to, days_requested, reason, attachment, applied_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $status_val = 'pending';
            $stmt->bind_param("iississs", $employee_id, $leave_type_id, $start_date, $end_date, $days_requested, $reason, $attachment, $status_val);
            
            if ($stmt->execute()) {
                $leave_id = $conn->insert_id;

                logAudit($conn, $employee_id, 'Created Leave Application', 'leave_application', $leave_id, null, [
                    'leave_type_id' => $leave_type_id,
                    'start_date' => $start_date,
                    'days_requested' => $days_requested,
                    'end_date' => $end_date
                ]);
                        // Send notifications & emails to admins
                        require_once __DIR__ . '/../includes/notification_functions.php';
                        require_once __DIR__ . '/../includes/email_functions.php';

                        // get leave type name
                        $lt_stmt = $conn->prepare("SELECT leave_name FROM leave_type WHERE leave_type_id = ?");
                        $lt_stmt->bind_param('i', $leave_type_id);
                        $lt_stmt->execute();
                        $lt_row = $lt_stmt->get_result()->fetch_assoc();
                        $lt_stmt->close();
                        $leave_name = $lt_row ? $lt_row['leave_name'] : '';


                        notifyAdminsNewLeave($conn, $leave_id, getEmployeeName($conn, $employee_id), $leave_name);
                        emailLeaveApplicationToAdmin($conn, $leave_id);

                        // Send designed email notification to employee
                        require_once __DIR__ . '/../includes/email_functions.php';
                        $emp_email_stmt = $conn->prepare("SELECT email, full_name FROM employee WHERE employee_id = ?");
                        $emp_email_stmt->bind_param('i', $employee_id);
                        $emp_email_stmt->execute();
                        $emp_row = $emp_email_stmt->get_result()->fetch_assoc();
                        $emp_email_stmt->close();
                        if ($emp_row && !empty($emp_row['email'])) {
                            $emp_subject = "Leave Application Submitted";
                            $emp_message = '<h2>Leave Application Submitted</h2>';
                            $emp_message .= '<p>Dear ' . htmlspecialchars($emp_row['full_name']) . ',</p>';
                            $emp_message .= '<div class="info-box">';
                            $emp_message .= '<p><strong>Leave Type:</strong> ' . htmlspecialchars($leave_name) . '</p>';
                            $emp_message .= '<p><strong>Start Date:</strong> ' . formatDate($start_date) . '</p>';
                            $emp_message .= '<p><strong>End Date:</strong> ' . formatDate($end_date) . '</p>';
                            $emp_message .= '</div>';
                            $emp_message .= '<p>Your leave application has been submitted and is pending approval.</p>';
                            $emp_message .= '<p>Thank you.</p>';
                            sendEmail($emp_row['email'], $emp_subject, $emp_message);
                        }

                        setFlashMessage('success', 'Leave application submitted successfully! Your leave will be from ' . formatDate($start_date) . ' to ' . formatDate($end_date));
                        header('Location: ' . BASE_URL . '/employee/my_leaves.php');
                        exit();
            } else {
                setFlashMessage('error', 'Failed to submit leave application.');
            }
            $stmt->close();
        } else {
            $remaining = isset($balance['remaining_credits']) ? $balance['remaining_credits'] : 0;
            setFlashMessage('error', 'Insufficient leave balance. You have ' . $remaining . ' days remaining.');
        }
    }
}

$leave_types = $conn->query("SELECT leave_type_id, leave_name, description, max_days_per_year FROM leave_type WHERE is_active = 1");

$upcoming_holidays_query = "
    SELECT holiday_name, holiday_date, holiday_type
    FROM holidays
    WHERE holiday_date >= CURDATE()
    ORDER BY holiday_date
    LIMIT 10
";
$upcoming_holidays = $conn->query($upcoming_holidays_query);

                        include __DIR__ . '/../includes/header.php';

?>

<style>
    .form-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 900px;
        margin: 0 auto;
    }
    
    .form-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    
    .form-section {
        display: flex;
        flex-direction: column;
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
    
    .form-group input,
    .form-group select,
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
        min-height: 100px;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .balance-info {
        background: #e3f2fd;
        padding: 10px;
        border-radius: 5px;
        margin-top: 10px;
        font-size: 14px;
        color: #1976d2;
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
        transition: transform 0.2s;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
    }
    
    .btn-cancel {
        background: #6c757d;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
    }
    
    .holidays-sidebar {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }
    
    .holidays-sidebar h3 {
        color: #333;
        margin-bottom: 15px;
        font-size: 18px;
    }
    
    .holiday-item {
        padding: 10px;
        background: white;
        border-radius: 5px;
        margin-bottom: 10px;
        border-left: 3px solid #f39c12;
    }
    
    .holiday-item.regular {
        border-left-color: #e74c3c;
    }
    
    .holiday-item.special {
        border-left-color: #f39c12;
    }
    
    .holiday-name {
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    .holiday-date {
        color: #666;
        font-size: 12px;
        margin-top: 3px;
    }
    
    .holiday-type {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 600;
        margin-top: 5px;
    }
    
    .holiday-type.regular {
        background: #fefefeff;
        color: #721c24;
    }
    
    .holiday-type.special {
        background: #fff3cd;
        color: #856404;
    }
    
    .info-note {
        background: #fff3cd;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border-left: 4px solid #f39c12;
    }
    
    .info-note strong {
        color: #856404;
    }
    
    .info-note p {
        margin: 5px 0;
        color: #856404;
        font-size: 14px;
    }
</style>

<h1>Apply for Leave</h1>

<div class="form-container">
    <div class="info-note">
    <div class="form-layout">
        <div class="form-section">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="leave_type_id">Leave Type *</label>
                    <select name="leave_type_id" id="leave_type_id" required>
                        <option value="">Select Leave Type</option>
                        <?php 
                        $leave_types->data_seek(0);
                        while ($type = $leave_types->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $type['leave_type_id']; ?>">
                            <?php echo escape($type['leave_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="balance-info" id="balance-info" style="display: none;">
                        Available balance: <strong id="balance-display">0</strong> days
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Start Date *</label>
                    <input type="date" name="date_from" id="date_from" required min="<?php echo date('Y-m-d'); ?>">
                    <small style="color: #666; display: block; margin-top: 5px;">This should be a working day (Monday-Friday)</small>
                </div>
                
                <div class="form-group">
                    <label for="days_requested">Number of Days *</label>
                    <input type="number" name="days_requested" id="days_requested" required min="1" placeholder="e.g., 3">
                    <small style="color: #666; display: block; margin-top: 5px;">Enter working days only (excluding weekends & holidays)</small>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason *</label>
                    <textarea name="reason" id="reason" required placeholder="Please provide a reason for your leave request"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="attachment">Attachment (Optional)</label>
                    <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small style="color: #666; display: block; margin-top: 5px;">Max 5MB. Allowed: PDF, JPG, PNG, DOC, DOCX</small>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn-submit">Submit Application</button>
                    <a href="employee_dashboard.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
        
        <div class="holidays-sidebar">
            <h3>🗓️ Upcoming Holidays</h3>
            <?php if ($upcoming_holidays->num_rows > 0): ?>
                <?php while ($holiday = $upcoming_holidays->fetch_assoc()): ?>
                <div class="holiday-item <?php echo strtolower($holiday['holiday_type']); ?>">
                    <div class="holiday-name"><?php echo escape($holiday['holiday_name']); ?></div>
                    <div class="holiday-date"><?php echo formatDate($holiday['holiday_date']); ?></div>
                    <span class="holiday-type <?php echo strtolower($holiday['holiday_type']); ?>">
                        <?php echo $holiday['holiday_type']; ?>
                    </span>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No upcoming holidays</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>