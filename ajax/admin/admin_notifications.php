<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_functions.php';

requireLogin();

$user_type = isAdmin() ? 'admin' : 'employee';
$user_id = isAdmin() ? $_SESSION['user_id'] : $_SESSION['employee_id'];

// Handle mark/read actions BEFORE any output (prevents headers already sent)
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    markNotificationAsRead($conn, $notification_id);
    header('Location: ' . BASE_URL . '/admin/admin_notifications.php');
    exit();
}

if (isset($_GET['mark_all_read'])) {
    markAllNotificationsAsRead($conn, $user_type, $user_id);
    header('Location: ' . BASE_URL . '/admin/admin_notifications.php');
    exit();
}

include __DIR__ . '/../includes/header.php';

// Get notifications
$notifications = getUserNotifications($conn, $user_type, $user_id, 50);
$unread_count = getUnreadNotificationsCount($conn, $user_type, $user_id);
?>

<style>
    .notifications-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 900px;
        margin: 0 auto;
    }
    
    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #667eea;
    }
    
    .notifications-header h1 {
        margin: 0;
    }
    
    .unread-badge {
        background: #e74c3c;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
    }
    
    .btn-mark-all {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .notification-item {
        border-left: 4px solid;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 5px;
        transition: transform 0.2s;
    }
    
    .notification-item:hover {
        transform: translateX(5px);
    }
    
    .notification-item.unread {
        background: #e3f2fd;
    }
    
    .notification-item.read {
        background: #f8f9fa;
        opacity: 0.8;
    }
    
    .notification-item.info { border-left-color: #2196f3; }
    .notification-item.success { border-left-color: #27ae60; }
    .notification-item.warning { border-left-color: #f39c12; }
    .notification-item.danger { border-left-color: #e74c3c; }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
    }
    
    .notification-title {
        font-weight: bold;
        font-size: 16px;
        color: #333;
    }
    
    .notification-time {
        font-size: 12px;
        color: #666;
    }
    
    .notification-message {
        color: #555;
        line-height: 1.6;
        margin-bottom: 10px;
    }
    
    .notification-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-mark-read {
        padding: 5px 15px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 12px;
    }
    
    .btn-view {
        padding: 5px 15px;
        background: #27ae60;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 12px;
    }
    
    .no-notifications {
        text-align: center;
        padding: 60px 20px;
        color: #666;
    }
    
    .no-notifications-icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
</style>

<div class="notifications-container">
    <div class="notifications-header">
        <div>
            <h1>Notifications</h1>
            <?php if ($unread_count > 0): ?>
            <span class="unread-badge"><?php echo $unread_count; ?> Unread</span>
            <?php endif; ?>
        </div>
        <?php if ($unread_count > 0): ?>
        <a href="?mark_all_read=1" class="btn-mark-all">Mark All as Read</a>
        <?php endif; ?>
    </div>
    
    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notif): ?>
        <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?> <?php echo $notif['type']; ?>">
            <div class="notification-header">
                <div class="notification-title">
                    <?php if (!$notif['is_read']): ?>
                    <span style="color: #e74c3c;">●</span>
                    <?php endif; ?>
                    <?php echo escape($notif['title']); ?>
                </div>
                <div class="notification-time">
                    <?php echo formatDateTime($notif['created_at']); ?>
                </div>
            </div>
            <div class="notification-message">
                <?php echo nl2br(escape($notif['message'])); ?>
            </div>
            <div class="notification-actions">
                            <?php if (!$notif['is_read']): ?>
                            <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="btn-mark-read">Mark as Read</a>
                            <?php endif; ?>
                
                <?php if ($notif['related_type'] === 'leave_application' && $notif['related_id']): ?>
                    <?php if (isAdmin()): ?>
                    <a href="admin_manage_leave.php" class="btn-view">View Leave</a>
                    <?php else: ?>
                    <a href="view_leave.php?id=<?php echo $notif['related_id']; ?>" class="btn-view">View Details</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div class="no-notifications">
        <div class="no-notifications-icon">🔔</div>
        <h2>No Notifications</h2>
        <p>You're all caught up! You'll see notifications here when there are updates.</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
