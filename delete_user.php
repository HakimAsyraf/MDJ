<?php
require_once __DIR__ . '/includes/auth_check.php';
if (!is_admin()) { http_response_code(403); exit('Forbidden'); }

$userId = (int)($_GET['id'] ?? 0);
$myId   = (int)($_SESSION['user']['id'] ?? 0);

$returnTo = (string)($_GET['return_to'] ?? (BASE_URL . '/admin.php'));
if ($returnTo === '') $returnTo = BASE_URL . '/admin.php';

if ($userId <= 0) {
  header("Location: " . $returnTo);
  exit;
}

// Block delete diri sendiri
if ($userId === $myId) {
  header("Location: " . $returnTo);
  exit;
}

try {
  // Pastikan user wujud
  $st = $pdo->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
  $st->execute([$userId]);
  $exists = $st->fetchColumn();

  if (!$exists) {
    header("Location: " . $returnTo);
    exit;
  }

  $pdo->beginTransaction();

  // Delete settings jika table wujud
  try {
    $pdo->query("SELECT 1 FROM user_settings LIMIT 1");
    $st = $pdo->prepare("DELETE FROM user_settings WHERE user_id=?");
    $st->execute([$userId]);
  } catch (Throwable $e) {
    // ignore
  }

  // OPTIONAL cleanup: jika ada table lain yang reference user_id, letak sini bila perlu.
  // Contoh (jika anda ada notifications):
  // try { $pdo->query("SELECT 1 FROM notifications LIMIT 1"); $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$userId]); } catch(Throwable $e) {}

  // Delete user
  $st = $pdo->prepare("DELETE FROM users WHERE id=? LIMIT 1");
  $st->execute([$userId]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // untuk production: elak papar error detail
}

header("Location: " . $returnTo);
exit;
