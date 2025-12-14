<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $authenticated = false;

        $admin_stmt = $conn->prepare("SELECT admin_id, full_name, email, password, status FROM admin WHERE email = ?");
        $admin_stmt->bind_param("s", $email);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        
        if ($admin_result->num_rows === 1) {
            $user = $admin_result->fetch_assoc();
            
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['admin_id'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];

                logAudit($conn, $user['admin_id'], 'Admin Login', 'admin', $user['admin_id'], null, ['email' => $email]);
                
                $authenticated = true;
                header('Location: ' . BASE_URL . '/admin/admin_dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        }
        $admin_stmt->close();

        if (!$authenticated) {
            $emp_stmt = $conn->prepare("SELECT employee_id, full_name, email, password, status FROM employee WHERE email = ?");
            $emp_stmt->bind_param("s", $email);
            $emp_stmt->execute();
            $emp_result = $emp_stmt->get_result();
            
            if ($emp_result->num_rows === 1) {
                $user = $emp_result->fetch_assoc();
                
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Please contact HR.';
                } elseif (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['employee_id'];
                    $_SESSION['user_type'] = 'employee';
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];

                    logAudit($conn, $user['employee_id'], 'Employee Login', 'employee', $user['employee_id'], null, ['email' => $email]);
                    
                    header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
            $emp_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
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
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .demo-credentials {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .demo-credentials h4 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .demo-credentials p {
            margin: 5px 0;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo SITE_SHORT_NAME; ?></h1>
            <p><?php echo SITE_NAME; ?></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo escape($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
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
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="Enter your password"
                >
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>   
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></p>
        </div>
    </div>
</body>
</html>