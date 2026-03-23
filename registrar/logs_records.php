<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// ── Filters ───────────────────────────────────────────────────────
$search       = trim($_GET['search']        ?? '');
$filterType   = $_GET['doc_type']           ?? '';
$filterStatus = $_GET['status']             ?? '';
$filterMethod = $_GET['pay_method']         ?? '';
$filterCourse = $_GET['course']             ?? '';
$filterYear   = (int)($_GET['year_level']   ?? 0);
$filterFrom   = $_GET['date_from']          ?? '';
$filterTo     = $_GET['date_to']            ?? '';
$perPage     = 50;
$page        = max(1, (int)($_GET['page']   ?? 1));
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

// FIXED STATUS FILTER LOGIC
if ($filterStatus !== '') {
    if ($filterStatus === 'approved') {
        // Kapag pinili ang 'Approved', isama yung manually approved AT online payments (kahit payment_verification pa status)
        $where[] = "(dr.status = 'approved' OR (p.payment_method IN ('gcash','maya','bank_transfer') AND dr.status = 'payment_verification'))";
    } elseif ($filterStatus === 'payment_verification') {
        // Kapag 'Payment Verification' filter, CASH lang ang dapat lumabas (kasi online is automatic approved)
        $where[] = "dr.status = 'payment_verification' AND p.payment_method = 'cash'";
    } else {
        $where[] = "dr.status = ?";
        $params[] = $filterStatus;
    }
}

if ($filterMethod !== '') {
    $where[]  = "p.payment_method = ?";
    $params[] = $filterMethod;
}
if ($filterCourse !== '') {
    $where[]  = "s.course = ?";
    $params[] = $filterCourse;
}
if ($filterYear > 0) {
    $where[]  = "s.year_level = ?";
    $params[] = $filterYear;
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
    SELECT dr.*, s.first_name, s.last_name, s.student_number, s.course, s.year_level,
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

function payStatusBadge(?string $status, string $method = 'cash'): string {
    if (!$status) return '<span class="badge badge-gray">—</span>';
    $method = strtolower($method);
    if ($method !== 'cash') {
        return '<span class="badge" style="background:#e6fcf5; color:#0ca678; border:1px solid #b2f2bb;">APPROVED (PAID)</span>';
    }
    return match(strtolower($status)) {
        'verified' => '<span class="badge badge-success">Verified</span>',
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">'.ucfirst($status).'</span>',
    };
}

$qParams = array_filter([
    'search'     => $search,
    'doc_type'   => $filterType,
    'status'     => $filterStatus,
    'pay_method' => $filterMethod,
    'course'     => $filterCourse,
    'year_level' => $filterYear ?: '',
    'date_from'  => $filterFrom,
    'date_to'    => $filterTo,
]);
$baseQuery = http_build_query($qParams);

$docTypes  = ['TOR','Diploma','Certificate of Enrollment','Good Moral','Honorable Dismissal','Transfer Credentials','Authentication'];
$statuses  = ['pending','payment_verification','approved','rejected','ready_for_pickup','released'];
$payMethods= ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer'];
$courses   = [
    'BS Computer Science', 'BS Information Technology', 'BS Nursing', 'BS Accountancy',
    'BS Business Administration', 'BS Engineering', 'AB Communication', 'BS Education',
    'BS Psychology', 'BS Criminology',
];
?>

<style>
.filter-bar { background:#fff; border:1px solid var(--border); border-radius:14px; padding:18px 22px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:5px; flex:1; min-width:120px; }
.filter-group label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--gray); }
.filter-group .form-control { padding:8px 12px; font-size:13px; }
.filter-actions { display:flex; gap:8px; align-items:flex-end; flex-shrink:0; }
.pagination { display:flex; justify-content:center; align-items:center; gap:5px; flex-wrap:wrap; margin-top:20px; }
.page-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 8px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:#fff; color:var(--navy); text-decoration:none; transition:all 0.15s; }
.page-btn.active { background:#7b1fa2; color:#fff; border-color:#7b1fa2; }
.results-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
.btn-print { background:#fff; border:1.5px solid #7b1fa2; color:#7b1fa2; }

@media print {
  body, html { background:#fff !important; margin:0; padding:0; }
  .sidebar, .topbar, .filter-bar, .pagination, .no-print, .results-bar, .card { display:none !important; }
  .print-only { display:block !important; }
  .excel-print-table { width:100%; border-collapse:collapse; font-size:9pt; }
  .excel-print-table th { background:#d9d9d9 !important; border:1px solid #000; padding:4px; }
  .excel-print-table td { border:1px solid #000; padding:3px; }
}
.print-only { display:none; }
</style>

<?php if (!empty($allRecords)): ?>
<div class="print-only">
  <div style="text-align:center;padding:10px;border-bottom:2px solid #4a0e72;">
    <h2>Arellano University — Registrar Office</h2>
    <p>Transaction Records | Generated: <?= date('M d, Y h:i A') ?></p>
  </div>
  <table class="excel-print-table" style="width:100%; margin-top:10px;">
    <thead>
      <tr>
        <th>#</th><th>Student</th><th>No.</th><th>Course</th><th>Doc</th><th>Status</th><th>Amount</th><th>Requested</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allRecords as $i => $r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
          <td><?= $r['student_number'] ?></td>
          <td><?= $r['course'] ?></td>
          <td><?= $r['document_type'] ?></td>
          <td><?= ucfirst($r['status']) ?></td>
          <td>₱<?= number_format($r['amount'],2) ?></td>
          <td><?= date('Y-m-d', strtotime($r['requested_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<form method="GET" class="filter-bar no-print" id="filterForm">
  <div class="filter-group" style="flex:2;min-width:200px">
    <label>🔍 Search</label>
    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
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
    <label>Year Level</label>
    <select name="year_level" class="form-control">
      <option value="">All Years</option>
      <?php for ($y = 1; $y <= 5; $y++): ?>
        <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>>Year <?= $y ?></option>
      <?php endfor; ?>
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
    <label>Date From</label>
    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filterFrom) ?>">
  </div>
  <div class="filter-group">
    <label>Date To</label>
    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filterTo) ?>">
  </div>
  <div class="filter-actions">
    <button type="submit" class="btn btn-primary">Apply</button>
    <a href="logs_records.php" class="btn" style="background:#fff;border:1px solid var(--border);">Clear</a>
  </div>
</form>

<div class="results-bar no-print">
  <div class="results-count">Showing <strong><?= number_format(count($records)) ?></strong> of <strong><?= $total ?></strong></div>
  <div style="display:flex;gap:8px;">
    <a href="logs_dashboard.php" class="btn btn-sm" style="background:#fff;border:1px solid var(--border);color:var(--navy)">📊 Dashboard View</a>
    <button onclick="window.print()" class="btn btn-sm btn-print">🖨 Print / Export PDF</button>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="data-table records-table">
      <thead>
        <tr>
          <th>#</th><th>Student</th><th>Document</th><th>Status</th><th>Payment</th><th>Processed By</th><th>Requested</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): 
          $currentStatus = $r['status'];
          $payMethod = strtolower($r['payment_method'] ?? 'cash');

          // Logic: Kung GCash/Maya/Bank Transfer, force "approved" badge display
          if ($payMethod !== 'cash' && $currentStatus === 'payment_verification') {
              $currentStatus = 'approved';
          }

          [$sl, $sb, $si] = docStatusBadge($currentStatus);
        ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
              <div style="font-size:11px;color:gray;"><?= $r['student_number'] ?> · <?= $r['course'] ?></div>
            </td>
            <td style="font-weight:600;color:#4a0e72;"><?= htmlspecialchars($r['document_type']) ?></td>
            <td><span class="badge <?= $sb ?>"><?= $si ?> <?= $sl ?></span></td>
            <td>
              <?= payStatusBadge($r['pay_status'], $r['payment_method'] ?? 'cash') ?>
              <div style="font-size:11px;color:gray;">₱<?= number_format($r['amount'],2) ?> · <?= strtoupper(str_replace('_',' ',$r['payment_method'])) ?></div>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($r['processed_by_name'] ?? '—') ?></td>
            <td style="font-size:12px;"><?= date('M d, Y h:i A', strtotime($r['requested_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
<div class="pagination no-print">
  <a href="?page=<?= max(1, $page-1) ?>&<?= $baseQuery ?>" class="page-btn">‹</a>
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?>&<?= $baseQuery ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <a href="?page=<?= min($pages, $page+1) ?>&<?= $baseQuery ?>" class="page-btn">›</a>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('#filterForm select').forEach(s => {
    s.addEventListener('change', () => document.getElementById('filterForm').submit());
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
