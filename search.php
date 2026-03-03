<?php
require_once __DIR__ . '/includes/auth_check.php';
require __DIR__ . '/includes/header.php';

/**
 * Carian Fail (Role-based + Jabatan rules)
 * - Staff: only own department
 * - Admin: can choose department (Semua / specific)
 * - JPPL: supports Mukim + No Fail Permohonan + Lot/PT
 * - Other depts: those fields hidden + ignored
 */

if (!function_exists('h')) {
  function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$me = $_SESSION['user'] ?? [];
$isAdmin = function_exists('is_admin') ? is_admin() : (($me['role'] ?? '') === 'admin');
$myDept  = $me['department'] ?? '';

$JPPL_NAME = 'Jabatan Perancangan Pembangunan dan Landskap';

// --- Department list for admin dropdown ---
$departments = [];
if (function_exists('get_departments')) {
  $departments = get_departments($pdo);
} else {
  // fallback if helper not present
  $departments = [
    $JPPL_NAME, 'OSC', 'Kejuruteraan', 'Pentadbiran', 'Penguatkuasa', 'Kewangan', 'Penilaian', 'IT Department'
  ];
}
$departments = array_values(array_filter($departments, fn($d) => trim((string)$d) !== ''));

// --- Selected department logic ---
$selectedDept = 'Semua';
if ($isAdmin) {
  $selectedDept = trim((string)($_GET['department'] ?? 'Semua'));
  if ($selectedDept === '') $selectedDept = 'Semua';
  if ($selectedDept !== 'Semua' && !in_array($selectedDept, $departments, true)) {
    $selectedDept = 'Semua';
  }
} else {
  // staff forced to own dept
  $selectedDept = $myDept ?: $JPPL_NAME; // safe fallback
}

// JPPL context: only when dept == JPPL (admin selected JPPL or staff in JPPL)
$isJPPLContext = ($selectedDept === $JPPL_NAME);

// --- Read filters ---
$q        = trim((string)($_GET['q'] ?? ''));
$tahun    = trim((string)($_GET['tahun'] ?? ''));
$kotak    = trim((string)($_GET['kotak'] ?? ''));
$aras     = trim((string)($_GET['aras'] ?? ''));
$kabinet  = trim((string)($_GET['kabinet'] ?? ''));

// JPPL-only filters (ignored for other departments)
$noFail   = trim((string)($_GET['no_fail'] ?? ''));
$lotpt    = trim((string)($_GET['lotpt'] ?? ''));
$mukim    = trim((string)($_GET['mukim'] ?? ''));

// --- Build query safely ---
$sql = "
  SELECT
    f.*,
    (SELECT COUNT(*) FROM documents d WHERE d.file_id = f.id) AS doc_count
  FROM files f
";
$where = [];
$params = [];

// role restriction
if ($isAdmin) {
  if ($selectedDept !== 'Semua') {
    $where[] = "f.department = :dept";
    $params[':dept'] = $selectedDept;
  }
} else {
  // staff only see their own dept
  $where[] = "f.department = :dept";
  $params[':dept'] = $selectedDept;
}

// common filters
if ($tahun !== '') {
  if (ctype_digit($tahun)) {
    $where[] = "f.tahun = :tahun";
    $params[':tahun'] = (int)$tahun;
  }
}

if ($kotak !== '') {
  // keep leading zero if any (e.g. 01)
  $where[] = "f.no_kotak_fail = :kotak";
  $params[':kotak'] = $kotak;
}

if ($aras !== '') {
  if (ctype_digit($aras)) {
    $where[] = "f.aras = :aras";
    $params[':aras'] = (int)$aras;
  }
}

if ($kabinet !== '') {
  $where[] = "f.kabinet LIKE :kabinet";
  $params[':kabinet'] = "%{$kabinet}%";
}

// JPPL-only filters
if ($isJPPLContext) {
  if ($noFail !== '') {
    $where[] = "f.no_fail_permohonan LIKE :nofail";
    $params[':nofail'] = "%{$noFail}%";
  }
  if ($lotpt !== '') {
    $where[] = "f.lot_pt LIKE :lotpt";
    $params[':lotpt'] = "%{$lotpt}%";
  }
  if ($mukim !== '') {
    $where[] = "f.mukim LIKE :mukim";
    $params[':mukim'] = "%{$mukim}%";
  }
}

// keyword search (ROLE/JABATAN aware)
if ($q !== '') {
  $kw = "%{$q}%";

  // support dd/mm/yyyy or dd.mm.yyyy -> match DATE as formatted
  $qDate = null;
  if (preg_match('/^(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})$/', $q, $m)) {
    $dd = $m[1]; $mm = $m[2]; $yy = $m[3];
    $qDate = "{$yy}-{$mm}-{$dd}";
  }

  $or = [];
  // common fields for all departments
  $or[] = "CAST(f.tahun AS CHAR) LIKE :kw";
  $or[] = "f.no_kotak_fail LIKE :kw";
  $or[] = "CAST(f.aras AS CHAR) LIKE :kw";
  $or[] = "f.kabinet LIKE :kw";
  $or[] = "f.department LIKE :kw";
  // allow date search in dd/mm/yyyy form
  $or[] = "DATE_FORMAT(f.tarikh_permohonan_masuk, '%d/%m/%Y') LIKE :kw";

  // JPPL extra fields ONLY if JPPL context
  if ($isJPPLContext) {
    $or[] = "f.no_fail_permohonan LIKE :kw";
    $or[] = "f.lot_pt LIKE :kw";
    $or[] = "f.mukim LIKE :kw";
  }

  // if keyword is valid dd/mm/yyyy, also try exact date match
  if ($qDate) {
    $or[] = "f.tarikh_permohonan_masuk = :qdate";
    $params[':qdate'] = $qDate;
  }

  $where[] = "(" . implode(" OR ", $or) . ")";
  $params[':kw'] = $kw;
}

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY f.updated_at DESC, f.id DESC LIMIT 200";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// --- Decide what to show in table (JPPL vs others) ---
$showJPPLCols = $isJPPLContext; // only show JPPL columns if JPPL department chosen
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="h4 mb-0">Carian Fail</div>
    <div class="text-muted small">Filter mengikut: Tahun, Kotak, <?= $showJPPLCols ? 'No Fail, Lot/PT, Mukim, ' : '' ?>Aras, Kabinet.</div>
  </div>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/add_file.php"><i class="bi bi-plus-circle"></i> Tambah Fail</a>
</div>

<div class="card p-3 p-md-4 mb-3">
  <form method="get" action="<?= BASE_URL ?>/search.php" class="row g-3">

    <?php if ($isAdmin): ?>
      <div class="col-12">
        <label class="form-label">Jabatan</label>
        <select name="department" class="form-select" id="deptSelect">
          <option value="Semua" <?= $selectedDept==='Semua'?'selected':'' ?>>Semua</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= h($d) ?>" <?= $selectedDept===$d?'selected':'' ?>><?= h($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <input type="hidden" name="department" value="<?= h($selectedDept) ?>">
      <div class="col-12">
        <label class="form-label">Jabatan</label>
        <div class="form-control" style="background:var(--panel2);"><?= h($selectedDept) ?></div>
      </div>
    <?php endif; ?>

    <div class="col-12 col-md-6">
      <label class="form-label">Keyword</label>
      <input
        class="form-control"
        name="q"
        value="<?= h($q) ?>"
        placeholder="<?= $showJPPLCols ? 'Contoh: PEDAH / 11/01/2022 / LOT 1 / (No Fail)' : 'Contoh: 2022 / 01 / 11/01/2022 / ARAS / KABINET' ?>"
      >
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">TAHUN</label>
      <input class="form-control" name="tahun" value="<?= h($tahun) ?>" placeholder="2022">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">NO. KOTAK FAIL</label>
      <input class="form-control" name="kotak" value="<?= h($kotak) ?>" placeholder="01">
    </div>

    <?php if ($showJPPLCols): ?>
      <div class="col-12 col-md-4">
        <label class="form-label">NO FAIL PERMOHONAN</label>
        <input class="form-control" name="no_fail" value="<?= h($noFail) ?>" placeholder="11/01/2022">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">LOT / PT</label>
        <input class="form-control" name="lotpt" value="<?= h($lotpt) ?>" placeholder="LOT 1234 (GM 763)">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">MUKIM</label>
        <input class="form-control" name="mukim" value="<?= h($mukim) ?>" placeholder="PEDAH">
      </div>
    <?php endif; ?>

    <div class="col-6 col-md-2">
      <label class="form-label">ARAS</label>
      <input class="form-control" name="aras" value="<?= h($aras) ?>" placeholder="4">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label">KABINET</label>
      <input class="form-control" name="kabinet" value="<?= h($kabinet) ?>" placeholder="A/B/C">
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Cari</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/search.php"><i class="bi bi-x-circle"></i> Reset</a>
    </div>

  </form>
</div>

<div class="card p-3 p-md-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="fw-semibold">Hasil Carian</div>
    <span class="badge text-bg-secondary"><?= count($rows) ?> rekod</span>
  </div>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Jabatan</th>
          <th>Tahun</th>
          <th>Kotak</th>
          <?php if ($showJPPLCols): ?>
            <th>No Fail Permohonan</th>
            <th>Lot/PT</th>
            <th>Mukim</th>
          <?php endif; ?>
          <th>Aras</th>
          <th>Kabinet</th>
          <th>Dokumen</th>
          <th class="text-end" style="width:160px;">Tindakan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="<?= $showJPPLCols ? 11 : 8 ?>" class="text-muted">Tiada rekod dijumpai.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><span class="fw-semibold"><?= h($r['department'] ?? '-') ?></span></td>
            <td><?= h((string)$r['tahun']) ?></td>
            <td><?= h((string)($r['no_kotak_fail'] ?? '')) ?></td>

            <?php if ($showJPPLCols): ?>
              <td><?= h((string)($r['no_fail_permohonan'] ?? '')) ?></td>
              <td><?= h((string)($r['lot_pt'] ?? '')) ?></td>
              <td><?= h((string)($r['mukim'] ?? '')) ?></td>
            <?php endif; ?>

            <td><?= h((string)($r['aras'] ?? '')) ?></td>
            <td><?= h((string)($r['kabinet'] ?? '')) ?></td>
            <td><span class="badge text-bg-secondary"><?= (int)($r['doc_count'] ?? 0) ?></span></td>

            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/view_file.php?id=<?= (int)$r['id'] ?>">
                <i class="bi bi-eye"></i> View
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Admin UX: when dept changes, reload so JPPL-only fields appear/disappear correctly.
  (function(){
    var s = document.getElementById('deptSelect');
    if(!s) return;
    s.addEventListener('change', function(){
      // submit GET immediately (keeps your filters consistent)
      this.form.submit();
    });
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
