<?php
// Printable complete leave history for an employee
// Expects $leave_history and $employee_id set by print.php
if (!isset($employee_id) || !isset($leave_history)) {
    echo 'Invalid template usage';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave History | <?php echo SITE_SHORT_NAME; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #222; background: #fff; padding: 20px; }
        .print-actions { text-align: right; margin-bottom: 10px; }
        .print-actions button { padding: 6px 12px; }
        .printable { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding:8px; border:1px solid #e9ecef; }
        th { background:#f8f9fa; }
        .status { font-weight:700; }
        @media print { .print-actions { display:none; } }
    </style>
    </head>
<body>
    <div class="print-actions"><button onclick="window.print();">Print</button></div>
    <div class="printable">
        <h1>Complete Leave History</h1>
        <p><strong>Employee:</strong> <?php echo escape(getEmployeeName($conn, $employee_id)); ?></p>

        <?php if ($leave_history && $leave_history->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Application #</th>
                        <th>Leave Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Applied</th>
                        <th>Decision</th>
                        <th>Approver</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $leave_history->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int)$row['leave_id']; ?></td>
                        <td><?php echo escape($row['leave_name']); ?></td>
                        <td><?php echo formatDate($row['date_from']); ?></td>
                        <td><?php echo formatDate($row['date_to']); ?></td>
                        <td><?php echo (int)$row['days_requested']; ?></td>
                        <td><?php echo formatDateTime($row['applied_date']); ?></td>
                        <td><?php echo !empty($row['approved_date']) ? formatDateTime($row['approved_date']) : '-'; ?></td>
                        <td><?php echo escape($row['approver_name']); ?></td>
                        <td class="status"><?php echo escape(ucfirst($row['status'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#666;">No leave applications found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
