<?php
define('SITE_NAME', 'Employee Leave Management System');
define('SITE_SHORT_NAME', 'ELMS');
define('SITE_VERSION', '1.0');

define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_EMPLOYEE', 'employee');

// Base URL path for header redirects (adjust if project is served from a subfolder)
define('BASE_URL', '/employee_leave_system');


