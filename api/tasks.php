<?php
// ==============================================
// Hospital TMS - Tasks API (Bulletproof Fix)
// ==============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// --- Must be logged in ---
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$role   = $_SESSION['user_role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// --- Read input: support both JSON body AND POST form ---
$input = [];

$rawBody = file_get_contents('php://input');
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

// Fallback to $_POST if JSON body was empty
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

$action = trim($input['action'] ?? '');

// Debug helper - uncomment if still having issues:
// error_log("TMS Tasks API: action=$action, role=$role, userId=$userId, raw=" . substr($rawBody,0,200));

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'No action received. Raw: ' . substr($rawBody, 0, 100)
    ]);
    exit;
}

switch ($action) {

    case 'create':
        if ($role !== 'doctor_admin') {
            echo json_encode(['success' => false, 'message' => 'Only doctor admins can create tasks.']);
            exit;
        }

        $nurseId   = (int)($input['nurse_id'] ?? 0);
        $taskTitle = trim($input['task_title'] ?? '');
        $taskDesc  = trim($input['task_description'] ?? '');
        $taskDate  = trim($input['task_date'] ?? '');
        $dueTime   = (!empty($input['due_time']) && $input['due_time'] !== '') ? trim($input['due_time']) : null;
        $priority  = $input['priority'] ?? 'medium';
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) $priority = 'medium';

        if (!$nurseId)   { echo json_encode(['success' => false, 'message' => 'Please select a nurse.']); exit; }
        if (!$taskTitle) { echo json_encode(['success' => false, 'message' => 'Task title is required.']); exit; }
        if (!$taskDesc)  { echo json_encode(['success' => false, 'message' => 'Task description is required.']); exit; }
        if (!$taskDate)  { echo json_encode(['success' => false, 'message' => 'Task date is required.']); exit; }

        // Verify nurse belongs to this doctor
        $myNurses = getNursesByDoctor($userId);
        $nurseIds = array_map('intval', array_column($myNurses, 'id'));
        if (!in_array($nurseId, $nurseIds)) {
            echo json_encode(['success' => false, 'message' => 'That nurse is not assigned to you.']);
            exit;
        }

        $result = createTask([
            'nurse_id'         => $nurseId,
            'doctor_id'        => $userId,
            'task_title'       => $taskTitle,
            'task_description' => $taskDesc,
            'priority'         => $priority,
            'task_date'        => $taskDate,
            'due_time'         => $dueTime,
        ]);
        echo json_encode($result);
        break;

    case 'update':
        if ($role !== 'doctor_admin') {
            echo json_encode(['success' => false, 'message' => 'Only doctor admins can update tasks.']);
            exit;
        }
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid task ID.']); exit; }

        $data = [];
        foreach (['task_title', 'task_description', 'priority', 'task_date', 'due_time', 'status'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') $data[$f] = $input[$f];
        }
        if (isset($input['nurse_id'])) {
            $myNurses = getNursesByDoctor($userId);
            $nurseIds = array_map('intval', array_column($myNurses, 'id'));
            if (in_array((int)$input['nurse_id'], $nurseIds)) $data['nurse_id'] = (int)$input['nurse_id'];
        }
        echo json_encode(updateTask($id, $data, $userId));
        break;

    case 'delete':
        if ($role !== 'doctor_admin') {
            echo json_encode(['success' => false, 'message' => 'Only doctor admins can delete tasks.']);
            exit;
        }
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid task ID.']); exit; }
        echo json_encode(deleteTask($id, $userId));
        break;

    case 'nurse_update':
        if ($role !== 'nurse') {
            echo json_encode(['success' => false, 'message' => 'Only nurses can use this action.']);
            exit;
        }
        $id     = (int)($input['id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notes  = trim($input['notes'] ?? '');
        if (!$id || !in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID or status.']);
            exit;
        }
        echo json_encode(nurseUpdateTask($id, $status, $notes, $userId));
        break;

    // Doctor updates nurse task progress (status + notes)
    case 'doctor_update_progress':
        if ($role !== 'doctor_admin') {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $id     = (int)($input['id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notes  = trim($input['notes'] ?? '');
        if (!$id || !in_array($status, ['pending','in_progress','completed','cancelled'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID or status.']);
            exit;
        }
        // Verify task belongs to this doctor
        $db   = getDB();
        $chk  = $db->prepare("SELECT id FROM tasks WHERE id = ? AND doctor_id = ?");
        $chk->execute([$id, $userId]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Task not found.']);
            exit;
        }
        $completedAt = ($status === 'completed') ? ', completed_at = NOW()' : ($status !== 'completed' ? ', completed_at = NULL' : '');
        $stmt = $db->prepare("UPDATE tasks SET status = ?, notes = ? $completedAt WHERE id = ?");
        $stmt->execute([$status, $notes, $id]);
        logActivity($userId, 'Task Progress Updated', "Task ID $id set to $status by doctor");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action: "' . htmlspecialchars($action) . '"'
        ]);
        break;
}