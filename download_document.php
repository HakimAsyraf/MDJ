<?php
require_once __DIR__ . '/includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo "Invalid document id.";
  exit;
}

$st = $pdo->prepare("SELECT * FROM documents WHERE id=? LIMIT 1");
$st->execute([$id]);
$doc = $st->fetch();

if (!$doc) {
  http_response_code(404);
  echo "Document not found.";
  exit;
}

// Staff restriction (doc belongs to a file)
$fileId = (int)($doc['file_id'] ?? 0);
if ($fileId <= 0 || !can_access_file($pdo, $fileId)) {
  http_response_code(403);
  echo 'Access denied.';
  exit;
}

$relPath = (string)($doc['document_path'] ?? '');
if ($relPath === '') {
  http_response_code(400);
  echo "Invalid path.";
  exit;
}

// Force path under uploads/
if (!str_starts_with($relPath, 'uploads/')) {
  http_response_code(400);
  echo "Invalid path.";
  exit;
}

$full = __DIR__ . '/' . $relPath;
$realBase = realpath(__DIR__ . '/uploads');
$realFile = realpath($full);

if (!$realFile || !$realBase || !str_starts_with($realFile, $realBase) || !is_file($realFile)) {
  http_response_code(404);
  echo "File not found on server.";
  exit;
}

$filename = (string)($doc['document_name'] ?? '');
if ($filename === '') $filename = basename($realFile);

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'pdf') $mime = 'application/pdf';
elseif ($ext === 'doc') $mime = 'application/msword';
elseif ($ext === 'docx') $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . filesize($realFile));
header('X-Content-Type-Options: nosniff');

readfile($realFile);
exit;
