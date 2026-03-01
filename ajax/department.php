<?php
// ajax/department.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
initSession();
requireRole('department');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
    exit;
}

$action = $_POST['action'] ?? '';
$db     = getDB();
$deptId = $_SESSION['dept_id'];

if ($action === 'update_clearance') {
    $statusId = (int)($_POST['status_id'] ?? 0);
    $status   = $_POST['status'] ?? '';
    $remarks  = sanitize($_POST['remarks'] ?? '');
    $allowed  = ['pending','cleared','deficiency'];

    if (!$statusId || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    // Verify this clearance_status belongs to this department
    $check = $db->prepare("SELECT id, clearance_id FROM clearance_status WHERE id=? AND department_id=?");
    $check->execute([$statusId, $deptId]);
    $row = $check->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Clearance record not found or access denied.']);
        exit;
    }

    $stmt = $db->prepare("UPDATE clearance_status SET status=?, remarks=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $stmt->execute([$status, $remarks, $_SESSION['user_id'], $statusId]);

    // Check if all departments cleared → auto-complete
    $clearanceId = $row['clearance_id'];
    $total = $db->prepare("SELECT COUNT(*) FROM clearance_status WHERE clearance_id=?");
    $total->execute([$clearanceId]);
    $totalCount = $total->fetchColumn();

    $cleared = $db->prepare("SELECT COUNT(*) FROM clearance_status WHERE clearance_id=? AND status='cleared'");
    $cleared->execute([$clearanceId]);
    $clearedCount = $cleared->fetchColumn();

    $hasDeficiency = $db->prepare("SELECT COUNT(*) FROM clearance_status WHERE clearance_id=? AND status='deficiency'");
    $hasDeficiency->execute([$clearanceId]);

    if ($clearedCount == $totalCount) {
        $upd = $db->prepare("UPDATE clearances SET overall_status='completed', completed_at=NOW() WHERE id=?");
        $upd->execute([$clearanceId]);
    } elseif ($hasDeficiency->fetchColumn() > 0) {
        // keep in_progress
    }

    logActivity($_SESSION['user_id'], "Updated clearance status #$statusId to $status", 'clearance_status', $statusId);
    echo json_encode(['success' => true, 'message' => "Clearance status updated to \"$status\" successfully."]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
