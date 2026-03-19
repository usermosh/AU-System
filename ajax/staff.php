<?php
// ajax/staff.php - Profile & Password AJAX Handler
// Handles: department, registrar, admin account updates
//          student profile + password updates
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
initSession();
requireLogin();

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
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

// ─── Update Account (username + email) — department, registrar, admin ───
if ($action === 'update_account') {
    if (!in_array($role, ['department', 'registrar', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    $username = sanitize($_POST['username'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$username || !$email) {
        echo json_encode(['success' => false, 'message' => 'Username and a valid email are required.']);
        exit;
    }

    // Check duplicate — exclude current user
    $chk = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
    $chk->execute([$username, $email, $userId]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or email already taken by another account.']);
        exit;
    }

    $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
    $stmt->execute([$username, $email, $userId]);

    // Keep session username in sync
    $_SESSION['username'] = $username;
    $_SESSION['email']    = $email;

    logActivity($userId, "Updated account info (username/email)", 'users', $userId);
    echo json_encode(['success' => true, 'message' => 'Account updated successfully.']);
    exit;
}

// ─── Update Profile — student only ───
if ($action === 'update_profile') {
    if ($role !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    $firstName     = sanitize($_POST['first_name']      ?? '');
    $lastName      = sanitize($_POST['last_name']       ?? '');
    $middleName    = sanitize($_POST['middle_name']     ?? '');
    $contactNumber = sanitize($_POST['contact_number']  ?? '');
    $course        = sanitize($_POST['course']          ?? '');
    $yearLevel     = (int)($_POST['year_level']         ?? 0);
    $section       = sanitize($_POST['section']         ?? '');
    $address       = sanitize($_POST['address']         ?? '');
    $email         = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$firstName || !$lastName || !$course || !$yearLevel || !$email) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Check email duplicate — exclude current user
    $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $chk->execute([$email, $userId]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Update users table email
        $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $userId]);

        // Update students table
        $db->prepare("
            UPDATE students
            SET first_name = ?, middle_name = ?, last_name = ?,
                course = ?, year_level = ?, section = ?,
                contact_number = ?, address = ?
            WHERE user_id = ?
        ")->execute([
            $firstName, $middleName, $lastName,
            $course, $yearLevel, $section,
            $contactNumber, $address,
            $userId
        ]);

        $db->commit();

        // Keep session in sync
        $_SESSION['full_name'] = $firstName . ' ' . $lastName;
        $_SESSION['email']     = $email;

        logActivity($userId, "Student updated profile", 'students', $_SESSION['student_id']);
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Profile update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update profile. Please try again.']);
    }
    exit;
}

// ─── Change Password — student ───
if ($action === 'change_password') {
    if ($role !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    $current    = $_POST['current_password'] ?? '';
    $newPass    = $_POST['new_password']     ?? '';
    $confirmPass= $_POST['confirm_password'] ?? '';

    if (!$current || !$newPass || !$confirmPass) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
        exit;
    }
    if ($newPass !== $confirmPass) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }
    if (strlen($newPass) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        exit;
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);

    logActivity($userId, "Student changed password", 'users', $userId);
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    exit;
}

// ─── Change Password — staff (department, registrar, admin) ───
if ($action === 'change_password_staff') {
    if (!in_array($role, ['department', 'registrar', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    $current     = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!$current || !$newPass || !$confirmPass) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
        exit;
    }
    if ($newPass !== $confirmPass) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }
    if (strlen($newPass) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        exit;
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);

    logActivity($userId, ucfirst($role) . " changed password", 'users', $userId);
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);