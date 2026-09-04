<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — login.php                        │
 * │  Admin login. GET renders the form, POST verifies.   │
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

if (mp_is_logged_in()) {
    header('Location: upload.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mp_check_csrf()) {
        $error = 'Your session expired — please try again.';
    } elseif (!mp_login_attempt_allowed('login')) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $creds = mp_read_credentials();
        $u = (string)($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');
        $ok = $creds
            && isset($creds['username'], $creds['password_hash'])
            && hash_equals((string)$creds['username'], $u)
            && password_verify($p, (string)$creds['password_hash']);

        if ($ok) {
            mp_login_record_success('login');

            // Self-upgrading hash: if the stored hash uses an older
            // algorithm/cost, re-hash and save now that we have the
            // plaintext in hand.
            if (password_needs_rehash($creds['password_hash'], PASSWORD_DEFAULT)) {
                $creds['password_hash'] = password_hash($p, PASSWORD_DEFAULT);
                mp_write_credentials($creds);
            }

            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['expires'] = time() + 14400;
            $_SESSION['session_version'] = $creds['session_version'] ?? 0;
            mp_log_event('login_success');
            header('Location: upload.php');
            exit;
        } else {
            mp_login_record_failure('login');
            mp_log_event('login_fail');
            $error = 'Invalid username or password.';
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
<title>Admin Login — .+Memeshift+. Player</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style><?php echo mp_admin_css(); ?></style>
</head>
<body>
<h1>Admin Login</h1>
<p class="lede">Sign in to upload and manage tracks.</p>

<?php echo mp_sr_status_html($error); ?>
<?php if ($error): ?>
  <div class="msg msg-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="post" action="login.php" novalidate>
  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="field">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
  </div>
  <button type="submit">Log in</button>
</form>

<a class="secondary-action" href="forgot-password.php">Forgot password?</a>
</body>
</html>
