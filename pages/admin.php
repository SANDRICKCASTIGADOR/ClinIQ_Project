<?php
// ==============================================
// Hospital TMS - Main Admin Dashboard
// ==============================================
require_once __DIR__ . '/../includes/functions.php';
requireRole('main_admin');

$user   = currentUser();
$tab    = $_GET['tab'] ?? 'overview';
$stats  = getDashboardStats('main_admin', $user['id']);
$pageTitle = 'Admin Dashboard';
$activeNav = $tab === 'overview' ? 'overview' : $tab;

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">
            <?= match($tab) {
                'doctors'  => 'Doctor Management',
                'nurses'   => 'Nurse Management',
                'tasks'    => 'All Tasks',
                'activity' => 'Activity Log',
                default    => 'Performance Deck'
            } ?>
        </h1>
        <p class="page-subtitle">
            Welcome back, <span class="highlight"><?= sanitize($user['name']) ?></span>.
            <?= match($tab) {
                'doctors'  => 'Manage doctor admin accounts.',
                'nurses'   => 'Manage nurse accounts and assignments.',
                'tasks'    => 'Monitoring all hospital tasks.',
                'activity' => 'Full system activity log.',
                default    => 'Monitoring real-time hospital operations for <span class="highlight">All Nodes</span>.'
            } ?>
        </p>
    </div>
    <?php if ($tab === 'overview' || $tab === 'doctors' || $tab === 'nurses'): ?>
    <button class="btn btn-primary" onclick="openCreateUserModal('<?= $tab === 'nurses' ? 'nurse' : ($tab === 'doctors' ? 'doctor_admin' : '') ?>')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $tab === 'nurses' ? 'Add Nurse' : ($tab === 'doctors' ? 'Add Doctor' : 'New Account') ?>
    </button>
    <?php endif; ?>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ===== OVERVIEW ===== -->
<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(61,111,255,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6389ff" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <span class="stat-badge neut">Doctors</span>
        </div>
        <div class="stat-label">Total Doctors</div>
        <div class="stat-value"><?= $stats['total_doctors'] ?></div>
    </div>
    <div class="stat-card teal">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(45,212,191,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span class="stat-badge neut">Staff</span>
        </div>
        <div class="stat-label">Total Nurses</div>
        <div class="stat-value"><?= $stats['total_nurses'] ?></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-top">
            <div class="stat-icon" style="background:rgba(155,123,255,0.1)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#9b7bff" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
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
            <span class="stat-badge up">+Today</span>
        </div>
        <div class="stat-label">Completed Today</div>
        <div class="stat-value"><?= $stats['completed_today'] ?></div>
    </div>
</div>

<div class="content-grid">
    <!-- Tasks Overview -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Tasks</span>
            <a href="admin.php?tab=tasks" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body p0">
            <?php $tasks = getAllTasks(date('Y-m-d')); ?>
            <?php if ($tasks): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Nurse</th>
                        <th>Doctor</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($tasks, 0, 6) as $t): ?>
                    <tr data-searchable>
                        <td>
                            <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                            <?php if ($t['due_time']): ?>
                            <div class="table-sub"><?= date('g:i A', strtotime($t['due_time'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= sanitize($t['nurse_name']) ?></td>
                        <td><?= sanitize($t['doctor_name']) ?></td>
                        <td><span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                        <td><span class="badge-pill <?= statusClass($t['status']) ?>"><?= str_replace('_', ' ', $t['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                <p>No tasks scheduled for today</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live Activity -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Live Activity</span>
        </div>
        <div class="activity-feed">
            <?php $activities = getRecentActivity(8); ?>
            <?php foreach ($activities as $act): ?>
            <div class="activity-item">
                <div class="activity-dot">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="activity-body">
                    <div class="activity-title"><?= sanitize($act['action']) ?></div>
                    <div class="activity-detail"><?= sanitize($act['details']) ?></div>
                </div>
                <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (!$activities): ?>
            <div class="empty-state"><p>No activity yet</p></div>
            <?php endif; ?>
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--border)">
            <a href="admin.php?tab=activity" class="btn btn-outline btn-sm w-full" style="justify-content:center">View Audit Log</a>
        </div>
    </div>
</div>

<!-- Doctors Overview -->
<div class="mt-24">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:16px;color:var(--text-secondary)">Active Doctor Admins</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
        <?php $doctors = getUsersByRole('doctor_admin'); ?>
        <?php foreach ($doctors as $doc): ?>
        <?php
            $nurseCount = count(getNursesByDoctor($doc['id']));
            $db = getDB();
            $ts = $db->prepare("SELECT COUNT(*) FROM tasks WHERE doctor_id=?"); $ts->execute([$doc['id']]); $tc = $ts->fetchColumn();
        ?>
        <div class="doctor-card">
            <?= userAvatar($doc['profile_image'] ?? null, 'lg') ?>
            <div class="doctor-card-info">
                <div class="doctor-card-name"><?= sanitize($doc['name']) ?></div>
                <div class="doctor-card-spec"><?= sanitize($doc['specialty'] ?? 'General') ?></div>
                <div class="doctor-card-stats">
                    <div class="doctor-stat"><strong><?= $nurseCount ?></strong> Nurses</div>
                    <div class="doctor-stat"><strong><?= $tc ?></strong> Tasks</div>
                    <div class="doctor-stat"><?= sanitize($doc['department'] ?? '—') ?></div>
                </div>
            </div>
            <span class="badge-pill <?= $doc['is_active'] ? 'status-completed' : 'status-cancelled' ?>" style="font-size:.7rem">
                <?= $doc['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($tab === 'doctors'): ?>
<!-- ===== DOCTORS TAB ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Doctor Admin Accounts</span>
        <span class="text-muted text-sm"><?= count(getUsersByRole('doctor_admin')) ?> doctors</span>
    </div>
    <div class="card-body p0">
        <table class="data-table">
            <thead><tr><th>Doctor</th><th>Specialty</th><th>Department</th><th>Nurses</th><th>Tasks</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach (getUsersByRole('doctor_admin') as $doc): ?>
                <?php
                    $nc = count(getNursesByDoctor($doc['id']));
                    $db = getDB(); $ts = $db->prepare("SELECT COUNT(*) FROM tasks WHERE doctor_id=?"); $ts->execute([$doc['id']]); $tc = $ts->fetchColumn();
                ?>
                <tr data-searchable>
                    <td>
                        <div class="flex align-center gap-12">
                            <?= userAvatar($doc['profile_image'] ?? null, 'sm') ?>
                            <div>
                                <div class="table-name"><?= sanitize($doc['name']) ?></div>
                                <div class="table-sub"><?= sanitize($doc['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= sanitize($doc['specialty'] ?? '—') ?></td>
                    <td><?= sanitize($doc['department'] ?? '—') ?></td>
                    <td><span class="font-mono" style="color:var(--accent-blue-g)"><?= $nc ?></span></td>
                    <td><span class="font-mono" style="color:var(--accent-purple)"><?= $tc ?></span></td>
                    <td><span class="badge-pill <?= $doc['is_active'] ? 'status-completed' : 'status-cancelled' ?>"><?= $doc['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="actions-row">
                            <button class="btn btn-outline btn-sm" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($doc)) ?>)">Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteUserConfirm(<?= $doc['id'] ?>, '<?= sanitize($doc['name']) ?>')">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'nurses'): ?>
<!-- ===== NURSES TAB ===== -->
<?php $doctors = getUsersByRole('doctor_admin'); ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Nurse Accounts</span>
        <span class="text-muted text-sm"><?= count(getUsersByRole('nurse')) ?> nurses</span>
    </div>
    <div class="card-body p0">
        <table class="data-table">
            <thead><tr><th>Nurse</th><th>Department</th><th>Assigned Doctor</th><th>Tasks</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach (getUsersByRole('nurse') as $nurse): ?>
                <?php
                    $doc = null;
                    foreach ($doctors as $d) { if ($d['id'] == $nurse['doctor_id']) { $doc = $d; break; } }
                    $db = getDB(); $ts = $db->prepare("SELECT COUNT(*) FROM tasks WHERE nurse_id=?"); $ts->execute([$nurse['id']]); $tc = $ts->fetchColumn();
                ?>
                <tr data-searchable>
                    <td>
                        <div class="flex align-center gap-12">
                            <?= userAvatar($nurse['profile_image'] ?? null, 'sm') ?>
                            <div>
                                <div class="table-name"><?= sanitize($nurse['name']) ?></div>
                                <div class="table-sub"><?= sanitize($nurse['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= sanitize($nurse['department'] ?? '—') ?></td>
                    <td><?= $doc ? sanitize($doc['name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                    <td><span class="font-mono" style="color:var(--accent-purple)"><?= $tc ?></span></td>
                    <td><span class="badge-pill <?= $nurse['is_active'] ? 'status-completed' : 'status-cancelled' ?>"><?= $nurse['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="actions-row">
                            <button class="btn btn-outline btn-sm" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($nurse)) ?>)">Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteUserConfirm(<?= $nurse['id'] ?>, '<?= sanitize($nurse['name']) ?>')">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'tasks'): ?>
<!-- ===== ALL TASKS TAB ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Tasks</span>
        <div class="flex gap-8 align-center">
            <input type="date" class="form-control" id="taskDateFilter" value="<?= date('Y-m-d') ?>" style="width:auto;padding:6px 12px;font-size:.8rem" onchange="filterTasks(this.value)">
        </div>
    </div>
    <div class="card-body p0">
        <?php $tasks = getAllTasks(); ?>
        <table class="data-table" id="tasksTable">
            <thead><tr><th>Task</th><th>Nurse</th><th>Doctor</th><th>Date</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($tasks as $t): ?>
                <tr data-searchable>
                    <td>
                        <div class="table-name"><?= sanitize($t['task_title']) ?></div>
                        <div class="table-sub" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($t['task_description']) ?></div>
                    </td>
                    <td>
                        <div class="flex align-center gap-8">
                            <?= userAvatar($t['nurse_image'] ?? null, 'sm') ?>
                            <?= sanitize($t['nurse_name']) ?>
                        </div>
                    </td>
                    <td><?= sanitize($t['doctor_name']) ?></td>
                    <td><span class="font-mono text-sm"><?= date('M j', strtotime($t['task_date'])) ?></span></td>
                    <td><span class="badge-pill <?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                    <td><span class="badge-pill <?= statusClass($t['status']) ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tasks): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No tasks found</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'activity'): ?>
<!-- ===== ACTIVITY LOG TAB ===== -->
<div class="card">
    <div class="card-header">
        <span class="card-title">System Activity Log</span>
        <span class="text-muted text-sm">Full audit trail</span>
    </div>
    <div class="card-body p0">
        <?php $activities = getRecentActivity(50); ?>
        <table class="data-table">
            <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($activities as $act): ?>
                <tr data-searchable>
                    <td>
                        <div class="flex align-center gap-8">
                            <?= userAvatar($act['profile_image'] ?? null, 'sm') ?>
                            <?= sanitize($act['name']) ?>
                        </div>
                    </td>
                    <td><span class="role-dot role-<?= $act['role'] ?>"><?= str_replace('_',' ',$act['role']) ?></span></td>
                    <td><strong style="color:var(--text-primary)"><?= sanitize($act['action']) ?></strong></td>
                    <td style="max-width:300px;color:var(--text-muted);font-size:.8rem"><?= sanitize($act['details']) ?></td>
                    <td class="text-sm text-muted font-mono"><?= date('M j, g:i A', strtotime($act['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ===== ADMIN JAVASCRIPT ===== -->
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
        input.type      = 'text';
        eyeOn.style.display  = 'none';
        eyeOff.style.display = 'block';
    } else {
        input.type      = 'password';
        eyeOn.style.display  = 'block';
        eyeOff.style.display = 'none';
    }
}

// ---- Create User Modal ----
function openCreateUserModal(forceRole) {
    forceRole = forceRole || '';
    const roleOpts = forceRole
        ? `<option value="${forceRole}" selected>${forceRole === 'nurse' ? 'Nurse' : 'Doctor Admin'}</option>`
        : `<option value="doctor_admin">Doctor Admin</option><option value="nurse">Nurse</option>`;
    openModal(`
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">Create New Account</span>
        <button class="modal-close" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div id="createFormErr" class="error-msg" style="display:none"></div>

        <div class="form-group">
          <label class="form-label">Role</label>
          <select id="cu_role" class="form-control" onchange="toggleDoctorFields()">${roleOpts}</select>
        </div>

        <!-- Split name fields: shown for doctor_admin -->
        <div id="splitNameFields" style="display:none">
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
        </div>

        <!-- Split name fields: shown for nurse -->
        <div id="fullNameField" style="display:none">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name</label>
              <input id="cu_firstname_nurse" class="form-control" placeholder="First Name">
            </div>
            <div class="form-group">
              <label class="form-label">Middle Name <span class="text-muted">(optional)</span></label>
              <input id="cu_middlename_nurse" class="form-control" placeholder="Middle Name">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input id="cu_lastname_nurse" class="form-control" placeholder="Last Name">
          </div>
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
        <div class="form-group">
            <label class="form-label">Department</label>
            <input id="cu_dept" class="form-control" placeholder="e.g. Cardiology">
        </div>
        <div id="doctorFields">
          <div class="form-group">
            <label class="form-label">Specialty</label>
            <input id="cu_spec" class="form-control" placeholder="e.g. Cardiologist">
          </div>
        </div>
        <div id="nurseFields" style="display:none">
          <div class="form-group">
            <label class="form-label">Assign to Doctor</label>
            <select id="cu_doc" class="form-control">
              <option value="">Select Doctor...</option>
              ${doctors.map(d => `<option value="${d.id}">${d.name}</option>`).join('')}
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" onclick="submitCreateUser()">Create Account</button>
      </div>
    </div>`);
    toggleDoctorFields();
}

function toggleDoctorFields() {
    const role = document.getElementById('cu_role') ? document.getElementById('cu_role').value : '';
    const df   = document.getElementById('doctorFields');
    const nf   = document.getElementById('nurseFields');
    const snf  = document.getElementById('splitNameFields');
    const fnf  = document.getElementById('fullNameField');

    // Show split name fields for both doctor_admin and nurse
    if (snf) snf.style.display = role === 'doctor_admin' ? '' : 'none';
    if (fnf) fnf.style.display = role === 'nurse' ? '' : 'none';

    if (df) df.style.display = role === 'doctor_admin' ? '' : 'none';
    if (nf) nf.style.display = role === 'nurse' ? '' : 'none';
}

async function submitCreateUser() {
    const err  = document.getElementById('createFormErr');
    const role = document.getElementById('cu_role').value;

    // Build full name depending on role
    let fullName = '';
    if (role === 'doctor_admin') {
        const first  = (document.getElementById('cu_firstname').value || '').trim();
        const middle = (document.getElementById('cu_middlename').value || '').trim();
        const last   = (document.getElementById('cu_lastname').value || '').trim();
        if (!first || !last) {
            err.textContent = 'Please enter at least First Name and Last Name.';
            err.style.display = ''; return;
        }
        fullName = middle ? `${first} ${middle} ${last}` : `${first} ${last}`;
    } else {
        const first  = (document.getElementById('cu_firstname_nurse').value || '').trim();
        const middle = (document.getElementById('cu_middlename_nurse').value || '').trim();
        const last   = (document.getElementById('cu_lastname_nurse').value || '').trim();
        if (!first || !last) {
            err.textContent = 'Please enter at least First Name and Last Name.';
            err.style.display = ''; return;
        }
        fullName = middle ? `${first} ${middle} ${last}` : `${first} ${last}`;
    }

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
        role:       role,
        department: document.getElementById('cu_dept').value,
        specialty:  document.getElementById('cu_spec') ? document.getElementById('cu_spec').value : '',
        doctor_id:  document.getElementById('cu_doc')  ? document.getElementById('cu_doc').value  : '',
    };
    if (!payload.name || !payload.email) {
        err.textContent = 'Please fill in all required fields.';
        err.style.display = ''; return;
    }
    const res = await apiCall(USERS_API, payload);
    if (res.success) {
        showToast('Account created!', 'success');
        closeModal();
        setTimeout(() => location.reload(), 800);
    } else {
        err.textContent = res.message || 'Failed to create account.';
        err.style.display = '';
    }
}

function openEditUserModal(user) {
    openModal(`
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">Edit: ${user.name}</span>
        <button class="modal-close" onclick="closeModal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div class="modal-body">
        <div id="editFormErr" class="error-msg" style="display:none"></div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input id="eu_name" class="form-control" value="${user.name}">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input id="eu_email" class="form-control" type="email" value="${user.email}">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">New Password <span class="text-muted">(leave blank)</span></label>
            <input id="eu_pass" class="form-control" type="password" placeholder="New password only">
          </div>
          <div class="form-group">
            <label class="form-label">Department</label>
            <input id="eu_dept" class="form-control" value="${user.department || ''}">
          </div>
        </div>
        ${user.role === 'doctor_admin' ? `<div class="form-group"><label class="form-label">Specialty</label><input id="eu_spec" class="form-control" value="${user.specialty || ''}"></div>` : ''}
        ${user.role === 'nurse' ? `<div class="form-group"><label class="form-label">Assign to Doctor</label><select id="eu_doc" class="form-control">${doctors.map(d => `<option value="${d.id}" ${d.id == user.doctor_id ? 'selected' : ''}>${d.name}</option>`).join('')}</select></div>` : ''}
        <div class="form-group">
          <label class="form-label">Status</label>
          <select id="eu_active" class="form-control">
            <option value="1" ${user.is_active ? 'selected' : ''}>Active</option>
            <option value="0" ${!user.is_active ? 'selected' : ''}>Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" onclick="submitEditUser(${user.id}, '${user.role}')">Save Changes</button>
      </div>
    </div>`);
}

async function submitEditUser(id, role) {
    const payload = {
        action:     'update', id,
        name:       document.getElementById('eu_name').value,
        email:      document.getElementById('eu_email').value,
        password:   document.getElementById('eu_pass').value,
        department: document.getElementById('eu_dept').value,
        is_active:  document.getElementById('eu_active').value,
    };
    if (role === 'doctor_admin' && document.getElementById('eu_spec')) payload.specialty  = document.getElementById('eu_spec').value;
    if (role === 'nurse'        && document.getElementById('eu_doc'))  payload.doctor_id  = document.getElementById('eu_doc').value;
    const res = await apiCall(USERS_API, payload);
    if (res.success) {
        showToast('Account updated!', 'success');
        closeModal();
        setTimeout(() => location.reload(), 800);
    } else {
        document.getElementById('editFormErr').textContent = res.message || 'Failed.';
        document.getElementById('editFormErr').style.display = '';
    }
}

function deleteUserConfirm(id, name) {
    confirmAction('Delete account for <strong>' + name + '</strong>? This cannot be undone.', async () => {
        const res = await apiCall(USERS_API, { action: 'delete', id });
        if (res.success) { showToast('Account deleted.', 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Failed to delete.', 'error');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>