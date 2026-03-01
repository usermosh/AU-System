<?php
// ============================================================
// includes/auth.php - Authentication & Session Management
// ============================================================
require_once __DIR__ . '/../config/db.php';

// Initialize secure session
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in; redirect if not
 */
function requireLogin(string $redirect = '/index.php'): void {
    initSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . $redirect);
        exit;
    }
    // Session timeout
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . $redirect . '?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Require a specific role; redirect if wrong
 */
function requireRole(string|array $roles): void {
    requireLogin();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role'] ?? '', $allowed)) {
        header('Location: ' . APP_URL . '/index.php?unauthorized=1');
        exit;
    }
}

/**
 * Authenticate user login
 */
function loginUser(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        logActivity(null, 'Failed login attempt for email: ' . $email, 'users', null);
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account is deactivated. Contact administrator.'];
    }

    initSession();
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['last_activity'] = time();

    // Load role-specific data
    if ($user['role'] === 'student') {
        $stmt2 = $db->prepare("SELECT id, student_number, first_name, last_name, course FROM students WHERE user_id = ?");
        $stmt2->execute([$user['id']]);
        $student = $stmt2->fetch();
        if ($student) {
            $_SESSION['student_id']     = $student['id'];
            $_SESSION['student_number'] = $student['student_number'];
            $_SESSION['full_name']      = $student['first_name'] . ' ' . $student['last_name'];
        }
    } elseif ($user['role'] === 'department') {
        $stmt3 = $db->prepare("SELECT id, department_name, department_code FROM departments WHERE user_id = ?");
        $stmt3->execute([$user['id']]);
        $dept = $stmt3->fetch();
        if ($dept) {
            $_SESSION['dept_id']   = $dept['id'];
            $_SESSION['dept_name'] = $dept['department_name'];
            $_SESSION['dept_code'] = $dept['department_code'];
        }
    }

    logActivity($user['id'], 'User logged in', 'users', $user['id']);
    return ['success' => true, 'role' => $user['role']];
}

/**
 * Logout user
 */
function logoutUser(): void {
    initSession();
    if (!empty($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'User logged out', 'users', $_SESSION['user_id']);
    }
    session_unset();
    session_destroy();
}

/**
 * Register new student
 */
function registerStudent(array $data): array {
    $db = getDB();

    // Check duplicate email/username/student_number
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$data['email'], $data['username']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email or username already exists.'];
    }
    $stmt2 = $db->prepare("SELECT id FROM students WHERE student_number = ? LIMIT 1");
    $stmt2->execute([$data['student_number']]);
    if ($stmt2->fetch()) {
        return ['success' => false, 'message' => 'Student number already registered.'];
    }

    try {
        $db->beginTransaction();
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $stmt3 = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'student')");
        $stmt3->execute([$data['username'], $data['email'], $hash]);
        $userId = $db->lastInsertId();

        $stmt4 = $db->prepare("INSERT INTO students (user_id, student_number, first_name, middle_name, last_name, course, year_level, section, contact_number, address) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt4->execute([
            $userId,
            sanitize($data['student_number']),
            sanitize($data['first_name']),
            sanitize($data['middle_name'] ?? ''),
            sanitize($data['last_name']),
            sanitize($data['course']),
            (int)$data['year_level'],
            sanitize($data['section'] ?? ''),
            sanitize($data['contact_number'] ?? ''),
            sanitize($data['address'] ?? '')
        ]);
        $db->commit();

        logActivity($userId, 'New student registered: ' . $data['student_number'], 'students', (int)$db->lastInsertId());
        return ['success' => true, 'message' => 'Account registered successfully. You may now log in.'];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/**
 * Log system activity
 */
function logActivity(?int $userId, string $action, ?string $table = null, ?int $recordId = null): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $db->prepare("INSERT INTO logs (user_id, action_performed, affected_table, affected_record_id, ip_address, user_agent) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip, $ua]);
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get role dashboard URL
 */
function getRoleDashboard(string $role): string {
    return match($role) {
        'student'    => APP_URL . '/student/dashboard.php',
        'department' => APP_URL . '/department/dashboard.php',
        'registrar'  => APP_URL . '/registrar/dashboard.php',
        'admin'      => APP_URL . '/admin/dashboard.php',
        default      => APP_URL . '/index.php'
    };
}

/**
 * Generate CSRF token
 */
function csrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
