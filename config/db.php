<?php
// config/db.php

$host = 'localhost';
$db_name = 'wms_smart';
$username = 'root';
$password = ''; // Default XAMPP password is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);

    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // In production, log this error instead of echoing it
    // error_log("Connection failed: " . $e->getMessage());
    die("Database Connection Failed. Please ensure the database 'wms_smart' exists.");
}

// Security Helper Functions

// Prevent XSS
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Start Session with security settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session params
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // ini_set('session.cookie_secure', 1); // Enable if using HTTPS

    session_start();
}

// CSRF Protection
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('CSRF Token Validation Failed');
    }
    return true;
}

// Auth Checks
function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin()
{
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        die('Access Denied: Admin permission required.');
    }
}

// ฟังก์ชันตรวจสอบสิทธิ์ Staff (พนักงานคลัง - ทำงานจริง: รับเข้า, เบิกออก, picking, packing)
function requireStaffWarehouse()
{
    requireLogin();
    if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
        die('Access Denied: Staff permission required.');
    }
}

// ฟังก์ชันตรวจสอบสิทธิ์ Office (พนักงานออฟฟิศ - คีย์ข้อมูล: shipment, invoice)
function requireOffice()
{
    requireLogin();
    if (!in_array($_SESSION['role'], ['admin', 'office'])) {
        die('Access Denied: office permission required.');
    }
}

// ตรวจสอบว่ามีสิทธิ์หรือไม่ (ไม่ die แต่ return true/false)
function hasRole($roles)
{
    if (!isset($_SESSION['role']))
        return false;
    if (!is_array($roles))
        $roles = [$roles];
    return in_array($_SESSION['role'], $roles);
}
?>