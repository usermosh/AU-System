<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// ── Filters ───────────────────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$filterType  = $_GET['doc_type']      ?? '';
$filterStatus= $_GET['status']        ?? '';
$filterMethod= $_GET['pay_method']    ?? '';
$filterCourse= $_GET['course']        ?? '';
$filterFrom  = $_GET['date_from']     ?? '';
$filterTo    = $_GET['date_to']       ?? '';
$perPage     = 50;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $perPage;

// ── Build WHERE ───────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ? OR dr.document_type LIKE ? OR dr.purpose LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($filterType !== '') {
    $where[]  = "dr.document_type = ?";
    $params[] = $filterType;
}
if ($filterStatus !== '') {
    $where[]  = "dr.status = ?";
    $params[] = $filterStatus;
}
if ($filterMethod !== '') {
    $where[]  = "p.payment_method = ?";
    $params[] = $filterMethod;
}
if ($filterCourse !== '') {
    $where[]  = "s.course = ?";
    $params[] = $filterCourse;
}
if ($filterFrom !== '') {
    $where[]  = "DATE(dr.requested_at) >= ?";
    $params[] = $filterFrom;
}
if ($filterTo !== '') {
    $where[]  = "DATE(dr.requested_at) <= ?";
    $params[] = $filterTo;
}

$whereSQL = implode(' AND ', $where);

$baseSQL = "
    FROM document_requests dr
    JOIN students s ON s.id = dr.student_id
    LEFT JOIN payments p ON p.document_request_id = dr.id
    LEFT JOIN users u ON u.id = dr.processed_by
    WHERE {$whereSQL}
";

// Count
$cntStmt = $db->prepare("SELECT COUNT(*) {$baseSQL}");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// Paginated fetch
$stmt = $db->prepare("
    SELECT dr.*, s.first_name, s.last_name, s.student_number, s.course, s.contact_number,
           p.id AS pay_id, p.amount, p.status AS pay_status, p.payment_method, p.reference_number, p.submitted_at AS pay_submitted,
           u.username AS processed_by_name
    {$baseSQL}
    ORDER BY dr.requested_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// All records for print
$allStmt = $db->prepare("
    SELECT dr.*, s.first_name, s.last_name, s.student_number, s.course,
           p.amount, p.status AS pay_status, p.payment_method, p.reference_number,
           u.username AS processed_by_name
    {$baseSQL}
    ORDER BY dr.requested_at DESC
");
$allStmt->execute($params);
$allRecords = $allStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Transaction Records';
$activeNav = 'logs_records.php';
require_once __DIR__ . '/../includes/header.php';

function docStatusBadge(string $s): array {
    return match($s) {
        'pending'              => ['Pending',           'badge-warning',  '⏳'],
        'payment_verification' => ['Verifying Payment', 'badge-info',     '🔍'],
        'approved'             => ['Approved',          'badge-success',  '✅'],
        'rejected'             => ['Rejected',          'badge-danger',   '❌'],
        'ready_for_pickup'     => ['Ready for Pickup',  'badge-purple',   '📦'],
        'released'             => ['Released',          'badge-success',  '✔'],
        default                => [ucfirst($s),         'badge-gray',     '•'],
    };
}
function payStatusBadge(?string $s): string {
    if (!$s) return '<span class="badge badge-gray">—</span>';
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">'.ucfirst($s).'</span>',
    };
}

$qParams = array_filter([
    'search'     => $search,
    'doc_type'   => $filterType,
    'status'     => $filterStatus,
    'pay_method' => $filterMethod,
    'course'     => $filterCourse,
    'date_from'  => $filterFrom,
    'date_to'    => $filterTo,
]);
$baseQuery = http_build_query($qParams);

$docTypes  = ['TOR','Diploma','Certificate of Enrollment','Good Moral','Honorable Dismissal','Transfer Credentials','Authentication'];
$statuses  = ['pending','payment_verification','approved','rejected','ready_for_pickup','released'];
$payMethods= ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer'];
$courses   = [
    'BS Computer Science',
    'BS Information Technology',
    'BS Nursing',
    'BS Accountancy',
    'BS Business Administration',
    'BS Engineering',
    'AB Communication',
    'BS Education',
    'BS Psychology',
    'BS Criminology',
];
?>

<style>
/* Filter bar */
.filter-bar { background:#fff; border:1px solid var(--border); border-radius:14px; padding:18px 22px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:5px; flex:1; min-width:120px; }
.filter-group label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--gray); }
.filter-group .form-control { padding:8px 12px; font-size:13px; }
.filter-actions { display:flex; gap:8px; align-items:flex-end; flex-shrink:0; }

/* Table */
.records-table th { position:sticky; top:0; z-index:2; background:var(--white); }

/* Pagination */
.pagination { display:flex; justify-content:center; align-items:center; gap:5px; flex-wrap:wrap; margin-top:20px; }
.page-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:#fff; color:var(--navy); text-decoration:none; transition:all 0.15s; }
.page-btn:hover { background:var(--white); border-color:#7b1fa2; }
.page-btn.active { background:#7b1fa2; color:#fff; border-color:#7b1fa2; }
.page-btn.disabled { opacity:0.4; pointer-events:none; }
.page-info { font-size:12px; color:var(--gray); padding:0 8px; }

/* Results bar */
.results-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
.results-count { font-size:13px; color:var(--gray); }
.results-count strong { color:var(--navy); }

/* Print button */
.btn-print { background:#fff; border:1.5px solid #7b1fa2; color:#7b1fa2; }
.btn-print:hover { background:#7b1fa2; color:#fff; }

/* ── PRINT ──────────────────────────── */
@media print {
  /* hide everything except the excel table */
  body, html { background:#fff !important; margin:0; padding:0; }
  .sidebar, .topbar, .filter-bar, .pagination, .no-print,
  .print-header, .results-bar, .card { display:none !important; }
  .main-wrap { margin-left:0 !important; }
  .page-content { padding:4px !important; }

  /* show only the print table */
  .print-only { display:block !important; }

  /* excel-style table */
  .excel-print-table {
    width:100%;
    border-collapse:collapse;
    font-family:Arial, sans-serif;
    font-size:9pt;
    color:#000;
  }
  .excel-print-table th {
    background:#d9d9d9 !important;
    color:#000 !important;
    font-weight:700;
    border:1px solid #000 !important;
    padding:4px 6px;
    text-align:left;
    white-space:nowrap;
    -webkit-print-color-adjust:exact;
    print-color-adjust:exact;
  }
  .excel-print-table td {
    border:1px solid #000 !important;
    padding:3px 6px;
    vertical-align:top;
    background:#fff !important;
  }
  .excel-print-table tr:nth-child(even) td {
    background:#f2f2f2 !important;
    -webkit-print-color-adjust:exact;
    print-color-adjust:exact;
  }
  .excel-print-title {
    font-family:Arial,sans-serif;
    font-size:11pt;
    font-weight:700;
    margin-bottom:2px;
  }
  .excel-print-meta {
    font-family:Arial,sans-serif;
    font-size:8pt;
    color:#444;
    margin-bottom:6px;
  }
}
.print-only { display:none; }
</style>

<!-- Print-only full table (Excel style) -->
<?php if (!empty($allRecords)): ?>
<div class="print-only">
  <div style="text-align:center;padding:16px 0 10px;border-bottom:2px solid #4a0e72;margin-bottom:10px">
    <h2 style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;margin-bottom:4px">Arellano University — Registrar Office</h2>
    <h3 style="font-size:14px;font-weight:400;margin-bottom:4px">Document Request Transaction Records<?= $filterCourse ? ' — ' . htmlspecialchars($filterCourse) : '' ?></h3>
    <p style="font-size:10px;color:#555;margin:0">
      Generated: <?= date('F j, Y  h:i A') ?>
      <?= $filterCourse ? ' | Course: ' . htmlspecialchars($filterCourse) : ' | All Courses' ?>
      <?= $filterType   ? ' | Type: ' . htmlspecialchars($filterType) : '' ?>
      <?= $filterStatus ? ' | Status: ' . ucfirst(str_replace('_',' ',$filterStatus)) : '' ?>
      <?= $filterFrom   ? ' | From: ' . htmlspecialchars($filterFrom) : '' ?>
      <?= $filterTo     ? ' | To: '   . htmlspecialchars($filterTo)   : '' ?>
      | <?= number_format(count($allRecords)) ?> record(s)
    </p>
  </div>
  <table class="excel-print-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Student Name</th>
        <th>Student No.</th>
        <th>Course</th>
        <th>Document Type</th>
        <th>Copies</th>
        <th>Purpose</th>
        <th>Request Status</th>
        <th>Pay Status</th>
        <th>Amount</th>
        <th>Pay Method</th>
        <th>Reference No.</th>
        <th>Processed By</th>
        <th>Requested At</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allRecords as $i => $r):
        [$sl] = docStatusBadge($r['status']);
      ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
          <td><?= htmlspecialchars($r['student_number']) ?></td>
          <td><?= htmlspecialchars($r['course']) ?></td>
          <td><?= htmlspecialchars($r['document_type']) ?></td>
          <td style="text-align:center"><?= $r['copies'] ?></td>
          <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
          <td><?= $sl ?></td>
          <td><?= $r['pay_status'] ? ucfirst($r['pay_status']) : '—' ?></td>
          <td><?= $r['amount'] ? '₱' . number_format($r['amount'], 2) : '—' ?></td>
          <td><?= $r['payment_method'] ? strtoupper(str_replace('_', ' ', $r['payment_method'])) : '—' ?></td>
          <td><?= htmlspecialchars($r['reference_number'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['processed_by_name'] ?? '—') ?></td>
          <td><?= date('M d, Y h:i A', strtotime($r['requested_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<form method="GET" class="filter-bar no-print" id="filterForm">
  <div class="filter-group" style="flex:2;min-width:200px">
    <label>🔍 Search</label>
    <input type="text" name="search" class="form-control"
           placeholder="Student name, number, purpose…"
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <div class="filter-group">
    <label>Course</label>
    <select name="course" class="form-control">
      <option value="">All Courses</option>
      <?php foreach ($courses as $c): ?>
        <option value="<?= $c ?>" <?= $filterCourse === $c ? 'selected' : '' ?>><?= $c ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Document Type</label>
    <select name="doc_type" class="form-control">
      <option value="">All Types</option>
      <?php foreach ($docTypes as $dt): ?>
        <option value="<?= $dt ?>" <?= $filterType === $dt ? 'selected' : '' ?>><?= $dt ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Status</label>
    <select name="status" class="form-control">
      <option value="">All Statuses</option>
      <?php foreach ($statuses as $st): ?>
        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Payment Method</label>
    <select name="pay_method" class="form-control">
      <option value="">All Methods</option>
      <?php foreach ($payMethods as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filterMethod === $val ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Date From</label>
    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filterFrom) ?>">
  </div>
  <div class="filter-group">
    <label>Date To</label>
    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filterTo) ?>">
  </div>
  <div class="filter-actions">
    <button type="submit" class="btn btn-primary">Apply</button>
    <a href="logs_records.php" class="btn" style="background:#fff;border:1px solid var(--border);color:var(--navy)">Clear</a>
  </div>
</form>

<!-- Results Bar -->
<div class="results-bar">
  <div class="results-count">
    Showing <strong><?= number_format(min($offset + 1, $total)) ?>–<?= number_format(min($offset + $perPage, $total)) ?></strong>
    of <strong><?= number_format($total) ?></strong> records
    <?php if ($search || $filterType || $filterStatus || $filterMethod || $filterCourse || $filterFrom || $filterTo): ?>
      <span style="color:#7b1fa2;font-weight:600"> (filtered)</span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <a href="logs_dashboard.php" class="btn btn-sm no-print"
       style="background:#fff;border:1px solid var(--border);color:var(--navy)">
      📊 Dashboard View
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-print no-print">
      🖨 Print / Export PDF
    </button>
  </div>
</div>

<!-- Screen Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">📄 Document Request Records<?= $filterCourse ? ' — ' . htmlspecialchars($filterCourse) : '' ?></span>
    <span style="font-size:12px;color:var(--gray)"><?= number_format($total) ?> total</span>
  </div>
  <div style="overflow-x:auto">
    <table class="data-table records-table">
      <thead>
        <tr>
          <th style="width:44px">#</th>
          <th>Student</th>
          <th>Document</th>
          <th style="width:54px;text-align:center">Copies</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Purpose</th>
          <th>Processed By</th>
          <th style="min-width:140px">Requested</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($records)): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <div class="empty-title">No records found</div>
                <div class="empty-desc">Try adjusting your filters.</div>
              </div>
            </td>
          </tr>
        <?php else:
          foreach ($records as $r):
            [$statLabel, $statBadge, $statIcon] = docStatusBadge($r['status']);
            $highlight = in_array($r['status'], ['rejected']) ? 'background:rgba(192,57,43,0.03)' : '';
          ?>
            <tr style="<?= $highlight ?>">
              <td style="font-size:11px;color:var(--gray)"><?= $r['id'] ?></td>
              <td>
                <div style="font-weight:600;font-size:13px">
                  <?php if ($search): ?>
                    <?= preg_replace('/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
                        '<mark style="background:#f3e8fd;border-radius:2px;padding:0 2px">$1</mark>',
                        htmlspecialchars($r['first_name'].' '.$r['last_name'])) ?>
                  <?php else: ?>
                    <?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?>
                  <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--gray)"><?= htmlspecialchars($r['student_number']) ?> · <?= htmlspecialchars($r['course']) ?></div>
                <?php if ($r['contact_number']): ?>
                  <div style="font-size:10px;color:var(--gray)"><?= htmlspecialchars($r['contact_number']) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-weight:600;font-size:13px;color:#4a0e72"><?= htmlspecialchars($r['document_type']) ?></td>
              <td style="text-align:center;font-weight:600"><?= $r['copies'] ?></td>
              <td>
                <span class="badge <?= $statBadge ?>" style="display:inline-flex;align-items:center;gap:4px">
                  <?= $statIcon ?> <?= $statLabel ?>
                </span>
                <?php if ($r['rejected_at'] ?? false): ?>
                  <div style="font-size:10px;color:var(--danger);margin-top:2px"><?= htmlspecialchars($r['rejection_reason'] ?? '') ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['pay_id']): ?>
                  <?= payStatusBadge($r['pay_status']) ?>
                  <div style="font-size:11px;color:var(--gray);margin-top:3px">
                    ₱<?= number_format($r['amount'], 2) ?>
                    · <?= strtoupper(str_replace('_',' ', $r['payment_method'])) ?>
                  </div>
                  <?php if ($r['reference_number']): ?>
                    <div style="font-size:10px;font-family:monospace;color:var(--gray)"><?= htmlspecialchars($r['reference_number']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-gray">No Payment</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--gray);max-width:160px">
                <?= htmlspecialchars($r['purpose'] ?? '—') ?>
              </td>
              <td style="font-size:12px;color:var(--gray)">
                <?= htmlspecialchars($r['processed_by_name'] ?? '—') ?>
                <?php if ($r['processed_at']): ?>
                  <div style="font-size:10px"><?= date('M d h:i A', strtotime($r['processed_at'])) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--gray);white-space:nowrap">
                <?= date('M d, Y', strtotime($r['requested_at'])) ?><br>
                <span style="font-size:11px"><?= date('h:i A', strtotime($r['requested_at'])) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination no-print">
  <?php
  $prevHref = $page > 1 ? '?' . ($baseQuery ? $baseQuery . '&' : '') . 'page=' . ($page-1) : '#';
  $nextHref = $page < $pages ? '?' . ($baseQuery ? $baseQuery . '&' : '') . 'page=' . ($page+1) : '#';
  $ws = max(1, $page-3); $we = min($pages, $page+3);
  ?>
  <a href="<?= $prevHref ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
  <?php if ($ws > 1): ?>
    <a href="?<?= $baseQuery ? $baseQuery.'&' : '' ?>page=1" class="page-btn">1</a>
    <?php if ($ws > 2): ?><span class="page-info">…</span><?php endif; ?>
  <?php endif; ?>
  <?php for ($i = $ws; $i <= $we; $i++): ?>
    <a href="?<?= $baseQuery ? $baseQuery.'&' : '' ?>page=<?= $i ?>"
       class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($we < $pages): ?>
    <?php if ($we < $pages - 1): ?><span class="page-info">…</span><?php endif; ?>
    <a href="?<?= $baseQuery ? $baseQuery.'&' : '' ?>page=<?= $pages ?>" class="page-btn"><?= $pages ?></a>
  <?php endif; ?>
  <a href="<?= $nextHref ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">›</a>
  <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
</div>
<?php endif; ?>



<script>
document.querySelector('[name="search"]').addEventListener('keypress', function(e){
    if(e.key==='Enter') document.getElementById('filterForm').submit();
});
document.querySelectorAll('#filterForm select').forEach(function(s){
    s.addEventListener('change', function(){ document.getElementById('filterForm').submit(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>