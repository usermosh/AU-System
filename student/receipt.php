<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('student');

$db        = getDB();
$studentId = $_SESSION['student_id'];
$payId     = (int)($_GET['id'] ?? 0);

if (!$payId) { header('Location: payments.php'); exit; }

$stmt = $db->prepare("
    SELECT p.*,
           s.student_number, s.first_name, s.middle_name, s.last_name,
           s.course, s.year_level, s.section, s.contact_number,
           u.email,
           dr.document_type, dr.copies, dr.purpose,
           dr.status AS request_status, dr.requested_at, dr.released_at,
           vu.username AS verified_by_name
    FROM payments p
    JOIN students   s  ON s.id  = p.student_id
    JOIN users      u  ON u.id  = s.user_id
    JOIN document_requests dr ON dr.id = p.document_request_id
    LEFT JOIN users vu ON vu.id = p.verified_by
    WHERE p.id = ? AND p.student_id = ?
");
$stmt->execute([$payId, $studentId]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) { header('Location: payments.php'); exit; }

$receiptNo = 'RCP-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT);
$fullName  = trim($p['first_name'] . ' ' . ($p['middle_name'] ? $p['middle_name'][0].'. ' : '') . $p['last_name']);
$methods   = ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer'];

$pageTitle = 'Receipt ' . $receiptNo;
$activeNav = 'payments.php';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.no-print { }
@media print {
  .no-print, .sidebar, .topbar { display:none !important; }
  .main-wrap { margin-left:0 !important; }
  .page-content { padding:0 !important; }
  body,html { background:#fff !important; }
  .receipt-wrap { box-shadow:none !important; border:1px solid #ccc !important; }
}

/* layout */
.receipt-wrap {
  max-width:660px; margin:0 auto;
  background:#fff; border-radius:16px;
  border:1px solid var(--border);
  box-shadow:0 6px 32px rgba(0,0,0,0.09);
  overflow:hidden;
}

/* ── header ── */
.rh {
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy-light,#1a3a5c) 100%);
  padding:28px 32px 48px; position:relative; overflow:hidden;
}
.rh-wave {
  position:absolute; bottom:-1px; left:0; right:0; height:36px;
  background:#fff; border-radius:60% 60% 0 0 / 36px 36px 0 0;
}
.rh-top { display:flex; align-items:center; gap:14px; margin-bottom:20px; }
.rh-logo {
  width:48px; height:48px; border-radius:50%; overflow:hidden; flex-shrink:0;
  background:linear-gradient(135deg,var(--gold),#8b1a1a);
  display:flex; align-items:center; justify-content:center;
  font-family:'Playfair Display',serif; font-size:18px; font-weight:900; color:#fff;
}
.rh-logo img { width:100%; height:100%; object-fit:cover; }
.rh-uni { font-family:'Playfair Display',serif; font-size:14px; font-weight:700; color:var(--gold); }
.rh-sub { font-size:11px; color:rgba(255,255,255,0.55); margin-top:2px; }
.rh-bottom { display:flex; align-items:flex-end; justify-content:space-between; }
.rh-title { font-family:'Playfair Display',serif; font-size:28px; font-weight:700; color:#fff; line-height:1; }
.rh-rno { font-size:11px; color:rgba(255,255,255,0.5); font-family:monospace; margin-top:5px; }

.status-pill {
  padding:5px 14px; border-radius:100px;
  font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px;
}
.pill-verified { background:rgba(45,158,107,.25); color:#6ee7b7; border:1px solid rgba(45,158,107,.4); }
.pill-pending  { background:rgba(212,160,23,.25);  color:#fcd34d; border:1px solid rgba(212,160,23,.4); }
.pill-rejected { background:rgba(192,57,43,.25);   color:#fca5a5; border:1px solid rgba(192,57,43,.4); }

/* ── body ── */
.rb { padding:28px 32px; }

.amount-box {
  text-align:center; padding:20px 24px; margin-bottom:24px;
  border:1.5px solid var(--gold); border-radius:12px;
  background:linear-gradient(135deg,#fdf8ee,#fff);
}
.ab-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.2px; color:var(--gray); margin-bottom:6px; }
.ab-amount { font-family:'Playfair Display',serif; font-size:44px; font-weight:700; color:var(--navy); line-height:1; }
.ab-meta { font-size:12px; color:var(--gray); margin-top:6px; }

.sec { margin-bottom:22px; }
.sec-title {
  font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:1.4px; color:var(--gray);
  padding-bottom:7px; border-bottom:1px solid var(--border); margin-bottom:10px;
}
.sec-row {
  display:flex; justify-content:space-between; align-items:flex-start;
  padding:6px 0; gap:16px;
}
.sec-row:not(:last-child) { border-bottom:1px dashed #ece8e0; }
.sec-lbl { font-size:12px; color:var(--gray); flex-shrink:0; width:155px; }
.sec-val { font-size:13px; font-weight:600; color:var(--navy); text-align:right; flex:1; }

/* rejected watermark */
.watermark {
  position:absolute; top:50%; left:50%;
  transform:translate(-50%,-50%) rotate(-25deg);
  font-family:'Playfair Display',serif; font-size:80px; font-weight:900;
  color:rgba(192,57,43,0.06); pointer-events:none; white-space:nowrap; z-index:0;
}

/* ── footer strip ── */
.rf {
  background:#f8f6f0; border-top:1px solid var(--border);
  padding:14px 32px;
  display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
}
.rf-note { font-size:11px; color:var(--gray); }
.rf-badge {
  background:var(--navy); color:rgba(255,255,255,.45);
  border-radius:8px; padding:6px 10px;
  font-size:9px; font-family:monospace; text-align:center; line-height:1.5;
}
</style>

<!-- controls -->
<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <a href="payments.php" class="btn" style="background:#fff;border:1px solid var(--border);color:var(--navy);">← Back to Payments</a>
  <button onclick="window.print()" class="btn btn-primary">🖨 Print Receipt</button>
</div>

<?php
$pillClass = match($p['status']) { 'verified'=>'pill-verified','rejected'=>'pill-rejected',default=>'pill-pending' };
$pillLabel = match($p['status']) { 'verified'=>'✓ Verified','rejected'=>'✗ Rejected',default=>'⏳ Pending' };
?>

<div class="receipt-wrap">

  <!-- Header -->
  <div class="rh">
    <div class="rh-top">
      <div class="rh-logo">
        <img src="<?= APP_URL ?>/assets/au-logo.png" alt="AU"
             onerror="this.style.display='none';this.parentElement.textContent='AU';">
      </div>
      <div>
        <div class="rh-uni">Arellano University</div>
        <div class="rh-sub">Office of the Registrar — Clearance System</div>
      </div>
    </div>
    <div class="rh-bottom">
      <div>
        <div class="rh-title">Official Receipt</div>
        <div class="rh-rno"><?= $receiptNo ?></div>
      </div>
      <span class="status-pill <?= $pillClass ?>"><?= $pillLabel ?></span>
    </div>
    <div class="rh-wave"></div>
  </div>

  <!-- Body -->
  <div class="rb" style="position:relative;">

    <?php if ($p['status'] === 'rejected'): ?>
      <div class="watermark">REJECTED</div>
    <?php endif; ?>

    <!-- Amount -->
    <div class="amount-box">
      <div class="ab-label">Amount Paid</div>
      <div class="ab-amount">₱<?= number_format($p['amount'], 2) ?></div>
      <div class="ab-meta">
        via <?= $methods[$p['payment_method']] ?? strtoupper($p['payment_method']) ?>
        <?php if ($p['reference_number']): ?> &nbsp;·&nbsp; Ref: <strong><?= htmlspecialchars($p['reference_number']) ?></strong><?php endif; ?>
      </div>
    </div>

    <!-- Student -->
    <div class="sec">
      <div class="sec-title">Student Information</div>
      <div class="sec-row"><span class="sec-lbl">Full Name</span><span class="sec-val"><?= htmlspecialchars($fullName) ?></span></div>
      <div class="sec-row"><span class="sec-lbl">Student No.</span><span class="sec-val" style="font-family:monospace"><?= htmlspecialchars($p['student_number']) ?></span></div>
      <div class="sec-row">
        <span class="sec-lbl">Course &amp; Year</span>
        <span class="sec-val"><?= htmlspecialchars($p['course']) ?> — Yr <?= $p['year_level'] ?><?= $p['section'] ? ' · ' . htmlspecialchars($p['section']) : '' ?></span>
      </div>
      <div class="sec-row"><span class="sec-lbl">Email</span><span class="sec-val"><?= htmlspecialchars($p['email']) ?></span></div>
      <?php if ($p['contact_number']): ?>
      <div class="sec-row"><span class="sec-lbl">Contact No.</span><span class="sec-val"><?= htmlspecialchars($p['contact_number']) ?></span></div>
      <?php endif; ?>
    </div>

    <!-- Document -->
    <div class="sec">
      <div class="sec-title">Document Request</div>
      <div class="sec-row"><span class="sec-lbl">Document Type</span><span class="sec-val"><?= htmlspecialchars($p['document_type']) ?></span></div>
      <div class="sec-row"><span class="sec-lbl">Copies</span><span class="sec-val"><?= $p['copies'] ?> <?= $p['copies'] > 1 ? 'copies' : 'copy' ?></span></div>
      <?php if ($p['purpose']): ?>
      <div class="sec-row"><span class="sec-lbl">Purpose</span><span class="sec-val"><?= htmlspecialchars($p['purpose']) ?></span></div>
      <?php endif; ?>
      <div class="sec-row"><span class="sec-lbl">Request Status</span><span class="sec-val"><?= ucfirst(str_replace('_',' ',$p['request_status'])) ?></span></div>
      <div class="sec-row"><span class="sec-lbl">Date Requested</span><span class="sec-val"><?= date('M d, Y  h:i A', strtotime($p['requested_at'])) ?></span></div>
      <?php if ($p['released_at']): ?>
      <div class="sec-row"><span class="sec-lbl">Date Released</span><span class="sec-val"><?= date('M d, Y  h:i A', strtotime($p['released_at'])) ?></span></div>
      <?php endif; ?>
    </div>

    <!-- Payment -->
    <div class="sec">
      <div class="sec-title">Payment Details</div>
      <div class="sec-row"><span class="sec-lbl">Receipt No.</span><span class="sec-val" style="font-family:monospace"><?= $receiptNo ?></span></div>
      <div class="sec-row"><span class="sec-lbl">Payment Method</span><span class="sec-val"><?= $methods[$p['payment_method']] ?? strtoupper($p['payment_method']) ?></span></div>
      <?php if ($p['reference_number']): ?>
      <div class="sec-row"><span class="sec-lbl">Reference No.</span><span class="sec-val" style="font-family:monospace"><?= htmlspecialchars($p['reference_number']) ?></span></div>
      <?php endif; ?>
      <?php if ($p['proof_notes']): ?>
      <div class="sec-row"><span class="sec-lbl">Payment Notes</span><span class="sec-val"><?= htmlspecialchars($p['proof_notes']) ?></span></div>
      <?php endif; ?>
      <div class="sec-row"><span class="sec-lbl">Date Submitted</span><span class="sec-val"><?= date('M d, Y  h:i A', strtotime($p['submitted_at'])) ?></span></div>
      <div class="sec-row">
        <span class="sec-lbl">Payment Status</span>
        <span class="sec-val">
          <?php if ($p['status']==='verified'): ?><span style="color:var(--success)">✓ Verified</span>
          <?php elseif ($p['status']==='rejected'): ?><span style="color:var(--danger)">✗ Rejected</span>
          <?php else: ?><span style="color:var(--warning)">⏳ Pending Verification</span>
          <?php endif; ?>
        </span>
      </div>
      <?php if ($p['verified_at']): ?>
      <div class="sec-row"><span class="sec-lbl">Verified On</span><span class="sec-val"><?= date('M d, Y  h:i A', strtotime($p['verified_at'])) ?></span></div>
      <div class="sec-row"><span class="sec-lbl">Verified By</span><span class="sec-val"><?= htmlspecialchars($p['verified_by_name'] ?? '—') ?></span></div>
      <?php endif; ?>
    </div>

  </div><!-- /rb -->

  <!-- Footer -->
  <div class="rf">
    <div>
      <div class="rf-note" style="font-weight:600;color:var(--navy);margin-bottom:2px;">Arellano University — Office of the Registrar</div>
      <div class="rf-note">This is an official computer-generated receipt. No signature required.</div>
      <div class="rf-note">Printed: <?= date('F j, Y  h:i A') ?></div>
    </div>
    <div class="rf-badge"><?= $receiptNo ?><br>AU-CLS</div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>