<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/integrations.php';
requireRole(['doctor_admin', 'main_admin']);
header('Content-Type: application/json');

$user = currentUser();
$in   = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$db   = getDB();

switch ($in['action'] ?? '') {
    case 'drug_lookup':
        $name  = trim($in['name'] ?? '');
        $label = OpenFDA::lookup($name);
        $label['interaction_flags'] = !empty($label['found'])
            ? OpenFDA::interactions($name, $in['patient_ref'] ?? '', $in['date'] ?? date('Y-m-d'))
            : [];
        unset($label['interactions']);
        echo json_encode(['success' => true, 'drug' => $label]);
        break;

    case 'check_conflict':
        $nurseId = (int) ($in['nurse_id'] ?? 0);
        $chk = $db->prepare("SELECT id FROM users WHERE id=? AND doctor_id=? AND role='nurse'");
        $chk->execute([$nurseId, $user['id']]);
        if (!$chk->fetch()) { echo json_encode(['success' => false, 'message' => 'Access denied.']); break; }
        echo json_encode(['success' => true, 'conflicts' => GCal::conflicts($nurseId, $in['date'] ?? '', $in['time'] ?? null)]);
        break;

    case 'notification_log':
        if ($user['role'] !== 'main_admin') { echo json_encode(['success' => false, 'message' => 'Access denied.']); break; }
        Brevo::refreshStatuses();
        $rows = $db->query("SELECT id, task_id, type, recipient, subject, status, last_event, error, created_at
                            FROM notification_log ORDER BY id DESC LIMIT 50")->fetchAll();
        echo json_encode(['success' => true, 'log' => $rows]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
