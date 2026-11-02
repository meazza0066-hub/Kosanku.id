<?php
session_start();
if (($_SESSION['login'] ?? false) === true) {
    header("Location: home.php");
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessages = [
    'invalid' => 'Periksa kembali data pendaftaran Anda.',
    'username' => 'Username sudah digunakan. Silakan pilih username lain.',
    'password' => 'Password dan konfirmasi password tidak sama.',
];
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun - KosanKu.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=20261022">
</head>
<body class="login-page">
    <main class="login-container register-container">
        <div class="logo-container">
            <i class="fas fa-house logo-icon"></i>
            <h1 class="logo-text">KosanKu<span>.id</span></h1>
            <p class="logo-tagline">Cari Kos jadi lebih mudah</p>
        </div>
        <h2 class="login-title">Buat Akun User</h2>
        <p class="login-subtitle">Daftar untuk melihat informasi kos Anda</p>
        <?php if (isset($errorMessages[$error])): ?>
            <p class="login-error" role="alert"><?php echo htmlspecialchars($errorMessages[$error], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form action="proses_daftar.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="input-group">
                <i class="far fa-user left-icon"></i>
                <input type="text" name="nama" placeholder="Nama lengkap" maxlength="150" autocomplete="name" required>
            </div>
            <div class="input-group">
                <i class="fas fa-phone left-icon"></i>
                <input type="tel" name="no_hp" placeholder="Nomor telepon (opsional)" maxlength="50" autocomplete="tel">
            </div>
            <div class="input-group">
                <i class="far fa-user left-icon"></i>
                <input type="text" name="username" placeholder="Username" minlength="3" maxlength="100" autocomplete="username" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" name="password" placeholder="Password (minimal 8 karakter)" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" name="password_confirmation" placeholder="Ulangi password" minlength="8" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn-login">Daftar</button>
        </form>
        <p class="account-prompt">Sudah punya akun? <a href="login.php">Login</a></p>
        <a href="home.php" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
    </main>
</body>
</html>
