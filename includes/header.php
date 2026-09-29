<?php
// ==============================================
// Shared Layout Header Component
// ==============================================
// Usage: include this at the top of each page after requireLogin()
// Variables expected: $pageTitle, $activeNav

$user = currentUser();
$role = $user['role'] ?? '';

$navItems = [];
if ($role === 'main_admin') {
    $navItems = [
        ['icon' => 'grid', 'label' => 'Overview',      'href' => 'admin.php',        'id' => 'overview'],
        ['icon' => 'users', 'label' => 'Doctors',       'href' => 'admin.php?tab=doctors', 'id' => 'doctors'],
        ['icon' => 'user-nurse', 'label' => 'Nurses',   'href' => 'admin.php?tab=nurses',  'id' => 'nurses'],
        ['icon' => 'tasks', 'label' => 'All Tasks',     'href' => 'admin.php?tab=tasks',   'id' => 'tasks'],
        ['icon' => 'activity', 'label' => 'Activity Log','href' => 'admin.php?tab=activity','id' => 'activity'],
    ];
} elseif ($role === 'doctor_admin') {
    $navItems = [
        ['icon' => 'grid',    'label' => 'Overview',   'href' => 'doctor.php',              'id' => 'overview'],
        ['icon' => 'users',   'label' => 'My Nurses',  'href' => 'doctor.php?tab=nurses',   'id' => 'nurses'],
        ['icon' => 'tasks',   'label' => 'Tasks',      'href' => 'doctor.php?tab=tasks',    'id' => 'tasks'],
        ['icon' => 'plus-circle', 'label' => 'New Task','href' => 'doctor.php?tab=newtask', 'id' => 'newtask'],
        ['icon' => 'account', 'label' => 'My Account', 'href' => 'account.php',             'id' => 'account'],
    ];
} elseif ($role === 'nurse') {
    $navItems = [
        ['icon' => 'grid',    'label' => 'Overview',   'href' => 'nurse.php',               'id' => 'overview'],
        ['icon' => 'tasks',   'label' => 'My Tasks',   'href' => 'nurse.php?tab=tasks',     'id' => 'tasks'],
        ['icon' => 'history', 'label' => 'History',    'href' => 'nurse.php?tab=history',   'id' => 'history'],
        ['icon' => 'account', 'label' => 'My Account', 'href' => 'account.php',             'id' => 'account'],
    ];
}

$roleLabel = match($role) {
    'main_admin'   => 'System Admin',
    'doctor_admin' => $user['specialty'] ?? 'Doctor Admin',
    'nurse'        => 'Nurse',
    default        => 'User'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> — ClinIQ</title>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');</script>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>
// ==============================================
// MediTrack - Core JS (inlined to guarantee availability)
// ==============================================

function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 3500;
    var container = document.getElementById('toastContainer');
    if (!container) {
        // DOM not ready, retry after load
        window.addEventListener('load', function(){ showToast(message, type, duration); });
        return;
    }
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    var icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };
    toast.innerHTML = (icons[type] || icons.info) + '<span>' + message + '</span>';
    container.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(110%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(function(){ if (toast.parentNode) toast.parentNode.removeChild(toast); }, 320);
    }, duration);
}

function openModal(html) {
    var overlay = document.getElementById('modalOverlay');
    var mc      = document.getElementById('modalContainer');
    if (!overlay || !mc) return;
    overlay.classList.remove('hidden');
    mc.innerHTML = html;
    mc.classList.remove('hidden');
}

function closeModal() {
    var overlay = document.getElementById('modalOverlay');
    var mc      = document.getElementById('modalContainer');
    if (overlay) overlay.classList.add('hidden');
    if (mc)      { mc.classList.add('hidden'); mc.innerHTML = ''; }
}

async function apiCall(url, data) {
    var opts = data
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }
        : { method: 'GET' };
    try {
        var res  = await fetch(url, opts);
        var text = await res.text();
        try   { return JSON.parse(text); }
        catch (e) { return { success: false, message: 'Server error: ' + text.substring(0, 200) }; }
    } catch (e) {
        return { success: false, message: 'Network error: ' + e.message };
    }
}

function confirmAction(message, onConfirm) {
    openModal(
        '<div class="modal">' +
        '<div class="modal-header"><span class="modal-title">Confirm Action</span>' +
        '<button class="btn btn-icon modal-close" onclick="closeModal()">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
        '</button></div>' +
        '<div class="modal-body"><p style="color:var(--text-secondary)">' + message + '</p></div>' +
        '<div class="modal-footer">' +
        '<button class="btn btn-outline" onclick="closeModal()">Cancel</button>' +
        '<button class="btn btn-danger" id="confirmBtn">Confirm</button>' +
        '</div></div>'
    );
    setTimeout(function() {
        var btn = document.getElementById('confirmBtn');
        if (btn) btn.onclick = function(){ closeModal(); onConfirm(); };
    }, 50);
}

// Run after DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Close modal on overlay click
    var overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });
    }
    // Global search
    var search = document.getElementById('globalSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('[data-searchable]').forEach(function(el) {
                el.style.display = (!q || el.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
    // Bar chart styles
    var s = document.createElement('style');
    s.textContent = '.bar-chart{display:flex;align-items:flex-end;gap:10px;height:200px;padding:10px 0}.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%}.bar-fill{width:100%;background:linear-gradient(180deg,var(--accent-blue-g),var(--accent-blue));border-radius:6px 6px 0 0;position:relative;min-height:4px;transition:all .5s ease}.bar-fill:hover{filter:brightness(1.2)}.bar-label{font-size:.72rem;color:var(--text-muted);text-align:center}';
    document.head.appendChild(s);
});
    </script>
</head>
<body>

<div class="app-shell">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon-img">
                <img src="../assets/logo.png" alt="ClinIQ Logo" style="width:42px;height:42px;object-fit:cover;border-radius:50%;filter:drop-shadow(0 0 6px rgba(45,212,191,0.5));border:2px solid rgba(45,212,191,0.3);">
            </div>
            <div class="brand-text">
                <span class="brand-name">ClinIQ</span>
                <span class="brand-sub">Hospital OS</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($navItems as $item): ?>
            <a href="<?= $item['href'] ?>" class="nav-item <?= ($activeNav ?? '') === $item['id'] ? 'active' : '' ?>">
                <span class="nav-icon"><?= getNavIcon($item['icon']) ?></span>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="profile-avatar">
                    <img src="../assets/profile.png" alt="Profile"
                         style="width:38px;height:38px;object-fit:cover;border-radius:50%;
                                border:2px solid rgba(99,135,255,0.45);
                                box-shadow:0 0 10px rgba(99,135,255,0.3);">
                </div>
                <div class="user-info">
                    <span class="user-name"><?= sanitize($user['name']) ?></span>
                    <span class="user-role"><?= $roleLabel ?></span>
                </div>
                <a href="/hospital_management/includes/logout.php" class="logout-btn" title="Logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-btn menu-btn" type="button" aria-label="Menu" onclick="document.body.classList.toggle('sidebar-open')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="system-status">
                    <span class="status-dot"></span>
                    System Nominal
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Quick search..." id="globalSearch">
                </div>
                <div class="topbar-icons">
                    <button class="icon-btn" id="themeToggle" title="Toggle light/dark" onclick="(function(){var r=document.documentElement,t=r.getAttribute('data-theme')==='dark'?'light':'dark';r.setAttribute('data-theme',t);localStorage.setItem('theme',t);})()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="page-content">
<?php

function getNavIcon(string $name): string {
    return match($name) {
        'grid'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'users'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
        'tasks'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
        'activity'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        'history'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 108.42-8.97"/><polyline points="1 4 3 6 5 4"/><line x1="3" y1="6" x2="3" y2="3"/></svg>',
        'plus-circle'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        'user-nurse' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'account'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        default      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>'
    };
}