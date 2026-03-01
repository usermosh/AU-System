<?php
// ajax/admin.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
initSession();
requireRole('admin');

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

// ─── Create User ───
if ($action === 'create_user') {
    $username = sanitize($_POST['username'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['registrar','department','admin']) ? $_POST['role'] : null;
    $deptId   = (int)($_POST['department_id'] ?? 0);

    if (!$username || !$email || strlen($password) < 8 || !$role) {
        echo json_encode(['success' => false, 'message' => 'All fields required. Password minimum 8 chars.']);
        exit;
    }

    // Check duplicate
    $chk = $db->prepare("SELECT id FROM users WHERE email=? OR username=?");
    $chk->execute([$email, $username]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email or username already exists.']);
        exit;
    }

    try {
        $db->beginTransaction();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,?)");
        $stmt->execute([$username, $email, $hash, $role]);
        $userId = (int)$db->lastInsertId();

        if ($role === 'department' && $deptId) {
            $dstmt = $db->prepare("UPDATE departments SET user_id=? WHERE id=? AND user_id IS NULL");
            $dstmt->execute([$userId, $deptId]);
        }
        $db->commit();
        logActivity($_SESSION['user_id'], "Admin created user: $username ($role)", 'users', $userId);
        echo json_encode(['success' => true, 'message' => "User account \"$username\" created successfully."]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create user.']);
    }
    exit;
}

// ─── Update User ───
if ($action === 'update_user') {
    $userId   = (int)($_POST['user_id'] ?? 0);
    $username = sanitize($_POST['username'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$userId || !$username || !$email) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    if ($password) {
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $stmt = $db->prepare("UPDATE users SET username=?, email=?, password_hash=? WHERE id=?");
        $stmt->execute([$username, $email, $hash, $userId]);
    } else {
        $stmt = $db->prepare("UPDATE users SET username=?, email=? WHERE id=?");
        $stmt->execute([$username, $email, $userId]);
    }

    logActivity($_SESSION['user_id'], "Updated user #$userId", 'users', $userId);
    echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
    exit;
}

// ─── Toggle User Active Status ───
if ($action === 'toggle_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if (!$userId || $userId === (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify your own account.']);
        exit;
    }
    $stmt = $db->prepare("SELECT is_active FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    $newStatus = $user['is_active'] ? 0 : 1;
    $upd = $db->prepare("UPDATE users SET is_active=? WHERE id=?");
    $upd->execute([$newStatus, $userId]);
    logActivity($_SESSION['user_id'], ($newStatus ? 'Activated' : 'Deactivated') . " user #$userId", 'users', $userId);
    echo json_encode(['success' => true, 'message' => 'User status updated.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
