<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
require_once __DIR__ . '/../includes/notification_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adjust_credit'])) {
        $employee_id = (int)$_POST['employee_id'];
        $credit_change = (float)$_POST['credit_change'];
        $reason = sanitize($_POST['reason']);
        $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0);

        if (empty($reason)) {
            setFlashMessage('error', 'Reason is required for credit adjustment.');
            header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
            exit();
        }
        $check_stmt = $conn->prepare("SELECT total_credits FROM employee_credit WHERE employee_id = ?");
            if ($check_stmt) {
            $check_stmt->bind_param("i", $employee_id);
            $check_stmt->execute();
            $res = $check_stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt = $conn->prepare("UPDATE employee_credit SET total_credits = total_credits + ?, remaining_credits = remaining_credits + ?, last_updated = NOW() WHERE employee_id = ?");
                if ($stmt) {
                    $stmt->bind_param("ddi", $credit_change, $credit_change, $employee_id);
                    if (!$stmt->execute()) {
                        setFlashMessage('error', 'Failed to adjust leave credit: ' . $conn->error);
                        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
                        exit();
                    }
                    $stmt->close();
                        // create in-system notification for this employee
                        if (function_exists('createNotification')) {
                            createNotification($conn, 'employee', $employee_id, 'Leave Credit Adjusted', "Your leave credits have been adjusted by {$credit_change} (Reason: " . htmlspecialchars($reason) . ").", 'info', 'leave_balance', null);
                        }
                        // Send plain email to employee
                        require_once __DIR__ . '/../includes/email_functions.php';
                        $emp_email_stmt = $conn->prepare("SELECT email, full_name FROM employee WHERE employee_id = ?");
                        $emp_email_stmt->bind_param('i', $employee_id);
                        $emp_email_stmt->execute();
                        $emp_row = $emp_email_stmt->get_result()->fetch_assoc();
                        $emp_email_stmt->close();
                        if ($emp_row && !empty($emp_row['email'])) {
                            $emp_subject = "Leave Credit Adjusted";
                            $emp_message_html = '<h2>Leave Credit Adjusted</h2>';
                            $emp_message_html .= '<p>Dear ' . htmlspecialchars($emp_row['full_name']) . ',</p>';
                            $emp_message_html .= '<div class="info-box">';
                            $emp_message_html .= '<p><strong>Credit Change:</strong> ' . $credit_change . '</p>';
                            $emp_message_html .= '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>';
                            $emp_message_html .= '</div>';
                            $emp_message_html .= '<p>Your leave credits have been adjusted.</p>';
                            $emp_message_html .= '<p>Thank you.</p>';
                            sendEmail($emp_row['email'], $emp_subject, $emp_message_html);
                        }
                }
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO employee_credit (employee_id, total_credits, used_credits, remaining_credits, last_updated) VALUES (?, ?, 0, ?, NOW())");
                if ($insert_stmt) {
                    $initial_total = $credit_change;
                    $initial_remaining = $credit_change;
                    $insert_stmt->bind_param("idd", $employee_id, $initial_total, $initial_remaining);
                    if (!$insert_stmt->execute()) {
                        setFlashMessage('error', 'Failed to create employee credit row: ' . $conn->error);
                        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
                        exit();
                    }
                    $insert_stmt->close();
                        if (function_exists('createNotification')) {
                            createNotification($conn, 'employee', $employee_id, 'Leave Credit Added', "Your leave credits have been set to {$initial_total} (Reason: " . htmlspecialchars($reason) . ").", 'success', 'leave_balance', null);
                        }
                        // Send plain email to employee
                        require_once __DIR__ . '/../includes/email_functions.php';
                        $emp_email_stmt = $conn->prepare("SELECT email, full_name FROM employee WHERE employee_id = ?");
                        $emp_email_stmt->bind_param('i', $employee_id);
                        $emp_email_stmt->execute();
                        $emp_row = $emp_email_stmt->get_result()->fetch_assoc();
                        $emp_email_stmt->close();
                        if ($emp_row && !empty($emp_row['email'])) {
                            $emp_subject = "Leave Credit Added";
                            $emp_message_html = '<h2>Leave Credit Added</h2>';
                            $emp_message_html .= '<p>Dear ' . htmlspecialchars($emp_row['full_name']) . ',</p>';
                            $emp_message_html .= '<div class="info-box">';
                            $emp_message_html .= '<p><strong>Credits Set To:</strong> ' . $initial_total . '</p>';
                            $emp_message_html .= '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>';
                            $emp_message_html .= '</div>';
                            $emp_message_html .= '<p>Your leave credits have been set.</p>';
                            $emp_message_html .= '<p>Thank you.</p>';
                            sendEmail($emp_row['email'], $emp_subject, $emp_message_html);
                        }
                }
            }
            $check_stmt->close();

            $log_stmt = $conn->prepare("INSERT INTO leave_credit_log (employee_id, leave_type_id, credit_change, reason, recorded_by_admin) VALUES (?, 1000, ?, ?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("idsi", $employee_id, $credit_change, $reason, $admin_id);
                $log_stmt->execute();
                $log_stmt->close();
            }

            logAudit($conn, $admin_id, 'Adjusted Leave Credit', 'employee_credit', $employee_id, null, [
                'employee_id' => $employee_id,
                'credit_change' => $credit_change,
                'reason' => $reason
            ]);

            setFlashMessage('success', 'Leave credit adjusted successfully!');
        } else {
            setFlashMessage('error', 'Failed to verify existing employee credit: ' . $conn->error);
        }
        
        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
        exit();
    }

    if (isset($_POST['add_credit'])) {
        $employee_id = (int)$_POST['employee_id'];
        $credit_change = (float)$_POST['credit_change'];
        $reason = sanitize($_POST['reason']);
        $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0);

        if (empty($reason)) {
            setFlashMessage('error', 'Reason is required for adding credit.');
            header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
            exit();
        }

        $check_stmt = $conn->prepare("SELECT total_credits FROM employee_credit WHERE employee_id = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("i", $employee_id);
            $check_stmt->execute();
            $res = $check_stmt->get_result();
            if ($res && $res->fetch_assoc()) {
                $stmt = $conn->prepare("UPDATE employee_credit SET total_credits = total_credits + ?, remaining_credits = remaining_credits + ?, last_updated = NOW() WHERE employee_id = ?");
                if ($stmt) {
                    $stmt->bind_param("ddi", $credit_change, $credit_change, $employee_id);
                    if (!$stmt->execute()) {
                        setFlashMessage('error', 'Failed to adjust leave credit: ' . $conn->error);
                        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
                        exit();
                    }
                        $stmt->close();
                        // notify employee about added credit
                        if (function_exists('createNotification')) {
                            createNotification($conn, 'employee', $employee_id, 'Leave Credit Added', "Your leave credits have been increased by {$credit_change} (Reason: " . htmlspecialchars($reason) . ").", 'success', 'leave_balance', null);
                        }
                        // Send designed email to employee
                        require_once __DIR__ . '/../includes/email_functions.php';
                        $emp_email_stmt = $conn->prepare("SELECT email, full_name FROM employee WHERE employee_id = ?");
                        $emp_email_stmt->bind_param('i', $employee_id);
                        $emp_email_stmt->execute();
                        $emp_row = $emp_email_stmt->get_result()->fetch_assoc();
                        $emp_email_stmt->close();
                        if ($emp_row && !empty($emp_row['email'])) {
                            $is_increase = $credit_change > 0;
                            $emp_subject = $is_increase ? "Leave Credit Added" : "Leave Credit Deducted";
                            $emp_message_html = $is_increase ? '<h2>Leave Credit Added</h2>' : '<h2>Leave Credit Deducted</h2>';
                            $emp_message_html .= '<p>Dear ' . htmlspecialchars($emp_row['full_name']) . ',</p>';
                            $emp_message_html .= '<div class="info-box">';
                            $emp_message_html .= '<p><strong>Credit Change:</strong> ' . $credit_change . '</p>';
                            $emp_message_html .= '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>';
                            $emp_message_html .= '</div>';
                            if ($is_increase) {
                                $emp_message_html .= '<p>Your leave credits have been increased.</p>';
                            } else {
                                $emp_message_html .= '<p>Your leave credits have been deducted.</p>';
                            }
                            $emp_message_html .= '<p>Thank you.</p>';
                            sendEmail($emp_row['email'], $emp_subject, $emp_message_html);
                        }
                }
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO employee_credit (employee_id, total_credits, used_credits, remaining_credits, last_updated) VALUES (?, ?, 0, ?, NOW())");
                if ($insert_stmt) {
                    $initial_total = $credit_change;
                    $initial_remaining = $credit_change;
                    $insert_stmt->bind_param("idd", $employee_id, $initial_total, $initial_remaining);
                    if (!$insert_stmt->execute()) {
                        setFlashMessage('error', 'Failed to create employee credit row: ' . $conn->error);
                        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
                        exit();
                    }
                    $insert_stmt->close();
                        // notify employee about new credit row / added credits
                        if (function_exists('createNotification')) {
                            createNotification($conn, 'employee', $employee_id, 'Leave Credit Added', "Your leave credits have been set to {$initial_total} (Reason: " . htmlspecialchars($reason) . ").", 'success', 'leave_balance', null);
                        }
                    }
            }
            $check_stmt->close();

            $log_stmt = $conn->prepare("INSERT INTO leave_credit_log (employee_id, leave_type_id, credit_change, reason, recorded_by_admin) VALUES (?, 1000, ?, ?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("idsi", $employee_id, $credit_change, $reason, $admin_id);
                $log_stmt->execute();
                $log_stmt->close();
            }

            logAudit($conn, $admin_id, 'Added Leave Credit', 'employee_credit', $employee_id, null, [
                'employee_id' => $employee_id,
                'credit_change' => $credit_change,
                'reason' => $reason
            ]);
            setFlashMessage('success', 'Credit added successfully.');
        }
        
        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
        exit();
    }

    if (isset($_POST['run_accrual'])) {
        $amount = isset($_POST['accrual_amount']) ? (float)$_POST['accrual_amount'] : 1.25;
        $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0);

        // optional: support running accrual for a specific employee if `employee_id` provided in POST
        $specific_employee = isset($_POST['employee_id']) && (int)$_POST['employee_id'] > 0 ? (int)$_POST['employee_id'] : 0;

        // ensure notification/email helpers are available for per-employee notifications
        require_once __DIR__ . '/../includes/notification_functions.php';
        require_once __DIR__ . '/../includes/email_functions.php';

        $emp_query = "SELECT employee_id FROM employee WHERE " . 
            (isset($employee_active_col) && $employee_active_col === 'is_active' ? "is_active = 1" : 
            (isset($employee_active_col) && $employee_active_col === 'status' ? "status = 'active'" : "1=1"));
            
        $emp_res = $conn->query($emp_query);
        $success = 0; $failed = 0;

        if ($emp_res) {
            while ($e = $emp_res->fetch_assoc()) {
                $eid = (int)$e['employee_id'];
                $check_stmt = $conn->prepare("SELECT total_credits FROM employee_credit WHERE employee_id = ?");
                if ($check_stmt) {
                    $check_stmt->bind_param("i", $eid);
                    $check_stmt->execute();
                    $res = $check_stmt->get_result();
                    if ($res && $res->fetch_assoc()) {
                        $stmt = $conn->prepare("UPDATE employee_credit SET total_credits = total_credits + ?, remaining_credits = remaining_credits + ?, last_updated = NOW() WHERE employee_id = ?");
                        if ($stmt) {
                                $stmt->bind_param("ddi", $amount, $amount, $eid);
                                if ($stmt->execute()) {
                                    $success++;
                                    // create in-system notification for this employee
                                    createNotification($conn, 'employee', $eid, 'Monthly Accrual', "Your leave credits have been increased by {$amount}.", 'success', 'leave_balance', null);
                                    // Send plain email to employee
                                    require_once __DIR__ . '/../includes/email_functions.php';
                                    $emp_email_stmt = $conn->prepare("SELECT email, full_name FROM employee WHERE employee_id = ?");
                                    $emp_email_stmt->bind_param('i', $eid);
                                    $emp_email_stmt->execute();
                                    $emp_row = $emp_email_stmt->get_result()->fetch_assoc();
                                    $emp_email_stmt->close();
                                    if ($emp_row && !empty($emp_row['email'])) {
                                        $emp_subject = "Monthly Leave Credit Accrual";
                                        $emp_message_html = '<h2>Monthly Leave Credit Accrual</h2>';
                                        $emp_message_html .= '<p>Dear ' . htmlspecialchars($emp_row['full_name']) . ',</p>';
                                        $emp_message_html .= '<div class="info-box">';
                                        $emp_message_html .= '<p><strong>Credit Increase:</strong> ' . $amount . '</p>';
                                        $emp_message_html .= '</div>';
                                        $emp_message_html .= '<p>Your leave credits have been increased as part of the monthly accrual.</p>';
                                        $emp_message_html .= '<p>Thank you.</p>';
                                        sendEmail($emp_row['email'], $emp_subject, $emp_message_html);
                                    }
                                } else $failed++;
                                $stmt->close();
                            } else { $failed++; }
                    } else {
                        $insert_stmt = $conn->prepare("INSERT INTO employee_credit (employee_id, total_credits, used_credits, remaining_credits, last_updated) VALUES (?, ?, 0, ?, NOW())");
                        if ($insert_stmt) {
                            $initial_total = $amount;
                            $initial_remaining = $amount;
                            $insert_stmt->bind_param("idd", $eid, $initial_total, $initial_remaining);
                            if ($insert_stmt->execute()) {
                                $success++;
                                createNotification($conn, 'employee', $eid, 'Monthly Accrual', "Your leave credits have been set to {$initial_total}.", 'success', 'leave_balance', null);
                                // Send plain email to employee
                                require_once __DIR__ . '/../includes/email_functions.php';
                                $emp_email_stmt = $conn->prepare("SELECT email, full_name FROM employee WHERE employee_id = ?");
                                $emp_email_stmt->bind_param('i', $eid);
                                $emp_email_stmt->execute();
                                $emp_row = $emp_email_stmt->get_result()->fetch_assoc();
                                $emp_email_stmt->close();
                                if ($emp_row && !empty($emp_row['email'])) {
                                    $emp_subject = "Monthly Leave Credit Accrual";
                                    $emp_message_html = '<h2>Monthly Leave Credit Accrual</h2>';
                                    $emp_message_html .= '<p>Dear ' . htmlspecialchars($emp_row['full_name']) . ',</p>';
                                    $emp_message_html .= '<div class="info-box">';
                                    $emp_message_html .= '<p><strong>Credits Set To:</strong> ' . $initial_total . '</p>';
                                    $emp_message_html .= '</div>';
                                    $emp_message_html .= '<p>Your leave credits have been set as part of the monthly accrual.</p>';
                                    $emp_message_html .= '<p>Thank you.</p>';
                                    sendEmail($emp_row['email'], $emp_subject, $emp_message_html);
                                }
                            } else $failed++;
                            $insert_stmt->close();
                        } else { $failed++; }
                    }
                    $check_stmt->close();

                    $log_stmt = $conn->prepare("INSERT INTO leave_credit_log (employee_id, leave_type_id, credit_change, reason, recorded_by_admin) VALUES (?, 1000, ?, ?, ?)");
                    if ($log_stmt) {
                        $reason = 'Monthly accrual';
                        $log_stmt->bind_param("idsi", $eid, $amount, $reason, $admin_id);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }

                    logAudit($conn, $admin_id, 'Monthly Accrual', 'employee_credit', $eid, null, [
                        'employee_id' => $eid,
                        'credit_change' => $amount
                    ]);
                } else { $failed++; }
            }
        } else {
            setFlashMessage('error', 'Failed to fetch employees for accrual: ' . $conn->error);
            header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
            exit();
        }

        setFlashMessage('success', "Monthly accrual completed. Success: $success, Failed: $failed");
        header('Location: ' . BASE_URL . '/admin/admin_credit_management.php');
        exit();
    }
}

include __DIR__ . '/../includes/header.php';

$employee_active_col = null;
$col_check = $conn->query("SHOW COLUMNS FROM employee LIKE 'is_active'");
if ($col_check && $col_check->num_rows > 0) {
    $employee_active_col = 'is_active';
} else {
    $col_check2 = $conn->query("SHOW COLUMNS FROM employee LIKE 'status'");
    if ($col_check2 && $col_check2->num_rows > 0) {
        $employee_active_col = 'status';
    }
}

$employee_filter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$department_filter = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

$emp_where = '';
if ($employee_active_col === 'is_active') {
    $emp_where = "WHERE is_active = 1";
} elseif ($employee_active_col === 'status') {
    $emp_where = "WHERE status = 'active'";
} else {
    $emp_where = "";
}
$employees = $conn->query("SELECT employee_id, full_name FROM employee $emp_where ORDER BY full_name");

$departments = $conn->query("SELECT department_id, department_name FROM department ORDER BY department_name");

$emp_active_clause = '';
if ($employee_active_col === 'is_active') {
    $emp_active_clause = "WHERE e.is_active = 1";
} elseif ($employee_active_col === 'status') {
    $emp_active_clause = "WHERE e.status = 'active'";
} else {
    $emp_active_clause = "WHERE 1";
}

$balance_query = "
    SELECT e.employee_id, e.full_name, e.email, d.department_name,
           ec.total_credits, ec.used_credits, ec.remaining_credits, ec.last_updated
    FROM employee e
    JOIN department d ON e.department_id = d.department_id
    LEFT JOIN employee_credit ec ON e.employee_id = ec.employee_id
    " . $emp_active_clause;

if ($employee_filter > 0) {
    $balance_query .= " AND e.employee_id = $employee_filter";
}
if ($department_filter > 0) {
    $balance_query .= " AND e.department_id = $department_filter";
}

$balance_query .= " ORDER BY e.full_name";
$balances = $conn->query($balance_query);
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .filters {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        margin-bottom: 5px;
        color: #333;
        font-weight: 600;
        font-size: 14px;
    }
    
    .filter-group select {
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
        margin-top: 20px;
    }
    
    .balances-container {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    
    .text-right {
        text-align: right;
    }
    
    .btn-adjust {
        padding: 6px 15px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 13px;
        cursor: pointer;
        font-weight: 600;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
    }
    
    .modal.active {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        max-width: 500px;
        width: 90%;
    }
    
    .modal-header {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }
    
    .modal-header h2 {
        color: #333;
        margin: 0;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 600;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-submit {
        flex: 1;
        padding: 12px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .btn-cancel {
        flex: 1;
        padding: 12px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .info-note {
        background: #e3f2fd;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-size: 13px;
        color: #1976d2;
    }
</style>

<div class="page-header">
    <h1>Credit Management</h1>
    <div style="display:flex;gap:10px;">
        <button class="btn-filter" type="button" onclick="showAddModal()">Add Credit</button>
        <button class="btn-filter" type="button" onclick="showAccrualModal()">Run Monthly Accrual</button>
    </div>
</div>

<div class="filters">
    <form method="GET" action="">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="employee_id">Employee</label>
                <select name="employee_id" id="employee_id">
                    <option value="0">All Employees</option>
                    <?php 
                    $employees->data_seek(0);
                    while ($emp = $employees->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $emp['employee_id']; ?>" <?php echo $employee_filter == $emp['employee_id'] ? 'selected' : ''; ?>>
                        <?php echo escape($emp['full_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="department_id">Department</label>
                <select name="department_id" id="department_id">
                    <option value="0">All Departments</option>
                    <?php 
                    $departments->data_seek(0);
                    while ($dept = $departments->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $dept['department_id']; ?>" <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                        <?php echo escape($dept['department_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-filter">Filter</button>
    </form>
</div>

<div class="balances-container">
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th class="text-right">Total</th>
                <th class="text-right">Used</th>
                <th class="text-right">Remaining</th>
                <th>Last Updated</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($balance = $balances->fetch_assoc()): ?>
            <tr>
                <td><?php echo escape($balance['full_name']); ?></td>
                <td><?php echo escape($balance['department_name']); ?></td>
                <td class="text-right"><?php echo $balance['total_credits']; ?></td>
                <td class="text-right"><?php echo $balance['used_credits']; ?></td>
                <td class="text-right"><strong><?php echo $balance['remaining_credits']; ?></strong></td>
                <td><?php echo formatDateTime($balance['last_updated']); ?></td>
                <td>
                    <button class="btn-adjust" onclick='showAdjustModal(<?php echo json_encode($balance); ?>)'>
                        Adjust
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="adjustModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Adjust Leave Credit</h2>
        </div>
        
        <div class="info-note">
            <strong>Note:</strong> Use positive numbers to add credits, negative numbers to deduct credits.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" id="adjust_employee_id" name="employee_id">
            
            <div class="form-group">
                <label>Employee</label>
                <input type="text" id="adjust_employee_name" disabled style="background: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label>Current Balance</label>
                <input type="text" id="adjust_current_balance" disabled style="background: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label for="credit_change">Credit Change *</label>
                <input type="number" id="credit_change" name="credit_change" step="0.5" required placeholder="e.g., 5 or -3">
            </div>
            
            <div class="form-group">
                <label for="reason">Reason *</label>
                <textarea id="reason" name="reason" required placeholder="Explain why you are adjusting this credit"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="adjust_credit" class="btn-submit">Adjust Credit</button>
                <button type="button" class="btn-cancel" onclick="closeAdjustModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAdjustModal(balance) {
    document.getElementById('adjust_employee_id').value = balance.employee_id;
    document.getElementById('adjust_employee_name').value = balance.full_name;
    document.getElementById('adjust_current_balance').value = (balance.remaining_credits !== null ? balance.remaining_credits : 0) + ' days remaining';
    document.getElementById('credit_change').value = '';
    document.getElementById('reason').value = '';
    document.getElementById('adjustModal').classList.add('active');
}

function closeAdjustModal() {
    document.getElementById('adjustModal').classList.remove('active');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>

<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2>Add Credit</h2></div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="add_employee_id">Employee</label>
                <select id="add_employee_id" name="employee_id" required>
                    <option value="">-- Select Employee --</option>
                    <?php $employees->data_seek(0); while ($emp = $employees->fetch_assoc()): ?>
                    <option value="<?php echo $emp['employee_id']; ?>"><?php echo escape($emp['full_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="add_credit_change">Credit Change *</label>
                <input type="number" id="add_credit_change" name="credit_change" step="0.25" required placeholder="e.g., 5 or -3">
            </div>
            <div class="form-group">
                <label for="add_reason">Reason *</label>
                <textarea id="add_reason" name="reason" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_credit" class="btn-submit">Add Credit</button>
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="accrualModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2>Run Monthly Accrual</h2></div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="accrual_amount">Amount per employee</label>
                <input type="number" id="accrual_amount" name="accrual_amount" step="0.01" value="1.25" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="run_accrual" class="btn-submit">Run Accrual</button>
                <button type="button" class="btn-cancel" onclick="closeAccrualModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddModal(){ document.getElementById('addModal').classList.add('active'); }
function closeAddModal(){ document.getElementById('addModal').classList.remove('active'); }
function showAccrualModal(){ document.getElementById('accrualModal').classList.add('active'); }
function closeAccrualModal(){ document.getElementById('accrualModal').classList.remove('active'); }

window.addEventListener('click', function(e){
    if (e.target.classList && e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
