<?php
require_once __DIR__ . '/../../core/config.php';
header('Content-Type: application/json; charset=utf-8');
$db = getDB();
$categories = $db->query("SELECT id, category_name_ar FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($categories);
