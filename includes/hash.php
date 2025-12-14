<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_POST['password'])) {
    $password = $_POST['password'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $message = "Password Hash: " . $hash;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .result {
            margin-top: 20px;
            padding: 10px;
            background-color: #f0f0f0;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <h2>Password Hash Generator</h2>
    <form method="POST">
        <div class="form-group">
            <label for="password">Enter Password:</label><br>
            <input type="text" id="password" name="password" required>
        </div>
        <button type="submit">Generate Hash</button>
    </form>

    <?php if (isset($message)): ?>
        <div class="result">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
</body>
</html>