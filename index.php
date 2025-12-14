<?php
require_once __DIR__ . '/config/constants.php';
session_start();

if (isset($_SESSION['user_type']) && isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: ' . BASE_URL . '/admin/admin_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/employee/employee_dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <style>
        html, body {
            width: 100%;
            overflow-x: hidden;
        }

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
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .landing-container {
            background: white;
            border-radius: 0;
            box-shadow: none;
            max-width: 100%;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: visible;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 100px 40px;
            text-align: center;
            color: white;
            min-height: 70vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .hero-section h1 {
            font-size: 56px;
            margin-bottom: 18px;
            text-shadow: 0 4px 18px rgba(0,0,0,0.25);
            line-height: 1.05;
        }

        .hero-section p {
            font-size: 20px;
            opacity: 0.97;
            margin-bottom: 36px;
            max-width: 1000px;
        }
        
        .btn-login {
            display: inline-block;
            padding: 18px 56px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 36px;
            font-size: 20px;
            font-weight: 800;
            transition: transform 0.25s, box-shadow 0.25s;
            box-shadow: 0 8px 28px rgba(0,0,0,0.18);
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
        
        .features-section {
            padding: 50px 40px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 36px;
            margin-top: 40px;
        }
        
        .feature-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 10px;
            background: #f8f9fa;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 56px;
            margin-bottom: 18px;
        }
        
        .feature-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .section-title {
            text-align: center;
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .section-subtitle {
            text-align: center;
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 40px;
            text-align: center;
        }
        
        .info-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .demo-credentials {
            background: white;
            padding: 28px;
            border-radius: 10px;
            max-width: 680px;
            margin: 0 auto;
            box-shadow: 0 8px 28px rgba(0,0,0,0.12);
        }
        
        .demo-credentials h4 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .credential-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            text-align: left;
        }
        
        .credential-item strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        
        .credential-item span {
            color: #666;
            font-size: 14px;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 28px 20px;
        }
        
        .footer p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 40px;
            background: white;
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 40px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        @media (max-width: 900px) {
            .hero-section {
                padding: 48px 20px;
                min-height: 50vh;
            }

            .hero-section h1 {
                font-size: 30px;
            }

            .hero-section p {
                font-size: 15px;
                margin-bottom: 20px;
            }

            .btn-login {
                padding: 12px 28px;
                font-size: 16px;
            }

            .features-grid {
                gap: 18px;
            }

            .demo-credentials {
                max-width: 92%;
                padding: 18px;
            }
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="hero-section">
            <h1>📋 <?php echo SITE_NAME; ?></h1>
            <p>Streamline your leave management process with our modern, easy-to-use system</p>
            <a href="auth/login.php" class="btn-login">Login to Continue</a>
        </div>
        
        <div class="stats-section">
            <div class="stat-box">
                <div class="stat-number">100%</div>
                <div class="stat-label">Digital</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Access</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">Real-time</div>
                <div class="stat-label">Updates</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">Secure</div>
                <div class="stat-label">Data</div>
            </div>
        </div>
        
        <div class="features-section">
            <h2 class="section-title">Key Features</h2>
            <p class="section-subtitle">Everything you need to manage employee leave efficiently</p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Easy Application</h3>
                    <p>Submit leave requests with just a few clicks. Track status in real-time.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">✅</div>
                    <h3>Quick Approval</h3>
                    <p>Admins can approve or reject leave applications instantly with remarks.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💳</div>
                    <h3>Balance Tracking</h3>
                    <p>View your leave credits, used days, and remaining balance at a glance.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Detailed Reports</h3>
                    <p>Generate comprehensive reports by employee, department, or date range.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>Notifications</h3>
                    <p>Get instant updates on leave application status and approvals.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure & Private</h3>
                    <p>Role-based access control.</p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p><strong><?php echo SITE_NAME; ?></strong> v<?php echo SITE_VERSION; ?></p>
            <p>&copy; <?php echo date('Y'); ?> All rights reserved.</p>
            <p style="font-size: 13px; margin-top: 10px; opacity: 0.7;">
                Built with PHP & MySQL | Secure & Reliable
            </p>
        </div>
    </div>
</body>
</html>