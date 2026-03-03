<?php
require_once __DIR__ . '/includes/auth_check.php';
require_admin();

$docId  = (int)($_GET['id'] ?? 0);
$fileId = (int)($_GET['file_id'] ?? 0);

if ($docId <= 0) {
  flash_set('danger', 'ID dokumen tidak sah.');
  redirect('/dashboard.php');
}

$st = $pdo->prepare("SELECT * FROM documents WHERE id=? LIMIT 1");
$st->execute([$docId]);
$doc = $st->fetch();

if (!$doc) {
  flash_set('danger', 'Dokumen tidak dijumpai.');
  redirect($fileId > 0 ? '/view_file.php?id='.$fileId : '/dashboard.php');
}

// (Optional) confirm file relation
$fileId = $fileId > 0 ? $fileId : (int)($doc['file_id'] ?? 0);

// delete db row first
$pdo->prepare("DELETE FROM documents WHERE id=? LIMIT 1")->execute([$docId]);

// delete file on disk
$rel = (string)($doc['document_path'] ?? '');
if (str_starts_with($rel, 'uploads/')) {
  $full = __DIR__ . '/' . $rel;
  $realBase = realpath(__DIR__ . '/uploads');
  $realFile = realpath($full);

  if ($realFile && $realBase && str_starts_with($realFile, $realBase) && is_file($realFile)) {
    @unlink($realFile);
  }
}

flash_set('success', 'Dokumen berjaya dipadam.');
redirect($fileId > 0 ? '/view_file.php?id='.$fileId : '/dashboard.php');
