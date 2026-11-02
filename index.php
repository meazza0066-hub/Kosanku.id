<?php
session_start();
$roomId = filter_input(INPUT_GET, 'pesan', FILTER_VALIDATE_INT);
$selectedRoomId = $roomId !== false && $roomId !== null && $roomId > 0 ? $roomId : 0;
if (($_SESSION['login'] ?? false) === true) {
    if ($selectedRoomId > 0 && ($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: pembayaran.php?pesan=" . $selectedRoomId);
        exit;
    }
    header("Location: home.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KosanKu.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=20261022-6">
</head>

<body class="login-page">

    <div class="login-container">
        <div class="logo-container">
            <i class="fas fa-home logo-icon"></i>
            <h1 class="logo-text">KosanKu<span>.id</span></h1>
            <p class="logo-tagline">Cari Kos jadi lebih mudah</p>
        </div>

        <h2 class="login-title">Login</h2>
        <p class="login-subtitle">Silakan masuk ke akun KosanKu.id Anda</p>

        <?php if (isset($_GET['error'])): ?>
            <p class="login-error" role="alert">Username atau password salah.</p>
        <?php endif; ?>

        <form action="proses_login.php" method="POST">
            <?php if ($selectedRoomId > 0): ?>
                <input type="hidden" name="pesan" value="<?php echo (int) $selectedRoomId; ?>">
            <?php endif; ?>
            <div class="input-group">
                <i class="far fa-user left-icon"></i>
                <input type="text" name="username" placeholder="Username" autocomplete="username" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock left-icon"></i>
                <input type="password" name="password" id="passwordInput" placeholder="Password" autocomplete="current-password" required>
                <i class="far fa-eye right-icon" id="togglePassword"></i>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <p class="account-prompt">Belum punya akun? <a href="daftar.php">Daftar sekarang</a></p>
        <a href="home.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
        </a>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#passwordInput');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    </script>

</body>

</html>