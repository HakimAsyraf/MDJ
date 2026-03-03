<?php
require_once __DIR__ . '/includes/auth_check.php';
// JANGAN include header.php lagi di sini (sebab ia output HTML)

$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) { header("Location: ".BASE_URL."/logout.php"); exit; }

// Ensure user_settings exists
$pdo->prepare("INSERT IGNORE INTO user_settings(user_id) VALUES(?)")->execute([$uid]);

// Save theme (RUN BEFORE OUTPUT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
  $theme = (($_POST['theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

  $st = $pdo->prepare("UPDATE user_settings SET theme=? WHERE user_id=?");
  $st->execute([$theme, $uid]);

  // update session theme so it applies instantly
  if (function_exists('set_theme_session')) {
    set_theme_session($theme);
  } else {
    $_SESSION['theme'] = $theme;
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
      $_SESSION['user']['theme'] = $theme;
    }
  }

  header("Location: ".BASE_URL."/profile.php");
  exit;
}

// OK, now safe to output HTML
require __DIR__ . '/includes/header.php';

// Load theme
$st = $pdo->prepare("SELECT theme FROM user_settings WHERE user_id=? LIMIT 1");
$st->execute([$uid]);
$row = $st->fetch();
$currentTheme = ($row && ($row['theme'] ?? '') === 'dark') ? 'dark' : 'light';

// Reload user fresh from DB (so jabatan/role updated shows here)
$st = $pdo->prepare("SELECT id, username, email, full_name, role, department, status, created_at, last_login
                     FROM users WHERE id=? LIMIT 1");
$st->execute([$uid]);
$me = $st->fetch();

if ($me) {
  // keep session in sync
  $_SESSION['user']['role'] = $me['role'];
  $_SESSION['user']['department'] = $me['department'];
  $_SESSION['user']['full_name'] = $me['full_name'];
  $_SESSION['user']['email'] = $me['email'];
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="h4 mb-0">Tetapan</div>
    <div class="text-muted small">Profil pengguna & tema sistem.</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card p-3 p-md-4">
      <div class="fw-semibold mb-2">Profil Pengguna</div>
      <hr class="hr-soft my-2">

      <?php if ($me): ?>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="small text-muted">Username</div>
            <div class="fw-semibold"><?= h($me['username']) ?></div>
          </div>

          <div class="col-12 col-md-6">
            <div class="small text-muted">Email</div>
            <div class="fw-semibold"><?= h($me['email']) ?></div>
          </div>

          <div class="col-12">
            <div class="small text-muted">Nama</div>
            <div class="fw-semibold"><?= h($me['full_name'] ?: '-') ?></div>
          </div>

          <div class="col-12 col-md-6">
            <div class="small text-muted">Peranan</div>
            <div class="fw-semibold text-uppercase"><?= h($me['role']) ?></div>
          </div>

          <div class="col-12 col-md-6">
            <div class="small text-muted">Jabatan</div>
            <div class="fw-semibold"><?= h($me['department'] ?: '-') ?></div>
          </div>

          <div class="col-12 col-md-6">
            <div class="small text-muted">Status</div>
            <div class="fw-semibold"><?= h($me['status'] ?: '-') ?></div>
          </div>

          <div class="col-12 col-md-6">
            <div class="small text-muted">Last Login</div>
            <div class="fw-semibold"><?= h($me['last_login'] ?: '-') ?></div>
          </div>
        </div>
      <?php else: ?>
        <div class="text-danger">Profil tidak dijumpai.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card p-3 p-md-4">
      <div class="fw-semibold mb-2">Tema Sistem</div>
      <div class="text-muted small">Tukar tema sistem.</div>
      <hr class="hr-soft my-2">

      <form method="post" class="vstack gap-3">
        <div class="d-flex gap-4">
          <label class="d-flex align-items-center gap-2">
            <input type="radio" name="theme" value="light" <?= $currentTheme==='light'?'checked':'' ?>>
            <span>Light</span>
          </label>

          <label class="d-flex align-items-center gap-2">
            <input type="radio" name="theme" value="dark" <?= $currentTheme==='dark'?'checked':'' ?>>
            <span>Dark</span>
          </label>
        </div>

        <div>
          <button class="btn btn-primary" type="submit" name="save_theme" value="1">
            <i class="bi bi-save"></i> Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
