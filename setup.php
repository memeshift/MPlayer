<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  MPlayer — setup.php                                 │
 * │  One-time first-run page: creates the admin account  │
 * │  on a fresh install. Refuses to run once the         │
 * │  credentials file exists, or when SETUP_CODE is ''.  │
 * │  Delete this file after setup.                       │
 * └──────────────────────────────────────────────────────┘
 *
 * The setup code is what stops a passer-by claiming a freshly uploaded
 * site before its owner does. Set it in config.php before uploading,
 * give it to whoever is setting the site up, and clear it afterwards.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-style.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
if (!empty($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000');
}

/* ── Two hard gates, checked before anything else happens ── */

if (is_file(CREDENTIALS_FILE)) {
    http_response_code(403);
    exit('Setup has already been completed. Delete setup.php from the server.');
}

if (!defined('SETUP_CODE') || SETUP_CODE === '') {
    http_response_code(403);
    exit('Setup is disabled. Set SETUP_CODE in config.php to enable it.');
}

mp_session_start();

$error = '';
$done  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code    = (string) ($_POST['setup_code'] ?? '');
    $user    = trim((string) ($_POST['username'] ?? ''));
    $email   = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!mp_check_csrf()) {
        $error = 'Your session expired — please try again.';
    } elseif (!mp_login_attempt_allowed('setup')) {
        // Same file-based backoff the login form uses, so the setup code
        // can't be guessed by brute force.
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } elseif (!hash_equals(SETUP_CODE, $code)) {
        mp_login_record_failure('setup');
        mp_log_event('setup_bad_code');
        $error = 'That setup code is not correct.';
    } elseif ($user === '' || !preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $user)) {
        $error = 'Username must be 3–32 characters: letters, numbers, dot, dash or underscore.';
    } elseif ($email === false) {
        $error = 'Enter a valid email address — password resets are sent there.';
    } elseif (strlen($pass) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $written = mp_write_credentials([
            'username'        => $user,
            'password_hash'   => password_hash($pass, PASSWORD_DEFAULT),
            'email'           => $email,
            'session_version' => 0,
            'created_at'      => date('c'),
        ]);

        if (!$written) {
            // By far the most common failure on an unfamiliar host: the
            // credentials file lives one level above the web root, and PHP
            // has to be able to write there.
            $error = 'Could not write the credentials file to ' . dirname(CREDENTIALS_FILE)
                   . ' — that directory must be writable by PHP. Fix the permissions and try again.';
        } else {
            mp_login_record_success('setup');
            mp_log_event('setup_complete');
            $done = true;
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
<title>Set up — <?php echo htmlspecialchars(mp_site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style><?php echo mp_admin_css(); ?></style>
</head>
<body>
<h1>Set up your player</h1>

<?php if ($done): ?>

  <?php echo mp_sr_status_html('Account created.'); ?>
  <div class="msg msg-success">
    Your admin account is ready. Log in, then delete <code>setup.php</code> from the server
    and set <code>SETUP_CODE</code> back to <code>''</code> in <code>config.php</code>.
  </div>
  <a class="secondary-action" href="login.php">Go to login</a>

<?php else: ?>

  <p class="lede">This runs once, to create the admin account for this site. You'll need the
  setup code from whoever sent you the link.</p>

  <?php echo mp_sr_status_html($error); ?>
  <?php if ($error): ?>
    <div class="msg msg-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <form method="post" action="setup.php" novalidate>
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field">
      <label for="setup_code">Setup code</label>
      <input type="text" id="setup_code" name="setup_code" required autofocus autocomplete="off">
    </div>
    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autocomplete="username"
             value="<?php echo htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
      <p class="hint">3–32 characters: letters, numbers, dot, dash or underscore.</p>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email"
             value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
      <p class="hint">Where password-reset links are sent.</p>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="new-password">
      <p class="hint">At least 12 characters.</p>
    </div>
    <div class="field">
      <label for="password_confirm">Confirm password</label>
      <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>
    <button type="submit">Create admin account</button>
  </form>

<?php endif; ?>
</body>
</html>
