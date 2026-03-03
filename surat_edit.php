<?php
require_once __DIR__ . '/includes/auth_check.php';

if (!can_access_surat_module()) {
  flash_set('danger', 'Akses ditolak.');
  redirect('/dashboard.php');
}

require_once __DIR__ . '/includes/header.php';

$me = current_user();
$uid = (int)($me['id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM surat_menyurat WHERE id=? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();

if (!$row) {
  http_response_code(404);
  echo "Rekod tidak dijumpai.";
  exit;
}

$data = [
  'tarikh_penerimaan' => (string)($row['tarikh_penerimaan'] ?? ''),
  'no_fail_kementerian' => (string)($row['no_fail_kementerian'] ?? ''),
  'tarikh_surat' => (string)($row['tarikh_surat'] ?? ''),
  'daripada_siapa' => (string)($row['daripada_siapa'] ?? ''),
  'perkara' => (string)($row['perkara'] ?? ''),
  'dikirim_kepada' => (string)($row['dikirim_kepada'] ?? ''),
  'tindakan' => (string)($row['tindakan'] ?? ''),
  'tempoh_menjawab' => (string)($row['tempoh_menjawab'] ?? ''),
  'status' => (string)($row['status'] ?? ''),
  'tarikh_dijawab' => (string)($row['tarikh_dijawab'] ?? ''),
  'catatan' => (string)($row['catatan'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  foreach ($data as $k => $_) $data[$k] = trim((string)($_POST[$k] ?? ''));

  $tp = normalize_date($data['tarikh_penerimaan']);
  $ts = normalize_date($data['tarikh_surat']);
  $td = normalize_date($data['tarikh_dijawab']);

  $st2 = $pdo->prepare("
    UPDATE surat_menyurat SET
      tarikh_penerimaan=:tp,
      no_fail_kementerian=:nf,
      tarikh_surat=:ts,
      daripada_siapa=:drp,
      perkara=:prk,
      dikirim_kepada=:dk,
      tindakan=:tnd,
      tempoh_menjawab=:tmp,
      status=:st,
      tarikh_dijawab=:td,
      catatan=:ct,
      updated_by=:ub
    WHERE id=:id
  ");
  $st2->execute([
    ':tp' => ($tp === '' ? null : $tp),
    ':nf' => ($data['no_fail_kementerian'] === '' ? null : $data['no_fail_kementerian']),
    ':ts' => ($ts === '' ? null : $ts),
    ':drp' => ($data['daripada_siapa'] === '' ? null : $data['daripada_siapa']),
    ':prk' => ($data['perkara'] === '' ? null : $data['perkara']),
    ':dk' => ($data['dikirim_kepada'] === '' ? null : $data['dikirim_kepada']),
    ':tnd' => ($data['tindakan'] === '' ? null : $data['tindakan']),
    ':tmp' => ($data['tempoh_menjawab'] === '' ? null : $data['tempoh_menjawab']),
    ':st' => ($data['status'] === '' ? null : $data['status']),
    ':td' => ($td === '' ? null : $td),
    ':ct' => ($data['catatan'] === '' ? null : $data['catatan']),
    ':ub' => $uid,
    ':id' => $id,
  ]);

  flash_set('success', 'Rekod berjaya dikemaskini.');
  redirect('/surat.php');
}
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h3 class="mb-1">Edit Rekod Surat #<?= (int)$id ?></h3>
    <div class="text-muted">Anda boleh ubah mana-mana medan.</div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat.php"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" class="row g-3">
      <?= csrf_field() ?>

      <div class="col-md-4">
        <label class="form-label">Tarikh Penerimaan</label>
        <input class="form-control" name="tarikh_penerimaan" value="<?= h($data['tarikh_penerimaan']) ?>" placeholder="dd/mm/yyyy">
      </div>

      <div class="col-md-8">
        <label class="form-label">No. Fail Kementerian/Ibu Pejabat</label>
        <input class="form-control" name="no_fail_kementerian" value="<?= h($data['no_fail_kementerian']) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Tarikh Surat</label>
        <input class="form-control" name="tarikh_surat" value="<?= h($data['tarikh_surat']) ?>" placeholder="dd/mm/yyyy">
      </div>

      <div class="col-md-8">
        <label class="form-label">Daripada Siapa</label>
        <input class="form-control" name="daripada_siapa" value="<?= h($data['daripada_siapa']) ?>">
      </div>

      <div class="col-12">
        <label class="form-label">Perkara</label>
        <textarea class="form-control" name="perkara" rows="3"><?= h($data['perkara']) ?></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">Dikirim Kepada</label>
        <input class="form-control" name="dikirim_kepada" value="<?= h($data['dikirim_kepada']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Tindakan</label>
        <input class="form-control" name="tindakan" value="<?= h($data['tindakan']) ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Tempoh Menjawab</label>
        <input class="form-control" name="tempoh_menjawab" value="<?= h($data['tempoh_menjawab']) ?>" placeholder="Contoh: 7 hari">
      </div>

      <div class="col-md-4">
        <label class="form-label">Status</label>
        <input class="form-control" name="status" value="<?= h($data['status']) ?>" placeholder="Contoh: Dalam proses">
      </div>

      <div class="col-md-4">
        <label class="form-label">Tarikh Dijawab</label>
        <input class="form-control" name="tarikh_dijawab" value="<?= h($data['tarikh_dijawab']) ?>" placeholder="dd/mm/yyyy">
      </div>

      <div class="col-12">
        <label class="form-label">Catatan</label>
        <textarea class="form-control" name="catatan" rows="3"><?= h($data['catatan']) ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/surat.php">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
