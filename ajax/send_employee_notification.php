<?php
require_once __DIR__ . '/../includes/email_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $from_name = 'Admin Notification';

    $result = false;
    $error = '';
    try {
        $result = sendEmail($to, $subject, $message, $from_name);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if ($result) {
        echo '<div style="color:green;">Notification sent successfully to ' . htmlspecialchars($to) . '.</div>';
    } else {
        echo '<div style="color:red;">Failed to send notification.';
        if (!empty($error)) {
            echo '<br>Error: ' . htmlspecialchars($error);
        } else {
            echo '<br>Check your configuration and error logs.';
        }
        if (function_exists('error_get_last')) {
            $last_error = error_get_last();
            if ($last_error) {
                echo '<br>Last PHP error: ' . htmlspecialchars($last_error['message']);
            }
        }
        echo '</div>';
    }
}
?>
