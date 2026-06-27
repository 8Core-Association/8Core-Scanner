<?php
require __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

$allowed = ['ignore', 'checked', 'quarantine_requested', 'delete_requested', 'new'];

if ($id < 1 || !in_array($action, $allowed, true)) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

if (!can_access_finding($pdo, $id)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$user = current_user();

$stmt = $pdo->prepare("
    UPDATE findings
    SET action_status = ?, action_note = ?, action_at = NOW(), action_by = ?
    WHERE id = ?
");
$stmt->execute([$action, $note, $user['username'], $id]);

$stmt = $pdo->prepare("
    INSERT INTO scanner_actions (finding_id, action, note, created_at, created_by)
    VALUES (?, ?, ?, NOW(), ?)
");
$stmt->execute([$id, $action, $note, $user['username']]);

$back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header('Location: ' . $back);
exit;
