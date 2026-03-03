<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/config.php';

$id = (int)($_GET['att_id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid attachment.'); }

if (!db_table_exists($pdo, 'surat_attachments')) {
  http_response_code(404); exit('No attachment table.');
}

$st = $pdo->prepare("SELECT * FROM surat_attachments WHERE id=? LIMIT 1");
$st->execute([$id]);
$att = $st->fetch();
if (!$att) { http_response_code(404); exit('Attachment not found.'); }

$suratId = (int)($att['surat_id'] ?? 0);
if ($suratId <= 0 || !surat_can_view($pdo, $suratId)) {
  http_response_code(403); exit('Forbidden.');
}

$origName = (string)($att['file_name'] ?? 'lampiran');
$filePath = (string)($att['file_path'] ?? '');
$stored   = (string)($att['stored_name'] ?? '');

$abs = '';
$root = realpath(__DIR__);
$uploadsDir = realpath(__DIR__ . '/uploads');

if ($filePath !== '') {
  $candidate = __DIR__ . '/' . ltrim($filePath, '/\\');
  $real = realpath($candidate);
  if ($real && $uploadsDir && str_starts_with($real, $uploadsDir)) $abs = $real;
}

if ($abs === '' && $stored !== '') {
  $candidate = __DIR__ . '/uploads/surat/' . basename($stored);
  $real = realpath($candidate);
  if ($real && $uploadsDir && str_starts_with($real, $uploadsDir)) $abs = $real;
}

if ($abs === '' || !is_file($abs)) {
  http_response_code(404); exit('File missing.');
}

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext === '') $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

$mime = 'application/octet-stream';
if ($ext === 'pdf') $mime = 'application/pdf';
if ($ext === 'doc') $mime = 'application/msword';
if ($ext === 'docx') $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($abs));
header('Content-Disposition: inline; filename="' . str_replace('"','', $origName) . '"');
header('X-Content-Type-Options: nosniff');

readfile($abs);
exit;
