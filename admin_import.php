<?php
require_once __DIR__ . '/includes/config.php';
require_admin();
require __DIR__ . '/includes/header.php';

$departments = get_file_owner_departments($pdo);
$defaultDept = FULL_FORM_DEPARTMENT;

$imported = 0;
$skipped = 0;
$errors = [];

function csv_required_headers(): array {
  return [
    'tahun',
    'no_kotak_fail',
    'no_fail_permohonan',
    'tarikh_permohonan_masuk',
    'lot_pt',
    'mukim',
    'aras',
    'kabinet'
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors[] = 'Sila pilih fail CSV.';
  } else {
    $tmp = (string)$_FILES['csv']['tmp_name'];
    $fh = fopen($tmp, 'r');
    if (!$fh) {
      $errors[] = 'Tidak dapat membaca fail CSV.';
    } else {
      $header = fgetcsv($fh);
      if (!$header) {
        $errors[] = 'CSV kosong.';
      } else {
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
        $req = csv_required_headers();
        foreach ($req as $h) {
          if (!in_array($h, $header, true)) {
            $errors[] = "Header CSV tidak lengkap. Medan hilang: $h";
          }
        }

        if (!$errors) {
          $map = array_flip($header);

          $stmt = $pdo->prepare(
            "INSERT INTO files(department,tahun,no_kotak_fail,no_fail_permohonan,tarikh_permohonan_masuk,lot_pt,mukim,aras,kabinet,created_by)
             VALUES(?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               department=VALUES(department),
               tahun=VALUES(tahun),
               no_kotak_fail=VALUES(no_kotak_fail),
               tarikh_permohonan_masuk=VALUES(tarikh_permohonan_masuk),
               lot_pt=VALUES(lot_pt),
               mukim=VALUES(mukim),
               aras=VALUES(aras),
               kabinet=VALUES(kabinet),
               updated_at=CURRENT_TIMESTAMP"
          );

          $rowNum = 1;
          while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;

            $get = function(string $k) use ($row, $map) {
              $idx = $map[$k] ?? null;
              if ($idx === null) return '';
              return trim((string)($row[$idx] ?? ''));
            };

            // Optional department column
            $dept = '';
            if (isset($map['department'])) {
              $dept = $get('department');
            }
            if ($dept === '' || !in_array($dept, $departments, true)) {
              $dept = $defaultDept;
            }

            $tahun = (int)$get('tahun');
            $noKotak = $get('no_kotak_fail');
            $noFail = $get('no_fail_permohonan');
            $tarikh = normalize_date($get('tarikh_permohonan_masuk'));
            $lot = $get('lot_pt');
            $mukim = $get('mukim');
            $aras = $get('aras') !== '' ? (int)$get('aras') : null;
            $kabinet = $get('kabinet');

            if ($tahun <= 0) { $skipped++; continue; }

            $fullForm = is_full_form_department($dept);
            if ($fullForm) {
              if ($noFail === '' || $lot === '' || $mukim === '') { $skipped++; continue; }
            } else {
              if ($noFail === '') {
                $noFail = department_code($dept) . '-' . $tahun . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
              }
              if ($lot === '') $lot = null;
              if ($mukim === '') $mukim = null;
            }

            try {
              $stmt->execute([
                $dept,
                $tahun,
                $noKotak !== '' ? $noKotak : null,
                $noFail !== '' ? $noFail : null,
                $tarikh,
                $lot !== '' ? $lot : null,
                $mukim !== '' ? $mukim : null,
                $aras,
                $kabinet !== '' ? $kabinet : null,
                (int)current_user()['id']
              ]);
              $imported++;
            } catch (Throwable $e) {
              $skipped++;
              if (count($errors) < 5) {
                $errors[] = "Row $rowNum gagal diimport (mungkin duplicate No Fail): " . $e->getMessage();
              }
            }
          }

          fclose($fh);

          if ($imported > 0) {
            flash_set('success', "Import selesai: $imported rekod berjaya, $skipped rekod di-skip.");
            redirect('/add_file.php');
          }
        }
      }
    }
  }
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="h4 mb-0">Import CSV (Tambah Fail)</div>
    <div class="text-muted small">Import data fail secara pukal. (Admin sahaja)</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/add_file.php"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card p-3 p-md-4">
  <div class="alert alert-info">
    <div class="fw-semibold mb-1">Format CSV</div>
    <div class="small">
      Header wajib: <code>tahun,no_kotak_fail,no_fail_permohonan,tarikh_permohonan_masuk,lot_pt,mukim,aras,kabinet</code>.<br>
      <span class="text-muted">Optional: lajur <code>department</code> (IT Department akan diabaikan & ditukar ke default).</span>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <div class="fw-semibold">Ralat Import</div>
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-12 col-lg-6">
      <label class="form-label">Fail CSV</label>
      <input class="form-control" type="file" name="csv" accept=".csv" required>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Import</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/add_file.php">Batal</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
