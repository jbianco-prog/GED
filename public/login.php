<?php
// public/login.php
require_once __DIR__ . '/../bootstrap.php';

if (Auth::check()) {
    H::redirect(APP_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if (Auth::attempt($email, $password)) {
        H::redirect(APP_URL . '/index.php');
    } else {
        $error = 'Invalid credentials or account disabled.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-card__logo">
            <div style="font-size:36px;margin-bottom:8px">📂</div>
            <div class="login-card__title"><?= APP_NAME ?></div>
            <div class="login-card__sub">Electronic Document Management</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= H::e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?= H::e($_POST['email'] ?? '') ?>"
                       autocomplete="email" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
                Sign in
            </button>
        </form>

        <p class="text-muted text-small" style="text-align:center;margin-top:16px">
            Default admin account : <strong>admin@ged.local</strong> / <strong>password</strong><br>
            <em>Change this password immediately after installation.</em>
        </p>
    </div>
</div>
</body>
</html>
