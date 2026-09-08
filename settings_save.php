<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — settings_save.php                         │
 * │  Auth-gated JSON endpoint. Merges posted fields into │
 * │  SITE_SETTINGS_FILE under "site" (from settings.php) │
 * │  or "design" (from the Customize modal in index.html). │
 * └──────────────────────────────────────────────────────┘
 *
 * POST section=site    csrf, site_name, icon, email, youtube, instagram,
 *                       soundcloud, rss
 * POST section=design  csrf, titlebar_color, controls_dock_color,
 *                       pl_item_color, bg_image, bg_repeat_x, bg_repeat_y,
 *                       bg_align, bg_fixed
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/id3.php'; // sanitiseUrl()/sanitiseText()

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

mp_require_login();

if (!mp_check_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired, please reload the page.']);
    exit;
}

function ss_hexColor(string $raw, string $fallback): string {
    $raw = trim($raw);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? strtoupper($raw) : $fallback;
}

$section = (string)($_POST['section'] ?? '');
$settings = mp_read_site_settings();

if ($section === 'site') {
    $settings['site_name'] = mb_substr(sanitiseText((string)($_POST['site_name'] ?? '')), 0, 60);

    // Only accept paths we generated ourselves (see background_upload.php),
    // never an arbitrary path — this value is rendered site-wide and is read
    // back off disk by favicon.php.
    $icon = sanitiseText((string)($_POST['icon'] ?? ''));
    if ($icon !== '' && !preg_match('#^images/icons/[A-Za-z0-9_\-]+\.(jpe?g|png|gif|webp)$#', $icon)) {
        $icon = $settings['icon'];
    }
    $settings['icon'] = $icon;

    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $settings['social'] = [
        'email'      => $email ?: '',
        'youtube'    => sanitiseUrl((string)($_POST['youtube'] ?? '')),
        'instagram'  => sanitiseUrl((string)($_POST['instagram'] ?? '')),
        'soundcloud' => sanitiseUrl((string)($_POST['soundcloud'] ?? '')),
        'rss'        => sanitiseUrl((string)($_POST['rss'] ?? '')),
    ];
} elseif ($section === 'design') {
    $defaults = mp_default_site_settings()['design'];
    $bgImage = sanitiseText((string)($_POST['bg_image'] ?? ''));
    // Only accept paths we generated ourselves (see background_upload.php)
    // — never trust an arbitrary URL/path here. This is admin-only, but the
    // value ends up rendered site-wide via site-config.php.
    if ($bgImage !== '' && $bgImage !== $defaults['bg_image']
        && !preg_match('#^images/backgrounds/[A-Za-z0-9_\-]+\.(jpe?g|png|gif|webp)$#', $bgImage)) {
        $bgImage = $settings['design']['bg_image'];
    }
    $align = (string)($_POST['bg_align'] ?? '');
    if (!in_array($align, ['left', 'center', 'right'], true)) {
        $align = $defaults['bg_align'];
    }
    $size = (string)($_POST['bg_size'] ?? '');
    if (!in_array($size, ['contain', 'cover', 'auto'], true)) {
        $size = $defaults['bg_size'];
    }
    $settings['design'] = [
        'titlebar_color'      => ss_hexColor((string)($_POST['titlebar_color'] ?? ''), $settings['design']['titlebar_color']),
        'controls_dock_color' => ss_hexColor((string)($_POST['controls_dock_color'] ?? ''), $settings['design']['controls_dock_color']),
        'pl_item_color'       => ss_hexColor((string)($_POST['pl_item_color'] ?? ''), $settings['design']['pl_item_color']),
        'bg_image'            => $bgImage,
        'bg_size'             => $size,
        'bg_repeat_x'         => !empty($_POST['bg_repeat_x']),
        'bg_repeat_y'         => !empty($_POST['bg_repeat_y']),
        'bg_align'            => $align,
        'bg_fixed'            => !empty($_POST['bg_fixed']),
    ];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown settings section.']);
    exit;
}

if (!mp_write_site_settings($settings)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save settings.']);
    exit;
}

echo json_encode(['ok' => true, 'settings' => $settings]);
