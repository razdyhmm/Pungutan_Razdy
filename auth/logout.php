<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    $email = $_SESSION['email'];

    logAudit($conn, $user_id, 'User Logout', $user_type, $user_id, null, ['email' => $email]);

    session_destroy();
}

header('Location: ' . BASE_URL . '/auth/login.php');
exit();
?>
