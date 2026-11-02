<?php
require_once "auth.php";
require_once "koneksi.php";
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $namaInput = $_POST['nama'] ?? '';
        $noHpInput = $_POST['no_hp'] ?? '';
        $nama = is_string($namaInput) ? trim($namaInput) : '';
        $noHp = is_string($noHpInput) ? trim($noHpInput) : '';
        if ($nama === '') {
            header("Location: penyewa.php?error=invalid");
            exit;
        }
        $statement = mysqli_prepare($koneksi, "INSERT INTO penyewa (nama, no_hp) VALUES (?, ?)");
        mysqli_stmt_bind_param($statement, "ss", $nama, $noHp);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id_penyewa', FILTER_VALIDATE_INT);
        if (!$id) {
            header("Location: penyewa.php?error=invalid");
            exit;
        }
        mysqli_begin_transaction($koneksi);
        try {
            $tenantStatement = mysqli_prepare($koneksi, "SELECT id_penyewa FROM penyewa WHERE id_penyewa = ? FOR UPDATE");
            mysqli_stmt_bind_param($tenantStatement, "i", $id);
            mysqli_stmt_execute($tenantStatement);
            $tenant = mysqli_fetch_assoc(mysqli_stmt_get_result($tenantStatement));
            mysqli_stmt_close($tenantStatement);
            if (!$tenant) {
                mysqli_rollback($koneksi);
                header("Location: penyewa.php?error=invalid");
                exit;
            }

            $paymentStatement = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total FROM pembayaran WHERE id_penyewa = ?");
            mysqli_stmt_bind_param($paymentStatement, "i", $id);
            mysqli_stmt_execute($paymentStatement);
            $hasPayments = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($paymentStatement))['total'] > 0;
            mysqli_stmt_close($paymentStatement);
            if ($hasPayments) {
                mysqli_rollback($koneksi);
                header("Location: penyewa.php?error=has_payments");
                exit;
            }

            $statement = mysqli_prepare($koneksi, "DELETE FROM penyewa WHERE id_penyewa = ?");
            mysqli_stmt_bind_param($statement, "i", $id);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            mysqli_commit($koneksi);
        } catch (mysqli_sql_exception $error) {
            mysqli_rollback($koneksi);
            throw $error;
        }
    } else {
        http_response_code(400);
        exit("Aksi penyewa tidak valid.");
    }
    header("Location: penyewa.php?success=1");
    exit;
}

$tenants = mysqli_query($koneksi, "SELECT s.id_penyewa, s.nama, s.no_hp, (SELECT COUNT(*) FROM pembayaran p WHERE p.id_penyewa = s.id_penyewa) AS jumlah_pembayaran FROM penyewa s ORDER BY s.nama");
require_once "app_layout.php";

render_app_start('Data Penghuni', 'penyewa', strtoupper((string) ($_SESSION['role'] ?? 'user')));
?>
<div class="page-heading">
    <div><h1>Data Penghuni</h1><p>Kelola informasi penghuni kos</p></div>
    <a class="btn" href="#tambah-penghuni"><i class="fas fa-plus"></i> Tambah Penghuni</a>
</div>
<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?><p class="notice error" role="alert">Nama penyewa wajib diisi atau data penghuni tidak valid.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'has_payments'): ?><p class="notice error" role="alert">Penghuni tidak dapat dihapus karena masih memiliki riwayat pembayaran.</p><?php endif; ?>
<?php if (isset($_GET['success'])): ?><p class="notice" role="status">Perubahan data penyewa berhasil disimpan.</p><?php endif; ?>
<section class="form-panel" id="tambah-penghuni">
    <h2 style="margin:0 0 18px;font-size:20px">Tambah Penghuni</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="form-field"><label for="nama">Nama Penghuni <span aria-hidden="true">*</span></label><input id="nama" name="nama" maxlength="150" placeholder="Nama lengkap" required></div>
            <div class="form-field"><label for="no-hp">Nomor Telepon</label><input id="no-hp" name="no_hp" maxlength="50" placeholder="08xxxxxxxxxx"></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Simpan</button></div>
    </form>
</section>
<h2 class="form-section-title">Daftar Penghuni</h2>
<section class="table-panel">
    <table class="data-table">
        <thead><tr><th>No</th><th>Nama Penghuni</th><th>Nomor Telepon</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($tenants) === 0): ?>
            <tr><td colspan="4" class="empty-state">Belum ada data penghuni.</td></tr>
        <?php else: ?>
            <?php $index = 1; while ($tenant = mysqli_fetch_assoc($tenants)): ?>
                <tr>
                    <td><?php echo $index++; ?></td>
                    <td><?php echo e($tenant['nama']); ?></td>
                    <td><?php echo e($tenant['no_hp']); ?></td>
                    <td>
                        <?php if ((int) $tenant['jumlah_pembayaran'] > 0): ?>
                            <span class="muted-text">Tidak dapat dihapus: memiliki riwayat pembayaran</span>
                        <?php else: ?>
                            <form method="post" onsubmit="return confirm('Hapus penghuni ini?')">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_penyewa" value="<?php echo (int) $tenant['id_penyewa']; ?>">
                                <button class="action-button delete" type="submit" aria-label="Hapus penghuni <?php echo e($tenant['nama']); ?>"><i class="far fa-trash-alt"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php render_app_end(); ?>
