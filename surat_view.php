<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

if (!function_exists('surat_tables_ready') || !surat_tables_ready($pdo)) {
  echo '<div class="alert alert-warning">Table surat belum siap. Sila paste SQL patch surat dahulu.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo '<div class="alert alert-danger">ID tidak sah.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

if (!surat_can_view($pdo, $id)) {
  http_response_code(403);
  echo '<div class="alert alert-danger">Akses ditolak.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

$st = $pdo->prepare("SELECT * FROM surat_menyurat WHERE id=? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { echo '<div class="alert alert-danger">Surat tidak dijumpai.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

// recipients
$recipients = [];
$stR = $pdo->prepare("SELECT * FROM surat_recipients WHERE surat_id=? ORDER BY id DESC");
$stR->execute([$id]);
$recipients = $stR->fetchAll() ?: [];

// attachments
$atts = [];
if (db_table_exists($pdo, 'surat_attachments')) {
  $stA = $pdo->prepare("SELECT * FROM surat_attachments WHERE surat_id=? ORDER BY id DESC");
  $stA->execute([$id]);
  $atts = $stA->fetchAll() ?: [];
}

$me = current_user();
$myDept = trim((string)($me['department'] ?? ''));
$isAdminOrPent = can_access_surat_module();

// figure which recipient row to show as "current" for non-admin
$myRec = null;
if (!$isAdminOrPent) {
  foreach ($recipients as $r) {
    if (trim((string)($r['recipient_department'] ?? '')) === $myDept) { $myRec = $r; break; }
  }
}

// choose status/catatan display:
$showStatus = (string)($myRec['status'] ?? $row['status'] ?? '');
$showCatatan = (string)($myRec['comment'] ?? $row['catatan'] ?? '');
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="h3 mb-0">Butiran Surat</div>
    <div class="text-muted small">Maklumat surat & lampiran.</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($isAdminOrPent): ?>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_masuk.php"><i class="bi bi-arrow-left"></i> Kembali</a>
    <?php else: ?>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_menyurat.php"><i class="bi bi-arrow-left"></i> Kembali</a>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/surat_respond.php?id=<?= (int)$id ?>"><i class="bi bi-chat-dots"></i> Respon</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-4">
      <!-- Row 1 -->
      <div class="col-12 col-md-4">
        <div class="text-muted small">Tarikh Terima</div>
        <div class="fw-semibold fs-6"><?= h((string)($row['tarikh_penerimaan'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Tarikh Surat</div>
        <div class="fw-semibold fs-6"><?= h((string)($row['tarikh_surat'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">No. Fail Kementerian</div>
        <div class="fw-semibold fs-6"><?= h((string)($row['no_fail_kementerian'] ?? '')) ?></div>
      </div>

      <!-- Row 2 -->
      <div class="col-12 col-md-6">
        <div class="text-muted small">Daripada Siapa</div>
        <div class="fw-semibold"><?= h((string)($row['daripada_siapa'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-6">
        <div class="text-muted small">Dikirimkan Kepada</div>
        <div class="fw-semibold"><?= h((string)($row['dikirim_kepada'] ?? '')) ?></div>
      </div>

      <!-- Perkara -->
      <div class="col-12">
        <div class="text-muted small">Perkara</div>
        <div class="fw-semibold"><?= nl2br(h((string)($row['perkara'] ?? ''))) ?></div>
      </div>

      <!-- Row 3 -->
      <div class="col-12 col-md-4">
        <div class="text-muted small">Tindakan</div>
        <div class="fw-semibold"><?= h((string)($row['tindakan'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Tempoh Menjawab</div>
        <div class="fw-semibold"><?= h((string)($row['tempoh_menjawab'] ?? '')) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Status (Semasa)</div>
        <div class="fw-semibold"><?= h($showStatus) ?></div>
      </div>

      <div class="col-12 col-md-4">
        <div class="text-muted small">Tarikh Jawab</div>
        <div class="fw-semibold"><?= h((string)($row['tarikh_dijawab'] ?? '')) ?></div>
      </div>

      <div class="col-12 col-md-8">
        <div class="text-muted small">Catatan (Semasa)</div>
        <div class="fw-semibold"><?= h($showCatatan) ?></div>
      </div>

      <?php if ($isAdminOrPent && $recipients): ?>
        <div class="col-12">
          <hr>
          <div class="fw-semibold mb-2">Status Jabatan (Ringkasan)</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Jabatan</th>
                  <th>Status</th>
                  <th>Catatan</th>
                  <th>Due</th>
                  <th>Respon</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recipients as $r): ?>
                  <tr>
                    <td class="fw-semibold"><?= h((string)($r['recipient_department'] ?? '')) ?></td>
                    <td><?= h((string)($r['status'] ?? '')) ?></td>
                    <td><?= h((string)($r['comment'] ?? '')) ?></td>
                    <td><?= h((string)($r['due_date'] ?? '')) ?></td>
                    <td><?= h((string)($r['responded_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="fw-semibold mb-2">Lampiran</div>
    <?php if (!$atts): ?>
      <div class="text-muted">Tiada lampiran.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Lampiran</th>
              <th class="text-end">Tindakan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($atts as $a): ?>
              <tr>
                <td class="fw-semibold"><?= h((string)($a['file_name'] ?? 'Lampiran')) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/surat_download.php?id=<?= (int)$a['id'] ?>" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> Buka
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>