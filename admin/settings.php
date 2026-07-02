<?php
$page_title = 'Restaurant Settings';
include dirname(__DIR__) . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/activity.php';

$restaurant = !empty($current_user['restaurantId'])
    ? db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$current_user['restaurantId']])
    : null;
$rid = $restaurant['id'] ?? null;
$slug = $restaurant['slug'] ?? '';

$settings = [];
if ($rid) {
    $rows = db_query('SELECT settingKey, settingValue FROM RestaurantSetting WHERE restaurantId = ?', [$rid]);
    foreach ($rows as $row) $settings[$row['settingKey']] = $row['settingValue'];
}

$defaults = [
    'primary_color' => '#059669', 'primary_dark' => '#047857', 'bg_color' => '#f0fdf4',
    'card_bg' => '#ffffff', 'text_color' => '#111827', 'header_bg' => '#ffffff',
    'sidebar_bg' => '#1e293b', 'accent_color' => '#0d9488',
    'font_family' => 'system-ui, -apple-system, sans-serif', 'font_size' => '16',
    'border_radius' => '8', 'logo_url' => '', 'banner_url' => '',
    'restaurant_name' => $restaurant['name'] ?? '',
    'restaurant_desc' => $restaurant['description'] ?? '',
    'tax_rate' => '10', 'currency_primary' => 'USD', 'exchange_rate' => '4000',
    'theme_mode' => 'light',
];
$s = array_merge($defaults, $settings);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rid) {
    $fields = ['primary_color','primary_dark','bg_color','card_bg','text_color','header_bg',
        'sidebar_bg','accent_color','font_family','font_size','border_radius',
        'logo_url','banner_url','restaurant_name','restaurant_desc',
        'tax_rate','currency_primary','exchange_rate','theme_mode'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $val = trim($_POST[$f]);
            $existing = db_fetch('SELECT id FROM RestaurantSetting WHERE restaurantId = ? AND settingKey = ?', [$rid, $f]);
            if ($existing) {
                db_execute('UPDATE RestaurantSetting SET settingValue = ?, updatedAt = datetime("now") WHERE id = ?', [$val, $existing['id']]);
            } else {
                db_execute('INSERT INTO RestaurantSetting (id, restaurantId, settingKey, settingValue, createdAt, updatedAt) VALUES (?, ?, ?, ?, datetime("now"), datetime("now"))',
                    [new_id(), $rid, $f, $val]);
            }
        }
    }
    if (!empty($_POST['restaurant_name'])) {
        db_execute('UPDATE Restaurant SET name = ?, description = ?, updatedAt = datetime("now") WHERE id = ?',
            [$_POST['restaurant_name'], $_POST['restaurant_desc'] ?? '', $rid]);
    }
    log_activity($current_user, 'UPDATE_SETTINGS', 'Restaurant', $rid, "Updated settings for " . ($restaurant['name'] ?? ''));
    header('Location: settings.php?saved=1');
    exit;
}
?>

<style>
.settings-layout{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start}
.settings-section{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:1.25rem;margin-bottom:1.25rem}
.settings-section h3{font-size:.95rem;font-weight:700;margin-bottom:1rem;color:#1e293b}
.color-row{display:flex;gap:.75rem;align-items:center;margin-bottom:.75rem}
.color-row label{font-size:.82rem;font-weight:500;min-width:120px;color:#475569}
.color-row input[type="color"]{width:40px;height:32px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;padding:2px}
.color-row input[type="text"]{width:100px;font-size:.82rem;font-family:monospace}
.setting-input{width:100%;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem}
.setting-input:focus{outline:none;border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,.1)}
.preview-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.preview-header{padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem}
.preview-menu{padding:.75rem;display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
.preview-item{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:.6rem;text-align:center;font-size:.78rem}
.preview-item .pi-icon{font-size:1.5rem;margin-bottom:.2rem}
.preview-item .pi-name{font-weight:600;font-size:.8rem}
</style>

<?php if (!empty($_GET['saved'])): ?>
<div class="alert alert-success" style="margin-bottom:1rem">✅ Settings saved successfully! Changes reflect across superadmin &amp; admin panels.</div>
<?php endif; ?>

<form method="POST" action="settings.php">
<div class="settings-layout">
<div>
    <!-- Restaurant Info -->
    <div class="settings-section">
        <h3>🏪 Restaurant Info</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
            <div><label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Name</label>
            <input type="text" name="restaurant_name" class="setting-input" value="<?= htmlspecialchars($s['restaurant_name']) ?>"></div>
            <div><label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Description</label>
            <input type="text" name="restaurant_desc" class="setting-input" value="<?= htmlspecialchars($s['restaurant_desc']) ?>"></div>
        </div>
    </div>

    <!-- Theme Colors -->
    <div class="settings-section">
        <h3>🎨 Theme Colors</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem 2rem">
            <?php foreach ([
                ['primary_color','Primary'],['primary_dark','Primary Dark'],['bg_color','Background'],
                ['card_bg','Card Background'],['text_color','Text Color'],['header_bg','Header Background'],
                ['sidebar_bg','Sidebar'],['accent_color','Accent']
            ] as [$key, $label]): ?>
            <div class="color-row">
                <label><?= $label ?></label>
                <input type="color" name="<?= $key ?>" value="<?= htmlspecialchars($s[$key]) ?>" onchange="this.nextElementSibling.value=this.value;updatePreview()">
                <input type="text" value="<?= htmlspecialchars($s[$key]) ?>" onchange="this.previousElementSibling.value=this.value">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="color-row" style="margin-top:.5rem">
            <label>Theme Mode</label>
            <select name="theme_mode" class="setting-input" style="width:120px">
                <option value="light" <?= $s['theme_mode'] === 'light' ? 'selected' : '' ?>>Light</option>
                <option value="dark" <?= $s['theme_mode'] === 'dark' ? 'selected' : '' ?>>Dark</option>
            </select>
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
                    <option value="<?= $v ?>" <?= $s['font_family'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Font Size (px)</label>
                <input type="number" name="font_size" class="setting-input" value="<?= htmlspecialchars($s['font_size']) ?>" min="12" max="24">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Border Radius (px)</label>
                <input type="number" name="border_radius" class="setting-input" value="<?= htmlspecialchars($s['border_radius']) ?>" min="0" max="24">
            </div>
        </div>
    </div>

    <!-- Images -->
    <div class="settings-section">
        <h3>🖼 Images</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
            <div><label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Logo URL</label>
            <input type="url" name="logo_url" class="setting-input" value="<?= htmlspecialchars($s['logo_url']) ?>" placeholder="https://..."></div>
            <div><label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Banner/Hero URL</label>
            <input type="url" name="banner_url" class="setting-input" value="<?= htmlspecialchars($s['banner_url']) ?>" placeholder="https://..."></div>
        </div>
    </div>

    <!-- Currency -->
    <div class="settings-section">
        <h3>💱 Currency & Payment</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
            <div>
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Primary Currency</label>
                <select name="currency_primary" class="setting-input">
                    <option value="USD" <?= $s['currency_primary'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                    <option value="KHR" <?= $s['currency_primary'] === 'KHR' ? 'selected' : '' ?>>KHR (៛)</option>
                    <option value="BOTH" <?= $s['currency_primary'] === 'BOTH' ? 'selected' : '' ?>>Both</option>
                </select>
            </div>
            <div><label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Exchange Rate (KHR per $1)</label>
            <input type="number" name="exchange_rate" class="setting-input" value="<?= htmlspecialchars($s['exchange_rate']) ?>" min="0" step="100"></div>
            <div><label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Tax Rate (%)</label>
            <input type="number" name="tax_rate" class="setting-input" value="<?= htmlspecialchars($s['tax_rate']) ?>" min="0" max="100" step="0.5"></div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="settings.php" class="btn btn-outline">Reset</a>
        <button type="submit" class="btn btn-primary">💾 Save All Settings</button>
    </div>
</div>

<!-- Live Preview -->
<div>
    <div style="font-size:.85rem;font-weight:600;color:#64748b;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em">Live Preview</div>
    <div class="preview-card">
        <div class="preview-header" id="preview-header" style="background:#fff;border-bottom:2px solid <?= htmlspecialchars($s['primary_color']) ?>">
            <span style="font-size:1.3rem">🍽</span>
            <div>
                <div style="font-weight:700;font-size:1rem" id="preview-name"><?= htmlspecialchars($s['restaurant_name']) ?></div>
                <div style="font-size:.75rem;color:#6b7280" id="preview-desc"><?= htmlspecialchars($s['restaurant_desc']) ?></div>
            </div>
        </div>
        <div class="preview-menu" id="preview-menu">
            <div class="preview-item"><div class="pi-icon">🍔</div><div class="pi-name">Burger</div><div class="pi-price" style="color:<?= htmlspecialchars($s['primary_color']) ?>;font-weight:700">$10.99</div></div>
            <div class="preview-item"><div class="pi-icon">🍟</div><div class="pi-name">Fries</div><div class="pi-price" style="color:<?= htmlspecialchars($s['primary_color']) ?>;font-weight:700">$3.99</div></div>
            <div class="preview-item"><div class="pi-icon">🥤</div><div class="pi-name">Drink</div><div class="pi-price" style="color:<?= htmlspecialchars($s['primary_color']) ?>;font-weight:700">$2.50</div></div>
            <div class="preview-item"><div class="pi-icon">🥗</div><div class="pi-name">Salad</div><div class="pi-price" style="color:<?= htmlspecialchars($s['primary_color']) ?>;font-weight:700">$8.99</div></div>
        </div>
    </div>
    <?php $folder = storefront_folder($restaurant['slug'] ?? ''); if ($folder): ?>
    <a href="/<?= htmlspecialchars($folder) ?>/index.html" target="_blank" class="btn btn-sm btn-primary" style="width:100%;justify-content:center;margin-top:1rem">🌐 View Storefront ↗</a>
    <?php endif; ?>
</div>
</div>
</form>

<script>
function updatePreview() {
    const primary = document.querySelector('[name="primary_color"]')?.value || '#059669';
    document.getElementById('preview-header').style.borderBottomColor = primary;
    document.querySelectorAll('.pi-price').forEach(el => el.style.color = primary);
}
document.querySelectorAll('[name="restaurant_name"], [name="restaurant_desc"]').forEach(el => {
    el.addEventListener('input', () => {
        document.getElementById('preview-name').textContent = document.querySelector('[name="restaurant_name"]').value || 'Restaurant';
        document.getElementById('preview-desc').textContent = document.querySelector('[name="restaurant_desc"]').value || 'Description';
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
