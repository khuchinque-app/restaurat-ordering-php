// ===== Superadmin JS — Shared utilities & presence system =====

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function roleBadge(role) {
    const colors = {SUPERADMIN:'#7c3aed',ADMIN:'#1d4ed8',MANAGER:'#92400e',CUSTOMER:'#166534'};
    return '<span class="role-badge role-'+role+'" style="background:'+(colors[role]||'#6b7280')+'22;color:'+(colors[role]||'#6b7280')+'">'+role+'</span>';
}

function formatTime(isoStr) {
    try { return new Date(isoStr+'Z').toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }
    catch { return ''; }
}

/**
 * Initialize the presence bar that shows online/offline staff members.
 * Call this on any page that has a <div id="presenceContainer"> element.
 */
function initPresence() {
    const container = document.getElementById('presenceContainer');
    if (!container) return;

    async function poll() {
        try {
            const r = await fetch('/api/staff/presence.php', {credentials:'include'});
            const d = await r.json();
            if (!d.success || !d.data.staff) return;
            const online = d.data.staff.filter(s => s.isOnline == 1);
            const offline = d.data.staff.filter(s => s.isOnline == 0);
            let html = '<div class="presence-bar">';
            html += '<span style="font-weight:600;color:#64748b;margin-right:.25rem">🟢 Online:</span>';
            if (online.length === 0) html += '<span style="color:#94a3b8">None</span>';
            online.forEach(s => {
                html += '<span class="presence-online"><span class="presence-dot online"></span>'+escHtml(s.name)+' <small style="opacity:.7">('+s.role+')</small></span>';
            });
            if (offline.length > 0) {
                html += '<span style="font-weight:600;color:#64748b;margin:0 .25rem 0 .5rem">⭕ Offline:</span>';
                offline.forEach(s => {
                    html += '<span class="presence-offline"><span class="presence-dot offline"></span>'+escHtml(s.name)+'</span>';
                });
            }
            html += '</div>';
            container.innerHTML = html;
        } catch(e) {}
    }
    poll();
    setInterval(poll, 10000);
}
