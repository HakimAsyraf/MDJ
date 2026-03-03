<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

if (!function_exists('can_access_surat_module') || !can_access_surat_module()) {
  http_response_code(403);
  echo '<div class="alert alert-danger">Akses ditolak. Modul ini untuk Admin/Pentadbiran sahaja.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

if (!function_exists('surat_tables_ready') || !surat_tables_ready($pdo)) {
  echo '<div class="alert alert-warning">Table surat belum siap. Sila paste SQL patch surat dahulu.</div>';
  require __DIR__ . '/includes/footer.php';
  exit;
}

$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($q !== '') {
  $where[] = "(s.no_fail_kementerian LIKE ? OR s.daripada_siapa LIKE ? OR s.perkara LIKE ? OR r.recipient_department LIKE ?)";
  $like = "%{$q}%";
  $params = [$like,$like,$like,$like];
}

$sql = "
  SELECT
    r.id AS rid,
    r.surat_id,
    r.recipient_department,
    r.status AS r_status,
    r.comment AS r_comment,
    r.due_date,
    s.tarikh_penerimaan,
    s.no_fail_kementerian,
    s.daripada_siapa,
    s.perkara
  FROM surat_recipients r
  INNER JOIN surat_menyurat s ON s.id = r.surat_id
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY s.id DESC, r.id DESC";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
}

function badge_status(string $s): string {
  $s = trim($s);
  return match ($s) {
    'Selesai' => 'bg-success',
    'Diproses' => 'bg-primary',
    default => 'bg-warning text-dark'
  };
}

function due_label(?string $due): string {
  $d = trim((string)$due);
  if ($d === '') return '-';
  $ts = strtotime($d);
  if ($ts === false) return $d;
  $today = strtotime(date('Y-m-d'));
  $diff = (int)floor(($ts - $today) / 86400);
  if ($diff === 0) return $d . ' (Hari ini)';
  if ($diff > 0) return $d . " ({$diff}h)";
  $late = abs($diff);
  return $d . " (Lewat {$late}h)";
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <div class="h3 mb-0">Daftar Surat Menyurat</div>
    <div class="text-muted small">Surat Masuk (untuk tindakan & respon).</div>
  </div>

  <div class="d-flex gap-2 align-items-center">
    <form class="d-flex gap-2" method="get" action="<?= BASE_URL ?>/surat_masuk.php">
      <input class="form-control" style="min-width:240px" name="q" value="<?= h($q) ?>" placeholder="Carian...">
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Carian</button>
    </form>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/surat_add.php"><i class="bi bi-plus-circle"></i> Surat Baru</a>
  </div>
</div>

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
                $perkara = trim((string)($r['perkara'] ?? ''));
                $perkaraShort = $perkara === '' ? '---' : (mb_strlen($perkara) > 40 ? mb_substr($perkara, 0, 40) . '…' : $perkara);
                $stt = (string)($r['r_status'] ?? 'Diterima');
              ?>
              <tr>
                <td><?= h((string)($r['tarikh_penerimaan'] ?? '')) ?></td>
                <td class="fw-semibold"><?= h((string)($r['no_fail_kementerian'] ?? '')) ?></td>
                <td><?= h((string)($r['daripada_siapa'] ?? '')) ?></td>
                <td><?= h($perkaraShort) ?></td>
                <td><?= h((string)($r['recipient_department'] ?? '')) ?></td>
                <td>
                  <span class="badge <?= h(badge_status($stt)) ?>"><?= h($stt) ?></span>
                </td>
                <td><?= h(due_label((string)($r['due_date'] ?? ''))) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= (int)$r['surat_id'] ?>">
                    <i class="bi bi-eye"></i> Lihat
                  </a>
                  <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/surat_respond.php?id=<?= (int)$r['surat_id'] ?>">
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
