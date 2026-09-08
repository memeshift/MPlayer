<?php
/**
 * ┌──────────────────────────────────────────────────────┐
 * │  Memeshift Player — customize_check.php               │
 * │  Tiny auth-check endpoint for index.html's Customize   │
 * │  mode: tells the page's JS whether the current visitor │
 * │  has an admin session, without exposing anything else. │
 * └──────────────────────────────────────────────────────┘
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$loggedIn = mp_is_logged_in();
echo json_encode([
    'loggedIn' => $loggedIn,
    'csrf'     => $loggedIn ? mp_csrf_token() : null,
]);
