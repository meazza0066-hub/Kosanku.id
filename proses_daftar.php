<?php
session_start();
require_once "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: daftar.php");
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit("Permintaan tidak valid. Muat ulang halaman lalu coba lagi.");
}

$nameInput = $_POST['nama'] ?? '';
$phoneInput = $_POST['no_hp'] ?? '';
$usernameInput = $_POST['username'] ?? '';
$passwordInput = $_POST['password'] ?? '';
$confirmationInput = $_POST['password_confirmation'] ?? '';
if (!is_string($nameInput) || !is_string($phoneInput) || !is_string($usernameInput) || !is_string($passwordInput) || !is_string($confirmationInput)) {
    header("Location: daftar.php?error=invalid");
    exit;
}

$name = trim($nameInput);
$phone = trim($phoneInput);
$username = trim($usernameInput);
$password = $passwordInput;
if (
    $name === ''
    || strlen($name) > 150
    || strlen($phone) > 50
    || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)
    || strlen($password) < 8
    || $password !== $confirmationInput
) {
    header("Location: daftar.php?error=" . ($password !== $confirmationInput ? 'password' : 'invalid'));
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
try {
    mysqli_begin_transaction($koneksi);
    $userStatement = mysqli_prepare($koneksi, "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
    mysqli_stmt_bind_param($userStatement, "ss", $username, $passwordHash);
    mysqli_stmt_execute($userStatement);
    $userId = mysqli_insert_id($koneksi);
    mysqli_stmt_close($userStatement);

    $tenantStatement = mysqli_prepare($koneksi, "INSERT INTO penyewa (nama, no_hp, id_user) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($tenantStatement, "ssi", $name, $phone, $userId);
    mysqli_stmt_execute($tenantStatement);
    mysqli_stmt_close($tenantStatement);
    mysqli_commit($koneksi);
} catch (mysqli_sql_exception $error) {
    mysqli_rollback($koneksi);
    if ((int) $error->getCode() === 1062) {
        header("Location: daftar.php?error=username");
        exit;
    }
    throw $error;
}

session_regenerate_id(true);
$_SESSION['login'] = true;
$_SESSION['username'] = $username;
$_SESSION['role'] = 'user';
$_SESSION['id_user'] = $userId;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
header("Location: home.php");
exit;
?>
