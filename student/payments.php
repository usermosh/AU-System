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

$total     = count($payments);
$verified  = array_filter($payments, fn($p) => $p['status'] === 'verified' || $p['payment_method'] !== 'cash');
$pending   = array_filter($payments, fn($p) => $p['status'] === 'pending' && $p['payment_method'] === 'cash');
$rejected  = array_filter($payments, fn($p) => $p['status'] === 'rejected');
$totalPaid = array_sum(array_column($verified, 'amount'));

// Unpaid document requests
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


function payBadge(?string $s, string $method): string {
    if ($method !== 'cash') return '<span class="badge badge-success">Verified</span>';
    return match($s) {
        'pending'  => '<span class="badge badge-warning">Pending Verification</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">—</span>',
    };
}

function reqBadge(?string $s, string $method): string {
    if ($method !== 'cash' && $s === 'payment_verification') return '<span class="badge badge-success">Approved</span>';
    return match($s) {
        'pending'              => '<span class="badge badge-warning">Pending</span>',
        'payment_verification' => '<span class="badge badge-info">Verifying</span>',
        'approved'             => '<span class="badge badge-success">Approved</span>',
        'rejected'             => '<span class="badge badge-danger">Rejected</span>',
        'ready_for_pickup'     => '<span class="badge badge-purple" style="background:#f3e5f5; color:#7b1fa2;">Ready</span>',
        'released'             => '<span class="badge badge-success">Released</span>',
        default                => '<span class="badge badge-gray">' . ucfirst($s ?? '') . '</span>',
    };
}
?>

<style>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: #fff; padding: 20px; border-radius: 12px; border-left: 5px solid var(--accent-color); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

#receipt-print-container { display: none; }

@media print {
    body * { visibility: hidden; }
    .sidebar, .topbar, .card, .stats-grid, .alert, .no-print { display: none !important; }
    
    /* Show receipt container only */
    #receipt-print-container, #receipt-print-container * { visibility: visible; }
    #receipt-print-container {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        color: #000;
    }
}

.receipt-wrapper { padding: 20px; border: 1px solid #000; }
.receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
.receipt-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
.receipt-table th, .receipt-table td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 12px; }
.receipt-table th { background-color: #f8f9fa !important; color: #000 !important; }
</style>

<div class="stats-grid no-print">
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
        <div class="stat-label">Pending (Cash)</div>
    </div>
    <div class="stat-card" style="--accent-color: var(--danger);">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= count($rejected) ?></div>
        <div class="stat-label">Rejected Payments</div>
    </div>
</div>

<?php if ($unpaid): ?>
<div class="alert alert-warning no-print" style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 20px; padding: 15px 20px; border-left: 5px solid #d4a017;">
    <span>⚠️ You have <strong><?= count($unpaid) ?></strong> document request(s) waiting for payment.</span>
    <a href="document_requests.php" class="btn btn-sm" style="background: var(--navy); color: var(--gold);">Pay Now →</a>
</div>
<?php endif; ?>

<div class="card no-print">
    <div class="card-header">
        <span class="card-title">💳 Payment History</span>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button class="btn" style="background: var(--navy); color: var(--gold); padding: 6px 14px; font-size: 12px;" onclick="showReceiptOptions()">
                📄 Generate Receipt
            </button>
            <select id="filterStatus" class="form-control" style="width:auto; padding:6px 12px; font-size:13px;" onchange="filterTable()">
                <option value="">All Status</option>
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
                    <th>Document</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference No.</th>
                    <th>Submitted</th>
                    <th>Req. Status</th>
                    <th>Pay Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $i => $p): 
                    $displayStatus = ($p['payment_method'] !== 'cash') ? 'verified' : $p['status'];
                ?>
                <tr data-status="<?= $displayStatus ?>">
                    <td style="font-size:11px; color:var(--gray);"><?= $i + 1 ?></td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($p['document_type']) ?></div>
                        <div style="font-size:11px; color:var(--gray);"><?= $p['copies'] ?> copy/ies</div>
                    </td>
                    <td style="font-weight:700; color:var(--navy);">₱<?= number_format($p['amount'], 2) ?></td>
                    <td>
                        <span class="badge <?= $p['payment_method'] === 'cash' ? 'badge-info' : 'badge-primary' ?>">
                            <?= strtoupper(str_replace('_', ' ', $p['payment_method'])) ?>
                        </span>
                    </td>
                    <td style="font-size:12px; font-family:monospace;"><?= htmlspecialchars($p['reference_number'] ?: '—') ?></td>
                    <td style="font-size:12px; color:var(--gray);">
                        <?= date('M d, Y', strtotime($p['submitted_at'])) ?>
                    </td>
                    <td><?= reqBadge($p['request_status'], $p['payment_method']) ?></td>
                    <td><?= payBadge($p['status'], $p['payment_method']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="padding: 40px; text-align: center; color: var(--gray);">
            <div style="font-size: 40px; margin-bottom: 10px;">💳</div>
            <p>No payment records found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="receipt-print-container"></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function filterTable() {
    const f = document.getElementById('filterStatus').value;
    document.querySelectorAll('#payTable tbody tr').forEach(r => {
        r.style.display = (!f || r.dataset.status === f) ? '' : 'none';
    });
}

async function showReceiptOptions() {
    const { value: timeframe } = await Swal.fire({
        title: 'Generate Official Receipt',
        text: 'Select the period for your printable summary:',
        input: 'select',
        inputOptions: {
            'all': 'All Records',
            'weekly': 'This Week',
            'monthly': 'This Month',
            'yearly': 'This Year'
        },
        inputPlaceholder: 'Select timeframe',
        showCancelButton: true,
        confirmButtonColor: '#0a1628',
        confirmButtonText: 'View & Print'
    });

    if (timeframe) {
        generateReceipt(timeframe);
    }
}

function generateReceipt(timeframe) {
    const payments = <?= json_encode($payments); ?>;
    const now = new Date();
    const studentName = "<?= htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>";
    
    let filtered = payments.filter(p => {
        const pDate = new Date(p.submitted_at);
        if (timeframe === 'all') return true;
        if (timeframe === 'weekly') {
            const lastWeek = new Date();
            lastWeek.setDate(now.getDate() - 7);
            return pDate >= lastWeek;
        }
        if (timeframe === 'monthly') {
            return pDate.getMonth() === now.getMonth() && pDate.getFullYear() === now.getFullYear();
        }
        if (timeframe === 'yearly') {
            return pDate.getFullYear() === now.getFullYear();
        }
        return true;
    });

    if (filtered.length === 0) {
        Swal.fire('No Records', 'No verified payments found for this period.', 'info');
        return;
    }

    let totalAmount = filtered.reduce((sum, p) => sum + parseFloat(p.amount), 0);

    let receiptHTML = `
        <div class="receipt-wrapper">
            <div class="receipt-header">
                <h2 style="margin:0;">ARELLANO UNIVERSITY</h2>
                <p style="margin:5px 0; font-size:12px; letter-spacing:2px;">OFFICIAL PAYMENT SUMMARY</p>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-size:13px;">
                <span><strong>Student:</strong> ${studentName}</span>
                <span><strong>Date Generated:</strong> ${now.toLocaleDateString()}</span>
            </div>
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    ${filtered.map(p => `
                        <tr>
                            <td>${new Date(p.submitted_at).toLocaleDateString()}</td>
                            <td>${p.document_type}</td>
                            <td>${p.payment_method.toUpperCase().replace('_', ' ')}</td>
                            <td>${p.reference_number || 'CASH'}</td>
                            <td style="text-align:right;">₱${parseFloat(p.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <h3 style="text-align:right; margin-top:20px;">Total Paid: ₱${totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</h3>
            <div style="margin-top:50px; text-align:center; font-size:10px; color:#666; border-top:1px solid #eee; padding-top:10px;">
                This is a system-generated summary of verified payments.<br>
                AU Clearance & Document System - 2026
            </div>
        </div>
    `;

    document.getElementById('receipt-print-container').innerHTML = receiptHTML;
    
    setTimeout(() => {
        window.print();
    }, 300);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
