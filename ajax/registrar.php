<?php
// ajax/registrar.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
initSession();
requireRole('registrar');

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

// ─── Verify Payment ───
if ($action === 'verify_payment') {
    $payId  = (int)($_POST['pay_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['verified','rejected']) 
              ? $_POST['status'] : null;

    if (!$payId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE payments 
        SET status = ?, 
            verified_by = ?, 
            verified_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$status, $_SESSION['user_id'], $payId]);

    logActivity(
        $_SESSION['user_id'], 
        "Payment #$payId marked as $status", 
        'payments', 
        $payId
    );

    $msg = $status === 'verified' ? 'Payment verified successfully.' : 'Payment rejected.';
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

// ─── Update Document Request Status ───
if ($action === 'update_doc_status') {
    $reqId  = (int)($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $reason = sanitize($_POST['rejection_reason'] ?? '');
    $allowed = ['approved', 'rejected', 'ready_for_pickup', 'released'];

    if (!$reqId || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    // Build query based on status
    if ($status === 'released') {
        $stmt = $db->prepare("
            UPDATE document_requests 
            SET status        = ?,
                rejection_reason = NULL,
                processed_by  = ?,
                processed_at  = NOW(),
                released_at   = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $_SESSION['user_id'], $reqId]);

    } elseif ($status === 'rejected') {
        $stmt = $db->prepare("
            UPDATE document_requests 
            SET status           = ?,
                rejection_reason = ?,
                processed_by     = ?,
                processed_at     = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $reason, $_SESSION['user_id'], $reqId]);

    } else {
        // approved or ready_for_pickup
        $stmt = $db->prepare("
            UPDATE document_requests 
            SET status           = ?,
                rejection_reason = NULL,
                processed_by     = ?,
                processed_at     = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $_SESSION['user_id'], $reqId]);
    }

    logActivity(
        $_SESSION['user_id'], 
        "Document request #$reqId updated to: $status", 
        'document_requests', 
        $reqId
    );

    $msgs = [
        'approved'         => 'Document request approved successfully.',
        'rejected'         => 'Document request rejected.',
        'ready_for_pickup' => 'Document marked as ready for pickup.',
        'released'         => 'Document marked as released.',
    ];

    echo json_encode([
        'success' => true, 
        'message' => $msgs[$status] ?? 'Status updated.'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);