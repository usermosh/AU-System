<?php
require_once __DIR__ . '/config/db.php';

$db = getDB();

// Generate fresh hashes
$accounts = [
    'admin'     => 'admin123',
    'registrar' => 'registrar123',
    'lib'       => 'lib123',
    'fin'       => 'fin123',
    'reg'       => 'reg123',
];

echo "<h2>Password Reset</h2>";

foreach ($accounts as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    
    $rows = $stmt->rowCount();
    
    if ($rows > 0) {
        echo "✅ <strong>$username</strong> — password set to: <strong>$password</strong><br>";
    } else {
        echo "❌ <strong>$username</strong> — user NOT FOUND in database<br>";
    }
}

echo "<br><hr>";
echo "<h3>Verifying accounts exist in database:</h3>";

$check = $db->query("SELECT id, username, email, role, is_active FROM users");
$users = $check->fetchAll();

if (empty($users)) {
    echo "❌ NO USERS FOUND — database may be empty. Run setup.php first.";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th></tr>";
    foreach ($users as $u) {
        echo "<tr>
            <td>{$u['id']}</td>
            <td>{$u['username']}</td>
            <td>{$u['email']}</td>
            <td>{$u['role']}</td>
            <td>{$u['is_active']}</td>
        </tr>";
    }
    echo "</table>";
}

echo "<br><hr>";
echo "<h3>Test password verification:</h3>";

foreach ($accounts as $username => $password) {
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        $valid = password_verify($password, $user['password_hash']);
        $icon  = $valid ? '✅' : '❌';
        echo "$icon <strong>$username</strong> / <strong>$password</strong> — " . ($valid ? 'WORKS' : 'FAILED') . "<br>";
    } else {
        echo "❌ <strong>$username</strong> — not found<br>";
    }
}
?>