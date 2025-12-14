<?php 
include __DIR__ . '/../includes/header.php'; 
requireAdmin();

$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$entity_filter = isset($_GET['entity_type']) ? sanitize($_GET['entity_type']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 50;
$offset = ($page - 1) * $records_per_page;

$users = $conn->query("SELECT employee_id, full_name FROM employee ORDER BY full_name");

$where_conditions = ["DATE(at.timestamp) BETWEEN '$date_from' AND '$date_to'"];
$params = [];
$types = '';

if ($user_filter > 0) {
    $where_conditions[] = "at.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}
if (!empty($action_filter)) {
    $where_conditions[] = "at.action LIKE ?";
    $params[] = "%$action_filter%";
    $types .= 's';
}
if (!empty($entity_filter)) {
    $where_conditions[] = "at.entity_type = ?";
    $params[] = $entity_filter;
    $types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$count_query = "
    SELECT COUNT(*) as total
    FROM audit_trail at
    $where_clause
";

if ($params) {
    $count_stmt = $conn->prepare($count_query);
    if (!empty($types)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $records_per_page);

$logs_query = "
    SELECT at.log_id, at.action, at.entity_type, at.entity_id,
           at.old_value, at.new_value, at.ip_address, at.timestamp,
           COALESCE(a.full_name, e.full_name) as user_name,
           COALESCE(a.email, e.email) as email
    FROM audit_trail at
    LEFT JOIN admin a ON at.user_type = 'admin' AND at.user_id = a.admin_id
    LEFT JOIN employee e ON at.user_type = 'employee' AND at.user_id = e.employee_id
    $where_clause
    ORDER BY at.timestamp DESC
    LIMIT $offset, $records_per_page
";

if ($params) {
    $stmt = $conn->prepare($logs_query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($logs_query);
}

$actions = $conn->query("SELECT DISTINCT action FROM audit_trail ORDER BY action");

$entity_types = $conn->query("SELECT DISTINCT entity_type FROM audit_trail ORDER BY entity_type");
?>

<style>
    .filters {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
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
    
    .filter-group select,
    .filter-group input {
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
    }
    
    .logs-container {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .log-item {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        transition: border-color 0.3s;
    }
    
    .log-item:hover {
        border-color: #667eea;
    }
    
    .log-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .log-user {
        display: flex;
        flex-direction: column;
    }
    
    .log-user .name {
        font-weight: 600;
        color: #333;
        font-size: 16px;
    }
    
    .log-user .email {
        color: #666;
        font-size: 13px;
    }
    
    .log-time {
        text-align: right;
    }
    
    .log-time .date {
        color: #333;
        font-weight: 600;
    }
    
    .log-time .ip {
        color: #666;
        font-size: 12px;
    }
    
    .log-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 10px;
    }
    
    .detail-item {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
    }
    
    .detail-item label {
        display: block;
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .detail-item .value {
        color: #333;
        font-weight: 600;
    }
    
    .log-changes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 10px;
    }
    
    .change-box {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        font-size: 13px;
    }
    
    .change-box label {
        display: block;
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .change-box pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
        color: #333;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 30px;
    }
    
    .pagination a,
    .pagination span {
        padding: 8px 15px;
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        text-decoration: none;
        color: #333;
        font-weight: 600;
    }
    
    .pagination a:hover {
        border-color: #667eea;
        color: #667eea;
    }
    
    .pagination .active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
</style>

<h1>Audit Logs</h1>

<div class="filters">
    <form method="GET" action="">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="user_id">User</label>
                <select name="user_id" id="user_id">
                    <option value="0">All Users</option>
                    <?php 
                    $users->data_seek(0);
                    while ($user = $users->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $user['employee_id']; ?>" <?php echo $user_filter == $user['employee_id'] ? 'selected' : ''; ?>>
                        <?php echo escape($user['full_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="action">Action</label>
                <select name="action" id="action">
                    <option value="">All Actions</option>
                    <?php while ($action = $actions->fetch_assoc()): ?>
                    <option value="<?php echo escape($action['action']); ?>" <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                        <?php echo escape($action['action']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="entity_type">Entity Type</label>
                <select name="entity_type" id="entity_type">
                    <option value="">All Types</option>
                    <?php while ($entity = $entity_types->fetch_assoc()): ?>
                    <option value="<?php echo escape($entity['entity_type']); ?>" <?php echo $entity_filter === $entity['entity_type'] ? 'selected' : ''; ?>>
                        <?php echo escape($entity['entity_type']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from">Date From</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">Date To</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
            </div>
        </div>
        
        <button type="submit" class="btn-filter">Filter Logs</button>
    </form>
</div>

<div class="logs-container">
    <p style="color: #666; margin-bottom: 20px;">
        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> logs
    </p>
    
    <?php if ($logs->num_rows > 0): ?>
        <?php while ($log = $logs->fetch_assoc()): ?>
        <div class="log-item">
            <div class="log-header">
                <div class="log-user">
                    <span class="name"><?php echo escape($log['user_name']); ?></span>
                    <span class="email"><?php echo escape($log['email']); ?></span>
                </div>
                <div class="log-time">
                    <div class="date"><?php echo formatDateTime($log['timestamp']); ?></div>
                    <div class="ip">IP: <?php echo escape($log['ip_address']); ?></div>
                </div>
            </div>
            
            <div class="log-details">
                <div class="detail-item">
                    <label>Action</label>
                    <div class="value"><?php echo escape($log['action']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Entity Type</label>
                    <div class="value"><?php echo escape($log['entity_type']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Entity ID</label>
                    <div class="value">#<?php echo $log['entity_id']; ?></div>
                </div>
            </div>
            
            <?php if ($log['old_value'] || $log['new_value']): ?>
            <div class="log-changes">
                <?php if ($log['old_value']): ?>
                <div class="change-box">
                    <label>Old Value</label>
                    <pre><?php echo escape($log['old_value']); ?></pre>
                </div>
                <?php endif; ?>
                
                <?php if ($log['new_value']): ?>
                <div class="change-box">
                    <label>New Value</label>
                    <pre><?php echo escape($log['new_value']); ?></pre>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&user_id=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&entity_type=<?php echo urlencode($entity_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                ← Previous
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&user_id=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&entity_type=<?php echo urlencode($entity_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
               class="<?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&user_id=<?php echo $user_filter; ?>&action=<?php echo urlencode($action_filter); ?>&entity_type=<?php echo urlencode($entity_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                Next →
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
    <p style="text-align: center; color: #666; padding: 40px;">No audit logs found for the selected filters.</p>
    <?php endif; ?>
</div>
