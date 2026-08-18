<?php
// ==============================================
// Hospital TMS - Authentication & Helper Functions
// ==============================================

require_once __DIR__ . '/config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------------------------
// Authentication Functions
// --------------------------------------------------

function login(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['doctor_id'] = $user['doctor_id'];
        $_SESSION['login_time'] = time();

        // Log activity
        logActivity($user['id'], 'Login', 'User logged in successfully');

        return ['success' => true, 'role' => $user['role']];
    }

    return ['success' => false, 'message' => 'Invalid email or password.'];
}

function logout(): void {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
    }
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id'], $_SESSION['login_time'])) return false;
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        logout();
        return false;
    }
    $_SESSION['login_time'] = time(); // refresh
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'], $roles)) {
        header('Location: ../index.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    if (!isLoggedIn()) return [];
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: [];
}

// --------------------------------------------------
// User Management Functions
// --------------------------------------------------

function getAllUsers(): array {
    $db = getDB();
    return $db->query("SELECT id, name, email, role, doctor_id, specialty, department, avatar_initials, profile_image, is_active, created_at FROM users ORDER BY role, name")->fetchAll();
}

function getUsersByRole(string $role): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role, doctor_id, specialty, department, avatar_initials, profile_image, is_active, created_at FROM users WHERE role = ? ORDER BY name");
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

function getNursesByDoctor(int $doctorId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, department, avatar_initials, profile_image, is_active FROM users WHERE role = 'nurse' AND doctor_id = ? ORDER BY name");
    $stmt->execute([$doctorId]);
    return $stmt->fetchAll();
}

function createUser(array $data): array {
    $db = getDB();
    // Check email uniqueness
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$data['email']]);
    if ($check->fetch()) return ['success' => false, 'message' => 'Email already exists.'];

    $hashed = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $initials = getInitials($data['name']);

    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, doctor_id, specialty, department, avatar_initials) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $data['name'], $data['email'], $hashed, $data['role'],
        $data['doctor_id'] ?? null, $data['specialty'] ?? null,
        $data['department'] ?? null, $initials
    ]);
    $newId = $db->lastInsertId();
    logActivity($_SESSION['user_id'], 'User Created', "Created user: {$data['name']} ({$data['role']})");
    return ['success' => true, 'id' => $newId];
}

function updateUser(int $id, array $data): array {
    $db = getDB();
    $fields = [];
    $params = [];

    foreach (['name', 'email', 'specialty', 'department', 'doctor_id', 'is_active'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (!empty($data['password'])) {
        $fields[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    if (isset($data['name'])) {
        $fields[] = "avatar_initials = ?";
        $params[] = getInitials($data['name']);
    }

    if (empty($fields)) return ['success' => false, 'message' => 'Nothing to update.'];
    $params[] = $id;
    $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    logActivity($_SESSION['user_id'], 'User Updated', "Updated user ID: $id");
    return ['success' => true];
}

function deleteUser(int $id): array {
    $db = getDB();
    $db->prepare("DELETE FROM users WHERE id = ? AND role != 'main_admin'")->execute([$id]);
    logActivity($_SESSION['user_id'], 'User Deleted', "Deleted user ID: $id");
    return ['success' => true];
}

// --------------------------------------------------
// Task Management Functions
// --------------------------------------------------

function getTasksByNurse(int $nurseId, ?string $date = null): array {
    $db = getDB();
    $sql = "SELECT t.*, u.name as doctor_name, u.profile_image as doctor_image FROM tasks t 
            JOIN users u ON t.doctor_id = u.id 
            WHERE t.nurse_id = ?";
    $params = [$nurseId];
    if ($date) { $sql .= " AND t.task_date = ?"; $params[] = $date; }
    $sql .= " ORDER BY t.task_date DESC, FIELD(t.priority,'urgent','high','medium','low'), t.due_time";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTasksByDoctor(int $doctorId, ?string $date = null): array {
    $db = getDB();
    $sql = "SELECT t.*, u.name as nurse_name, u.avatar_initials as nurse_initials, u.profile_image as nurse_image 
            FROM tasks t JOIN users u ON t.nurse_id = u.id 
            WHERE t.doctor_id = ?";
    $params = [$doctorId];
    if ($date) { $sql .= " AND t.task_date = ?"; $params[] = $date; }
    $sql .= " ORDER BY t.task_date DESC, FIELD(t.priority,'urgent','high','medium','low')";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAllTasks(?string $date = null): array {
    $db = getDB();
    $sql = "SELECT t.*, n.name as nurse_name, d.name as doctor_name, n.avatar_initials as nurse_initials, n.profile_image as nurse_image
            FROM tasks t 
            JOIN users n ON t.nurse_id = n.id 
            JOIN users d ON t.doctor_id = d.id";
    $params = [];
    if ($date) { $sql .= " WHERE t.task_date = ?"; $params[] = $date; }
    $sql .= " ORDER BY t.task_date DESC, FIELD(t.priority,'urgent','high','medium','low')";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createTask(array $data): array {
    try {
        $db = getDB();
        $dueTime = (!empty($data['due_time'])) ? $data['due_time'] : null;
        $stmt = $db->prepare("INSERT INTO tasks (nurse_id, doctor_id, task_title, task_description, priority, task_date, due_time) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            (int)$data['nurse_id'],
            (int)$data['doctor_id'],
            trim($data['task_title']),
            trim($data['task_description']),
            $data['priority'] ?? 'medium',
            $data['task_date'],
            $dueTime
        ]);
        $taskId = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'Task Created', "Created task: " . $data['task_title'] . " for nurse ID " . $data['nurse_id']);
        return ['success' => true, 'id' => $taskId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateTask(int $id, array $data, int $doctorId): array {
    $db = getDB();
    // Verify ownership
    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND doctor_id = ?");
    $check->execute([$id, $doctorId]);
    if (!$check->fetch()) return ['success' => false, 'message' => 'Task not found or access denied.'];

    $fields = []; $params = [];
    foreach (['task_title','task_description','priority','task_date','due_time','status'] as $f) {
        if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
    }
    if (isset($data['status']) && $data['status'] === 'completed') {
        $fields[] = "completed_at = NOW()";
    }
    if (empty($fields)) return ['success' => false, 'message' => 'Nothing to update.'];
    $params[] = $id;
    $db->prepare("UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    logActivity($_SESSION['user_id'], 'Task Updated', "Updated task ID: $id");
    return ['success' => true];
}

function nurseUpdateTask(int $id, string $status, string $notes, int $nurseId): array {
    $db = getDB();
    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND nurse_id = ?");
    $check->execute([$id, $nurseId]);
    if (!$check->fetch()) return ['success' => false, 'message' => 'Task not found.'];

    $completedAt = ($status === 'completed') ? 'NOW()' : 'NULL';
    $stmt = $db->prepare("UPDATE tasks SET status = ?, notes = ?, completed_at = $completedAt WHERE id = ?");
    $stmt->execute([$status, $notes, $id]);
    logActivity($nurseId, 'Task Status Updated', "Task ID $id marked as $status");
    return ['success' => true];
}

function deleteTask(int $id, int $doctorId): array {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$id, $doctorId]);
    logActivity($_SESSION['user_id'], 'Task Deleted', "Deleted task ID: $id");
    return ['success' => true];
}

// --------------------------------------------------
// Statistics & Activity
// --------------------------------------------------

function getDashboardStats(string $role, int $userId): array {
    $db = getDB();
    $stats = [];

    if ($role === 'main_admin') {
        $stats['total_doctors'] = $db->query("SELECT COUNT(*) FROM users WHERE role='doctor_admin'")->fetchColumn();
        $stats['total_nurses']  = $db->query("SELECT COUNT(*) FROM users WHERE role='nurse'")->fetchColumn();
        $stats['total_tasks']   = $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
        $stats['pending_tasks'] = $db->query("SELECT COUNT(*) FROM tasks WHERE status='pending'")->fetchColumn();
        $stats['completed_today'] = $db->query("SELECT COUNT(*) FROM tasks WHERE status='completed' AND DATE(completed_at)=CURDATE()")->fetchColumn();
    } elseif ($role === 'doctor_admin') {
        $s = $db->prepare("SELECT COUNT(*) FROM users WHERE role='nurse' AND doctor_id=?");
        $s->execute([$userId]); $stats['my_nurses'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE doctor_id=?");
        $s->execute([$userId]); $stats['total_tasks'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE doctor_id=? AND status='pending'");
        $s->execute([$userId]); $stats['pending_tasks'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE doctor_id=? AND status='completed' AND DATE(completed_at)=CURDATE()");
        $s->execute([$userId]); $stats['completed_today'] = $s->fetchColumn();
    } elseif ($role === 'nurse') {
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=?");
        $s->execute([$userId]); $stats['total_tasks'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='pending'");
        $s->execute([$userId]); $stats['pending_tasks'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='completed'");
        $s->execute([$userId]); $stats['completed_tasks'] = $s->fetchColumn();
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND task_date=CURDATE()");
        $s->execute([$userId]); $stats['today_tasks'] = $s->fetchColumn();
    }
    return $stats;
}

function getRecentActivity(int $limit = 10): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT al.*, u.name, u.role, u.avatar_initials, u.profile_image FROM activity_log al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function logActivity(int $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?,?,?,?)")
           ->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) { /* silent fail */ }
}

// --------------------------------------------------
// Avatar / Profile Picture
// --------------------------------------------------
// Every user shows a picture. If the account has no uploaded photo,
// it falls back to the same default image the admin uses
// (assets/profile.png), so the interface never shows a blank circle.

define('DEFAULT_AVATAR', 'profile.png');

function avatarSrc(?string $image = null, string $base = '../'): string {
    $image = trim((string) $image);
    if ($image !== '' && file_exists(__DIR__ . '/../assets/uploads/' . $image)) {
        return $base . 'assets/uploads/' . rawurlencode($image);
    }
    return $base . 'assets/' . DEFAULT_AVATAR;
}

function userAvatar(?string $image = null, string $size = '', string $style = '', string $base = '../'): string {
    $cls = trim('avatar ' . $size);
    $st  = $style ? ' style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : '';
    return '<div class="' . $cls . '"' . $st . '>'
         . '<img src="' . htmlspecialchars(avatarSrc($image, $base), ENT_QUOTES) . '" alt="Profile">'
         . '</div>';
}

// --------------------------------------------------
// Utility Functions
// --------------------------------------------------

function getInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= strtoupper($w[0] ?? '');
    }
    return $initials ?: 'U';
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirectByRole(): void {
    $role = $_SESSION['user_role'] ?? '';
    match($role) {
        'main_admin'   => header('Location: pages/admin.php'),
        'doctor_admin' => header('Location: pages/doctor.php'),
        'nurse'        => header('Location: pages/nurse.php'),
        default        => header('Location: index.php')
    };
    exit;
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

function priorityClass(string $priority): string {
    return match($priority) {
        'urgent' => 'priority-urgent',
        'high'   => 'priority-high',
        'medium' => 'priority-medium',
        'low'    => 'priority-low',
        default  => 'priority-medium'
    };
}

function statusClass(string $status): string {
    return match($status) {
        'completed'   => 'status-completed',
        'in_progress' => 'status-progress',
        'cancelled'   => 'status-cancelled',
        default       => 'status-pending'
    };
}