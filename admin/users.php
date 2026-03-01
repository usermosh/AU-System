<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('admin');

$db = getDB();

$users = $db->query("
    SELECT u.*, s.student_number, s.first_name, s.last_name, s.course,
           d.department_name
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    LEFT JOIN departments d ON d.user_id = u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE user_id IS NULL AND is_active=1")->fetchAll();

$pageTitle = 'User Management';
$activeNav = 'users.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('addUserModal')">+ Add User Account</button>
</div>

<div id="pageAlert" class="alert" style="display:none;"></div>

<div class="card">
  <div class="card-header">
    <span class="card-title">All User Accounts (<?= count($users) ?>)</span>
    <input type="text" id="searchInput" class="form-control" style="width:220px; padding:7px 12px; font-size:13px;" placeholder="Search users..." oninput="searchTable()">
  </div>
  <div class="card-body" style="padding: 0; overflow-x: auto;">
    <table class="data-table" id="usersTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Name / Info</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr data-search="<?= strtolower(htmlspecialchars($u['username'] . ' ' . $u['email'] . ' ' . ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' . ($u['student_number'] ?? ''))) ?>">
            <td style="font-size: 11px; color: var(--gray);">#<?= $u['id'] ?></td>
            <td style="font-weight: 600;"><?= htmlspecialchars($u['username']) ?></td>
            <td>
              <?php if ($u['role'] === 'student' && $u['first_name']): ?>
                <div style="font-size: 13px;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                <div style="font-size: 11px; color: var(--gray);"><?= htmlspecialchars($u['student_number'] ?? '') ?> · <?= htmlspecialchars($u['course'] ?? '') ?></div>
              <?php elseif ($u['role'] === 'department' && $u['department_name']): ?>
                <div style="font-size: 13px;"><?= htmlspecialchars($u['department_name']) ?></div>
              <?php else: ?>
                <span style="color: var(--gray); font-size: 12px;">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size: 12px;"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <?php
              $roleColors = ['student'=>'info','department'=>'purple','registrar'=>'warning','admin'=>'gray'];
              ?>
              <span class="badge badge-<?= $roleColors[$u['role']] ?? 'gray' ?>"><?= ucfirst($u['role']) ?></span>
            </td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="font-size: 12px; color: var(--gray);"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div style="display: flex; gap: 4px;">
                <button class="btn btn-sm" style="background:#e8f0fe;color:#1252a3;border:1px solid #90aee4;" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">Edit</button>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                  <button class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>" onclick="toggleUser(<?= $u['id'] ?>, <?= $u['is_active'] ?>)">
                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Add User Account</span>
      <button class="modal-close" onclick="closeModal('addUserModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="addUserMsg" class="alert" style="display:none;"></div>
      <form id="addUserForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-row">
          <div class="form-group">
            <label>Username *</label>
            <input type="text" class="form-control" name="username" required>
          </div>
          <div class="form-group">
            <label>Role *</label>
            <select class="form-control" name="role" required onchange="toggleDeptField(this.value)">
              <option value="registrar">Registrar</option>
              <option value="department">Department</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" class="form-control" name="email" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Password *</label>
            <input type="password" class="form-control" name="password" required minlength="8">
          </div>
          <div class="form-group">
            <label>Confirm Password *</label>
            <input type="password" class="form-control" name="confirm_password" required>
          </div>
        </div>
        <div class="form-group" id="deptField" style="display:none;">
          <label>Assign Department *</label>
          <select class="form-control" name="department_id">
            <option value="">— Select Department —</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('addUserModal')">Cancel</button>
      <button class="btn btn-primary" id="addUserBtn" onclick="addUser()">Create Account</button>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
  <div class="modal-box" style="max-width: 440px;">
    <div class="modal-header">
      <span class="modal-title">Edit User</span>
      <button class="modal-close" onclick="closeModal('editUserModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="editUserMsg" class="alert" style="display:none;"></div>
      <form id="editUserForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="user_id" id="editUserId">
        <div class="form-group">
          <label>Username</label>
          <input type="text" class="form-control" name="username" id="editUsername" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" class="form-control" name="email" id="editEmail" required>
        </div>
        <div class="form-group">
          <label>New Password <span style="font-weight: 400; color: var(--gray);">(leave blank to keep current)</span></label>
          <input type="password" class="form-control" name="password" minlength="8">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('editUserModal')">Cancel</button>
      <button class="btn btn-primary" id="saveUserBtn" onclick="saveUser()">Save Changes</button>
    </div>
  </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }

function searchTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#usersTable tbody tr').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
}

function toggleDeptField(val) {
  document.getElementById('deptField').style.display = val === 'department' ? 'block' : 'none';
}

function editUser(u) {
  document.getElementById('editUserId').value = u.id;
  document.getElementById('editUsername').value = u.username;
  document.getElementById('editEmail').value = u.email;
  openModal('editUserModal');
}

function showMsg(id, msg, type) {
  const el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
}

function showPageAlert(msg, type) {
  const el = document.getElementById('pageAlert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth' });
  setTimeout(() => el.style.display = 'none', 6000);
}

async function addUser() {
  const btn = document.getElementById('addUserBtn');
  btn.disabled = true; btn.textContent = 'Creating...';
  const form = document.getElementById('addUserForm');
  if (form.password.value !== form.confirm_password.value) {
    showMsg('addUserMsg', 'Passwords do not match.', 'error');
    btn.disabled = false; btn.textContent = 'Create Account'; return;
  }
  const fd = new FormData(form);
  fd.append('action', 'create_user');
  try {
    const res = await fetch('<?= APP_URL ?>/ajax/admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closeModal('addUserModal');
      showPageAlert(data.message, 'success');
      setTimeout(() => location.reload(), 800);
    } else { showMsg('addUserMsg', data.message, 'error'); }
  } catch { showMsg('addUserMsg', 'Network error.', 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Create Account'; }
}

async function saveUser() {
  const btn = document.getElementById('saveUserBtn');
  btn.disabled = true; btn.textContent = 'Saving...';
  const fd = new FormData(document.getElementById('editUserForm'));
  fd.append('action', 'update_user');
  try {
    const res = await fetch('<?= APP_URL ?>/ajax/admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closeModal('editUserModal');
      showPageAlert(data.message, 'success');
      setTimeout(() => location.reload(), 800);
    } else { showMsg('editUserMsg', data.message, 'error'); }
  } catch { showMsg('editUserMsg', 'Network error.', 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Save Changes'; }
}

async function toggleUser(userId, currentStatus) {
  const action = currentStatus ? 'deactivate' : 'activate';
  if (!confirm(`Are you sure you want to ${action} this user?`)) return;
  try {
    const fd = new FormData();
    fd.append('action', 'toggle_user');
    fd.append('user_id', userId);
    fd.append('csrf_token', '<?= csrfToken() ?>');
    const res = await fetch('<?= APP_URL ?>/ajax/admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    showPageAlert(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
  } catch { showPageAlert('Network error.', 'error'); }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
