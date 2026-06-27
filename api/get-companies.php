<?php
require_once __DIR__ . '/../../core/config.php';
header('Content-Type: application/json; charset=utf-8');
$db = getDB();
$companies = $db->query("SELECT id, company_name_ar FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($companies);
