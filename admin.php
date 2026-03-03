<?php
require_once __DIR__ . '/includes/auth_check.php';
if (!is_admin()) { http_response_code(403); exit('Akses ditalat. Admin sahaja.'); }

require __DIR__ . '/includes/header.php';

$flash = null;

// Departments list (admin can choose IT Department here)
$departments = get_departments($pdo);
$departments = array_values(array_filter($departments, fn($d) => trim((string)$d) !== ''));

// Handle update user (role/department/status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
  $userId = (int)($_POST['user_id'] ?? 0);
  $role = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
  $department = trim((string)($_POST['department'] ?? ''));
  $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

  // validate dept exists
  if ($department !== '' && !in_array($department, $departments, true)) {
    $department = '';
  }

  // If dept is IT Department => role auto admin
  if ($department === 'IT Department') {
    $role = 'admin';
  }

  if ($userId > 0) {
    $st = $pdo->prepare("UPDATE users SET role=?, department=?, status=? WHERE id=?");
    $st->execute([$role, $department, $status, $userId]);
    $flash = "Pengguna dikemaskini.";
  }
}

// list users
$users = $pdo->query("
  SELECT id, username, email, full_name, role, department, status, created_at, last_login
  FROM users
  ORDER BY id ASC
")->fetchAll();

$myId = (int)($_SESSION['user']['id'] ?? 0);
$returnTo = BASE_URL . '/admin.php';
?>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="h3 mb-0">Admin Panel</div>
    <div class="text-muted small">Urus pengguna (akses penuh System Administrator).</div>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-success"><?= h($flash) ?></div>
<?php endif; ?>

<div class="card p-3 p-md-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="fw-semibold">Senarai Pengguna</div>
    <span class="badge text-bg-secondary"><?= count($users) ?> pengguna</span>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th style="width:70px;">ID</th>
          <th>Username</th>
          <th>Nama</th>
          <th style="width:110px;">Role</th>
          <th style="width:240px;">Jabatan</th>
          <th style="width:110px;">Status</th>
          <th class="text-end" style="width:220px;">Tindakan</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <?php $uid = (int)$u['id']; ?>
        <tr>
          <td><?= $uid ?></td>
          <td>
            <div class="fw-semibold"><?= h((string)$u['username']) ?></div>
            <div class="text-muted small"><?= h((string)$u['email']) ?></div>
          </td>
          <td><?= h((string)($u['full_name'] ?: '-')) ?></td>
          <td><span class="badge text-bg-secondary"><?= h((string)$u['role']) ?></span></td>
          <td><?= h((string)($u['department'] ?: '-')) ?></td>
          <td>
            <?php if (($u['status'] ?? '') === 'active'): ?>
              <span class="badge text-bg-success">active</span>
            <?php else: ?>
              <span class="badge text-bg-danger">inactive</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#edit-<?= $uid ?>">
              <i class="bi bi-pencil-square"></i> Edit
            </button>

            <?php if ($uid !== $myId): ?>
              <a class="btn btn-sm btn-outline-danger"
                 href="<?= BASE_URL ?>/delete_user.php?id=<?= $uid ?>&return_to=<?= urlencode($returnTo) ?>"
                 onclick="return confirm('Padam pengguna ini?');">
                <i class="bi bi-trash"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>

        <tr class="collapse-row">
          <td colspan="7" class="p-0 border-0">
            <div class="collapse" id="edit-<?= $uid ?>">
              <div class="admin-edit-wrap">
                <form method="post" class="admin-edit-form">
                  <input type="hidden" name="update_user" value="1">
                  <input type="hidden" name="user_id" value="<?= $uid ?>">

                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                      <label class="form-label mb-1">Role</label>
                      <select name="role" class="form-select">
                        <option value="staff" <?= ($u['role'] ?? '')==='staff'?'selected':'' ?>>staff</option>
                        <option value="admin" <?= ($u['role'] ?? '')==='admin'?'selected':'' ?>>admin</option>
                      </select>
                      <div class="text-muted small mt-1">Jika Jabatan = IT Department, role jadi admin automatik.</div>
                    </div>

                    <div class="col-12 col-md-5">
                      <label class="form-label mb-1">Jabatan</label>
                      <select name="department" class="form-select">
                        <option value="">-- pilih jabatan --</option>
                        <?php foreach ($departments as $d): ?>
                          <option value="<?= h((string)$d) ?>" <?= (($u['department'] ?? '')===$d)?'selected':'' ?>><?= h((string)$d) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-12 col-md-2">
                      <label class="form-label mb-1">Status</label>
                      <select name="status" class="form-select">
                        <option value="active" <?= ($u['status'] ?? '')==='active'?'selected':'' ?>>active</option>
                        <option value="inactive" <?= ($u['status'] ?? '')==='inactive'?'selected':'' ?>>inactive</option>
                      </select>
                    </div>

                    <div class="col-12 col-md-2 d-grid">
                      <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save"></i> Simpan
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </td>
        </tr>

      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
