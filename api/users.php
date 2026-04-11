<?php
// ==============================================
// Hospital TMS - Users API (Bulletproof Fix)
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

$role = $_SESSION['user_role'] ?? '';

// Read JSON or POST
$input = [];
$rawBody = file_get_contents('php://input');
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) $input = $decoded;
}
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

$action = trim($input['action'] ?? '');

switch ($action) {

    case 'create':
        if ($role !== 'main_admin') {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $data = [
            'name'       => trim($input['name'] ?? ''),
            'email'      => trim($input['email'] ?? ''),
            'password'   => $input['password'] ?? '',
            'role'       => $input['role'] ?? '',
            'doctor_id'  => !empty($input['doctor_id']) ? (int)$input['doctor_id'] : null,
            'specialty'  => trim($input['specialty'] ?? ''),
            'department' => trim($input['department'] ?? ''),
        ];
        if (!in_array($data['role'], ['doctor_admin', 'nurse'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role.']);
            exit;
        }
        if (strlen($data['password']) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit;
        }
        echo json_encode(createUser($data));
        break;

    case 'update':
        if ($role !== 'main_admin') {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }

        $data = [];
        foreach (['name', 'email', 'specialty', 'department'] as $f) {
            if (!empty($input[$f])) $data[$f] = trim($input[$f]);
        }
        if (isset($input['doctor_id'])) $data['doctor_id'] = !empty($input['doctor_id']) ? (int)$input['doctor_id'] : null;
        if (isset($input['is_active'])) $data['is_active'] = (int)$input['is_active'];
        if (!empty($input['password']))  $data['password']  = $input['password'];

        echo json_encode(updateUser($id, $data));
        break;

    case 'delete':
        if ($role !== 'main_admin') {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }
        echo json_encode(deleteUser($id));
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: "' . htmlspecialchars($action) . '"']);
        break;
}