<?php
// ==============================================
// API: Verify Nurse Password
// Used by doctor dashboard before viewing nurse tasks
// ==============================================
require_once __DIR__ . '/../includes/functions.php';
requireRole('doctor_admin');

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';

if ($action !== 'verify_nurse_password') {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$nurseId  = (int)($data['nurse_id'] ?? 0);
$password = $data['password'] ?? '';
$doctor   = currentUser();

if (!$nurseId || !$password) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Security: confirm the nurse belongs to this doctor
$db  = getDB();
$stmt = $db->prepare(
    "SELECT id, password FROM users
     WHERE id = ? AND doctor_id = ? AND role = 'nurse' AND is_active = 1"
);
$stmt->execute([$nurseId, $doctor['id']]);
$nurse = $stmt->fetch();

if (!$nurse) {
    echo json_encode(['success' => false, 'message' => 'Nurse not found or access denied.']);
    exit;
}

// Verify password against the stored hash
if (password_verify($password, $nurse['password'])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect password. Please try again.']);
}
