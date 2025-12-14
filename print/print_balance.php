<?php
// Printable current leave balance (root template)
// Expects $credit, $credit_history and $employee_id to be set by print.php
if (!isset($employee_id) || !isset($credit)) {
    echo 'Invalid template usage';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Balance | <?php echo SITE_SHORT_NAME; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #222; background: #fff; padding: 20px; }
        .print-actions { text-align: right; margin-bottom: 10px; }
        .print-actions button { padding: 6px 12px; }
        .printable { max-width: 1000px; margin: 0 auto; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        .balance-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); margin-bottom: 16px; }
        .balance-stats { display:flex; gap:20px; }
        .balance-stat { flex:1; text-align:center; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding:8px; border:1px solid #e9ecef; }
        th { background:#f8f9fa; }
        @media print { .print-actions { display:none; } }
    </style>
    </head>
<body>
    <div class="print-actions"><button onclick="window.print();">Print</button></div>
    <div class="printable">
        <h1>Current Leave Balance</h1>
        <p><strong>Employee:</strong> <?php echo escape(getEmployeeName($conn, $employee_id)); ?></p>

        <div class="balance-card">
            <div class="balance-stats">
                <div class="balance-stat"><div style="font-size:14px;color:#666;">Total</div><div style="font-size:20px;font-weight:700;"><?php echo isset($credit['total_credits']) ? $credit['total_credits'] : '0'; ?></div></div>
                <div class="balance-stat"><div style="font-size:14px;color:#666;">Used</div><div style="font-size:20px;font-weight:700;color:#e74c3c;"><?php echo isset($credit['used_credits']) ? $credit['used_credits'] : '0'; ?></div></div>
                <div class="balance-stat"><div style="font-size:14px;color:#666;">Remaining</div><div style="font-size:20px;font-weight:700;color:#27ae60;"><?php echo isset($credit['remaining_credits']) ? $credit['remaining_credits'] : '0'; ?></div></div>
            </div>
            <p style="font-size:12px;color:#666;margin-top:10px;">Last updated: <?php echo isset($credit['last_updated']) ? formatDateTime($credit['last_updated']) : 'Never'; ?></p>
        </div>

        <h2>Credit History</h2>
        <?php if ($credit_history && $credit_history->num_rows > 0): ?>
            <table>
                <thead>
                    <tr><th>Date</th><th>Leave Type</th><th>Change</th><th>Reason</th><th>Recorded By</th></tr>
                </thead>
                <tbody>
                    <?php while ($r = $credit_history->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo formatDateTime($r['date_recorded']); ?></td>
                        <td><?php echo escape($r['leave_name']); ?></td>
                        <td><?php echo ($r['credit_change'] >= 0 ? '+' : '') . $r['credit_change']; ?></td>
                        <td><?php echo escape($r['reason']); ?></td>
                        <td><?php echo escape($r['recorded_by_name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#666;">No credit history available.</p>
        <?php endif; ?>
    </div>
</body>
</html>
