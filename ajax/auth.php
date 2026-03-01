<?php
// ajax/auth.php - Authentication AJAX Handler
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
initSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh.']);
        exit;
    }
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    $result = loginUser($email, $password);
    if ($result['success']) {
        $result['redirect'] = getRoleDashboard($result['role']);
    }
    echo json_encode($result);

} elseif ($action === 'register') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        exit;
    }
    $data = [
        'first_name'      => $_POST['first_name'] ?? '',
        'middle_name'     => $_POST['middle_name'] ?? '',
        'last_name'       => $_POST['last_name'] ?? '',
        'student_number'  => $_POST['student_number'] ?? '',
        'course'          => $_POST['course'] ?? '',
        'year_level'      => $_POST['year_level'] ?? '',
        'section'         => $_POST['section'] ?? '',
        'contact_number'  => $_POST['contact_number'] ?? '',
        'address'         => $_POST['address'] ?? '',
        'username'        => $_POST['username'] ?? '',
        'email'           => $_POST['email'] ?? '',
        'password'        => $_POST['password'] ?? '',
    ];
    echo json_encode(registerStudent($data));

} elseif ($action === 'logout') {
    logoutUser();
    echo json_encode(['success' => true, 'redirect' => '../index.php']);
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
