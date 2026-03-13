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

// ─── Verify Payment (existing) ───
if ($action === 'verify_payment') {
    $payId  = (int)($_POST['pay_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['verified','rejected']) ? $_POST['status'] : null;

    if (!$payId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    $stmt = $db->prepare("UPDATE payments SET status=?, verified_by=?, verified_at=NOW() WHERE id=?");
    $stmt->execute([$status, $_SESSION['user_id'], $payId]);

    logActivity($_SESSION['user_id'], "Payment #$payId marked as $status", 'payments', $payId);
    echo json_encode(['success' => true, 'message' => $status === 'verified' ? 'Payment verified successfully.' : 'Payment rejected.']);
    exit;
}

// ─── Update Document Request Status (existing) ───
if ($action === 'update_doc_status') {
    $reqId   = (int)($_POST['request_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $reason  = sanitize($_POST['rejection_reason'] ?? '');
    $allowed = ['approved','rejected','ready_for_pickup','released'];

    if (!$reqId || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    if ($status === 'released') {
        $stmt = $db->prepare("UPDATE document_requests SET status=?, rejection_reason=NULL, processed_by=?, processed_at=NOW(), released_at=NOW() WHERE id=?");
        $stmt->execute([$status, $_SESSION['user_id'], $reqId]);
    } elseif ($status === 'rejected') {
        $stmt = $db->prepare("UPDATE document_requests SET status=?, rejection_reason=?, processed_by=?, processed_at=NOW() WHERE id=?");
        $stmt->execute([$status, $reason, $_SESSION['user_id'], $reqId]);
    } else {
        $stmt = $db->prepare("UPDATE document_requests SET status=?, rejection_reason=NULL, processed_by=?, processed_at=NOW() WHERE id=?");
        $stmt->execute([$status, $_SESSION['user_id'], $reqId]);
    }

    logActivity($_SESSION['user_id'], "Document request #$reqId updated to: $status", 'document_requests', $reqId);
    $msgs = [
        'approved'         => 'Document request approved successfully.',
        'rejected'         => 'Document request rejected.',
        'ready_for_pickup' => 'Document marked as ready for pickup.',
        'released'         => 'Document marked as released.',
    ];
    echo json_encode(['success' => true, 'message' => $msgs[$status] ?? 'Status updated.']);
    exit;
}

// ─── Record Payment & Generate Receipt (NEW) ───
if ($action === 'record_payment') {
    $studentId     = (int)($_POST['student_id'] ?? 0);
    $requestId     = (int)($_POST['request_id'] ?? 0);
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cash','gcash','bank_transfer','online','check'])
                     ? $_POST['payment_method'] : null;
    $amount        = round((float)($_POST['amount'] ?? 0), 2);
    $referenceNo   = sanitize($_POST['reference_no'] ?? '');
    $notes         = sanitize($_POST['notes'] ?? '');

    if (!$studentId || !$paymentMethod || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment data.']);
        exit;
    }
    if ($paymentMethod !== 'cash' && !$referenceNo) {
        echo json_encode(['success' => false, 'message' => 'Reference number is required for digital payments.']);
        exit;
    }

    // Verify student exists
    $chk = $db->prepare("SELECT id FROM students WHERE id=?");
    $chk->execute([$studentId]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Insert transaction
        $txnStmt = $db->prepare("
            INSERT INTO transactions (student_id, request_id, payment_method, amount, reference_no, notes, status, processed_by)
            VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)
        ");
        $txnStmt->execute([$studentId, $requestId ?: null, $paymentMethod, $amount, $referenceNo ?: null, $notes ?: null, $_SESSION['user_id']]);
        $txnId = (int)$db->lastInsertId();

        // 2. Generate unique receipt number: AU-RCP-YYYY-NNNNN
        $year    = date('Y');
        $cntStmt = $db->query("SELECT COUNT(*) FROM receipts WHERE YEAR(generated_at) = $year");
        $seqNum  = (int)$cntStmt->fetchColumn() + 1;
        $receiptNumber = sprintf('AU-RCP-%s-%05d', $year, $seqNum);

        // 3. Insert receipt
        $rcptStmt = $db->prepare("INSERT INTO receipts (transaction_id, receipt_number, generated_by) VALUES (?,?,?)");
        $rcptStmt->execute([$txnId, $receiptNumber, $_SESSION['user_id']]);

        // 4. If linked to a document request, auto-verify the payment record
        if ($requestId) {
            $upPay = $db->prepare("UPDATE payments SET status='verified', verified_by=?, verified_at=NOW() WHERE document_request_id=? AND status='pending'");
            $upPay->execute([$_SESSION['user_id'], $requestId]);
        }

        $db->commit();

        logActivity($_SESSION['user_id'], "Payment recorded for student #$studentId — ₱$amount via $paymentMethod. Receipt: $receiptNumber", 'transactions', $txnId);

        echo json_encode([
            'success'        => true,
            'message'        => 'Payment recorded successfully.',
            'transaction_id' => $txnId,
            'receipt_number' => $receiptNumber,
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("record_payment error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to record payment. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);