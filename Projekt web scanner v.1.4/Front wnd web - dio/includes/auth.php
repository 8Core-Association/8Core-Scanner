<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function current_user() {
    return isset($_SESSION['scanner_user']) ? $_SESSION['scanner_user'] : null;
}

function is_logged_in() {
    return current_user() !== null;
}

function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function login_user(PDO $pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT * FROM scanner_users WHERE username = ? AND active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['scanner_user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'account_name' => $user['account_name'],
    ];

    $upd = $pdo->prepare("UPDATE scanner_users SET last_login = NOW() WHERE id = ?");
    $upd->execute([$user['id']]);

    return true;
}

function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function can_access_finding(PDO $pdo, $findingId) {
    if (is_admin()) return true;

    $u = current_user();
    if (!$u) return false;

    $stmt = $pdo->prepare("SELECT account_name, owner_name FROM findings WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$findingId]);
    $f = $stmt->fetch();

    if (!$f) return false;

    $acc = $f['account_name'] ?: $f['owner_name'];
    return $acc === $u['account_name'];
}
