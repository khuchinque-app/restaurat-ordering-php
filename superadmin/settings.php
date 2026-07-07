<?php
$page_title = 'Settings';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/activity.php';

$restaurants = db_query('SELECT id, name, slug FROM Restaurant WHERE isActive = 1 ORDER BY name');
$slug = $_GET['restaurant'] ?? ($restaurants[0]['slug'] ?? null);
$restaurant = $slug ? get_restaurant($slug) : null;
$rid = $restaurant['id'] ?? null;

// Load current settings
$settings = [];
if ($rid) {
    $rows = db_query('SELECT settingKey, settingValue FROM RestaurantSetting WHERE restaurantId = ?', [$rid]);
    foreach ($rows as $row) $settings[$row['settingKey']] = $row['settingValue'];
}

// Defaults
$defaults = [
    'primary_color'   => '#dc2626',
    'primary_dark'    => '#b91c1c',
    'bg_color'        => '#e8eaed',
    'card_bg'         => '#ffffff',
    'text_color'      => '#111827',
    'header_bg'       => '#ffffff',
    'sidebar_bg'      => '#1e293b',
    'accent_color'    => '#6366f1',        'logo_url'        => '',
        'banner_url'      => '',
        'restaurant_name' => $restaurant['name'] ?? '',
        'restaurant_desc' => $restaurant['description'] ?? '',
        'tax_rate'        => '10',
        'currency_primary'=> 'USD',
        'exchange_rate'   => '4000',
        'theme_mode'      => 'light',
        'font_family'     => 'system-ui, -apple-system, sans-serif',
        'font_size'       => '16',
        'border_radius'   => '8',
];
$s = array_merge($defaults, $settings);

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rid) {
    $fields = [
        'primary_color', 'primary_dark', 'bg_color', 'card_bg', 'text_color',
        'header_bg', 'sidebar_bg', 'accent_color', 'logo_url', 'banner_url',
        'restaurant_name', 'restaurant_desc', 'tax_rate', 'currency_primary',
        'exchange_rate', 'theme_mode', 'font_family', 'font_size', 'border_radius'
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $val = trim($_POST[$f]);
            $existing = db_fetch('SELECT id FROM RestaurantSetting WHERE restaurantId = ? AND settingKey = ?', [$rid, $f]);
            if ($existing) {
                db_execute('UPDATE RestaurantSetting SET settingValue = ?, updatedAt = datetime("now") WHERE id = ?', [$val, $existing['id']]);
            } else {
                $sid = new_id();
                db_execute('INSERT INTO RestaurantSetting (id, restaurantId, settingKey, settingValue, createdAt, updatedAt) VALUES (?, ?, ?, ?, datetime("now"), datetime("now"))', [$sid, $rid, $f, $val]);
            }
        }
    }
    // Update restaurant name/desc directly
    if (!empty($_POST['restaurant_name'])) {
        db_execute('UPDATE Restaurant SET name = ?, description = ?, updatedAt = datetime("now") WHERE id = ?', [$_POST['restaurant_name'], $_POST['restaurant_desc'] ?? '', $rid]);
    }
    log_activity($current_user, 'UPDATE_SETTINGS', 'Restaurant', $rid, "Updated settings for " . ($restaurant['name'] ?? ''));
    header('Location: settings.php?restaurant=' . urlencode($slug) . '&saved=1');
    exit;
}
?>

<style>
.settings-layout { display:grid; grid-template-columns:1fr 380px; gap:var(--space-6); align-items:start; }
.settings-section {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    margin-bottom: var(--space-4);
}
.settings-section h3 { font-size:.95rem; font-weight:700; margin-bottom:1rem; color:var(--color-text-primary); }
.color-row { display:flex; gap:.75rem; align-items:center; margin-bottom:.75rem; }
.color-row label { font-size:.82rem; font-weight:500; min-width:120px; color:var(--color-text-secondary); }
.color-row input[type="color"] { width:40px; height:32px; border:1px solid var(--color-border); border-radius:var(--radius-sm); cursor:pointer; padding:2px; background:var(--color-surface); }
.color-row input[type="text"] { width:100px; font-size:.82rem; font-family:var(--font-mono); background:var(--color-surface); color:var(--color-text-primary); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:.25rem .4rem; }
.setting-input {
    width:100%; padding:.5rem .75rem;
    border:1px solid var(--color-border);
    border-radius:var(--radius-sm);
    font-size:.875rem;
    background:var(--color-surface-raised);
    color:var(--color-text-primary);
    font-family:var(--font-sans);
}
.setting-input:focus { outline:none; border-color:var(--color-accent); box-shadow:0 0 0 3px var(--color-accent-soft); }
.setting-textarea {
    width:100%; padding:.5rem .75rem;
    border:1px solid var(--color-border);
    border-radius:var(--radius-sm);
    font-size:.875rem; resize:vertical;
    background:var(--color-surface-raised);
    color:var(--color-text-primary);
}

/* ---- Live Preview ---- */
.preview-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-xl); overflow:hidden; box-shadow:var(--shadow-lg); }
.preview-header { padding:1rem 1.25rem; display:flex; align-items:center; gap:.75rem; }
.preview-menu { padding:.75rem; display:grid; grid-template-columns:1fr 1fr; gap:.5rem; background:var(--color-surface-raised); }
.preview-item { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.6rem; text-align:center; font-size:.78rem; }
.preview-item .pi-icon { font-size:1.5rem; margin-bottom:.2rem; }
.preview-item .pi-name { font-weight:600; font-size:.8rem; color:var(--color-text-primary); }
.preview-item .pi-price { color:var(--color-accent); font-weight:700; }
.quick-btns { display:flex; gap:.5rem; margin-top:var(--space-4); }

@media (max-width: 1024px) {
    .settings-layout { grid-template-columns:1fr; }
}
</style>

<?php if (!empty($_GET['saved'])): ?>
<div class="alert alert-success">✅ Settings saved successfully!</div>
<?php endif; ?>

<!-- Restaurant Selector -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:var(--space-6);flex-wrap:wrap">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Restaurant</label>
        <select onchange="location.href='settings.php?restaurant='+this.value" class="form-control" style="min-width:200px">
            <?php foreach ($restaurants as $r): ?>
            <option value="<?= htmlspecialchars($r['slug']) ?>" <?= $slug === $r['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="menu.php?restaurant=<?= urlencode($slug ?? '') ?>" class="btn btn-sm btn-outline">🍽 Menu</a>
        <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>" class="btn btn-sm btn-outline">📦 Stock</a>
        <?php $folder = storefront_folder($slug ?? ''); if ($folder): ?>
        <a href="/<?= htmlspecialchars($folder) ?>/" target="_blank" class="btn btn-sm btn-primary">🌐 View Storefront ↗</a>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="settings.php?restaurant=<?= urlencode($slug ?? '') ?>">
<div class="settings-layout">

<!-- Left: Settings Form -->
<div>
    <!-- Restaurant Info -->
    <div class="settings-section">
        <h3>🏪 Restaurant Info</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Name</label>
                <input type="text" name="restaurant_name" class="setting-input" value="<?= htmlspecialchars($s['restaurant_name']) ?>">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Description</label>
                <input type="text" name="restaurant_desc" class="setting-input" value="<?= htmlspecialchars($s['restaurant_desc']) ?>">
            </div>
        </div>
    </div>

    <!-- Theme Colors -->
    <div class="settings-section">
        <h3>🎨 Theme Colors</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem 2rem">
            <div class="color-row">
                <label>Primary</label>
                <input type="color" name="primary_color" value="<?= htmlspecialchars($s['primary_color']) ?>">
                <input type="text" value="<?= htmlspecialchars($s['primary_color']) ?>">
            </div>
            <div class="color-row">
                <label>Primary Dark</label>
                <input type="color" name="primary_dark" value="<?= htmlspecialchars($s['primary_dark']) ?>">
            </div>
            <div class="color-row">
                <label>Background</label>
                <input type="color" name="bg_color" value="<?= htmlspecialchars($s['bg_color']) ?>">
            </div>
            <div class="color-row">
                <label>Card Background</label>
                <input type="color" name="card_bg" value="<?= htmlspecialchars($s['card_bg']) ?>">
            </div>
            <div class="color-row">
                <label>Text Color</label>
                <input type="color" name="text_color" value="<?= htmlspecialchars($s['text_color']) ?>">
            </div>
            <div class="color-row">
                <label>Header Background</label>
                <input type="color" name="header_bg" value="<?= htmlspecialchars($s['header_bg']) ?>">
            </div>
            <div class="color-row">
                <label>Accent</label>
                <input type="color" name="accent_color" value="<?= htmlspecialchars($s['accent_color']) ?>">
            </div>
            <div class="color-row">
                <label>Theme Mode</label>
                <select name="theme_mode" class="setting-input" style="width:120px">
                    <option value="light" <?= $s['theme_mode'] === 'light' ? 'selected' : '' ?>>Light</option>
                    <option value="dark" <?= $s['theme_mode'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Typography -->
    <div class="settings-section">
        <h3>🔤 Typography</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Font Family</label>
                <select name="font_family" class="setting-input">
                    <?php foreach (['system-ui, -apple-system, sans-serif'=>'System UI','Georgia, serif'=>'Georgia','Arial, sans-serif'=>'Arial','Verdana, sans-serif'=>'Verdana','Courier New, monospace'=>'Courier New'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($s['font_family'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Font Size (px)</label>
                <input type="number" name="font_size" class="setting-input" value="<?= htmlspecialchars($s['font_size'] ?? '16') ?>" min="12" max="24">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Border Radius (px)</label>
                <input type="number" name="border_radius" class="setting-input" value="<?= htmlspecialchars($s['border_radius'] ?? '8') ?>" min="0" max="24">
            </div>
        </div>
    </div>

    <!-- Images -->
    <div class="settings-section">
        <h3>🖼 Images</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Logo URL</label>
                <input type="url" name="logo_url" class="setting-input" value="<?= htmlspecialchars($s['logo_url']) ?>" placeholder="https://...">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Banner/Hero URL</label>
                <input type="url" name="banner_url" class="setting-input" value="<?= htmlspecialchars($s['banner_url']) ?>" placeholder="https://...">
            </div>
        </div>
    </div>

    <!-- Payment / Currency -->
    <div class="settings-section">
        <h3>💱 Currency & Payment</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Primary Currency</label>
                <select name="currency_primary" class="setting-input">
                    <option value="USD" <?= $s['currency_primary'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                    <option value="KHR" <?= $s['currency_primary'] === 'KHR' ? 'selected' : '' ?>>KHR (៛)</option>
                    <option value="BOTH" <?= $s['currency_primary'] === 'BOTH' ? 'selected' : '' ?>>Both (USD + KHR)</option>
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Exchange Rate (KHR per $1)</label>
                <input type="number" name="exchange_rate" class="setting-input" value="<?= htmlspecialchars($s['exchange_rate']) ?>" min="0" step="100">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem;color:var(--color-text-secondary)">Tax Rate (%)</label>
                <input type="number" name="tax_rate" class="setting-input" value="<?= htmlspecialchars($s['tax_rate']) ?>" min="0" max="100" step="0.5">
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="settings.php?restaurant=<?= urlencode($slug ?? '') ?>" class="btn btn-outline">Reset</a>
        <button type="submit" class="btn btn-primary">💾 Save All Settings</button>
    </div>
</div>

<!-- Right: Live Preview -->
<div>
    <div style="font-size:.85rem;font-weight:600;color:var(--color-text-muted);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em">Live Preview</div>
    <div class="preview-card" id="preview-card">
        <div class="preview-header" id="preview-header" style="background:var(--color-surface);border-bottom:2px solid var(--color-accent)">
            <span style="font-size:1.3rem">🍽</span>
            <div>
                <div style="font-weight:700;font-size:1rem;color:var(--color-text-primary)" id="preview-name"><?= htmlspecialchars($s['restaurant_name']) ?></div>
                <div style="font-size:.75rem;color:var(--color-text-muted)" id="preview-desc"><?= htmlspecialchars($s['restaurant_desc']) ?></div>
            </div>
        </div>
        <div class="preview-menu" id="preview-menu">
            <div class="preview-item"><div class="pi-icon">🍔</div><div class="pi-name">Burger</div><div class="pi-price">$10.99</div></div>
            <div class="preview-item"><div class="pi-icon">🍟</div><div class="pi-name">Fries</div><div class="pi-price">$3.99</div></div>
            <div class="preview-item"><div class="pi-icon">🥤</div><div class="pi-name">Drink</div><div class="pi-price">$2.50</div></div>
            <div class="preview-item"><div class="pi-icon">🥗</div><div class="pi-name">Salad</div><div class="pi-price">$8.99</div></div>
        </div>
    </div>

    <div class="quick-btns">
        <a href="checkout.php?restaurant=<?= urlencode($slug ?? '') ?>" class="btn btn-sm btn-outline" style="flex:1;justify-content:center">📦 Orders</a>
        <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>" class="btn btn-sm btn-outline" style="flex:1;justify-content:center">📦 Stock</a>
    </div>
</div>

</div>
</form>

<script>
function updatePreview() {
    const p = document.querySelector('[name="primary_color"]')?.value || '#f97316';
    const pd = document.querySelector('[name="primary_dark"]')?.value || '#ea580c';
    const bg = document.querySelector('[name="bg_color"]')?.value || '#f9fafb';
    const card = document.querySelector('[name="card_bg"]')?.value || '#ffffff';
    const text = document.querySelector('[name="text_color"]')?.value || '#111827';
    const hbg = document.querySelector('[name="header_bg"]')?.value || '#ffffff';
    const accent = document.querySelector('[name="accent_color"]')?.value || '#6366f1';
    const fs = document.querySelector('[name="font_size"]')?.value || '16';
    const br = document.querySelector('[name="border_radius"]')?.value || '8';
    const ff = document.querySelector('[name="font_family"]')?.value || 'system-ui, -apple-system, sans-serif';
    const mode = document.querySelector('[name="theme_mode"]')?.value || 'light';

    const cardEl = document.getElementById('preview-card');
    const header = document.getElementById('preview-header');
    const items = document.querySelectorAll('.preview-item');
    const prices = document.querySelectorAll('.pi-price');
    const menu = document.getElementById('preview-menu');

    cardEl.style.background = card;
    cardEl.style.borderRadius = br + 'px';
    cardEl.style.color = text;
    cardEl.style.fontFamily = ff;
    cardEl.style.fontSize = fs + 'px';

    header.style.background = hbg;
    header.style.borderBottom = '2px solid ' + p;

    items.forEach(el => {
        el.style.background = bg;
        el.style.borderRadius = Math.max(4, parseInt(br) - 2) + 'px';
        el.style.borderColor = text + '22';
    });
    prices.forEach(el => { el.style.color = p; });

    menu.style.background = mode === 'dark' ? '#1e293b' : '#f8fafc';
    menu.style.color = mode === 'dark' ? '#e2e8f0' : text;

    document.documentElement.style.setProperty('--primary', p);
}

document.querySelectorAll('[name]').forEach(el => {
    if (el.name !== 'restaurant_name' && el.name !== 'restaurant_desc') {
        el.addEventListener('change', updatePreview);
        el.addEventListener('input', updatePreview);
    }
});

document.querySelectorAll('input[type="color"]').forEach(el => {
    el.addEventListener('input', function() {
        const next = this.nextElementSibling;
        if (next && next.type === 'text') next.value = this.value;
    });
});
document.querySelectorAll('input[type="text"]').forEach(el => {
    if (el.previousElementSibling && el.previousElementSibling.type === 'color') {
        el.addEventListener('change', function() {
            this.previousElementSibling.value = this.value;
        });
    }
});

document.querySelectorAll('[name="restaurant_name"], [name="restaurant_desc"]').forEach(el => {
    el.addEventListener('input', () => {
        document.getElementById('preview-name').textContent = document.querySelector('[name="restaurant_name"]').value || 'Restaurant';
        document.getElementById('preview-desc').textContent = document.querySelector('[name="restaurant_desc"]').value || 'Description';
    });
});

updatePreview();
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
