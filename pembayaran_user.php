<?php
require_once "auth.php";
require_once "koneksi.php";

if (($_SESSION['role'] ?? '') === 'admin') {
    header("Location: pembayaran.php");
    exit;
}

$userId = (int) ($_SESSION['id_user'] ?? 0);
$summaryStatement = mysqli_prepare(
    $koneksi,
    "SELECT COUNT(*) AS total,
        COALESCE(SUM(p.status = 'Belum dibayar'), 0) AS belum_dibayar,
        COALESCE(SUM(p.status = 'Lunas'), 0) AS lunas
    FROM pembayaran p
    INNER JOIN penyewa s ON s.id_penyewa = p.id_penyewa
    WHERE s.id_user = ?"
);
mysqli_stmt_bind_param($summaryStatement, "i", $userId);
mysqli_stmt_execute($summaryStatement);
$paymentSummary = mysqli_fetch_assoc(mysqli_stmt_get_result($summaryStatement));
mysqli_stmt_close($summaryStatement);

$paymentStatement = mysqli_prepare(
    $koneksi,
    "SELECT p.jumlah, p.periode, p.tanggal_bayar, p.status, p.metode, k.nomor_kamar
    FROM pembayaran p
    INNER JOIN penyewa s ON s.id_penyewa = p.id_penyewa
    LEFT JOIN kamar k ON k.id_user = s.id_user
    WHERE s.id_user = ?
    ORDER BY p.tanggal_bayar DESC, p.id_pembayaran DESC"
);
mysqli_stmt_bind_param($paymentStatement, "i", $userId);
mysqli_stmt_execute($paymentStatement);
$payments = mysqli_stmt_get_result($paymentStatement);
mysqli_stmt_close($paymentStatement);

$methodNames = [
    'transfer_bank' => 'Transfer Bank',
    'ewallet' => 'E-Wallet',
    'qris' => 'QRIS',
];
$successMessages = [
    'booking' => 'Kamar berhasil dipesan. Tagihan Anda sudah dibuat dan menunggu konfirmasi admin.',
];
$successKey = $_GET['success'] ?? '';
$successMessage = is_string($successKey) ? ($successMessages[$successKey] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran - KosanKu.id</title>
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
            <nav class="profile-side-nav" aria-label="Navigasi akun">
                <a class="profile-side-link" href="homeuser.php"><i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
                <a class="profile-side-link" href="profile.php"><i class="far fa-user" aria-hidden="true"></i> Profil</a>
                <a class="profile-side-link active" href="pembayaran_user.php" aria-current="page"><i class="far fa-credit-card" aria-hidden="true"></i> Pembayaran</a>
            </nav>
            <a class="profile-side-logout" href="logout.php"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Keluar</a>
        </aside>

        <main class="profile-main">
            <div class="profile-content">
                <header class="profile-page-heading">
                    <span>AKUN KOSANKU.ID</span>
                    <h1>Pembayaran</h1>
                    <p>Ringkasan tagihan dan riwayat pembayaran Anda.</p>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="notice" role="status"><?php echo e($successMessage); ?></p>
                <?php endif; ?>

                <section class="profile-info-panel payment-overview-panel" aria-labelledby="payment-overview-heading">
                    <h2 id="payment-overview-heading"><i class="far fa-credit-card" aria-hidden="true"></i> Info Pembayaran</h2>
                    <dl>
                        <div><dt>Total tagihan</dt><dd><?php echo (int) $paymentSummary['total']; ?></dd></div>
                        <div><dt>Belum dibayar</dt><dd><?php echo (int) $paymentSummary['belum_dibayar']; ?></dd></div>
                        <div><dt>Lunas</dt><dd><?php echo (int) $paymentSummary['lunas']; ?></dd></div>
                    </dl>
                    <a class="payment-history-link" href="#riwayat-pembayaran">Lihat riwayat pembayaran <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </section>

                <section class="payment-history-section" id="riwayat-pembayaran" aria-labelledby="payment-history-heading">
                    <div class="payment-history-heading">
                        <div>
                            <span>RINCIAN TAGIHAN</span>
                            <h2 id="payment-history-heading">Riwayat Pembayaran</h2>
                        </div>
                    </div>
                    <?php if (mysqli_num_rows($payments) === 0): ?>
                        <p class="payment-history-empty">Belum ada riwayat pembayaran. Tagihan akan muncul setelah Anda memesan kamar.</p>
                    <?php else: ?>
                        <div class="payment-history-list">
                            <?php while ($payment = mysqli_fetch_assoc($payments)): ?>
                                <article class="payment-history-item">
                                    <span class="payment-history-icon"><i class="far fa-file-lines" aria-hidden="true"></i></span>
                                    <div class="payment-history-main">
                                        <h3><?php echo e($payment['periode']); ?></h3>
                                        <p>Kamar <?php echo e($payment['nomor_kamar'] ?? '-'); ?> · <?php echo e($methodNames[$payment['metode']] ?? 'Metode belum dipilih'); ?></p>
                                    </div>
                                    <div class="payment-history-amount">
                                        <strong>Rp <?php echo number_format((float) $payment['jumlah'], 0, ',', '.'); ?></strong>
                                        <span class="status <?php echo $payment['status'] === 'Lunas' ? 'status-paid' : 'status-unpaid'; ?>"><?php echo e($payment['status']); ?></span>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
