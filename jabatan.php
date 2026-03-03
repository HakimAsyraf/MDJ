<?php
require_once __DIR__ . '/includes/auth_check.php';
require __DIR__ . '/includes/header.php';

$isAdmin = is_admin();
$allDepartments = get_departments($pdo);

// Staff: only own department
$departments = $isAdmin ? $allDepartments : [staff_department()];
$departments = array_values(array_filter($departments, fn($d) => trim((string)$d) !== ''));

/** Department code mapping */
function dept_code(string $dept): string {
  $map = [
    'IT Department' => 'IT',
    'Jabatan Perancangan Pembangunan dan Landskap' => 'JPPL',
    'OSC' => 'OSC',
    'Kejuruteraan' => 'KEJ',
    'Pentadbiran' => 'PENT',
    'Penguatkuasa' => 'PKS',
    'Kewangan' => 'KEW',
    'Penilaian' => 'NIL',
  ];
  return $map[$dept] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $dept), 0, 4));
}

/** Accent color mapping */
function dept_accent(string $dept): string {
  $map = [
    'IT Department' => '#111827',
    'Jabatan Perancangan Pembangunan dan Landskap' => '#0ea5a6',
    'OSC' => '#2563eb',
    'Kejuruteraan' => '#7c3aed',
    'Pentadbiran' => '#334155',
    'Penguatkuasa' => '#ef4444',
    'Kewangan' => '#16a34a',
    'Penilaian' => '#f59e0b',
  ];
  return $map[$dept] ?? '#0d6efd';
}

/** Icon mapping (Bootstrap Icons) */
function dept_icon(string $dept): string {
  $map = [
    'IT Department' => 'bi-cpu',
    'Jabatan Perancangan Pembangunan dan Landskap' => 'bi-buildings',
    'OSC' => 'bi-clipboard-check',
    'Kejuruteraan' => 'bi-tools',
    'Pentadbiran' => 'bi-briefcase',
    'Penguatkuasa' => 'bi-shield-check',
    'Kewangan' => 'bi-cash-stack',
    'Penilaian' => 'bi-clipboard-data',
  ];
  return $map[$dept] ?? 'bi-diagram-3';
}

/** “Logo behind text” badge */
function dept_logo(string $dept): string {
  $code = dept_code($dept);
  $accent = dept_accent($dept);
  $icon = dept_icon($dept);

  return '
    <div class="dept-logo" style="--dept-accent: '.$accent.'">
      <div class="dept-logo__bg"><i class="bi '.$icon.'"></i></div>
      <div class="dept-logo__code">'.$code.'</div>
    </div>
  ';
}

// --- Stats ---
$deptUsers = [];
$deptFiles = [];
$deptDocs  = [];

// active users per dept
$rows = $pdo->query("
  SELECT department, COUNT(*) c
  FROM users
  WHERE status='active' AND department<>'' AND department IS NOT NULL
  GROUP BY department
")->fetchAll();

foreach ($rows as $r) $deptUsers[(string)$r['department']] = (int)$r['c'];

// files per dept
$rows = $pdo->query("
  SELECT department, COUNT(*) c
  FROM files
  WHERE department<>'' AND department IS NOT NULL
  GROUP BY department
")->fetchAll();

foreach ($rows as $r) $deptFiles[(string)$r['department']] = (int)$r['c'];

// documents per dept (join files)
$rows = $pdo->query("
  SELECT f.department, COUNT(*) c
  FROM documents d
  JOIN files f ON f.id = d.file_id
  WHERE f.department<>'' AND f.department IS NOT NULL
  GROUP BY f.department
")->fetchAll();

foreach ($rows as $r) $deptDocs[(string)$r['department']] = (int)$r['c'];
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="h4 mb-0">Jabatan</div>
    <div class="text-muted small">Ringkasan fail mengikut jabatan.</div>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($departments as $d): ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card p-3 h-100 dept-card">
        <div class="d-flex align-items-start justify-content-between">
          <div class="d-flex gap-3 align-items-center">
            <?= dept_logo($d) ?>
            <div>
              <div class="fw-bold"><?= h($d) ?></div>
              <div class="text-muted small">Pengguna aktif: <?= (int)($deptUsers[$d] ?? 0) ?></div>
            </div>
          </div>
          <?php if ($isAdmin): ?>
            <span class="badge text-bg-secondary">Admin</span>
          <?php endif; ?>
        </div>

        <hr class="hr-soft my-3">

        <div class="row g-2">
          <div class="col-6">
            <div class="dept-metric">
              <div class="small text-muted">Fail</div>
              <div class="fw-bold fs-5"><?= (int)($deptFiles[$d] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="dept-metric">
              <div class="small text-muted">Dokumen</div>
              <div class="fw-bold fs-5"><?= (int)($deptDocs[$d] ?? 0) ?></div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <a class="btn btn-outline-primary w-50" href="<?= BASE_URL ?>/search.php?department=<?= urlencode($d) ?>">
            <i class="bi bi-search"></i> Cari Fail
          </a>

          <?php if ($isAdmin): ?>
            <a class="btn btn-primary w-50" href="<?= BASE_URL ?>/dashboard.php?dept=<?= urlencode($d) ?>">
              <i class="bi bi-speedometer2"></i> Lihat Dashboard
            </a>
          <?php else: ?>
            <a class="btn btn-primary w-50" href="<?= BASE_URL ?>/dashboard.php">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
          <?php endif; ?> 
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
