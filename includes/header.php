<?php
// includes/header.php - Shared navigation header
// Requires: $pageTitle, $activeNav to be set before including
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$roleLabels = [
    'student'    => ['label' => 'Student Portal',     'color' => '#2d9e6b'],
    'department' => ['label' => 'Department Portal',  'color' => '#1565c0'],
    'registrar'  => ['label' => 'Registrar Portal',   'color' => '#7b1fa2'],
    'admin'      => ['label' => 'Admin Portal',       'color' => '#c9a84c'],
];
$roleInfo = $roleLabels[$role] ?? ['label' => 'Portal', 'color' => '#555'];

// Role-specific nav items
$navItems = match($role) {
    'student' => [
        ['href' => 'dashboard.php',          'icon' => '⊞', 'label' => 'Dashboard'],
        ['href' => 'clearance.php',          'icon' => '✓', 'label' => 'My Clearance'],
        ['href' => 'document_requests.php', 'icon' => '📄', 'label' => 'Document Requests'],
        ['href' => 'payments.php',          'icon' => '💳', 'label' => 'Payments'],
        ['href' => 'profile.php',            'icon' => '👤', 'label' => 'My Profile'],
    ],
    'department' => [
        ['href' => 'dashboard.php',   'icon' => '⊞', 'label' => 'Dashboard'],
        ['href' => 'clearances.php',  'icon' => '✓', 'label' => 'Clearance Requests'],
        ['href' => 'students.php',    'icon' => '👥', 'label' => 'Students'],
    ],
    'registrar' => [
        ['href' => 'dashboard.php',          'icon' => '⊞', 'label' => 'Dashboard'],
        ['href' => 'document_requests.php',  'icon' => '📄', 'label' => 'Document Requests'],
        ['href' => 'payments.php',           'icon' => '💳', 'label' => 'Payment Verification'],
        ['href' => 'students.php',           'icon' => '👥', 'label' => 'Students'],
        ['href' => 'logs_dashboard.php',     'icon' => '📊', 'label' => 'Logs Dashboard'],
        ['href' => 'logs_records.php',       'icon' => '🗄',  'label' => 'Records List'],
    ],
    'admin' => [
        ['href' => 'dashboard.php',    'icon' => '⊞', 'label' => 'Dashboard'],
        ['href' => 'users.php',        'icon' => '👥', 'label' => 'User Management'],
        ['href' => 'departments.php',  'icon' => '🏛', 'label' => 'Departments'],
        ['href' => 'clearances.php',   'icon' => '✓', 'label' => 'Clearances'],
        ['href' => 'documents.php',    'icon' => '📄', 'label' => 'Documents'],
        ['href' => 'logs.php',           'icon' => '📋', 'label' => 'Activity Logs'],
        ['href' => 'logs_dashboard.php', 'icon' => '📊', 'label' => 'Logs Dashboard'],
        ['href' => 'logs_records.php',   'icon' => '🗄',  'label' => 'Records List'],
        ['href' => 'reports.php',      'icon' => '📊', 'label' => 'Reports'],
    ],
    default => []
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — AU Clearance System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --navy: #0a1628;
  --navy-mid: #112240;
  --navy-light: #1d3461;
  --gold: #c9a84c;
  --gold-light: #e8c96d;
  --white: #f8f6f0;
  --gray: #8a95a3;
  --border: #e0d8c8;
  --success: #2d9e6b;
  --danger: #c0392b;
  --warning: #d4a017;
  --info: #1565c0;
  --sidebar-width: 250px;
  --role-color: <?= $roleInfo['color'] ?>;
}
html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: #f0ece4; color: var(--navy); }

/* ─── Sidebar ─── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: var(--sidebar-width);
  height: 100vh;
  background: var(--navy);
  display: flex; flex-direction: column;
  z-index: 100;
  overflow-y: auto;
  transition: transform 0.3s;
}
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(201,168,76,0.2); }

.sidebar-brand {
  padding: 24px 20px;
  border-bottom: 1px solid rgba(201,168,76,0.15);
  display: flex; align-items: center; gap: 12px;
}
.sidebar-logo {
  width: 44px; height: 44px; border-radius: 50%;
  background: linear-gradient(135deg, var(--gold), #8b1a1a);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 900;
  color: #fff; flex-shrink: 0; overflow: hidden;
}
.sidebar-logo img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sidebar-brand-text { flex: 1; min-width: 0; }
.sidebar-brand-text .uni { font-family: 'Playfair Display', serif; font-size: 13px; font-weight: 700; color: var(--gold); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-brand-text .portal { font-size: 10px; font-weight: 600; color: var(--role-color); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 2px; }

.sidebar-nav { flex: 1; padding: 16px 0; }
.nav-section-label {
  padding: 12px 20px 6px;
  font-size: 9px; font-weight: 700;
  color: rgba(138,149,163,0.7);
  text-transform: uppercase; letter-spacing: 2px;
}
.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 20px;
  color: rgba(248,246,240,0.65);
  text-decoration: none;
  font-size: 13.5px; font-weight: 500;
  transition: all 0.2s;
  border-left: 3px solid transparent;
  margin: 1px 0;
}
.nav-item:hover { background: rgba(201,168,76,0.08); color: var(--gold-light); border-left-color: rgba(201,168,76,0.3); }
.nav-item.active { background: rgba(201,168,76,0.12); color: var(--gold); border-left-color: var(--gold); }
.nav-item .nav-icon { font-size: 16px; width: 22px; text-align: center; }

.sidebar-footer {
  padding: 16px 20px;
  border-top: 1px solid rgba(201,168,76,0.1);
}
.user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.user-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--role-color);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.user-details { flex: 1; min-width: 0; }
.user-details .u-name { font-size: 12px; font-weight: 600; color: var(--white); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-details .u-role { font-size: 10px; color: var(--gray); text-transform: capitalize; }
.btn-logout {
  display: block; width: 100%;
  padding: 9px;
  background: rgba(192,57,43,0.15);
  border: 1px solid rgba(192,57,43,0.3);
  border-radius: 8px;
  color: #e57373;
  font-family: 'DM Sans', sans-serif;
  font-size: 12px; font-weight: 600;
  cursor: pointer; text-align: center;
  text-decoration: none; transition: all 0.2s;
}
.btn-logout:hover { background: rgba(192,57,43,0.3); color: #ef9a9a; }

/* ─── Main content ─── */
.main-wrap { margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; }

.topbar {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 14px 32px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 50;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.topbar-title {
  font-family: 'Playfair Display', serif;
  font-size: 20px; font-weight: 700; color: var(--navy);
}
.topbar-meta { font-size: 12px; color: var(--gray); margin-top: 2px; }

.topbar-right { display: flex; align-items: center; gap: 14px; }
.topbar-date { font-size: 12px; color: var(--gray); }
.notification-btn {
  width: 36px; height: 36px;
  background: var(--white); border: 1px solid var(--border);
  border-radius: 8px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; transition: background 0.2s;
}
.notification-btn:hover { background: var(--gold-light); }

.page-content { padding: 28px 32px; flex: 1; }

/* ─── Cards & Stats ─── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card {
  background: #fff;
  border-radius: 14px;
  padding: 22px 24px;
  border: 1px solid var(--border);
  position: relative; overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.stat-card::before {
  content: '';
  position: absolute; top: 0; left: 0;
  width: 4px; height: 100%;
  background: var(--accent-color, var(--gold));
}
.stat-value { font-size: 32px; font-weight: 700; color: var(--navy); line-height: 1; }
.stat-label { font-size: 12px; color: var(--gray); margin-top: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-icon { position: absolute; top: 18px; right: 18px; font-size: 28px; opacity: 0.15; }

/* ─── Tables ─── */
.card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid var(--border);
  overflow: hidden;
  margin-bottom: 24px;
}
.card-header {
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.card-title { font-size: 15px; font-weight: 700; color: var(--navy); }
.card-body { padding: 20px 24px; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
  padding: 10px 14px;
  background: var(--white);
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.8px;
  color: var(--gray);
  border-bottom: 1px solid var(--border);
  text-align: left;
}
.data-table td {
  padding: 12px 14px;
  border-bottom: 1px solid #f0ece4;
  font-size: 13.5px;
  vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(240,236,228,0.4); }

/* ─── Badges ─── */
.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 100px;
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
}
.badge-success { background: #e8f8f2; color: #1a7a50; }
.badge-warning { background: #fef9e7; color: #9a6b00; }
.badge-danger  { background: #fdecea; color: #a93226; }
.badge-info    { background: #e8f0fe; color: #1252a3; }
.badge-gray    { background: #f5f5f5; color: #666; }
.badge-purple  { background: #f3e8fd; color: #6b2fa0; }

/* ─── Buttons ─── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px; font-weight: 600;
  cursor: pointer; border: none;
  transition: all 0.2s; text-decoration: none;
}
.btn-primary { background: var(--navy); color: var(--gold); }
.btn-primary:hover { background: var(--navy-light); box-shadow: 0 4px 12px rgba(10,22,40,0.2); }
.btn-success { background: #e8f8f2; color: var(--success); border: 1px solid #a8dfc7; }
.btn-success:hover { background: var(--success); color: #fff; }
.btn-danger { background: #fdecea; color: var(--danger); border: 1px solid #f5c6c6; }
.btn-danger:hover { background: var(--danger); color: #fff; }
.btn-warning { background: #fef9e7; color: var(--warning); border: 1px solid #f5dc80; }
.btn-sm { padding: 5px 11px; font-size: 12px; }

/* ─── Forms ─── */
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--navy); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
.form-control {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: 'DM Sans', sans-serif; font-size: 13.5px;
  color: var(--navy); background: #fff;
  transition: border-color 0.2s; outline: none;
}
.form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.12); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* ─── Modal ─── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(10,22,40,0.6);
  z-index: 200;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.25s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal-box {
  background: #fff;
  border-radius: 16px;
  width: 92%; max-width: 540px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 24px 64px rgba(0,0,0,0.2);
  transform: translateY(16px);
  transition: transform 0.25s;
}
.modal-overlay.open .modal-box { transform: translateY(0); }
.modal-header {
  padding: 22px 24px 16px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.modal-title { font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: var(--navy); }
.modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--gray); padding: 2px 6px; border-radius: 6px; }
.modal-close:hover { background: var(--white); color: var(--navy); }
.modal-body { padding: 20px 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }

/* ─── Progress Bar ─── */
.progress-bar-wrap { background: #f0ece4; border-radius: 100px; height: 8px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 100px; background: linear-gradient(90deg, var(--success), #4caf50); transition: width 0.5s; }

/* ─── Alert ─── */
.alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.alert-success { background: #e8f8f2; border: 1px solid #a8dfc7; color: var(--success); }
.alert-error { background: #fdecea; border: 1px solid #f5c6c6; color: var(--danger); }
.alert-info { background: #e8f0fe; border: 1px solid #90aee4; color: var(--info); }
.alert-warning { background: #fef9e7; border: 1px solid #f5dc80; color: var(--warning); }

/* ─── Empty state ─── */
.empty-state { text-align: center; padding: 48px 24px; }
.empty-icon { font-size: 48px; opacity: 0.3; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: var(--navy); margin-bottom: 6px; }
.empty-desc { font-size: 13px; color: var(--gray); }

/* ─── Clearance progress ─── */
.dept-clearance-list { display: flex; flex-direction: column; gap: 8px; }
.dept-clearance-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px;
  border-radius: 10px;
  background: var(--white);
  border: 1px solid var(--border);
}
.dept-name { font-size: 13px; font-weight: 600; color: var(--navy); }
.dept-remarks { font-size: 11px; color: var(--gray); margin-top: 2px; }

/* Responsive */
@media (max-width: 900px) {
  .main-wrap { margin-left: 0; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .page-content { padding: 18px 16px; }
  .topbar { padding: 12px 16px; }
  .form-row { grid-template-columns: 1fr; }
}
</style>

<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo">
      <img src="<?= APP_URL ?>/assets/au-logo.png" alt="AU" onerror="this.style.display='none'; this.parentElement.textContent='AU';">
    </div>
    <div class="sidebar-brand-text">
      <div class="uni">Arellano University</div>
      <div class="portal"><?= $roleInfo['label'] ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Navigation</div>
    <?php foreach ($navItems as $item): ?>
      <a href="<?= $item['href'] ?>" class="nav-item <?= ($activeNav ?? '') === $item['href'] ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
      <div class="user-details">
        <div class="u-name"><?= htmlspecialchars($name) ?></div>
        <div class="u-role"><?= ucfirst($role) ?></div>
      </div>
    </div>
    <a href="#" class="btn-logout" onclick="logout(event)">Sign Out</a>
  </div>
</div>

<div class="main-wrap" id="mainWrap">
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      <div class="topbar-meta"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="live-time"></div>
      <div class="notification-btn" title="Notifications">🔔</div>
    </div>
  </div>
  <div class="page-content">

<script>
// Live clock
(function(){
  const el = document.getElementById('live-time');
  function tick() {
    const now = new Date();
    el.textContent = now.toLocaleTimeString('en-PH', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
  }
  tick(); setInterval(tick, 1000);
})();

function logout(e) {
  e.preventDefault();
  
  Swal.fire({
    title: 'Sign Out?',
    text: "Are you sure you want to sign out??",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#0a1628',
    cancelButtonColor: '#c0392b',
    confirmButtonText: 'Yes, sign out!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch('<?= APP_URL ?>/ajax/auth.php', {
        method: 'POST',
        body: new URLSearchParams({
          action: 'logout', 
          csrf_token: '<?= csrfToken() ?>'
        })
      }).then(() => window.location.href = '<?= APP_URL ?>/index.php');
    }
  });
}

// Mobile sidebar toggle
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
