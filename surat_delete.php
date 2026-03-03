<?php
require_once __DIR__ . '/includes/auth_check.php';

if (!can_access_surat_module()) {
  flash_set('danger', 'Akses ditolak.');
  redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('danger', 'Rekod tidak sah.');
  redirect('/surat.php');
}

$pdo->prepare("DELETE FROM surat_menyurat WHERE id=?")->execute([$id]);
flash_set('success', 'Rekod berjaya dipadam.');
redirect('/surat.php');
