// ===== Admin JS — Shared utilities & presence system =====

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function roleBadge(role) {
    const colors = {SUPERADMIN:'#7c3aed',ADMIN:'#059669',MANAGER:'#92400e',CUSTOMER:'#1d4ed8'};
    return '<span class="role-badge role-'+role+'">'+role+'</span>';
}

/**
 * Initialize the presence bar showing online/offline staff status.
 * Call on any page with a <div id="presenceContainer"> element.
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
            html += '<span style="font-weight:600;color:#64748b;margin-right:.25rem">&#x1f7e2; Online:</span>';
            if (online.length === 0) html += '<span style="color:#94a3b8">None</span>';
            online.forEach(s => {
                html += '<span class="presence-online"><span class="presence-dot online"></span>'+escHtml(s.name)+' <small style="opacity:.7">('+s.role+')</small></span>';
            });
            if (offline.length > 0) {
                html += '<span style="font-weight:600;color:#64748b;margin:0 .25rem 0 .5rem">&#x2b55; Offline:</span>';
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

// ===== Admin Menu Management =====
(function() {
    if (!document.getElementById('items-table')) return;

    // Add category form toggle
    document.getElementById('add-category-btn')?.addEventListener('click', () => {
        document.getElementById('add-category-form').style.display = '';
    });
    document.getElementById('cancel-category-btn')?.addEventListener('click', () => {
        document.getElementById('add-category-form').style.display = 'none';
    });

    // Submit category
    document.getElementById('category-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const name = form.name.value.trim();
        const sortOrder = parseInt(form.sortOrder.value) || 0;
        const slug = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');

        try {
            const res = await fetch(`/api/menu/categories.php?restaurant=${RESTAURANT_SLUG}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, slug, sortOrder })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Failed to create category');
        } catch { alert('Network error'); }
    });

    // Toggle category active
    document.querySelectorAll('.toggle-cat-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const active = btn.dataset.active === '1' ? 0 : 1;
            const res = await fetch(`/api/menu/categories.php?id=${id}&restaurant=${RESTAURANT_SLUG}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ isActive: active })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Error');
        });
    });

    // Delete category
    document.querySelectorAll('.delete-cat-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this category?')) return;
            const id = btn.dataset.id;
            const res = await fetch(`/api/menu/categories.php?id=${id}&restaurant=${RESTAURANT_SLUG}`, {
                method: 'DELETE'
            });
            const data = await res.json();
            if (data.success) document.getElementById(`cat-row-${id}`)?.remove();
            else alert(data.error || 'Error');
        });
    });

    // ===== Item Modal =====
    const modal = document.getElementById('item-modal');
    const form  = document.getElementById('item-form');

    function openModal(item) {
        document.getElementById('modal-title').textContent = item ? 'Edit Menu Item' : 'Add Menu Item';
        document.getElementById('item-id').value          = item?.id || '';
        document.getElementById('item-name').value        = item?.name || '';
        document.getElementById('item-category').value    = item?.categoryId || '';
        document.getElementById('item-price').value       = item?.price || '';
        document.getElementById('item-stock').value       = item?.stockQuantity ?? '';
        document.getElementById('item-threshold').value   = item?.lowStockThreshold || 5;
        document.getElementById('item-prep').value        = item?.preparationTime || 15;
        document.getElementById('item-desc').value        = item?.description || '';
        document.getElementById('item-image').value       = item?.image || '';
        modal.style.display = '';
    }

    function closeModal() { modal.style.display = 'none'; }

    document.getElementById('add-item-btn')?.addEventListener('click', () => openModal(null));
    document.getElementById('close-modal')?.addEventListener('click', closeModal);
    document.getElementById('cancel-item-btn')?.addEventListener('click', closeModal);
    document.getElementById('modal-backdrop')?.addEventListener('click', closeModal);

    document.querySelectorAll('.edit-item-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            try { openModal(JSON.parse(btn.dataset.item)); }
            catch { alert('Error reading item data'); }
        });
    });

    // Submit item form
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const saveBtn = document.getElementById('save-item-btn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const id = document.getElementById('item-id').value;
        const stockVal = document.getElementById('item-stock').value;
        const body = {
            name:              document.getElementById('item-name').value.trim(),
            categoryId:        document.getElementById('item-category').value,
            price:             document.getElementById('item-price').value,
            stockQuantity:     stockVal !== '' ? parseInt(stockVal) : null,
            lowStockThreshold: parseInt(document.getElementById('item-threshold').value) || 5,
            preparationTime:   parseInt(document.getElementById('item-prep').value) || 15,
            description:       document.getElementById('item-desc').value.trim() || null,
            image:             document.getElementById('item-image').value.trim() || null,
        };

        try {
            const url = id
                ? `/api/menu/items.php?id=${id}&restaurant=${RESTAURANT_SLUG}`
                : `/api/menu/items.php?restaurant=${RESTAURANT_SLUG}`;
            const res = await fetch(url, {
                method: id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Failed to save item');
        } catch { alert('Network error'); }

        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Item';
    });

    // Toggle item availability
    document.querySelectorAll('.toggle-item-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const avail = btn.dataset.available === '1' ? 0 : 1;
            const res = await fetch(`/api/menu/items.php?id=${id}&restaurant=${RESTAURANT_SLUG}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ isAvailable: avail })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error);
        });
    });

    // Delete item
    document.querySelectorAll('.delete-item-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this menu item?')) return;
            const id = btn.dataset.id;
            const res = await fetch(`/api/menu/items.php?id=${id}&restaurant=${RESTAURANT_SLUG}`, {
                method: 'DELETE'
            });
            const data = await res.json();
            if (data.success) document.getElementById(`item-row-${id}`)?.remove();
            else alert(data.error);
        });
    });

    // ===== Item Filtering =====
    const catFilter   = document.getElementById('cat-filter');
    const itemSearch  = document.getElementById('item-search');

    function filterItems() {
        const cat = catFilter?.value || '';
        const q   = itemSearch?.value.toLowerCase().trim() || '';
        document.querySelectorAll('#items-table tbody tr').forEach(row => {
            const matchCat  = !cat || row.dataset.category === cat;
            const matchName = !q   || row.dataset.name.includes(q);
            row.style.display = matchCat && matchName ? '' : 'none';
        });
    }

    catFilter?.addEventListener('change', filterItems);
    itemSearch?.addEventListener('input', filterItems);
})();
