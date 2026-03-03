<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$dept = trim((string)($me['department'] ?? ''));

// Admin & Pentadbiran guna modul "Daftar Surat Menyurat" (surat_masuk.php)
if (function_exists('can_access_surat_module') && can_access_surat_module()) {
  header('Location: ' . BASE_URL . '/surat_masuk.php');
  exit;
}

if ($dept === '') {
  echo '<div class="alert alert-danger">Jabatan anda belum ditetapkan. Sila hubungi admin.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

// Pastikan table wujud
if (function_exists('db_table_exists')) {
  if (!db_table_exists($pdo, 'surat_menyurat') || !db_table_exists($pdo, 'surat_recipients')) {
    echo '<div class="alert alert-warning">Table surat belum lengkap. Sila paste SQL surat_menyurat & surat_recipients dahulu.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
  }
}

$q = trim((string)($_GET['q'] ?? ''));

// Helper: due label
function surat_due_label(?string $due): string {
  $d = trim((string)$due);
  if ($d === '') return '-';
  $ts = strtotime($d);
  if ($ts === false) return $d;

  $today = strtotime(date('Y-m-d'));
  $diffDays = (int)round(($ts - $today) / 86400);

  if ($diffDays === 0) return $d . ' (Hari ini)';
  if ($diffDays > 0) return $d . ' (' . $diffDays . 'h)';
  return $d . ' (Lewat ' . abs($diffDays) . 'h)';
}

function surat_status_badge(string $status): string {
  $s = trim($status);
  $cls = 'text-bg-secondary';
  if (strcasecmp($s, 'Diterima') === 0) $cls = 'text-bg-warning';
  if (strcasecmp($s, 'Diproses') === 0) $cls = 'text-bg-primary';
  if (strcasecmp($s, 'Selesai') === 0) $cls = 'text-bg-success';
  return '<span class="badge ' . $cls . '">' . h($s === '' ? '-' : $s) . '</span>';
}

// Query inbox by recipient_department
$sql = "
  SELECT
    s.id,
    s.tarikh_penerimaan,
    s.no_fail_kementerian,
    s.tarikh_surat,
    s.daripada_siapa,
    s.perkara,
    r.recipient_department,
    r.status,
    r.due_date,
    r.responded_at,
    r.updated_at
  FROM surat_recipients r
  INNER JOIN surat_menyurat s ON s.id = r.surat_id
  WHERE r.recipient_department = ?
";
$params = [$dept];

if ($q !== '') {
  $sql .= " AND (
      s.no_fail_kementerian LIKE ?
      OR s.daripada_siapa LIKE ?
      OR s.perkara LIKE ?
    )";
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY s.tarikh_penerimaan DESC, s.id DESC LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll() ?: [];
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="h3 mb-0">Surat Menyurat</div>
    <div class="text-muted small">Inbox surat untuk jabatan: <span class="fw-semibold"><?= h($dept) ?></span></div>
  </div>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2" method="get">
      <input class="form-control" type="search" name="q" value="<?= h($q) ?>" placeholder="Carian...">
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Carian</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Tarikh Terima</th>
            <th>No. Fail Kementerian</th>
            <th>Daripada</th>
            <th>Perkara</th>
            <th style="width:140px;">Status</th>
            <th style="width:180px;">Due</th>
            <th class="text-end" style="width:200px;">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tiada surat untuk jabatan ini.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)($r['tarikh_penerimaan'] ?? '')) ?></td>
              <td class="fw-semibold"><?= h((string)($r['no_fail_kementerian'] ?? '')) ?></td>
              <td><?= h((string)($r['daripada_siapa'] ?? '')) ?></td>
              <td><?= h((string)($r['perkara'] ?? '')) ?></td>
              <td><?= surat_status_badge((string)($r['status'] ?? '')) ?></td>
              <td><?= h(surat_due_label((string)($r['due_date'] ?? ''))) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= (int)$r['id'] ?>">
                  <i class="bi bi-eye"></i> Lihat
                </a>
                <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/surat_respond.php?id=<?= (int)$r['id'] ?>">
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
