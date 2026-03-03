<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

require_login();
if (!can_access_surat_module()) { http_response_code(403); die('Forbidden'); }

// Safety: if tables not ready, show message instead of DB error
if (!surat_tables_ready($pdo)) {
  echo '<div class="alert alert-danger">Jadual surat belum tersedia. Sila jalankan SQL untuk jadual surat_menyurat & surat_recipients.</div>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$deps = get_departments($pdo);

$errors = [];
$values = [
  'tarikh_penerimaan' => '',
  'no_fail_kementerian' => '',
  'tarikh_surat' => '',
  'daripada_siapa' => '',
  'perkara' => '',
  'dikirim_kepada' => '',
  'tindakan' => 'Segera',
  'tempoh_menjawab' => '7',
  'catatan' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $values['tarikh_penerimaan']   = normalize_date((string)($_POST['tarikh_penerimaan'] ?? ''));
  $values['no_fail_kementerian'] = trim((string)($_POST['no_fail_kementerian'] ?? ''));
  $values['tarikh_surat']        = normalize_date((string)($_POST['tarikh_surat'] ?? ''));
  $values['daripada_siapa']      = trim((string)($_POST['daripada_siapa'] ?? ''));
  $values['perkara']             = trim((string)($_POST['perkara'] ?? ''));
  $values['dikirim_kepada']      = trim((string)($_POST['dikirim_kepada'] ?? ''));
  $values['tindakan']            = trim((string)($_POST['tindakan'] ?? 'Segera'));
  $values['tempoh_menjawab']     = (string)((int)($_POST['tempoh_menjawab'] ?? 0));
  $values['catatan']             = trim((string)($_POST['catatan'] ?? ''));

  if ($values['tarikh_penerimaan'] === '') $errors[] = 'Tarikh Terima diperlukan.';
  if ($values['tarikh_surat'] === '') $errors[] = 'Tarikh Surat diperlukan.';
  if ($values['no_fail_kementerian'] === '') $errors[] = 'No. Fail Kementerian diperlukan.';
  if ($values['dikirim_kepada'] === '') $errors[] = 'Sila pilih jabatan penerima.';
  if ($values['perkara'] === '') $errors[] = 'Perkara diperlukan.';

  $tempoh = (int)$values['tempoh_menjawab'];
  if ($tempoh <= 0) $tempoh = 7;

  // optional upload
  $hasUpload = isset($_FILES['lampiran']) && is_array($_FILES['lampiran']) && (int)($_FILES['lampiran']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

  if (!$errors) {
    try {
      // Insert surat_menyurat
      $cols = [];
      $params = [];
      $vals = [];

      $cols[]='tarikh_penerimaan';   $vals[]=$values['tarikh_penerimaan'];
      $cols[]='no_fail_kementerian'; $vals[]=$values['no_fail_kementerian'];
      $cols[]='tarikh_surat';        $vals[]=$values['tarikh_surat'];
      $cols[]='daripada_siapa';      $vals[]=$values['daripada_siapa'];
      $cols[]='perkara';             $vals[]=$values['perkara'];
      $cols[]='dikirim_kepada';      $vals[]=$values['dikirim_kepada'];
      $cols[]='tindakan';            $vals[]=$values['tindakan'];
      $cols[]='tempoh_menjawab';     $vals[]=$tempoh;

      // status column exists in your schema
      if (db_column_exists($pdo, 'surat_menyurat', 'status')) {
        $cols[]='status'; $vals[]='Diterima';
      }

      if (db_column_exists($pdo, 'surat_menyurat', 'catatan')) {
        $cols[]='catatan'; $vals[]=$values['catatan'];
      }

      // timestamps (only if exist)
      if (db_column_exists($pdo, 'surat_menyurat', 'created_at')) {
        $cols[]='created_at'; $vals[] = date('Y-m-d H:i:s');
      }
      if (db_column_exists($pdo, 'surat_menyurat', 'updated_at')) {
        $cols[]='updated_at'; $vals[] = date('Y-m-d H:i:s');
      }

      $placeholders = implode(',', array_fill(0, count($cols), '?'));
      $sql = "INSERT INTO surat_menyurat (".implode(',', $cols).") VALUES ($placeholders)";
      $st = $pdo->prepare($sql);
      $st->execute($vals);

      $suratId = (int)$pdo->lastInsertId();

      // Insert into surat_recipients (this is what was failing before FK fix)
      $due = surat_due_date($values['tarikh_penerimaan'], $tempoh);

      $rCols = ['surat_id','recipient_department'];
      $rVals = [$suratId, $values['dikirim_kepada']];

      if (db_column_exists($pdo, 'surat_recipients', 'status')) {
        $rCols[]='status'; $rVals[]='Diterima';
      }
      if (db_column_exists($pdo, 'surat_recipients', 'comment')) {
        $rCols[]='comment'; $rVals[] = null;
      }
      if (db_column_exists($pdo, 'surat_recipients', 'due_date')) {
        $rCols[]='due_date'; $rVals[] = $due;
      }
      if (db_column_exists($pdo, 'surat_recipients', 'created_at')) {
        $rCols[]='created_at'; $rVals[] = date('Y-m-d H:i:s');
      }
      if (db_column_exists($pdo, 'surat_recipients', 'updated_at')) {
        $rCols[]='updated_at'; $rVals[] = date('Y-m-d H:i:s');
      }

      $rPh = implode(',', array_fill(0, count($rCols), '?'));
      $rSql = "INSERT INTO surat_recipients (".implode(',', $rCols).") VALUES ($rPh)";
      $pdo->prepare($rSql)->execute($rVals);

      // Attachment (optional)
      if ($hasUpload && db_table_exists($pdo, 'surat_attachments')) {
        $meta = surat_store_upload($_FILES['lampiran']);

        $aCols = ['surat_id'];
        $aVals = [$suratId];

        // store relative path if column exists
        if (db_column_exists($pdo, 'surat_attachments', 'file_path')) {
          $aCols[]='file_path'; $aVals[] = (string)$meta['relative_path'];
        }
        if (db_column_exists($pdo, 'surat_attachments', 'original_name')) {
          $aCols[]='original_name'; $aVals[] = (string)$meta['original_name'];
        }
        if (db_column_exists($pdo, 'surat_attachments', 'stored_name')) {
          $aCols[]='stored_name'; $aVals[] = (string)$meta['stored_name'];
        }
        if (db_column_exists($pdo, 'surat_attachments', 'ext')) {
          $aCols[]='ext'; $aVals[] = (string)$meta['ext'];
        }
        if (db_column_exists($pdo, 'surat_attachments', 'size')) {
          $aCols[]='size'; $aVals[] = (int)$meta['size'];
        }
        if (db_column_exists($pdo, 'surat_attachments', 'created_at')) {
          $aCols[]='created_at'; $aVals[] = date('Y-m-d H:i:s');
        }

        $aPh = implode(',', array_fill(0, count($aCols), '?'));
        $aSql = "INSERT INTO surat_attachments (".implode(',', $aCols).") VALUES ($aPh)";
        $pdo->prepare($aSql)->execute($aVals);
      }

      // Notify recipient department users (bell)
      surat_notify_new($pdo, $suratId, $values['dikirim_kepada'], $values['no_fail_kementerian']);

      flash_set('success', 'Surat berjaya disimpan & dihantar.');
      redirect('/surat_menyurat.php');
    } catch (Throwable $e) {
      // If surat_menyurat already inserted but recipients fails, you will see it in DB.
      // After FK fix, this should stop happening.
      $errors[] = 'Gagal simpan surat. (DB error)';
    }
  }
}

$fl = flash_get_all();
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-1">Surat Baru</h3>
    <div class="text-muted">Daftar surat menyurat & hantar kepada jabatan.</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_menyurat.php"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<?php foreach ($fl as $f): ?>
  <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
<?php endforeach; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1">Gagal simpan surat.</div>
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card">
  <div class="card-body">
    <?= csrf_field() ?>

    <div class="row g-3">
      <div class="col-12 col-md-4">
        <label class="form-label">Tarikh Terima</label>
        <input class="form-control" type="date" name="tarikh_penerimaan" value="<?= h($values['tarikh_penerimaan']) ?>" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">No. Fail Kementerian</label>
        <input class="form-control" name="no_fail_kementerian" placeholder="Contoh: KPKT/0003" value="<?= h($values['no_fail_kementerian']) ?>" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Tarikh Surat</label>
        <input class="form-control" type="date" name="tarikh_surat" value="<?= h($values['tarikh_surat']) ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Daripada Siapa</label>
        <input class="form-control" name="daripada_siapa" value="<?= h($values['daripada_siapa']) ?>" placeholder="Contoh: Pentadbiran">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Dikirimkan Kepada (Jabatan)</label>
        <select class="form-select" name="dikirim_kepada" required>
          <option value="">-- pilih jabatan --</option>
          <?php foreach ($deps as $d): ?>
            <option value="<?= h($d) ?>" <?= ($values['dikirim_kepada']===$d?'selected':'') ?>><?= h($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Perkara</label>
        <textarea class="form-control" name="perkara" rows="3" required><?= h($values['perkara']) ?></textarea>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">Tindakan</label>
        <select class="form-select" name="tindakan">
          <?php
            $opts = ['Segera','Biasa','Makluman'];
            foreach ($opts as $o):
          ?>
            <option value="<?= h($o) ?>" <?= ($values['tindakan']===$o?'selected':'') ?>><?= h($o) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">Tempoh Menjawab (hari)</label>
        <input class="form-control" type="number" name="tempoh_menjawab" min="1" max="365" value="<?= h($values['tempoh_menjawab']) ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">Lampiran (pdf/doc/docx)</label>
        <input class="form-control" type="file" name="lampiran" accept=".pdf,.doc,.docx">
        <div class="text-muted small mt-1">Had saiz: <?= (int)MAX_UPLOAD_MB ?>MB</div>
      </div>

      <div class="col-12">
        <label class="form-label">Catatan (pilihan)</label>
        <textarea class="form-control" name="catatan" rows="2"><?= h($values['catatan']) ?></textarea>
      </div>
    </div>
  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat_menyurat.php">Batal</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan & Hantar</button>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
