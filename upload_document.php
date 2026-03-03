<?php
require_once __DIR__ . '/includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/dashboard.php');
csrf_verify();

$fileId = (int)($_POST['file_id'] ?? 0);

if ($fileId <= 0 || !can_access_file($pdo, $fileId)) {
  flash_set('danger', 'Akses ditolak untuk fail ini.');
  redirect('/search.php');
}

$st = $pdo->prepare("SELECT id FROM files WHERE id=? LIMIT 1");
$st->execute([$fileId]);
if (!$st->fetch()) {
  flash_set('danger', 'Fail tidak wujud.');
  redirect('/search.php');
}

if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
  flash_set('danger', 'Upload gagal.');
  redirect('/view_file.php?id=' . $fileId);
}

$origName = $_FILES['document']['name'];
$tmp = $_FILES['document']['tmp_name'];
$size = (int)$_FILES['document']['size'];

$docType = detect_doc_type($origName);
if (!$docType) {
  flash_set('danger', 'Jenis fail tidak dibenarkan. Hanya Word/PDF.');
  redirect('/view_file.php?id=' . $fileId);
}
if ($size > 25 * 1024 * 1024) {
  flash_set('danger', 'Fail terlalu besar (maks 25MB).');
  redirect('/view_file.php?id=' . $fileId);
}

$safe = safe_filename(pathinfo($origName, PATHINFO_FILENAME));
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$stored = $safe . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

$destDir = __DIR__ . '/uploads';
if (!is_dir($destDir)) mkdir($destDir, 0775, true);
$dest = $destDir . '/' . $stored;

if (!move_uploaded_file($tmp, $dest)) {
  flash_set('danger', 'Gagal simpan fail.');
  redirect('/view_file.php?id=' . $fileId);
}

$path = 'uploads/' . $stored;
$st2 = $pdo->prepare("INSERT INTO documents(file_id, document_name, document_path, document_type, uploaded_by)
                      VALUES(?,?,?,?,?)");
$st2->execute([$fileId, $origName, $path, $docType, (int)current_user()['id']]);

flash_set('success', 'Dokumen berjaya diupload.');
redirect('/view_file.php?id=' . $fileId);
