<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/../includes/notification_functions.php';

$daysAhead = 2;

$query = "SELECT leave_id FROM leave_application WHERE status = 'approved' AND DATE(date_from) = DATE_ADD(CURDATE(), INTERVAL ? DAY)";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $daysAhead);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

while ($r = $res->fetch_assoc()) {
    $leave_id = (int)$r['leave_id'];
    emailLeaveReminder($conn, $leave_id);
    $q2 = $conn->prepare("SELECT employee_id, leave_type_id FROM leave_application WHERE leave_id = ?");
    $q2->bind_param('i', $leave_id);
    $q2->execute();
    $info = $q2->get_result()->fetch_assoc();
    $q2->close();
    if ($info) {
        $lt = $conn->prepare("SELECT leave_name FROM leave_type WHERE leave_type_id = ?");
        $lt->bind_param('i', $info['leave_type_id']);
        $lt->execute();
        $lname = $lt->get_result()->fetch_assoc();
        $lt->close();
        $leave_name = $lname ? $lname['leave_name'] : 'Leave';
        createNotification($conn, 'employee', $info['employee_id'], 'Leave Reminder', "Your $leave_name starts in $daysAhead day(s).", 'info', 'leave_application', $leave_id);
    }
}

deleteOldNotifications($conn);

echo "Reminders processed.\n";
