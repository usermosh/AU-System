<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// Kinukuha lahat ng payments kasama ang student at document info
$payments = $db->query("
    SELECT p.*,
           s.student_number, s.first_name, s.last_name, s.course,
           dr.document_type, dr.copies, dr.status as request_status
    FROM payments p
    JOIN students s ON s.id = p.student_id
    JOIN document_requests dr ON dr.id = p.document_request_id
    ORDER BY p.submitted_at DESC
")->fetchAll();

// Stats logic - Online payments are treated as verified in the stats
$pending_pay  = array_filter($payments, fn($p) => $p['status'] === 'pending' && strtolower($p['payment_method']) === 'cash');
$verified_pay = array_filter($payments, fn($p) => $p['status'] === 'verified' || strtolower($p['payment_method']) !== 'cash');
$rejected_pay = array_filter($payments, fn($p) => $p['status'] === 'rejected');
$total_amount = array_sum(array_column(array_filter($payments, fn($p) => $p['status'] === 'verified' || strtolower($p['payment_method']) !== 'cash'), 'amount'));

$pageTitle = 'Payment Verification';
$activeNav = 'payments.php';
require_once __DIR__ . '/../includes/header.php';

function payBadge(?string $s, string $method = 'cash'): string {
    $method = strtolower($method);
    // Force "VERIFIED" UI for online payments regardless of DB status for immediate feedback
    if ($method === 'gcash' || $method === 'paymaya') {
        return '<span class="badge" style="background: #e6fcf5; color: #0ca678; border: 1px solid #b2f2bb; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 11px;">VERIFIED (ONLINE)</span>';
    }
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">—</span>',
    };
}
?>

<div class="stats-grid">
    <div class="stat-card" style="--accent-color: var(--warning);">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= count($pending_pay) ?></div>
        <div class="stat-label">Pending Cash</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--success);">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= count($verified_pay) ?></div>
        <div class="stat-label">Verified (Online/Cash)</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--gold);">
        <div class="stat-icon">💰</div>
        <div class="stat-value">₱<?= number_format($total_amount, 2) ?></div>
        <div class="stat-label">Total Collections</div>
    </div>
</div>

<div id="pageAlert" class="alert" style="display:none;"></div>

<div class="card">
    <div class="card-header">
        <span class="card-title">💳 Payment Records</span>
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="text" id="searchInput" class="form-control" style="width:200px; padding:6px 12px; font-size:13px;" placeholder="Search student..." oninput="searchTable()">
            <select id="filterStatus" class="form-control" style="width:auto; padding:6px 12px; font-size:13px;" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>
    <div class="card-body" style="padding:0; overflow-x:auto;">
        <table class="data-table" id="payTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Document</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference No.</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): 
                    $method = strtolower($p['payment_method']);
                    // For filtering purposes: Online is always 'verified'
                    $rowStatus = ($method !== 'cash') ? 'verified' : $p['status'];
                ?>
                <tr data-status="<?= $rowStatus ?>" data-search="<?= strtolower($p['student_number'].' '.$p['first_name'].' '.$p['last_name']) ?>">
                    <td style="font-size:11px; color:var(--gray);"><?= $p['id'] ?></td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($p['last_name'].', '.$p['first_name']) ?></div>
                        <div style="font-size:11px; color:var(--gray);"><?= htmlspecialchars($p['student_number']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($p['document_type']) ?></div>
                        <div style="font-size:11px; color:var(--gray);"><?= $p['copies'] ?> copy/ies</div>
                    </td>
                    <td style="font-weight:700; color:var(--navy);">₱<?= number_format($p['amount'], 2) ?></td>
                    <td><span class="badge badge-info"><?= strtoupper($method) ?></span></td>
                    <td style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($p['reference_number'] ?: '—') ?></td>
                    <td style="font-size:12px; color:var(--gray);"><?= date('M d, Y', strtotime($p['submitted_at'])) ?></td>
                    <td><?= payBadge($p['status'], $method) ?></td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:4px;">
                            <?php if ($method === 'cash' && $p['status'] === 'pending'): ?>
                                <button class="btn btn-success btn-sm" onclick="verifyPayment(<?= $p['id'] ?>, 'verified')">✓ Verify</button>
                                <button class="btn btn-danger btn-sm" onclick="verifyPayment(<?= $p['id'] ?>, 'rejected')">✗ Reject</button>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #0ca678; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                    <span style="font-size:14px;">✓</span> AUTO-VERIFIED
                                </span>
                            <?php endif; ?>
                            <button class="btn btn-sm" style="background:#fff; border:1px solid #ddd; color:var(--navy); font-size:11px; font-weight:600; margin-top:4px;" onclick="viewReceipt(<?= htmlspecialchars(json_encode($p)) ?>)">🧾 Receipt</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="receiptModal">
    <div class="modal-box" style="max-width: 450px; border-radius: 12px;">
        <div class="modal-header" style="border-bottom: 2px dashed #eee; padding-bottom:15px;">
            <span class="modal-title">Official Receipt Preview</span>
            <button class="modal-close" onclick="closeReceipt()">✕</button>
        </div>
        <div class="modal-body" id="receiptContent" style="padding: 20px; font-family: 'Courier New', monospace; line-height: 1.6; background: #fff;">
            </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #eee; padding-top: 15px;">
            <select id="reportType" class="form-control" style="width: auto; font-size: 12px;">
                <option value="weekly">Weekly Report Format</option>
                <option value="monthly">Monthly Report Format</option>
                <option value="yearly">Yearly Report Format</option>
            </select>
            <button class="btn btn-primary btn-sm" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const f = document.getElementById('filterStatus').value;
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#payTable tbody tr').forEach(r => {
        const statusMatch = !f || r.dataset.status === f;
        const searchMatch = !q || r.dataset.search.includes(q);
        r.style.display = (statusMatch && searchMatch) ? '' : 'none';
    });
}

function searchTable() { filterTable(); }

async function verifyPayment(payId, status) {
    if (!confirm(`Are you sure you want to mark this as ${status}?`)) return;
    try {
        const fd = new FormData();
        fd.append('action', 'verify_payment');
        fd.append('pay_id', payId);
        fd.append('status', status);
        fd.append('csrf_token', '<?= csrfToken() ?>');

        const res = await fetch('<?= APP_URL ?>/ajax/registrar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
    } catch (err) { alert('System error occurred.'); }
}

function viewReceipt(p) {
    const content = `
        <div style="text-align:center; margin-bottom:15px;">
            <strong style="font-size:18px;">ARELLANO UNIVERSITY</strong><br>
            <small>Legarda St, Sampaloc, Manila</small><br>
            <strong style="font-size:14px; text-decoration: underline;">OFFICIAL RECEIPT</strong>
        </div>
        <div style="font-size:13px;">
            <div style="display:flex; justify-content:space-between;">
                <span>OR #: 2026-PAY-${p.id}</span>
                <span>Date: ${new Date(p.submitted_at).toLocaleDateString()}</span>
            </div>
            <hr style="border:0.5px dashed #000; margin: 10px 0;">
            <strong>STUDENT:</strong> ${p.first_name} ${p.last_name}<br>
            <strong>ID NO:</strong> ${p.student_number}<br>
            <strong>COURSE:</strong> ${p.course}<br>
            <strong>DOCUMENT:</strong> ${p.document_type}<br>
            <strong>COPIES:</strong> ${p.copies}<br>
            <hr style="border:0.5px dashed #000; margin: 10px 0;">
            <div style="display:flex; justify-content:space-between; font-size:16px; font-weight:bold;">
                <span>TOTAL AMOUNT:</span>
                <span>₱${parseFloat(p.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
            </div>
            <br>
            <strong>PAYMENT METHOD:</strong> ${p.payment_method.toUpperCase()}<br>
            <strong>REFERENCE NO:</strong> ${p.reference_number || 'N/A'}<br>
            <strong>STATUS:</strong> ${p.status.toUpperCase() === 'VERIFIED' ? 'PAID' : p.status.toUpperCase()}
        </div>
        <div style="margin-top:30px; text-align:center;">
            <div style="border-bottom: 1px solid #000; width: 200px; margin: 0 auto;"></div>
            <small>Registrar's Office Signature</small>
        </div>
    `;
    document.getElementById('receiptContent').innerHTML = content;
    document.getElementById('receiptModal').classList.add('open');
}

function closeReceipt() {
    document.getElementById('receiptModal').classList.remove('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
