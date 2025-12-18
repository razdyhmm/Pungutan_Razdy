<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';
    $target_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
    $current_admin = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($target_id && $action) {
        if ($target_id === $current_admin && $action === 'delete') {
            setFlashMessage('error', 'You cannot delete your own admin account.');
        } else {
            if ($action === 'deactivate') {
                $stmt = $conn->prepare("UPDATE admin SET status = 'inactive' WHERE admin_id = ?");
                $stmt->bind_param('i', $target_id);
                if ($stmt->execute()) {
                    logAudit($conn, $current_admin, 'Deactivated Admin', 'admin', $target_id, null, null);
                    setFlashMessage('success', 'Admin deactivated.');
                } else {
                    setFlashMessage('error', 'Failed to deactivate admin.');
                }
                $stmt->close();
            } elseif ($action === 'activate') {
                $stmt = $conn->prepare("UPDATE admin SET status = 'active' WHERE admin_id = ?");
                $stmt->bind_param('i', $target_id);
                if ($stmt->execute()) {
                    logAudit($conn, $current_admin, 'Activated Admin', 'admin', $target_id, null, null);
                    setFlashMessage('success', 'Admin activated.');
                } else {
                    setFlashMessage('error', 'Failed to activate admin.');
                }
                $stmt->close();
            } elseif ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
                $stmt->bind_param('i', $target_id);
                if ($stmt->execute()) {
                    logAudit($conn, $current_admin, 'Deleted Admin', 'admin', $target_id, null, null);
                    setFlashMessage('success', 'Admin deleted.');
                } else {
                    setFlashMessage('error', 'Failed to delete admin.');
                }
                $stmt->close();
            }
        }
    }

    if (!headers_sent()) {
        header('Location: ' . BASE_URL . '/admin/admin_manage_admins.php');
        exit();
    } else {
        echo '<script>window.location.href = "admin_manage_admins.php";</script>';
        exit();
    }
}

$admins = $conn->query("SELECT admin_id, full_name, email, status FROM admin ORDER BY full_name");

include __DIR__ . '/../includes/header.php';
?>

<style>
.section { max-width: 1000px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);} 
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
.btn { background: #667eea; color: #fff; padding: 8px 12px; border-radius: 6px; text-decoration: none; }
.btn-danger { background: #e74c3c; }
.btn-muted { background: #aaa; }
.action-form { display:inline-block; }
</style>

<div class="section">
    <h2>Manage Admin Accounts</h2>
    <?php $msg = getFlashMessage(); if ($msg): ?>
        <div style="margin-bottom:10px; padding:10px; background: <?php echo $msg['type'] === 'success' ? '#e6ffed' : '#ffe6e6'; ?>; border-radius:6px;">
            <?php echo escape($msg['message']); ?>
        </div>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($a = $admins->fetch_assoc()): ?>
            <tr>
                <td><?php echo escape($a['full_name']); ?></td>
                <td><?php echo escape($a['email']); ?></td>
                <td><?php echo ucfirst($a['status']); ?></td>
                <td>
                    <?php if ($a['admin_id'] != ($_SESSION['user_id'] ?? 0)): ?>
                        <?php if ($a['status'] === 'active'): ?>
                            <form method="POST" class="action-form" onsubmit="return confirm('Deactivate this admin?');">
                                <input type="hidden" name="admin_id" value="<?php echo (int)$a['admin_id']; ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="btn btn-muted">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="action-form" onsubmit="return confirm('Activate this admin?');">
                                <input type="hidden" name="admin_id" value="<?php echo (int)$a['admin_id']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn">Activate</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" class="action-form" onsubmit="return confirm('Permanently delete this admin? This cannot be undone.');">
                            <input type="hidden" name="admin_id" value="<?php echo (int)$a['admin_id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    <?php else: ?>
                        <em>(You)</em>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php';
