<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$supplier_id = intval($_GET['id'] ?? 0);
if ($supplier_id <= 0) {
    redirect('index.php');
}

// Check if supplier exists
$stmt = $db->prepare("SELECT id FROM suppliers WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '')");
$stmt->execute([$supplier_id]);
if (!$stmt->fetch()) {
    redirect('index.php');
}

try {
    // Soft delete - set deleted_at timestamp
    $db->prepare("UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$supplier_id]);

    // Log the action
    $db->prepare("
        INSERT INTO activity_logs (user_id, user_name, action, table_name, record_id, new_value)
        VALUES (?, ?, 'delete', 'suppliers', ?, ?)
    ")->execute([
        $_SESSION['user_id'] ?? null,
        $_SESSION['full_name'] ?? 'System',
        $supplier_id,
        json_encode(['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0])
    ]);

    session_write_close();
    header("Location: index.php?deleted=1");
    exit;

} catch (PDOException $e) {
    session_write_close();
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}