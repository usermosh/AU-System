<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('registrar');

$db = getDB();

// ── KPI counts ────────────────────────────────────────────────────
$totalReqs   = (int)$db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pendingReqs = (int)$db->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','payment_verification')")->fetchColumn();
$releasedReqs= (int)$db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'released'")->fetchColumn();
$pendingPay  = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$verifiedPay = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn();
$totalRevenue= (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'")->fetchColumn();

// ── Document requests by status ────────────────────────────────────
$reqByStatus = $db->query("
    SELECT status, COUNT(*) AS cnt
    FROM document_requests
    GROUP BY status
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Document requests by type ──────────────────────────────────────
$reqByType = $db->query("
    SELECT document_type, COUNT(*) AS cnt
    FROM document_requests
    GROUP BY document_type
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxType = max(array_column($reqByType, 'cnt') ?: [1]);

// ── Daily request trend (last 14 days) ────────────────────────────
$dailyReqs = $db->query("
    SELECT DATE(requested_at) AS day, COUNT(*) AS cnt
    FROM document_requests
    WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day
    ORDER BY day
")->fetchAll(PDO::FETCH_ASSOC);
$maxDayReq = max(array_column($dailyReqs, 'cnt') ?: [1]);

// ── Payment method breakdown ──────────────────────────────────────
$payMethods = $db->query("
    SELECT payment_method, COUNT(*) AS cnt, SUM(amount) AS total
    FROM payments
    GROUP BY payment_method
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxPayMethod = max(array_column($payMethods, 'cnt') ?: [1]);

// ── Revenue by day (last 14 days) ─────────────────────────────────
$dailyRevenue = $db->query("
    SELECT DATE(verified_at) AS day, SUM(amount) AS total
    FROM payments
    WHERE status='verified' AND verified_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day
    ORDER BY day
")->fetchAll(PDO::FETCH_ASSOC);
$maxRev = max(array_column($dailyRevenue, 'total') ?: [1]);

// ── Recent activity from logs (registrar scope) ───────────────────
$recentLogs = $db->query("
    SELECT l.*, u.username
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE l.affected_table IN ('document_requests','payments')
       OR l.action_performed LIKE '%document%'
       OR l.action_performed LIKE '%payment%'
       OR l.action_performed LIKE '%Payment%'
    ORDER BY l.date_time DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

// ── Processing time stats (approved → released) ───────────────────
$avgProcessingHours = (float)$db->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, requested_at, released_at))
    FROM document_requests
    WHERE status='released' AND released_at IS NOT NULL
")->fetchColumn();

// ── Top requesting students ────────────────────────────────────────
$topStudents = $db->query("
    SELECT s.first_name, s.last_name, s.student_number, s.course, COUNT(dr.id) AS cnt
    FROM document_requests dr
    JOIN students s ON s.id = dr.student_id
    GROUP BY dr.student_id
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
$maxStudReq = max(array_column($topStudents, 'cnt') ?: [1]);

$pageTitle = 'Registrar Logs Dashboard';
$activeNav = 'logs_dashboard.php';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.dash-grid.cols3 { grid-template-columns: 1fr 1fr 1fr; }
@media (max-width: 1100px) { .dash-grid, .dash-grid.cols3 { grid-template-columns: 1fr; } }

/* Bar charts */
.chart-bar-wrap { display: flex; flex-direction: column; gap: 6px; }
.chart-bar-row { display: flex; align-items: center; gap: 10px; }
.chart-bar-label { width: 130px; flex-shrink: 0; font-size: 12px; font-weight: 500; color: var(--navy); text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chart-bar-track { flex: 1; height: 24px; background: #f0ece4; border-radius: 6px; overflow: hidden; }
.chart-bar-fill { height: 100%; border-radius: 6px; min-width: 4px; transition: width 0.8s cubic-bezier(.4,0,.2,1); }
.chart-bar-count { flex-shrink: 0; width: 42px; text-align: right; font-size: 12px; font-weight: 700; color: var(--gray); }

/* Sparkline */
.sparkline-wrap { position: relative; height: 80px; display: flex; align-items: flex-end; gap: 3px; }
.spark-bar { flex: 1; border-radius: 4px 4px 0 0; min-height: 4px; cursor: pointer; transition: opacity 0.2s; position: relative; }
.spark-bar:hover { opacity: 0.75; }
.spark-bar .spark-tip { display: none; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%); background: var(--navy); color: #fff; font-size: 10px; padding: 3px 7px; border-radius: 5px; white-space: nowrap; z-index: 10; }
.spark-bar:hover .spark-tip { display: block; }

/* Status donut-style pills row */
.status-pill-row { display: flex; flex-wrap: wrap; gap: 8px; }
.status-pill {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; border-radius: 10px;
  border: 1px solid var(--border);
  background: #fff; flex: 1; min-width: 110px;
}
.status-pill-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.status-pill-label { font-size: 11px; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; }
.status-pill-val { font-size: 20px; font-weight: 700; color: var(--navy); margin-top: 2px; }

/* Feed */
.feed-list { display: flex; flex-direction: column; }
.feed-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0ece4; }
.feed-item:last-child { border-bottom: none; }
.feed-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.feed-body { flex: 1; min-width: 0; }
.feed-action { font-size: 13px; font-weight: 500; color: var(--navy); }
.feed-meta { font-size: 11px; color: var(--gray); margin-top: 2px; }
.feed-time { font-size: 11px; color: var(--gray); white-space: nowrap; flex-shrink: 0; }

/* Panel scroll */
.panel-scroll { max-height: 340px; overflow-y: auto; }
.panel-scroll::-webkit-scrollbar { width: 4px; }
.panel-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

/* User rank */
.user-rank-list { display: flex; flex-direction: column; gap: 10px; }
.user-rank-item { display: flex; align-items: center; gap: 12px; }
.user-rank-num { width: 22px; height: 22px; border-radius: 50%; background: var(--white); border: 1px solid var(--border); font-size: 10px; font-weight: 700; color: var(--gray); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.user-rank-num.gold { background: #fef9e7; border-color: var(--gold); color: var(--gold); }

/* KPI accent */
.kpi-pending  { --accent-color: var(--warning); }
.kpi-released { --accent-color: var(--success); }
.kpi-pay      { --accent-color: var(--info); }
.kpi-revenue  { --accent-color: #7b1fa2; }
.kpi-total    { --accent-color: var(--navy); }
.kpi-verified { --accent-color: var(--success); }
</style>

<!-- ── KPI Row ─────────────────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(6,1fr);margin-bottom:20px">
  <div class="stat-card kpi-total">
    <div class="stat-icon">📄</div>
    <div class="stat-value"><?= number_format($totalReqs) ?></div>
    <div class="stat-label">Total Requests</div>
  </div>
  <div class="stat-card kpi-pending">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= number_format($pendingReqs) ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card kpi-released">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= number_format($releasedReqs) ?></div>
    <div class="stat-label">Released</div>
  </div>
  <div class="stat-card kpi-pay">
    <div class="stat-icon">💳</div>
    <div class="stat-value"><?= number_format($pendingPay) ?></div>
    <div class="stat-label">Payments Pending</div>
  </div>
  <div class="stat-card kpi-verified">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= number_format($verifiedPay) ?></div>
    <div class="stat-label">Payments Verified</div>
  </div>
  <div class="stat-card kpi-revenue">
    <div class="stat-icon">₱</div>
    <div class="stat-value" style="font-size:22px">₱<?= number_format($totalRevenue, 0) ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>
</div>

<!-- ── Status Pills + Daily Request Trend ─────────────────────── -->
<div class="dash-grid" style="margin-bottom:20px">
  <!-- Status breakdown pills -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📊 Requests by Status</span>
    </div>
    <div class="card-body">
      <?php
      $statusCfg = [
        'pending'              => ['#d4a017', 'Pending'],
        'payment_verification' => ['#1565c0', 'Verifying Payment'],
        'approved'             => ['#2d9e6b', 'Approved'],
        'ready_for_pickup'     => ['#7b1fa2', 'Ready for Pickup'],
        'released'             => ['#1a7a50', 'Released'],
        'rejected'             => ['#c0392b', 'Rejected'],
      ];
      $statusMap = array_column($reqByStatus, 'cnt', 'status');
      ?>
      <div class="status-pill-row">
        <?php foreach ($statusCfg as $key => [$color, $label]):
          $val = $statusMap[$key] ?? 0;
        ?>
          <div class="status-pill">
            <div>
              <div style="display:flex;align-items:center;gap:6px">
                <div class="status-pill-dot" style="background:<?= $color ?>"></div>
                <span class="status-pill-label"><?= $label ?></span>
              </div>
              <div class="status-pill-val"><?= number_format($val) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Daily request trend -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📈 Daily Requests — Last 14 Days</span>
    </div>
    <div class="card-body">
      <?php if (empty($dailyReqs)): ?>
        <div class="empty-state" style="padding:20px"><div class="empty-desc">No data yet</div></div>
      <?php else: ?>
        <div class="sparkline-wrap">
          <?php foreach ($dailyReqs as $d):
            $h = $maxDayReq > 0 ? max(4, round(($d['cnt'] / $maxDayReq) * 72)) : 4;
          ?>
            <div class="spark-bar" style="height:<?= $h ?>px;background:linear-gradient(180deg,#7b1fa2,#4a0e72)">
              <span class="spark-tip"><?= date('M d', strtotime($d['day'])) ?>: <?= $d['cnt'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:6px">
          <span style="font-size:10px;color:var(--gray)"><?= date('M d', strtotime($dailyReqs[0]['day'])) ?></span>
          <span style="font-size:10px;color:var(--gray)"><?= date('M d', strtotime(end($dailyReqs)['day'])) ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Doc Type Breakdown + Payment Methods + Revenue ──────────── -->
<div class="dash-grid cols3" style="margin-bottom:20px">
  <!-- By document type -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📋 By Document Type</span>
    </div>
    <div class="card-body">
      <div class="chart-bar-wrap">
        <?php
        $typePalette = ['TOR'=>'#7b1fa2','Diploma'=>'#c9a84c','Certificate of Enrollment'=>'#1565c0','Good Moral'=>'#2d9e6b','Honorable Dismissal'=>'#d4a017','Transfer Credentials'=>'#e57373','Authentication'=>'#8a95a3'];
        foreach ($reqByType as $t):
          $pct = $maxType > 0 ? round(($t['cnt'] / $maxType) * 100) : 0;
          $color = $typePalette[$t['document_type']] ?? '#c9a84c';
        ?>
          <div class="chart-bar-row">
            <div class="chart-bar-label"><?= htmlspecialchars($t['document_type']) ?></div>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <div class="chart-bar-count"><?= $t['cnt'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Payment methods -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">💳 Payment Methods</span>
    </div>
    <div class="card-body">
      <?php if (empty($payMethods)): ?>
        <div class="empty-state" style="padding:20px"><div class="empty-desc">No payments yet</div></div>
      <?php else: ?>
        <div class="chart-bar-wrap">
          <?php
          $methodPalette = ['cash'=>'#2d9e6b','gcash'=>'#1565c0','maya'=>'#7b1fa2','bank_transfer'=>'#c9a84c'];
          $methodLabels  = ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer'];
          foreach ($payMethods as $m):
            $pct = $maxPayMethod > 0 ? round(($m['cnt'] / $maxPayMethod) * 100) : 0;
            $color = $methodPalette[$m['payment_method']] ?? '#8a95a3';
            $label = $methodLabels[$m['payment_method']] ?? strtoupper($m['payment_method']);
          ?>
            <div class="chart-bar-row">
              <div class="chart-bar-label"><?= $label ?></div>
              <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
              </div>
              <div class="chart-bar-count"><?= $m['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
          <?php foreach ($payMethods as $m):
            $label = $methodLabels[$m['payment_method']] ?? strtoupper($m['payment_method']);
          ?>
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
              <span style="color:var(--gray)"><?= $label ?></span>
              <span style="font-weight:600;color:var(--navy)">₱<?= number_format($m['total'], 2) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top students -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">👥 Top Requesting Students</span>
    </div>
    <div class="card-body">
      <?php if (empty($topStudents)): ?>
        <div class="empty-state" style="padding:20px"><div class="empty-desc">No data yet</div></div>
      <?php else: ?>
        <div class="user-rank-list">
          <?php foreach ($topStudents as $i => $s):
            $pct = $maxStudReq > 0 ? round(($s['cnt'] / $maxStudReq) * 100) : 0;
          ?>
            <div class="user-rank-item">
              <div class="user-rank-num <?= $i === 0 ? 'gold' : '' ?>"><?= $i+1 ?></div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                <div style="font-size:11px;color:var(--gray)"><?= htmlspecialchars($s['student_number']) ?> · <?= htmlspecialchars($s['course']) ?></div>
                <div style="height:5px;background:#f0ece4;border-radius:100px;margin-top:4px;overflow:hidden">
                  <div style="width:<?= $pct ?>%;height:100%;background:#7b1fa2;border-radius:100px"></div>
                </div>
              </div>
              <div style="font-size:12px;font-weight:700;color:var(--navy);width:24px;text-align:right"><?= $s['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Revenue Sparkline + Processing Time + Activity Feed ──────── -->
<div class="dash-grid" style="margin-bottom:20px">
  <!-- Revenue trend + processing time -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-header">
        <span class="card-title">₱ Daily Revenue — Last 14 Days</span>
      </div>
      <div class="card-body">
        <?php if (empty($dailyRevenue)): ?>
          <div class="empty-state" style="padding:16px"><div class="empty-desc">No verified payments yet</div></div>
        <?php else: ?>
          <div class="sparkline-wrap">
            <?php foreach ($dailyRevenue as $d):
              $h = $maxRev > 0 ? max(4, round(($d['total'] / $maxRev) * 72)) : 4;
            ?>
              <div class="spark-bar" style="height:<?= $h ?>px;background:linear-gradient(180deg,#2d9e6b,#1a5c40)">
                <span class="spark-tip"><?= date('M d', strtotime($d['day'])) ?>: ₱<?= number_format($d['total'], 0) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:6px">
            <span style="font-size:10px;color:var(--gray)"><?= date('M d', strtotime($dailyRevenue[0]['day'])) ?></span>
            <span style="font-size:10px;color:var(--gray)"><?= date('M d', strtotime(end($dailyRevenue)['day'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">⚡ Avg. Processing Time</span>
      </div>
      <div class="card-body" style="text-align:center;padding:28px">
        <?php if ($avgProcessingHours > 0): ?>
          <div style="font-size:48px;font-weight:700;font-family:'Playfair Display',serif;color:var(--navy)">
            <?= $avgProcessingHours < 24 ? number_format($avgProcessingHours, 1) . '<span style="font-size:20px;color:var(--gray)"> hrs</span>' : number_format($avgProcessingHours / 24, 1) . '<span style="font-size:20px;color:var(--gray)"> days</span>' ?>
          </div>
          <div style="font-size:12px;color:var(--gray);margin-top:8px">Average time from request to release</div>
        <?php else: ?>
          <div class="empty-desc">No released documents yet</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Activity feed -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🕑 Recent Activity</span>
      <a href="logs_records.php" style="font-size:12px;color:#7b1fa2;text-decoration:none;font-weight:600">View Records →</a>
    </div>
    <div class="card-body panel-scroll">
      <div class="feed-list">
        <?php
        $dotMap = [
          'document_requests' => '#7b1fa2',
          'payments'          => '#2d9e6b',
        ];
        foreach ($recentLogs as $l):
          $dc = $dotMap[$l['affected_table']] ?? 'var(--gray)';
          $isReject = stripos($l['action_performed'], 'rejected') !== false;
          if ($isReject) $dc = 'var(--danger)';
          $isVerify = stripos($l['action_performed'], 'verified') !== false;
          if ($isVerify) $dc = 'var(--success)';
        ?>
          <div class="feed-item">
            <div class="feed-dot" style="background:<?= $dc ?>"></div>
            <div class="feed-body">
              <div class="feed-action"><?= htmlspecialchars($l['action_performed']) ?></div>
              <div class="feed-meta">
                <span style="font-weight:600"><?= htmlspecialchars($l['username'] ?? 'System') ?></span>
                <?php if ($l['affected_table']): ?>
                  <span style="font-family:monospace;color:var(--info);font-size:10px"><?= htmlspecialchars($l['affected_table']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="feed-time"><?= date('M d, H:i', strtotime($l['date_time'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>