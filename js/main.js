// ==============================================
// MediTrack Hospital TMS - Main JavaScript
// Loaded in <head> so all functions are available immediately
// ==============================================

// ---------- Toast Notifications ----------
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 3500;

    // If DOM not ready yet, wait for it
    if (!document.getElementById('toastContainer')) {
        document.addEventListener('DOMContentLoaded', function() {
            showToast(message, type, duration);
        });
        return;
    }

    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;

    var icon = '';
    if (type === 'success') {
        icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    } else if (type === 'error') {
        icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    } else {
        icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    }

    toast.innerHTML = icon + '<span>' + message + '</span>';
    container.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(function() { 
            if (toast.parentNode) toast.parentNode.removeChild(toast); 
        }, 300);
    }, duration);
}

// ---------- Modal ----------
function openModal(html) {
    var overlay = document.getElementById('modalOverlay');
    var mc = document.getElementById('modalContainer');
    if (!overlay || !mc) return;
    overlay.classList.remove('hidden');
    mc.innerHTML = html;
    mc.classList.remove('hidden');
    overlay.onclick = function(e) {
        if (e.target === overlay) closeModal();
    };
}

function closeModal() {
    var overlay = document.getElementById('modalOverlay');
    var mc = document.getElementById('modalContainer');
    if (overlay) overlay.classList.add('hidden');
    if (mc) { mc.classList.add('hidden'); mc.innerHTML = ''; }
}

// ---------- AJAX helper ----------
async function apiCall(url, data) {
    var opts = data
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }
        : { method: 'GET' };
    try {
        var res = await fetch(url, opts);
        var text = await res.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            return { success: false, message: 'Server returned invalid response: ' + text.substring(0, 150) };
        }
    } catch (e) {
        return { success: false, message: 'Network error: ' + e.message };
    }
}

// ---------- Confirm dialog ----------
function confirmAction(message, onConfirm) {
    openModal(
        '<div class="modal">' +
        '<div class="modal-header"><span class="modal-title">Confirm Action</span>' +
        '<button class="modal-close" onclick="closeModal()">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
        '</button></div>' +
        '<div class="modal-body"><p style="color:var(--text-secondary)">' + message + '</p></div>' +
        '<div class="modal-footer">' +
        '<button class="btn btn-outline" onclick="closeModal()">Cancel</button>' +
        '<button class="btn btn-danger" id="confirmBtn">Confirm</button>' +
        '</div></div>'
    );
    var btn = document.getElementById('confirmBtn');
    if (btn) btn.addEventListener('click', function() { closeModal(); onConfirm(); });
}

// ---------- DOM-ready listeners ----------
document.addEventListener('DOMContentLoaded', function() {

    // Close modal on overlay click or .modal-close button
    document.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('.modal-close')) {
            closeModal();
        }
    });

    // Global search filter
    var searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('[data-searchable]').forEach(function(el) {
                el.style.display = (!q || el.textContent.toLowerCase().includes(q)) ? '' : 'none';
            });
        });
    }

    // Bar chart injection styles
    var s = document.createElement('style');
    s.textContent = '.bar-chart{display:flex;align-items:flex-end;gap:10px;height:200px;padding:10px 0}.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%}.bar-fill{width:100%;background:linear-gradient(180deg,var(--accent-blue-g),var(--accent-blue));border-radius:6px 6px 0 0;position:relative;min-height:4px;display:flex;align-items:flex-start;justify-content:center;transition:all .5s cubic-bezier(.4,0,.2,1)}.bar-fill:hover{filter:brightness(1.2)}.bar-val{font-size:.65rem;color:white;font-weight:600;padding-top:4px;opacity:0;transition:.2s}.bar-fill:hover .bar-val{opacity:1}.bar-label{font-size:.72rem;color:var(--text-muted);text-align:center;white-space:nowrap}';
    document.head.appendChild(s);
});

// ---------- Bar chart renderer ----------
function renderBarChart(containerId, data, maxVal) {
    var c = document.getElementById(containerId);
    if (!c) return;
    var max = maxVal || Math.max.apply(null, data.map(function(d){ return d.value; }).concat([1]));
    c.innerHTML = data.map(function(d) {
        return '<div class="bar-col">' +
            '<div class="bar-fill" style="height:' + Math.round((d.value/max)*100) + '%" title="' + d.value + '">' +
            '<div class="bar-val">' + d.value + '</div></div>' +
            '<div class="bar-label">' + d.label + '</div></div>';
    }).join('');
}