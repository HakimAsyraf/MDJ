<?php
require_once __DIR__ . '/includes/auth_check.php';
require __DIR__ . '/includes/header.php';

/**
 * FIX: normalize_date() missing -> define it safely here.
 * Also includes safe fallbacks for detect_doc_type / safe_filename if not present.
 */

if (!function_exists('normalize_date')) {
  function normalize_date(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;

    // yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
      $dt = DateTime::createFromFormat('Y-m-d', $raw);
      return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : null;
    }

    // dd/mm/yyyy or dd.mm.yyyy or dd-mm-yyyy
    if (preg_match('/^(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})$/', $raw, $m)) {
      $d = $m[1]; $mo = $m[2]; $y = $m[3];
      $iso = "{$y}-{$mo}-{$d}";
      $dt = DateTime::createFromFormat('Y-m-d', $iso);
      return ($dt && $dt->format('Y-m-d') === $iso) ? $iso : null;
    }

    return null;
  }
}

if (!function_exists('detect_doc_type')) {
  function detect_doc_type(string $filename): ?string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'pdf') return 'pdf';
    if ($ext === 'doc' || $ext === 'docx') return 'word';
    return null;
  }
}

if (!function_exists('safe_filename')) {
  function safe_filename(string $name): string {
    $name = preg_replace('/[^\pL\pN\-_\. ]/u', '', $name);
    $name = preg_replace('/\s+/', '_', trim($name));
    $name = trim($name, '._-');
    return $name !== '' ? $name : 'document';
  }
}

if (!function_exists('current_user')) {
  function current_user(): array {
    return $_SESSION['user'] ?? [];
  }
}

if (!function_exists('is_admin')) {
  function is_admin(): bool {
    $u = current_user();
    return ($u['role'] ?? '') === 'admin';
  }
}

if (!function_exists('flash_set')) {
  function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
  }
}

if (!function_exists('redirect')) {
  function redirect(string $path): void {
    header("Location: " . BASE_URL . $path);
    exit;
  }
}

$JPPL_NAME = 'Jabatan Perancangan Pembangunan dan Landskap';
$IT_NAME   = 'IT Department';

function dept_code(string $dept): string {
  $map = [
    'Jabatan Perancangan Pembangunan dan Landskap' => 'JPPL',
    'OSC' => 'OSC',
    'Kejuruteraan' => 'KEJ',
    'Pentadbiran' => 'PNT',
    'Penguatkuasa' => 'PKS',
    'Kewangan' => 'KEW',
    'Penilaian' => 'PEN',
    'IT Department' => 'IT',
  ];
  return $map[$dept] ?? strtoupper(substr(preg_replace('/\s+/', '', $dept), 0, 4));
}

function generate_no_fail_auto(string $dept, int $tahun, string $kotak): string {
  $code = dept_code($dept);
  $kotak = trim($kotak) !== '' ? trim($kotak) : 'XX';
  $rand = strtoupper(bin2hex(random_bytes(3))); // 6 chars
  $ymd  = date('Ymd');
  // Unique + readable
  return "AUTO-{$code}-{$tahun}-{$kotak}-{$ymd}-{$rand}";
}

// Department list (for admin choosing owner dept). Exclude IT Department from Tambah Fail.
$departmentList = [
  $JPPL_NAME, 'OSC', 'Kejuruteraan', 'Pentadbiran', 'Penguatkuasa', 'Kewangan', 'Penilaian'
];

// Owner dept rules:
// - Staff: forced to their department
// - Admin: can choose department owner (but not IT Department)
$user = current_user();
$ownerDept = $user['department'] ?? $JPPL_NAME;
if (is_admin()) {
  $ownerDept = trim((string)($_POST['department_owner'] ?? $_GET['department_owner'] ?? $ownerDept));
  if ($ownerDept === $IT_NAME) $ownerDept = $JPPL_NAME;
  if (!in_array($ownerDept, $departmentList, true)) $ownerDept = $JPPL_NAME;
} else {
  // staff cannot pick other dept
  $ownerDept = $user['department'] ?? $JPPL_NAME;
}

$isJPPL = ($ownerDept === $JPPL_NAME);

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (function_exists('csrf_verify')) csrf_verify();

  // Read common inputs
  $tahun = (int)($_POST['tahun'] ?? 0);
  $no_kotak_fail = trim((string)($_POST['no_kotak_fail'] ?? ''));
  $tarikh_raw = trim((string)($_POST['tarikh_permohonan_masuk'] ?? ''));
  $tarikh = normalize_date($tarikh_raw);

  $aras = (($_POST['aras'] ?? '') !== '') ? (int)$_POST['aras'] : null;
  $kabinet = trim((string)($_POST['kabinet'] ?? ''));

  // JPPL inputs
  $no_fail_permohonan = trim((string)($_POST['no_fail_permohonan'] ?? ''));
  $lot_pt = trim((string)($_POST['lot_pt'] ?? ''));
  $mukim  = trim((string)($_POST['mukim'] ?? ''));

  // Re-evaluate owner dept from POST for admin (secure)
  if (is_admin()) {
    $ownerDept = trim((string)($_POST['department_owner'] ?? $ownerDept));
    if ($ownerDept === $IT_NAME) $ownerDept = $JPPL_NAME;
    if (!in_array($ownerDept, $departmentList, true)) $ownerDept = $JPPL_NAME;
  } else {
    $ownerDept = $user['department'] ?? $JPPL_NAME;
  }
  $isJPPL = ($ownerDept === $JPPL_NAME);

  // Validation
  if ($tahun <= 0) {
    flash_set('danger', 'Sila masukkan Tahun yang betul.');
  } else {
    if ($isJPPL) {
      if ($no_fail_permohonan === '' || $lot_pt === '' || $mukim === '') {
        flash_set('danger', 'Sila lengkapkan medan wajib (JPPL): No Fail Permohonan, Lot/PT, Mukim.');
      }
    } else {
      // For non-JPPL: auto-generate No Fail; Lot/PT & Mukim saved as empty string
      if ($no_fail_permohonan === '') {
        $no_fail_permohonan = generate_no_fail_auto($ownerDept, $tahun, $no_kotak_fail);
      }
      $lot_pt = '';
      $mukim  = '';
    }

    // If user typed a date but format invalid
    if ($tarikh_raw !== '' && $tarikh === null) {
      flash_set('danger', 'Format tarikh tidak sah. Guna dd/mm/yyyy atau dd.mm.yyyy atau yyyy-mm-dd.');
    } else {
      try {
        $pdo->beginTransaction();

        // IMPORTANT: assumes files table has `department` column (owner)
        $st = $pdo->prepare("
          INSERT INTO files
            (department, tahun, no_kotak_fail, no_fail_permohonan, tarikh_permohonan_masuk, lot_pt, mukim, aras, kabinet, created_by)
          VALUES
            (:dept, :tahun, :kotak, :nofail, :tarikh, :lotpt, :mukim, :aras, :kabinet, :created_by)
        ");

        $st->execute([
          ':dept' => $ownerDept,
          ':tahun' => $tahun,
          ':kotak' => ($no_kotak_fail !== '' ? $no_kotak_fail : null),
          ':nofail' => $no_fail_permohonan,
          ':tarikh' => $tarikh,
          ':lotpt' => $lot_pt,
          ':mukim' => $mukim,
          ':aras' => $aras,
          ':kabinet' => ($kabinet !== '' ? $kabinet : null),
          ':created_by' => (int)($user['id'] ?? 0),
        ]);

        $fileId = (int)$pdo->lastInsertId();

        // Documents upload (multiple)
        if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
          $count = count($_FILES['documents']['name']);
          for ($i = 0; $i < $count; $i++) {
            if (($_FILES['documents']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

            $origName = (string)($_FILES['documents']['name'][$i] ?? '');
            $tmp = (string)($_FILES['documents']['tmp_name'][$i] ?? '');
            $size = (int)($_FILES['documents']['size'][$i] ?? 0);

            $docType = detect_doc_type($origName);
            if (!$docType) continue;
            if ($size > 25 * 1024 * 1024) continue; // 25MB

            $safe = safe_filename(pathinfo($origName, PATHINFO_FILENAME));
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $stored = $safe . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

            $destDir = __DIR__ . '/uploads';
            if (!is_dir($destDir)) mkdir($destDir, 0775, true);

            $dest = $destDir . '/' . $stored;
            if (!move_uploaded_file($tmp, $dest)) continue;

            $path = 'uploads/' . $stored;

            $st2 = $pdo->prepare("
              INSERT INTO documents(file_id, document_name, document_path, document_type, uploaded_by)
              VALUES(?,?,?,?,?)
            ");
            $st2->execute([$fileId, $origName, $path, $docType, (int)($user['id'] ?? 0)]);
          }
        }

        $pdo->commit();
        flash_set('success', 'Fail berjaya ditambah.');
        redirect('/view_file.php?id=' . $fileId);

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('danger', 'Gagal tambah fail. Semak No Fail Permohonan (unik) & cuba lagi.');
      }
    }
  }
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="h4 mb-0">Tambah Fail</div>
    <div class="text-muted small">Tambah maklumat fail dan upload dokumen Word/PDF.</div>
  </div>

  <div class="d-flex gap-2">
    <?php if (is_admin()): ?>
      <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/admin_import.php">
        <i class="bi bi-upload"></i> Import CSV
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/search.php">
      <i class="bi bi-arrow-left"></i> Kembali
    </a>
  </div>
</div>

<div class="card p-3 p-md-4">
  <form method="post" enctype="multipart/form-data" class="row g-3" id="addFileForm">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

    <?php if (is_admin()): ?>
      <div class="col-12 col-lg-4">
        <label class="form-label">Jabatan (Pemilik Fail) *</label>
        <select class="form-select" name="department_owner" id="deptOwner" required>
          <?php foreach ($departmentList as $d): ?>
            <option value="<?= htmlspecialchars($d, ENT_QUOTES) ?>" <?= $ownerDept === $d ? 'selected' : '' ?>>
              <?= htmlspecialchars($d) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <input type="hidden" name="department_owner" value="<?= htmlspecialchars($ownerDept, ENT_QUOTES) ?>">
    <?php endif; ?>

    <div class="col-6 col-lg-2">
      <label class="form-label">TAHUN *</label>
      <input class="form-control" name="tahun" required inputmode="numeric" placeholder="2022" value="<?= htmlspecialchars($_POST['tahun'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-6 col-lg-2">
      <label class="form-label">NO. KOTAK FAIL</label>
      <input class="form-control" name="no_kotak_fail" placeholder="01" value="<?= htmlspecialchars($_POST['no_kotak_fail'] ?? '', ENT_QUOTES) ?>">
    </div>

    <!-- JPPL only -->
    <div class="col-12 col-lg-4 jppl-only">
      <label class="form-label">NO FAIL PERMOHONAN <?= $isJPPL ? '*' : '(Auto)' ?></label>
      <input class="form-control" name="no_fail_permohonan"
             <?= $isJPPL ? 'required' : '' ?>
             placeholder="<?= $isJPPL ? '11/01/2022' : 'Akan dijana automatik' ?>"
             value="<?= htmlspecialchars($_POST['no_fail_permohonan'] ?? '', ENT_QUOTES) ?>"
             <?= $isJPPL ? '' : 'readonly' ?>>
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label">TARIKH PERMOHONAN MASUK</label>
      <input class="form-control" name="tarikh_permohonan_masuk"
             placeholder="dd/mm/yyyy atau dd.mm.yyyy atau yyyy-mm-dd"
             value="<?= htmlspecialchars($_POST['tarikh_permohonan_masuk'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-12 jppl-only">
      <label class="form-label">LOT / PT *</label>
      <textarea class="form-control" name="lot_pt" rows="2" placeholder="Contoh: LOT 1234 (GM 763)"><?= htmlspecialchars($_POST['lot_pt'] ?? '', ENT_QUOTES) ?></textarea>
    </div>

    <div class="col-12 col-lg-4 jppl-only">
      <label class="form-label">MUKIM *</label>
      <input class="form-control" name="mukim" placeholder="PEDAH" value="<?= htmlspecialchars($_POST['mukim'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-6 col-lg-2">
      <label class="form-label">ARAS</label>
      <input class="form-control" name="aras" inputmode="numeric" placeholder="4" value="<?= htmlspecialchars($_POST['aras'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-6 col-lg-2">
      <label class="form-label">KABINET</label>
      <input class="form-control" name="kabinet" placeholder="A/B/C" value="<?= htmlspecialchars($_POST['kabinet'] ?? '', ENT_QUOTES) ?>">
    </div>

    <div class="col-12 col-lg-4">
      <label class="form-label">Dokumen (Word/PDF)</label>
      <input class="form-control" type="file" name="documents[]" multiple accept=".pdf,.doc,.docx">
      <div class="form-text">Maksimum 25MB setiap fail.</div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Simpan</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/search.php">Batal</a>
    </div>
  </form>
</div>

<script>
(function(){
  var dept = document.getElementById('deptOwner');
  var jpplName = <?= json_encode($JPPL_NAME) ?>;

  function applyJPPL(){
    var isJPPL = true;
    if (dept) isJPPL = (dept.value === jpplName);

    document.querySelectorAll('.jppl-only').forEach(function(el){
      el.style.display = isJPPL ? '' : 'none';
    });

    // For non-JPPL, mark fields non-required and clear readonly display rule
    var noFail = document.querySelector('input[name="no_fail_permohonan"]');
    if (noFail){
      if (isJPPL){
        noFail.removeAttribute('readonly');
        noFail.setAttribute('required', 'required');
      } else {
        noFail.setAttribute('readonly', 'readonly');
        noFail.removeAttribute('required');
        noFail.value = '';
      }
    }
  }

  if (dept){
    dept.addEventListener('change', applyJPPL);
  }
  applyJPPL();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
