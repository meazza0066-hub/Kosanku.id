<?php
require_once "auth.php";
require_once "koneksi.php";
require_once "room_facilities.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $nomorInput = $_POST['nomor_kamar'] ?? '';
        $nomor = is_string($nomorInput) ? trim($nomorInput) : '';
        $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
        $status = $_POST['status'] ?? '';
        $facilityInput = $_POST['fasilitas'] ?? [];
        $facilityOptions = room_facility_options();
        $invalidFacilities = !is_array($facilityInput);
        if (is_array($facilityInput)) {
            foreach ($facilityInput as $facilityKey) {
                if (!is_string($facilityKey) || !isset($facilityOptions[$facilityKey])) {
                    $invalidFacilities = true;
                    break;
                }
            }
        }
        $facilities = $invalidFacilities ? '' : implode(',', room_facility_keys($facilityInput));
        if ($nomor === '' || $harga === false || $harga === null || $harga < 0 || !in_array($status, ['Tersedia', 'Terisi'], true) || $invalidFacilities) {
            header("Location: kamar.php?error=invalid");
            exit;
        }
        if ($facilities === '') {
            $facilities = implode(',', room_default_facilities_for_price($harga));
        }
        $check = mysqli_prepare($koneksi, "SELECT id_kamar FROM kamar WHERE nomor_kamar = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $nomor);
        mysqli_stmt_execute($check);
        $exists = mysqli_stmt_get_result($check);
        mysqli_stmt_close($check);
        if (mysqli_num_rows($exists) > 0) {
            header("Location: kamar.php?error=duplicate");
            exit;
        }
        $statement = mysqli_prepare($koneksi, "INSERT INTO kamar (nomor_kamar, status, harga, fasilitas) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($statement, "ssds", $nomor, $status, $harga, $facilities);
        mysqli_stmt_execute($statement);
        if (mysqli_stmt_affected_rows($statement) !== 1) {
            mysqli_stmt_close($statement);
            throw new RuntimeException("Data kamar tidak berhasil disimpan.");
        }
        mysqli_stmt_close($statement);
        header("Location: kamar.php?success=added");
        exit;
    } elseif ($action === 'update_room') {
        $id = filter_input(INPUT_POST, 'id_kamar', FILTER_VALIDATE_INT);
        $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
        $status = $_POST['status'] ?? '';
        $facilityInput = $_POST['fasilitas'] ?? [];
        $facilityOptions = room_facility_options();
        $invalidFacilities = !is_array($facilityInput);
        if (is_array($facilityInput)) {
            foreach ($facilityInput as $facilityKey) {
                if (!is_string($facilityKey) || !isset($facilityOptions[$facilityKey])) {
                    $invalidFacilities = true;
                    break;
                }
            }
        }
        $facilities = $invalidFacilities ? '' : implode(',', room_facility_keys($facilityInput));
        if (!$id || $harga === false || $harga === null || $harga < 0 || !in_array($status, ['Tersedia', 'Terisi'], true) || $invalidFacilities) {
            header("Location: kamar.php?error=invalid");
            exit;
        }
        $statement = mysqli_prepare($koneksi, "UPDATE kamar SET harga = ?, fasilitas = ?, status = ?, id_user = IF(? = 'Tersedia', NULL, id_user) WHERE id_kamar = ?");
        mysqli_stmt_bind_param($statement, "dsssi", $harga, $facilities, $status, $status, $id);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        header("Location: kamar.php?success=updated");
        exit;
    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id_kamar', FILTER_VALIDATE_INT);
        if (!$id) {
            header("Location: kamar.php?error=invalid");
            exit;
        }
        $statement = mysqli_prepare($koneksi, "DELETE FROM kamar WHERE id_kamar = ? AND id_user IS NULL");
        mysqli_stmt_bind_param($statement, "i", $id);
        mysqli_stmt_execute($statement);
        if (mysqli_stmt_affected_rows($statement) === 0) {
            mysqli_stmt_close($statement);
            header("Location: kamar.php?error=occupied");
            exit;
        }
        mysqli_stmt_close($statement);
        header("Location: kamar.php?success=deleted");
        exit;
    } else {
        http_response_code(400);
        exit("Aksi kamar tidak valid.");
    }
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$userId = (int) ($_SESSION['id_user'] ?? 0);
$userHasRoom = false;
if ($isAdmin) {
    $rooms = mysqli_query($koneksi, "SELECT id_kamar, nomor_kamar, status, harga, fasilitas, id_user FROM kamar ORDER BY nomor_kamar");
} else {
    $occupancyStatement = mysqli_prepare($koneksi, "SELECT id_kamar FROM kamar WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($occupancyStatement, "i", $userId);
    mysqli_stmt_execute($occupancyStatement);
    $userHasRoom = mysqli_fetch_assoc(mysqli_stmt_get_result($occupancyStatement)) !== null;
    mysqli_stmt_close($occupancyStatement);

    $roomsStatement = mysqli_prepare($koneksi, "SELECT id_kamar, nomor_kamar, status, harga, fasilitas, id_user FROM kamar WHERE id_user = ? OR (status = 'Tersedia' AND id_user IS NULL AND ? = 0) ORDER BY nomor_kamar");
    mysqli_stmt_bind_param($roomsStatement, "ii", $userId, $userHasRoom);
    mysqli_stmt_execute($roomsStatement);
    $rooms = mysqli_stmt_get_result($roomsStatement);
    mysqli_stmt_close($roomsStatement);
}
require_once "app_layout.php";

render_app_start('Data Kamar', 'kamar', strtoupper((string) ($_SESSION['role'] ?? 'user')));
?>
<div class="page-heading">
    <div><h1>Data Kamar</h1><p><?php echo $isAdmin ? 'Kelola data kamar kos' : 'Lihat status kamar di KosanKu.id'; ?></p></div>
    <?php if ($isAdmin): ?><a class="btn" href="#tambah-kamar"><i class="fas fa-plus"></i> Tambah Kamar</a><?php endif; ?>
</div>
<?php if (isset($_GET['error']) && in_array($_GET['error'], ['invalid', 'duplicate'], true)): ?><p class="notice error" role="alert">Data kamar tidak valid atau nomor kamar sudah terdaftar.</p><?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] === 'added'): ?><p class="notice" role="status">Kamar berhasil ditambahkan ke database dan daftar kamar telah diperbarui.</p><?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?><p class="notice" role="status">Data kamar berhasil diperbarui. Jika status diubah menjadi Tersedia, penghuni akan dilepaskan dari kamar.</p><?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?><p class="notice" role="status">Kamar berhasil dihapus.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'occupied'): ?><p class="notice error" role="alert">Kamar yang sedang dihuni tidak dapat dihapus.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'already_booked'): ?><p class="notice error" role="alert">Akun Anda sudah memiliki kamar. Hubungi pengelola jika perlu pindah kamar.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'profile'): ?><p class="notice error" role="alert">Profil penghuni belum terhubung. Hubungi pengelola kos.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'unavailable'): ?><p class="notice error" role="alert">Kamar tersebut sudah tidak tersedia.</p><?php endif; ?>
<?php if ($isAdmin): ?>
<section class="form-panel" id="tambah-kamar">
    <h2 style="margin:0 0 18px;font-size:20px">Tambah Data Kamar</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="form-field"><label for="nomor-kamar">Nomor Kamar <span aria-hidden="true">*</span></label><input id="nomor-kamar" name="nomor_kamar" maxlength="50" placeholder="Contoh: A1" required></div>
            <div class="form-field"><label for="harga-kamar">Harga Sewa per Bulan (Rp) <span aria-hidden="true">*</span></label><input id="harga-kamar" name="harga" type="number" min="0" step="1000" placeholder="Contoh: 750000" required></div>
            <div class="form-field"><label for="status-kamar">Status <span aria-hidden="true">*</span></label><select id="status-kamar" name="status"><option>Tersedia</option><option>Terisi</option></select></div>
            <fieldset class="facility-editor">
                <legend>Fasilitas kamar</legend>
                <?php foreach (room_facility_options() as $facilityKey => $facility): ?>
                    <label><input type="checkbox" name="fasilitas[]" value="<?php echo e($facilityKey); ?>"> <?php echo e($facility['label']); ?></label>
                <?php endforeach; ?>
            </fieldset>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Simpan</button></div>
    </form>
</section>
<?php endif; ?>
<h2 class="form-section-title">Daftar Kamar</h2>
<section class="table-panel">
    <table class="data-table">
        <thead><tr><th>No</th><th>Nomor Kamar</th><th>Harga Sewa</th><th>Status</th><th>Fasilitas</th><?php if ($isAdmin): ?><th>Aksi</th><?php else: ?><th>Pilih Kamar</th><?php endif; ?></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($rooms) === 0): ?>
            <tr><td colspan="6" class="empty-state"><?php echo $isAdmin ? 'Belum ada data kamar.' : 'Belum ada kamar tersedia.'; ?></td></tr>
        <?php else: ?>
            <?php $index = 1; while ($room = mysqli_fetch_assoc($rooms)): ?>
                <tr id="kamar-<?php echo (int) $room['id_kamar']; ?>">
                    <td><?php echo $index++; ?></td>
                    <td><?php echo e($room['nomor_kamar']); ?></td>
                    <td><?php echo (float) $room['harga'] > 0 ? 'Rp ' . number_format((float) $room['harga'], 0, ',', '.') . ' / bulan' : 'Harga belum diatur'; ?></td>
                    <td><span class="status <?php echo $room['status'] === 'Tersedia' ? 'status-available' : 'status-occupied'; ?>"><?php echo e($room['status']); ?></span></td>
                    <td>
                        <?php $roomFacilities = room_facility_keys($room['fasilitas']); ?>
                        <?php if ($roomFacilities): ?>
                            <div class="facility-editor-list">
                                <?php foreach ($roomFacilities as $facilityKey): ?><span><?php echo e(room_facility_options()[$facilityKey]['label']); ?></span><?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="muted-text">Belum diatur</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?><td>
                        <div class="action-group action-group-room">
                        <form method="post" class="inline-room-form" data-assigned="<?php echo $room['id_user'] !== null ? '1' : '0'; ?>" onsubmit="return this.elements['status'].value !== 'Tersedia' || this.dataset.assigned !== '1' || confirm('Mengubah status menjadi Tersedia akan melepaskan penghuni dari kamar ini. Lanjutkan?')">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="update_room">
                            <input type="hidden" name="id_kamar" value="<?php echo (int) $room['id_kamar']; ?>">
                            <label class="sr-only" for="harga-<?php echo (int) $room['id_kamar']; ?>">Harga kamar <?php echo e($room['nomor_kamar']); ?></label>
                            <input id="harga-<?php echo (int) $room['id_kamar']; ?>" name="harga" type="number" min="0" step="1000" value="<?php echo e($room['harga']); ?>" required>
                            <label class="sr-only" for="status-<?php echo (int) $room['id_kamar']; ?>">Status kamar <?php echo e($room['nomor_kamar']); ?></label>
                            <select id="status-<?php echo (int) $room['id_kamar']; ?>" name="status" aria-label="Status kamar <?php echo e($room['nomor_kamar']); ?>">
                                <option value="Tersedia" <?php echo $room['status'] === 'Tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                                <option value="Terisi" <?php echo $room['status'] === 'Terisi' ? 'selected' : ''; ?>>Terisi</option>
                            </select>
                            <fieldset class="facility-editor compact">
                                <legend>Fasilitas</legend>
                                <?php $roomFacilityKeys = room_facility_keys($room['fasilitas']); ?>
                                <?php foreach (room_facility_options() as $facilityKey => $facility): ?>
                                    <label><input type="checkbox" name="fasilitas[]" value="<?php echo e($facilityKey); ?>" <?php echo in_array($facilityKey, $roomFacilityKeys, true) ? 'checked' : ''; ?>> <?php echo e($facility['label']); ?></label>
                                <?php endforeach; ?>
                            </fieldset>
                            <button class="action-button" type="submit" aria-label="Simpan perubahan kamar <?php echo e($room['nomor_kamar']); ?>"><i class="fas fa-floppy-disk"></i></button>
                        </form>
                        <form method="post" onsubmit="return confirm('Hapus kamar ini?')">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id_kamar" value="<?php echo (int) $room['id_kamar']; ?>">
                            <button class="action-button delete" type="submit" aria-label="Hapus kamar <?php echo e($room['nomor_kamar']); ?>"><i class="far fa-trash-alt"></i></button>
                        </form>
                        </div>
                    </td><?php else: ?><td>
                        <?php if ((int) $room['id_user'] === $userId): ?>
                            <span class="status status-available">Kamar Anda</span>
                        <?php elseif (!$userHasRoom && (float) $room['harga'] > 0): ?>
                            <a class="btn btn-small" href="pembayaran.php?pesan=<?php echo (int) $room['id_kamar']; ?>">Pilih Kamar</a>
                        <?php else: ?>
                            <span class="muted-text">Hubungi pengelola</span>
                        <?php endif; ?>
                    </td><?php endif; ?>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php render_app_end(); ?>
