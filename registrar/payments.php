<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// All payments with student and document info
$payments = $db->query("
    SELECT p.*,
           s.student_number, s.first_name, s.last_name, s.course,
           dr.document_type, dr.copies, dr.status as request_status
    FROM payments p
    JOIN students s ON s.id = p.student_id
    JOIN document_requests dr ON dr.id = p.document_request_id
    ORDER BY p.submitted_at DESC
")->fetchAll();

// Stats
$pending_pay  = array_filter($payments, fn($p) => $p['status'] === 'pending');
$verified_pay = array_filter($payments, fn($p) => $p['status'] === 'verified');
$rejected_pay = array_filter($payments, fn($p) => $p['status'] === 'rejected');
$total_amount = array_sum(array_column(array_filter($payments, fn($p) => $p['status'] === 'verified'), 'amount'));

$pageTitle = 'Payment Verification';
$activeNav = 'payments.php';
require_once __DIR__ . '/../includes/header.php';

function payBadge(?string $s): string {
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">—</span>',
    };
}
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card" style="--accent-color: var(--warning);">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= count($pending_pay) ?></div>
        <div class="stat-label">Pending Verification</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--success);">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= count($verified_pay) ?></div>
        <div class="stat-label">Verified</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--danger);">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= count($rejected_pay) ?></div>
        <div class="stat-label">Rejected</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--gold);">
        <div class="stat-icon">💰</div>
        <div class="stat-value">₱<?= number_format($total_amount, 0) ?></div>
        <div class="stat-label">Total Verified Amount</div>
    </div>
</div>

<div id="pageAlert" class="alert" style="display:none;"></div>

<div class="card">
    <div class="card-header">
        <span class="card-title">💳 Payment Records</span>
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="text" id="searchInput" class="form-control"
                   style="width:200px; padding:6px 12px; font-size:13px;"
                   placeholder="Search student..." oninput="searchTable()">
            <select id="filterStatus" class="form-control"
                    style="width:auto; padding:6px 12px; font-size:13px;" onchange="filterTable()">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>
    <div class="card-body" style="padding:0; overflow-x:auto;">
        <?php if ($payments): ?>
        <table class="data-table" id="payTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Document</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference No.</th>
                    <th>Notes</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr data-status="<?= $p['status'] ?>"
                    data-search="<?= strtolower($p['student_number'].' '.$p['first_name'].' '.$p['last_name']) ?>">
                    <td style="font-size:11px; color:var(--gray);"><?= $p['id'] ?></td>
                    <td>
                        <div style="font-weight:600; font-size:13px;">
                            <?= htmlspecialchars($p['last_name'].', '.$p['first_name']) ?>
                        </div>
                        <div style="font-size:11px; color:var(--gray); font-family:monospace;">
                            <?= htmlspecialchars($p['student_number']) ?>
                        </div>
                        <div style="font-size:11px; color:var(--gray);">
                            <?= htmlspecialchars($p['course']) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($p['document_type']) ?></div>
                        <div style="font-size:11px; color:var(--gray);"><?= $p['copies'] ?> cop<?= $p['copies'] > 1 ? 'ies' : 'y' ?></div>
                    </td>
                    <td style="font-weight:700; color:var(--navy); font-size:14px;">
                        ₱<?= number_format($p['amount'], 2) ?>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <?= strtoupper(str_replace('_', ' ', $p['payment_method'])) ?>
                        </span>
                    </td>
                    <td style="font-size:12px; font-family:monospace;">
                        <?= htmlspecialchars($p['reference_number'] ?: '—') ?>
                    </td>
                    <td style="font-size:12px; color:var(--gray); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <?= htmlspecialchars($p['proof_notes'] ?: '—') ?>
                    </td>
                    <td style="font-size:12px; color:var(--gray); white-space:nowrap;">
                        <?= date('M d, Y', strtotime($p['submitted_at'])) ?>
                        <div style="font-size:10px;"><?= date('H:i', strtotime($p['submitted_at'])) ?></div>
                    </td>
                    <td>
                        <?= payBadge($p['status']) ?>
                        <?php if ($p['verified_at']): ?>
                            <div style="font-size:10px; color:var(--gray); margin-top:3px;">
                                <?= date('M d, Y', strtotime($p['verified_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                        <div style="display:flex; flex-direction:column; gap:4px;">
                            <button class="btn btn-success btn-sm"
                                    onclick="verifyPayment(<?= $p['id'] ?>, 'verified')">
                                ✓ Verify
                            </button>
                            <button class="btn btn-danger btn-sm"
                                    onclick="verifyPayment(<?= $p['id'] ?>, 'rejected')">
                                ✗ Reject
                            </button>
                        </div>
                        <?php else: ?>
                            <span style="font-size:12px; color:var(--gray);">—</span>
                        <?php endif; ?>
                        <a href="receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm"
                           style="background:#fff;border:1px solid var(--border);color:var(--navy);white-space:nowrap;margin-top:6px;display:inline-flex;align-items:center;gap:4px;">
                            🧾 Receipt
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <div class="empty-title">No Payment Records Yet</div>
            <div class="empty-desc">Payments will appear here once students submit payment records for their document requests.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterTable() {
    const f = document.getElementById('filterStatus').value;
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#payTable tbody tr').forEach(r => {
        const statusMatch  = !f || r.dataset.status === f;
        const searchMatch  = !q || r.dataset.search.includes(q);
        r.style.display = (statusMatch && searchMatch) ? '' : 'none';
    });
}

function searchTable() { filterTable(); }

function showAlert(msg, type) {
    const el = document.getElementById('pageAlert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => el.style.display = 'none', 5000);
}

async function verifyPayment(payId, status) {
    const label = status === 'verified' ? 'verify' : 'reject';
    if (!confirm(`Are you sure you want to ${label} this payment?`)) return;

    try {
        const fd = new FormData();
        fd.append('action', 'verify_payment');
        fd.append('pay_id', payId);
        fd.append('status', status);
        fd.append('csrf_token', '<?= csrfToken() ?>');

        const res  = await fetch('<?= APP_URL ?>/ajax/registrar.php', { method: 'POST', body: fd });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch {
        showAlert('Network error. Please try again.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>