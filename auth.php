<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — auth.php                                  │
 * │  Session, CSRF, and login-lockout helpers.           │
 * │  No output, no side effects beyond session/lock      │
 * │  files. Safe to require_once from any admin script.  │
 * └──────────────────────────────────────────────────────┘
 *
 * Provides: mp_session_start(), mp_is_logged_in(), mp_require_login(),
 *           mp_csrf_token(), mp_check_csrf(), mp_login_attempt_allowed(),
 *           mp_login_record_failure(), mp_login_record_success(),
 *           mp_read_credentials(), mp_write_credentials(), mp_log_event()
 *
 * Used by: login.php, logout.php, forgot-password.php, reset-password.php,
 *          upload.php, upload_inspect.php, upload_commit.php
 */

require_once __DIR__ . '/config.php';

function mp_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('mp_admin');
    session_start();
}

/* ── Credentials file (JSON, filesystem-only — see config.php) ── */

function mp_read_credentials(): ?array {
    $raw = @file_get_contents(CREDENTIALS_FILE);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function mp_write_credentials(array $data): bool {
    return file_put_contents(CREDENTIALS_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

/* ── Site settings file (JSON, filesystem-only — see config.php) ──
   Public-facing values: site name, icon, social links, player design.
   Defaults are deliberately blank so a fresh install starts unbranded —
   index.html hides any social icon whose value is empty, and favicon.php
   falls back to the bundled default mark. */

function mp_default_site_settings(): array {
    return [
        // Shown in the player title bar and admin page titles. Blank falls
        // back to MP_DEFAULT_SITE_NAME, so a fresh install is never nameless.
        'site_name' => '',
        // Favicon path, set by uploading an image in settings.php. Blank
        // means favicon.php serves the bundled default mark.
        'icon' => '',
        // Blank by default: a fresh install shows no social icons at all
        // (applySocial() in index.html hides an icon whose value is empty).
        'social' => [
            'email'      => '',
            'youtube'    => '',
            'instagram'  => '',
            'soundcloud' => '',
            'rss'        => '',
        ],
        'design' => [
            'titlebar_color'     => '#FAC946',
            'controls_dock_color'=> '#007998',
            'pl_item_color'      => '#d4c07a',
            // Blank by default — a fresh install has no background photo.
            // When set, the value must always be applied explicitly (see
            // index.html's applyBackground()): the theme's own --bg-image
            // CSS var is declared on #app, which is body's descendant, not
            // its ancestor, so body never inherits it from the stylesheet.
            'bg_image'           => '',
            'bg_size'            => 'contain',
            'bg_repeat_x'        => true,
            'bg_repeat_y'        => true,
            'bg_align'           => 'right',
            'bg_fixed'           => false,
        ],
    ];
}

function mp_read_site_settings(): array {
    $raw = @file_get_contents(SITE_SETTINGS_FILE);
    $data = $raw === false ? null : json_decode($raw, true);
    $defaults = mp_default_site_settings();
    if (!is_array($data)) return $defaults;
    return [
        'site_name' => is_string($data['site_name'] ?? null) ? $data['site_name'] : $defaults['site_name'],
        'icon'      => is_string($data['icon'] ?? null) ? $data['icon'] : $defaults['icon'],
        'social'    => array_merge($defaults['social'], $data['social'] ?? []),
        'design'    => array_merge($defaults['design'], $data['design'] ?? []),
    ];
}

/* Site name for page titles and reset emails. Falls back to the product
   name so an install where nobody has set one yet still reads sensibly. */
function mp_site_name(): string {
    $name = trim((string) (mp_read_site_settings()['site_name'] ?? ''));
    if ($name !== '') return $name;
    // defined() guard, not a bare constant: config.php is per-install and
    // never overwritten by an update, so an install predating this constant
    // would otherwise fatal on every page.
    return defined('MP_DEFAULT_SITE_NAME') ? MP_DEFAULT_SITE_NAME : 'MPlayer';
}

function mp_write_site_settings(array $data): bool {
    return file_put_contents(SITE_SETTINGS_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

/* ── Login/session state ── */

function mp_is_logged_in(): bool {
    mp_session_start();
    if (empty($_SESSION['admin']) || ($_SESSION['expires'] ?? 0) <= time()) {
        return false;
    }
    // A password reset bumps session_version in the credentials file,
    // invalidating every session issued before the change.
    $creds = mp_read_credentials();
    $currentVersion = $creds['session_version'] ?? 0;
    if (($_SESSION['session_version'] ?? -1) !== $currentVersion) {
        return false;
    }
    return true;
}

function mp_require_login(): void {
    if (!mp_is_logged_in()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    mp_session_start();
    $_SESSION['expires'] = time() + 14400; // sliding 4-hour expiry
}

function mp_require_login_page(): void {
    if (!mp_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    mp_session_start();
    $_SESSION['expires'] = time() + 14400;
}

/* ── CSRF ── */

function mp_csrf_token(): string {
    mp_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function mp_check_csrf(): bool {
    mp_session_start();
    $sent = $_POST['csrf'] ?? '';
    return !empty($_SESSION['csrf']) && is_string($sent) && hash_equals($_SESSION['csrf'], $sent);
}

/* ── File-based login lockout, shared by login.php and forgot-password.php ──
   ponytail: one lockfile per IP, exponential backoff. Fine for a
   single-admin low-traffic site; per-account+IP sharding only matters if
   this ever sees real bot traffic at scale. */

function mp_lockfile(string $bucket): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return dirname(CREDENTIALS_FILE) . '/.mplayer-lock-' . $bucket . '-' . md5($ip) . '.json';
}

function mp_login_attempt_allowed(string $bucket = 'login'): bool {
    $f = mp_lockfile($bucket);
    $data = @json_decode((string)@file_get_contents($f), true) ?: ['count' => 0, 'until' => 0];
    return time() >= ($data['until'] ?? 0);
}

function mp_login_record_failure(string $bucket = 'login'): void {
    $f = mp_lockfile($bucket);
    $data = @json_decode((string)@file_get_contents($f), true) ?: ['count' => 0, 'until' => 0];
    $n = ($data['count'] ?? 0) + 1;
    $data['count'] = $n;
    $lock = $n >= 15 ? 900 : ($n >= 10 ? 300 : ($n >= 5 ? 60 : 0));
    $data['until'] = $lock ? time() + $lock : 0;
    @file_put_contents($f, json_encode($data), LOCK_EX);
}

function mp_login_record_success(string $bucket = 'login'): void {
    @unlink(mp_lockfile($bucket));
}

/* ── Audit log (append-only, no PII beyond IP, never logs secrets) ── */

function mp_log_event(string $event): void {
    $line = sprintf(
        "%s\t%s\t%s\n",
        date('c'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $event
    );
    @file_put_contents(dirname(CREDENTIALS_FILE) . '/.mplayer-audit.log', $line, FILE_APPEND | LOCK_EX);
}
