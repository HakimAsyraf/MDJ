<?php
// includes/config.php
declare(strict_types=1);
session_start();

// Prevent "Cannot modify header information" warnings by buffering output.
// This allows redirect() to send headers even if some HTML was already started.
if (ob_get_level() === 0) {
  ob_start();
}


/** Auto-detect base URL from folder name in htdocs. */
if (!defined('BASE_URL')) {
  $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
  $base = rtrim($base, '/');
  if ($base === '' || $base === '.') $base = '';
  define('BASE_URL', $base);
}

define('MAX_UPLOAD_MB', 25);
define('FULL_FORM_DEPARTMENT', 'Jabatan Perancangan Pembangunan dan Landskap');
define('IT_DEPARTMENT', 'IT Department');
define('SCOPE_FILES_TO_DEPARTMENT', true);

/**
 * Register page uses this.
 * Set to false if you want ONLY admin to create accounts.
 */
if (!defined('ALLOW_SELF_REGISTER')) {
  define('ALLOW_SELF_REGISTER', true);
}

// ---------------- DB ----------------
$dbHost = 'localhost';
$dbName = 'mdj_tracking_db';
$dbUser = 'root';
$dbPass = '';

try {
  $pdo = new PDO(
    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  die('Database connection failed.');
}

// -------------- Helpers --------------
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('redirect')) {
if (!function_exists('redirect')) {
  function redirect(string $path): void {
    $url = BASE_URL . $path;

    // If output buffering is enabled, clear buffered output before redirect.
    if (!headers_sent()) {
      if (ob_get_level() > 0) {
        @ob_clean();
      }
      header('Location: ' . $url);
      exit;
    }

    // Fallback when headers already sent: JS + meta refresh redirect (no PHP warning).
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($url) . '"></noscript>';
    exit;
  }
}
  function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['csrf'];
  }
}
if (!function_exists('csrf_field')) {
  function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">'; }
}
if (!function_exists('csrf_verify')) {
  function csrf_verify(): void {
    $t = (string)($_POST['csrf'] ?? '');
    if ($t === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $t)) {
      http_response_code(403); die('CSRF token mismatch.');
    }
  }
}

if (!function_exists('flash_set')) {
  function flash_set(string $type, string $message): void { $_SESSION['flash'][] = ['type'=>$type,'message'=>$message]; }
}
if (!function_exists('flash_get_all')) {
  function flash_get_all(): array { $f = $_SESSION['flash'] ?? []; $_SESSION['flash'] = []; return is_array($f)?$f:[]; }
}

// -------------- Auth --------------
if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool { return !empty($_SESSION['user']); }
}
if (!function_exists('current_user')) {
  function current_user(): array { return $_SESSION['user'] ?? []; }
}

/** Admin check case-insensitive */
if (!function_exists('is_admin')) {
  function is_admin(): bool {
    $r = strtolower(trim((string)(current_user()['role'] ?? '')));
    return $r === 'admin';
  }
}

if (!function_exists('require_login')) {
  function require_login(): void { if (!is_logged_in()) redirect('/login.php'); }
}
if (!function_exists('require_admin')) {
  function require_admin(): void { require_login(); if (!is_admin()) { http_response_code(403); die('Forbidden'); } }
}
if (!function_exists('staff_department')) {
  function staff_department(): string { return (string)(current_user()['department'] ?? ''); }
}

/** Robust IT dept check */
if (!function_exists('is_it_department')) {
  function is_it_department(string $dept): bool {
    $d = strtolower(trim($dept));
    $it = strtolower(trim(IT_DEPARTMENT));
    return ($d === $it || $d === 'it');
  }
}
if (!function_exists('is_full_form_department')) {
  function is_full_form_department(string $dept): bool { return trim($dept) === FULL_FORM_DEPARTMENT; }
}

/** normalize_role */
if (!function_exists('normalize_role')) {
  function normalize_role(string $role, string $dept = ''): string {
    $r = strtolower(trim($role));
    $d = strtolower(trim($dept));

    if ($d === strtolower(trim(IT_DEPARTMENT)) || $d === 'it') return 'admin';
    if (in_array($r, ['admin','administrator','system administrator','sysadmin'], true)) return 'admin';
    return 'staff';
  }
}

// ---------- Date normalize ----------
if (!function_exists('normalize_date')) {
  function normalize_date(?string $input): string {
    $s = trim((string)$input);
    if ($s === '') return '';

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

    if (preg_match('/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})$/', $s, $m)) {
      $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
      if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
      return '';
    }

    $ts = strtotime($s);
    if ($ts !== false) return date('Y-m-d', $ts);
    return '';
  }
}

// ---------- DB Introspection ----------
if (!function_exists('db_table_exists')) {
if (!function_exists('db_table_exists')) {
  function db_table_exists(PDO $pdo, string $table): bool {
    $table = trim($table);
    if ($table === '') return false;
    try {
      // Avoid information_schema (some hosts restrict it).
      $st = $pdo->prepare("SHOW TABLES LIKE ?");
      $st->execute([$table]);
      return (bool)$st->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) {
      return false;
    }
  }
}
}
if (!function_exists('db_column_exists')) {
if (!function_exists('db_column_exists')) {
  function db_column_exists(PDO $pdo, string $table, string $column): bool {
    $table = trim($table);
    $column = trim($column);
    if ($table === '' || $column === '') return false;
    try {
      // Avoid information_schema (some hosts restrict it).
      $safeTable = str_replace('`', '``', $table);
      $st = $pdo->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE ?");
      $st->execute([$column]);
      return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      return false;
    }
  }
}
}

// ---------- Departments ----------
if (!function_exists('get_departments')) {
  function get_departments(PDO $pdo): array {
    try {
      $rows = $pdo->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll();
      $deps = array_map(fn($r) => (string)$r['name'], $rows);
      $deps = array_values(array_filter($deps, fn($d)=> trim((string)$d) !== ''));
      if ($deps) return $deps;
    } catch (Throwable $e) {}
    return [
      FULL_FORM_DEPARTMENT,
      'OSC','Kejuruteraan','Pentadbiran','Penguatkuasa','Kewangan','Penilaian',
      IT_DEPARTMENT
    ];
  }
}

if (!function_exists('department_code')) {
  function department_code(string $dept): string {
    $map = [
      FULL_FORM_DEPARTMENT => 'JPPL',
      'OSC' => 'OSC',
      'Kejuruteraan' => 'KEJ',
      'Pentadbiran' => 'PENT',
      'Penguatkuasa' => 'PKS',
      'Kewangan' => 'KEW',
      'Penilaian' => 'PNL',
      IT_DEPARTMENT => 'IT',
    ];
    return $map[$dept] ?? 'MDJ';
  }
}

if (!function_exists('department_accent')) {
  function department_accent(string $dept): string {
    return match ($dept) {
      FULL_FORM_DEPARTMENT => '#0ea5a6',
      'OSC' => '#2563eb',
      'Kejuruteraan' => '#9333ea',
      'Pentadbiran' => '#0f766e',
      'Penguatkuasa' => '#dc2626',
      'Kewangan' => '#16a34a',
      'Penilaian' => '#f59e0b',
      IT_DEPARTMENT => '#111827',
      default => '#64748b',
    };
  }
}
if (!function_exists('department_icon')) {
  function department_icon(string $dept): string {
    return match ($dept) {
      FULL_FORM_DEPARTMENT => 'bi-building',
      'OSC' => 'bi-door-open',
      'Kejuruteraan' => 'bi-gear',
      'Pentadbiran' => 'bi-clipboard-check',
      'Penguatkuasa' => 'bi-shield-check',
      'Kewangan' => 'bi-cash-coin',
      'Penilaian' => 'bi-graph-up-arrow',
      IT_DEPARTMENT => 'bi-hdd-network',
      default => 'bi-diagram-3',
    };
  }
}
if (!function_exists('department_mark_html')) {
  function department_mark_html(string $dept): string {
    $code = department_code($dept);
    $accent = department_accent($dept);
    $icon = department_icon($dept);

    return '<div class="dept-mark" style="--dept-accent:'.h($accent).'">'.
             '<div class="dept-mark__icon"><i class="bi '.h($icon).'"></i></div>'.
             '<div class="dept-mark__code">'.h($code).'</div>'.
           '</div>';
  }
}

// ---------- Settings ----------
if (!function_exists('ensure_user_settings')) {
  function ensure_user_settings(PDO $pdo, int $userId): void {
    $pdo->prepare("INSERT IGNORE INTO user_settings(user_id) VALUES(?)")->execute([$userId]);
  }
}
if (!function_exists('load_user_theme')) {
  function load_user_theme(PDO $pdo, int $userId): string {
    $st = $pdo->prepare("SELECT theme FROM user_settings WHERE user_id=? LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch();
    $theme = (string)($row['theme'] ?? 'light');
    return in_array($theme, ['light','dark'], true) ? $theme : 'light';
  }
}
if (!function_exists('set_theme_session')) {
  function set_theme_session(string $theme): void {
    $theme = ($theme === 'dark') ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) $_SESSION['user']['theme'] = $theme;
  }
}

// ---------- Asset loaders ----------
if (!function_exists('bootstrap_css_href')) {
  function bootstrap_css_href(): string {
    $local = __DIR__ . '/../assets/bootstrap.min.css';
    return file_exists($local) ? (BASE_URL . '/assets/bootstrap.min.css')
      : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
  }
}
if (!function_exists('bootstrap_js_href')) {
  function bootstrap_js_href(): string {
    $local = __DIR__ . '/../assets/bootstrap.bundle.min.js';
    return file_exists($local) ? (BASE_URL . '/assets/bootstrap.bundle.min.js')
      : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
  }
}
if (!function_exists('bootstrap_icons_href')) {
  function bootstrap_icons_href(): string {
    $local = __DIR__ . '/../assets/bootstrap-icons.css';
    return file_exists($local) ? (BASE_URL . '/assets/bootstrap-icons.css')
      : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css';
  }
}
if (!function_exists('chartjs_href')) {
  function chartjs_href(): string {
    $local = __DIR__ . '/../assets/chart.umd.min.js';
    return file_exists($local) ? (BASE_URL . '/assets/chart.umd.min.js')
      : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
  }
}

// ---------- Tambah Fail: Jabatan dropdown (remove IT Department) ----------
if (!function_exists('get_file_owner_departments')) {
  function get_file_owner_departments(PDO $pdo): array {
    $deps = get_departments($pdo);
    return array_values(array_filter($deps, function($d){
      return strtolower(trim((string)$d)) !== strtolower(trim(IT_DEPARTMENT));
    }));
  }
}

// ---------- FILE ACCESS ----------
if (!function_exists('get_file_row')) {
  function get_file_row(PDO $pdo, int $fileId): ?array {
    if ($fileId <= 0) return null;
    try {
      $st = $pdo->prepare("SELECT * FROM files WHERE id=? LIMIT 1");
      $st->execute([$fileId]);
      $row = $st->fetch();
      return $row ?: null;
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('can_access_file')) {
  function can_access_file(...$args): bool {
    $db = null;
    $fileId = 0;

    if (count($args) === 2 && $args[0] instanceof PDO) {
      $db = $args[0];
      $fileId = (int)$args[1];
    } elseif (count($args) === 1) {
      global $pdo;
      if ($pdo instanceof PDO) {
        $db = $pdo;
        $fileId = (int)$args[0];
      }
    } else {
      return false;
    }

    if (!($db instanceof PDO) || $fileId <= 0) return false;

    $me = current_user();
    if (!$me) return false;

    if (is_admin()) return true;
    if (is_it_department((string)($me['department'] ?? ''))) return true;

    if (!defined('SCOPE_FILES_TO_DEPARTMENT') || SCOPE_FILES_TO_DEPARTMENT !== true) return true;

    $file = get_file_row($db, $fileId);
    if (!$file) return false;

    $myDept = trim((string)($me['department'] ?? ''));
    if ($myDept === '') return false;

    $ownerDept = trim((string)($file['owner_department'] ?? $file['department_owner'] ?? $file['department'] ?? ''));
    if ($ownerDept !== '') return $ownerDept === $myDept;

    $createdBy = (int)($file['created_by'] ?? 0);
    $myId = (int)($me['id'] ?? 0);
    return ($createdBy > 0 && $myId > 0 && $createdBy === $myId);
  }
}

// ---------- MAIL HELPERS ----------
if (!function_exists('list_active_users')) {
  function list_active_users(PDO $pdo): array {
    try {
      $st = $pdo->query("
        SELECT id, username, full_name, department, status
        FROM users
        WHERE status='active'
        ORDER BY department ASC, full_name ASC, username ASC
      ");
      return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('list_departments_from_users')) {
  function list_departments_from_users(PDO $pdo): array {
    try {
      $st = $pdo->query("
        SELECT DISTINCT department
        FROM users
        WHERE status='active' AND department IS NOT NULL AND department <> ''
        ORDER BY department ASC
      ");
      $rows = $st->fetchAll() ?: [];
      return array_values(array_map(fn($r)=> (string)$r['department'], $rows));
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('mail_unread_count')) {
  function mail_unread_count(PDO $pdo, int $userId): int {
    if ($userId <= 0) return 0;
    try {
      $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM mail_recipients r
        INNER JOIN mail_messages m ON m.id = r.message_id
        WHERE r.recipient_id = ?
          AND r.is_read = 0
          AND r.deleted_by_recipient = 0
      ");
      $st->execute([$userId]);
      return (int)$st->fetchColumn();
    } catch (Throwable $e) {
      return 0;
    }
  }
}

// =============================
// NOTIFICATIONS (for bell icon)
// =============================
if (!function_exists('notifications_unread_count')) {
  function notifications_unread_count(PDO $pdo, int $userId): int {
    if ($userId <= 0) return 0;
    if (!db_table_exists($pdo, 'notifications')) return 0;
    try {
      $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
      $st->execute([$userId]);
      return (int)$st->fetchColumn();
    } catch (Throwable $e) {
      return 0;
    }
  }
}

if (!function_exists('notifications_latest')) {
  function notifications_latest(PDO $pdo, int $userId, int $limit = 5): array {
    if ($userId <= 0) return [];
    if (!db_table_exists($pdo, 'notifications')) return [];
    $limit = max(1, min(20, $limit));
    try {
      $st = $pdo->prepare("
        SELECT id, title, body, link_url, is_read, created_at
        FROM notifications
        WHERE user_id=?
        ORDER BY id DESC
        LIMIT {$limit}
      ");
      $st->execute([$userId]);
      return $st->fetchAll() ?: [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('notifications_create')) {
  function notifications_create(PDO $pdo, int $userId, string $title, string $body = '', string $linkUrl = ''): void {
    if ($userId <= 0) return;
    if (!db_table_exists($pdo, 'notifications')) return;
    try {
      $st = $pdo->prepare("
        INSERT INTO notifications(user_id, title, body, link_url, is_read, created_at)
        VALUES(?,?,?,?,0,NOW())
      ");
      $st->execute([$userId, $title, $body, $linkUrl]);
    } catch (Throwable $e) {
      // silent fail (don't break app)
    }
  }
}

if (!function_exists('notify_department_users')) {
  function notify_department_users(PDO $pdo, string $dept, string $title, string $body, string $linkUrl): void {
    $dept = trim($dept);
    if ($dept === '') return;
    if (!db_table_exists($pdo, 'users')) return;

    try {
      $st = $pdo->prepare("
        SELECT id FROM users
        WHERE status='active'
          AND department IS NOT NULL AND department <> ''
          AND TRIM(department)=?
      ");
      $st->execute([$dept]);
      $ids = $st->fetchAll() ?: [];
      foreach ($ids as $r) {
        $uid = (int)($r['id'] ?? 0);
        if ($uid > 0) notifications_create($pdo, $uid, $title, $body, $linkUrl);
      }
    } catch (Throwable $e) {}
  }
}

if (!function_exists('notify_admin_and_pentadbiran')) {
  function notify_admin_and_pentadbiran(PDO $pdo, string $title, string $body, string $linkUrl): void {
    if (!db_table_exists($pdo, 'users')) return;
    try {
      $st = $pdo->query("
        SELECT id FROM users
        WHERE status='active'
          AND (
            LOWER(TRIM(role))='admin'
            OR LOWER(TRIM(department))='pentadbiran'
          )
      ");
      $ids = $st->fetchAll() ?: [];
      foreach ($ids as $r) {
        $uid = (int)($r['id'] ?? 0);
        if ($uid > 0) notifications_create($pdo, $uid, $title, $body, $linkUrl);
      }
    } catch (Throwable $e) {}
  }
}

// =============================
// SURAT MENYURAT (core helpers)
// =============================
if (!function_exists('is_pentadbiran_department')) {
  function is_pentadbiran_department(string $dept): bool {
    return strtolower(trim($dept)) === 'pentadbiran';
  }
}

/**
 * MENU access (sidebar):
 * - Admin + Pentadbiran sahaja nampak menu "Daftar Surat Menyurat"
 * NOTE: Jabatan lain still boleh view/respond via notification link (surat_can_view).
 */
if (!function_exists('can_access_surat_module')) {
  function can_access_surat_module(): bool {
    $me = current_user();
    $dept = (string)($me['department'] ?? '');
    return is_admin() || is_pentadbiran_department($dept);
  }
}

if (!function_exists('surat_tables_ready')) {
  function surat_tables_ready(PDO $pdo): bool {
    return db_table_exists($pdo, 'surat_menyurat') && db_table_exists($pdo, 'surat_recipients');
  }
}

if (!function_exists('surat_upload_dir')) {
  function surat_upload_dir(): string {
    return __DIR__ . '/../uploads/surat';
  }
}

if (!function_exists('surat_allowed_ext')) {
  function surat_allowed_ext(): array {
    return ['pdf','doc','docx'];
  }
}

if (!function_exists('surat_store_upload')) {
  /**
   * Store uploaded attachment, returns array:
   * ['original_name','stored_name','ext','size','relative_path']
   */
  function surat_store_upload(array $file): array {
    if (!isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Upload gagal.');
    }

    $orig = (string)($file['name'] ?? '');
    $tmp  = (string)$file['tmp_name'];
    $size = (int)($file['size'] ?? 0);

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, surat_allowed_ext(), true)) {
      throw new RuntimeException('Jenis fail tidak dibenarkan (pdf/doc/docx sahaja).');
    }

    $maxBytes = (int)(MAX_UPLOAD_MB * 1024 * 1024);
    if ($size <= 0 || $size > $maxBytes) {
      throw new RuntimeException('Saiz fail melebihi had.');
    }

    $dir = surat_upload_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir)) throw new RuntimeException('Folder upload surat tidak wujud / gagal dibuat.');

    $stored = 'SURAT_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $stored;

    if (!move_uploaded_file($tmp, $dest)) {
      throw new RuntimeException('Gagal simpan fail.');
    }

    // relative path for DB (web root)
    $relative = 'uploads/surat/' . $stored;

    return [
      'original_name' => $orig,
      'stored_name'   => $stored,
      'ext'           => $ext,
      'size'          => $size,
      'relative_path' => $relative,
    ];
  }
}

if (!function_exists('surat_due_date')) {
  function surat_due_date(?string $tarikhTerima, ?int $tempohDays): ?string {
    $t = trim((string)$tarikhTerima);
    $d = (int)($tempohDays ?? 0);
    if ($t === '' || $d <= 0) return null;
    $ts = strtotime($t);
    if ($ts === false) return null;
    return date('Y-m-d', strtotime("+{$d} days", $ts));
  }
}

/**
 * VIEW access:
 * - Admin/Pentadbiran boleh view semua surat
 * - Jabatan lain boleh view surat yang dia terima (surat_recipients.recipient_department)
 */
if (!function_exists('surat_can_view')) {
  function surat_can_view(PDO $pdo, int $suratId): bool {
    if ($suratId <= 0) return false;
    if (!surat_tables_ready($pdo)) return false;

    if (can_access_surat_module()) return true;

    $me = current_user();
    $myDept = trim((string)($me['department'] ?? ''));
    if ($myDept === '') return false;

    try {
      $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM surat_recipients
        WHERE surat_id=? AND TRIM(recipient_department)=?
      ");
      $st->execute([$suratId, $myDept]);
      return ((int)$st->fetchColumn()) > 0;
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('surat_can_respond')) {
  function surat_can_respond(PDO $pdo, int $suratId): bool {
    // Jabatan penerima boleh respond. Admin/Pentadbiran juga boleh (optional).
    return surat_can_view($pdo, $suratId);
  }
}

/**
 * Dashboard count:
 * - Admin/Pentadbiran: kira semua surat yang belum selesai
 * - Jabatan lain: kira surat masuk untuk dept dia yang belum selesai
 */
if (!function_exists('surat_masuk_count')) {
  function surat_masuk_count(PDO $pdo, string $dept): int {
    if (!surat_tables_ready($pdo)) return 0;
    $dept = trim($dept);

    try {
      if (can_access_surat_module()) {
        // overall
        if (db_column_exists($pdo, 'surat_menyurat', 'status')) {
          $st = $pdo->query("SELECT COUNT(*) FROM surat_menyurat WHERE status <> 'Selesai'");
          return (int)$st->fetchColumn();
        }
        $st = $pdo->query("SELECT COUNT(*) FROM surat_menyurat");
        return (int)$st->fetchColumn();
      }

      if ($dept === '') return 0;

      $sql = "SELECT COUNT(*) FROM surat_recipients WHERE TRIM(recipient_department)=?";
      if (db_column_exists($pdo, 'surat_recipients', 'status')) {
        $sql .= " AND status <> 'Selesai'";
      }
      $st = $pdo->prepare($sql);
      $st->execute([$dept]);
      return (int)$st->fetchColumn();
    } catch (Throwable $e) {
      return 0;
    }
  }
}

/**
 * Ringkasan ikut jabatan penerima (untuk admin/pentadbiran dashboard)
 */
if (!function_exists('surat_ringkasan_by_department')) {
  function surat_ringkasan_by_department(PDO $pdo): array {
    if (!surat_tables_ready($pdo)) return [];

    // breakdown status
    $hasStatus = db_column_exists($pdo, 'surat_recipients', 'status');

    $sql = "
      SELECT
        recipient_department AS department,
        COUNT(*) AS jumlah,
        " . ($hasStatus ? "
        SUM(CASE WHEN status='Diterima' THEN 1 ELSE 0 END) AS diterima,
        SUM(CASE WHEN status='Diproses' THEN 1 ELSE 0 END) AS diproses,
        SUM(CASE WHEN status='Selesai' THEN 1 ELSE 0 END) AS selesai
        " : "
        0 AS diterima, 0 AS diproses, 0 AS selesai
        ") . "
      FROM surat_recipients
      GROUP BY recipient_department
      ORDER BY jumlah DESC, department ASC
    ";

    try {
      return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

/**
 * Update overall status in surat_menyurat based on recipients:
 * - If ALL recipients = Selesai -> Selesai
 * - Else if ANY Diproses -> Diproses
 * - Else -> Diterima
 */
if (!function_exists('surat_update_overall_status')) {
  function surat_update_overall_status(PDO $pdo, int $suratId): void {
    if ($suratId <= 0) return;
    if (!surat_tables_ready($pdo)) return;
    if (!db_column_exists($pdo, 'surat_menyurat', 'status')) return;

    try {
      $st = $pdo->prepare("SELECT status FROM surat_recipients WHERE surat_id=?");
      $st->execute([$suratId]);
      $rows = $st->fetchAll() ?: [];
      if (!$rows) return;

      $statuses = array_map(fn($r)=> strtolower(trim((string)($r['status'] ?? ''))), $rows);

      $allSelesai = true;
      $anyDiproses = false;
      foreach ($statuses as $s) {
        if ($s !== 'selesai') $allSelesai = false;
        if ($s === 'diproses') $anyDiproses = true;
      }

      $overall = 'Diterima';
      if ($allSelesai) $overall = 'Selesai';
      else if ($anyDiproses) $overall = 'Diproses';

      $stU = $pdo->prepare("UPDATE surat_menyurat SET status=?, updated_at=NOW() WHERE id=?");
      $stU->execute([$overall, $suratId]);
    } catch (Throwable $e) {
      // ignore
    }
  }
}

/** Notify recipient dept when new letter created */
if (!function_exists('surat_notify_new')) {
  function surat_notify_new(PDO $pdo, int $suratId, string $toDept, string $noFail = ''): void {
    $toDept = trim($toDept);
    if ($toDept === '') return;
    $link = "/surat_view.php?id=" . (int)$suratId;

    $title = "Surat Masuk Baru";
    $body  = $noFail !== '' ? ("No. Fail: {$noFail}") : "Anda menerima surat baru.";

    notify_department_users($pdo, $toDept, $title, $body, $link);
  }
}

/** Notify admin/pentadbiran when recipient updates status */
if (!function_exists('surat_notify_status_update')) {
  function surat_notify_status_update(PDO $pdo, int $suratId, string $dept, string $status): void {
    $link = "/surat_view.php?id=" . (int)$suratId;
    $title = "Kemas Kini Surat";
    $body  = "Jabatan {$dept} kemas kini status: {$status}";
    notify_admin_and_pentadbiran($pdo, $title, $body, $link);
  }
}
