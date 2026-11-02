<?php
require_once "auth.php";
require_once "koneksi.php";

$username = (string) ($_SESSION['username'] ?? 'User');
$role = (string) ($_SESSION['role'] ?? 'user');
$isAdmin = $role === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $roomInput = $_POST['nomor_kamar'] ?? '';
    $contentInput = $_POST['isi'] ?? '';
    $room = is_string($roomInput) ? trim($roomInput) : '';
    $content = is_string($contentInput) ? trim($contentInput) : '';
    if ($room === '' || $content === '') {
        header("Location: laporan.php?error=invalid");
        exit;
    }
    $roomCheck = mysqli_prepare($koneksi, "SELECT id_kamar FROM kamar WHERE nomor_kamar = ? LIMIT 1");
    mysqli_stmt_bind_param($roomCheck, "s", $room);
    mysqli_stmt_execute($roomCheck);
    $roomExists = mysqli_stmt_get_result($roomCheck);
    mysqli_stmt_close($roomCheck);
    if (mysqli_num_rows($roomExists) === 0) {
        header("Location: laporan.php?error=invalid");
        exit;
    }

    $statement = mysqli_prepare($koneksi, "INSERT INTO laporan (nama_pelapor, nomor_kamar, isi) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($statement, "sss", $username, $room, $content);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    header("Location: laporan.php?success=1");
    exit;
}

if ($isAdmin) {
    $reports = mysqli_query($koneksi, "SELECT nama_pelapor, nomor_kamar, isi, created_at FROM laporan ORDER BY created_at DESC LIMIT 20");
} else {
    $statement = mysqli_prepare($koneksi, "SELECT nama_pelapor, nomor_kamar, isi, created_at FROM laporan WHERE nama_pelapor = ? ORDER BY created_at DESC LIMIT 20");
    mysqli_stmt_bind_param($statement, "s", $username);
    mysqli_stmt_execute($statement);
    $reports = mysqli_stmt_get_result($statement);
    mysqli_stmt_close($statement);
}
$rooms = mysqli_query($koneksi, "SELECT nomor_kamar FROM kamar ORDER BY nomor_kamar");

require_once "app_layout.php";
render_app_start('Laporan', 'laporan', strtoupper($role));
?>
<?php if ($isAdmin): ?>
    <div class="page-heading"><div><h1>Laporan Terbaru</h1></div></div>
<?php else: ?>
    <div class="page-heading"><div><h1>Laporan</h1><p>Sampaikan keluhan atau saran kepada pengelola</p></div></div>
    <?php if (isset($_GET['error'])): ?><p class="notice error" role="alert">Pilih kamar dan isi laporan sebelum mengirim.</p><?php endif; ?>
    <?php if (isset($_GET['success'])): ?><p class="notice" role="status">Laporan berhasil dikirim.</p><?php endif; ?>
    <section class="form-panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="nomor-kamar">Nomor Kamar <span aria-hidden="true">*</span></label>
                    <select id="nomor-kamar" name="nomor_kamar" required>
                        <option value="">Pilih kamar</option>
                        <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                            <option value="<?php echo e($room['nomor_kamar']); ?>">Kamar <?php echo e($room['nomor_kamar']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-field full-width">
                    <label for="isi-laporan">Keluhan atau saran <span aria-hidden="true">*</span></label>
                    <textarea id="isi-laporan" name="isi" placeholder="Jelaskan keluhan atau saran Anda secara detail di sini" required></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Kirim</button>
                <a class="btn btn-secondary" href="homeuser.php">Batal</a>
            </div>
        </form>
    </section>
    <h2 class="form-section-title">Laporan Saya</h2>
<?php endif; ?>
<section class="report-card">
    <?php if (mysqli_num_rows($reports) === 0): ?>
        <p class="empty-state">Belum ada laporan.</p>
    <?php else: ?>
        <?php while ($report = mysqli_fetch_assoc($reports)): ?>
            <article class="report-item">
                <div class="report-author">
                    <i class="far fa-circle-user"></i>
                    <div><?php echo e($report['nama_pelapor']); ?><small><?php echo e(date('d M Y', strtotime($report['created_at']))); ?></small></div>
                </div>
                <div class="report-description">
                    <strong>Kamar: <?php echo e($report['nomor_kamar']); ?></strong>
                    <p><?php echo nl2br(e($report['isi'])); ?></p>
                </div>
                <div class="report-photo" aria-label="Tidak ada foto terlampir">Foto</div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</section>
<?php render_app_end(); ?>
