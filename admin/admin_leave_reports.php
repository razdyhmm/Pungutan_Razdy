<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';
$employee_filter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$department_filter = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-01-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'All';

$employees = $conn->query("SELECT employee_id, full_name FROM employee WHERE status = 'active' ORDER BY full_name");

$departments = $conn->query("SELECT department_id, department_name FROM department ORDER BY department_name");

$report_data = null;
$query = "
    SELECT e.employee_id, e.full_name, d.department_name,
           SUM(CASE WHEN TRIM(LOWER(la.status)) = 'pending' THEN 1 ELSE 0 END) as pending_count,
           SUM(CASE WHEN TRIM(LOWER(la.status)) = 'approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN TRIM(LOWER(la.status)) = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
           COALESCE(SUM(CASE WHEN TRIM(LOWER(la.status)) = 'approved' THEN la.days_requested ELSE 0 END),0) as total_days_taken
    FROM employee e
    LEFT JOIN department d ON e.department_id = d.department_id
    LEFT JOIN leave_application la ON e.employee_id = la.employee_id
        AND (
            (la.applied_date BETWEEN ? AND ?) 
            OR (la.date_from <= ? AND la.date_to >= ?)
        )
";

$conditions = ["e.status = 'active'"];
if ($employee_filter > 0) {
    $conditions[] = "e.employee_id = $employee_filter";
}
if ($department_filter > 0) {
    $conditions[] = "e.department_id = $department_filter";
}

$query .= " WHERE " . implode(" AND ", $conditions);
$query .= " GROUP BY e.employee_id ORDER BY e.full_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $date_from, $date_to, $date_to, $date_from);
$stmt->execute();
$report_data = $stmt->get_result();

include __DIR__ . '/../includes/header.php';

?>

<h1>Leave Reports</h1>

<style>
    .filters {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .filter-group { display:flex; flex-direction:column; }
    .filter-group label { margin-bottom:6px; font-weight:600; }
    .filter-group select, .filter-group input { padding:10px; border:1px solid #e0e0e0; border-radius:5px; }
    .filter-actions { display:flex; gap:10px; }
    .btn-filter { padding:10px 20px; background:#667eea; color:#fff; border:none; border-radius:5px; cursor:pointer; }
    .report-container { background:white; padding:25px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
    .report-header { margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid #667eea; display:flex; justify-content:space-between; align-items:center; }
    .btn-print-report { padding:8px 12px; background:#34495e; color:white; text-decoration:none; border-radius:6px; font-weight:600; }
    .btn-print-report:hover { background:#2c3e50; }
    table { width:100%; border-collapse:collapse; }
    table th { background:#f8f9fa; padding:12px; text-align:left; border-bottom:2px solid #dee2e6; }
    table td { padding:12px; border-bottom:1px solid #dee2e6; }
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    .no-data { text-align:center; padding:40px; color:#666; }
    .badge { padding:5px 10px; border-radius:12px; font-weight:600; }
</style>

<div class="filters">
    <form method="GET" action="">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="employee_id">Employee</label>
                <select name="employee_id" id="employee_id">
                    <option value="0">All Employees</option>
                    <?php $employees->data_seek(0); while ($emp = $employees->fetch_assoc()): ?>
                        <option value="<?php echo $emp['employee_id']; ?>" <?php echo $employee_filter == $emp['employee_id'] ? 'selected' : ''; ?>><?php echo escape($emp['full_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="department_id">Department</label>
                <select name="department_id" id="department_id">
                    <option value="0">All Departments</option>
                    <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>><?php echo escape($dept['department_name']); ?></option>
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

        <div class="filter-actions">
            <button type="submit" class="btn-filter">Generate Report</button>
        </div>
    </form>
</div>

<div class="report-container">
    <div class="report-header">
        <div>
            <h2>Leave Summary Report</h2>
            <p>Period: <?php echo formatDate($date_from); ?> to <?php echo formatDate($date_to); ?></p>
        </div>
        <div>
            <?php
                $print_q = http_build_query([
                    'type' => 'report',
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'employee_id' => $employee_filter,
                    'department_id' => $department_filter
                ]);
            ?>
            <a href="<?php echo BASE_URL; ?>/print/print.php?<?php echo $print_q; ?>" target="_blank" class="btn-print-report">🖨️ Print Report</a>
        </div>
    </div>

    <?php if ($report_data && $report_data->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Approved</th>
                    <th class="text-center">Rejected</th>
                    <th class="text-right">Total Days Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $report_data->fetch_assoc()): ?>
                <tr>
                    <td><?php echo escape($row['full_name']); ?></td>
                    <td><?php echo escape($row['department_name']); ?></td>
                    <td class="text-center"><?php echo $row['pending_count']; ?></td>
                    <td class="text-center"><?php echo $row['approved_count']; ?></td>
                    <td class="text-center"><?php echo $row['rejected_count']; ?></td>
                    <td class="text-right"><strong><?php echo $row['total_days_taken']; ?></strong> days</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No data available for the selected filters.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>