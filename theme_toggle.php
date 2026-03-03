<?php
require_once __DIR__ . '/includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}
csrf_verify();

$theme = (string)($_POST['theme'] ?? 'light');
if (!in_array($theme, ['light','dark'], true)) $theme = 'light';

$uid = (int)(current_user()['id'] ?? 0);
ensure_user_settings($pdo, $uid);
$st = $pdo->prepare('UPDATE user_settings SET theme=? WHERE user_id=?');
$st->execute([$theme, $uid]);

$_SESSION['theme'] = $theme;
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'theme' => $theme]);
