<?php
$page_title = 'Settings';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

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
    'primary_color'   => '#f97316',
    'primary_dark'    => '#ea580c',
    'bg_color'        => '#f9fafb',
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
.settings-layout { display:grid; grid-template-columns:1fr 380px; gap:1.5rem; align-items:start; }
.settings-section { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; margin-bottom:1.25rem; }
.settings-section h3 { font-size:.95rem; font-weight:700; margin-bottom:1rem; color:#1e293b; }
.color-row { display:flex; gap:.75rem; align-items:center; margin-bottom:.75rem; }
.color-row label { font-size:.82rem; font-weight:500; min-width:120px; color:#475569; }
.color-row input[type="color"] { width:40px; height:32px; border:1px solid #d1d5db; border-radius:6px; cursor:pointer; padding:2px; }
.color-row input[type="text"] { width:100px; font-size:.82rem; font-family:monospace; }
.setting-input { width:100%; padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; }
.setting-input:focus { outline:none; border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.1); }
.setting-textarea { width:100%; padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; resize:vertical; }
.preview-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.06); }
.preview-header { padding:1rem 1.25rem; display:flex; align-items:center; gap:.75rem; }
.preview-menu { padding:.75rem; display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
.preview-item { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:.6rem; text-align:center; font-size:.78rem; }
.preview-item .pi-icon { font-size:1.5rem; margin-bottom:.2rem; }
.preview-item .pi-name { font-weight:600; font-size:.8rem; }
.preview-item .pi-price { color:var(--primary); font-weight:700; }
.quick-btns { display:flex; gap:.5rem; margin-top:1rem; }
</style>

<?php if (!empty($_GET['saved'])): ?>
<div class="alert alert-success" style="margin-bottom:1rem">✅ Settings saved successfully!</div>
<?php endif; ?>

<!-- Restaurant Selector -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Restaurant</label>
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
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Name</label>
                <input type="text" name="restaurant_name" class="setting-input" value="<?= htmlspecialchars($s['restaurant_name']) ?>">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Description</label>
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
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Font Family</label>
                <select name="font_family" class="setting-input">
                    <?php foreach (['system-ui, -apple-system, sans-serif'=>'System UI','Georgia, serif'=>'Georgia','Arial, sans-serif'=>'Arial','Verdana, sans-serif'=>'Verdana','Courier New, monospace'=>'Courier New'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($s['font_family'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Font Size (px)</label>
                <input type="number" name="font_size" class="setting-input" value="<?= htmlspecialchars($s['font_size'] ?? '16') ?>" min="12" max="24">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Border Radius (px)</label>
                <input type="number" name="border_radius" class="setting-input" value="<?= htmlspecialchars($s['border_radius'] ?? '8') ?>" min="0" max="24">
            </div>
        </div>
    </div>

    <!-- Images -->
    <div class="settings-section">
        <h3>🖼 Images</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Logo URL</label>
                <input type="url" name="logo_url" class="setting-input" value="<?= htmlspecialchars($s['logo_url']) ?>" placeholder="https://...">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Banner/Hero URL</label>
                <input type="url" name="banner_url" class="setting-input" value="<?= htmlspecialchars($s['banner_url']) ?>" placeholder="https://...">
            </div>
        </div>
    </div>

    <!-- Payment / Currency -->
    <div class="settings-section">
        <h3>💱 Currency & Payment</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Primary Currency</label>
                <select name="currency_primary" class="setting-input">
                    <option value="USD" <?= $s['currency_primary'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                    <option value="KHR" <?= $s['currency_primary'] === 'KHR' ? 'selected' : '' ?>>KHR (៛)</option>
                    <option value="BOTH" <?= $s['currency_primary'] === 'BOTH' ? 'selected' : '' ?>>Both (USD + KHR)</option>
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Exchange Rate (KHR per $1)</label>
                <input type="number" name="exchange_rate" class="setting-input" value="<?= htmlspecialchars($s['exchange_rate']) ?>" min="0" step="100">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Tax Rate (%)</label>
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
    <div style="font-size:.85rem;font-weight:600;color:#64748b;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em">Live Preview</div>
    <div class="preview-card" id="preview-card">
        <div class="preview-header" id="preview-header" style="background:#fff;border-bottom:2px solid #f97316">
            <span style="font-size:1.3rem">🍽</span>
            <div>
                <div style="font-weight:700;font-size:1rem" id="preview-name"><?= htmlspecialchars($s['restaurant_name']) ?></div>
                <div style="font-size:.75rem;color:#6b7280" id="preview-desc"><?= htmlspecialchars($s['restaurant_desc']) ?></div>
            </div>
        </div>
        <div class="preview-menu">
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

    // Update the preview background for dark mode
    document.querySelector('.preview-menu').style.background = mode === 'dark' ? '#1e293b' : '#f8fafc';
    document.querySelector('.preview-menu').style.color = mode === 'dark' ? '#e2e8f0' : text;

    document.documentElement.style.setProperty('--primary', p);
}

// Attach updatePreview to all setting inputs
document.querySelectorAll('[name]').forEach(el => {
    if (el.name !== 'restaurant_name' && el.name !== 'restaurant_desc') {
        el.addEventListener('change', updatePreview);
        el.addEventListener('input', updatePreview);
    }
});

// Sync color inputs with their text display
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

// Name/desc updates
document.querySelectorAll('[name="restaurant_name"], [name="restaurant_desc"]').forEach(el => {
    el.addEventListener('input', () => {
        document.getElementById('preview-name').textContent = document.querySelector('[name="restaurant_name"]').value || 'Restaurant';
        document.getElementById('preview-desc').textContent = document.querySelector('[name="restaurant_desc"]').value || 'Description';
    });
});

// Initial preview setup
updatePreview();
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
