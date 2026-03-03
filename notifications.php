<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me  = current_user();
$uid = (int)($me['id'] ?? 0);
if ($uid <= 0) { redirect('/login.php'); }

// Safety: kalau helper belum ditambah dalam includes/config.php, define di sini supaya tak fatal
if (!function_exists('notifications_mark_read')) {
  function notifications_mark_read(PDO $pdo, int $userId, int $notifId): void {
    if ($userId <= 0 || $notifId <= 0) return;

    $hasReadAt = function_exists('db_column_exists') ? db_column_exists($pdo, 'notifications', 'read_at') : false;
    $hasUpdatedAt = function_exists('db_column_exists') ? db_column_exists($pdo, 'notifications', 'updated_at') : false;

    $set = 'is_read = 1';
    if ($hasReadAt) $set .= ', read_at = NOW()';
    if ($hasUpdatedAt) $set .= ', updated_at = NOW()';

    try {
      $st = $pdo->prepare("UPDATE notifications SET {$set} WHERE id = ? AND user_id = ? LIMIT 1");
      $st->execute([$notifId, $userId]);
    } catch (Throwable $e) {
      // ignore
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $nid = (int)($_POST['notif_id'] ?? 0);
  if ($nid > 0) {
    notifications_mark_read($pdo, $uid, $nid);
    flash_set('success', 'Notifikasi ditanda sebagai dibaca.');
    redirect('/notifications.php');
  }
}

$st = $pdo->prepare("
  SELECT id, type, title, body, link_url, is_read, created_at
  FROM notifications
  WHERE user_id=?
  ORDER BY created_at DESC
  LIMIT 300
");
$st->execute([$uid]);
$rows = $st->fetchAll() ?: [];

$fl = flash_get_all();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-1">Notifikasi</h3>
    <div class="text-muted">Senarai notifikasi dalam sistem.</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<?php foreach ($fl as $f): ?>
  <div class="alert alert-<?= h((string)($f['type'] ?? 'secondary')) ?>"><?= h((string)($f['message'] ?? '')) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Notifikasi</th>
            <th style="width: 20%;">Tarikh</th>
            <th style="width: 10%;">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="3" class="text-center text-muted py-4">Tiada notifikasi.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $n): ?>
            <?php
              $isUnread = ((int)($n['is_read'] ?? 0) === 0);
              $link = (string)($n['link_url'] ?? '');
              $href = $link ? (BASE_URL . $link) : '#';
            ?>
            <tr class="<?= $isUnread ? 'table-warning' : '' ?>">
              <td>
                <div class="<?= $isUnread ? 'fw-bold' : 'fw-semibold' ?>">
                  <a href="<?= h($href) ?>"><?= h((string)($n['title'] ?? 'Notifikasi')) ?></a>
                </div>
                <?php if (!empty($n['body'])): ?>
                  <div class="text-muted small"><?= h((string)$n['body']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-muted"><?= h((string)($n['created_at'] ?? '')) ?></td>
              <td>
                <?php if ($isUnread): ?>
                  <form method="post" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="notif_id" value="<?= (int)($n['id'] ?? 0) ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Dibaca</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
