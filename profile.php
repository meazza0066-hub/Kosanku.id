<?php
require_once "auth.php";
require_once "koneksi.php";

$userId = (int) ($_SESSION['id_user'] ?? 0);
$statement = mysqli_prepare(
    $koneksi,
    "SELECT u.username, u.role, s.nama, s.no_hp, k.nomor_kamar
    FROM users u
    LEFT JOIN penyewa s ON s.id_user = u.id_user
    LEFT JOIN kamar k ON k.id_user = u.id_user
    WHERE u.id_user = ?
    LIMIT 1"
);
mysqli_stmt_bind_param($statement, "i", $userId);
mysqli_stmt_execute($statement);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

if (!$profile) {
    http_response_code(404);
    exit("Profil tidak ditemukan.");
}

$displayName = $profile['nama'] ?: $profile['username'];
$avatarLetter = strtoupper(substr($displayName, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil - KosanKu.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=20261027-profile">
</head>
<body class="profile-page">
    <div class="profile-shell">
        <aside class="profile-sidebar">
            <a class="profile-brand" href="home.php">
                <i class="fas fa-house" aria-hidden="true"></i>
                <span><strong>KosanKu.id</strong><small>Cari Kos Jadi Lebih Mudah</small></span>
            </a>
            <nav class="profile-side-nav" aria-label="Navigasi profil">
                <a class="profile-side-link" href="homeuser.php"><i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
                <a class="profile-side-link active" href="profile.php" aria-current="page"><i class="far fa-user" aria-hidden="true"></i> Profil</a>
                <a class="profile-side-link" href="pembayaran_user.php"><i class="far fa-credit-card" aria-hidden="true"></i> Pembayaran</a>
            </nav>
            <a class="profile-side-logout" href="logout.php"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Keluar</a>
        </aside>

        <main class="profile-main">
            <div class="profile-content">
                <header class="profile-page-heading">
                    <span>AKUN KOSANKU.ID</span>
                    <h1>Profil</h1>
                    <p>Informasi akun dan data diri Anda.</p>
                </header>

                <section class="profile-hero" aria-label="Ringkasan akun">
                    <span class="profile-hero-avatar" aria-hidden="true"><?php echo e($avatarLetter); ?></span>
                    <div class="profile-hero-identity">
                        <h2><?php echo e($displayName); ?></h2>
                        <span class="profile-role"><?php echo e(ucfirst($profile['role'])); ?></span>
                    </div>
                </section>

                <div class="profile-info-grid">
                    <section class="profile-info-panel" aria-labelledby="profile-info-heading">
                        <h2 id="profile-info-heading"><i class="far fa-id-card" aria-hidden="true"></i> Info Profil</h2>
                        <dl>
                            <div><dt>Nama</dt><dd><?php echo e($profile['nama'] ?: 'Belum diisi'); ?></dd></div>
                            <div><dt>Nama pengguna</dt><dd><?php echo e($profile['username']); ?></dd></div>
                            <div><dt>Nomor HP</dt><dd><?php echo e($profile['no_hp'] ?: 'Belum diisi'); ?></dd></div>
                            <div><dt>Kamar</dt><dd><?php echo !empty($profile['nomor_kamar']) ? 'Kamar ' . e($profile['nomor_kamar']) : 'Belum memiliki kamar'; ?></dd></div>
                        </dl>
                    </section>

                    <section class="profile-info-panel" aria-labelledby="account-info-heading">
                        <h2 id="account-info-heading"><i class="fas fa-lock" aria-hidden="true"></i> Info Akun</h2>
                        <dl>
                            <div><dt>ID Pengguna</dt><dd><?php echo (int) $userId; ?></dd></div>
                            <div><dt>Jenis akun</dt><dd><?php echo e(ucfirst($profile['role'])); ?></dd></div>
                        </dl>
                    </section>

                </div>

                <a class="profile-dashboard-link" href="home.php"><i class="fas fa-house" aria-hidden="true"></i> Kembali ke Beranda</a>
            </div>
        </main>
    </div>
</body>
</html>
