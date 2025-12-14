<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$PRINT_CSS = <<<'CSS'
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #222; background: #fff; padding: 20px; }
.printable { max-width: 800px; margin: 0 auto; }
h1 { font-size: 20px; margin-bottom: 8px; }
h3 { margin-top: 20px; }
@media print { .print-actions { display: none; } a { color: #000; text-decoration: underline; } body { margin: 0; } }
CSS;

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

       $allowed = ['leave', 'employee', 'report', 'balance', 'history'];
if (!in_array($type, $allowed)) {
    http_response_code(400);
    echo 'Invalid printable type';
    exit;
}

switch ($type) {
    case 'leave':
        $stmt = $conn->prepare(
            "SELECT la.*, lt.leave_name, lt.description, la.employee_id, e.full_name as approver_name
             FROM leave_application la
             JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
            LEFT JOIN admin e ON la.approved_by = e.admin_id
             WHERE la.leave_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            setFlashMessage('error', 'Printable not found.');
            header('Location: ' . BASE_URL . '/employee/my_leaves.php');
            exit();
        }

        $leave = $res->fetch_assoc();
        $stmt->close();

        if (!isAdmin() && isset($_SESSION['employee_id']) && $leave['employee_id'] != $_SESSION['employee_id']) {
            setFlashMessage('error', 'Not authorized to view this printable.');
            header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
            exit();
        }
        
        if (function_exists('logAudit')) {
            logAudit($conn, $_SESSION['user_id'], 'Printed Leave', 'leave_application', $id);
        }

    include __DIR__ . '/print_leave.php';
        break;

    case 'report':
        if (!isAdmin()) {
            setFlashMessage('error', 'Not authorized to generate reports.');
            header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
            exit();
        }

        $report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';
        $employee_filter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        $department_filter = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-01-01');
        $date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

        $query = "
            SELECT e.employee_id, e.full_name, d.department_name,
                   SUM(CASE WHEN TRIM(LOWER(la.status)) = 'pending' THEN 1 ELSE 0 END) as pending_count,
                   SUM(CASE WHEN TRIM(LOWER(la.status)) = 'approved' THEN 1 ELSE 0 END) as approved_count,
                   SUM(CASE WHEN TRIM(LOWER(la.status)) = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                   COALESCE(SUM(CASE WHEN TRIM(LOWER(la.status)) = 'approved' THEN la.days_requested ELSE 0 END),0) as total_days_taken
            FROM employee e
            LEFT JOIN department d ON e.department_id = d.department_id
            LEFT JOIN leave_application la ON e.employee_id = la.employee_id
                AND (
                    (la.applied_date BETWEEN ? AND ?) 
                    OR (la.date_from <= ? AND la.date_to >= ?)
                )
        ";

        $conditions = ["e.status = 'active'"];
        if ($employee_filter > 0) {
            $conditions[] = "e.employee_id = $employee_filter";
        }
        if ($department_filter > 0) {
            $conditions[] = "e.department_id = $department_filter";
        }

        $query .= " WHERE " . implode(" AND ", $conditions);
        $query .= " GROUP BY e.employee_id ORDER BY e.full_name";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $date_from, $date_to, $date_to, $date_from);
        $stmt->execute();
        $report_data = $stmt->get_result();
        $stmt->close();

    include __DIR__ . '/print_report.php';
        break;

    case 'balance':
        $employee_id_param = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        if (isAdmin() && $employee_id_param > 0) {
            $employee_id = $employee_id_param;
        } else {
            $employee_id = isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : 0;
        }

        if (!$employee_id) {
            setFlashMessage('error', 'Employee not specified.');
            header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
            exit();
        }

        $stmt = $conn->prepare("SELECT total_credits, used_credits, remaining_credits, last_updated FROM employee_credit WHERE employee_id = ?");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $credit = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $col_check = $conn->query("SHOW COLUMNS FROM leave_credit_log LIKE 'recorded_by'");
        if ($col_check && $col_check->num_rows > 0) {
            $history_query = "
                SELECT lcl.credit_change, lcl.reason, lcl.date_recorded,
                       lt.leave_name, a.full_name as recorded_by_name
                FROM leave_credit_log lcl
                JOIN leave_type lt ON lcl.leave_type_id = lt.leave_type_id
                LEFT JOIN admin a ON lcl.recorded_by = a.admin_id
                WHERE lcl.employee_id = ?
                ORDER BY lcl.date_recorded DESC
            ";
        } else {
            $history_query = "
                SELECT lcl.credit_change, lcl.reason, lcl.date_recorded,
                       lt.leave_name, '' as recorded_by_name
                FROM leave_credit_log lcl
                JOIN leave_type lt ON lcl.leave_type_id = lt.leave_type_id
                WHERE lcl.employee_id = ?
                ORDER BY lcl.date_recorded DESC
            ";
        }

        $stmt = $conn->prepare($history_query);
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $credit_history = $stmt->get_result();
        $stmt->close();

        include __DIR__ . '/print_balance.php';
        break;

    case 'history':
        $employee_id_param = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
        if (isAdmin() && $employee_id_param > 0) {
            $employee_id = $employee_id_param;
        } else {
            $employee_id = isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : 0;
        }

        if (!$employee_id) {
            setFlashMessage('error', 'Employee not specified.');
            header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
            exit();
        }

        $leave_query = "
            SELECT la.*, lt.leave_name, a.full_name as approver_name
            FROM leave_application la
            JOIN leave_type lt ON la.leave_type_id = lt.leave_type_id
            LEFT JOIN admin a ON la.approved_by = a.admin_id
            WHERE la.employee_id = ?
            ORDER BY la.applied_date DESC
        ";

        $stmt = $conn->prepare($leave_query);
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $leave_history = $stmt->get_result();
        $stmt->close();

        include __DIR__ . '/print_history.php';
        break;


    default:
        http_response_code(400);
        echo 'Unsupported printable';
        break;
}

?>
