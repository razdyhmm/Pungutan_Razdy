<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . BASE_URL . '/admin/admin_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
    }
    exit();
}

$registration_enabled = false;

if (!$registration_enabled) {
    die('Registration is disabled. Please contact your administrator.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $position = sanitize($_POST['position']);
    $department_id = (int)$_POST['department_id'];

    if (empty($full_name) || empty($email) || empty($password) || empty($position) || empty($department_id)) {
        $error = 'All fields are required.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare("SELECT employee_id FROM employee WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email address is already registered.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $date_hired = date('Y-m-d');

            $stmt = $conn->prepare("INSERT INTO employee (full_name, email, password, position, department_id, date_hired, role, status) VALUES (?, ?, ?, ?, ?, ?, 'employee', 'active')");
            $stmt->bind_param("ssssis", $full_name, $email, $hashed_password, $position, $department_id, $date_hired);
            
            if ($stmt->execute()) {
                $new_employee_id = $conn->insert_id;

                $initial_total = 1.0;
                $insert_credit = $conn->prepare("INSERT INTO employee_credit (employee_id, total_credits, used_credits, remaining_credits, last_updated) VALUES (?, ?, 0, ?, NOW())");
                if ($insert_credit) {
                    $insert_credit->bind_param("idd", $new_employee_id, $initial_total, $initial_total);
                    $insert_credit->execute();
                    $insert_credit->close();
                }

                $credit_stmt = $conn->prepare("INSERT INTO leave_credit_log (employee_id, leave_type_id, credit_change, reason, recorded_by) VALUES (?, 0, ?, 'Initial default credit point', ?)");
                if ($credit_stmt) {
                    $credit_stmt->bind_param("idi", $new_employee_id, $initial_total, $new_employee_id);
                    $credit_stmt->execute();
                    $credit_stmt->close();
                }
                
                $success = 'Registration successful! You can now login.';

                $_POST = array();
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}

$departments = $conn->query("SELECT department_id, department_name FROM department ORDER BY department_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            padding: 40px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .register-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create Account</h1>
            <p><?php echo SITE_NAME; ?></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo escape($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo escape($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    required
                    value="<?php echo isset($_POST['full_name']) ? escape($_POST['full_name']) : ''; ?>"
                    placeholder="Enter your full name"
                >
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required
                    value="<?php echo isset($_POST['email']) ? escape($_POST['email']) : ''; ?>"
                    placeholder="Enter your email"
                >
            </div>
            
            <div class="form-group">
                <label for="position">Position</label>
                <input 
                    type="text" 
                    id="position" 
                    name="position" 
                    required
                    value="<?php echo isset($_POST['position']) ? escape($_POST['position']) : ''; ?>"
                    placeholder="Enter your position"
                >
            </div>
            
            <div class="form-group">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id" required>
                    <option value="">Select Department</option>
                    <?php while ($dept = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo escape($dept['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="At least 8 characters"
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required
                    placeholder="Re-enter password"
                >
            </div>
            
            <button type="submit" class="btn-register">Register</button>
        </form>
        
        <div class="register-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>