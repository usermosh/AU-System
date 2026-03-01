<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('admin');

$db = getDB();

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$total = $db->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$pages = ceil($total / $perPage);

$logs = $db->prepare("
    SELECT l.*, u.username, u.role
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.date_time DESC
    LIMIT ? OFFSET ?
");
$logs->bindValue(1, $perPage, PDO::PARAM_INT);
$logs->bindValue(2, $offset, PDO::PARAM_INT);
$logs->execute();
$logs = $logs->fetchAll();

$pageTitle = 'Activity Logs';
$activeNav = 'logs.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">📋 System Activity Logs</span>
    <span style="font-size: 12px; color: var(--gray);">Total: <?= number_format($total) ?> entries</span>
  </div>
  <div class="card-body" style="padding: 0; overflow-x: auto;">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Role</th>
          <th>Action Performed</th>
          <th>Table Affected</th>
          <th>Record ID</th>
          <th>IP Address</th>
          <th>Date & Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td style="font-size: 11px; color: var(--gray);"><?= $l['id'] ?></td>
            <td style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
            <td>
              <?php
              $rc = ['student'=>'info','department'=>'purple','registrar'=>'warning','admin'=>'gray'];
              $r = $l['role'] ?? '';
              if ($r): ?>
                <span class="badge badge-<?= $rc[$r] ?? 'gray' ?>"><?= ucfirst($r) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="font-size: 13px;"><?= htmlspecialchars($l['action_performed']) ?></td>
            <td style="font-size: 12px; color: var(--info); font-family: monospace;"><?= htmlspecialchars($l['affected_table'] ?? '—') ?></td>
            <td style="font-size: 12px; color: var(--gray); text-align: center;"><?= $l['affected_record_id'] ?? '—' ?></td>
            <td style="font-size: 12px; font-family: monospace; color: var(--gray);"><?= htmlspecialchars($l['ip_address'] ?? '') ?></td>
            <td style="font-size: 12px; color: var(--gray); white-space: nowrap;"><?= date('M d, Y H:i:s', strtotime($l['date_time'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
  <div style="display: flex; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : '' ?>" style="<?= $i !== $page ? 'background:#fff; color:var(--navy); border:1px solid var(--border);' : '' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
