<?php
// Placeholder - نقطة البيع POS
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();
$page_title = 'نقطة البيع (POS)';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>:root{--primary:#667eea;--secondary:#764ba2}body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}.main-content{margin-right:260px;padding:20px}.topbar{background:white;border-radius:15px;padding:15px 25px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center}.content-card{background:white;border-radius:15px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.05)}@media(max-width:768px){.main-content{margin-right:0}}</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
<div class="topbar"><div><h5 class="mb-0"><i class="bi bi-cart-check"></i> <?= $page_title ?></h5></div></div>
<div class="content-card text-center py-5">
<i class="bi bi-cone-striped text-warning" style="font-size:64px"></i>
<h4 class="mt-3">قيد التطوير</h4>
<p class="text-muted">نقطة البيع (POS) قيد التطوير. استخدم <a href="<?= APP_URL ?>/modules/customer-requests/new-order.php">طلبات العملاء</a> مؤقتاً.</p>
<a href="<?= APP_URL ?>/modules/sales/" class="btn btn-primary"><i class="bi bi-arrow-right"></i> العودة للمبيعات</a>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
