<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/notification_functions.php')) {
    require_once __DIR__ . '/notification_functions.php';
}

$current_page = basename($_SERVER['PHP_SELF']);

$is_admin = function_exists('isAdmin') ? isAdmin() : (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin');
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fa;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0 1.5rem;
            height: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            height: 100%;
            position: relative;
        }
        
        .navbar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            padding: 0 1.5rem;
            height: 100%;
            display: flex;
            align-items: center;
            background: rgba(0,0,0,0.1);
            letter-spacing: 1px;
            position: absolute;
            left: 0;
        }
        
        .navbar-menu {
            display: flex;
            align-items: center;
            height: 100%;
            margin: 0 auto;
            gap: 1.5rem;
            padding: 0 200px;
        }
        
        .navbar-menu a {
            color: white;
            text-decoration: none;
            padding: 0 0.6rem;
            height: 100%;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            position: relative;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .navbar-menu a:hover,
        .navbar-menu a.active {
            background: rgba(255,255,255,0.15);
        }
        
        .navbar-menu a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: white;
        }
        
        .user-info {
            color: white;
            display: flex;
            align-items: center;
            height: 100%;
            font-size: 0.9rem;
            background: rgba(0,0,0,0.1);
            position: absolute;
            right: 0;
        }

        .user-info span {
            padding: 0 0.85rem;
            display: flex;
            align-items: center;
            height: 100%;
        }

        .user-info a {
            height: 100%;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            color: white;
            text-decoration: none;
            background: rgba(0,0,0,0.15);
            transition: background 0.3s;
            font-weight: 500;
        }

        .user-info a:hover {
            background: rgba(0,0,0,0.25);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .flash-message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .flash-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .flash-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .flash-message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .flash-message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .notif-link {
            color: white;
            text-decoration: none;
            margin-right: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            position: relative;
            padding: 0 8px;
        }

        .notif-icon {
            font-size: 16px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 18px;
            height: 18px;
            overflow: visible;
        }

        .notif-badge {
            position: absolute;
            top: -6px;
            right: -4px;
            transform: translateX(40%);
            background: #e74c3c;
            color: #fff;
            width: 15px;
            height: 15px;
            padding: 0;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: 700;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
            white-space: nowrap;
        }

        /* ensure the notification link itself doesn't get a full background and keeps small badge */
        .user-info .notif-link {
            background: transparent !important;
            padding: 0 6px !important;
            height: auto !important;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'employee_dashboard.php'; ?>" class="navbar-brand">
                <?php echo SITE_SHORT_NAME; ?>
            </a>
            
            <?php if (isLoggedIn()): ?>
            <div class="navbar-menu">
                <?php if ($is_admin): ?>
                    <a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                        Dashboard
                    </a>
                    <a href="admin_manage_leave.php" class="<?php echo $current_page == 'admin_manage_leave.php' ? 'active' : ''; ?>">
                        Leaves
                    </a>
                    <a href="admin_manage_employees.php" class="<?php echo $current_page == 'admin_manage_employees.php' ? 'active' : ''; ?>">
                        Employees
                    </a>
                    <a href="admin_credit_management.php" class="<?php echo $current_page == 'admin_credit_management.php' ? 'active' : ''; ?>">
                        Credit
                    </a>
                    <a href="admin_send_notification.php" class="<?php echo $current_page == 'admin_send_notification.php' ? 'active' : ''; ?>">
                        Send Notification
                    </a>
                    <a href="admin_manage_holidays.php" class="<?php echo $current_page == 'admin_manage_holidays.php' ? 'active' : ''; ?>">
                        Holidays
                    </a>
                    <a href="admin_leave_reports.php" class="<?php echo $current_page == 'admin_leave_reports.php' ? 'active' : ''; ?>">
                        Reports
                    </a>
                <?php else: ?>
                    <a href="employee_dashboard.php" class="<?php echo $current_page == 'employee_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
                    <a href="apply_leave.php" class="<?php echo $current_page == 'apply_leave.php' ? 'active' : ''; ?>">Apply Leave</a>
                    <a href="my_leaves.php" class="<?php echo $current_page == 'my_leaves.php' ? 'active' : ''; ?>">My Leaves</a>
                    <a href="leave_balance.php" class="<?php echo $current_page == 'leave_balance.php' ? 'active' : ''; ?>">Leave Balance</a>
                <?php endif; ?>
                
                <div class="user-info">
                        <?php if (isLoggedIn()):
                            $u_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'employee';
                            $u_id = $_SESSION['user_id'];
                            $unread = getUnreadNotificationsCount($conn, $u_type, $u_id);
                            $notif_link = $u_type === 'admin' ? 'admin_notifications.php' : 'employee_notifications.php';
                        ?>
                            <a href="<?php echo $notif_link; ?>" class="notif-link">
                                <span class="notif-icon">🔔
                                    <?php if($unread>0) echo '<span class="notif-badge">'. $unread .'</span>'; ?>
                                </span>
                            </a>
                        <span>👤 <?php echo escape($user_name); ?></span>
                        <a href="<?php echo BASE_URL; ?>/auth/logout.php">Logout</a>
                        <?php endif; ?>
                    </div>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="container">
        <?php
        $flash = getFlashMessage();
        if ($flash):
        ?>
        <div class="flash-message <?php echo $flash['type']; ?>">
            <?php echo escape($flash['message']); ?>
        </div>
        <?php endif; ?>



