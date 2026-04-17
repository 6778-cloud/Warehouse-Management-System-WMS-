<?php
require_once 'config/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'วิธีการร้องขอไม่ถูกต้อง']);
    exit;
}

verifyCsrfToken($_POST['csrf_token']);

$discrepancy_id = $_POST['discrepancy_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$discrepancy_id || !in_array($action, ['accepted', 'escalated'])) {
    echo json_encode(['success' => false, 'message' => 'พารามิเตอร์ไม่ถูกต้อง']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE inbound_discrepancies 
                           SET resolution = ?, resolved_by = ?, resolved_at = NOW() 
                           WHERE discrepancy_id = ?");
    $stmt->execute([$action, $_SESSION['user_id'], $discrepancy_id]);

    echo json_encode(['success' => true, 'message' => 'ดำเนินการเรียบร้อย']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
