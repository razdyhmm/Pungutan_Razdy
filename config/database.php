<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "employee_leave_system";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    if (!$conn->ping()) {
        throw new Exception("Database connection lost: " . $conn->error);
    }
} catch (Exception $e) {
    die("Fatal Error: " . $e->getMessage() . " (Error occurred in " . __FILE__ . " on line " . __LINE__ . ")");
}
?>