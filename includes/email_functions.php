<?php
// Ensure constants are available for SITE_NAME, etc.
require_once __DIR__ . '/../config/constants.php';

/**
 * Send email using PHP mail() function
 * For production, consider using PHPMailer or similar
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $message, $from_name = null, $plain = false) {
    if ($from_name === null) {
        $from_name = SITE_NAME;
    }

    $mail = new PHPMailer(true);
    try {
        // SMTP configuration for Gmail
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'razdypungutan@gmail.com'; // <-- PUT YOUR GMAIL ADDRESS HERE
        $mail->Password = 'nauexgxcfkiomtlu';      // <-- PUT YOUR APP PASSWORD HERE
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($mail->Username, $from_name);
        $mail->addAddress($to);
        $mail->isHTML(!$plain ? true : false);
        $mail->Subject = $subject;
        if ($plain) {
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
        } else {
            $mail->Body = getEmailTemplate($subject, $message);
            $mail->AltBody = strip_tags($message);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Helper for sending plain emails (no design)
function sendPlainEmail($to, $subject, $message, $from_name = null) {
    return sendEmail($to, $subject, $message, $from_name, true);
}

/**
 * Email template wrapper
 */
function getEmailTemplate($subject, $content) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f4f7fa;
                margin: 0;
                padding: 0;
            }
            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .email-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .email-header h1 {
                margin: 0;
                font-size: 24px;
            }
            .email-body {
                padding: 30px;
            }
            .email-footer {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
                border-top: 1px solid #dee2e6;
            }
            .button {
                display: inline-block;
                padding: 12px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
            .info-box {
                background: #e3f2fd;
                border-left: 4px solid #2196f3;
                padding: 15px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>' . SITE_NAME . '</h1>
                <p>' . $subject . '</p>
            </div>
            <div class="email-body">
                ' . $content . '
            </div>
            <div class="email-footer">
                <p>This is an automated email from ' . SITE_NAME . '</p>
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return $html;
}

/**
 * Send leave application notification to admin
 */
function emailLeaveApplicationToAdmin($conn, $leave_id) {
    // Get leave details
    $query = "
        SELECT la.*, e.full_name, e.email as emp_email, lt.leave_name
        FROM leave_application la
        JOIN employee e ON la.employee_id = e.employee_id
        JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
        WHERE la.leave_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$leave) return false;
    
    // Get all admin emails
    $admin_emails = [];
    $admin_query = "SELECT email FROM admin WHERE status = 'active'";
    $result = $conn->query($admin_query);
    while ($row = $result->fetch_assoc()) {
        $admin_emails[] = $row['email'];
    }
    
    if (empty($admin_emails)) return false;
    
    $subject = "New Leave Application - " . $leave['full_name'];
    
    $start_date = isset($leave['start_date']) && $leave['start_date'] ? date('F d, Y', strtotime($leave['start_date'])) : 'N/A';
    $end_date = isset($leave['end_date']) && $leave['end_date'] ? date('F d, Y', strtotime($leave['end_date'])) : 'N/A';
    $message = '
        <h2>New Leave Application Received</h2>
        <p>A new leave application has been submitted and requires your approval.</p>
        <div class="info-box">
            <p><strong>Employee:</strong> ' . htmlspecialchars($leave['full_name']) . '</p>
            <p><strong>Leave Type:</strong> ' . htmlspecialchars($leave['leave_name']) . '</p>
            <p><strong>Start Date:</strong> ' . $start_date . '</p>
            <p><strong>End Date:</strong> ' . $end_date . '</p>
            <p><strong>Days Requested:</strong> ' . $leave['days_requested'] . ' day(s)</p>
            <p><strong>Reason:</strong> ' . nl2br(htmlspecialchars($leave['reason'])) . '</p>
        </div>
        <p>Please log in to the system to review and approve/reject this application.</p>
        <a href="http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/admin/manage_leaves.php" class="button">Review Application</a>';
    
    foreach ($admin_emails as $email) {
        sendEmail($email, $subject, $message);
    }
    
    return true;
}

/**
 * Send leave status notification to employee
 */
function emailLeaveStatusToEmployee($conn, $leave_id) {
    // Get leave details
    $query = "
        SELECT la.*, e.full_name, e.email as emp_email, lt.leave_name,
               a.full_name as admin_name
        FROM leave_application la
        JOIN employee e ON la.employee_id = e.employee_id
        JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
        LEFT JOIN admin a ON la.approved_by = a.admin_id
        WHERE la.leave_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$leave || !$leave['emp_email']) return false;
    
    $status = $leave['status'];
    $status_color = $status === 'Approved' ? '#27ae60' : '#e74c3c';
    
    $subject = "Leave Application " . $status . " - " . $leave['leave_name'];
    
    $message = '
        <h2 style="color: ' . $status_color . ';">Leave Application ' . $status . '</h2>
        <p>Your leave application has been <strong>' . strtolower($status) . '</strong>.</p>
        
        <div class="info-box">
            <p><strong>Leave Type:</strong> ' . htmlspecialchars($leave['leave_name']) . '</p>
            <p><strong>Start Date:</strong> ' . date('F d, Y', strtotime($leave['start_date'])) . '</p>
            <p><strong>End Date:</strong> ' . date('F d, Y', strtotime($leave['end_date'])) . '</p>
            <p><strong>Days:</strong> ' . $leave['days_requested'] . ' day(s)</p>
            <p><strong>Status:</strong> <span style="color: ' . $status_color . ';">' . $status . '</span></p>
            ' . ($leave['admin_name'] ? '<p><strong>Processed By:</strong> ' . htmlspecialchars($leave['admin_name']) . '</p>' : '') . '
            ' . ($leave['remarks'] ? '<p><strong>Remarks:</strong> ' . nl2br(htmlspecialchars($leave['remarks'])) . '</p>' : '') . '
        </div>
        
        <a href="http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/employee/my_leaves.php" class="button">
            View My Leaves
        </a>
    ';
    
    return sendEmail($leave['emp_email'], $subject, $message);
}

/**
 * Send leave reminder email (upcoming leave)
 */
function emailLeaveReminder($conn, $leave_id) {
    $query = "
        SELECT la.*, e.full_name, e.email, lt.leave_name
        FROM leave_application la
        JOIN employee e ON la.employee_id = e.employee_id
        JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
        WHERE la.leave_id = ? AND la.status = 'Approved'
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$leave || !$leave['email']) return false;
    
    $subject = "Reminder: Upcoming Leave - " . $leave['leave_name'];
    
    $message = '
        <h2>Leave Reminder</h2>
        <p>This is a reminder that your approved leave is coming up soon.</p>
        
        <div class="info-box">
            <p><strong>Leave Type:</strong> ' . htmlspecialchars($leave['leave_name']) . '</p>
            <p><strong>Start Date:</strong> ' . date('F d, Y', strtotime($leave['start_date'])) . '</p>
            <p><strong>End Date:</strong> ' . date('F d, Y', strtotime($leave['end_date'])) . '</p>
            <p><strong>Duration:</strong> ' . $leave['days_requested'] . ' day(s)</p>
        </div>
        
        <p>Please make sure all necessary arrangements are in place before your leave starts.</p>
    ';
    
    return sendEmail($leave['email'], $subject, $message);
}
?>


