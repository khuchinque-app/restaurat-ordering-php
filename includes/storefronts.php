<?php
/**
 * Shared storefront navigation for the admin & superadmin panels.
 *
 * Lists every active restaurant (from the same data the API serves) and links
 * out to its public storefront folder. Folder names don't always equal the
 * slug, so we resolve the folder by checking which directory actually exists
 * at the web root.
 */
require_once dirname(__DIR__) . '/db.php';

/** Resolve a restaurant slug to its on-disk storefront folder name. */
function storefront_folder(string $slug): ?string {
    $root = dirname(__DIR__);
    foreach ([$slug, $slug . '_restaurant', $slug . '_house'] as $candidate) {
        if (is_dir($root . '/' . $candidate)) return $candidate;
    }
    return null; // no storefront folder deployed for this restaurant
}

/** Render the "Storefronts" nav block (sidebar links, open in a new tab). */
function render_storefront_nav(): void {
    try {
        $restaurants = db_query('SELECT name, slug FROM Restaurant WHERE isActive = 1 ORDER BY name ASC');
    } catch (Throwable $e) {
        $restaurants = [];
    }
    echo '<div class="sidebar-section-label" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;opacity:.6;margin:.3rem 0 .2rem;padding-left:.2rem">Storefronts</div>';
    if (!$restaurants) {
        echo '<span style="font-size:.8rem;opacity:.6;padding-left:.2rem">No restaurants yet</span>';
        return;
    }
    foreach ($restaurants as $r) {
        $folder = storefront_folder($r['slug']);
        if ($folder === null) continue; // skip restaurants with no deployed storefront
        $href = APP_URL . '/' . rawurlencode($folder) . '/';
        printf(
            '<a href="%s" target="_blank" rel="noopener" title="Open %s storefront">&#127978; %s &#8599;</a>',
            htmlspecialchars($href),
            htmlspecialchars($r['name']),
            htmlspecialchars($r['name'])
        );
    }
}
