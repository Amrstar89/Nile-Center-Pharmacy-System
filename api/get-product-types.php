<?php
require_once __DIR__ . '/../../core/config.php';
header('Content-Type: application/json; charset=utf-8');
$db = getDB();
$types = $db->query("SELECT id, type_name_ar FROM product_types WHERE is_active = 1 ORDER BY type_name_ar")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($types);
