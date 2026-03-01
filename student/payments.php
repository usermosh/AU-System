<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('student');

$db        = getDB();
$studentId = $_SESSION['student_id'];

// All payments for this student
$payments = $db->prepare("
    SELECT p.*,
           dr.document_type,
           dr.copies,
           dr.status as request_status
    FROM payments p
    JOIN document_requests dr ON dr.id = p.document_request_id
    WHERE p.student_id = ?
    ORDER BY p.submitted_at DESC
");
$payments->execute([$studentId]);
$payments = $payments->fetchAll();

// Summary counts
$total     = count($payments);
$verified  = array_filter($payments, fn($p) => $p['status'] === 'verified');
$pending   = array_filter($payments, fn($p) => $p['status'] === 'pending');
$rejected  = array_filter($payments, fn($p) => $p['status'] === 'rejected');
$totalPaid = array_sum(array_column(iterator_to_array((function() use ($verified) { yield from $verified; })()), 'amount'));

// Unpaid document requests (no payment submitted yet)
$unpaid = $db->prepare("
    SELECT dr.* FROM document_requests dr
    LEFT JOIN payments p ON p.document_request_id = dr.id
    WHERE dr.student_id = ?
    AND p.id IS NULL
    AND dr.status NOT IN ('rejected','released')
    ORDER BY dr.requested_at DESC
");
$unpaid->execute([$studentId]);
$unpaid = $unpaid->fetchAll();

$pageTitle = 'My Payments';
$activeNav = 'payments.php';
require_once __DIR__ . '/../includes/header.php';

function payBadge(?string $s): string {
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending Verification</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">—</span>',
    };
}
function reqBadge(?string $s): string {
    return match($s) {
        'pending'              => '<span class="badge badge-warning">Pending</span>',
        'payment_verification' => '<span class="badge badge-info">Verifying</span>',
        'approved'             => '<span class="badge badge-success">Approved</span>',
        'rejected'             => '<span class="badge badge-danger">Rejected</span>',
        'ready_for_pickup'     => '<span class="badge badge-purple">Ready</span>',
        'released'             => '<span class="badge badge-success">Released</span>',
        default                => '<span class="badge badge-gray">' . ucfirst($s ?? '') . '</span>',
    };
}
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card" style="--accent-color: var(--success);">
        <div class="stat-icon">💰</div>
        <div class="stat-value">₱<?= number_format($totalPaid, 2) ?></div>
        <div class="stat-label">Total Verified Paid</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--info);">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-label">Total Submissions</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--warning);">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= count($pending) ?></div>
        <div class="stat-label">Pending Verification</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--danger);">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= count($rejected) ?></div>
        <div class="stat-label">Rejected Payments</div>
    </div>
</div>

<div id="pageAlert" class="alert" style="display:none;"></div>

<!-- Unpaid Requests Banner -->
<?php if ($unpaid): ?>
<div class="alert alert-warning" style="display:block; margin-bottom: 20px;">
    ⚠️ You have <strong><?= count($unpaid) ?></strong> document request(s) with no payment submitted yet.
    <a href="document_requests.php" style="color: var(--warning); font-weight:700; margin-left:8px;">Submit Payment →</a>
</div>
<?php endif; ?>

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        <span class="card-title">💳 Payment History</span>
        <select id="filterStatus" class="form-control" style="width:auto; padding:6px 12px; font-size:13px;" onchange="filterTable()">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="verified">Verified</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>
    <div class="card-body" style="padding:0; overflow-x:auto;">
        <?php if ($payments): ?>
        <table class="data-table" id="payTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Document</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference No.</th>
                    <th>Notes</th>
                    <th>Submitted</th>
                    <th>Request Status</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr data-status="<?= $p['status'] ?>">
                    <td style="font-size:11px; color:var(--gray);"><?= $i + 1 ?></td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($p['document_type']) ?></div>
                        <div style="font-size:11px; color:var(--gray);"><?= $p['copies'] ?> cop<?= $p['copies'] > 1 ? 'ies' : 'y' ?></div>
                    </td>
                    <td style="font-weight:700; color:var(--navy);">₱<?= number_format($p['amount'], 2) ?></td>
                    <td>
                        <span class="badge badge-info"><?= strtoupper(str_replace('_', ' ', $p['payment_method'])) ?></span>
                    </td>
                    <td style="font-size:12px; font-family:monospace;"><?= htmlspecialchars($p['reference_number'] ?: '—') ?></td>
                    <td style="font-size:12px; color:var(--gray); max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <?= htmlspecialchars($p['proof_notes'] ?: '—') ?>
                    </td>
                    <td style="font-size:12px; color:var(--gray); white-space:nowrap;"><?= date('M d, Y H:i', strtotime($p['submitted_at'])) ?></td>
                    <td><?= reqBadge($p['request_status']) ?></td>
                    <td>
                        <?= payBadge($p['status']) ?>
                        <?php if ($p['verified_at']): ?>
                            <div style="font-size:10px; color:var(--gray); margin-top:3px;">
                                <?= date('M d, Y', strtotime($p['verified_at'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <div class="empty-title">No Payment Records</div>
            <div class="empty-desc">You have not submitted any payments yet. Request a document first then submit payment.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Document Fee Reference -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <span class="card-title">📌 Document Fee Reference</span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Document Type</th>
                    <th>Fee per Copy</th>
                    <th>Processing Time</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Transcript of Records (TOR)</strong></td>
                    <td>₱150.00</td>
                    <td>3–5 working days</td>
                    <td>Must be cleared first</td>
                </tr>
                <tr>
                    <td><strong>Diploma</strong></td>
                    <td>₱500.00</td>
                    <td>5–7 working days</td>
                    <td>Graduation clearance required</td>
                </tr>
                <tr>
                    <td><strong>Certificate of Enrollment</strong></td>
                    <td>₱50.00</td>
                    <td>1–2 working days</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><strong>Good Moral Certificate</strong></td>
                    <td>₱50.00</td>
                    <td>1–2 working days</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><strong>Honorable Dismissal</strong></td>
                    <td>₱100.00</td>
                    <td>2–3 working days</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><strong>Transfer Credentials</strong></td>
                    <td>₱200.00</td>
                    <td>3–5 working days</td>
                    <td>Clearance required</td>
                </tr>
                <tr>
                    <td><strong>Authentication</strong></td>
                    <td>₱75.00</td>
                    <td>1–2 working days</td>
                    <td>Per document</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable() {
    const f = document.getElementById('filterStatus').value;
    document.querySelectorAll('#payTable tbody tr').forEach(r => {
        r.style.display = (!f || r.dataset.status === f) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
