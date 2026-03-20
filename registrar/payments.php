<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

$payments = $db->query("
    SELECT p.*,
           s.student_number, s.first_name, s.last_name, s.course,
           dr.document_type, dr.copies, dr.status as request_status
    FROM payments p
    JOIN students s ON s.id = p.student_id
    JOIN document_requests dr ON dr.id = p.document_request_id
    ORDER BY p.submitted_at DESC
")->fetchAll();


$pending_pay  = array_filter($payments, fn($p) => strtolower($p['status']) === 'pending' && strtolower($p['payment_method']) === 'cash');
$verified_pay = array_filter($payments, fn($p) => strtolower($p['status']) === 'verified' || strtolower($p['payment_method']) !== 'cash');
$total_amount = array_sum(array_column($verified_pay, 'amount'));

$pageTitle = 'Payment Verification';
require_once __DIR__ . '/../includes/header.php';

function payBadge(?string $s, string $method = 'cash'): string {
    $method = strtolower($method);
    if ($method !== 'cash') {
        return '<span class="badge" style="background: #e6fcf5; color: #0ca678; border: 1px solid #b2f2bb; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 11px;">VERIFIED (ONLINE)</span>';
    }
    return match(strtolower($s)) {
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        default    => '<span class="badge badge-gray">—</span>',
    };
}
?>

<style>
    /* ─── SCREEN LAYOUT ─── */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
    .stat-card { background: white; padding: 20px; border-radius: 12px; border-left: 5px solid var(--accent-color); box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .stat-value { font-size: 24px; font-weight: 800; }
    .stat-label { font-size: 13px; color: #666; }

    /* ─── PRINT─── */
    @media print {
        body * { visibility: hidden; }
        .sidebar, .header, .stats-grid, .card, .modal-close, .no-print, .modal-footer, .modal-header { 
            display: none !important; 
        }
        #receiptModal, #receiptModal * { visibility: visible; }
        #receiptModal {
            position: absolute;
            left: 0; top: 0; width: 100%;
            display: flex !important;
            justify-content: center;
            background: white !important;
        }
        .modal-overlay { background: none !important; position: static !important; }
        .modal-box { box-shadow: none !important; border: none !important; width: 500px !important; margin: 0 auto !important; padding: 0 !important; }
        #receiptContent { padding: 20px !important; width: 100% !important; }
    }
</style>

<div class="stats-grid">
    <div class="stat-card" style="--accent-color: #fcc419;">
        <div class="stat-value"><?= count($pending_pay) ?></div>
        <div class="stat-label">Pending Cash</div>
    </div>
    <div class="stat-card" style="--accent-color: #40c057;">
        <div class="stat-value"><?= count($verified_pay) ?></div>
        <div class="stat-label">Verified (Online/Cash)</div>
    </div>
    <div class="stat-card" style="--accent-color: #228be6;">
        <div class="stat-value">₱<?= number_format($total_amount, 2) ?></div>
        <div class="stat-label">Total Collections</div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">💳 Payment Records</span>
        <div style="display:flex; gap:10px;">
            <input type="text" id="searchInput" class="form-control" placeholder="Search..." oninput="filterTable()">
            <select id="filterStatus" class="form-control" onchange="filterTable()">
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
                    <th>Student</th>
                    <th>Document</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): 
                    $method = strtolower($p['payment_method']);
                    // Fixed logic for filtering online payments
                    $displayStatus = ($method !== 'cash') ? 'verified' : strtolower($p['status']);
                ?>
                <tr data-status="<?= $displayStatus ?>" data-search="<?= strtolower($p['student_number'].' '.$p['first_name'].' '.$p['last_name']) ?>">
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($p['last_name'].', '.$p['first_name']) ?></div>
                        <small style="color:gray;"><?= htmlspecialchars($p['student_number']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($p['document_type']) ?> (<?= $p['copies'] ?>)</td>
                    <td style="font-weight:700;">₱<?= number_format($p['amount'], 2) ?></td>
                    <td><span class="badge badge-info"><?= strtoupper($method) ?></span></td>
                    <td style="font-family:monospace;"><?= htmlspecialchars($p['reference_number'] ?: '—') ?></td>
                    <td><?= payBadge($p['status'], $method) ?></td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:5px;">
                            <?php if ($method === 'cash' && strtolower($p['status']) === 'pending'): ?>
                                <button class="btn btn-success btn-sm" onclick="verifyPayment(<?= $p['id'] ?>, 'verified')">Verify</button>
                                <button class="btn btn-danger btn-sm" onclick="verifyPayment(<?= $p['id'] ?>, 'rejected')">Reject</button>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #0ca678; font-weight: 700;">✓ AUTO-VERIFIED</span>
                            <?php endif; ?>
                            <button class="btn btn-sm" style="border:1px solid #ddd; background:white;" onclick='viewReceipt(<?= json_encode($p) ?>)'>🧾 Receipt</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="receiptModal">
    <div class="modal-box" style="max-width: 450px; border-radius: 12px; background:#fff;">
        <div class="modal-header no-print" style="border-bottom: 2px dashed #eee; padding: 15px;">
            <span class="modal-title">Official Receipt Preview</span>
            <button class="modal-close" onclick="closeReceipt()">✕</button>
        </div>
        <div id="receiptContent" style="padding: 40px; background:white;"></div>
        <div class="modal-footer no-print" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-top: 1px solid #eee;">
            <select id="reportType" class="form-control" style="width: auto; font-size: 12px;">
                <option value="weekly">Weekly Report Format</option>
                <option value="monthly">Monthly Report Format</option>
                <option value="yearly">Yearly Report Format</option>
            </select>
            <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const f = document.getElementById('filterStatus').value.toLowerCase();
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#payTable tbody tr').forEach(r => {
        const rowStatus = r.getAttribute('data-status').toLowerCase();
        const rowSearch = r.getAttribute('data-search').toLowerCase();
        const statusMatch = (f === "") || (rowStatus === f);
        const searchMatch = (q === "") || (rowSearch.includes(q));
        r.style.display = (statusMatch && searchMatch) ? '' : 'none';
    });
}

function viewReceipt(p) {
    const method = p.payment_method.toLowerCase();
    // Receipt shows "PAID" for all non-cash or verified cash
    const isOnline = (method !== 'cash');
    const displayStatus = (isOnline || p.status.toLowerCase() === 'verified') ? 'PAID' : p.status.toUpperCase();

    const content = `
        <div style="text-align:center; font-family:'Courier New', monospace; margin-bottom:20px;">
            <strong style="font-size:22px;">ARELLANO UNIVERSITY</strong><br>
            <small>Legarda St, Sampaloc, Manila</small><br><br>
            <strong style="font-size:16px; text-decoration: underline; letter-spacing:2px;">OFFICIAL RECEIPT</strong>
        </div>
        <div style="font-family:'Courier New', monospace; font-size:14px; line-height:1.6;">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span>OR #: AU-2026-${p.id}</span>
                <span>Date: ${new Date(p.submitted_at).toLocaleDateString()}</span>
            </div>
            <div style="border-top: 1px dashed #000; margin-bottom:10px;"></div>
            <table style="width:100%; border-collapse: collapse;">
                <tr><td style="width:100px;">STUDENT:</td><td>${p.first_name} ${p.last_name}</td></tr>
                <tr><td>ID NO:</td><td>${p.student_number}</td></tr>
                <tr><td>COURSE:</td><td>${p.course}</td></tr>
                <tr><td>ITEM:</td><td>${p.document_type} (${p.copies} copy/ies)</td></tr>
            </table>
            <div style="border-top: 1px dashed #000; margin:15px 0 10px 0;"></div>
            <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:18px;">
                <span>TOTAL:</span>
                <span>₱${parseFloat(p.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
            </div>
            <br>
            <strong>METHOD :</strong> ${p.payment_method.toUpperCase()}<br>
            <strong>REF NO :</strong> ${p.reference_number || 'N/A'}<br>
            <strong>STATUS :</strong> ${displayStatus}
            <div style="margin-top:60px; text-align:center;">
                <div style="border-bottom: 1px solid #000; width: 220px; margin: 0 auto;"></div>
                <small>Registrar's Office Signature</small>
            </div>
        </div>
    `;
    document.getElementById('receiptContent').innerHTML = content;
    document.getElementById('receiptModal').classList.add('open');
}

function closeReceipt() { document.getElementById('receiptModal').classList.remove('open'); }

async function verifyPayment(payId, status) {
    if(!confirm(`Verify as ${status}?`)) return;
    const fd = new FormData();
    fd.append('action', 'verify_payment');
    fd.append('pay_id', payId);
    fd.append('status', status);
    const res = await fetch('<?= APP_URL ?>/ajax/registrar.php', { method: 'POST', body: fd });
    const data = await res.json();
    if(data.success) location.reload(); else alert(data.message);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
