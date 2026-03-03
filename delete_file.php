<?php
require_once __DIR__ . '/includes/auth_check.php';
require_admin();

$fileId = (int)($_GET['id'] ?? 0);
if ($fileId <= 0) {
  flash_set('danger', 'ID fail tidak sah.');
  redirect('/dashboard.php');
}

// get all docs first (to delete files on disk)
$docs = [];
try {
  $st = $pdo->prepare("SELECT id, document_path FROM documents WHERE file_id=?");
  $st->execute([$fileId]);
  $docs = $st->fetchAll();
} catch (Throwable $e) { $docs = []; }

$pdo->beginTransaction();
try {
  // delete documents rows
  $pdo->prepare("DELETE FROM documents WHERE file_id=?")->execute([$fileId]);

  // delete file row
  $pdo->prepare("DELETE FROM files WHERE id=? LIMIT 1")->execute([$fileId]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  flash_set('danger', 'Gagal padam fail.');
  redirect('/dashboard.php');
}

// delete physical files
$uploadBase = realpath(__DIR__ . '/uploads');
foreach ($docs as $d) {
  $rel = (string)($d['document_path'] ?? '');
  if ($rel === '') continue;
  if (!str_starts_with($rel, 'uploads/')) continue;

  $full = __DIR__ . '/' . $rel;
  $realFile = realpath($full);
  if ($realFile && $uploadBase && str_starts_with($realFile, $uploadBase) && is_file($realFile)) {
    @unlink($realFile);
  }
}

flash_set('success', 'Fail berjaya dipadam.');
redirect('/dashboard.php');
