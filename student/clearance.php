<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('student');

$db = getDB();
$studentId = $_SESSION['student_id'];

$clearances = $db->prepare("
    SELECT c.*,
           COUNT(cs.id) as total_depts,
           SUM(cs.status='cleared') as cleared_count,
           SUM(cs.status='deficiency') as deficiency_count
    FROM clearances c
    LEFT JOIN clearance_status cs ON cs.clearance_id = c.id
    WHERE c.student_id = ?
    GROUP BY c.id
    ORDER BY c.applied_at DESC
");
$clearances->execute([$studentId]);
$clearances = $clearances->fetchAll();

$selected = null;
$deptRows = [];
if (!empty($clearances)) {
    $selId = (int)($_GET['id'] ?? $clearances[0]['id']);
    foreach ($clearances as $c) {
        if ($c['id'] == $selId) { $selected = $c; break; }
    }
    if (!$selected) $selected = $clearances[0];

    $ds = $db->prepare("
        SELECT cs.*, d.department_name, d.department_code, u.username as reviewer
        FROM clearance_status cs
        JOIN departments d ON d.id = cs.department_id
        LEFT JOIN users u ON u.id = cs.reviewed_by
        WHERE cs.clearance_id = ?
        ORDER BY d.department_name
    ");
    $ds->execute([$selected['id']]);
    $deptRows = $ds->fetchAll();
}

$pageTitle = 'My Clearance';
$activeNav = 'clearance.php';
require_once __DIR__ . '/../includes/header.php';

function sBadge($s) {
    return match($s) {
        'cleared'    => '<span class="badge badge-success">✓ Cleared</span>',
        'pending'    => '<span class="badge badge-warning">⏳ Pending</span>',
        'deficiency' => '<span class="badge badge-danger">⚠ Deficiency</span>',
        'completed'  => '<span class="badge badge-success">Completed</span>',
        'in_progress'=> '<span class="badge badge-info">In Progress</span>',
        default      => '<span class="badge badge-gray">' . ucfirst($s) . '</span>',
    };
}
?>

<?php if ($clearances): ?>

<!-- Clearance selector -->
<div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
  <?php foreach ($clearances as $c): ?>
    <a href="?id=<?= $c['id'] ?>" class="btn <?= $c['id'] == ($selected['id'] ?? 0) ? 'btn-primary' : '' ?>" style="<?= $c['id'] != ($selected['id'] ?? 0) ? 'background:#fff; color:var(--navy); border:1px solid var(--border);' : '' ?>">
      <?= ucfirst($c['clearance_type']) ?> · <?= $c['school_year'] ?> <?= $c['semester'] ?> Sem
    </a>
  <?php endforeach; ?>
</div>

<?php if ($selected): ?>
  <?php
  $pct = $selected['total_depts'] > 0 ? round($selected['cleared_count'] / $selected['total_depts'] * 100) : 0;
  ?>
  <div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
          <div style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: var(--navy);">
            <?= ucfirst($selected['clearance_type']) ?> Clearance
          </div>
          <div style="font-size: 13px; color: var(--gray); margin-top: 4px;">
            <?= htmlspecialchars($selected['school_year']) ?> &nbsp;·&nbsp; <?= $selected['semester'] ?> Semester
            &nbsp;·&nbsp; Applied <?= date('M d, Y', strtotime($selected['applied_at'])) ?>
          </div>
        </div>
        <div style="text-align: right;">
          <?= sBadge($selected['overall_status']) ?>
          <?php if ($selected['completed_at']): ?>
            <div style="font-size: 11px; color: var(--gray); margin-top: 4px;">Completed <?= date('M d, Y', strtotime($selected['completed_at'])) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="margin-top: 20px;">
        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--gray); margin-bottom: 7px;">
          <span><?= $selected['cleared_count'] ?> of <?= $selected['total_depts'] ?> departments cleared</span>
          <span><?= $pct ?>%</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width: <?= $pct ?>%;"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Department Clearance Status</span>
      <?php if ($selected['deficiency_count'] > 0): ?>
        <span class="badge badge-danger"><?= $selected['deficiency_count'] ?> Deficiency</span>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Department</th>
            <th>Code</th>
            <th>Status</th>
            <th>Remarks</th>
            <th>Reviewed By</th>
            <th>Reviewed At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($deptRows as $d): ?>
            <tr>
              <td style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($d['department_name']) ?></td>
              <td style="font-family: monospace; font-size: 12px; color: var(--info);"><?= htmlspecialchars($d['department_code']) ?></td>
              <td><?= sBadge($d['status']) ?></td>
              <td style="font-size: 12px; color: var(--gray);"><?= htmlspecialchars($d['remarks'] ?: '—') ?></td>
              <td style="font-size: 12px; color: var(--gray);"><?= htmlspecialchars($d['reviewer'] ?: '—') ?></td>
              <td style="font-size: 12px; color: var(--gray);"><?= $d['reviewed_at'] ? date('M d, Y H:i', strtotime($d['reviewed_at'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <div class="empty-title">No Clearance Applications</div>
    <div class="empty-desc">You haven't applied for clearance yet. Go to the Dashboard to apply.</div>
    <a href="dashboard.php" class="btn btn-primary" style="margin-top: 16px;">Go to Dashboard</a>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
