<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('admin');

$db = getDB();

$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalClear    = $db->query("SELECT COUNT(*) FROM clearances WHERE overall_status='completed'")->fetchColumn();
$totalDocs     = $db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$totalPending  = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='pending'")->fetchColumn();
$totalReleased = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='released'")->fetchColumn();

// Recent logs
$logs = $db->query("
    SELECT l.*, u.username, u.role
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.date_time DESC
    LIMIT 25
")->fetchAll();

// Recent users
$recentUsers = $db->query("
    SELECT u.*, s.student_number, s.first_name, s.last_name
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    ORDER BY u.created_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'System Administrator Dashboard';
$activeNav = 'dashboard.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Banner -->
<div style="background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); border-radius: 16px; padding: 24px 28px; margin-bottom: 24px; color: #fff; position: relative; overflow: hidden;">
  <div style="position: absolute; right: -20px; top: -20px; width: 160px; height: 160px; border-radius: 50%; background: rgba(201,168,76,0.1);"></div>
  <div style="font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: var(--gold);">System Administrator</div>
  <div style="font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 4px;">Full system access · AU Digital Clearance System v1.0</div>
</div>

<div class="stats-grid">
  <div class="stat-card" style="--accent-color: var(--navy-light);">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $totalUsers ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--info);">
    <div class="stat-icon">🎓</div>
    <div class="stat-value"><?= $totalStudents ?></div>
    <div class="stat-label">Students</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--success);">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $totalClear ?></div>
    <div class="stat-label">Clearances Completed</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--gold);">
    <div class="stat-icon">📄</div>
    <div class="stat-value"><?= $totalDocs ?></div>
    <div class="stat-label">Document Requests</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--warning);">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= $totalPending ?></div>
    <div class="stat-label">Pending Requests</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--success);">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $totalReleased ?></div>
    <div class="stat-label">Released</div>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px;">

  <!-- Activity Logs -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📋 Recent Activity Logs</span>
      <a href="logs.php" class="btn btn-primary btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding: 0; max-height: 460px; overflow-y: auto;">
      <?php if ($logs): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Action</th>
              <th>Table</th>
              <th>Time</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td>
                  <div style="font-size: 12px; font-weight: 600;"><?= htmlspecialchars($l['username'] ?? 'System') ?></div>
                  <?php if ($l['role']): ?>
                    <div style="font-size: 10px; color: var(--gray);"><?= $l['role'] ?></div>
                  <?php endif; ?>
                </td>
                <td style="font-size: 12px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($l['action_performed']) ?></td>
                <td style="font-size: 11px; color: var(--gray);"><?= htmlspecialchars($l['affected_table'] ?? '—') ?></td>
                <td style="font-size: 11px; color: var(--gray); white-space: nowrap;"><?= date('M d H:i', strtotime($l['date_time'])) ?></td>
                <td style="font-size: 11px; font-family: monospace; color: var(--gray);"><?= htmlspecialchars($l['ip_address'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><div class="empty-icon">📋</div><div class="empty-title">No logs yet</div></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Users -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">👥 Recent Users</span>
      <a href="users.php" class="btn btn-primary btn-sm">Manage</a>
    </div>
    <div class="card-body" style="padding: 0;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name / Username</th>
            <th>Role</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td>
                <div style="font-weight: 600; font-size: 13px;">
                  <?= htmlspecialchars($u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : $u['username']) ?>
                </div>
                <?php if ($u['student_number']): ?>
                  <div style="font-size: 11px; color: var(--gray);"><?= htmlspecialchars($u['student_number']) ?></div>
                <?php else: ?>
                  <div style="font-size: 11px; color: var(--gray);"><?= htmlspecialchars($u['email']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php
                $roleColors = ['student'=>'info','department'=>'purple','registrar'=>'warning','admin'=>'gray'];
                $rc = $roleColors[$u['role']] ?? 'gray';
                ?>
                <span class="badge badge-<?= $rc ?>"><?= ucfirst($u['role']) ?></span>
              </td>
              <td style="font-size: 12px; color: var(--gray);"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
