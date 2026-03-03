<?php
require_once __DIR__ . '/includes/config.php';

if (!function_exists('h')) {
  function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// If already logged in, go dashboard
if (function_exists('is_logged_in') && is_logged_in()) {
  header("Location: " . BASE_URL . "/dashboard.php");
  exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  if ($email === '' || $pass === '') {
    $error = 'Sila masukkan email dan password.';
  } else {
    $st = $pdo->prepare("SELECT id, username, email, password, full_name, role, department, status FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u || !password_verify($pass, $u['password'])) {
      $error = 'Email atau password tidak sah.';
    } elseif (($u['status'] ?? 'active') !== 'active') {
      $error = 'Akaun anda tidak aktif. Sila hubungi System Administrator.';
    } else {
      // If IT Department => force admin
      if (($u['department'] ?? '') === 'IT Department') {
        $u['role'] = 'admin';
      }

      // Save session
      $_SESSION['user'] = [
        'id' => (int)$u['id'],
        'username' => (string)($u['username'] ?? ''),
        'email' => (string)($u['email'] ?? ''),
        'full_name' => (string)($u['full_name'] ?? ''),
        'role' => (string)($u['role'] ?? 'staff'),
        'department' => (string)($u['department'] ?? ''),
        'status' => (string)($u['status'] ?? 'active')
      ];

      // Load theme from user_settings (default light)
      $uid = (int)$u['id'];
      $pdo->prepare("INSERT IGNORE INTO user_settings(user_id) VALUES(?)")->execute([$uid]);
      $st2 = $pdo->prepare("SELECT theme FROM user_settings WHERE user_id=? LIMIT 1");
      $st2->execute([$uid]);
      $row = $st2->fetch(PDO::FETCH_ASSOC);
      $_SESSION['theme'] = (($row['theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

      // Update last_login
      $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id=?")->execute([$uid]);

      header("Location: " . BASE_URL . "/dashboard.php");
      exit;
    }
  }
}

?><!doctype html>
<html lang="ms" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log Masuk | MDJ Tracking System</title>

  <!-- Bootstrap + Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Your system CSS (must exist) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css?v=1">

  <style>
    :root{
      --auth-bg: #f6f8fb;
      --auth-panel: #ffffff;
      --auth-text: #0b1220;
      --auth-muted: #5f6b7a;
      --auth-border: #d6dde7;
      --auth-shadow: 0 18px 50px rgba(2,6,23,.10);
      --auth-brand: #0ea5a6;
      --auth-brand2:#0d6efd;
      --radius: 18px;
    }

    body{
      background: var(--auth-bg);
      color: var(--auth-text);
      min-height: 100vh;
    }

    .auth-wrap{
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
    }

    @media (max-width: 992px){
      .auth-wrap{ grid-template-columns: 1fr; }
      .auth-left{ display:none; }
      .auth-right{ padding: 24px; }
    }

    .auth-left{
      padding: 32px;
      display:flex;
      align-items:center;
      justify-content:center;
      background: radial-gradient(1200px 500px at 25% 15%, rgba(14,165,166,.18), transparent 55%),
                  radial-gradient(900px 500px at 55% 35%, rgba(13,110,253,.10), transparent 55%),
                  linear-gradient(180deg, #eef4ff, #f6f8fb);
      border-right: 1px solid var(--auth-border);
      position: relative;
      overflow:hidden;
    }

    .auth-left .hero{
      width: min(700px, 92%);
      background: rgba(255,255,255,.72);
      border: 0px dashed rgba(2,6,23,.18);
      border-radius: 20px;
      padding: 9px;
      box-shadow: 0 12px 40px rgba(2,6,23,.08);
    }

    .hero-logo{
      height: 260px;
      border-radius: 18px;
      border: 1px dashed rgba(2,6,23,.20);
      display:flex;
      align-items:center;
      justify-content:center;
      color: var(--auth-muted);
      background: rgba(255,255,255,.55);
      margin-bottom: 22px;
      font-size: 1.05rem;
    }

    .hero-title{
      font-weight: 900;
      letter-spacing: .2px;
      font-size: 1.55rem;
      line-height: 1.25;
      margin-bottom: 6px;
    }

    .hero-sub{
      color: var(--auth-muted);
      font-weight: 600;
      letter-spacing: .04em;
    }

    .auth-right{
      padding: 32px;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    .auth-card{
      width: min(520px, 94vw);
      background: var(--auth-panel);
      border: 1px solid var(--auth-border);
      border-radius: 22px;
      box-shadow: var(--auth-shadow);
      padding: 28px;
    }

    .auth-top{
      display:flex;
      align-items:center;
      justify-content:center;
      flex-direction:column;
      gap: 10px;
      margin-bottom: 16px;
    }

    /* LOGO BOX — keep same style, but show PNG inside */
    .auth-logo{
      width: 92px;
      height: 92px;
      border-radius: 24px;
      border: 1px dashed rgba(2,6,23,.25);
      background: rgba(14,165,166,.08);
      display:flex;
      align-items:center;
      justify-content:center;
      position: relative;
      overflow:hidden;
    }

    .auth-logo::after{
      content:"";
      position:absolute;
      inset:-30px;
      background: radial-gradient(circle at 30% 30%, rgba(14,165,166,.25), transparent 55%),
                  radial-gradient(circle at 70% 70%, rgba(13,110,253,.18), transparent 55%);
      pointer-events:none;
    }

    .auth-logo img{
      position: relative;
      z-index: 1;
      width: 72px;
      height: 72px;
      object-fit: contain;
      display:block;
      filter: drop-shadow(0 6px 14px rgba(2,6,23,.18));
    }

    .auth-title{
      color: var(--auth-brand);
      font-weight: 900;
      font-size: 1.45rem;
      margin: 0;
    }

    .auth-note{
      font-size:.9rem;
      color: var(--auth-muted);
      margin: 0;
    }

    .input-group-text{
      background: #f2f6fb;
      border-color: var(--auth-border);
    }

    .form-control{
      border-color: var(--auth-border);
      border-radius: 12px;
      padding: 12px 12px;
    }

    .btn-auth{
      border-radius: 12px;
      padding: 12px 14px;
      font-weight: 800;
      background: linear-gradient(135deg, var(--auth-brand2), var(--auth-brand));
      border: none;
    }

    .btn-auth:hover{
      filter: brightness(1.03);
    }

    .auth-footer{
      text-align:center;
      margin-top: 14px;
      color: var(--auth-muted);
      font-size: .95rem;
    }
  </style>
</head>

<body>
  <div class="auth-wrap">
    <!-- LEFT HERO -->
    <div class="auth-left">
      <div class="hero">
        <div class="hero-logo">
          <img src="<?= BASE_URL ?>/assets/logo_kerajaan.png" alt="Logo Kerajaan">
        </div>
        <div class="hero-title">MAJLIS DAERAH JERANTUT<br>TRACKING SYSTEM</div>
        <div class="hero-sub">Sistem Pengesanan Fail & Dokumen (Word/PDF)</div>
      </div>
    </div>

    <!-- RIGHT LOGIN -->
    <div class="auth-right">
      <div class="auth-card">
        <div class="auth-top">
          <div class="auth-logo" title="Logo MDJ">
            <img src="<?= BASE_URL ?>/assets/mdj_logo.png" alt="Logo MDJ">
          </div>
          <h1 class="auth-title">Log Masuk</h1>
          <p class="auth-note">Gunakan akaun email MDJ.</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3">
          <div>
            <label class="form-label">Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input class="form-control" type="email" name="email" value="<?= h($email) ?>" placeholder="nama@mdj.gov.my" required>
            </div>
          </div>

          <div>
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input class="form-control" type="password" name="password" placeholder="••••••••" required>
            </div>
          </div>

          <button class="btn btn-primary btn-auth w-100" type="submit">
            Log Masuk
          </button>

          <div class="auth-footer">
            Tiada akaun? <a href="<?= BASE_URL ?>/register.php">Daftar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
