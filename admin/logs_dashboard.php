<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
initSession();
requireRole('admin');

$db = getDB();

// ── System-wide KPIs ──────────────────────────────────────────────
$totalUsers      = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents   = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalDepts      = (int)$db->query("SELECT COUNT(*) FROM departments WHERE is_active=1")->fetchColumn();
$totalReqs       = (int)$db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pendingReqs     = (int)$db->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','payment_verification')")->fetchColumn();
$releasedReqs    = (int)$db->query("SELECT COUNT(*) FROM document_requests WHERE status='released'")->fetchColumn();
$totalClearances = (int)$db->query("SELECT COUNT(*) FROM clearances")->fetchColumn();
$completedClear  = (int)$db->query("SELECT COUNT(*) FROM clearances WHERE overall_status='completed'")->fetchColumn();
$pendingPay      = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
$verifiedPay     = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='verified'")->fetchColumn();
$totalRevenue    = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'")->fetchColumn();
$totalLogs       = (int)$db->query("SELECT COUNT(*) FROM logs")->fetchColumn();

// ── Users by role ────────────────────────────────────────────────
$usersByRole = $db->query("
    SELECT role, COUNT(*) AS cnt
    FROM users
    GROUP BY role
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxRole = max(array_column($usersByRole, 'cnt') ?: [1]);

// ── Document requests by status ───────────────────────────────────
$reqByStatus = $db->query("
    SELECT status, COUNT(*) AS cnt
    FROM document_requests
    GROUP BY status
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Document requests by type ─────────────────────────────────────
$reqByType = $db->query("
    SELECT document_type, COUNT(*) AS cnt
    FROM document_requests
    GROUP BY document_type
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxType = max(array_column($reqByType, 'cnt') ?: [1]);

// ── Clearances by status ──────────────────────────────────────────
$clearByStatus = $db->query("
    SELECT overall_status, COUNT(*) AS cnt
    FROM clearances
    GROUP BY overall_status
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxClear = max(array_column($clearByStatus, 'cnt') ?: [1]);

// ── Clearances by type ────────────────────────────────────────────
$clearByType = $db->query("
    SELECT clearance_type, COUNT(*) AS cnt
    FROM clearances
    GROUP BY clearance_type
")->fetchAll(PDO::FETCH_ASSOC);

// ── Students by course ────────────────────────────────────────────
$studentsByCourse = $db->query("
    SELECT course, COUNT(*) AS cnt
    FROM students
    GROUP BY course
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxCourse = max(array_column($studentsByCourse, 'cnt') ?: [1]);

// ── Daily request trend (last 14 days) ───────────────────────────
$dailyReqs = $db->query("
    SELECT DATE(requested_at) AS day, COUNT(*) AS cnt
    FROM document_requests
    WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day
    ORDER BY day
")->fetchAll(PDO::FETCH_ASSOC);
$maxDayReq = max(array_column($dailyReqs, 'cnt') ?: [1]);

// ── Daily logins (last 14 days) ───────────────────────────────────
$dailyLogins = $db->query("
    SELECT DATE(date_time) AS day, COUNT(*) AS cnt
    FROM logs
    WHERE action_performed LIKE '%logged in%'
    AND date_time >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day
    ORDER BY day
")->fetchAll(PDO::FETCH_ASSOC);
$maxLogin = max(array_column($dailyLogins, 'cnt') ?: [1]);

// ── Payment methods breakdown ─────────────────────────────────────
$payMethods = $db->query("
    SELECT payment_method, COUNT(*) AS cnt, SUM(amount) AS total
    FROM payments
    GROUP BY payment_method
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
$maxPayMethod = max(array_column($payMethods, 'cnt') ?: [1]);
$methodLabels = ['cash'=>'Cash','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer'];

// ── Revenue by day (last 14 days) ─────────────────────────────────
$dailyRevenue = $db->query("
    SELECT DATE(verified_at) AS day, SUM(amount) AS total
    FROM payments
    WHERE status='verified' AND verified_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY day
    ORDER BY day
")->fetchAll(PDO::FETCH_ASSOC);
$maxRev = max(array_column($dailyRevenue, 'total') ?: [1]);

// ── Avg processing time ───────────────────────────────────────────
$avgProcessingHours = (float)$db->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, requested_at, released_at))
    FROM document_requests
    WHERE status='released' AND released_at IS NOT NULL
")->fetchColumn();

// ── Top requesting students ───────────────────────────────────────
$topStudents = $db->query("
    SELECT s.first_name, s.last_name, s.student_number, s.course, COUNT(dr.id) AS cnt
    FROM document_requests dr
    JOIN students s ON s.id = dr.student_id
    GROUP BY dr.student_id
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
$maxStudReq = max(array_column($topStudents, 'cnt') ?: [1]);

// ── Most active departments (clearance reviews) ───────────────────
$topDepts = $db->query("
    SELECT d.department_name, d.department_code, COUNT(cs.id) AS cnt,
           SUM(cs.status='cleared') AS cleared,
           SUM(cs.status='deficiency') AS deficiency
    FROM clearance_status cs
    JOIN departments d ON d.id = cs.department_id
    WHERE cs.reviewed_at IS NOT NULL
    GROUP BY cs.department_id
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Recent activity (all system) ──────────────────────────────────
$recentLogs = $db->query("
    SELECT l.*, u.username, u.role
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.date_time DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'System Analytics Dashboard';
$activeNav = 'logs_dashboard.php';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.dash-grid.cols3 { grid-template-columns:1fr 1fr 1fr; }
.dash-grid.cols4 { grid-template-columns:1fr 1fr 1fr 1fr; }
@media(max-width:1100px){ .dash-grid,.dash-grid.cols3,.dash-grid.cols4{ grid-template-columns:1fr; } }

/* Bar charts */
.chart-bar-wrap { display:flex; flex-direction:column; gap:6px; }
.chart-bar-row { display:flex; align-items:center; gap:10px; }
.chart-bar-label { width:140px; flex-shrink:0; font-size:12px; font-weight:500; color:var(--navy); text-align:right; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chart-bar-track { flex:1; height:24px; background:#f0ece4; border-radius:6px; overflow:hidden; }
.chart-bar-fill { height:100%; border-radius:6px; min-width:4px; transition:width 0.8s cubic-bezier(.4,0,.2,1); }
.chart-bar-count { flex-shrink:0; width:42px; text-align:right; font-size:12px; font-weight:700; color:var(--gray); }

/* Sparkline */
.sparkline-wrap { position:relative; height:80px; display:flex; align-items:flex-end; gap:3px; }
.spark-bar { flex:1; border-radius:4px 4px 0 0; min-height:4px; cursor:pointer; transition:opacity 0.2s; position:relative; }
.spark-bar:hover { opacity:0.75; }
.spark-bar .spark-tip { display:none; position:absolute; bottom:110%; left:50%; transform:translateX(-50%); background:var(--navy); color:#fff; font-size:10px; padding:3px 7px; border-radius:5px; white-space:nowrap; z-index:10; }
.spark-bar:hover .spark-tip { display:block; }

/* Status pills */
.status-pill-row { display:flex; flex-wrap:wrap; gap:8px; }
.status-pill { display:flex; align-items:center; gap:8px; padding:10px 16px; border-radius:10px; border:1px solid var(--border); background:#fff; flex:1; min-width:110px; }
.status-pill-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.status-pill-label { font-size:11px; font-weight:600; color:var(--gray); text-transform:uppercase; letter-spacing:0.5px; }
.status-pill-val { font-size:20px; font-weight:700; color:var(--navy); margin-top:2px; }

/* Feed */
.feed-list { display:flex; flex-direction:column; }
.feed-item { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid #f0ece4; }
.feed-item:last-child { border-bottom:none; }
.feed-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
.feed-body { flex:1; min-width:0; }
.feed-action { font-size:13px; font-weight:500; color:var(--navy); }
.feed-meta { font-size:11px; color:var(--gray); margin-top:2px; }
.feed-time { font-size:11px; color:var(--gray); white-space:nowrap; flex-shrink:0; }

/* User rank */
.user-rank-list { display:flex; flex-direction:column; gap:10px; }
.user-rank-item { display:flex; align-items:center; gap:12px; }
.user-rank-num { width:22px; height:22px; border-radius:50%; background:var(--white); border:1px solid var(--border); font-size:10px; font-weight:700; color:var(--gray); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.user-rank-num.gold { background:#fef9e7; border-color:var(--gold); color:var(--gold); }

.panel-scroll { max-height:340px; overflow-y:auto; }
.panel-scroll::-webkit-scrollbar { width:4px; }
.panel-scroll::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }
</style>

<!-- Banner -->
<div style="background:linear-gradient(135deg,var(--navy) 0%,var(--navy-light) 100%);border-radius:16px;padding:24px 28px;margin-bottom:24px;color:#fff;position:relative;overflow:hidden;">
  <div style="position:absolute;right:-20px;top:-20px;width:160px;height:160px;border-radius:50%;background:rgba(201,168,76,0.1);"></div>
  <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--gold);">System Analytics Dashboard</div>
  <div style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:4px;">Full system overview — all roles, all data</div>
</div>

<!-- ── KPI Row 1: Users & Clearances ──────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="stat-card" style="--accent-color:var(--navy-light);">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= number_format($totalUsers) ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--info);">
    <div class="stat-icon">🎓</div>
    <div class="stat-value"><?= number_format($totalStudents) ?></div>
    <div class="stat-label">Students</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--success);">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= number_format($completedClear) ?></div>
    <div class="stat-label">Clearances Completed</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--warning);">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= number_format($totalClearances - $completedClear) ?></div>
    <div class="stat-label">Clearances In Progress</div>
  </div>
</div>

<!-- ── KPI Row 2: Docs & Payments ─────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card" style="--accent-color:var(--gold);">
    <div class="stat-icon">📄</div>
    <div class="stat-value"><?= number_format($totalReqs) ?></div>
    <div class="stat-label">Document Requests</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--warning);">
    <div class="stat-icon">📥</div>
    <div class="stat-value"><?= number_format($pendingReqs) ?></div>
    <div class="stat-label">Pending Requests</div>
  </div>
  <div class="stat-card" style="--accent-color:var(--info);">
    <div class="stat-icon">💳</div>
    <div class="stat-value"><?= number_format($pendingPay) ?></div>
    <div class="stat-label">Payments Pending</div>
  </div>
  <div class="stat-card" style="--accent-color:#7b1fa2;">
    <div class="stat-icon">₱</div>
    <div class="stat-value" style="font-size:22px">₱<?= number_format($totalRevenue, 0) ?></div>
    <div class="stat-label">Total Revenue</div>
  </div>
</div>

<!-- ── Users by Role + Students by Course ─────────────────────── -->
<div class="dash-grid" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">👥 Users by Role</span></div>
    <div class="card-body">
      <?php
      $rolePalette = ['student'=>'#1565c0','department'=>'#7b1fa2','registrar'=>'#d4a017','admin'=>'#0a1628'];
      $roleLabels  = ['student'=>'Student','department'=>'Department','registrar'=>'Registrar','admin'=>'Admin'];
      ?>
      <div class="chart-bar-wrap">
        <?php foreach ($usersByRole as $r):
          $pct = $maxRole > 0 ? round(($r['cnt'] / $maxRole) * 100) : 0;
          $color = $rolePalette[$r['role']] ?? '#8a95a3';
          $label = $roleLabels[$r['role']] ?? ucfirst($r['role']);
        ?>
          <div class="chart-bar-row">
            <div class="chart-bar-label"><?= $label ?></div>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <div class="chart-bar-count"><?= $r['cnt'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">🎓 Students by Course</span></div>
    <div class="card-body panel-scroll">
      <div class="chart-bar-wrap">
        <?php foreach ($studentsByCourse as $s):
          $pct = $maxCourse > 0 ? round(($s['cnt'] / $maxCourse) * 100) : 0;
        ?>
          <div class="chart-bar-row">
            <div class="chart-bar-label" title="<?= htmlspecialchars($s['course']) ?>"><?= htmlspecialchars($s['course']) ?></div>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="width:<?= $pct ?>%;background:#1565c0"></div>
            </div>
            <div class="chart-bar-count"><?= $s['cnt'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Doc Request Status Pills + Daily Trend ─────────────────── -->
<div class="dash-grid" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">📊 Document Requests by Status</span></div>
    <div class="card-body">
      <?php
      $statusCfg = [
        'pending'              => ['#d4a017','Pending'],
        'payment_verification' => ['#1565c0','Verifying Payment'],
        'approved'             => ['#2d9e6b','Approved'],
        'ready_for_pickup'     => ['#7b1fa2','Ready for Pickup'],
        'released'             => ['#1a7a50','Released'],
        'rejected'             => ['#c0392b','Rejected'],
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

  <div class="card">
    <div class="card-header"><span class="card-title">📈 Daily Requests — Last 14 Days</span></div>
    <div class="card-body">
      <?php if (empty($dailyReqs)): ?>
        <div class="empty-state" style="padding:20px"><div class="empty-desc">No data yet</div></div>
      <?php else: ?>
        <div class="sparkline-wrap">
          <?php foreach ($dailyReqs as $d):
            $h = $maxDayReq > 0 ? max(4, round(($d['cnt'] / $maxDayReq) * 72)) : 4;
          ?>
            <div class="spark-bar" style="height:<?= $h ?>px;background:linear-gradient(180deg,var(--gold),#a07830)">
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

<!-- ── Doc Type + Clearance Status + Payment Methods ─────────── -->
<div class="dash-grid cols3" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">📋 By Document Type</span></div>
    <div class="card-body">
      <?php
      $typePalette = ['TOR'=>'#7b1fa2','Diploma'=>'#c9a84c','Certificate of Enrollment'=>'#1565c0','Good Moral'=>'#2d9e6b','Honorable Dismissal'=>'#d4a017','Transfer Credentials'=>'#e57373','Authentication'=>'#8a95a3'];
      ?>
      <div class="chart-bar-wrap">
        <?php foreach ($reqByType as $t):
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

  <div class="card">
    <div class="card-header"><span class="card-title">✅ Clearances by Status</span></div>
    <div class="card-body">
      <?php
      $clearCfg = ['completed'=>['#2d9e6b','Completed'],'in_progress'=>['#1565c0','In Progress'],'pending'=>['#d4a017','Pending']];
      $clearMap  = array_column($clearByStatus, 'cnt', 'overall_status');
      ?>
      <div class="chart-bar-wrap">
        <?php foreach ($clearCfg as $key => [$color, $label]):
          $val = $clearMap[$key] ?? 0;
          $pct = $maxClear > 0 ? round(($val / $maxClear) * 100) : 0;
        ?>
          <div class="chart-bar-row">
            <div class="chart-bar-label"><?= $label ?></div>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <div class="chart-bar-count"><?= $val ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
        <?php foreach ($clearByType as $ct): ?>
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span style="color:var(--gray)"><?= ucfirst($ct['clearance_type']) ?> Clearance</span>
            <span style="font-weight:600;color:var(--navy)"><?= $ct['cnt'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">💳 Payment Methods</span></div>
    <div class="card-body">
      <?php if (empty($payMethods)): ?>
        <div class="empty-state" style="padding:20px"><div class="empty-desc">No payments yet</div></div>
      <?php else: ?>
        <?php
        $methodPalette = ['cash'=>'#2d9e6b','gcash'=>'#1565c0','maya'=>'#7b1fa2','bank_transfer'=>'#c9a84c'];
        ?>
        <div class="chart-bar-wrap">
          <?php foreach ($payMethods as $m):
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
</div>

<!-- ── Revenue + Processing Time + Top Students + Active Depts ── -->
<div class="dash-grid" style="margin-bottom:20px">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Revenue sparkline -->
    <div class="card">
      <div class="card-header"><span class="card-title">₱ Daily Revenue — Last 14 Days</span></div>
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

    <!-- Processing time -->
    <div class="card">
      <div class="card-header"><span class="card-title">⚡ Avg. Processing Time</span></div>
      <div class="card-body" style="text-align:center;padding:28px">
        <?php if ($avgProcessingHours > 0): ?>
          <div style="font-size:48px;font-weight:700;font-family:'Playfair Display',serif;color:var(--navy)">
            <?= $avgProcessingHours < 24
              ? number_format($avgProcessingHours, 1) . '<span style="font-size:20px;color:var(--gray)"> hrs</span>'
              : number_format($avgProcessingHours / 24, 1) . '<span style="font-size:20px;color:var(--gray)"> days</span>' ?>
          </div>
          <div style="font-size:12px;color:var(--gray);margin-top:8px">Average time from request to release</div>
        <?php else: ?>
          <div class="empty-desc">No released documents yet</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Login trend -->
    <div class="card">
      <div class="card-header"><span class="card-title">🔐 Daily Logins — Last 14 Days</span></div>
      <div class="card-body">
        <?php if (empty($dailyLogins)): ?>
          <div class="empty-state" style="padding:16px"><div class="empty-desc">No data yet</div></div>
        <?php else: ?>
          <div class="sparkline-wrap">
            <?php foreach ($dailyLogins as $d):
              $h = $maxLogin > 0 ? max(4, round(($d['cnt'] / $maxLogin) * 72)) : 4;
            ?>
              <div class="spark-bar" style="height:<?= $h ?>px;background:linear-gradient(180deg,#1565c0,#0d47a1)">
                <span class="spark-tip"><?= date('M d', strtotime($d['day'])) ?>: <?= $d['cnt'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:6px">
            <span style="font-size:10px;color:var(--gray)"><?= date('M d', strtotime($dailyLogins[0]['day'])) ?></span>
            <span style="font-size:10px;color:var(--gray)"><?= date('M d', strtotime(end($dailyLogins)['day'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Top students -->
    <div class="card">
      <div class="card-header"><span class="card-title">👥 Top Requesting Students</span></div>
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
                    <div style="width:<?= $pct ?>%;height:100%;background:var(--gold);border-radius:100px"></div>
                  </div>
                </div>
                <div style="font-size:12px;font-weight:700;color:var(--navy);width:24px;text-align:right"><?= $s['cnt'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Most active departments -->
    <div class="card">
      <div class="card-header"><span class="card-title">🏛 Most Active Departments</span></div>
      <div class="card-body">
        <?php if (empty($topDepts)): ?>
          <div class="empty-state" style="padding:20px"><div class="empty-desc">No reviews yet</div></div>
        <?php else: ?>
          <div class="user-rank-list">
            <?php foreach ($topDepts as $i => $d): ?>
              <div class="user-rank-item">
                <div class="user-rank-num <?= $i === 0 ? 'gold' : '' ?>"><?= $i+1 ?></div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= htmlspecialchars($d['department_name']) ?></div>
                  <div style="font-size:11px;color:var(--gray)">
                    <span style="color:var(--success)"><?= $d['cleared'] ?> cleared</span>
                    · <span style="color:var(--danger)"><?= $d['deficiency'] ?> deficiency</span>
                  </div>
                </div>
                <div style="font-size:12px;font-weight:700;color:var(--navy);width:32px;text-align:right"><?= $d['cnt'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent activity feed -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">🕑 Recent Activity</span>
        <a href="logs.php" style="font-size:12px;color:var(--gold);text-decoration:none;font-weight:600">View All →</a>
      </div>
      <div class="card-body panel-scroll">
        <div class="feed-list">
          <?php
          $roleDotMap = ['student'=>'#1565c0','department'=>'#7b1fa2','registrar'=>'#d4a017','admin'=>'var(--gold)'];
          foreach ($recentLogs as $l):
            $dc = $roleDotMap[$l['role'] ?? ''] ?? 'var(--gray)';
          ?>
            <div class="feed-item">
              <div class="feed-dot" style="background:<?= $dc ?>"></div>
              <div class="feed-body">
                <div class="feed-action"><?= htmlspecialchars($l['action_performed']) ?></div>
                <div class="feed-meta">
                  <span style="font-weight:600"><?= htmlspecialchars($l['username'] ?? 'System') ?></span>
                  <?php if ($l['role']): ?>
                    <span class="badge badge-<?= ['student'=>'info','department'=>'purple','registrar'=>'warning','admin'=>'gray'][$l['role']] ?? 'gray' ?>" style="font-size:9px;padding:2px 6px"><?= ucfirst($l['role']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="feed-time"><?= date('M d, h:i A', strtotime($l['date_time'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>