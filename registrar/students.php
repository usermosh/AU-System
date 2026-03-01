<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

$students = $db->query("
    SELECT s.*, u.email, u.is_active,
           COUNT(DISTINCT dr.id) as total_requests,
           COUNT(DISTINCT c.id) as total_clearances
    FROM students s
    JOIN users u ON u.id = s.user_id
    LEFT JOIN document_requests dr ON dr.student_id = s.id
    LEFT JOIN clearances c ON c.student_id = s.id
    GROUP BY s.id
    ORDER BY s.last_name ASC
")->fetchAll();

$pageTitle = 'Students';
$activeNav = 'students.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">All Students (<?= count($students) ?>)</span>
    <input type="text" id="searchInput" class="form-control" 
           style="width:220px; padding:7px 12px; font-size:13px;" 
           placeholder="Search students..." 
           oninput="searchTable()">
  </div>
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="data-table" id="studentsTable">
      <thead>
        <tr>
          <th>Student No.</th>
          <th>Full Name</th>
          <th>Course</th>
          <th>Year</th>
          <th>Section</th>
          <th>Email</th>
          <th>Contact</th>
          <th>Requests</th>
          <th>Clearances</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
          <tr data-search="<?= strtolower($s['student_number'].' '.$s['first_name'].' '.$s['last_name'].' '.$s['course']) ?>">
            <td style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($s['student_number']) ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($s['last_name'].', '.$s['first_name']) ?></div>
              <?php if ($s['middle_name']): ?>
                <div style="font-size:11px; color:var(--gray);"><?= htmlspecialchars($s['middle_name']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:13px;"><?= htmlspecialchars($s['course']) ?></td>
            <td style="text-align:center;"><?= $s['year_level'] ?></td>
            <td style="font-size:12px;"><?= htmlspecialchars($s['section'] ?: '—') ?></td>
            <td style="font-size:12px;"><?= htmlspecialchars($s['email']) ?></td>
            <td style="font-size:12px;"><?= htmlspecialchars($s['contact_number'] ?: '—') ?></td>
            <td style="text-align:center;">
              <span class="badge badge-info"><?= $s['total_requests'] ?></span>
            </td>
            <td style="text-align:center;">
              <span class="badge badge-purple"><?= $s['total_clearances'] ?></span>
            </td>
            <td>
              <?php if ($s['is_active']): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-danger">Inactive</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function searchTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#studentsTable tbody tr').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>