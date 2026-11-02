<?php
session_start();

include "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$roomId = filter_input(INPUT_POST, 'pesan', FILTER_VALIDATE_INT);
$selectedRoomId = $roomId !== false && $roomId !== null && $roomId > 0 ? $roomId : 0;
$loginErrorUrl = 'Location: login.php?error=1' . ($selectedRoomId > 0 ? '&pesan=' . $selectedRoomId : '');
$usernameInput = $_POST['username'] ?? '';
$passwordInput = $_POST['password'] ?? '';
if (!is_string($usernameInput) || !is_string($passwordInput)) {
    header($loginErrorUrl);
    exit;
}
$username = trim($usernameInput);
$password = $passwordInput;
$statement = mysqli_prepare($koneksi, "SELECT id_user, username, password, role FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($statement, "s", $username);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($statement);

$validPassword = false;
if ($data) {
    $validPassword = password_verify($password, $data['password'])
        || hash_equals($data['password'], md5($password));
}

if (!$validPassword) {
    header($loginErrorUrl);
    exit;
}

if (preg_match('/^[a-f0-9]{32}$/i', $data['password'])) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id_user = ?");
    mysqli_stmt_bind_param($update, "si", $newHash, $data['id_user']);
    mysqli_stmt_execute($update);
    mysqli_stmt_close($update);
}

session_regenerate_id(true);
$_SESSION['login'] = true;
$_SESSION['username'] = $data['username'];
$_SESSION['role'] = $data['role'] ?: 'user';
$_SESSION['id_user'] = (int) $data['id_user'];
if ($selectedRoomId > 0 && $_SESSION['role'] !== 'admin') {
    header("Location: pembayaran.php?pesan=" . $selectedRoomId);
    exit;
}
header("Location: home.php");
exit;
