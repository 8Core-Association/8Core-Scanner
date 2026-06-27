<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';

$error = '';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (login_user($pdo, $username, $password)) {
        header('Location: index.php');
        exit;
    }

    $error = 'Pogrešan username ili lozinka.';
}
?>
<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<title>8Core Scanner Login</title>
<link rel="stylesheet" href="assets/css/scanner.css">
</head>
<body>
<div class="login-wrap">
    <div class="panel">
        <h1>8Core Scanner</h1>
        <p class="small">Prijava u sigurnosni dashboard</p>

        <?php if ($error): ?>
            <div class="notice error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="username" placeholder="Username" autocomplete="username" required>
            <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</div>
</body>
</html>
