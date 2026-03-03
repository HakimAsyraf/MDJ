<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$uid = (int)($me['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = (string)($_POST['action'] ?? '');
  $msgId = (int)($_POST['message_id'] ?? 0);

  if ($msgId > 0 && $action === 'delete_sent') {
    $st = $pdo->prepare("UPDATE mail_messages SET sender_deleted=1 WHERE id=? AND sender_id=?");
    $st->execute([$msgId, $uid]);
    flash_set('success', 'Mesej dipadam dari Sent.');
    redirect('/mail_sent.php');
  }
}

$st = $pdo->prepare("
  SELECT m.id, m.subject, m.body, m.created_at,
         (SELECT GROUP_CONCAT(u.username SEPARATOR ', ')
          FROM mail_recipients mr
          JOIN users u ON u.id = mr.recipient_id
          WHERE mr.message_id = m.id) AS recipients
  FROM mail_messages m
  WHERE m.sender_id=? AND m.sender_deleted=0
  ORDER BY m.created_at DESC
  LIMIT 200
");
$st->execute([$uid]);
$rows = $st->fetchAll() ?: [];

$fl = flash_get_all();
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-1">Mail - Sent</h3>
    <div class="text-muted">Senarai mesej yang anda hantar.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/mail_inbox.php"><i class="bi bi-inbox"></i> Inbox</a>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/mail_compose.php"><i class="bi bi-pencil-square"></i> Tulis</a>
  </div>
</div>

<?php foreach ($fl as $f): ?>
  <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width: 30%;">Kepada</th>
            <th>Subjek</th>
            <th style="width: 18%;">Tarikh</th>
            <th style="width: 10%;">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Tiada mesej dihantar.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $subj = trim((string)($r['subject'] ?? ''));
              if ($subj === '') $subj = '(Tiada subjek)';
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h((string)($r['recipients'] ?? '')) ?></div>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/mail_view.php?id=<?= (int)$r['id'] ?>"><?= h($subj) ?></a>
                <div class="text-muted small">
                  <?= h(strlen((string)$r['body']) > 90 ? substr((string)$r['body'], 0, 90).'…' : (string)$r['body']) ?>
                </div>
              </td>
              <td class="text-muted"><?= h((string)($r['created_at'] ?? '')) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Padam mesej ini dari Sent?');" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_sent">
                  <input type="hidden" name="message_id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
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
