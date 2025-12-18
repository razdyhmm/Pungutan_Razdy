<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if (isset($_POST['add_employee'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $position = sanitize($_POST['position']);
    $department_id = (int)$_POST['department_id'];
    $date_hired = sanitize($_POST['date_hired']);
    
    $email_check = $conn->prepare("SELECT employee_id FROM employee WHERE email = ?");
    $email_check->bind_param("s", $email);
    $email_check->execute();
    if ($email_check->get_result()->num_rows > 0) {
        setFlashMessage('error', 'Email already exists.');
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO employee (full_name, email, password, position, department_id, date_hired, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssssss", $full_name, $email, $hashed_password, $position, $department_id, $date_hired);
        
        if ($stmt->execute()) {
            $new_employee_id = $conn->insert_id;
            
            $initial_total = 1.0;

            $insert_credit = $conn->prepare("INSERT INTO employee_credit (employee_id, total_credits, used_credits, remaining_credits, last_updated) VALUES (?, ?, 0, ?, NOW())");
            if ($insert_credit) {
                $insert_credit->bind_param("idd", $new_employee_id, $initial_total, $initial_total);
                $insert_credit->execute();
                $insert_credit->close();
            }
        
            $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            logAudit($conn, $admin_id, 'Created Employee', 'employee', $new_employee_id, null, ['full_name' => $full_name, 'email' => $email]);
            setFlashMessage('success', 'Employee added successfully!');
        } else {
            setFlashMessage('error', 'Failed to add employee.');
        }
        $stmt->close();
    }
    $email_check->close();
    
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . '/admin/admin_manage_employees.php');
        exit();
    } else {
        echo '<script>window.location.href = "admin_manage_employees.php";</script>';
        exit();
    }
}

if (isset($_POST['update_employee'])) {
    $employee_id = (int)$_POST['employee_id'];
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $position = sanitize($_POST['position']);
    $department_id = (int)$_POST['department_id'];
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE employee SET full_name = ?, email = ?, position = ?, department_id = ?, status = ? WHERE employee_id = ?");
    $stmt->bind_param("sssssi", $full_name, $email, $position, $department_id, $status, $employee_id);
    
    if ($stmt->execute()) {
        $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        logAudit($conn, $admin_id, 'Updated Employee', 'employee', $employee_id, null, ['full_name' => $full_name]);
        setFlashMessage('success', 'Employee updated successfully!');
    } else {
        setFlashMessage('error', 'Failed to update employee.');
    }
    $stmt->close();
    
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . '/admin/admin_manage_employees.php');
        exit();
    } else {
        echo '<script>window.location.href = "admin_manage_employees.php";</script>';
        exit();
    }
}

if (isset($_POST['delete_employee'])) {
    $employee_id = (int)$_POST['employee_id'];
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    if ($employee_id === $admin_id) {
        setFlashMessage('error', 'You cannot delete your own account.');
    } else {
        $stmt = $conn->prepare("DELETE FROM employee WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        
        if ($stmt->execute()) {
            logAudit($conn, $admin_id, 'Deleted Employee', 'employee', $employee_id, null, null);
            setFlashMessage('success', 'Employee deleted successfully!');
        } else {
            setFlashMessage('error', 'Failed to delete employee.');
        }
        $stmt->close();
    }
    
    if (!headers_sent()) {
        header('Location: ' . BASE_URL . '/admin/admin_manage_employees.php');
        exit();
    } else {
        echo '<script>window.location.href = "admin_manage_employees.php";</script>';
        exit();
    }
}

$employees_query = "
    SELECT 
        e.*,
        d.department_name
    FROM employee e
    LEFT JOIN department d ON e.department_id = d.department_id
    ORDER BY e.full_name
";
$employees = $conn->query($employees_query);

$departments_query = "SELECT department_id, department_name FROM department WHERE department_name != '' ORDER BY department_name";
$departments = $conn->query($departments_query);

    include __DIR__ . '/../includes/header.php';

?>
<style>
    .section {
        max-width: 1100px;
        margin: 30px auto;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 25px;
    }
    .section h2 {
        color: #333;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
        font-size: 18px;
    }
    .mb-3 {
        margin-bottom: 20px;
    }
    .btn {
        background: #667eea;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 8px 18px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn:hover {
        background: #5563c1;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    th, td {
        padding: 10px 12px;
        border-bottom: 1px solid #e0e0e0;
        text-align: left;
    }
    th {
        background: #f8f9fa;
        color: #333;
        font-weight: 600;
    }
    tr:last-child td {
        border-bottom: none;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        color: #fff;
    }
    .badge-success {
        background: #27ae60;
    }
    .badge-danger {
        background: #e74c3c;
    }
    .action-btns {
        display: flex;
        gap: 8px;
    }
    .btn-edit {
        background: #f1c40f;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 6px 14px;
        font-size: 0.95rem;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.2s;
    }
    .btn-edit:hover {
        background: #d4ac0d;
    }
    .btn-delete {
        background: #e74c3c;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 6px 14px;
        font-size: 0.95rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-delete:hover {
        background: #c0392b;
    }
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        overflow: auto;
        background: rgba(0,0,0,0.25);
        justify-content: center;
        align-items: center;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background: #fff;
        padding: 30px 35px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        min-width: 350px;
        max-width: 95vw;
    }
    .form-group {
        margin-bottom: 18px;
    }
    .form-group label {
        display: block;
        margin-bottom: 6px;
        color: #333;
        font-weight: 500;
    }
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 1rem;
        background: #f8f9fa;
    }
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 10px;
    }
    .btn-submit {
        background: #667eea;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 8px 18px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-submit:hover {
        background: #5563c1;
    }
    .btn-cancel {
        background: #aaa;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 8px 18px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-cancel:hover {
        background: #888;
    }
</style>

<div class="section">
    <h2>Manage Employees</h2>
    
    <div class="mb-3">
        <button onclick="showAddModal()" class="btn">Add New Employee</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Position</th>
                <th>Department</th>
                <th>Status</th>
                <th>Date Hired</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($emp = $employees->fetch_assoc()) { ?>
            <tr>
                <td><?php echo escape($emp['full_name']); ?></td>
                <td><?php echo escape($emp['email']); ?></td>
                <td><?php echo escape($emp['position']); ?></td>
                <td><?php echo escape($emp['department_name'] ?? 'Not Assigned'); ?></td>
                <td>
                    <span class="badge badge-pill badge-<?php echo $emp['status'] === 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($emp['status']); ?>
                    </span>
                </td>
                <td><?php echo formatDate($emp['date_hired']); ?></td>
                <td>
                    <div class="action-btns">
                        <a href="#" class="btn-edit" onclick='showEditModal(<?php echo json_encode($emp); ?>); return false;'>Edit</a>
                        <?php 
                        $current_employee_id = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
                        if ($emp['employee_id'] != $current_employee_id) { ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                            <input type="hidden" name="employee_id" value="<?php echo $emp['employee_id']; ?>">
                            <button type="submit" name="delete_employee" class="btn-delete">Delete</button>
                        </form>
                        <?php } ?>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <h2>Add New Employee</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Position *</label>
                <input type="text" name="position" required>
            </div>
            <div class="form-group">
                <label>Department *</label>
                <select name="department_id" required>
                    <option value="">Select Department</option>
                    <?php while ($dept = $departments->fetch_assoc()) { ?>
                    <option value="<?php echo $dept['department_id']; ?>">
                        <?php echo escape($dept['department_name']); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date Hired *</label>
                <input type="date" name="date_hired" required>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_employee" class="btn-submit">Add Employee</button>
                <button type="button" onclick="closeAddModal()" class="btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h2>Edit Employee</h2>
        <form method="POST" action="">
            <input type="hidden" id="edit_employee_id" name="employee_id">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" id="edit_full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            <div class="form-group">
                <label>Position *</label>
                <input type="text" id="edit_position" name="position" required>
            </div>
            <div class="form-group">
                <label>Department *</label>
                <select id="edit_department_id" name="department_id" required>
                    <option value="">Select Department</option>
                    <?php 
                    $departments->data_seek(0);
                    while ($dept = $departments->fetch_assoc()) { 
                    ?>
                    <option value="<?php echo $dept['department_id']; ?>">
                        <?php echo escape($dept['department_name']); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select id="edit_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_employee" class="btn-submit">Update Employee</button>
                <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancel</button>
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

function showEditModal(employee) {
    document.getElementById('edit_employee_id').value = employee.employee_id;
    document.getElementById('edit_full_name').value = employee.full_name;
    document.getElementById('edit_email').value = employee.email;
    document.getElementById('edit_position').value = employee.position;
    document.getElementById('edit_department_id').value = employee.department_id;
    document.getElementById('edit_status').value = employee.status;
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