<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'employee' && !isset($_SESSION['employee_id'])) {
        $_SESSION['employee_id'] = $_SESSION['user_id'];
    }
    if ($_SESSION['user_type'] === 'admin' && !isset($_SESSION['admin_id'])) {
        $_SESSION['admin_id'] = $_SESSION['user_id'];
    }
}

function isLoggedIn() {
    return (isset($_SESSION['user_id']) && isset($_SESSION['user_type']));
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
        exit();
    }
}

function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('F d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('F d, Y g:i A', strtotime($datetime));
}

function calculateDays($start_date, $end_date, $exclude_weekends = false) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end = $end->modify('+1 day'); 
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $days = 0;
    foreach ($period as $date) {
        if ($exclude_weekends) {
            if ($date->format('w') != 0 && $date->format('w') != 6) {
                $days++;
            }
        } else {
            $days++;
        }
    }
    
    return $days;
}

function calculateEndDate($conn, $start_date, $days_requested) {
    $current_date = new DateTime($start_date);
    $days_counted = 0;

    $holidays_query = "SELECT holiday_date FROM holidays";
    $holidays_result = $conn->query($holidays_query);
    $holidays = [];
    while ($row = $holidays_result->fetch_assoc()) {
        $holidays[] = $row['holiday_date'];
    }

    while ($days_counted < $days_requested) {
        $current_date_str = $current_date->format('Y-m-d');
        $day_of_week = $current_date->format('N');

        if ($day_of_week != 6 && $day_of_week != 7 && !in_array($current_date_str, $holidays)) {
            $days_counted++;
            if ($days_counted < $days_requested) {
                $current_date->modify('+1 day');
            }
        } else {
            $current_date->modify('+1 day');
        }
    }

    return $current_date->format('Y-m-d');
}

function isWeekend($date) {
    $day_of_week = date('N', strtotime($date));
    return ($day_of_week == 6 || $day_of_week == 7);
}

function isHoliday($conn, $date) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM holidays WHERE holiday_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] > 0;
}

function logAudit($conn, $user_id, $action, $entity_type, $entity_id, $old_value = null, $new_value = null) {
    if (!$user_id) {
        return;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $old_json = $old_value ? json_encode($old_value) : null;
    $new_json = $new_value ? json_encode($new_value) : null;

    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
    if (!$user_type) {
        $type_check = $conn->prepare("SELECT 'admin' as type FROM admin WHERE admin_id = ?");
        $type_check->bind_param("i", $user_id);
        $type_check->execute();
        $result = $type_check->get_result();
        if ($result->num_rows > 0) {
            $user_type = 'admin';
        } else {
            $user_type = 'employee';
        }
        $type_check->close();
    }

    $entity_id = $entity_id ?? '';
    
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, user_type, action, entity_type, entity_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $user_id, $user_type, $action, $entity_type, $entity_id, $old_json, $new_json, $ip_address);
    $stmt->execute();
    $stmt->close();
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'];
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

function getStatusBadge($status) {
    switch ($status) {
        case STATUS_PENDING:
            return 'warning';
        case STATUS_APPROVED:
            return 'success';
        case STATUS_REJECTED:
            return 'danger';
        default:
            return 'secondary';
    }
}

function handleFileUpload($file, $allowed_extensions = ALLOWED_EXTENSIONS, $max_size = MAX_FILE_SIZE) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload failed with error code: ' . $file['error']];
    }
    
    if ($file['size'] > $max_size) {
        return ['error' => 'File size exceeds maximum allowed size of ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions)];
    }
    
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $upload_path = UPLOAD_DIR . $new_filename;
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $new_filename];
    }
    
    return ['error' => 'Failed to move uploaded file'];
}

function getEmployeeName($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT full_name FROM employee WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['full_name'] : 'Unknown';
}
?>