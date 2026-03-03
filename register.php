<?php
require_once __DIR__ . '/includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists('h')) {
  function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/**
 * If you later want to disable self registration:
 * define('ALLOW_SELF_REGISTER', false); in includes/config.php
 *
 * This file will NOT crash if the constant is missing.
 */
$allowSelfRegister = defined('ALLOW_SELF_REGISTER') ? (bool)ALLOW_SELF_REGISTER : true;

if (!$allowSelfRegister) {
  http_response_code(403);
  echo "Pendaftaran pengguna ditutup. Sila hubungi System Administrator.";
  exit;
}

// If already logged in, go dashboard
if (function_exists('is_logged_in') && is_logged_in()) {
  header("Location: " . BASE_URL . "/dashboard.php");
  exit;
}

// Department list (exclude IT Department from self-register)
$JPPL_NAME = 'Jabatan Perancangan Pembangunan dan Landskap';
$deptList = [];

if (function_exists('get_departments')) {
  $deptList = get_departments($pdo);
} else {
  // fallback if helper is not present
  $deptList = [
    $JPPL_NAME,
    'OSC',
    'Kejuruteraan',
    'Pentadbiran',
    'Penguatkuasa',
    'Kewangan',
    'Penilaian',
    'IT Department',
  ];
}

$deptList = array_values(array_filter($deptList, function($d){
  $d = trim((string)$d);
  return $d !== '' && $d !== 'IT Department'; // remove IT from registration
}));

$error = '';
$success = '';
$email = '';
$full_name = '';
$department = '';

/** Create a safe username from email and ensure uniqueness */
function generate_unique_username(PDO $pdo, string $email): string {
  $base = strtolower(trim(explode('@', $email)[0] ?? 'user'));
  $base = preg_replace('/[^a-z0-9_\.]/', '_', $base);
  $base = preg_replace('/\.+/', '.', $base);
  $base = trim($base, '._');
  if ($base === '') $base = 'user';

  $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
  $st->execute([$base]);
  if ((int)$st->fetchColumn() === 0) return $base;

  for ($i=2; $i<=50; $i++) {
    $candidate = $base . $i;
    $st->execute([$candidate]);
    if ((int)$st->fetchColumn() === 0) return $candidate;
  }
  // final fallback
  return $base . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  $pass2 = (string)($_POST['password2'] ?? '');
  $full_name = trim((string)($_POST['full_name'] ?? ''));
  $department = trim((string)($_POST['department'] ?? ''));

  // Basic validation
  if ($email === '' || $pass === '' || $pass2 === '') {
    $error = "Sila lengkapkan maklumat pendaftaran.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Format email tidak sah.";
  } elseif (!preg_match('/@mdj\.gov\.my$/i', $email)) {
    $error = "Sila gunakan email MDJ (contoh: nama@mdj.gov.my).";
  } elseif ($pass !== $pass2) {
    $error = "Password tidak sepadan.";
  } elseif (strlen($pass) < 8) {
    $error = "Password mesti sekurang-kurangnya 8 aksara.";
  } elseif ($department === '' || !in_array($department, $deptList, true)) {
    $error = "Sila pilih jabatan yang betul.";
  } else {
    // Prevent IT Department registration (safety)
    if ($department === 'IT Department') {
      $error = "IT Department tidak dibenarkan daftar sebagai staff.";
    } else {
      // Check email exists
      $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
      $st->execute([$email]);
      if ((int)$st->fetchColumn() > 0) {
        $error = "Email ini sudah didaftarkan. Sila log masuk.";
      } else {
        // Create user
        $username = generate_unique_username($pdo, $email);
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        try {
          $pdo->beginTransaction();

          $st = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, department, status)
            VALUES (?, ?, ?, ?, 'staff', ?, 'active')
          ");
          $st->execute([$username, $email, $hash, $full_name, $department]);

          $newId = (int)$pdo->lastInsertId();

          // user_settings default
          $pdo->prepare("INSERT IGNORE INTO user_settings(user_id, theme) VALUES(?, 'light')")
              ->execute([$newId]);

          $pdo->commit();

          header("Location: " . BASE_URL . "/login.php?registered=1");
          exit;

        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $error = "Gagal daftar. Sila cuba semula atau hubungi admin.";
        }
      }
    }
  }
}

?><!doctype html>
<html lang="ms" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar | MDJ Tracking System</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=1">

  <style>
    :root{
      --auth-bg:#f6f8fb;
      --auth-panel:#ffffff;
      --auth-text:#0b1220;
      --auth-muted:#5f6b7a;
      --auth-border:#d6dde7;
      --auth-shadow:0 18px 50px rgba(2,6,23,.10);
      --auth-brand:#0ea5a6;
      --auth-brand2:#0d6efd;
      --radius:18px;
    }
    body{ background:var(--auth-bg); color:var(--auth-text); min-height:100vh; }
    .auth-wrap{ min-height:100vh; display:grid; grid-template-columns: 1.15fr 0.85fr; }
    @media (max-width: 992px){ .auth-wrap{ grid-template-columns:1fr; } .auth-left{ display:none; } .auth-right{ padding:24px; } }

    .auth-left{
      padding:32px; display:flex; align-items:center; justify-content:center;
      background:
        radial-gradient(1200px 500px at 25% 15%, rgba(14,165,166,.18), transparent 55%),
        radial-gradient(900px 500px at 55% 35%, rgba(13,110,253,.10), transparent 55%),
        linear-gradient(180deg, #eef4ff, #f6f8fb);
      border-right:1px solid var(--auth-border);
    }
    .hero{
      width:min(720px,92%);
      background:rgba(255,255,255,.72);
      border:1px dashed rgba(2,6,23,.18);
      border-radius:22px;
      padding:34px;
      box-shadow:0 12px 40px rgba(2,6,23,.08);
    }
    .hero-logo{
      height:260px; border-radius:18px;
      border:1px dashed rgba(2,6,23,.20);
      display:flex; align-items:center; justify-content:center;
      color:var(--auth-muted);
      background:rgba(255,255,255,.55);
      margin-bottom:22px;
      font-size:1.05rem;
    }
    .hero-title{ font-weight:900; letter-spacing:.2px; font-size:1.55rem; line-height:1.25; margin-bottom:6px; }
    .hero-sub{ color:var(--auth-muted); font-weight:600; letter-spacing:.04em; }

    .auth-right{ padding:32px; display:flex; align-items:center; justify-content:center; }
    .auth-card{
      width:min(560px,94vw);
      background:var(--auth-panel);
      border:1px solid var(--auth-border);
      border-radius:22px;
      box-shadow:var(--auth-shadow);
      padding:28px;
    }
    .auth-top{ display:flex; align-items:center; justify-content:center; flex-direction:column; gap:10px; margin-bottom:16px; }
    .auth-logo{
      width:92px; height:92px; border-radius:24px;
      border:1px dashed rgba(2,6,23,.25);
      background:rgba(14,165,166,.08);
      display:flex; align-items:center; justify-content:center;
      color:#0f766e; font-weight:900; position:relative; overflow:hidden;
    }
    .auth-logo::after{
      content:""; position:absolute; inset:-30px;
      background:
        radial-gradient(circle at 30% 30%, rgba(14,165,166,.25), transparent 55%),
        radial-gradient(circle at 70% 70%, rgba(13,110,253,.18), transparent 55%);
      pointer-events:none;
    }
    .auth-logo span{ position:relative; z-index:1; }
    .auth-title{ color:var(--auth-brand); font-weight:900; font-size:1.45rem; margin:0; }
    .auth-note{ font-size:.9rem; color:var(--auth-muted); margin:0; }

    .input-group-text{ background:#f2f6fb; border-color:var(--auth-border); }
    .form-control, .form-select{ border-color:var(--auth-border); border-radius:12px; padding:12px 12px; }
    .btn-auth{
      border-radius:12px; padding:12px 14px; font-weight:800;
      background:linear-gradient(135deg, var(--auth-brand2), var(--auth-brand));
      border:none;
    }
    .btn-auth:hover{ filter:brightness(1.03); }
    .auth-footer{ text-align:center; margin-top:14px; color:var(--auth-muted); font-size:.95rem; }
  </style>
</head>

<body>
  <div class="auth-wrap">
    <div class="auth-left">
      <div class="hero">
        <div class="hero-logo">Logo Kerajaan</div>
        <div class="hero-title">MAJLIS DAERAH JERANTUT<br>TRACKING SYSTEM</div>
        <div class="hero-sub">Pendaftaran Akaun Staff</div>
      </div>
    </div>

    <div class="auth-right">
      <div class="auth-card">
        <div class="auth-top">
          <div class="auth-logo" title="Logo Kerajaan"><span>LOGO</span></div>
          <h1 class="auth-title">Daftar</h1>
          <p class="auth-note">Gunakan email MDJ & pilih jabatan.</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3">
          <div>
            <label class="form-label">Nama (optional)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input class="form-control" name="full_name" value="<?= h($full_name) ?>" placeholder="Nama penuh">
            </div>
          </div>

          <div>
            <label class="form-label">Email (MDJ)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input class="form-control" type="email" name="email" value="<?= h($email) ?>" placeholder="nama@mdj.gov.my" required>
            </div>
          </div>

          <div>
            <label class="form-label">Jabatan</label>
            <select class="form-select" name="department" required>
              <option value="" disabled <?= $department===''?'selected':'' ?>>-- pilih jabatan --</option>
              <?php foreach ($deptList as $d): ?>
                <option value="<?= h($d) ?>" <?= $department===$d?'selected':'' ?>><?= h($d) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="text-muted small mt-1">IT Department tidak tersenarai (akaun IT diurus oleh admin).</div>
          </div>

          <div>
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input class="form-control" type="password" name="password" placeholder="Minimum 8 aksara" required>
            </div>
          </div>

          <div>
            <label class="form-label">Sahkan Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
              <input class="form-control" type="password" name="password2" placeholder="Ulang password" required>
            </div>
          </div>

          <button class="btn btn-primary btn-auth w-100" type="submit">
            Daftar Akaun
          </button>

          <div class="auth-footer">
            Dah ada akaun? <a href="<?= BASE_URL ?>/login.php">Log Masuk</a>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
