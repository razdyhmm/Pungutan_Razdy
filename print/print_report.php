<?php
// Printable report template (moved to project root)
// Expects $report_data, $date_from, $date_to and helpers available
if (!isset($report_data)) {
    echo 'Invalid template usage';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Report | <?php echo SITE_SHORT_NAME; ?></title>
    <style>
        /* Embedded print styles (from assets/css/print.css) */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #222; background: #fff; padding: 20px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .print-actions { text-align: right; margin-bottom: 10px; }
        .print-actions button { padding: 6px 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        @media print { .print-actions { display: none; } a { color: #000; text-decoration: underline; } body { margin: 0; } }
    </style>
    <style>
        /* Template-specific small styles */
        .report-header { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print();">Print</button>
    </div>

    <div class="report-header">
        <h1>Leave Summary Report</h1>
        <p>Period: <?php echo formatDate($date_from); ?> — <?php echo formatDate($date_to); ?></p>
    </div>

    <?php if ($report_data && $report_data->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th style="text-align:center;">Pending</th>
                    <th style="text-align:center;">Approved</th>
                    <th style="text-align:center;">Rejected</th>
                    <th style="text-align:right;">Total Days Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $report_data->fetch_assoc()): ?>
                <tr>
                    <td><?php echo escape($row['full_name']); ?></td>
                    <td><?php echo escape($row['department_name']); ?></td>
                    <td style="text-align:center;"><?php echo $row['pending_count']; ?></td>
                    <td style="text-align:center;"><?php echo $row['approved_count']; ?></td>
                    <td style="text-align:center;"><?php echo $row['rejected_count']; ?></td>
                    <td style="text-align:right;"><strong><?php echo $row['total_days_taken']; ?></strong> days</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color:#666;">No data available for the selected filters.</p>
    <?php endif; ?>
</body>
</html>
