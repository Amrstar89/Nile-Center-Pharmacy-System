<?php
require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$governorate_id = intval($_GET['governorate_id'] ?? 0);

if ($governorate_id === 0) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, area_name_ar, area_name_en FROM areas WHERE governorate_id = ? AND is_active = 1 ORDER BY area_name_ar");
$stmt->execute([$governorate_id]);
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($areas);