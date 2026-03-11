<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// Stats
$pending_docs   = $db->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','payment_verification')")->fetchColumn();
$approved_docs  = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='approved'")->fetchColumn();
$pending_pay    = $db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
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

function statusBadge(string $s): string {
    return match($s) {
        'pending'              => '<span class="badge badge-warning">Pending</span>',
        'payment_verification' => '<span class="badge badge-info">Verifying Payment</span>',
        'approved'             => '<span class="badge badge-success">Approved</span>',
        'rejected'             => '<span class="badge badge-danger">Rejected</span>',
        'ready_for_pickup'     => '<span class="badge badge-purple">Ready for Pickup</span>',
        'released'             => '<span class="badge badge-success">Released</span>',
        default                => '<span class="badge badge-gray">' . ucfirst($s) . '</span>',
    };
}
function payBadge(?string $s): string {
    if (!$s) return '<span class="badge badge-gray">No Payment</span>';
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">' . $s . '</span>',
    };
}
?>

<!-- Banner -->
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
    <div class="stat-label">Payments to Verify</div>
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

<div id="pageAlert" class="alert" style="display:none;"></div>

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
        <?php foreach ($requests as $r): ?>
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
                <div><?= payBadge($r['pay_status']) ?></div>
                <div style="font-size: 11px; color: var(--gray); margin-top: 3px;">
                  ₱<?= number_format($r['amount'], 2) ?> · <?= strtoupper(str_replace('_',' ',$r['payment_method'])) ?>
                  <?php if ($r['reference_number']): ?>— <?= htmlspecialchars($r['reference_number']) ?><?php endif; ?>
                </div>
                <?php if ($r['pay_status'] === 'pending'): ?>
                  <div style="margin-top: 4px;">
                    <button class="btn btn-success btn-sm" onclick="verifyPayment(<?= $r['pay_id'] ?>, 'verified')">Verify</button>
                    <button class="btn btn-danger btn-sm" style="margin-left: 4px;" onclick="verifyPayment(<?= $r['pay_id'] ?>, 'rejected')">Reject</button>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-gray">No Payment</span>
              <?php endif; ?>
            </td>
            <td><?= statusBadge($r['status']) ?></td>
            <td>
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <?php if (in_array($r['status'], ['pending','payment_verification'])): ?>
                  <?php if ($r['pay_status'] === 'verified'): ?>
                    <button class="btn btn-success btn-sm" onclick="updateDocStatus(<?= $r['id'] ?>, 'approved')">Approve</button>
                  <?php endif; ?>
                  <button class="btn btn-danger btn-sm" onclick="rejectDoc(<?= $r['id'] ?>)">Reject</button>
                <?php elseif ($r['status'] === 'approved'): ?>
                  <button class="btn btn-sm" style="background: #e8f0fe; color:#1252a3; border: 1px solid #90aee4;" onclick="updateDocStatus(<?= $r['id'] ?>, 'ready_for_pickup')">Mark Ready</button>
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

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box" style="max-width: 440px;">
    <div class="modal-header">
      <span class="modal-title">Reject Document Request</span>
      <button class="modal-close" onclick="closeModal('rejectModal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="rejectForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="request_id" id="rejectReqId">
        <div class="form-group">
          <label>Rejection Reason *</label>
          <textarea class="form-control" name="rejection_reason" rows="3" required placeholder="Explain why this request is being rejected..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('rejectModal')">Cancel</button>
      <button class="btn btn-danger" id="confirmRejectBtn" onclick="confirmReject()">Confirm Rejection</button>
    </div>
  </div>
</div>

<script>
// Store CSRF token as a JS variable — avoids PHP-in-JS issues
const CSRF_TOKEN = '<?php echo csrfToken(); ?>';
const AJAX_URL   = '<?php echo APP_URL; ?>/ajax/registrar.php';

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

function filterTable() {
    const f = document.getElementById('filterStatus').value;
    document.querySelectorAll('#reqTable tbody tr').forEach(r => {
        r.style.display = (!f || r.dataset.status === f) ? '' : 'none';
    });
}

function showAlert(msg, type) {
    const el = document.getElementById('pageAlert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => el.style.display = 'none', 6000);
}

async function sendRequest(fields) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Response was not JSON:', text);
        throw new Error('Server returned invalid response.');
    }
}

async function verifyPayment(payId, status) {
    const label = status === 'verified' ? 'verify' : 'reject';
    if (!confirm('Are you sure you want to ' + label + ' this payment?')) return;
    try {
        const data = await sendRequest({
            action: 'verify_payment',
            pay_id: payId,
            status: status
        });
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) {
        showAlert('Error: ' + err.message, 'error');
        console.error(err);
    }
}

async function updateDocStatus(reqId, status) {
    const labels = {
        approved:         'approve',
        ready_for_pickup: 'mark as ready for pickup',
        released:         'mark as released'
    };
    const label = labels[status] || status;
    if (!confirm('Are you sure you want to ' + label + ' this request?')) return;

    try {
        const data = await sendRequest({
            action:     'update_doc_status',
            request_id: reqId,
            status:     status
        });
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) {
        showAlert('Error: ' + err.message, 'error');
        console.error(err);
    }
}

function rejectDoc(reqId) {
    document.getElementById('rejectReqId').value = reqId;
    document.getElementById('rejectModal').classList.add('open');
}

async function confirmReject() {
    const btn    = document.getElementById('confirmRejectBtn');
    const reason = document.querySelector('[name=rejection_reason]').value.trim();

    if (!reason) {
        alert('Please enter a rejection reason.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Rejecting...';

    try {
        const data = await sendRequest({
            action:           'update_doc_status',
            request_id:       document.getElementById('rejectReqId').value,
            status:           'rejected',
            rejection_reason: reason
        });
        closeModal('rejectModal');
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) {
        showAlert('Error: ' + err.message, 'error');
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirm Rejection';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
