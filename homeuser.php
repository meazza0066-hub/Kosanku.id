<?php
require_once "auth.php";
require_once "koneksi.php";
require_once "app_layout.php";

$username = (string) ($_SESSION['username'] ?? 'User');
$userId = (int) ($_SESSION['id_user'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$role = strtoupper((string) ($_SESSION['role'] ?? 'user'));

$totalKamar = (int) mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kamar"))['total'];
$kamarTersedia = (int) mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kamar WHERE status = 'Tersedia'"))['total'];
$rooms = mysqli_query($koneksi, "SELECT nomor_kamar, status FROM kamar ORDER BY nomor_kamar LIMIT 5");

if ($isAdmin) {
    $displayName = ucfirst($username);
    $totalPenyewa = (int) mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM penyewa"))['total'];
    $totalPembayaran = (int) mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pembayaran"))['total'];
} else {
    $tenantStatement = mysqli_prepare($koneksi, "SELECT nama FROM penyewa WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($tenantStatement, "i", $userId);
    mysqli_stmt_execute($tenantStatement);
    $tenant = mysqli_fetch_assoc(mysqli_stmt_get_result($tenantStatement));
    mysqli_stmt_close($tenantStatement);
    $displayName = $tenant['nama'] ?? $username;

    $roomStatement = mysqli_prepare($koneksi, "SELECT nomor_kamar FROM kamar WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($roomStatement, "i", $userId);
    mysqli_stmt_execute($roomStatement);
    $userRoom = mysqli_fetch_assoc(mysqli_stmt_get_result($roomStatement));
    mysqli_stmt_close($roomStatement);

    $reportStatement = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total FROM laporan WHERE nama_pelapor = ?");
    mysqli_stmt_bind_param($reportStatement, "s", $username);
    mysqli_stmt_execute($reportStatement);
    $totalLaporan = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($reportStatement))['total'];
    mysqli_stmt_close($reportStatement);
}

render_app_start('Dashboard', 'dashboard', $role);
?>
<div class="dashboard-page">
    <div class="page-heading dashboard-heading">
        <div>
            <span class="dashboard-eyebrow"><?php echo $isAdmin ? 'RINGKASAN KOS' : 'AREA PENGHUNI'; ?></span>
            <h1><?php echo $isAdmin ? 'Dashboard' : 'Beranda'; ?></h1>
            <p><?php echo $isAdmin ? 'Selamat datang, ' : 'Selamat datang di KosanKu.id, '; ?><?php echo e($displayName); ?>!</p>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <section class="stats-grid" aria-label="Ringkasan kos">
            <article class="stat-card"><span class="stat-icon"><i class="fas fa-bed" aria-hidden="true"></i></span><span>Total Kamar</span><strong><?php echo $totalKamar; ?></strong></article>
            <article class="stat-card"><span class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></span><span>Penghuni</span><strong><?php echo $totalPenyewa; ?></strong></article>
            <article class="stat-card"><span class="stat-icon"><i class="far fa-credit-card" aria-hidden="true"></i></span><span>Pembayaran</span><strong><?php echo $totalPembayaran; ?></strong></article>
            <article class="stat-card"><span class="stat-icon"><i class="far fa-house" aria-hidden="true"></i></span><span>Kos Tersedia</span><strong><?php echo $kamarTersedia; ?></strong></article>
        </section>
    <?php else: ?>
        <section class="stats-grid resident-stats-grid" aria-label="Ringkasan akun Anda">
            <article class="stat-card"><span class="stat-icon"><i class="fas fa-bed" aria-hidden="true"></i></span><span>Kamar Saya</span><strong><?php echo e($userRoom['nomor_kamar'] ?? 'Belum'); ?></strong></article>
            <article class="stat-card"><span class="stat-icon"><i class="far fa-house" aria-hidden="true"></i></span><span>Kamar Tersedia</span><strong><?php echo $kamarTersedia; ?></strong></article>
            <article class="stat-card"><span class="stat-icon"><i class="far fa-file-lines" aria-hidden="true"></i></span><span>Laporan Saya</span><strong><?php echo $totalLaporan; ?></strong></article>
        </section>
    <?php endif; ?>

    <section class="table-panel dashboard-room-panel">
        <div class="dashboard-section-heading">
            <div>
                <h2><?php echo $isAdmin ? 'Kondisi Kamar' : 'Daftar Kamar'; ?></h2>
                <p><?php echo $isAdmin ? 'Ringkasan status kamar terbaru.' : 'Lihat status kamar yang tersedia di kos.'; ?></p>
            </div>
            <?php if ($isAdmin): ?>
                <a class="btn btn-outline" href="kamar.php">Lihat Semua <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>No</th><th>Nomor Kamar</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($rooms) === 0): ?>
                    <tr><td colspan="3" class="empty-state">Belum ada data kamar.</td></tr>
                <?php else: ?>
                    <?php $index = 1; while ($room = mysqli_fetch_assoc($rooms)): ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <td><?php echo e($room['nomor_kamar']); ?></td>
                            <td><span class="status <?php echo $room['status'] === 'Tersedia' ? 'status-available' : 'status-occupied'; ?>"><?php echo e($room['status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php render_app_end(); ?>
