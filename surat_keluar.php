<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

if (!can_access_surat_module()) {
  echo '<div class="alert alert-danger">Akses ditolak.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$rows = [];
try {
  $st = $pdo->query("
    SELECT s.*,
      (SELECT COUNT(*) FROM surat_recipients r WHERE r.surat_id=s.id) AS jumlah_jabatan
    FROM surat_letters s
    ORDER BY s.id DESC
  ");
  $rows = $st->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1">Daftar Surat Menyurats</h1>
    <div class="text-muted">Surat Keluar (Pentadbiran/Admin).</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_search.php"><i class="bi bi-search"></i> Carian</a>
    <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/surat_masuk.php"><i class="bi bi-inbox"></i> Surat Masuk</a>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/surat_add.php"><i class="bi bi-plus-circle"></i> Surat Baru</a>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tarikh Terima</th>
            <th>No. Fail Kementerian</th>
            <th>Daripada</th>
            <th>Tindakan</th>
            <th>Tempoh</th>
            <th>Jabatan</th>
            <th class="text-end">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-muted">Tiada surat.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $s): ?>
            <tr>
              <td><?= (int)$s['id'] ?></td>
              <td><?= h((string)($s['tarikh_terima'] ?? '')) ?></td>
              <td><?= h((string)($s['no_fail_kementerian'] ?? '')) ?></td>
              <td><?= h((string)($s['daripada_siapa'] ?? '')) ?></td>
              <td><?= h((string)($s['tindakan'] ?? '')) ?></td>
              <td><?= h((string)($s['tempoh_days'] ?? '')) ?></td>
              <td><?= (int)($s['jumlah_jabatan'] ?? 0) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= (int)$s['id'] ?>"><i class="bi bi-eye"></i> Lihat</a>
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
