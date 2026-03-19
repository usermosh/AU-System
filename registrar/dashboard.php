<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// Stats
$pending_docs   = $db->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','payment_verification')")->fetchColumn();
$approved_docs  = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='approved'")->fetchColumn();
$pending_pay    = $db->query("SELECT COUNT(*) FROM payments WHERE status='pending' AND LOWER(payment_method) = 'cash'")->fetchColumn();
$released_docs  = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='released'")->fetchColumn();

// Recent doc requests
$requests = $db->query("
    SELECT dr.*, s.student_number, s.first_name, s.last_name, s.course,
           p.id as pay_id, p.amount, p.status as pay_status, p.payment_method, p.reference_number
    FROM document_requests dr
    JOIN students s ON s.id = dr.student_id
    LEFT JOIN payments p ON p.document_request_id = dr.id
    ORDER BY dr.requested_at DESC
    LIMIT 30
")->fetchAll();

$pageTitle = 'Registrar Dashboard';
$activeNav = 'dashboard.php';
require_once __DIR__ . '/../includes/header.php';

function statusBadge(string $s, string $method = 'cash'): string {
    $method = strtolower($method);
    if ($method !== 'cash' && ($s === 'payment_verification' || $s === 'pending')) {
        return '<span class="badge" style="background: #e6fcf5; color: #0ca678; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 11px;">VERIFIED (ONLINE)</span>';
    }
    return match($s) {
        'pending'              => '<span class="badge badge-warning">Pending</span>',
        'payment_verification' => '<span class="badge badge-info">Verifying Payment</span>',
        'approved'             => '<span class="badge badge-success">Approved</span>',
        'rejected'             => '<span class="badge badge-danger">Rejected</span>',
        'ready_for_pickup'     => '<span class="badge badge-purple" style="background:#f3e5f5; color:#7b1fa2;">Ready for Pickup</span>',
        'released'             => '<span class="badge badge-success">Released</span>',
        default                => '<span class="badge badge-gray">' . ucfirst($s) . '</span>',
    };
}

function payBadge(?string $s, string $method = 'cash'): string {
    if (!$s) return '<span class="badge badge-gray">No Payment</span>';
    $method = strtolower($method);
    if ($method !== 'cash') return '<span class="badge badge-success">PAID</span>';
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">' . $s . '</span>',
    };
}
?>

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
  <div class="card-header">
    <span class="card-title">Document Requests</span>
    <select id="filterStatus" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;" onchange="filterTable()">
      <option value="">All</option>
      <option value="pending">Pending</option>
      <option value="payment_verification">Verifying Payment</option>
      <option value="approved">Approved</option>
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
          <th>Copies</th>
          <th>Requested</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): 
            $method = strtolower($r['payment_method'] ?? 'cash');
            $isOnline = ($method !== 'cash');
        ?>
          <tr data-status="<?= $r['status'] ?>">
            <td style="font-size: 11px; color: var(--gray);"><?= $r['id'] ?></td>
            <td>
              <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
              <div style="font-size: 11px; color: var(--gray);"><?= htmlspecialchars($r['student_number']) ?> · <?= htmlspecialchars($r['course']) ?></div>
            </td>
            <td style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($r['document_type']) ?></td>
            <td style="text-align: center;"><?= $r['copies'] ?></td>
            <td style="font-size: 12px; color: var(--gray);"><?= date('M d, Y', strtotime($r['requested_at'])) ?></td>
            <td>
              <?php if ($r['pay_id']): ?>
                <div><?= payBadge($r['pay_status'], $method) ?></div>
                <div style="font-size: 11px; color: var(--gray); margin-top: 3px;">
                  ₱<?= number_format($r['amount'], 2) ?> · <?= strtoupper(str_replace('_',' ',$r['payment_method'])) ?>
                  <?php if ($r['reference_number']): ?>— <?= htmlspecialchars($r['reference_number']) ?><?php endif; ?>
                </div>
              <?php else: ?>
                <span class="badge badge-gray">No Payment</span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($r['status'], $method) ?></td>
            <td>
              <div style="display: flex; flex-direction: column; gap: 4px;">
                
                <?php if ($isOnline && in_array($r['status'], ['pending', 'payment_verification'])): ?>
                    <button class="btn btn-sm" style="background: #e8f0fe; color:#1252a3; border: 1px solid #90aee4; font-weight: 600;" onclick="updateDocStatus(<?= $r['id'] ?>, 'ready_for_pickup')">Mark Ready</button>

                <?php elseif (!$isOnline && $r['pay_status'] === 'pending'): ?>
                    <button class="btn btn-success btn-sm" onclick="verifyPayment(<?= $r['pay_id'] ?>, 'verified')">Verify</button>
                    <button class="btn btn-danger btn-sm" onclick="verifyPayment(<?= $r['pay_id'] ?>, 'rejected')">Reject</button>

                <?php elseif (!$isOnline && $r['pay_status'] === 'verified' && in_array($r['status'], ['pending', 'payment_verification'])): ?>
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
    const f = document.getElementById('filterStatus').value;
    document.querySelectorAll('#reqTable tbody tr').forEach(r => {
        r.style.display = (!f || r.dataset.status === f) ? '' : 'none';
    });
}

function showAlert(msg, type) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    Toast.fire({
        icon: type === 'error' ? 'error' : 'success',
        title: msg
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
        text: `Are you sure you want to mark this payment as ${status}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: status === 'verified' ? '#2d9e6b' : '#c0392b',
        confirmButtonText: 'Yes, proceed'
    });

    if (result.isConfirmed) {
        try {
            const data = await sendRequest({ action: 'verify_payment', pay_id: payId, status: status });
            if (data.success) {
                Swal.fire('Updated!', 'Payment status changed.', 'success').then(() => location.reload());
            } else {
                showAlert(data.message || 'Action failed', 'error');
            }
        } catch (err) { showAlert('Error: ' + err.message, 'error'); }
    }
}

async function updateDocStatus(reqId, status) {
    const displayStatus = status.replace(/_/g, ' ');
    const result = await Swal.fire({
        title: 'Update Request?',
        text: `Set this request status to "${displayStatus}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0a1628',
        confirmButtonText: 'Confirm'
    });

    if (result.isConfirmed) {
        try {
            const data = await sendRequest({ action: 'update_doc_status', request_id: reqId, status: status });
            if (data.success) {
                Swal.fire('Success!', `Request is now ${displayStatus}.`, 'success').then(() => location.reload());
            } else {
                showAlert(data.message || 'Update failed', 'error');
            }
        } catch (err) { showAlert('Error: ' + err.message, 'error'); }
    }
}

async function rejectDoc(reqId) {
    const { value: reason } = await Swal.fire({
        title: 'Reject Document Request',
        input: 'textarea',
        inputLabel: 'Provide a reason for rejection',
        inputPlaceholder: 'e.g., Incomplete requirements, blurred attachment...',
        showCancelButton: true,
        confirmButtonColor: '#c0392b',
        confirmButtonText: 'Confirm Rejection',
        inputValidator: (value) => {
            if (!value) return 'A reason is required to reject a request.';
        }
    });

    if (reason) {
        try {
            const data = await sendRequest({
                action: 'update_doc_status',
                request_id: reqId,
                status: 'rejected',
                rejection_reason: reason
            });
            if (data.success) {
                Swal.fire('Rejected', 'The request has been declined.', 'success').then(() => location.reload());
            } else {
                showAlert(data.message || 'Rejection failed', 'error');
            }
        } catch (err) { showAlert('Error: ' + err.message, 'error'); }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
