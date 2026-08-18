<?php
// ==============================================
// Hospital TMS - Nurse Dashboard
// ==============================================
require_once __DIR__ . '/../includes/functions.php';
requireRole('nurse');

$user      = currentUser();
$tab       = $_GET['tab'] ?? 'overview';
$stats     = getDashboardStats('nurse', $user['id']);
$pageTitle = 'Nurse Dashboard';
$activeNav = $tab === 'overview' ? 'overview' : $tab;

// Get doctor info
$db = getDB();
$doctorStmt = $db->prepare("SELECT name, specialty, department FROM users WHERE id = ?");
$doctorStmt->execute([$user['doctor_id']]);
$myDoctor = $doctorStmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">
            <?= match($tab) {
                'tasks'   => 'My Daily Tasks',
                'history' => 'Task History',
                default   => 'My Dashboard'
            } ?>
        </h1>
        <p class="page-subtitle">
            <?= sanitize($user['department'] ?? 'Nurse') ?> —
            Supervised by <span class="highlight"><?= $myDoctor ? sanitize($myDoctor['name']) : 'Unassigned' ?></span>
        </p>
    </div>
    <div class="system-status" style="background:rgba(45,212,191,0.08);border-color:rgba(45,212,191,0.2);color:#2dd4bf">
        <?= date('l, F j, Y') ?>
    </div>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ===== OVERVIEW ===== -->
<div class="stat-grid">
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
            <span class="stat-badge down">Action</span>
        </div>
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $stats['pending_tasks'] ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(34,197,94,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="stat-badge up">Done</span>
        </div>
        <div class="stat-label">Completed</div>
        <div class="stat-value"><?= $stats['completed_tasks'] ?></div>
    </div>
    <div class="stat-card teal">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(45,212,191,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <span class="stat-badge neut">Today</span>
        </div>
        <div class="stat-label">Today's Tasks</div>
        <div class="stat-value"><?= $stats['today_tasks'] ?></div>
    </div>
</div>

<div class="content-grid">
    <!-- Today's Tasks -->
    <div>
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:16px;color:var(--text-secondary)">Today's Assignments</h2>
        <?php $todayTasks = getTasksByNurse($user['id'], date('Y-m-d')); ?>
        <?php if ($todayTasks): ?>
        <div class="task-list">
            <?php foreach ($todayTasks as $t): ?>
            <div class="task-card" id="task-<?= $t['id'] ?>">
                <div class="task-card-top">
                    <div style="flex:1">
                        <div class="task-card-title"><?= sanitize($t['task_title']) ?></div>
                        <div class="task-card-desc"><?= sanitize($t['task_description']) ?></div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;flex-shrink:0">
                        <span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span>
                        <span class="badge-pill <?= statusClass($t['status']) ?>" id="status-<?= $t['id'] ?>"><?= str_replace('_',' ',$t['status']) ?></span>
                    </div>
                </div>
                <div class="task-card-meta">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= $t['due_time'] ? date('g:i A', strtotime($t['due_time'])) : 'No due time' ?>
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?= sanitize($t['doctor_name']) ?>
                    </span>
                </div>
                <?php if ($t['status'] !== 'completed' && $t['status'] !== 'cancelled'): ?>
                <div class="completion-form" id="form-<?= $t['id'] ?>">
                    <div class="form-group" style="margin-bottom:10px">
                        <textarea class="form-control" id="notes-<?= $t['id'] ?>" placeholder="Add completion notes (optional)..." rows="2"></textarea>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn-success btn-sm" onclick="markTask(<?= $t['id'] ?>, 'completed')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Mark Complete
                        </button>
                        <?php if ($t['status'] === 'pending'): ?>
                        <button class="btn btn-outline btn-sm" onclick="markTask(<?= $t['id'] ?>, 'in_progress')">
                            Start Task
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif ($t['status'] === 'completed'): ?>
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
                    <p style="font-size:.78rem;color:var(--accent-green)">✓ Completed<?= $t['completed_at'] ? ' at ' . date('g:i A', strtotime($t['completed_at'])) : '' ?></p>
                    <?php if ($t['notes']): ?><p style="font-size:.78rem;color:var(--text-muted);margin-top:4px"><?= sanitize($t['notes']) ?></p><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card"><div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <p>No tasks assigned for today.</p>
        </div></div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Doctor Info + Quick Stats -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <?php if ($myDoctor): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">My Supervising Doctor</span></div>
            <div class="card-body">
                <div class="flex align-center gap-12" style="margin-bottom:14px">
                    <?= userAvatar($myDoctor['profile_image'] ?? null, 'lg') ?>
                    <div>
                        <div style="font-weight:600;color:var(--text-primary)"><?= sanitize($myDoctor['name']) ?></div>
                        <div style="font-size:.78rem;color:var(--accent-blue-g)"><?= sanitize($myDoctor['specialty'] ?? '') ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($myDoctor['department'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><span class="card-title">Task Summary</span></div>
            <div class="card-body">
                <?php
                $total     = $stats['total_tasks'] ?: 1;
                $compPct   = round(($stats['completed_tasks'] / $total) * 100);
                $pendPct   = round(($stats['pending_tasks'] / $total) * 100);
                ?>
                <div style="margin-bottom:14px">
                    <div class="flex justify-between" style="margin-bottom:6px">
                        <span class="text-sm text-muted">Completion Rate</span>
                        <span class="text-sm font-mono" style="color:var(--accent-green)"><?= $compPct ?>%</span>
                    </div>
                    <div style="height:6px;background:var(--bg-elevated);border-radius:3px;overflow:hidden">
                        <div style="height:100%;width:<?= $compPct ?>%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:3px;transition:width 1s ease"></div>
                    </div>
                </div>
                <div class="flex justify-between align-center" style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="text-sm text-muted">Pending</span>
                    <span class="badge-pill status-pending"><?= $stats['pending_tasks'] ?></span>
                </div>
                <div class="flex justify-between align-center" style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="text-sm text-muted">Completed</span>
                    <span class="badge-pill status-completed"><?= $stats['completed_tasks'] ?></span>
                </div>
                <div class="flex justify-between align-center" style="padding:8px 0">
                    <span class="text-sm text-muted">Today</span>
                    <span class="badge-pill status-progress"><?= $stats['today_tasks'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'tasks'): ?>
<!-- ===== ALL TASKS TAB ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">My Tasks</span>
        <input type="date" class="form-control" id="taskDate" value="<?= date('Y-m-d') ?>" style="width:auto;padding:6px 12px;font-size:.8rem" onchange="loadTasks(this.value)">
    </div>
    <div class="card-body p0" id="tasksContainer">
        <?php $tasks = getTasksByNurse($user['id']); ?>
        <table class="data-table">
            <thead><tr><th>Task</th><th>Date</th><th>Due Time</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr data-searchable>
                    <td>
                        <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                        <div class="table-sub" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($t['task_description']) ?></div>
                    </td>
                    <td class="font-mono text-sm"><?= date('M j, Y', strtotime($t['task_date'])) ?></td>
                    <td class="font-mono text-sm"><?= $t['due_time'] ? date('g:i A', strtotime($t['due_time'])) : '—' ?></td>
                    <td><span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                    <td><span class="badge-pill <?= statusClass($t['status']) ?>" id="status-<?= $t['id'] ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
                    <td>
                        <?php if ($t['status'] !== 'completed' && $t['status'] !== 'cancelled'): ?>
                        <button class="btn btn-success btn-sm" onclick="quickComplete(<?= $t['id'] ?>)">Complete</button>
                        <?php else: ?>
                        <span class="text-muted text-sm">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tasks): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No tasks found</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'history'): ?>
<!-- ===== HISTORY TAB ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Task History</span>
        <span class="text-muted text-sm">All completed tasks</span>
    </div>
    <div class="card-body p0">
        <?php $tasks = getTasksByNurse($user['id']); ?>
        <table class="data-table">
            <thead><tr><th>Task</th><th>Assigned Date</th><th>Completed At</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr data-searchable>
                    <td>
                        <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                    </td>
                    <td class="font-mono text-sm"><?= date('M j, Y', strtotime($t['task_date'])) ?></td>
                    <td class="font-mono text-sm"><?= $t['completed_at'] ? date('M j, g:i A', strtotime($t['completed_at'])) : '—' ?></td>
                    <td><span class="badge-pill <?= statusClass($t['status']) ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
                    <td style="max-width:200px;font-size:.8rem;color:var(--text-muted)"><?= $t['notes'] ? sanitize($t['notes']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tasks): ?>
                <tr><td colspan="5"><div class="empty-state"><p>No task history</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const TASKS_API = '/hospital_management/api/tasks.php';
const USERS_API = '/hospital_management/api/users.php';
const doctors   = <?= json_encode(getUsersByRole('doctor_admin')) ?>;

// ---- Show/Hide Password Toggle ----
function togglePass(inputId, eyeOnId, eyeOffId) {
    const input  = document.getElementById(inputId);
    const eyeOn  = document.getElementById(eyeOnId);
    const eyeOff = document.getElementById(eyeOffId);
    if (!input) return;
    if (input.type === 'password') {
        input.type           = 'text';
        eyeOn.style.display  = 'none';
        eyeOff.style.display = 'block';
    } else {
        input.type           = 'password';
        eyeOn.style.display  = 'block';
        eyeOff.style.display = 'none';
    }
}

// ---- Create User Modal (Add Doctor) ----
function openCreateUserModal(forceRole) {
    forceRole = forceRole || 'doctor_admin';
    openModal(`
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">Add Doctor</span>
        <button class="modal-close" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div id="createFormErr" class="error-msg" style="display:none"></div>

        <!-- Split name fields for doctor -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">First Name</label>
            <input id="cu_firstname" class="form-control" placeholder="First Name">
          </div>
          <div class="form-group">
            <label class="form-label">Middle Name <span class="text-muted">(optional)</span></label>
            <input id="cu_middlename" class="form-control" placeholder="Middle Name">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <input id="cu_lastname" class="form-control" placeholder="Last Name">
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input id="cu_email" class="form-control" type="email" placeholder="user@hospital.com">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <div style="position:relative;">
              <input id="cu_pass" class="form-control" type="password" placeholder="Min 6 characters" style="padding-right:42px;">
              <button type="button" onclick="togglePass('cu_pass','eyePass1','eyePassOff1')"
                style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:0;color:#6b7280;display:flex;align-items:center;" aria-label="Show password">
                <svg id="eyePass1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="eyePassOff1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div style="position:relative;">
              <input id="cu_pass_confirm" class="form-control" type="password" placeholder="Re-enter password" style="padding-right:42px;">
              <button type="button" onclick="togglePass('cu_pass_confirm','eyePass2','eyePassOff2')"
                style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:0;color:#6b7280;display:flex;align-items:center;" aria-label="Show confirm password">
                <svg id="eyePass2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="eyePassOff2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Department</label>
            <input id="cu_dept" class="form-control" placeholder="e.g. Cardiology">
          </div>
          <div class="form-group">
            <label class="form-label">Specialty</label>
            <input id="cu_spec" class="form-control" placeholder="e.g. Cardiologist">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" onclick="submitCreateUser()">Create Account</button>
      </div>
    </div>`);
}

async function submitCreateUser() {
    const err = document.getElementById('createFormErr');

    const first  = (document.getElementById('cu_firstname').value || '').trim();
    const middle = (document.getElementById('cu_middlename').value || '').trim();
    const last   = (document.getElementById('cu_lastname').value || '').trim();

    if (!first || !last) {
        err.textContent = 'Please enter at least First Name and Last Name.';
        err.style.display = ''; return;
    }

    const fullName = middle ? `${first} ${middle} ${last}` : `${first} ${last}`;

    const pass        = document.getElementById('cu_pass').value;
    const passConfirm = document.getElementById('cu_pass_confirm').value;

    if (!pass) {
        err.textContent = 'Please enter a password.';
        err.style.display = ''; return;
    }
    if (pass.length < 6) {
        err.textContent = 'Password must be at least 6 characters.';
        err.style.display = ''; return;
    }
    if (pass !== passConfirm) {
        err.textContent = 'Passwords do not match.';
        err.style.display = ''; return;
    }

    const payload = {
        action:     'create',
        name:       fullName,
        email:      document.getElementById('cu_email').value,
        password:   pass,
        role:       'doctor_admin',
        department: document.getElementById('cu_dept').value,
        specialty:  document.getElementById('cu_spec').value,
    };

    if (!payload.email) {
        err.textContent = 'Please fill in all required fields.';
        err.style.display = ''; return;
    }

    const res = await apiCall(USERS_API, payload);
    if (res.success) {
        showToast('Doctor account created!', 'success');
        closeModal();
        setTimeout(() => location.reload(), 800);
    } else {
        err.textContent = res.message || 'Failed to create account.';
        err.style.display = '';
    }
}

// ---- Task Actions ----
async function markTask(id, status) {
    const notes = document.getElementById('notes-' + id) ? document.getElementById('notes-' + id).value : '';
    const res = await apiCall(TASKS_API, { action: 'nurse_update', id: id, status: status, notes: notes });
    if (res.success) {
        showToast(status === 'completed' ? 'Task completed!' : 'Task started!', 'success');
        const badge = document.getElementById('status-' + id);
        if (badge) {
            badge.textContent = status.replace('_', ' ');
            badge.className = 'badge-pill ' + (status === 'completed' ? 'status-completed' : 'status-progress');
        }
        if (status === 'completed') {
            const form = document.getElementById('form-' + id);
            if (form) form.innerHTML = '<p style="font-size:.78rem;color:var(--accent-green);padding-top:10px;border-top:1px solid var(--border)">✓ Marked as completed</p>';
        }
    } else {
        showToast(res.message || 'Failed to update task.', 'error');
    }
}

async function quickComplete(id) {
    const res = await apiCall(TASKS_API, { action: 'nurse_update', id: id, status: 'completed', notes: '' });
    if (res.success) {
        showToast('Task completed!', 'success');
        const badge = document.getElementById('status-' + id);
        if (badge) { badge.textContent = 'completed'; badge.className = 'badge-pill status-completed'; }
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(res.message || 'Failed.', 'error');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>