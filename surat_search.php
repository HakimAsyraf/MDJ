<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$myDept = strtolower(trim((string)($me['department'] ?? '')));
$isPrivileged = can_access_surat_module();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$dept = trim((string)($_GET['dept'] ?? ''));
$tindakan = trim((string)($_GET['tindakan'] ?? ''));

$where = [];
$params = [];

if (!$isPrivileged) {
  $where[] = "LOWER(TRIM(r.recipient_department))=?";
  $params[] = $myDept;
}

if ($q !== '') {
  $where[] = "(s.no_fail_kementerian LIKE ? OR s.daripada_siapa LIKE ? OR s.perkara LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

if ($status !== '' && in_array($status, ['Diterima','Diproses','Selesai'], true)) {
  $where[] = "r.status = ?";
  $params[] = $status;
}

if ($tindakan !== '' && in_array($tindakan, ['Segera','Simpanan'], true)) {
  $where[] = "s.tindakan = ?";
  $params[] = $tindakan;
}

if ($isPrivileged && $dept !== '') {
  $where[] = "r.recipient_department = ?";
  $params[] = $dept;
}

$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT
    r.surat_id, r.recipient_department, r.status AS r_status, r.due_date, r.responded_at,
    s.tarikh_terima, s.no_fail_kementerian, s.tarikh_surat, s.daripada_siapa, s.perkara, s.tindakan, s.tempoh_days
  FROM surat_recipients r
  JOIN surat_letters s ON s.id = r.surat_id
  $wsql
  ORDER BY COALESCE(s.tarikh_terima, DATE(s.created_at)) DESC, r.id DESC
";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
}

$deps = get_departments($pdo);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1">Carian Daftar Surat Menyurat</h1>
    <div class="text-muted">Cari ikut no fail, perkara, jabatan, status, tindakan.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_masuk.php"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2" method="get">
      <div class="col-md-5">
        <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Keyword / No. Fail Kementerian / Daripada / Perkara">
      </div>

      <div class="col-md-2">
        <select class="form-select" name="status">
          <option value="">Status (semua)</option>
          <?php foreach (['Diterima','Diproses','Selesai'] as $s): ?>
            <option value="<?= h($s) ?>" <?= $status===$s?'selected':'' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <select class="form-select" name="tindakan">
          <option value="">Tindakan (semua)</option>
          <?php foreach (['Segera','Simpanan'] as $t): ?>
            <option value="<?= h($t) ?>" <?= $tindakan===$t?'selected':'' ?>><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($isPrivileged): ?>
        <div class="col-md-3">
          <select class="form-select" name="dept">
            <option value="">Jabatan (semua)</option>
            <?php foreach ($deps as $d): ?>
              <option value="<?= h($d) ?>" <?= $dept===$d?'selected':'' ?>><?= h($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Cari</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_search.php">Reset</a>
      </div>
    </form>
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
              <tr>
                <td><?= h((string)($r['tarikh_terima'] ?? '')) ?></td>
                <td><?= h((string)($r['no_fail_kementerian'] ?? '')) ?></td>
                <td><?= h((string)($r['daripada_siapa'] ?? '')) ?></td>
                <td class="text-truncate" style="max-width:320px;"><?= h(mb_strimwidth((string)($r['perkara'] ?? ''),0,120,'...')) ?></td>
                <td><?= h((string)($r['recipient_department'] ?? '')) ?></td>
                <td><?= h((string)($r['r_status'] ?? '')) ?></td>
                <td><?= h((string)($r['due_date'] ?? '-')) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/surat_view.php?id=<?= (int)$r['surat_id'] ?>"><i class="bi bi-eye"></i> Lihat</a>
                  <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/surat_respond.php?id=<?= (int)$r['surat_id'] ?>"><i class="bi bi-chat-dots"></i> Respon</a>
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
