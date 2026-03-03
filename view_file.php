<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$fileId = (int)($_GET['id'] ?? 0);
if ($fileId <= 0) {
  echo '<div class="alert alert-danger">Rekod fail tidak sah.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$st = $pdo->prepare("SELECT * FROM files WHERE id=? LIMIT 1");
$st->execute([$fileId]);
$file = $st->fetch();

if (!$file) {
  echo '<div class="alert alert-danger">Fail tidak dijumpai.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

// ✅ CENTRAL ACCESS GATE
if (!can_access_file($pdo, $fileId)) {
  echo '<div class="alert alert-danger">Akses ditolak. Anda hanya boleh lihat fail jabatan anda.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

// documents
$docs = [];
try {
  $st2 = $pdo->prepare("SELECT * FROM documents WHERE file_id=? ORDER BY id DESC");
  $st2->execute([$fileId]);
  $docs = $st2->fetchAll();
} catch (Throwable $e) {
  $docs = [];
}

$ownerDept = (string)($file['owner_department'] ?? $file['department_owner'] ?? $file['department'] ?? '');
$createdAt = (string)($file['created_at'] ?? $file['created_time'] ?? $file['createdOn'] ?? '');
$createdByName = (string)($file['created_by_name'] ?? '');

?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1">Butiran Fail</h1>
    <div class="text-muted">Maklumat fail & dokumen berkaitan.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard.php">
      <i class="bi bi-arrow-left"></i> Kembali
    </a>

    <?php if (is_admin()): ?>
      <a class="btn btn-outline-danger"
         href="<?= BASE_URL ?>/delete_file.php?id=<?= (int)$fileId ?>"
         onclick="return confirm('Padam fail ini? Semua dokumen berkaitan akan dipadam.');">
        <i class="bi bi-trash"></i> Padam Fail
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <div class="text-muted small">Jabatan (Pemilik Fail)</div>
        <div class="fw-semibold"><?= h($ownerDept) ?></div>
      </div>

      <div class="col-md-2">
        <div class="text-muted small">TAHUN</div>
        <div class="fw-semibold"><?= h((string)($file['tahun'] ?? $file['year'] ?? '')) ?></div>
      </div>

      <div class="col-md-2">
        <div class="text-muted small">NO. KOTAK FAIL</div>
        <div class="fw-semibold"><?= h((string)($file['no_kotak_fail'] ?? $file['box_no'] ?? '')) ?></div>
      </div>

      <div class="col-md-3">
        <div class="text-muted small">NO. FAIL PERMOHONAN</div>
        <div class="fw-semibold"><?= h((string)($file['no_fail_permohonan'] ?? $file['application_no'] ?? '')) ?></div>
      </div>

      <div class="col-md-2">
        <div class="text-muted small">TARIKH</div>
        <div class="fw-semibold">
          <?php
            $tarikh = (string)($file['tarikh'] ?? $file['tarikh_permohonan_masuk'] ?? $file['application_date'] ?? '');
            echo h($tarikh);
          ?>
        </div>
      </div>

      <div class="col-md-3">
        <div class="text-muted small">LOT / PT</div>
        <div class="fw-semibold"><?= h((string)($file['lot_pt'] ?? $file['lot'] ?? '')) ?></div>
      </div>

      <div class="col-md-3">
        <div class="text-muted small">MUKIM</div>
        <div class="fw-semibold"><?= h((string)($file['mukim'] ?? '')) ?></div>
      </div>

      <div class="col-md-2">
        <div class="text-muted small">ARAS</div>
        <div class="fw-semibold"><?= h((string)($file['aras'] ?? '')) ?></div>
      </div>

      <div class="col-md-2">
        <div class="text-muted small">KABINET</div>
        <div class="fw-semibold"><?= h((string)($file['kabinet'] ?? '')) ?></div>
      </div>

      <div class="col-md-12">
        <hr class="my-2">
        <div class="text-muted small">
          Dicipta oleh: <span class="fw-semibold"><?= h($createdByName ?: 'System Administrator') ?></span>
          <?= $createdAt ? ' · Tarikh: '.h($createdAt) : '' ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
      <div>
        <h2 class="h5 mb-0">Dokumen</h2>
        <div class="text-muted small">Word/PDF yang berkaitan dengan fail ini.</div>
      </div>
      <a class="btn btn-primary" href="<?= BASE_URL ?>/add_document.php?file_id=<?= (int)$fileId ?>">
        <i class="bi bi-plus-circle"></i> Tambah Dokumen
      </a>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Dokumen</th>
            <th>Jenis</th>
            <th>Tarikh Upload</th>
            <th class="text-end">Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$docs): ?>
            <tr><td colspan="4" class="text-muted">Tiada dokumen.</td></tr>
          <?php else: ?>
            <?php foreach ($docs as $d): ?>
              <?php
                $name = (string)($d['document_name'] ?? $d['name'] ?? '');
                $path = (string)($d['document_path'] ?? $d['path'] ?? '');
                $uploaded = (string)($d['uploaded_at'] ?? $d['created_at'] ?? $d['createdAt'] ?? '');
                $ext = strtolower(pathinfo($name ?: $path, PATHINFO_EXTENSION));
                $jenis = strtoupper($ext ?: (string)($d['document_type'] ?? ''));
              ?>
              <tr>
                <td class="fw-semibold"><?= h($name ?: basename($path)) ?></td>
                <td>
                  <span class="badge bg-secondary"><?= h($jenis ?: 'DOC') ?></span>
                </td>
                <td><?= h($uploaded) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/download_document.php?id=<?= (int)$d['id'] ?>" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> Buka
                  </a>

                  <?php if (is_admin()): ?>
                    <a class="btn btn-sm btn-outline-danger"
                       href="<?= BASE_URL ?>/delete_document.php?id=<?= (int)$d['id'] ?>&file_id=<?= (int)$fileId ?>"
                       onclick="return confirm('Padam dokumen ini?');">
                      <i class="bi bi-trash"></i>
                    </a>
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
