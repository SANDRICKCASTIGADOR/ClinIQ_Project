<?php
// ==============================================
// Hospital TMS - Account API
// Users can ONLY edit their own account
// ==============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

// Only doctors and nurses can use this API
$role   = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($role, ['doctor_admin', 'nurse'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Read input
$input = [];
$raw   = file_get_contents('php://input');
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $input = $decoded;
}
if (empty($input) && !empty($_POST)) $input = $_POST;

$action = trim($input['action'] ?? '');

switch ($action) {

    // ---- Update personal info ----
    case 'update_info':
        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $dept  = trim($input['department'] ?? '');
        $spec  = trim($input['specialty'] ?? '');

        if (!$name)  { echo json_encode(['success' => false, 'message' => 'Name is required.']);  exit; }
        if (!$email) { echo json_encode(['success' => false, 'message' => 'Email is required.']); exit; }

        // Check email not taken by someone else
        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
            exit;
        }

        $data = [
            'name'       => $name,
            'email'      => $email,
            'department' => $dept,
        ];
        if ($role === 'doctor_admin' && $spec) {
            $data['specialty'] = $spec;
        }

        $result = updateUser($userId, $data);
        if ($result['success']) {
            logActivity($userId, 'Profile Updated', 'User updated their own profile info');
        }
        echo json_encode($result);
        break;

    // ---- Change password ----
    case 'change_password':
        $currentPw = $input['current_password'] ?? '';
        $newPw     = $input['new_password'] ?? '';

        if (!$currentPw) { echo json_encode(['success' => false, 'message' => 'Current password is required.']); exit; }
        if (!$newPw)     { echo json_encode(['success' => false, 'message' => 'New password is required.']);     exit; }
        if (strlen($newPw) < 6) { echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']); exit; }

        // Verify current password
        $db   = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row  = $stmt->fetch();

        if (!$row || !password_verify($currentPw, $row['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        // Update to new password
        $newHash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 10]);
        $upd     = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$newHash, $userId]);

        logActivity($userId, 'Password Changed', 'User changed their own password');
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}