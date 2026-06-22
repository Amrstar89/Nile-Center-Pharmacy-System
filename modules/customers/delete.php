<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$customer_id = intval($_GET['id'] ?? 0);
if ($customer_id === 0) {
    redirect('index.php');
}

// Get customer name for log
$stmt = $db->prepare("SELECT customer_name FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect('index.php');
}

try {
    $db->beginTransaction();

    // Delete related records (cascade will handle some, but let's be explicit)
    $db->prepare("DELETE FROM customer_phones WHERE customer_id = ?")->execute([$customer_id]);
    $db->prepare("DELETE FROM customer_addresses WHERE customer_id = ?")->execute([$customer_id]);
    $db->prepare("DELETE FROM company_employees WHERE customer_id = ?")->execute([$customer_id]);

    // Delete customer
    $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$customer_id]);

    // Log activity
    logActivity('delete', 'customers', $customer_id, ['customer_name' => $customer['customer_name']]);

    $db->commit();

    // Redirect with success message
    header("Location: index.php?msg=deleted");
    exit;

} catch (Exception $e) {
    $db->rollBack();
    // Redirect with error
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}