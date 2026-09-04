<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — reset-password.php                │
 * │  Validates a reset token and sets a new password.     │
 * │  Single-use, 30-min expiry, bumps session_version to  │
 * │  invalidate every other active session on success.    │
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

// A short blocklist of trivially-guessed passwords — length + lockout do
// most of the real work here; this just catches the obvious cases without
// a live breached-password API dependency this app has never needed.
const COMMON_PASSWORDS = [
    'password123', '123456789012', 'qwertyuiopas', 'letmein12345',
    'password1234', 'admin12345678', 'welcome123456',
];

function mp_valid_token(?array $creds, string $token): bool {
    if (!$creds || empty($creds['reset_token_hash']) || empty($creds['reset_expires'])) return false;
    if ($creds['reset_expires'] < time()) return false;
    return password_verify($token, $creds['reset_token_hash']);
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$creds = mp_read_credentials();
$tokenValid = $token !== '' && mp_valid_token($creds, $token);

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    if (!mp_check_csrf()) {
        $error = 'Your session expired — please request a new reset link.';
        $tokenValid = false;
    } else {
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password_confirm'] ?? '');

        if ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } elseif (strlen($pw) < 12) {
            $error = 'Password must be at least 12 characters.';
        } elseif (in_array(strtolower($pw), COMMON_PASSWORDS, true)) {
            $error = 'That password is too common — please choose another.';
        } else {
            $creds['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            $creds['session_version'] = ($creds['session_version'] ?? 0) + 1; // logs out every other session
            unset($creds['reset_token_hash'], $creds['reset_expires']);       // single-use
            mp_write_credentials($creds);

            if (!empty($creds['email'])) {
                $body = "Your Memeshift Player admin password was just changed.\n\n"
                      . "If this wasn't you, someone may have access to your reset email — "
                      . "check your account immediately.";
                @mail($creds['email'], 'Memeshift Player — password changed', $body);
            }

            mp_log_event('reset_completed');
            $success = true;
        }
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
<title>Reset Password — .+Memeshift+. Player</title>
<style><?php echo mp_admin_css(); ?></style>
</head>
<body>
<h1>Reset Password</h1>

<?php echo mp_sr_status_html($error); ?>

<?php if ($success): ?>
  <div class="msg msg-success">Your password has been changed. Any other signed-in session has been logged out.</div>
  <a class="btn" href="login.php">Go to login</a>

<?php elseif (!$tokenValid): ?>
  <div class="msg msg-error">This link is invalid or has expired.</div>
  <a class="secondary-action" href="forgot-password.php">Request a new reset link</a>

<?php else: ?>
  <p class="lede">Choose a new password (at least 12 characters).</p>
  <?php if ($error): ?>
    <div class="msg msg-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>
  <form method="post" action="reset-password.php" novalidate>
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field">
      <label for="password">New password</label>
      <input type="password" id="password" name="password" autocomplete="new-password" minlength="12" required autofocus>
      <p class="hint">At least 12 characters.</p>
    </div>
    <div class="field">
      <label for="password_confirm">Confirm new password</label>
      <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="12" required>
    </div>
    <button type="submit">Set new password</button>
  </form>
<?php endif; ?>
</body>
</html>
