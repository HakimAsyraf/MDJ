<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$uid = (int)($me['id'] ?? 0);

$errors = [];
$subject = '';
$body = '';
$selected = [];

$replyTo = (int)($_GET['reply_to'] ?? 0);
if ($replyTo > 0) {
  // Prefill reply
  $st = $pdo->prepare("SELECT m.*, u.id AS sender_id, u.username, u.full_name
                       FROM mail_messages m
                       JOIN users u ON u.id = m.sender_id
                       WHERE m.id=? LIMIT 1");
  $st->execute([$replyTo]);
  $m = $st->fetch();
  if ($m) {
    $subject = trim((string)($m['subject'] ?? ''));
    if ($subject === '') $subject = 'Re: ';
    if (!str_starts_with(strtolower($subject), 're:')) $subject = 'Re: ' . $subject;

    $senderId = (int)($m['sender_id'] ?? 0);
    if ($senderId > 0 && $senderId !== $uid) $selected = [$senderId];

    $senderName = trim((string)($m['full_name'] ?? ''));
    if ($senderName === '') $senderName = (string)($m['username'] ?? 'user');

    $body = "\n\n--- Mesej asal (" . (string)($m['created_at'] ?? '') . ") dari {$senderName} ---\n" . (string)($m['body'] ?? '');
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $selected = array_map('intval', (array)($_POST['recipients'] ?? []));
  $selected = array_values(array_filter($selected, fn($id) => $id > 0 && $id !== $uid));

  $subject = trim((string)($_POST['subject'] ?? ''));
  $body = trim((string)($_POST['body'] ?? ''));

  if (!$selected) $errors[] = 'Sila pilih penerima.';
  if ($body === '') $errors[] = 'Mesej tidak boleh kosong.';

  if (!$errors) {
    try {
      $msgId = mail_send($pdo, $uid, $selected, $subject, $body);
      flash_set('success', 'Mesej berjaya dihantar.');
      redirect('/mail_view.php?id=' . $msgId);
    } catch (Throwable $e) {
      $errors[] = 'Gagal hantar mesej.';
    }
  }
}

$users = list_active_users($pdo, $uid);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-1">Tulis Mail</h3>
    <div class="text-muted">Hantar mesej kepada pengguna lain dalam sistem.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/mail_inbox.php"><i class="bi bi-inbox"></i> Inbox</a>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/mail_sent.php"><i class="bi bi-send"></i> Sent</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1">Sila semak:</div>
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="post" class="vstack gap-3">
      <?= csrf_field() ?>

      <div>
        <label class="form-label fw-semibold">Kepada</label>
        <select class="form-select" name="recipients[]" multiple size="8" required>
          <?php foreach ($users as $u): ?>
            <?php
              $id = (int)($u['id'] ?? 0);
              $name = trim((string)($u['full_name'] ?? ''));
              if ($name === '') $name = (string)($u['username'] ?? 'user');
              $dept = (string)($u['department'] ?? '');
              $label = $name . ' (' . (string)($u['username'] ?? '') . ') - ' . $dept;
            ?>
            <option value="<?= $id ?>" <?= in_array($id, $selected, true) ? 'selected' : '' ?>>
              <?= h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small text-muted mt-1">Tip: Tekan CTRL (Windows) untuk pilih ramai penerima.</div>
      </div>

      <div>
        <label class="form-label fw-semibold">Subjek (optional)</label>
        <input class="form-control" type="text" name="subject" value="<?= h($subject) ?>" maxlength="255" placeholder="Contoh: Makluman / Tindakan segera">
      </div>

      <div>
        <label class="form-label fw-semibold">Mesej</label>
        <textarea class="form-control" name="body" rows="8" required><?= h($body) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-send"></i> Hantar</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/mail_inbox.php">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
