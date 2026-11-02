<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SESSION['login'] ?? false) !== true) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verify_csrf_token()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit("Permintaan tidak valid. Muat ulang halaman lalu coba lagi.");
    }
}

function require_admin()
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit("Akses ditolak. Halaman ini hanya dapat dibuka oleh admin.");
    }
}
