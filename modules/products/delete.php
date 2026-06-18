<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) {
    header("Location: index.php");
    exit;
}

// Check if product exists
$stmt = $db->prepare("SELECT id, product_name FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit;
}

// Soft delete - update is_active to 0
$stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
$stmt->execute([$product_id]);

// Redirect back to index
header("Location: index.php");
exit;