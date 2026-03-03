<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

if (!function_exists('surat_tables_ready') || !surat_tables_ready($pdo)) {
  echo '<div class="alert alert-warning">Table surat belum siap. Sila paste SQL patch surat dahulu.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

/**
 * SAFE redirect: elak warning "headers already sent".
 * - Jika headers belum dihantar: guna header(Location)
 * - Jika headers dah dihantar (sebab header.php dah output HTML): guna JS redirect (tiada warning)
 */
if (!function_exists('safe_redirect')) {
  function safe_redirect(string $path): void {
    $url = (defined('BASE_URL') ? BASE_URL : '') . $path;

    if (!headers_sent()) {
      header('Location: ' . $url);
      exit;
    }

    // fallback tanpa warning
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
  }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo '<div class="alert alert-danger">ID tidak sah.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

if (!surat_can_respond($pdo, $id)) {
  http_response_code(403);
  echo '<div class="alert alert-danger">Akses ditolak.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

$st = $pdo->prepare("SELECT * FROM surat_menyurat WHERE id=? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { echo '<div class="alert alert-danger">Surat tidak dijumpai.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

$me = current_user();
$myDept = trim((string)($me['department'] ?? ''));

$errors = [];
$success = '';

$statusOpt = ['Diterima','Diproses','Selesai'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $status = trim((string)($_POST['status'] ?? ''));
  $comment = trim((string)($_POST['comment'] ?? ''));

  if (!in_array($status, $statusOpt, true)) $errors[] = 'Status tidak sah.';
  if ($myDept === '') $errors[] = 'Jabatan pengguna tidak sah.';

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Update recipient row untuk jabatan ini
      $stU = $pdo->prepare("
        UPDATE surat_recipients
        SET status=?, comment=?, responded_at=NOW()
        WHERE surat_id=? AND TRIM(recipient_department)=?
        LIMIT 1
      ");
      $stU->execute([$status, $comment, $id, $myDept]);

      // Pastikan ada row; jika tiada (rare), create
      if ($stU->rowCount() === 0) {
        $stI = $pdo->prepare("
          INSERT INTO surat_recipients(surat_id, recipient_department, status, comment, due_date, created_at, responded_at)
          VALUES(?,?,?,?,NULL,NOW(),NOW())
        ");
        $stI->execute([$id, $myDept, $status, $comment]);
      }

      // Update overall surat status
      if (function_exists('surat_update_overall_status')) {
        surat_update_overall_status($pdo, $id);
      }

      // Notify admin/pentadbiran
      if (function_exists('surat_notify_status_update')) {
        surat_notify_status_update($pdo, $id, $myDept, $status);
      }

      $pdo->commit();

      // ✅ FIX DI SINI: sebelum ni guna header(Location) -> warning.
      safe_redirect('/surat_view.php?id=' . (int)$id);

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'DB error';
    }
  }
}

// get current recipient row to show
$myRec = null;
$stR = $pdo->prepare("SELECT * FROM surat_recipients WHERE surat_id=? AND TRIM(recipient_department)=? LIMIT 1");
$stR->execute([$id, $myDept]);
$myRec = $stR->fetch() ?: null;

$currentStatus = (string)($myRec['status'] ?? 'Diterima');
$currentComment = (string)($myRec['comment'] ?? '');
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="h3 mb-0">Respon Surat</div>
    <div class="text-muted small">Kemas kini status & catatan jabatan.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= (int)$id ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1">Gagal simpan respon.</div>
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h((string)$e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="text-muted small">No. Fail</div>
        <div class="fw-semibold"><?= h((string)($row['no_fail_kementerian'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Daripada</div>
        <div class="fw-semibold"><?= h((string)($row['daripada_siapa'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Kepada</div>
        <div class="fw-semibold"><?= h((string)($row['dikirim_kepada'] ?? '')) ?></div>
      </div>
      <div class="col-12">
        <div class="text-muted small">Perkara</div>
        <div class="fw-semibold"><?= nl2br(h((string)($row['perkara'] ?? ''))) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
          <?php foreach ($statusOpt as $s): ?>
            <option value="<?= h($s) ?>" <?= ($currentStatus === $s ? 'selected' : '') ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Catatan</label>
        <textarea name="comment" class="form-control" rows="4" placeholder="Catatan / tindakan jabatan..."><?= h($currentComment) ?></textarea>
      </div>

      <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan Respon</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= (int)$id ?>">Batal</a>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
