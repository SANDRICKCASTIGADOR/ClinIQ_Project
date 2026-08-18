<?php
// ==============================================
// Hospital TMS - My Account Page
// Accessible by: doctor_admin, nurse
// Each user can ONLY view/edit their own account
// ==============================================
require_once __DIR__ . '/../includes/functions.php';
requireRole(['doctor_admin', 'nurse']);

$user      = currentUser();
$role      = $user['role'];
$pageTitle = 'My Account';
$activeNav = 'account';

// Handle password change success/error
$successMsg = '';
$errorMsg   = '';

// Get doctor info for nurses
$myDoctor = null;
if ($role === 'nurse' && $user['doctor_id']) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name, specialty, department, email FROM users WHERE id = ?");
    $stmt->execute([$user['doctor_id']]);
    $myDoctor = $stmt->fetch();
}

// Task stats
$db    = getDB();
$field = $role === 'nurse' ? 'nurse_id' : 'doctor_id';
$s1 = $db->prepare("SELECT COUNT(*) FROM tasks WHERE $field = ?"); $s1->execute([$user['id']]); $totalTasks = $s1->fetchColumn();
$s2 = $db->prepare("SELECT COUNT(*) FROM tasks WHERE $field = ? AND status = 'completed'"); $s2->execute([$user['id']]); $completedTasks = $s2->fetchColumn();
$s3 = $db->prepare("SELECT COUNT(*) FROM tasks WHERE $field = ? AND status = 'pending'"); $s3->execute([$user['id']]); $pendingTasks = $s3->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">My Account</h1>
        <p class="page-subtitle">Manage your personal information and password</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

    <!-- ===== LEFT: Profile Card ===== -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Profile Picture & Info -->
        <div class="card">
            <div class="card-body" style="text-align:center;padding:32px 24px">
                <div style="width:90px;height:90px;margin:0 auto 16px;position:relative">
                    <img src="<?= avatarSrc($user['profile_image'] ?? null) ?>" alt="Profile"
                         style="width:90px;height:90px;object-fit:cover;border-radius:50%;
                                border:3px solid var(--accent-a50)">
                    <div style="position:absolute;bottom:2px;right:2px;width:18px;height:18px;
                                background:var(--accent-green);border-radius:50%;
                                border:2px solid var(--bg-card)"></div>
                </div>
                <div style="font-size:1.15rem;font-weight:700;color:var(--text-primary);margin-bottom:4px">
                    <?= sanitize($user['name']) ?>
                </div>
                <div style="font-size:.8rem;color:var(--accent-blue-g);margin-bottom:4px">
                    <?= sanitize($user['specialty'] ?? ($role === 'nurse' ? 'Registered Nurse' : 'Doctor Admin')) ?>
                </div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px">
                    <?= sanitize($user['department'] ?? '') ?>
                </div>
                <span class="badge-pill <?= $role === 'doctor_admin' ? 'status-progress' : 'status-completed' ?>" style="font-size:.75rem">
                    <?= $role === 'doctor_admin' ? 'Doctor Admin' : 'Nurse' ?>
                </span>
            </div>
        </div>

        <!-- Task Stats -->
        <div class="card">
            <div class="card-header"><span class="card-title">My Statistics</span></div>
            <div class="card-body" style="padding:0">
                <div style="display:flex;flex-direction:column">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--border)">
                        <span style="font-size:.85rem;color:var(--text-secondary)">Total Tasks</span>
                        <span style="font-family:var(--font-mono);font-weight:700;color:var(--text-primary);font-size:1.1rem"><?= $totalTasks ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid var(--border)">
                        <span style="font-size:.85rem;color:var(--text-secondary)">Completed</span>
                        <span style="font-family:var(--font-mono);font-weight:700;color:var(--accent-green);font-size:1.1rem"><?= $completedTasks ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px">
                        <span style="font-size:.85rem;color:var(--text-secondary)">Pending</span>
                        <span style="font-family:var(--font-mono);font-weight:700;color:var(--accent-amber);font-size:1.1rem"><?= $pendingTasks ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($myDoctor): ?>
        <!-- Supervising Doctor (for nurses) -->
        <div class="card">
            <div class="card-header"><span class="card-title">My Doctor</span></div>
            <div class="card-body">
                <div style="display:flex;gap:12px;align-items:center">
                    <?= userAvatar($myDoctor['profile_image'] ?? null) ?>
                    <div>
                        <div style="font-weight:600;color:var(--text-primary);font-size:.9rem"><?= sanitize($myDoctor['name']) ?></div>
                        <div style="font-size:.75rem;color:var(--accent-blue-g)"><?= sanitize($myDoctor['specialty'] ?? '') ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($myDoctor['department'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Member Since -->
        <div class="card">
            <div class="card-body">
                <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Member Since</div>
                <div style="font-family:var(--font-mono);color:var(--text-primary);font-size:.9rem">
                    <?= date('F j, Y', strtotime($user['created_at'])) ?>
                </div>
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:8px">Account ID: #<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT: Edit Forms ===== -->
    <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Success / Error Messages -->
        <div id="successMsg" class="login-hint" style="display:<?= $successMsg ? '' : 'none' ?>;border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.06)">
            <p style="color:var(--accent-green)">✓ <?= sanitize($successMsg) ?></p>
        </div>
        <div id="errorMsg" class="error-msg" style="display:<?= $errorMsg ? '' : 'none' ?>">
            <?= sanitize($errorMsg) ?>
        </div>

        <!-- Edit Personal Info -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Personal Information</span>
                <span class="badge-pill status-progress" style="font-size:.7rem">Editable</span>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input id="acc_name" class="form-control" value="<?= sanitize($user['name']) ?>" placeholder="Your full name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input id="acc_email" class="form-control" type="email" value="<?= sanitize($user['email']) ?>" placeholder="your@email.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input id="acc_dept" class="form-control" value="<?= sanitize($user['department'] ?? '') ?>" placeholder="Your department">
                    </div>
                    <?php if ($role === 'doctor_admin'): ?>
                    <div class="form-group">
                        <label class="form-label">Specialty</label>
                        <input id="acc_spec" class="form-control" value="<?= sanitize($user['specialty'] ?? '') ?>" placeholder="Your specialty">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input class="form-control" value="Registered Nurse" disabled style="opacity:.5;cursor:not-allowed">
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:8px">
                    <button class="btn btn-primary" onclick="savePersonalInfo()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Change Password</span>
                <span class="badge-pill status-pending" style="font-size:.7rem">Security</span>
            </div>
            <div class="card-body">
                <div id="pwErrMsg" class="error-msg" style="display:none"></div>
                <div id="pwOkMsg" class="login-hint" style="display:none;border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.06)">
                    <p style="color:var(--accent-green)">✓ Password changed successfully!</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input id="pw_current" class="form-control" type="password" placeholder="Enter current password">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input id="pw_new" class="form-control" type="password" placeholder="Min 6 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input id="pw_confirm" class="form-control" type="password" placeholder="Repeat new password">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:8px">
                    <button class="btn btn-primary" onclick="changePassword()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Update Password
                    </button>
                </div>
            </div>
        </div>

        <!-- Read-only Account Details -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Account Details</span>
                <span class="badge-pill status-cancelled" style="font-size:.7rem">Read Only</span>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px">Account Role</div>
                        <div style="font-size:.9rem;color:var(--text-primary);font-weight:500">
                            <?= $role === 'doctor_admin' ? 'Doctor Admin' : 'Nurse' ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px">Account Status</div>
                        <span class="badge-pill status-completed">Active</span>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px">Email</div>
                        <div style="font-size:.9rem;color:var(--text-primary);font-family:var(--font-mono)"><?= sanitize($user['email']) ?></div>
                    </div>
                    <div>
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px">Account ID</div>
                        <div style="font-size:.9rem;color:var(--text-primary);font-family:var(--font-mono)">#<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ACCOUNT_API = '/hospital_management/api/account.php';

async function savePersonalInfo() {
    const name  = document.getElementById('acc_name').value.trim();
    const email = document.getElementById('acc_email').value.trim();
    const dept  = document.getElementById('acc_dept').value.trim();
    const spec  = document.getElementById('acc_spec') ? document.getElementById('acc_spec').value.trim() : '';

    if (!name)  { showToast('Name is required.',  'error'); return; }
    if (!email) { showToast('Email is required.', 'error'); return; }

    const res = await apiCall(ACCOUNT_API, {
        action: 'update_info', name, email, department: dept, specialty: spec
    });
    if (res.success) {
        showToast('Profile updated successfully!', 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(res.message || 'Failed to update.', 'error');
    }
}

async function changePassword() {
    const current  = document.getElementById('pw_current').value;
    const newPw    = document.getElementById('pw_new').value;
    const confirm  = document.getElementById('pw_confirm').value;
    const errEl    = document.getElementById('pwErrMsg');
    const okEl     = document.getElementById('pwOkMsg');

    errEl.style.display = 'none';
    okEl.style.display  = 'none';

    if (!current)           { errEl.textContent = 'Please enter your current password.'; errEl.style.display=''; return; }
    if (!newPw)             { errEl.textContent = 'Please enter a new password.';        errEl.style.display=''; return; }
    if (newPw.length < 6)   { errEl.textContent = 'New password must be at least 6 characters.'; errEl.style.display=''; return; }
    if (newPw !== confirm)  { errEl.textContent = 'New passwords do not match.';         errEl.style.display=''; return; }

    const res = await apiCall(ACCOUNT_API, {
        action: 'change_password', current_password: current, new_password: newPw
    });
    if (res.success) {
        okEl.style.display = '';
        document.getElementById('pw_current').value = '';
        document.getElementById('pw_new').value     = '';
        document.getElementById('pw_confirm').value = '';
        showToast('Password changed!', 'success');
    } else {
        errEl.textContent = res.message || 'Failed to change password.';
        errEl.style.display = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>