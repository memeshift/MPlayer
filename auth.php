<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — auth.php                          │
 * │  Session, CSRF, and login-lockout helpers.            │
 * │  No output, no side effects beyond session/lock       │
 * │  files. Safe to require_once from any admin script.   │
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
   Public-facing values (social links, player design). Defaults below
   match index.html's current hardcoded markup/CSS so an unset settings
   file renders identically to today's static page. */

function mp_default_site_settings(): array {
    return [
        // Matches index.html's current hardcoded links, so an unset
        // settings file changes nothing for existing visitors — only an
        // admin explicitly blanking a field in settings.php hides an icon.
        'social' => [
            'email'      => '',
            'youtube'    => 'https://www.youtube.com/memeshift',
            'instagram'  => 'https://www.instagram.com/memeshift/',
            'soundcloud' => 'https://soundcloud.com/memeshift',
            'rss'        => 'https://www.memeshift.com/rss',
        ],
        'design' => [
            'titlebar_color'     => '#FAC946',
            'controls_dock_color'=> '#007998',
            'pl_item_color'      => '#d4c07a',
            // Matches the memeshift theme's current background photo. The
            // theme's own --bg-image CSS var is declared on #app, which is
            // body's descendant, not ancestor — body never inherits it, so
            // this value must always be applied explicitly (see index.html's
            // applyBackground()) rather than left for the stylesheet to
            // supply on its own.
            'bg_image'           => 'https://www.memeshift.com/wp-content/uploads/2025/02/Scan-scaled.jpg',
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
        'social' => array_merge($defaults['social'], $data['social'] ?? []),
        'design' => array_merge($defaults['design'], $data['design'] ?? []),
    ];
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
