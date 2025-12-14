<?php
// Printable leave template (moved to project root)
// Expects $leave and $conn to be provided by the caller (print.php)
if (!isset($leave) || !isset($conn)) {
    echo 'Invalid template usage';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave #<?php echo escape($leave['leave_id']); ?> | <?php echo SITE_SHORT_NAME; ?></title>
    <style>
        /* Embedded print styles (migrated from assets/css/print.css) */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #222; background: #fff; padding: 20px; }
        .printable { max-width: 800px; margin: 0 auto; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        h3 { margin-top: 20px; }
        .print-actions { text-align: right; margin-bottom: 10px; }
        .print-actions button { padding: 6px 12px; }
        @media print { .print-actions { display: none; } a { color: #000; text-decoration: underline; } body { margin: 0; } }
    </style>
    <style>
        /* Template-specific small styles */
        .field { margin-bottom: 8px; }
        .signature { margin-top: 40px; }
        .signature .col { width: 45%; display: inline-block; }
    </style>
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print();">Print</button>
    </div>

    <div class="printable">
        <h1>Leave Application #<?php echo escape($leave['leave_id']); ?></h1>
        <p class="field"><strong>Employee:</strong> <?php echo escape(getEmployeeName($conn, $leave['employee_id'])); ?></p>
        <p class="field"><strong>Leave Type:</strong> <?php echo escape($leave['leave_name']); ?></p>
        <p class="field"><strong>Days Requested:</strong> <?php echo (int)$leave['days_requested']; ?> day(s)</p>
        <p class="field"><strong>From:</strong> <?php echo formatDate($leave['date_from']); ?> <strong>To:</strong> <?php echo formatDate($leave['date_to']); ?></p>
        <p class="field"><strong>Applied Date:</strong> <?php echo formatDateTime($leave['applied_date']); ?></p>

        <?php if (!empty($leave['approved_date'])): ?>
            <p class="field"><strong>Decision Date:</strong> <?php echo formatDateTime($leave['approved_date']); ?></p>
        <?php endif; ?>

        <h3>Reason</h3>
        <div style="white-space: pre-wrap; border: 1px solid #ddd; padding: 10px;">
            <?php echo escape($leave['reason']); ?>
        </div>

        <?php if (!empty($leave['remarks'])): ?>
            <h3>Admin Remarks</h3>
            <div style="white-space: pre-wrap; border: 1px solid #ddd; padding: 10px;">
                <?php echo escape($leave['remarks']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($leave['attachment'])): ?>
            <h3>Attachment</h3>
            <p><a href="<?php echo BASE_URL; ?>/assets/uploads/<?php echo escape($leave['attachment']); ?>" target="_blank">View attachment</a></p>
        <?php endif; ?>

        <div class="signature">
            <div class="col">
                <p>__________________________</p>
                <p>Employee Signature</p>
            </div>
            <div class="col" style="float:right; text-align:right;">
                <p>__________________________</p>
                <p>Approver Signature</p>
            </div>
        </div>
    </div>

</body>
</html>
