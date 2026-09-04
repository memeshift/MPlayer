<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — forgot-password.php               │
 * │  Requests a password-reset email. Always responds     │
 * │  identically regardless of match, to avoid account    │
 * │  enumeration and email-bombing.                        │
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

mp_session_start();

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = microtime(true);
    $submitted = true;

    if (mp_check_csrf() && mp_login_attempt_allowed('reset')) {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $creds = mp_read_credentials();

        // Do the match/hash work unconditionally (not short-circuited) so a
        // non-match takes roughly the same time as a match — timing-based
        // enumeration resistance.
        $matches = $creds
            && !empty($creds['email'])
            && !empty($identifier)
            && (hash_equals((string)$creds['email'], $identifier)
                || hash_equals((string)($creds['username'] ?? ''), $identifier));

        if ($matches) {
            $token = bin2hex(random_bytes(32));
            $creds['reset_token_hash'] = password_hash($token, PASSWORD_DEFAULT);
            $creds['reset_expires'] = time() + 1800; // 30 min, single-use
            mp_write_credentials($creds);

            // Built from the hardcoded APP_BASE_URL, never from
            // $_SERVER['HTTP_HOST'] — that header is attacker-controlled
            // on this very request and using it would let someone poison
            // the reset link sent to the real admin's inbox.
            $link = rtrim(APP_BASE_URL, '/') . '/reset-password.php?token=' . urlencode($token);
            $fromHost = parse_url(APP_BASE_URL, PHP_URL_HOST) ?: 'localhost';

            $body = "A password reset was requested for your Memeshift Player admin account.\n\n"
                  . "Reset your password (link valid for 30 minutes, single use):\n$link\n\n"
                  . "If you didn't request this, you can ignore this email — your password will not change.";
            @mail($creds['email'], 'Memeshift Player — password reset', $body, "From: no-reply@$fromHost");

            mp_log_event('reset_requested');
        } else {
            mp_log_event('reset_requested_nomatch');
        }

        mp_login_record_failure('reset'); // counts every request, matched or not — rate-limits this endpoint itself
    }

    // Constant-ish response time regardless of branch taken above.
    $elapsed = microtime(true) - $start;
    if ($elapsed < 0.3) {
        usleep((int)((0.3 - $elapsed) * 1_000_000));
    }
}

$csrf = mp_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Forgot Password — .+Memeshift+. Player</title>
<style><?php echo mp_admin_css(); ?></style>
</head>
<body>
<h1>Forgot Password</h1>
<p class="lede">Enter your admin username or email and we'll send a reset link.</p>

<?php echo mp_sr_status_html($submitted ? 'If that account exists, a reset link has been sent.' : ''); ?>

<?php if ($submitted): ?>
  <div class="msg msg-success">If that account exists, a reset link has been sent. Check your email.</div>
<?php else: ?>
<form method="post" action="forgot-password.php" novalidate>
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="field">
    <label for="identifier">Username or email</label>
    <input type="text" id="identifier" name="identifier" autocomplete="username" required autofocus>
  </div>
  <button type="submit">Send reset link</button>
</form>
<?php endif; ?>

<a class="secondary-action" href="login.php">Back to login</a>
</body>
</html>
