<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('admin');

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
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
            background: linear-gradient(135deg, var(--navy), var(--navy-light));
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 16px;
            font-family:'Playfair Display',serif;
            font-size:36px; font-weight:900; color:var(--gold);
            box-shadow:0 8px 24px rgba(10,22,40,0.3);
        ">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
        <div style="font-family:'Playfair Display',serif; font-size:20px; font-weight:700; color:var(--navy);">
            <?= htmlspecialchars($user['username']) ?>
        </div>
        <div style="font-size:13px; color:var(--gray); margin-top:4px;">System Administrator</div>
        <div style="margin-top:12px;">
            <span class="badge badge-gray">Admin</span>
            <span class="badge badge-success" style="margin-left:6px;">Active</span>
        </div>
        <div style="margin-top:20px; padding:14px; background:var(--white); border-radius:10px; text-align:left;">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray); margin-bottom:10px;">Account Info</div>
            <div style="font-size:13px; margin-bottom:8px;">
                <span style="color:var(--gray);">Email:</span>
                <strong style="float:right; font-size:12px;"><?= htmlspecialchars($user['email']) ?></strong>
            </div>
            <div style="font-size:13px; margin-bottom:8px;">
                <span style="color:var(--gray);">Role:</span>
                <strong style="float:right;">System Admin</strong>
            </div>
            <div style="font-size:13px;">
                <span style="color:var(--gray);">Member Since:</span>
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
                    <div class="alert alert-warning" style="display:block; font-size:12px;">
                        ⚠️ You are changing the <strong>System Administrator</strong> password. Make sure to remember it.
                    </div>
                    <button type="button" class="btn btn-primary" id="savePassBtn" onclick="changePassword()">
                        Update Password
                    </button>
                </form>
            </div>
        </div>

        <!-- System Info -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🖥️ System Information</span>
            </div>
            <div class="card-body" style="padding:0;">
                <table class="data-table">
                    <tr>
                        <td style="font-weight:600;">System Name</td>
                        <td>AU Digital Clearance & Document Request System</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Version</td>
                        <td><?= APP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">PHP Version</td>
                        <td><?= PHP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Server</td>
                        <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Database</td>
                        <td><?= DB_NAME ?> @ <?= DB_HOST ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Server Time</td>
                        <td><?= date('F j, Y H:i:s') ?></td>
                    </tr>
                </table>
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
