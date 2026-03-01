<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('department');

$db     = getDB();
$userId = $_SESSION['user_id'];
$deptId = $_SESSION['dept_id'];

$stmt = $db->prepare("SELECT u.*, d.department_name, d.department_code, d.description FROM users u JOIN departments d ON d.user_id = u.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'My Profile';
$activeNav = 'profile.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div id="pageAlert" class="alert" style="display:none;"></div>

<div style="display:grid; grid-template-columns: 1fr 1.6fr; gap:20px;">

    <!-- Profile Summary -->
    <div class="card" style="text-align:center; padding:32px 24px; height:fit-content;">
        <div style="
            width:90px; height:90px; border-radius:50%;
            background: linear-gradient(135deg, #1252a3, #1565c0);
            display:flex; align-items:center; justify-content:center;
            margin: 0 auto 16px;
            font-family:'Playfair Display',serif;
            font-size:32px; font-weight:900; color:#90caf9;
            box-shadow: 0 8px 24px rgba(18,82,163,0.3);
        ">
            <?= strtoupper(substr($user['department_code'] ?? 'D', 0, 2)) ?>
        </div>
        <div style="font-family:'Playfair Display',serif; font-size:18px; font-weight:700; color:var(--navy);">
            <?= htmlspecialchars($user['department_name'] ?? '') ?>
        </div>
        <div style="font-size:13px; color:var(--gray); margin-top:4px;">Department Account</div>
        <div style="margin-top:12px;">
            <span class="badge badge-purple"><?= htmlspecialchars($user['department_code'] ?? '') ?></span>
            <span class="badge badge-success" style="margin-left:6px;">Active</span>
        </div>
        <div style="margin-top:20px; padding:14px; background:var(--white); border-radius:10px; text-align:left;">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray); margin-bottom:10px;">Account Info</div>
            <div style="font-size:13px; margin-bottom:8px;">
                <span style="color:var(--gray);">Username:</span>
                <strong style="float:right;">@<?= htmlspecialchars($user['username']) ?></strong>
            </div>
            <div style="font-size:13px; margin-bottom:8px;">
                <span style="color:var(--gray);">Dept Code:</span>
                <strong style="float:right; font-family:monospace;"><?= htmlspecialchars($user['department_code'] ?? '') ?></strong>
            </div>
            <div style="font-size:13px;">
                <span style="color:var(--gray);">Since:</span>
                <strong style="float:right;"><?= date('M Y', strtotime($user['created_at'])) ?></strong>
            </div>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Account Settings -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">⚙️ Account Settings</span>
            </div>
            <div class="card-body">
                <form id="accountForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="update_account">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" class="form-control" name="username"
                               value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" class="form-control" name="email"
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <button type="button" class="btn btn-primary" id="saveAccountBtn" onclick="saveAccount()">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Department Info (read-only) -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🏛 Department Information</span>
                <span style="font-size:12px; color:var(--gray);">Contact admin to update</span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Department Name</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['department_name'] ?? '') ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department Code</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['department_code'] ?? '') ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['description'] ?? '—') ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                    </div>
                </div>
                <div class="alert alert-info" style="display:block; font-size:12px;">
                    ℹ️ Department details can only be modified by the System Administrator.
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔒 Change Password</span>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="change_password_staff">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" class="form-control" name="current_password"
                               placeholder="Enter current password" required autocomplete="current-password">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" class="form-control" name="new_password"
                                   placeholder="Min. 8 characters" required autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password"
                                   placeholder="Repeat new password" required autocomplete="new-password">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="savePassBtn" onclick="changePassword()">
                        Update Password
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function showAlert(msg, type) {
    const el = document.getElementById('pageAlert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => el.style.display = 'none', 5000);
}

async function saveAccount() {
    const btn = document.getElementById('saveAccountBtn');
    btn.disabled = true; btn.textContent = 'Saving...';
    const fd = new FormData(document.getElementById('accountForm'));
    try {
        const res  = await fetch('<?= APP_URL ?>/ajax/staff.php', { method: 'POST', body: fd });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
    } catch {
        showAlert('Network error.', 'error');
    } finally {
        btn.disabled = false; btn.textContent = 'Save Changes';
    }
}

async function changePassword() {
    const form    = document.getElementById('passwordForm');
    const newPass = form.querySelector('[name=new_password]').value;
    const confirm = form.querySelector('[name=confirm_password]').value;

    if (newPass !== confirm) {
        showAlert('New passwords do not match.', 'error'); return;
    }
    if (newPass.length < 8) {
        showAlert('Password must be at least 8 characters.', 'error'); return;
    }

    const btn = document.getElementById('savePassBtn');
    btn.disabled = true; btn.textContent = 'Updating...';
    const fd = new FormData(form);
    try {
        const res  = await fetch('<?= APP_URL ?>/ajax/staff.php', { method: 'POST', body: fd });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) form.reset();
    } catch {
        showAlert('Network error.', 'error');
    } finally {
        btn.disabled = false; btn.textContent = 'Update Password';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
