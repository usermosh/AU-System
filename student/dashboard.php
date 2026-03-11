<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('student');

$db = getDB();
$studentId = $_SESSION['student_id'];

// Student info
$stmt = $db->prepare("
    SELECT s.*, u.email FROM students s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Active clearance
$stmt2 = $db->prepare("
    SELECT c.*,
           COUNT(cs.id) as total_depts,
           COALESCE(SUM(cs.status = 'cleared'), 0) as cleared_depts,
           COALESCE(SUM(cs.status = 'deficiency'), 0) as deficiency_depts
    FROM clearances c
    LEFT JOIN clearance_status cs ON cs.clearance_id = c.id
    WHERE c.student_id = ?
    AND c.overall_status IN ('pending','in_progress')
    ORDER BY c.applied_at DESC LIMIT 1
");
$stmt2->execute([$studentId]);
$clearance = $stmt2->fetch();

// Clearance per-dept status
$deptStatuses = [];
if ($clearance) {
    $stmt3 = $db->prepare("
        SELECT cs.*, d.department_name, d.department_code
        FROM clearance_status cs
        JOIN departments d ON d.id = cs.department_id
        WHERE cs.clearance_id = ?
        ORDER BY d.department_name
    ");
    $stmt3->execute([$clearance['id']]);
    $deptStatuses = $stmt3->fetchAll();
}

// Recent document requests
$stmt4 = $db->prepare("
    SELECT * FROM document_requests WHERE student_id = ?
    ORDER BY requested_at DESC LIMIT 5
");
$stmt4->execute([$studentId]);
$recentDocs = $stmt4->fetchAll();

// Counts
$stmt5 = $db->prepare("SELECT COUNT(*) FROM document_requests WHERE student_id = ?");
$stmt5->execute([$studentId]);
$totalDocs = $stmt5->fetchColumn();

$stmt6 = $db->prepare("SELECT COUNT(*) FROM document_requests WHERE student_id = ? AND status IN ('ready_for_pickup','released')");
$stmt6->execute([$studentId]);
$readyDocs = $stmt6->fetchColumn();

$stmt7 = $db->prepare("SELECT COUNT(*) FROM payments WHERE student_id = ? AND status = 'pending'");
$stmt7->execute([$studentId]);
$pendingPayments = $stmt7->fetchColumn();

$progress = 0;
if ($clearance && $clearance['total_depts'] > 0) {
    $progress = round(($clearance['cleared_depts'] / $clearance['total_depts']) * 100);
}

$pageTitle  = 'Student Dashboard';
$activeNav  = 'dashboard.php';
require_once __DIR__ . '/../includes/header.php';

function statusBadge(?string $status): string {
    if (!$status) return '<span class="badge badge-gray">Pending</span>';
    return match($status) {
        'cleared'              => '<span class="badge badge-success">Cleared</span>',
        'deficiency'           => '<span class="badge badge-danger">Deficiency</span>',
        'pending'              => '<span class="badge badge-warning">Pending</span>',
        'in_progress'          => '<span class="badge badge-info">In Progress</span>',
        'completed'            => '<span class="badge badge-success">Completed</span>',
        'approved'             => '<span class="badge badge-success">Approved</span>',
        'rejected'             => '<span class="badge badge-danger">Rejected</span>',
        'ready_for_pickup'     => '<span class="badge badge-purple">Ready for Pickup</span>',
        'released'             => '<span class="badge badge-success">Released</span>',
        'payment_verification' => '<span class="badge badge-info">Payment Verification</span>',
        default                => '<span class="badge badge-gray">' . ucfirst($status) . '</span>',
    };
}
?>

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); border-radius: 16px; padding: 28px 32px; margin-bottom: 24px; color: #fff; position: relative; overflow: hidden;">
  <div style="position: absolute; top: -40px; right: -40px; width: 200px; height: 200px; border-radius: 50%; background: rgba(201,168,76,0.1); pointer-events: none;"></div>
  <div style="font-family: 'Playfair Display', serif; font-size: 24px; font-weight: 700; color: var(--gold);">
    Good Day, <?= htmlspecialchars($student['first_name']) ?>!
  </div>
  <div style="font-size: 13px; color: rgba(255,255,255,0.65); margin-top: 4px;">
    <?= htmlspecialchars($student['course']) ?> | Year <?= $student['year_level'] ?>
    <?= $student['section'] ? ' | ' . htmlspecialchars($student['section']) : '' ?>
    &nbsp;·&nbsp; Student No. <strong style="color: var(--gold-light);"><?= htmlspecialchars($student['student_number']) ?></strong>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card" style="--accent-color: var(--success);">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $clearance ? $clearance['cleared_depts'] : 0 ?></div>
    <div class="stat-label">Departments Cleared</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--info);">
    <div class="stat-icon">📄</div>
    <div class="stat-value"><?= $totalDocs ?></div>
    <div class="stat-label">Document Requests</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--gold);">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $readyDocs ?></div>
    <div class="stat-label">Ready for Pickup</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--warning);">
    <div class="stat-icon">💳</div>
    <div class="stat-value"><?= $pendingPayments ?></div>
    <div class="stat-label">Pending Payments</div>
  </div>
</div>

<!-- Main Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

  <!-- Clearance Status Card -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🎓 Clearance Status</span>
      <?php if (!$clearance): ?>
        <button class="btn btn-primary btn-sm" onclick="openClearanceModal()">Apply for Clearance</button>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($clearance): ?>
        <div style="margin-bottom: 16px;">
          <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--gray); margin-bottom: 6px;">
            <span><?= $clearance['clearance_type'] === 'graduation' ? 'Graduation Clearance' : 'Regular Clearance' ?> — <?= $clearance['school_year'] ?> <?= $clearance['semester'] ?> Sem</span>
            <span><?= $progress ?>% Complete</span>
          </div>
          <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width: <?= $progress ?>%;"></div>
          </div>
          <div style="margin-top: 8px;">
            <?= statusBadge($clearance['overall_status']) ?>
            <?php if ($clearance['deficiency_depts'] > 0): ?>
              <span class="badge badge-danger" style="margin-left: 6px;"><?= $clearance['deficiency_depts'] ?> Deficiency</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($deptStatuses): ?>
          <div class="dept-clearance-list">
            <?php foreach ($deptStatuses as $ds): ?>
              <div class="dept-clearance-item">
                <div>
                  <div class="dept-name"><?= htmlspecialchars($ds['department_name']) ?></div>
                  <?php if ($ds['remarks']): ?>
                    <div class="dept-remarks"><?= htmlspecialchars($ds['remarks']) ?></div>
                  <?php endif; ?>
                </div>
                <?= statusBadge($ds['status']) ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">📋</div>
          <div class="empty-title">No Active Clearance</div>
          <div class="empty-desc">Click "Apply for Clearance" to start your clearance process.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Document Requests -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📄 Recent Document Requests</span>
      <a href="document_requests.php" class="btn btn-primary btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding: 0;">
      <?php if ($recentDocs): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Document</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentDocs as $doc): ?>
              <tr>
                <td>
                  <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($doc['document_type']) ?></div>
                  <div style="font-size: 11px; color: var(--gray);"><?= $doc['copies'] ?> cop<?= $doc['copies'] > 1 ? 'ies' : 'y' ?></div>
                </td>
                <td style="font-size: 12px; color: var(--gray);"><?= date('M d, Y', strtotime($doc['requested_at'])) ?></td>
                <td><?= statusBadge($doc['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">📎</div>
          <div class="empty-title">No Requests Yet</div>
          <div class="empty-desc">Go to Document Requests to submit your first request.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Apply Clearance Modal -->
<div class="modal-overlay" id="clearanceModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Apply for Clearance</span>
      <button class="modal-close" onclick="closeModal('clearanceModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="clearanceMsg" class="alert" style="display:none;"></div>
      <form id="clearanceForm">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <div class="form-group">
          <label>Clearance Type</label>
          <select class="form-control" name="clearance_type" required>
            <option value="regular">Regular Semester Clearance</option>
            <option value="graduation">Graduation Clearance</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>School Year</label>
            <input type="text" class="form-control" name="school_year" value="<?= date('Y') . '-' . (date('Y')+1) ?>" required placeholder="e.g. 2024-2025">
          </div>
          <div class="form-group">
            <label>Semester</label>
            <select class="form-control" name="semester" required>
              <option value="1st">1st Semester</option>
              <option value="2nd">2nd Semester</option>
              <option value="Summer">Summer</option>
            </select>
          </div>
        </div>
        <p style="font-size: 12px; color: var(--gray);">Submitting this will create clearance request tickets for all university departments. They will review and update your status individually.</p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('clearanceModal')">Cancel</button>
      <button class="btn btn-primary" id="submitClearanceBtn" onclick="submitClearance()">Submit Application</button>
    </div>
  </div>
</div>

<script>
function openClearanceModal() {
  document.getElementById('clearanceModal').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
function showMsg(id, msg, type) {
  const el = document.getElementById(id);
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
}

async function submitClearance() {
  const btn = document.getElementById('submitClearanceBtn');
  btn.disabled = true; btn.textContent = 'Submitting...';
  const fd = new FormData(document.getElementById('clearanceForm'));
  fd.append('action', 'apply_clearance');
  try {
    const res = await fetch('<?= APP_URL ?>/ajax/student.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showMsg('clearanceMsg', data.message, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showMsg('clearanceMsg', data.message, 'error');
      btn.disabled = false; btn.textContent = 'Submit Application';
    }
  } catch {
    showMsg('clearanceMsg', 'Network error.', 'error');
    btn.disabled = false; btn.textContent = 'Submit Application';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
