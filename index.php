<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/estock-bridge.php';

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    session_destroy();
    redirect(APP_URL . '/index.php');
}

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(APP_URL . '/modules/dashboard/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Try ESTOCK login first
    if (isESTOCKAvailable() && loginWithESTOCK($username, $password)) {
        $redirect = $_SESSION['redirect_after_login'] ?? APP_URL . '/modules/dashboard/';
        unset($_SESSION['redirect_after_login']);
        redirect($redirect);
    }
    // Fallback to MySQL login
    elseif (login($username, $password)) {
        $redirect = $_SESSION['redirect_after_login'] ?? APP_URL . '/modules/dashboard/';
        unset($_SESSION['redirect_after_login']);
        redirect($redirect);
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
        }
        .login-title {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-weight: 700;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #e0e0e0;
            border-left: none;
            border-radius: 10px 0 0 10px;
        }
        .form-control.input-with-icon {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-capsule"></i>
        </div>
        <h3 class="login-title"><?= APP_NAME ?></h3>

        <?php if ($error): ?>
            <?= showAlert($error, 'danger') ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control input-with-icon" name="username" 
                           placeholder="اسم المستخدم" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control input-with-icon" name="password" 
                           placeholder="كلمة المرور" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-left me-2"></i> تسجيل الدخول
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">الإصدار <?= APP_VERSION ?></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
