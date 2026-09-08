<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — stats.php                                 │
 * │  Auth-gated admin page. Placeholder — no data wiring │
 * │  yet, just reachable from the nav dropdown.          │
 * └──────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-style.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (!empty($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000');
}

mp_require_login_page();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Stats — <?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
<?php echo mp_admin_css(); ?>
body { max-width: 640px; }
</style>
</head>
<body>
<?php echo mp_admin_nav_html('stats'); ?>
<div class="page-header">
  <h1>Stats</h1>
</div>
<p class="lede">Coming soon.</p>
</body>
</html>
