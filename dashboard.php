<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$me = current_user();
$dept = (string)($me['department'] ?? '');
$uid  = (int)($me['id'] ?? 0);

// ---------------- KPI COUNTS ----------------
try {
  if (is_admin() || is_it_department($dept) || !defined('SCOPE_FILES_TO_DEPARTMENT') || SCOPE_FILES_TO_DEPARTMENT !== true) {
    $totalFiles = (int)$pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
  } else {
    $col = null;
    foreach (['owner_department','department_owner','department'] as $c) {
      try { $pdo->query("SELECT {$c} FROM files LIMIT 1"); $col = $c; break; } catch (Throwable $e) {}
    }

    if ($col) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM files WHERE {$col} = ?");
      $st->execute([$dept]);
      $totalFiles = (int)$st->fetchColumn();
    } else {
      $st = $pdo->prepare("SELECT COUNT(*) FROM files WHERE created_by = ?");
      $st->execute([$uid]);
      $totalFiles = (int)$st->fetchColumn();
    }
  }

  $totalDocs = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

  $totalUsers = 0;
  try { $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(); }
  catch (Throwable $e) { $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); }

  $myRole = (string)($me['role'] ?? 'staff');
} catch (Throwable $e) {
  $totalFiles = 0; $totalDocs = 0; $totalUsers = 0;
  $myRole = (string)($me['role'] ?? 'staff');
}

// Surat count (Admin/Pentadbiran only for card)
$canSurat = can_access_surat_module();
$suratMasuk = 0;
$suratRingkasan = [];
if ($canSurat) {
  try {
    $suratMasuk = surat_masuk_count($pdo, $dept);
    $suratRingkasan = surat_ringkasan_by_department($pdo);
  } catch (Throwable $e) {
    $suratMasuk = 0;
    $suratRingkasan = [];
  }
}

// ---------------- CHART DATA: FILES BY YEAR ----------------
$years = [];
$counts = [];

try {
  $tahunCol = null;
  foreach (['tahun','TAHUN','year'] as $c) {
    try { $pdo->query("SELECT {$c} FROM files LIMIT 1"); $tahunCol = $c; break; } catch (Throwable $e) {}
  }

  if ($tahunCol) {
    if (is_admin() || is_it_department($dept) || !defined('SCOPE_FILES_TO_DEPARTMENT') || SCOPE_FILES_TO_DEPARTMENT !== true) {
      $sql = "SELECT {$tahunCol} AS y, COUNT(*) AS c
              FROM files
              WHERE {$tahunCol} IS NOT NULL AND {$tahunCol} <> ''
              GROUP BY {$tahunCol}
              ORDER BY {$tahunCol} ASC";
      $rows = $pdo->query($sql)->fetchAll();
    } else {
      $deptCol = null;
      foreach (['owner_department','department_owner','department'] as $c) {
        try { $pdo->query("SELECT {$c} FROM files LIMIT 1"); $deptCol = $c; break; } catch (Throwable $e) {}
      }

      if ($deptCol) {
        $sql = "SELECT {$tahunCol} AS y, COUNT(*) AS c
                FROM files
                WHERE {$deptCol} = ?
                  AND {$tahunCol} IS NOT NULL AND {$tahunCol} <> ''
                GROUP BY {$tahunCol}
                ORDER BY {$tahunCol} ASC";
        $st = $pdo->prepare($sql);
        $st->execute([$dept]);
        $rows = $st->fetchAll();
      } else {
        $sql = "SELECT {$tahunCol} AS y, COUNT(*) AS c
                FROM files
                WHERE created_by = ?
                  AND {$tahunCol} IS NOT NULL AND {$tahunCol} <> ''
                GROUP BY {$tahunCol}
                ORDER BY {$tahunCol} ASC";
        $st = $pdo->prepare($sql);
        $st->execute([$uid]);
        $rows = $st->fetchAll();
      }
    }

    foreach ($rows as $r) {
      $y = trim((string)($r['y'] ?? ''));
      if ($y === '') continue;
      $years[]  = $y;
      $counts[] = (int)($r['c'] ?? 0);
    }
  }
} catch (Throwable $e) {
  $years = [];
  $counts = [];
}

$q = trim((string)($_GET['q'] ?? ''));
?>

<style>
  .stat-card { cursor: pointer; }
  .stat-card .stretched-link { position:absolute; inset:0; }
</style>

<div class="container-fluid p-0">

  <!-- TOP CARDS (CLICKABLE ACTIONS) -->
  <div class="row g-3 mb-3">

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card stat-card position-relative">
        <a class="stretched-link" href="<?= BASE_URL ?>/search.php" aria-label="Cari Fail"></a>
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Jumlah Fail</div>
            <div class="display-6 fw-bold"><?= (int)$totalFiles ?></div>
          </div>
          <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card stat-card position-relative">
        <a class="stretched-link" href="<?= BASE_URL ?>/search.php" aria-label="Lihat Fail & Dokumen"></a>
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Dokumen</div>
            <div class="display-6 fw-bold"><?= (int)$totalDocs ?></div>
          </div>
          <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card stat-card position-relative">
        <a class="stretched-link" href="<?= BASE_URL ?>/admin.php" aria-label="Admin Panel"></a>
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Pengguna Aktif</div>
            <div class="display-6 fw-bold"><?= (int)$totalUsers ?></div>
          </div>
          <div class="stat-icon"><i class="bi bi-people"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card stat-card position-relative">
        <a class="stretched-link" href="<?= BASE_URL ?>/profile.php" aria-label="Tetapan"></a>
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small">Peranan</div>
            <div class="display-6 fw-bold"><?= h($myRole) ?></div>
          </div>
          <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
        </div>
      </div>
    </div>

    <?php if ($canSurat): ?>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="card stat-card position-relative">
          <a class="stretched-link" href="<?= BASE_URL ?>/surat_masuk.php" aria-label="Daftar Surat Menyurat"></a>
          <div class="card-body d-flex align-items-center justify-content-between">
            <div>
              <div class="text-muted small">Surat Masuk</div>
              <div class="display-6 fw-bold"><?= (int)$suratMasuk ?></div>
            </div>
            <div class="stat-icon"><i class="bi bi-envelope-paper"></i></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <!-- MAIN ROW -->
  <div class="row g-3">
    <!-- ANALYTICS -->
    <div class="col-12 col-lg-7">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="fw-bold">Analytics (Fail mengikut tahun)</div>
          <div class="text-muted small">Auto</div>
        </div>
        <div class="card-body">
          <?php if (!$years): ?>
            <div class="text-muted">Tiada data tahun untuk dipaparkan.</div>
          <?php else: ?>
            <div style="height:320px;">
              <canvas id="filesByYearChart"></canvas>
            </div>
            <div class="small text-muted mt-2">Carta ini dipaparkan untuk rujukan ringkas.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- QUICK SEARCH -->
    <div class="col-12 col-lg-5">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="fw-bold">Carian Pantas</div>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/search.php">Buka Carian</a>
        </div>

        <div class="card-body">
          <form method="get" action="<?= BASE_URL ?>/dashboard.php" class="vstack gap-2">
            <label class="form-label mb-0">Keyword / No. Fail Permohonan</label>
            <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Contoh: 11/01/2022 atau PEDAH">

            <button class="btn btn-primary mt-2" type="submit">
              <i class="bi bi-search me-1"></i> Cari
            </button>

            <div class="small text-muted mt-2">
              Carian menyokong: tahun, kotak fail, no fail permohonan, lot/pt, mukim, aras, kabinet.
            </div>
          </form>

          <?php if ($q !== ''): ?>
            <hr>
            <div class="alert alert-info mb-0">
              Anda mencari: <b><?= h($q) ?></b><br>
              Klik “Buka Carian” untuk hasil penuh (dengan filter lengkap).
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($canSurat): ?>
    <div class="card mt-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div>
            <div class="fw-bold">Ringkasan Daftar Surat Menyurat Mengikut Jabatan</div>
            <div class="text-muted small">Untuk pemantauan surat yang diterima & status tindakan jabatan.</div>
          </div>
          <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/surat_masuk.php"><i class="bi bi-journal-text"></i> Buka Daftar Surat</a>
        </div>

        <?php if (!$suratRingkasan): ?>
          <div class="alert alert-warning mb-0">Tiada data ringkasan surat.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Jabatan</th>
                  <th class="text-center">Diterima</th>
                  <th class="text-center">Diproses</th>
                  <th class="text-center">Selesai</th>
                  <th class="text-center">Jumlah</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($suratRingkasan as $r): ?>
                  <tr>
                    <td class="fw-semibold"><?= h((string)($r['department'] ?? '-')) ?></td>
                    <td class="text-center"><?= (int)($r['diterima'] ?? 0) ?></td>
                    <td class="text-center"><?= (int)($r['diproses'] ?? 0) ?></td>
                    <td class="text-center"><?= (int)($r['selesai'] ?? 0) ?></td>
                    <td class="text-center fw-bold"><?= (int)($r['jumlah'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>
  <?php endif; ?>

</div>

<?php if ($years): ?>
  <script src="<?= h(chartjs_href()) ?>"></script>
  <script>
    (function(){
      const el = document.getElementById('filesByYearChart');
      if (!el || typeof Chart === 'undefined') return;

      const labels = <?= json_encode($years, JSON_UNESCAPED_UNICODE) ?>;
      const values = <?= json_encode($counts, JSON_UNESCAPED_UNICODE) ?>;

      new Chart(el, {
        type: 'line',
        data: { labels, datasets: [{ label: 'Jumlah Fail', data: values, tension: 0.25, fill: true }] },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
      });
    })();
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
