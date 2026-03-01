<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('student');

$db = getDB();
$studentId = $_SESSION['student_id'];

$stmt = $db->prepare("
    SELECT dr.*, p.id as payment_id, p.status as payment_status, p.amount
    FROM document_requests dr
    LEFT JOIN payments p ON p.document_request_id = dr.id
    WHERE dr.student_id = ?
    ORDER BY dr.requested_at DESC
");
$stmt->execute([$studentId]);
$requests = $stmt->fetchAll();

$pageTitle = 'Document Requests';
$activeNav = 'document_requests.php';
require_once __DIR__ . '/../includes/header.php';

function statusBadge(string $status): string {
    return match($status) {
        'pending'              => '<span class="badge badge-warning">Pending</span>',
        'payment_verification' => '<span class="badge badge-info">Payment Verification</span>',
        'approved'             => '<span class="badge badge-success">Approved</span>',
        'rejected'             => '<span class="badge badge-danger">Rejected</span>',
        'ready_for_pickup'     => '<span class="badge badge-purple">Ready for Pickup</span>',
        'released'             => '<span class="badge badge-success">Released</span>',
        default                => '<span class="badge badge-gray">' . ucfirst($status) . '</span>',
    };
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('requestModal')">+ New Request</button>
</div>

<div id="alertMsg" class="alert" style="display:none;"></div>

<div class="card">
  <div class="card-header">
    <span class="card-title">My Document Requests</span>
    <span style="font-size: 12px; color: var(--gray);"><?= count($requests) ?> request(s)</span>
  </div>
  <div class="card-body" style="padding: 0;">
    <?php if ($requests): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Document Type</th>
            <th>Copies</th>
            <th>Purpose</th>
            <th>Requested</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $i => $r): ?>
            <tr>
              <td style="color: var(--gray); font-size: 12px;"><?= $i + 1 ?></td>
              <td><strong><?= htmlspecialchars($r['document_type']) ?></strong></td>
              <td><?= $r['copies'] ?></td>
              <td style="font-size: 12px; color: var(--gray); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($r['purpose'] ?: '—') ?></td>
              <td style="font-size: 12px; color: var(--gray);"><?= date('M d, Y', strtotime($r['requested_at'])) ?></td>
              <td>
                <?php if ($r['payment_id']): ?>
                  <?php if ($r['payment_status'] === 'verified'): ?>
                    <span class="badge badge-success">Paid ₱<?= number_format($r['amount'], 2) ?></span>
                  <?php elseif ($r['payment_status'] === 'rejected'): ?>
                    <span class="badge badge-danger">Payment Rejected</span>
                  <?php else: ?>
                    <span class="badge badge-warning">Verifying</span>
                  <?php endif; ?>
                <?php elseif ($r['status'] === 'pending'): ?>
                  <button class="btn btn-warning btn-sm" onclick="openPayment(<?= $r['id'] ?>)">Submit Payment</button>
                <?php else: ?>
                  <span class="badge badge-gray">—</span>
                <?php endif; ?>
              </td>
              <td><?= statusBadge($r['status']) ?></td>
              <td>
                <?php if ($r['rejection_reason']): ?>
                  <button class="btn btn-sm" style="background:#f5f5f5; color:#555;" onclick="showRemark('<?= htmlspecialchars(addslashes($r['rejection_reason'])) ?>')">Reason</button>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📄</div>
        <div class="empty-title">No Document Requests Yet</div>
        <div class="empty-desc">Click "+ New Request" to request official documents.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- New Request Modal -->
<div class="modal-overlay" id="requestModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">New Document Request</span>
      <button class="modal-close" onclick="closeModal('requestModal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="requestForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label>Document Type *</label>
          <select class="form-control" name="document_type" required>
            <option value="">— Select Document —</option>
            <option>TOR</option>
            <option>Diploma</option>
            <option>Certificate of Enrollment</option>
            <option>Good Moral</option>
            <option>Honorable Dismissal</option>
            <option>Transfer Credentials</option>
            <option>Authentication</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>No. of Copies *</label>
            <input type="number" class="form-control" name="copies" value="1" min="1" max="10" required>
          </div>
          <div class="form-group">
            <label>Purpose</label>
            <input type="text" class="form-control" name="purpose" placeholder="e.g. Employment, Scholarship">
          </div>
        </div>
        <p style="font-size: 12px; color: var(--gray);">⚠ Note: You will need to submit payment after your request is processed. Bring official receipt to the Registrar's Office upon release.</p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('requestModal')">Cancel</button>
      <button class="btn btn-primary" id="submitReqBtn" onclick="submitRequest()">Submit Request</button>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="paymentModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Submit Payment Record</span>
      <button class="modal-close" onclick="closeModal('paymentModal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="paymentForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="request_id" id="payRequestId">
        <div class="form-group">
          <label>Amount Paid (₱) *</label>
          <input type="number" class="form-control" name="amount" step="0.01" min="1" required placeholder="0.00">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Payment Method</label>
            <select class="form-control" name="payment_method">
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reference Number</label>
            <input type="text" class="form-control" name="reference_number" placeholder="OR No. / Ref No.">
          </div>
        </div>
        <div class="form-group">
          <label>Notes / Proof Details</label>
          <input type="text" class="form-control" name="proof_notes" placeholder="e.g. GCash receipt #123">
        </div>
        <p style="font-size: 12px; color: var(--gray);">Registrar will verify your payment before processing your request. Keep your official receipt.</p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('paymentModal')">Cancel</button>
      <button class="btn btn-primary" id="submitPayBtn" onclick="submitPayment()">Submit Payment</button>
    </div>
  </div>
</div>

<!-- Remark Modal -->
<div class="modal-overlay" id="remarkModal">
  <div class="modal-box" style="max-width: 420px;">
    <div class="modal-header">
      <span class="modal-title">Rejection Reason</span>
      <button class="modal-close" onclick="closeModal('remarkModal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="remarkText" style="background: #fdecea; border-radius: 8px; padding: 14px; color: var(--danger); font-size: 14px;"></div>
    </div>
  </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function showRemark(text) {
  document.getElementById('remarkText').textContent = text;
  openModal('remarkModal');
}
function openPayment(reqId) {
  document.getElementById('payRequestId').value = reqId;
  openModal('paymentModal');
}
function showAlertMsg(msg, type) {
  const el = document.getElementById('alertMsg');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth' });
}

async function submitRequest() {
  const btn = document.getElementById('submitReqBtn');
  btn.disabled = true; btn.textContent = 'Submitting...';
  const fd = new FormData(document.getElementById('requestForm'));
  fd.append('action', 'request_document');
  try {
    const res = await fetch('<?= APP_URL ?>/ajax/student.php', { method: 'POST', body: fd });
    const data = await res.json();
    closeModal('requestModal');
    showAlertMsg(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
  } catch { showAlertMsg('Network error.', 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Submit Request'; }
}

async function submitPayment() {
  const btn = document.getElementById('submitPayBtn');
  btn.disabled = true; btn.textContent = 'Submitting...';
  const fd = new FormData(document.getElementById('paymentForm'));
  fd.append('action', 'submit_payment');
  try {
    const res = await fetch('<?= APP_URL ?>/ajax/student.php', { method: 'POST', body: fd });
    const data = await res.json();
    closeModal('paymentModal');
    showAlertMsg(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
  } catch { showAlertMsg('Network error.', 'error'); }
  finally { btn.disabled = false; btn.textContent = 'Submit Payment'; }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
