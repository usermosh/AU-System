<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();


$pending_docs   = $db->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','payment_verification')")->fetchColumn();
$approved_docs  = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='approved'")->fetchColumn();
$pending_pay    = $db->query("SELECT COUNT(*) FROM payments WHERE status='pending' AND LOWER(payment_method) = 'cash'")->fetchColumn();
$released_docs  = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='released'")->fetchColumn();

$requests = $db->query("
    SELECT dr.*, s.student_number, s.first_name, s.last_name, s.course,
           p.id as pay_id, p.amount, p.status as pay_status, p.payment_method, p.reference_number
    FROM document_requests dr
    JOIN students s ON s.id = dr.student_id
    LEFT JOIN payments p ON p.document_request_id = dr.id
    ORDER BY dr.requested_at DESC
")->fetchAll();

$pageTitle = 'Registrar Dashboard';
$activeNav = 'dashboard.php';
require_once __DIR__ . '/../includes/header.php';


function statusBadge(string $s, string $method = 'cash'): array {
    $method = strtolower($method);
    
    
    if ($method !== 'cash' && ($s === 'payment_verification' || $s === 'pending')) {
        return ['VERIFIED (ONLINE)', 'badge-success', 'approved']; 
    }
    
   
    return match($s) {
        'pending'              => ['Pending',           'badge-warning', 'pending'],
        'payment_verification' => ['Verifying Payment', 'badge-info',    'payment_verification'],
        'approved'             => ['Approved',          'badge-success', 'approved'],
        'rejected'             => ['Rejected',          'badge-danger',  'rejected'],
        'ready_for_pickup'     => ['Ready for Pickup',  'badge-purple',  'ready_for_pickup'],
        'released'             => ['Released',          'badge-success', 'released'],
        default                => [ucfirst($s),         'badge-gray',    $s],
    };
}

function payBadge(?string $s, string $method = 'cash'): string {
    if (!$s) return '<span class="badge badge-gray">No Payment</span>';
    $method = strtolower($method);
    if ($method !== 'cash') return '<span class="badge badge-success" style="background:#e6fcf5; color:#0ca678; border:1px solid #c3e6cb;">PAID (ONLINE)</span>';
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending Cash</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">' . ucfirst($s) . '</span>',
    };
}
?>

<style>
    .badge-danger { background: #fff5f5; color: #e03131; border: 1px solid #ffa8a8; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
    .badge-purple { background: #f3e5f5; color: #7b1fa2; border: 1px solid #d1c4e9; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
    .badge-success { background: #e6fcf5; color: #0ca678; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
    .badge-warning { background: #fff9db; color: #f08c00; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
    .badge-info { background: #e7f5ff; color: #1971c2; padding: 4px 10px; border-radius: 4px; font-weight: 600; }
    .data-table tr { transition: all 0.2s; }
</style>

<div style="background: linear-gradient(135deg, #4a1472 0%, #7b1fa2 100%); border-radius: 16px; padding: 24px 28px; margin-bottom: 24px; color: #fff;">
  <div style="font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: #ce93d8;">Registrar Office Portal</div>
  <div style="font-size: 12px; color: rgba(255,255,255,0.65); margin-top: 4px;">Document Requests & Payment Verification Management</div>
</div>

<div class="stats-grid">
  <div class="stat-card" style="--accent-color: var(--warning);">
    <div class="stat-icon">📥</div>
    <div class="stat-value"><?= $pending_docs ?></div>
    <div class="stat-label">Pending Requests</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--info);">
    <div class="stat-icon">💳</div>
    <div class="stat-value"><?= $pending_pay ?></div>
    <div class="stat-label">Cash Payments to Verify</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--success);">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $approved_docs ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card" style="--accent-color: var(--gold);">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $released_docs ?></div>
    <div class="stat-label">Released</div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
    <span class="card-title">Document Requests</span>
    <select id="filterStatus" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;" onchange="filterTable()">
      <option value="">All Records</option>
      <option value="pending">Pending</option>
      <option value="payment_verification">Verifying Payment</option>
      <option value="approved">Approved / Verified</option>
      <option value="ready_for_pickup">Ready for Pickup</option>
      <option value="released">Released</option>
      <option value="rejected">Rejected</option>
    </select>
  </div>
  <div class="card-body" style="padding: 0; overflow-x: auto;">
    <table class="data-table" id="reqTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Document</th>
          <th>Requested</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): 
            $method = strtolower($r['payment_method'] ?? 'cash');
            [$label, $class, $filterVal] = statusBadge($r['status'], $method);
        ?>
          <tr data-status="<?= trim($filterVal) ?>">
            <td style="font-size: 11px; color: var(--gray);"><?= $r['id'] ?></td>
            <td>
              <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
              <div style="font-size: 11px; color: var(--gray);"><?= htmlspecialchars($r['student_number']) ?> · <?= htmlspecialchars($r['course']) ?></div>
            </td>
            <td>
                <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($r['document_type']) ?></div>
                <div style="font-size:11px; color:var(--gray);"><?= $r['copies'] ?> copy/ies</div>
            </td>
            <td style="font-size: 12px; color: var(--gray);"><?= date('M d, Y', strtotime($r['requested_at'])) ?></td>
            <td>
              <?php if ($r['pay_id']): ?>
                <div><?= payBadge($r['pay_status'], $method) ?></div>
                <div style="font-size: 11px; color: var(--gray); margin-top: 3px;">
                  ₱<?= number_format($r['amount'], 2) ?> · <?= strtoupper(str_replace('_',' ',$r['payment_method'])) ?>
                </div>
              <?php else: ?>
                <span class="badge badge-gray">No Payment</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $class ?>"><?= $label ?></span></td>
            <td>
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <?php if ($method !== 'cash' && in_array($r['status'], ['pending', 'payment_verification'])): ?>
                    <button class="btn btn-sm" style="background: #e8f0fe; color:#1252a3; border: 1px solid #90aee4; font-weight: 600;" onclick="updateDocStatus(<?= $r['id'] ?>, 'ready_for_pickup')">Mark Ready</button>
                <?php elseif ($method === 'cash' && $r['pay_status'] === 'pending'): ?>
                    <button class="btn btn-success btn-sm" onclick="verifyPayment(<?= $r['pay_id'] ?>, 'verified')">Verify Payment</button>
                    <button class="btn btn-danger btn-sm" onclick="verifyPayment(<?= $r['pay_id'] ?>, 'rejected')">Reject Payment</button>
                <?php elseif ($method === 'cash' && $r['pay_status'] === 'verified' && in_array($r['status'], ['pending', 'payment_verification'])): ?>
                    <button class="btn btn-success btn-sm" onclick="updateDocStatus(<?= $r['id'] ?>, 'approved')">Approve Request</button>
                    <button class="btn btn-danger btn-sm" onclick="rejectDoc(<?= $r['id'] ?>)">Reject Request</button>
                <?php elseif ($r['status'] === 'approved'): ?>
                    <button class="btn btn-sm" style="background: #e8f0fe; color:#1252a3; border: 1px solid #90aee4; font-weight: 600;" onclick="updateDocStatus(<?= $r['id'] ?>, 'ready_for_pickup')">Mark Ready</button>
                <?php elseif ($r['status'] === 'ready_for_pickup'): ?>
                    <button class="btn btn-primary btn-sm" onclick="updateDocStatus(<?= $r['id'] ?>, 'released')">Mark Released</button>
                <?php else: ?>
                    <span style="font-size: 12px; color: var(--gray);">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CSRF_TOKEN = '<?= csrfToken(); ?>';
const AJAX_URL   = '<?= APP_URL; ?>/ajax/registrar.php';

function filterTable() {
    const filter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#reqTable tbody tr');
    
    rows.forEach(row => {
       
        const rowStatus = row.getAttribute('data-status').trim();
        if (!filter || rowStatus === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

async function sendRequest(fields) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
    return await res.json();
}

async function verifyPayment(payId, status) {
    const result = await Swal.fire({
        title: status === 'verified' ? 'Verify Payment?' : 'Reject Payment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: status === 'verified' ? '#2d9e6b' : '#c0392b',
        confirmButtonText: 'Yes, proceed'
    });

    if (result.isConfirmed) {
        const data = await sendRequest({ action: 'verify_payment', pay_id: payId, status: status });
        if (data.success) {
            Swal.fire('Updated!', 'Payment status changed.', 'success').then(() => location.reload());
        }
    }
}

async function updateDocStatus(reqId, status) {
    const display = status.replace(/_/g, ' ');
    const result = await Swal.fire({
        title: 'Update Request?',
        text: `Set status to "${display.toUpperCase()}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4a1472',
        confirmButtonText: 'Confirm'
    });

    if (result.isConfirmed) {
        const data = await sendRequest({ action: 'update_doc_status', request_id: reqId, status: status });
        if (data.success) {
            Swal.fire('Success!', `Status updated.`, 'success').then(() => location.reload());
        }
    }
}

async function rejectDoc(reqId) {
    const { value: reason } = await Swal.fire({
        title: 'Reject Request',
        input: 'textarea',
        inputLabel: 'Reason for rejection',
        inputPlaceholder: 'Provide details...',
        showCancelButton: true,
        inputValidator: (v) => !v && 'A reason is required!'
    });

    if (reason) {
        const data = await sendRequest({ 
            action: 'update_doc_status', 
            request_id: reqId, 
            status: 'rejected', 
            rejection_reason: reason 
        });
        if (data.success) {
            Swal.fire('Rejected', 'The request has been declined.', 'success').then(() => location.reload());
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
