<?php
// ajax/student.php - Student AJAX Handler
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
initSession();
requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
    exit;
}

$action    = $_POST['action'] ?? '';
$db        = getDB();
$studentId = $_SESSION['student_id'];

// ─── Apply for Clearance ───
if ($action === 'apply_clearance') {
    $type       = in_array($_POST['clearance_type'] ?? '', ['regular','graduation']) ? $_POST['clearance_type'] : 'regular';
    $schoolYear = sanitize($_POST['school_year'] ?? '');
    $semester   = in_array($_POST['semester'] ?? '', ['1st','2nd','Summer']) ? $_POST['semester'] : '1st';

    if (!$schoolYear) {
        echo json_encode(['success' => false, 'message' => 'School year is required.']);
        exit;
    }

    // Check for duplicate
    $stmt = $db->prepare("SELECT id FROM clearances WHERE student_id=? AND school_year=? AND semester=? AND clearance_type=? AND overall_status IN ('pending','in_progress')");
    $stmt->execute([$studentId, $schoolYear, $semester, $type]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have an active clearance application for this period.']);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt2 = $db->prepare("INSERT INTO clearances (student_id, school_year, semester, clearance_type, overall_status) VALUES (?,?,?,?,'in_progress')");
        $stmt2->execute([$studentId, $schoolYear, $semester, $type]);
        $clearanceId = (int)$db->lastInsertId();

        // Create clearance_status row per active department
        $depts = $db->query("SELECT id FROM departments WHERE is_active = 1")->fetchAll();
        $stmt3 = $db->prepare("INSERT INTO clearance_status (clearance_id, department_id, status) VALUES (?,?,'pending')");
        foreach ($depts as $dept) {
            $stmt3->execute([$clearanceId, $dept['id']]);
        }

        $db->commit();
        logActivity($_SESSION['user_id'], "Applied for clearance: $type $schoolYear $semester", 'clearances', $clearanceId);
        echo json_encode(['success' => true, 'message' => 'Clearance application submitted. Departments will review your request.']);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Clearance apply error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to submit application.']);
    }
    exit;
}

// ─── Submit Document Request ───
if ($action === 'request_document') {
    $allowed = ['TOR','Diploma','Certificate of Enrollment','Good Moral','Honorable Dismissal','Transfer Credentials','Authentication'];
    $docType = $_POST['document_type'] ?? '';
    $copies  = max(1, (int)($_POST['copies'] ?? 1));
    $purpose = sanitize($_POST['purpose'] ?? '');

    if (!in_array($docType, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO document_requests (student_id, document_type, copies, purpose, status) VALUES (?,?,?,?,'pending')");
    $stmt->execute([$studentId, $docType, $copies, $purpose]);
    $reqId = (int)$db->lastInsertId();

    logActivity($_SESSION['user_id'], "Requested document: $docType x$copies", 'document_requests', $reqId);
    echo json_encode(['success' => true, 'message' => "Document request for \"$docType\" submitted successfully."]);
    exit;
}

// ─── Submit Payment Record ───
if ($action === 'submit_payment') {
    $requestId  = (int)($_POST['request_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $method     = $_POST['payment_method'] ?? 'cash';
    $refNum     = sanitize($_POST['reference_number'] ?? '');
    $notes      = sanitize($_POST['proof_notes'] ?? '');

    $allowed_methods = ['cash','bank_transfer','gcash','maya'];
    if (!in_array($method, $allowed_methods)) $method = 'cash';

    if (!$requestId || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment data.']);
        exit;
    }

    // Verify request belongs to student
    $check = $db->prepare("SELECT id FROM document_requests WHERE id=? AND student_id=?");
    $check->execute([$requestId, $studentId]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO payments (document_request_id, student_id, amount, payment_method, reference_number, proof_notes, status) VALUES (?,?,?,?,?,?,'pending')");
    $stmt->execute([$requestId, $studentId, $amount, $method, $refNum, $notes]);

    // Update request status to payment_verification
    $upd = $db->prepare("UPDATE document_requests SET status='payment_verification' WHERE id=?");
    $upd->execute([$requestId]);

    logActivity($_SESSION['user_id'], "Submitted payment for request #$requestId", 'payments', (int)$db->lastInsertId());
    echo json_encode(['success' => true, 'message' => 'Payment record submitted. Registrar will verify.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
