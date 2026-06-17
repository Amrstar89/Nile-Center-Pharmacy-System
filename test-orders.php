<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/auth.php';
requireAuth();

$db = getDB();

$search = 'test';
$status_filter = '';
$priority_filter = '';
$date_from = '';
$date_to = '';

$sql = "SELECT o.*, os.status_name, os.status_color, os.is_final, u.full_name as creator_name, updater.full_name as updater_name 
        FROM orders o 
        JOIN order_statuses os ON o.status_id = os.id 
        JOIN users u ON o.created_by = u.id 
        LEFT JOIN users updater ON o.updated_by = updater.id 
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND o.status_id = ?";
    $params[] = $status_filter;
}
if ($priority_filter) {
    $sql .= " AND o.priority = ?";
    $params[] = $priority_filter;
}
if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($date_from) {
    $sql .= " AND o.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $sql .= " AND o.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$sql .= " ORDER BY o.created_at DESC";

echo "SQL: $sql<br>";
echo "Params: ";
print_r($params);
echo "<br>Count ?: " . substr_count($sql, '?') . "<br>";
echo "Count params: " . count($params) . "<br>";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo "SUCCESS!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}