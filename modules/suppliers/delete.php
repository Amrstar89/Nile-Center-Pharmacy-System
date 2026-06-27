<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$supplier_id = intval($_GET['id'] ?? 0);
if ($supplier_id <= 0) {
    header("Location: index.php");
    exit;
}

// Check if supplier exists
$stmt = $db->prepare("SELECT id FROM suppliers WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '')");
$stmt->execute([$supplier_id]);
if (!$stmt->fetch()) {
    header("Location: index.php");
    exit;
}

try {
    // Soft delete - set deleted_at timestamp
    $db->prepare("UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$supplier_id]);

    header("Location: index.php?deleted=1");
    exit;

} catch (PDOException $e) {
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}
