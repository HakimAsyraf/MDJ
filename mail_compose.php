<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$uid = (int)($me['id'] ?? 0);

$error = '';
$success = '';

$departments = function_exists('list_departments_from_users') ? list_departments_from_users($pdo) : [];
$users = function_exists('list_active_users') ? list_active_users($pdo) : [];

$subject = '';
$body = '';
$selectedDept = 'ALL';
$selectedRecipients = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // If you use csrf everywhere, enable this:
  if (function_exists('csrf_verify')) csrf_verify();

  $selectedDept = (string)($_POST['dept'] ?? 'ALL');
  $subject = trim((string)($_POST['subject'] ?? ''));
  $body = trim((string)($_POST['body'] ?? ''));
  $selectedRecipients = $_POST['recipients'] ?? [];
  if (!is_array($selectedRecipients)) $selectedRecipients = [];

  // sanitize recipients to int
  $recipients = [];
  foreach ($selectedRecipients as $rid) {
    $rid = (int)$rid;
    if ($rid > 0 && $rid !== $uid) $recipients[] = $rid;
  }
  $recipients = array_values(array_unique($recipients));

  if ($uid <= 0) {
    $error = 'Sesi tidak sah. Sila login semula.';
  } elseif ($body === '') {
    $error = 'Mesej wajib diisi.';
  } elseif (!$recipients) {
    $error = 'Sila pilih sekurang-kurangnya seorang penerima.';
  } else {
    try {
      $pdo->beginTransaction();

      // Insert message
      $st = $pdo->prepare("INSERT INTO mail_messages(sender_id, subject, body) VALUES(?,?,?)");
      $st->execute([$uid, ($subject === '' ? null : $subject), $body]);
      $msgId = (int)$pdo->lastInsertId();

      // Insert recipients
      $st2 = $pdo->prepare("INSERT INTO mail_recipients(message_id, recipient_id) VALUES(?,?)");
      foreach ($recipients as $rid) {
        $st2->execute([$msgId, $rid]);
      }

      $pdo->commit();

      $success = 'Mail berjaya dihantar.';
      $subject = '';
      $body = '';
      $selectedRecipients = [];
      $selectedDept = 'ALL';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = 'Gagal hantar mail. Sila cuba semula.';
    }
  }
}

?>
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h1 class="mb-1">Tulis Mail</h1>
    <div class="text-muted">Hantar mesej kepada pengguna lain dalam sistem.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/mail_inbox.php"><i class="bi bi-inbox me-1"></i>Inbox</a>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/mail_sent.php"><i class="bi bi-send me-1"></i>Sent</a>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="post" class="vstack gap-3">
      <?= function_exists('csrf_field') ? csrf_field() : '' ?>

      <div class="row g-3">
        <!-- Department filter -->
        <div class="col-lg-4">
          <label class="form-label fw-semibold">Pilih Jabatan</label>
          <select class="form-select" name="dept" id="deptSelect">
            <option value="ALL" <?= ($selectedDept==='ALL'?'selected':'') ?>>Semua Jabatan</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= h($d) ?>" <?= ($selectedDept===$d?'selected':'') ?>><?= h($d) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="small text-muted mt-2">Pilih jabatan untuk tapis senarai penerima.</div>
        </div>

        <!-- Recipients -->
        <div class="col-lg-8">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label fw-semibold mb-0">Kepada</label>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="clearBtn">Kosongkan pilihan</button>
          </div>

          <select class="form-select mt-2" name="recipients[]" id="recipientSelect" size="10" multiple>
            <?php foreach ($users as $u): ?>
              <?php
                $rid = (int)$u['id'];
                $dept = (string)($u['department'] ?? '');
                $labelName = (string)($u['full_name'] ?? '');
                if ($labelName === '') $labelName = (string)($u['username'] ?? ('User '.$rid));
                $isSel = in_array($rid, array_map('intval', $selectedRecipients), true);
              ?>
              <option
                value="<?= (int)$rid ?>"
                data-dept="<?= h($dept) ?>"
                <?= $isSel ? 'selected' : '' ?>
              >
                <?= h($labelName) ?> (<?= h((string)($u['username'] ?? '')) ?>) - <?= h($dept) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <div class="small text-muted mt-2">
            Tip: Tekan <b>CTRL</b> (Windows) untuk pilih ramai penerima.
            <span id="countInfo"></span>
          </div>
        </div>
      </div>

      <div>
        <label class="form-label fw-semibold">Subjek (optional)</label>
        <input class="form-control" type="text" name="subject" value="<?= h($subject) ?>" placeholder="Contoh: Makluman / Tindakan segera">
      </div>

      <div>
        <label class="form-label fw-semibold">Mesej</label>
        <textarea class="form-control" name="body" rows="6" placeholder="Tulis mesej di sini..." required><?= h($body) ?></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Hantar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const deptSelect = document.getElementById('deptSelect');
  const recipientSelect = document.getElementById('recipientSelect');
  const clearBtn = document.getElementById('clearBtn');
  const countInfo = document.getElementById('countInfo');

  function applyFilter(){
    const dept = deptSelect.value;
    let shown = 0;
    for (const opt of recipientSelect.options){
      const oDept = opt.getAttribute('data-dept') || '';
      const show = (dept === 'ALL') || (oDept === dept);
      opt.hidden = !show;
      if (show) shown++;
    }
    updateCount(shown);
  }

  function updateCount(shown){
    const selected = [...recipientSelect.options].filter(o => o.selected).length;
    countInfo.textContent = ` (Dipaparkan: ${shown} | Dipilih: ${selected})`;
  }

  deptSelect.addEventListener('change', applyFilter);

  recipientSelect.addEventListener('change', () => {
    const shown = [...recipientSelect.options].filter(o => !o.hidden).length;
    updateCount(shown);
  });

  clearBtn.addEventListener('click', () => {
    for (const opt of recipientSelect.options) opt.selected = false;
    const shown = [...recipientSelect.options].filter(o => !o.hidden).length;
    updateCount(shown);
  });

  applyFilter();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
