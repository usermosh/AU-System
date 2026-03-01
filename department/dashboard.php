<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('department');

$db     = getDB();
$deptId = $_SESSION['dept_id'];

// Counts
$stmt = $db->prepare("SELECT COUNT(*) FROM clearance_status WHERE department_id=? AND status='pending'");
$stmt->execute([$deptId]); $pendingCount = $stmt->fetchColumn();

$stmt2 = $db->prepare("SELECT COUNT(*) FROM clearance_status WHERE department_id=? AND status='cleared'");
$stmt2->execute([$deptId]); $clearedCount = $stmt2->fetchColumn();

$stmt3 = $db->prepare("SELECT COUNT(*) FROM clearance_status WHERE department_id=? AND status='deficiency'");
$stmt3->execute([$deptId]); $deficiencyCount = $stmt3->fetchColumn();

// Recent clearance requests for this dept
$stmt4 = $db->prepare("
    SELECT cs.*, c.school_year, c.semester, c.clearance_type,
           s.student_number, s.first_name, s.last_name, s.course, s.year_level
    FROM clearance_status cs
    JOIN clearances c ON c.id = cs.clearance_id
    JOIN students s ON s.id = c.student_id
    WHERE cs.department_id = ?
    ORDER BY cs.updated_at DESC
    LIMIT 20
");
$stmt4->execute([$deptId]);
$clearances = $stmt4->fetchAll();

$pageTitle = 'Department Dashboard';
$activeNav = 'dashboard.php';
require_once __DIR__ . '/../includes/header.php';

function statusBadge(string $s): string {
    return match($s) {
        'cleared'    => '<span class="badge badge-success">Cleared</span>',
        'pending'    => '<span class="badge badge-warning">Pending</span>',
        'deficiency' => '<span class="badge badge-danger">Deficiency</span>',
        default      => '<span class="badge badge-gray">' . ucfirst($s) . '</span>',
    };
}
?>

<!-- Dept Banner -->
<div style="background: linear-gradient(135deg, #1252a3 0%, #1565c0 100%); border-radius: 16px; padding: 24px 28px; margin-bottom: 24px; color: #fff;">
  <div style="font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: #90caf9;">
    <?= htmlspecialchars($_SESSION['dept_name'] ?? 'Department') ?>
  </div>
  <div style="font-size: 12px; color: rgba(255,255,255,0.65); margin-top: 4px;">
    Code: <?= htmlspecialchars($_SESSION['dept_code'] ?? '') ?> &nbsp;·&nbsp; Clearance Management Portal
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card" style="--accent-color: var(--warning);">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= $pendingCount ?></div>
    <div class="stat-label">Pending Review</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--success);">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $clearedCount ?></div>
    <div class="stat-label">Cleared</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--danger);">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $deficiencyCount ?></div>
    <div class="stat-label">With Deficiency</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--info);">
    <div class="stat-icon">📊</div>
    <div class="stat-value"><?= $pendingCount + $clearedCount + $deficiencyCount ?></div>
    <div class="stat-label">Total Requests</div>
  </div>
</div>

<div id="pageAlert" class="alert" style="display:none;"></div>

<div class="card">
  <div class="card-header">
    <span class="card-title">Clearance Requests</span>
    <div style="display: flex; gap: 8px;">
      <select id="filterStatus" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;" onchange="filterTable()">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="cleared">Cleared</option>
        <option value="deficiency">Deficiency</option>
      </select>
    </div>
  </div>
  <div class="card-body" style="padding: 0;">
    <?php if ($clearances): ?>
      <table class="data-table" id="clearanceTable">
        <thead>
          <tr>
            <th>Student No.</th>
            <th>Student Name</th>
            <th>Course / Year</th>
            <th>Clearance Type</th>
            <th>School Year</th>
            <th>Status</th>
            <th>Remarks</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clearances as $c): ?>
            <tr data-status="<?= $c['status'] ?>">
              <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($c['student_number']) ?></td>
              <td><strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></strong></td>
              <td style="font-size: 12px;"><?= htmlspecialchars($c['course']) ?> · Yr<?= $c['year_level'] ?></td>
              <td style="font-size: 12px;"><?= ucfirst($c['clearance_type']) ?> — <?= $c['semester'] ?> Sem</td>
              <td style="font-size: 12px;"><?= htmlspecialchars($c['school_year']) ?></td>
              <td><?= statusBadge($c['status']) ?></td>
              <td style="font-size: 12px; color: var(--gray); max-width: 120px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($c['remarks'] ?: '—') ?></td>
              <td>
                <button class="btn btn-primary btn-sm" onclick='openUpdateModal(<?= $c["id"] ?>, "<?= $c["status"] ?>", "<?= htmlspecialchars(addslashes($c["remarks"] ?? "")) ?>", "<?= htmlspecialchars($c["first_name"]." ".$c["last_name"]) ?>")'>
                  Update
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📋</div>
        <div class="empty-title">No Clearance Requests</div>
        <div class="empty-desc">No students have applied for clearance yet.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Update Modal -->
<div class="modal-overlay" id="updateModal">
  <div class="modal-box" style="max-width: 440px;">
    <div class="modal-header">
      <span class="modal-title">Update Clearance Status</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div id="modalStudentName" style="font-weight: 600; color: var(--navy); margin-bottom: 16px; padding: 10px 14px; background: var(--white); border-radius: 8px;"></div>
      <form id="updateForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="status_id" id="statusId">
        <div class="form-group">
          <label>Clearance Status *</label>
          <select class="form-control" name="status" id="statusSelect" required>
            <option value="pending">Pending</option>
            <option value="cleared">Cleared</option>
            <option value="deficiency">Deficiency</option>
          </select>
        </div>
        <div class="form-group">
          <label>Remarks / Notes</label>
          <textarea class="form-control" name="remarks" id="remarksInput" rows="3" placeholder="e.g., Pending library fine of ₱50"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" id="saveBtn" onclick="saveUpdate()">Save Update</button>
    </div>
  </div>
</div>

<script>
function closeModal() { document.getElementById('updateModal').classList.remove('open'); }

function openUpdateModal(id, status, remarks, name) {
  document.getElementById('statusId').value = id;
  document.getElementById('statusSelect').value = status;
  document.getElementById('remarksInput').value = remarks;
  document.getElementById('modalStudentName').textContent = 'Student: ' + name;
  document.getElementById('updateModal').classList.add('open');
}

function filterTable() {
  const filter = document.getElementById('filterStatus').value;
  document.querySelectorAll('#clearanceTable tbody tr').forEach(row => {
    row.style.display = (!filter || row.dataset.status === filter) ? '' : 'none';
  });
}

function showAlert(msg, type) {
  const el = document.getElementById('pageAlert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 5000);
}

async function saveUpdate() {
  const btn = document.getElementById('saveBtn');
  btn.disabled = true; btn.textContent = 'Saving...';
  const fd = new FormData(document.getElementById('updateForm'));
  fd.append('action', 'update_clearance');
  try {
    const res = await fetch('<?= APP_URL ?>/ajax/department.php', { method: 'POST', body: fd });
    const data = await res.json();
    closeModal();
    showAlert(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
  } catch { showAlert('Network error.', 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Save Update'; }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
