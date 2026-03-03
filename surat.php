<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$dept = strtolower(trim((string)($me['department'] ?? '')));
$isPentadbiran = ($dept === 'pentadbiran');

if (!is_admin() && !$isPentadbiran) {
  http_response_code(403);
  echo '<div class="alert alert-danger">Akses ditolak. Modul surat untuk Admin & Jabatan Pentadbiran sahaja.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

if (!function_exists('db_table_exists') || !db_table_exists($pdo, 'surat_menyurat')) {
  echo '<div class="alert alert-warning">Table surat_menyurat belum wujud. Sila paste SQL table surat dahulu.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT * FROM surat_menyurat";
$params = [];

if ($q !== '') {
  $sql .= " WHERE (no_fail_kementerian LIKE ? OR daripada_siapa LIKE ? OR perkara LIKE ? OR dikirim_kepada LIKE ? OR status LIKE ?)";
  $like = "%{$q}%";
  $params = [$like,$like,$like,$like,$like];
}
$sql .= " ORDER BY id DESC";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
}

function due_text(?string $tarikhTerima, $tempohHari): string {
  $tempohHari = (int)$tempohHari;
  if (!$tarikhTerima || $tempohHari <= 0) return '-';
  $base = strtotime($tarikhTerima);
  if ($base === false) return '-';
  $due = $base + ($tempohHari * 86400);
  $left = (int)ceil(($due - time()) / 86400);
  $dueDate = date('Y-m-d', $due);
  if ($left < 0) return $dueDate . ' (lewat ' . abs($left) . 'h)';
  return $dueDate . ' (' . $left . 'h)';
}
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="h3 mb-0">Daftar Surat Menyurat</div>
    <div class="text-muted small">Surat Masuk (untuk tindakan & respon).</div>
  </div>

  <div class="d-flex gap-2">
    <form class="d-flex gap-2" method="get" action="<?= BASE_URL ?>/surat.php">
      <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Carian...">
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Carian</button>
    </form>

    <a class="btn btn-primary" href="<?= BASE_URL ?>/surat_add.php"><i class="bi bi-plus-circle"></i> Surat Baru</a>
  </div>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success">Surat berjaya disimpan.</div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Tarikh Terima</th>
            <th>No. Fail Kementerian</th>
            <th>Daripada</th>
            <th>Perkara</th>
            <th>Jabatan</th>
            <th>Status</th>
            <th>Due</th>
            <th class="text-end">Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-muted">Tiada rekod.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $id = (int)$r['id'];
                $status = (string)($r['status'] ?? '');
                $badge = 'secondary';
                if ($status === 'Diterima') $badge = 'warning';
                if ($status === 'Diproses') $badge = 'primary';
                if ($status === 'Selesai') $badge = 'success';
              ?>
              <tr>
                <td><?= h((string)($r['tarikh_penerimaan'] ?? '')) ?></td>
                <td><?= h((string)($r['no_fail_kementerian'] ?? '')) ?></td>
                <td><?= h((string)($r['daripada_siapa'] ?? '')) ?></td>
                <td><?= h(mb_strimwidth((string)($r['perkara'] ?? ''), 0, 40, '...')) ?></td>
                <td><?= h((string)($r['dikirim_kepada'] ?? '')) ?></td>
                <td><span class="badge bg-<?= h($badge) ?>"><?= h($status ?: '-') ?></span></td>
                <td><?= h(due_text((string)($r['tarikh_penerimaan'] ?? ''), $r['tempoh_menjawab'] ?? '')) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= $id ?>">
                    <i class="bi bi-eye"></i> Lihat
                  </a>
                  <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/surat_respond.php?id=<?= $id ?>">
                    <i class="bi bi-chat-dots"></i> Respon
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
