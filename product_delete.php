<?php
require_once 'config/db.php';
requireLogin();

$id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$id || !$token) {
    die("คำขอไม่ถูกต้อง");
}

if (!verifyCsrfToken($token)) {
    die("โทเค็นไม่ถูกต้อง");
}

// Check dependencies (e.g. stock, transaction history)
// If product has transaction history, we might want to soft-delete (set active=0) or forbid delete.
// For now, strict check: if stock exists, don't delete.

$check = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE product_id = ? AND qty > 0");
$check->execute([$id]);
if ($check->fetchColumn() > 0) {
    die("ไม่สามารถลบสินค้าที่มีสต็อกอยู่ได้ กรุณาลบสต็อกก่อน");
}

try {
    // Delete image if exists
    $stmt = $pdo->prepare("SELECT image_path FROM products WHERE product_id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();
    if ($img && file_exists($img)) {
        unlink($img);
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$id]);

    // Log
    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, ?, 'product', ?, ?)");
    $log->execute([$_SESSION['user_id'], 'delete', $id, "ลบสินค้ารหัส $id"]);

    header("Location: products.php");
    exit;
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการลบสินค้า: " . $e->getMessage());
}
?>