<?php
// ==============================================
// Hospital TMS - Doctor Admin Dashboard
// Doctor manages: nurses, tasks, sees progress
// ==============================================
require_once __DIR__ . '/../includes/functions.php';
requireRole('doctor_admin');

$user     = currentUser();
$tab      = $_GET['tab'] ?? 'overview';
$stats    = getDashboardStats('doctor_admin', $user['id']);
$myNurses = getNursesByDoctor($user['id']);

// Handle nurse create/edit/delete via POST
$formMsg = '';
$formErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'create_nurse') {
        $data = [
            'name'       => trim($_POST['name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'password'   => $_POST['password'] ?? '',
            'role'       => 'nurse',
            'doctor_id'  => $user['id'],
            'department' => trim($_POST['department'] ?? $user['department']),
        ];
        if (!$data['name'] || !$data['email'] || !$data['password']) {
            $formErr = 'Please fill in all required fields.';
        } elseif (strlen($data['password']) < 6) {
            $formErr = 'Password must be at least 6 characters.';
        } else {
            $res = createUser($data);
            if ($res['success']) {
                $formMsg = 'Nurse account created successfully!';
                $tab = 'nurses';
                $myNurses = getNursesByDoctor($user['id']);
            } else {
                $formErr = $res['message'];
            }
        }
    }

    if ($postAction === 'edit_nurse') {
        $nurseId = (int)($_POST['nurse_id'] ?? 0);
        // Verify nurse belongs to this doctor
        $db = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE id=? AND doctor_id=? AND role='nurse'");
        $chk->execute([$nurseId, $user['id']]);
        if ($chk->fetch()) {
            $data = [
                'name'       => trim($_POST['name'] ?? ''),
                'email'      => trim($_POST['email'] ?? ''),
                'department' => trim($_POST['department'] ?? ''),
            ];
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) {
                    $formErr = 'Password must be at least 6 characters.';
                } else {
                    $data['password'] = $_POST['password'];
                }
            }
            if (!$formErr) {
                $res = updateUser($nurseId, $data);
                $formMsg = $res['success'] ? 'Nurse account updated!' : $res['message'];
                $tab = 'nurses';
                $myNurses = getNursesByDoctor($user['id']);
            }
        } else {
            $formErr = 'Access denied.';
        }
    }

    if ($postAction === 'delete_nurse') {
        $nurseId = (int)($_POST['nurse_id'] ?? 0);
        $db = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE id=? AND doctor_id=? AND role='nurse'");
        $chk->execute([$nurseId, $user['id']]);
        if ($chk->fetch()) {
            deleteUser($nurseId);
            $formMsg = 'Nurse account deleted.';
            $tab = 'nurses';
            $myNurses = getNursesByDoctor($user['id']);
        }
    }

    if ($postAction === 'create_task') {
        $data = [
            'nurse_id'         => (int)($_POST['nurse_id'] ?? 0),
            'doctor_id'        => $user['id'],
            'task_title'       => trim($_POST['task_title'] ?? ''),
            'task_description' => trim($_POST['task_description'] ?? ''),
            'priority'         => $_POST['priority'] ?? 'medium',
            'task_date'        => $_POST['task_date'] ?? date('Y-m-d'),
            'due_time'         => !empty($_POST['due_time']) ? $_POST['due_time'] : null,
        ];
        // Verify nurse belongs to this doctor
        $nurseIds = array_column($myNurses, 'id');
        if (!$data['nurse_id'] || !$data['task_title'] || !$data['task_description']) {
            $formErr = 'Please fill in all required fields.';
        } elseif (!in_array($data['nurse_id'], array_map('intval', $nurseIds))) {
            $formErr = 'That nurse is not assigned to you.';
        } else {
            $res = createTask($data);
            $formMsg = $res['success'] ? 'Task created successfully!' : $res['message'];
            $tab = 'tasks';
        }
    }
}

// Nurse being viewed
$viewNurse = null;
$nurseTasksView = [];
if ($tab === 'nurse_view' && isset($_GET['nurse_id'])) {
    $nid = (int)$_GET['nurse_id'];
    $db = getDB();
    $ns = $db->prepare("SELECT * FROM users WHERE id=? AND doctor_id=? AND role='nurse'");
    $ns->execute([$nid, $user['id']]);
    $viewNurse = $ns->fetch();
    if ($viewNurse) {
        $nurseTasksView = getTasksByNurse($nid);
    }
}

$pageTitle = 'Doctor Dashboard';
$activeNav = match($tab) {
    'nurses', 'create_nurse', 'nurse_view' => 'nurses',
    'tasks', 'newtask' => 'tasks',
    'account' => 'account',
    default => 'overview'
};

include __DIR__ . '/../includes/header.php';
?>

<?php if ($formMsg): ?>
<div class="login-hint" style="margin-bottom:16px;border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.06)">
    <p style="color:var(--accent-green)">✓ <?= sanitize($formMsg) ?></p>
</div>
<?php endif; ?>
<?php if ($formErr): ?>
<div class="error-msg" style="margin-bottom:16px"><?= sanitize($formErr) ?></div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">
            <?= match($tab) {
                'nurses'      => 'My Nursing Team',
                'create_nurse'=> 'Add New Nurse',
                'nurse_view'  => 'Nurse Profile: ' . sanitize($viewNurse['name'] ?? ''),
                'tasks'       => 'Task Management',
                'newtask'     => 'Create New Task',
                'account'     => 'My Account',
                default       => 'My Dashboard'
            } ?>
        </h1>
        <p class="page-subtitle">
            <?= sanitize($user['specialty'] ?? 'Doctor Admin') ?> —
            <span class="highlight"><?= sanitize($user['department'] ?? '') ?></span>
        </p>
    </div>
    <div style="display:flex;gap:10px">
        <?php if ($tab === 'nurses'): ?>
        <a href="doctor.php?tab=create_nurse" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Nurse
        </a>
        <?php elseif ($tab !== 'newtask'): ?>
        <a href="doctor.php?tab=newtask" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Task
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ===== OVERVIEW ===== -->
<div class="stat-grid">
    <div class="stat-card teal">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(45,212,191,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <span class="stat-badge neut">Staff</span>
        </div>
        <div class="stat-label">My Nurses</div>
        <div class="stat-value"><?= $stats['my_nurses'] ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(61,111,255,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6389ff" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <span class="stat-badge neut">All</span>
        </div>
        <div class="stat-label">Total Tasks</div>
        <div class="stat-value"><?= $stats['total_tasks'] ?></div>
    </div>
    <div class="stat-card amber">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(245,158,11,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-badge down">Pending</span>
        </div>
        <div class="stat-label">Pending Tasks</div>
        <div class="stat-value"><?= $stats['pending_tasks'] ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(34,197,94,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="stat-badge up">Today</span>
        </div>
        <div class="stat-label">Completed Today</div>
        <div class="stat-value"><?= $stats['completed_today'] ?></div>
    </div>
</div>

<div class="content-grid">
    <!-- Today Tasks -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Today's Tasks</span>
            <a href="doctor.php?tab=tasks" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body p0">
            <?php $tasks = getTasksByDoctor($user['id'], date('Y-m-d')); ?>
            <?php if ($tasks): ?>
            <table class="data-table">
                <thead><tr><th>Task</th><th>Nurse</th><th>Priority</th><th>Progress</th></tr></thead>
                <tbody>
                    <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td>
                            <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                            <?php if ($t['due_time']): ?><div class="table-sub"><?= date('g:i A', strtotime($t['due_time'])) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <?= userAvatar($t['nurse_image'] ?? null, 'sm') ?>
                                <?= sanitize($t['nurse_name']) ?>
                            </div>
                        </td>
                        <td><span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                        <td><span class="badge-pill <?= statusClass($t['status']) ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/></svg>
                <p>No tasks for today. <a href="doctor.php?tab=newtask" style="color:var(--accent-blue-g)">Create one?</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Nurse Overview -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">My Nurses</span>
            <a href="doctor.php?tab=nurses" class="btn btn-outline btn-sm">Manage</a>
        </div>
        <div class="activity-feed">
            <?php foreach ($myNurses as $n): ?>
            <?php
                $db2 = getDB();
                $s2 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='pending' AND task_date=CURDATE()");
                $s2->execute([$n['id']]); $pendCount = $s2->fetchColumn();
            ?>
            <div class="activity-item">
                <?= userAvatar($n['profile_image'] ?? null, 'sm') ?>
                <div class="activity-body">
                    <div class="activity-title"><?= sanitize($n['name']) ?></div>
                    <div class="activity-detail"><?= sanitize($n['department'] ?? '—') ?></div>
                </div>
                <button class="btn btn-outline btn-sm"
                    onclick="openNursePasswordModal(<?= $n['id'] ?>, '<?= addslashes(sanitize($n['name'])) ?>')">
                    View
                </button>
            </div>
            <?php endforeach; ?>
            <?php if (!$myNurses): ?>
            <div class="empty-state"><p>No nurses assigned yet. <a href="doctor.php?tab=create_nurse" style="color:var(--accent-blue-g)">Add one?</a></p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'nurses'): ?>
<!-- ===== NURSES LIST ===== -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    <?php foreach ($myNurses as $n): ?>
    <?php
        $db2 = getDB();
        $s2 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=?"); $s2->execute([$n['id']]); $tc = $s2->fetchColumn();
        $s3 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='completed'"); $s3->execute([$n['id']]); $cc = $s3->fetchColumn();
        $s4 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='pending'"); $s4->execute([$n['id']]); $pc = $s4->fetchColumn();
    ?>
    <div class="card" style="padding:0">
        <div style="padding:20px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:center">
            <?= userAvatar($n['profile_image'] ?? null, 'lg') ?>
            <div style="flex:1">
                <div style="font-weight:600;color:var(--text-primary)"><?= sanitize($n['name']) ?></div>
                <div style="font-size:.78rem;color:var(--text-muted)"><?= sanitize($n['email']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px"><?= sanitize($n['department'] ?? '—') ?></div>
            </div>
            <span class="badge-pill <?= $n['is_active'] ? 'status-completed' : 'status-cancelled' ?>"><?= $n['is_active'] ? 'Active' : 'Inactive' ?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid var(--border)">
            <div style="padding:12px;text-align:center;border-right:1px solid var(--border)">
                <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);font-family:var(--font-mono)"><?= $tc ?></div>
                <div class="text-sm text-muted">Total</div>
            </div>
            <div style="padding:12px;text-align:center;border-right:1px solid var(--border)">
                <div style="font-size:1.1rem;font-weight:700;color:var(--accent-green);font-family:var(--font-mono)"><?= $cc ?></div>
                <div class="text-sm text-muted">Done</div>
            </div>
            <div style="padding:12px;text-align:center">
                <div style="font-size:1.1rem;font-weight:700;color:var(--accent-amber);font-family:var(--font-mono)"><?= $pc ?></div>
                <div class="text-sm text-muted">Pending</div>
            </div>
        </div>
        <div style="padding:14px;display:flex;gap:8px">
            <!-- CHANGED: View Tasks now opens password modal instead of navigating directly -->
            <button class="btn btn-outline btn-sm" style="flex:1;justify-content:center"
                onclick="openNursePasswordModal(<?= $n['id'] ?>, '<?= addslashes(sanitize($n['name'])) ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Tasks
            </button>
            <a href="doctor.php?tab=edit_nurse&nurse_id=<?= $n['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </a>
            <form method="POST" style="margin:0" onsubmit="return confirm('Delete this nurse account?')">
                <input type="hidden" name="post_action" value="delete_nurse">
                <input type="hidden" name="nurse_id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$myNurses): ?>
    <div class="empty-state" style="grid-column:1/-1">
        <p>No nurses yet. <a href="doctor.php?tab=create_nurse" style="color:var(--accent-blue-g)">Add your first nurse →</a></p>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'create_nurse'): ?>
<!-- ===== CREATE NURSE ===== -->
<div class="card" style="max-width:600px">
    <div class="card-header"><span class="card-title">Create Nurse Account</span></div>
    <div class="card-body">
        <form method="POST" action="doctor.php?tab=create_nurse">
            <input type="hidden" name="post_action" value="create_nurse">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input name="name" class="form-control" placeholder="Nurse Full Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input name="email" class="form-control" type="email" placeholder="nurse@hospital.com" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input name="password" class="form-control" type="password" placeholder="Min 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input name="department" class="form-control" value="<?= sanitize($user['department'] ?? '') ?>" placeholder="Department">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <a href="doctor.php?tab=nurses" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Create Nurse Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($tab === 'edit_nurse' && isset($_GET['nurse_id'])): ?>
<!-- ===== EDIT NURSE ===== -->
<?php
    $eid = (int)$_GET['nurse_id'];
    $db = getDB();
    $es = $db->prepare("SELECT * FROM users WHERE id=? AND doctor_id=? AND role='nurse'");
    $es->execute([$eid, $user['id']]);
    $editNurse = $es->fetch();
?>
<?php if ($editNurse): ?>
<div class="card" style="max-width:600px">
    <div class="card-header"><span class="card-title">Edit Nurse: <?= sanitize($editNurse['name']) ?></span></div>
    <div class="card-body">
        <form method="POST" action="doctor.php?tab=nurses">
            <input type="hidden" name="post_action" value="edit_nurse">
            <input type="hidden" name="nurse_id" value="<?= $editNurse['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input name="name" class="form-control" value="<?= sanitize($editNurse['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input name="email" class="form-control" type="email" value="<?= sanitize($editNurse['email']) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">New Password <span class="text-muted">(leave blank to keep)</span></label>
                    <input name="password" class="form-control" type="password" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input name="department" class="form-control" value="<?= sanitize($editNurse['department'] ?? '') ?>">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <a href="doctor.php?tab=nurses" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'nurse_view' && $viewNurse): ?>
<!-- ===== VIEW NURSE TASKS & PROGRESS ===== -->
<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start">

    <!-- Nurse Info Card -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-body" style="text-align:center;padding:28px 20px">
                <?= userAvatar($viewNurse['profile_image'] ?? null, 'lg', 'margin:0 auto 14px') ?>
                <div style="font-weight:700;color:var(--text-primary);font-size:1rem"><?= sanitize($viewNurse['name']) ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px"><?= sanitize($viewNurse['email']) ?></div>
                <div style="font-size:.75rem;color:var(--accent-teal);margin-top:4px"><?= sanitize($viewNurse['department'] ?? '') ?></div>
            </div>
        </div>
        <?php
            $db2 = getDB();
            $s2 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=?"); $s2->execute([$viewNurse['id']]); $tc = $s2->fetchColumn();
            $s3 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='completed'"); $s3->execute([$viewNurse['id']]); $cc = $s3->fetchColumn();
            $s4 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='pending'"); $s4->execute([$viewNurse['id']]); $pc = $s4->fetchColumn();
            $s5 = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=? AND status='in_progress'"); $s5->execute([$viewNurse['id']]); $ic = $s5->fetchColumn();
        ?>
        <div class="card">
            <div class="card-header"><span class="card-title">Progress</span></div>
            <div class="card-body" style="padding:0">
                <div style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
                    <span class="text-sm text-muted">Total</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--text-primary)"><?= $tc ?></span>
                </div>
                <div style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
                    <span class="text-sm text-muted">In Progress</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--accent-blue-g)"><?= $ic ?></span>
                </div>
                <div style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between">
                    <span class="text-sm text-muted">Completed</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--accent-green)"><?= $cc ?></span>
                </div>
                <div style="padding:12px 18px;display:flex;justify-content:space-between">
                    <span class="text-sm text-muted">Pending</span>
                    <span style="font-family:var(--font-mono);font-weight:700;color:var(--accent-amber)"><?= $pc ?></span>
                </div>
            </div>
        </div>
        <a href="doctor.php?tab=newtask&nurse_id=<?= $viewNurse['id'] ?>" class="btn btn-primary" style="justify-content:center">
            + Assign New Task
        </a>
        <a href="doctor.php?tab=nurses" class="btn btn-outline" style="justify-content:center">← Back to Nurses</a>
    </div>

    <!-- Nurse Tasks with Progress Update -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Tasks for <?= sanitize($viewNurse['name']) ?></span>
        </div>
        <div class="card-body p0">
            <?php if ($nurseTasksView): ?>
            <table class="data-table">
                <thead>
                    <tr><th>Task</th><th>Date</th><th>Priority</th><th>Status / Progress</th><th>Notes</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($nurseTasksView as $t): ?>
                    <tr>
                        <td>
                            <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                            <div class="table-sub" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($t['task_description']) ?></div>
                        </td>
                        <td class="font-mono text-sm"><?= date('M j, Y', strtotime($t['task_date'])) ?></td>
                        <td><span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                        <td>
                            <span class="badge-pill <?= statusClass($t['status']) ?>" id="status-badge-<?= $t['id'] ?>">
                                <?= str_replace('_',' ',$t['status']) ?>
                            </span>
                            <?php if ($t['completed_at']): ?>
                            <div class="table-sub">Done: <?= date('M j g:i A', strtotime($t['completed_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:160px;font-size:.78rem;color:var(--text-muted)" id="notes-cell-<?= $t['id'] ?>">
                            <?= $t['notes'] ? sanitize($t['notes']) : '<span style="color:var(--text-muted)">—</span>' ?>
                        </td>
                        <td>
                            <?php if ($t['status'] !== 'completed' && $t['status'] !== 'cancelled'): ?>
                            <button class="btn btn-outline btn-sm" onclick="openProgressModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                Update Progress
                            </button>
                            <?php else: ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteTaskConfirm(<?= $t['id'] ?>)">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/></svg>
                <p>No tasks assigned yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'tasks'): ?>
<!-- ===== ALL TASKS ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Tasks</span>
    </div>
    <div class="card-body p0">
        <?php $tasks = getTasksByDoctor($user['id']); ?>
        <table class="data-table">
            <thead><tr><th>Task</th><th>Nurse</th><th>Date</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td>
                        <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                        <div class="table-sub" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($t['task_description']) ?></div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <?= userAvatar($t['nurse_image'] ?? null, 'sm') ?>
                            <?= sanitize($t['nurse_name']) ?>
                        </div>
                    </td>
                    <td class="font-mono text-sm"><?= date('M j, Y', strtotime($t['task_date'])) ?></td>
                    <td><span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                    <td><span class="badge-pill <?= statusClass($t['status']) ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-outline btn-sm" onclick="openProgressModal(<?= htmlspecialchars(json_encode($t)) ?>)">Update</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteTaskConfirm(<?= $t['id'] ?>)">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tasks): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No tasks yet</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'newtask'): ?>
<!-- ===== CREATE TASK ===== -->
<div class="card" style="max-width:680px">
    <div class="card-header"><span class="card-title">Assign New Task</span></div>
    <div class="card-body">
        <form method="POST" action="doctor.php?tab=tasks">
            <input type="hidden" name="post_action" value="create_task">
            <div class="form-group">
                <label class="form-label">Assign to Nurse *</label>
                <select name="nurse_id" class="form-control" required>
                    <option value="">Select nurse...</option>
                    <?php foreach ($myNurses as $n): ?>
                    <option value="<?= $n['id'] ?>" <?= (isset($_GET['nurse_id']) && $_GET['nurse_id'] == $n['id']) ? 'selected' : '' ?>><?= sanitize($n['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Task Title *</label>
                <input name="task_title" class="form-control" placeholder="e.g. Morning Vital Signs" required>
            </div>
            <div class="form-group">
                <label class="form-label">Task Description *</label>
                <textarea name="task_description" class="form-control" rows="4" placeholder="Detailed instructions..." required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Task Date *</label>
                    <input name="task_date" class="form-control" type="date" value="<?= date('Y-m-d') ?>" required>
                </div>
              <div class="form-group">
    <label class="form-label">Due Time <span style="color:red">*</span></label>
    <input name="due_time" class="form-control" type="time" required>
</div>
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <a href="doctor.php?tab=tasks" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Create Task
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($tab === 'account'): ?>
<!-- ===== MY ACCOUNT ===== -->
<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
    <div class="card">
        <div class="card-body" style="text-align:center;padding:32px 20px">
            <img src="<?= avatarSrc($user['profile_image'] ?? null) ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--accent-a50);margin-bottom:16px">
            <div style="font-weight:700;color:var(--text-primary);font-size:1rem"><?= sanitize($user['name']) ?></div>
            <div style="font-size:.8rem;color:var(--accent-blue-g);margin-top:4px"><?= sanitize($user['specialty'] ?? 'Doctor Admin') ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($user['department'] ?? '') ?></div>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-header"><span class="card-title">Personal Information</span></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input id="acc_name" class="form-control" value="<?= sanitize($user['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input id="acc_email" class="form-control" type="email" value="<?= sanitize($user['email']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Specialty</label>
                        <input id="acc_spec" class="form-control" value="<?= sanitize($user['specialty'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input id="acc_dept" class="form-control" value="<?= sanitize($user['department'] ?? '') ?>">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button class="btn btn-primary" onclick="saveAccountInfo()">Save Changes</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">Change Password</span></div>
            <div class="card-body">
                <div id="pwErr" class="error-msg" style="display:none"></div>
                <div id="pwOk" class="login-hint" style="display:none;border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.06)"><p style="color:var(--accent-green)">✓ Password updated!</p></div>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input id="pw_current" class="form-control" type="password" placeholder="Current password">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input id="pw_new" class="form-control" type="password" placeholder="Min 6 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input id="pw_confirm" class="form-control" type="password" placeholder="Repeat new password">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button class="btn btn-primary" onclick="changePassword()">Update Password</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const TASKS_API   = '/hospital_management/api/tasks.php';
const ACCOUNT_API = '/hospital_management/api/account.php';
const VERIFY_API  = '/hospital_management/api/verify_nurse.php';

// ========================================
// NURSE PASSWORD VERIFICATION MODAL
// ========================================
function openNursePasswordModal(nurseId, nurseName) {
    openModal(`
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:6px;color:var(--accent-amber)">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Identity Verification
        </span>
        <button class="modal-close" onclick="closeModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div style="
          background:rgba(245,158,11,0.07);
          border:1px solid rgba(245,158,11,0.25);
          border-radius:8px;
          padding:14px 16px;
          margin-bottom:20px;
          display:flex;
          gap:12px;
          align-items:flex-start
        ">
          <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"
               style="width:18px;height:18px;flex-shrink:0;margin-top:1px">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <div style="font-size:.83rem;color:var(--text-secondary);line-height:1.5">
            To view tasks for <strong style="color:var(--text-primary)">${nurseName}</strong>,
            please enter their account password to confirm access.
          </div>
        </div>
        <div class="form-group" style="margin-bottom:6px">
          <label class="form-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="width:13px;height:13px;display:inline-block;vertical-align:middle;margin-right:4px">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            Nurse Password
          </label>
          <div style="position:relative">
            <input
              id="nurse_pw_input"
              class="form-control"
              type="password"
              placeholder="Enter nurse's password..."
              autocomplete="off"
              onkeydown="if(event.key==='Enter') verifyNursePassword(${nurseId})"
              style="padding-right:44px"
            >
            <button
              type="button"
              onclick="toggleNursePwVisibility()"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;display:flex;align-items:center"
              title="Show/hide password"
            >
              <svg id="eye_icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>
        <div id="nurse_pw_error" style="
          display:none;
          color:var(--accent-red, #f87171);
          font-size:.8rem;
          margin-top:8px;
          padding:8px 12px;
          background:rgba(248,113,113,0.08);
          border:1px solid rgba(248,113,113,0.2);
          border-radius:6px;
          display:none;
          align-items:center;
          gap:8px
        ">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0">
            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
          </svg>
          <span id="nurse_pw_error_text">Incorrect password. Please try again.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" id="verify_btn" onclick="verifyNursePassword(${nurseId})">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Verify & View Tasks
        </button>
      </div>
    </div>`);

    // Auto-focus the password field after modal renders
    setTimeout(() => {
        const input = document.getElementById('nurse_pw_input');
        if (input) input.focus();
    }, 100);
}

function toggleNursePwVisibility() {
    const input = document.getElementById('nurse_pw_input');
    const icon  = document.getElementById('eye_icon');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `
          <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
          <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
          <line x1="1" y1="1" x2="23" y2="23"/>`;
    } else {
        input.type = 'password';
        icon.innerHTML = `
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>`;
    }
}

async function verifyNursePassword(nurseId) {
    const pwInput  = document.getElementById('nurse_pw_input');
    const errEl    = document.getElementById('nurse_pw_error');
    const errText  = document.getElementById('nurse_pw_error_text');
    const verifyBtn = document.getElementById('verify_btn');

    if (!pwInput || !pwInput.value.trim()) {
        errEl.style.display = 'flex';
        errText.textContent = 'Please enter the nurse\'s password.';
        pwInput && pwInput.focus();
        return;
    }

    // Loading state
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           style="width:15px;height:15px;animation:spin 1s linear infinite">
        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
      </svg>
      Verifying...`;

    try {
        const res = await apiCall(VERIFY_API, {
            action:   'verify_nurse_password',
            nurse_id: nurseId,
            password: pwInput.value
        });

        if (res.success) {
            // Show brief success state then redirect
            verifyBtn.innerHTML = `
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              Access Granted!`;
            verifyBtn.style.background = 'var(--accent-green, #22c55e)';
            setTimeout(() => {
                window.location.href = `doctor.php?tab=nurse_view&nurse_id=${nurseId}`;
            }, 500);
        } else {
            errEl.style.display = 'flex';
            errText.textContent = res.message || 'Incorrect password. Please try again.';
            pwInput.value = '';
            pwInput.focus();
            // Reset button
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = `
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              Verify & View Tasks`;
            // Shake animation on error
            pwInput.style.animation = 'shake 0.4s ease';
            setTimeout(() => pwInput.style.animation = '', 400);
        }
    } catch (e) {
        errEl.style.display = 'flex';
        errText.textContent = 'Connection error. Please try again.';
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Verify & View Tasks`;
    }
}

// Add shake keyframe if not already present
if (!document.getElementById('shake-style')) {
    const s = document.createElement('style');
    s.id = 'shake-style';
    s.textContent = `
      @keyframes shake {
        0%,100%{transform:translateX(0)}
        20%{transform:translateX(-6px)}
        40%{transform:translateX(6px)}
        60%{transform:translateX(-4px)}
        80%{transform:translateX(4px)}
      }
      @keyframes spin {
        from{transform:rotate(0deg)}
        to{transform:rotate(360deg)}
      }`;
    document.head.appendChild(s);
}

// ---- Progress Update Modal (Doctor inputs nurse task progress) ----
function openProgressModal(task) {
    openModal(`
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">Update Task Progress</span>
        <button class="modal-close" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div style="background:var(--bg-elevated);border-radius:8px;padding:14px;margin-bottom:18px">
          <div style="font-weight:600;color:var(--text-primary);margin-bottom:4px">${task.task_title}</div>
          <div style="font-size:.82rem;color:var(--text-secondary)">${task.task_description}</div>
        </div>
        <div class="form-group">
          <label class="form-label">Task Status / Progress</label>
          <select id="prog_status" class="form-control">
            <option value="pending"     ${task.status==='pending'     ?'selected':''}>⏳ Pending</option>
            <option value="in_progress" ${task.status==='in_progress' ?'selected':''}>🔄 In Progress</option>
            <option value="completed"   ${task.status==='completed'   ?'selected':''}>✅ Completed</option>
            <option value="cancelled"   ${task.status==='cancelled'   ?'selected':''}>❌ Cancelled</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nurse Progress Notes</label>
          <textarea id="prog_notes" class="form-control" rows="3" placeholder="Enter nurse progress notes here...">${task.notes || ''}</textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" onclick="submitProgress(${task.id})">
          Save Progress
        </button>
      </div>
    </div>`);
}

async function submitProgress(taskId) {
    const status = document.getElementById('prog_status').value;
    const notes  = document.getElementById('prog_notes').value;
    const res = await apiCall(TASKS_API, {
        action: 'doctor_update_progress',
        id:     taskId,
        status: status,
        notes:  notes
    });
    if (res.success) {
        showToast('Task progress updated!', 'success');
        closeModal();
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(res.message || 'Failed to update.', 'error');
    }
}

async function deleteTaskConfirm(id) {
    confirmAction('Delete this task permanently?', async () => {
        const res = await apiCall(TASKS_API, { action: 'delete', id });
        if (res.success) { showToast('Task deleted.', 'success'); setTimeout(() => location.reload(), 800); }
        else showToast('Failed to delete.', 'error');
    });
}

// ---- My Account ----
async function saveAccountInfo() {
    const res = await apiCall(ACCOUNT_API, {
        action:     'update_info',
        name:       document.getElementById('acc_name')?.value,
        email:      document.getElementById('acc_email')?.value,
        department: document.getElementById('acc_dept')?.value,
        specialty:  document.getElementById('acc_spec')?.value,
    });
    if (res.success) { showToast('Profile updated!', 'success'); setTimeout(() => location.reload(), 800); }
    else showToast(res.message || 'Failed.', 'error');
}

async function changePassword() {
    const current = document.getElementById('pw_current').value;
    const newPw   = document.getElementById('pw_new').value;
    const confirm = document.getElementById('pw_confirm').value;
    const errEl   = document.getElementById('pwErr');
    const okEl    = document.getElementById('pwOk');
    errEl.style.display = 'none'; okEl.style.display = 'none';
    if (!current)          { errEl.textContent = 'Enter current password.'; errEl.style.display=''; return; }
    if (newPw.length < 6)  { errEl.textContent = 'Min 6 characters.';       errEl.style.display=''; return; }
    if (newPw !== confirm)  { errEl.textContent = 'Passwords do not match.'; errEl.style.display=''; return; }
    const res = await apiCall(ACCOUNT_API, { action:'change_password', current_password:current, new_password:newPw });
    if (res.success) { okEl.style.display=''; document.getElementById('pw_current').value=''; document.getElementById('pw_new').value=''; document.getElementById('pw_confirm').value=''; }
    else { errEl.textContent = res.message; errEl.style.display=''; }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>