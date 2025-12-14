<?php
/**
 * notification_functions.php
 * Single canonical implementation of notification helper functions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// database connection lives in config directory
require_once __DIR__ . '/../config/database.php';

/**
 * Create a notification
 */
function createNotification($conn, $user_type, $user_id, $title, $message, $type = 'info', $related_type = null, $related_id = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, title, message, type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("sissssi", $user_type, $user_id, $title, $message, $type, $related_type, $related_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($conn, $user_type, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("si", $user_type, $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return isset($res['count']) ? (int)$res['count'] : 0;
}

/**
 * Get notifications for user
 */
function getUserNotifications($conn, $user_type, $user_id, $limit = 10, $unread_only = false) {
    $where = "WHERE user_type = ? AND user_id = ?";
    if ($unread_only) {
        $where .= " AND is_read = 0";
    }
    $query = "SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) return [];
    $stmt->bind_param("sii", $user_type, $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($conn, $notification_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $notification_id);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($conn, $user_type, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("si", $user_type, $user_id);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Delete old notifications (older than 30 days)
 */
function deleteOldNotifications($conn) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if (!$stmt) return false;
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * Notify admins about new leave application
 */
function notifyAdminsNewLeave($conn, $leave_id, $employee_name, $leave_type) {
    $res = $conn->query("SELECT admin_id FROM admin WHERE status = 'active'");
    if (!$res) return false;
    $title = 'New Leave Application';
    $message = "$employee_name has submitted a new $leave_type application.";
    while ($r = $res->fetch_assoc()) {
        createNotification($conn, 'admin', $r['admin_id'], $title, $message, 'info', 'leave_application', $leave_id);
    }
    return true;
}

/**
 * Notify employee about leave status change
 */
function notifyEmployeeLeaveStatus($conn, $employee_id, $leave_id, $status, $leave_type) {
    $title = 'Leave Application ' . ucfirst($status);
    $message = "Your $leave_type application has been $status.";
    $type = strtolower($status) === 'approved' ? 'success' : 'danger';
    return createNotification($conn, 'employee', $employee_id, $title, $message, $type, 'leave_application', $leave_id);
}

/**
 * Notify employee about low leave balance
 */
function notifyLowLeaveBalance($conn, $employee_id, $leave_type, $remaining_days) {
    $title = 'Low Leave Balance Warning';
    $message = "Your $leave_type balance is low. You have only $remaining_days day(s) remaining.";
    return createNotification($conn, 'employee', $employee_id, $title, $message, 'warning', 'leave_balance', null);
}

?>
