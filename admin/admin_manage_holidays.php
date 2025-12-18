<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if (isset($_POST['add_holiday'])) {
    $holiday_name = sanitize($_POST['holiday_name']);
    $holiday_date = sanitize($_POST['holiday_date']);
    $holiday_type = sanitize($_POST['holiday_type']);
    $description = sanitize($_POST['description']);
    
    $check = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_date = ?");
    $check->bind_param("s", $holiday_date);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        setFlashMessage('error', 'A holiday already exists on this date.');
    } else {
        $stmt = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $holiday_name, $holiday_date, $holiday_type, $description);
        
        if ($stmt->execute()) {
            logAudit($conn, $_SESSION['admin_id'], 'Created Holiday', 'holidays', $conn->insert_id, null, ['holiday_name' => $holiday_name, 'holiday_date' => $holiday_date]);
            setFlashMessage('success', 'Holiday added successfully!');
        } else {
            setFlashMessage('error', 'Failed to add holiday.');
        }
    }
    $check->close();
    header('Location: ' . BASE_URL . '/admin/admin_manage_holidays.php');
    exit();
}

if (isset($_POST['update_holiday'])) {
    $holiday_id = (int)$_POST['holiday_id'];
    $holiday_name = sanitize($_POST['holiday_name']);
    $holiday_date = sanitize($_POST['holiday_date']);
    $holiday_type = sanitize($_POST['holiday_type']);
    $description = sanitize($_POST['description']);
    
    $stmt = $conn->prepare("UPDATE holidays SET holiday_name = ?, holiday_date = ?, holiday_type = ?, description = ? WHERE holiday_id = ?");
    $stmt->bind_param("ssssi", $holiday_name, $holiday_date, $holiday_type, $description, $holiday_id);
    
    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['admin_id'], 'Updated Holiday', 'holidays', $holiday_id, null, ['holiday_name' => $holiday_name]);
        setFlashMessage('success', 'Holiday updated successfully!');
    } else {
        setFlashMessage('error', 'Failed to update holiday.');
    }
    header('Location: ' . BASE_URL . '/admin/admin_manage_holidays.php');
    exit();
}

if (isset($_POST['delete_holiday'])) {
    $holiday_id = (int)$_POST['holiday_id'];
    
    $stmt = $conn->prepare("DELETE FROM holidays WHERE holiday_id = ?");
    $stmt->bind_param("i", $holiday_id);
    
    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['admin_id'], 'Deleted Holiday', 'holidays', $holiday_id, null, null);
        setFlashMessage('success', 'Holiday deleted successfully!');
    } else {
        setFlashMessage('error', 'Failed to delete holiday.');
    }
    header('Location: ' . BASE_URL . '/admin/admin_manage_holidays.php');
    exit();
}

include __DIR__ . '/../includes/header.php';

$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month_filter = isset($_GET['month']) ? (int)$_GET['month'] : 0;

$where_conditions = ["YEAR(holiday_date) = $year_filter"];
if ($month_filter > 0) {
    $where_conditions[] = "MONTH(holiday_date) = $month_filter";
}
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$holidays_query = "
    SELECT holiday_id, holiday_name, holiday_date, holiday_type, description,
           DAYNAME(holiday_date) as day_name,
           MONTHNAME(holiday_date) as month_name
    FROM holidays
    $where_clause
    ORDER BY holiday_date
";
$holidays = $conn->query($holidays_query);

$years_query = "SELECT DISTINCT YEAR(holiday_date) as year FROM holidays ORDER BY year DESC";
$years = $conn->query($years_query);
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .btn-add {
        padding: 12px 25px;
        background: #27ae60;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
    }
    
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
    
    .filter-group select {
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
        margin-top: 20px;
    }
    
    .holidays-container {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .holidays-by-month {
        margin-bottom: 30px;
    }
    
    .month-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 18px;
        font-weight: 600;
    }
    
    .holiday-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        transition: border-color 0.3s;
    }
    
    .holiday-card:hover {
        border-color: #667eea;
    }
    
    .holiday-card.regular {
        border-left: 4px solid #e74c3c;
    }
    
    .holiday-card.special {
        border-left: 4px solid #f39c12;
    }
    
    .holiday-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
    }
    
    .holiday-info h3 {
        color: #333;
        margin-bottom: 5px;
        font-size: 18px;
    }
    
    .holiday-date {
        color: #666;
        font-size: 14px;
    }
    
    .holiday-day {
        color: #667eea;
        font-weight: 600;
    }
    
    .badge {
        padding: 8px 15px;
        border-radius: 15px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .badge-regular {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-special {
        background: #fff3cd;
        color: #856404;
    }
    
    .holiday-description {
        color: #666;
        font-size: 14px;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e0e0e0;
    }
    
    .holiday-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .btn-edit {
        padding: 8px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        font-weight: 600;
    }
    
    .btn-delete {
        padding: 8px 20px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        font-weight: 600;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
    }
    
    .modal.active {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        max-width: 500px;
        width: 90%;
    }
    
    .modal-header {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }
    
    .modal-header h2 {
        color: #333;
        margin: 0;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 600;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .btn-submit {
        flex: 1;
        padding: 12px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .btn-cancel {
        flex: 1;
        padding: 12px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
    }
</style>

<div class="page-header">
    <h1>🗓️ Manage Holidays</h1>
    <a href="#" class="btn-add" onclick="showAddModal(); return false;">+ Add Holiday</a>
</div>

<div class="filters">
    <form method="GET" action="">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="year">Year</label>
                <select name="year" id="year">
                    <?php 
                    $current_year = date('Y');
                    for ($y = $current_year - 1; $y <= $current_year + 2; $y++): 
                    ?>
                    <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="month">Month</label>
                <select name="month" id="month">
                    <option value="0" <?php echo $month_filter == 0 ? 'selected' : ''; ?>>All Months</option>
                    <?php 
                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                    for ($m = 1; $m <= 12; $m++): 
                    ?>
                    <option value="<?php echo $m; ?>" <?php echo $month_filter == $m ? 'selected' : ''; ?>>
                        <?php echo $months[$m-1]; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-filter">Filter</button>
    </form>
</div>

<div class="holidays-container">
    <?php if ($holidays->num_rows > 0): ?>
        <?php 
        $current_month = '';
        while ($holiday = $holidays->fetch_assoc()): 
            if ($current_month != $holiday['month_name']) {
                if ($current_month != '') echo '</div>';
                $current_month = $holiday['month_name'];
                echo '<div class="holidays-by-month">';
                echo '<div class="month-header">' . $current_month . ' ' . $year_filter . '</div>';
            }
        ?>
        <div class="holiday-card <?php echo strtolower($holiday['holiday_type']); ?>">
            <div class="holiday-header">
                <div class="holiday-info">
                    <h3><?php echo escape($holiday['holiday_name']); ?></h3>
                    <div class="holiday-date">
                        <span class="holiday-day"><?php echo $holiday['day_name']; ?></span>, 
                        <?php echo formatDate($holiday['holiday_date']); ?>
                    </div>
                </div>
                <span class="badge badge-<?php echo strtolower($holiday['holiday_type']); ?>">
                    <?php echo $holiday['holiday_type']; ?>
                </span>
            </div>
            
            <?php if ($holiday['description']): ?>
            <div class="holiday-description">
                <?php echo escape($holiday['description']); ?>
            </div>
            <?php endif; ?>
            
            <div class="holiday-actions">
                <button class="btn-edit" onclick='showEditModal(<?php echo json_encode($holiday); ?>)'>Edit</button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this holiday?');">
                    <input type="hidden" name="holiday_id" value="<?php echo $holiday['holiday_id']; ?>">
                    <button type="submit" name="delete_holiday" class="btn-delete">Delete</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
    <p style="text-align: center; color: #666; padding: 40px;">No holidays found for the selected filters.</p>
    <?php endif; ?>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Holiday</h2>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="add_holiday_name">Holiday Name *</label>
                <input type="text" id="add_holiday_name" name="holiday_name" required placeholder="e.g., Christmas Day">
            </div>
            <div class="form-group">
                <label for="add_holiday_date">Date *</label>
                <input type="date" id="add_holiday_date" name="holiday_date" required>
            </div>
            <div class="form-group">
                <label for="add_holiday_type">Holiday Type *</label>
                <select id="add_holiday_type" name="holiday_type" required>
                    <option value="Regular">Regular Holiday</option>
                    <option value="Special">Special Non-working Day</option>
                </select>
            </div>
            <div class="form-group">
                <label for="add_description">Description (Optional)</label>
                <textarea id="add_description" name="description" placeholder="Brief description"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_holiday" class="btn-submit">Add Holiday</button>
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Holiday</h2>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="edit_holiday_id" name="holiday_id">
            <div class="form-group">
                <label for="edit_holiday_name">Holiday Name *</label>
                <input type="text" id="edit_holiday_name" name="holiday_name" required>
            </div>
            <div class="form-group">
                <label for="edit_holiday_date">Date *</label>
                <input type="date" id="edit_holiday_date" name="holiday_date" required>
            </div>
            <div class="form-group">
                <label for="edit_holiday_type">Holiday Type *</label>
                <select id="edit_holiday_type" name="holiday_type" required>
                    <option value="Regular">Regular Holiday</option>
                    <option value="Special">Special Non-working Day</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_description">Description (Optional)</label>
                <textarea id="edit_description" name="description"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_holiday" class="btn-submit">Update Holiday</button>
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function showEditModal(holiday) {
    document.getElementById('edit_holiday_id').value = holiday.holiday_id;
    document.getElementById('edit_holiday_name').value = holiday.holiday_name;
    document.getElementById('edit_holiday_date').value = holiday.holiday_date;
    document.getElementById('edit_holiday_type').value = holiday.holiday_type;
    document.getElementById('edit_description').value = holiday.description || '';
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>