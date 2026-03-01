<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('student');

$db        = getDB();
$studentId = $_SESSION['student_id'];
$userId    = $_SESSION['user_id'];

// Fetch full student data
$stmt = $db->prepare("
    SELECT s.*, u.username, u.email, u.created_at as account_created, u.is_active
    FROM students s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

$pageTitle = 'My Profile';
$activeNav = 'profile.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div id="pageAlert" class="alert" style="display:none;"></div>

<div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 20px;">

    <!-- Profile Card -->
    <div>
        <div class="card" style="text-align:center; padding: 32px 24px;">
            <!-- Avatar -->
            <div style="
                width: 90px; height: 90px; border-radius: 50%;
                background: linear-gradient(135deg, var(--navy), var(--navy-light));
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 16px;
                font-family: 'Playfair Display', serif;
                font-size: 36px; font-weight: 900; color: var(--gold);
                box-shadow: 0 8px 24px rgba(10,22,40,0.2);
            ">
                <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
            </div>

            <div style="font-family:'Playfair Display',serif; font-size:20px; font-weight:700; color:var(--navy);">
                <?= htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'][0] . '. ' : '') . $student['last_name']) ?>
            </div>
            <div style="font-size:13px; color:var(--gray); margin-top:4px;">
                <?= htmlspecialchars($student['course']) ?>
            </div>
            <div style="margin-top:12px;">
                <span class="badge badge-info">Year <?= $student['year_level'] ?></span>
                <?php if ($student['section']): ?>
                    <span class="badge badge-purple" style="margin-left:6px;"><?= htmlspecialchars($student['section']) ?></span>
                <?php endif; ?>
                <?php if ($student['is_active']): ?>
                    <span class="badge badge-success" style="margin-left:6px;">Active</span>
                <?php endif; ?>
            </div>

            <div style="margin-top:20px; padding:14px; background:var(--white); border-radius:10px; text-align:left;">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gray); margin-bottom:10px;">Account Info</div>
                <div style="font-size:13px; margin-bottom:8px;">
                    <span style="color:var(--gray);">Student No.:</span>
                    <strong style="float:right; font-family:monospace;"><?= htmlspecialchars($student['student_number']) ?></strong>
                </div>
                <div style="font-size:13px; margin-bottom:8px;">
                    <span style="color:var(--gray);">Username:</span>
                    <strong style="float:right;">@<?= htmlspecialchars($student['username']) ?></strong>
                </div>
                <div style="font-size:13px;">
                    <span style="color:var(--gray);">Member Since:</span>
                    <strong style="float:right;"><?= date('M Y', strtotime($student['account_created'])) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Forms -->
    <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Personal Information -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">👤 Personal Information</span>
            </div>
            <div class="card-body">
                <form id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" class="form-control" name="first_name"
                                   value="<?= htmlspecialchars($student['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" class="form-control" name="last_name"
                                   value="<?= htmlspecialchars($student['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" class="form-control" name="middle_name"
                                   value="<?= htmlspecialchars($student['middle_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" class="form-control" name="contact_number"
                                   value="<?= htmlspecialchars($student['contact_number'] ?? '') ?>"
                                   placeholder="09xxxxxxxxx">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Course *</label>
                            <select class="form-control" name="course" required>
                                <?php
                                $courses = [
                                    'BS Computer Science','BS Information Technology',
                                    'BS Nursing','BS Accountancy','BS Business Administration',
                                    'BS Engineering','AB Communication','BS Education',
                                    'BS Psychology','BS Criminology'
                                ];
                                foreach ($courses as $c): ?>
                                    <option value="<?= $c ?>" <?= $student['course'] === $c ? 'selected' : '' ?>>
                                        <?= $c ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year Level *</label>
                            <select class="form-control" name="year_level" required>
                                <?php for ($y = 1; $y <= 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= $student['year_level'] == $y ? 'selected' : '' ?>>
                                        Year <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Section</label>
                            <input type="text" class="form-control" name="section"
                                   value="<?= htmlspecialchars($student['section'] ?? '') ?>"
                                   placeholder="e.g. BSCS-4A">
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= htmlspecialchars($student['email']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Home Address</label>
                        <input type="text" class="form-control" name="address"
                               value="<?= htmlspecialchars($student['address'] ?? '') ?>"
                               placeholder="City / Province">
                    </div>

                    <button type="button" class="btn btn-primary" id="saveProfileBtn" onclick="saveProfile()">
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
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" class="form-control" name="current_password"
                               placeholder="Enter current password" required autocomplete="current-password">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" class="form-control" name="new_password" id="newPass"
                                   placeholder="Min. 8 characters" required autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password"
                                   placeholder="Repeat new password" required autocomplete="new-password">
                        </div>
                    </div>

                    <div style="background:var(--white); border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:12px; color:var(--gray);">
                        <strong style="color:var(--navy);">Password requirements:</strong><br>
                        • At least 8 characters long<br>
                        • Use a mix of letters, numbers, and symbols
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

async function saveProfile() {
    const btn = document.getElementById('saveProfileBtn');
    btn.disabled = true; btn.textContent = 'Saving...';
    const fd = new FormData(document.getElementById('profileForm'));
    try {
        const res  = await fetch('<?= APP_URL ?>/ajax/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
    } catch {
        showAlert('Network error. Please try again.', 'error');
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
        const res  = await fetch('<?= APP_URL ?>/ajax/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) form.reset();
    } catch {
        showAlert('Network error. Please try again.', 'error');
    } finally {
        btn.disabled = false; btn.textContent = 'Update Password';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
