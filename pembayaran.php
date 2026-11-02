<?php
require_once "auth.php";
require_once "koneksi.php";
require_once "room_images.php";

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$userId = (int) ($_SESSION['id_user'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit("Aksi pembayaran tidak valid.");
    }

    if ($action === 'pesan_kamar' && !$isAdmin) {
        $roomId = filter_input(INPUT_POST, 'id_kamar', FILTER_VALIDATE_INT);
        $method = $_POST['metode'] ?? '';
        $validMethods = ['transfer_bank', 'ewallet', 'qris'];
        if (!$roomId || !is_string($method) || !in_array($method, $validMethods, true)) {
            header("Location: kamar.php?error=invalid");
            exit;
        }

        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
        $periodLabel = $monthNames[(int) date('n')] . ' ' . date('Y');
        $dueDate = date('Y-m-t');

        try {
            mysqli_begin_transaction($koneksi);
            $accountStatement = mysqli_prepare($koneksi, "SELECT username FROM users WHERE id_user = ? AND role = 'user' FOR UPDATE");
            mysqli_stmt_bind_param($accountStatement, "i", $userId);
            mysqli_stmt_execute($accountStatement);
            $account = mysqli_fetch_assoc(mysqli_stmt_get_result($accountStatement));
            mysqli_stmt_close($accountStatement);
            if (!$account) {
                mysqli_rollback($koneksi);
                header("Location: kamar.php?error=profile");
                exit;
            }

            $occupancyStatement = mysqli_prepare($koneksi, "SELECT id_kamar FROM kamar WHERE id_user = ? LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($occupancyStatement, "i", $userId);
            mysqli_stmt_execute($occupancyStatement);
            $existingRoom = mysqli_fetch_assoc(mysqli_stmt_get_result($occupancyStatement));
            mysqli_stmt_close($occupancyStatement);
            if ($existingRoom) {
                mysqli_rollback($koneksi);
                header("Location: kamar.php?error=already_booked");
                exit;
            }

            $tenantStatement = mysqli_prepare($koneksi, "SELECT id_penyewa FROM penyewa WHERE id_user = ? LIMIT 1 FOR UPDATE");
            mysqli_stmt_bind_param($tenantStatement, "i", $userId);
            mysqli_stmt_execute($tenantStatement);
            $tenant = mysqli_fetch_assoc(mysqli_stmt_get_result($tenantStatement));
            mysqli_stmt_close($tenantStatement);
            if (!$tenant) {
                $createTenantStatement = mysqli_prepare($koneksi, "INSERT INTO penyewa (nama, id_user) VALUES (?, ?)");
                mysqli_stmt_bind_param($createTenantStatement, "si", $account['username'], $userId);
                mysqli_stmt_execute($createTenantStatement);
                $tenant = ['id_penyewa' => mysqli_insert_id($koneksi)];
                mysqli_stmt_close($createTenantStatement);
            }

            $roomStatement = mysqli_prepare($koneksi, "SELECT nomor_kamar, harga FROM kamar WHERE id_kamar = ? AND status = 'Tersedia' AND id_user IS NULL AND harga > 0 FOR UPDATE");
            mysqli_stmt_bind_param($roomStatement, "i", $roomId);
            mysqli_stmt_execute($roomStatement);
            $room = mysqli_fetch_assoc(mysqli_stmt_get_result($roomStatement));
            mysqli_stmt_close($roomStatement);

            if (!$room) {
                mysqli_rollback($koneksi);
                header("Location: kamar.php?error=unavailable");
                exit;
            }

            $occupancyStatement = mysqli_prepare($koneksi, "UPDATE kamar SET status = 'Terisi', id_user = ? WHERE id_kamar = ? AND status = 'Tersedia' AND id_user IS NULL");
            mysqli_stmt_bind_param($occupancyStatement, "ii", $userId, $roomId);
            mysqli_stmt_execute($occupancyStatement);
            $roomReserved = mysqli_stmt_affected_rows($occupancyStatement) === 1;
            mysqli_stmt_close($occupancyStatement);
            if (!$roomReserved) {
                mysqli_rollback($koneksi);
                header("Location: kamar.php?error=unavailable");
                exit;
            }

            $paymentStatement = mysqli_prepare($koneksi, "INSERT INTO pembayaran (id_penyewa, jumlah, periode, tanggal_bayar, status, metode) VALUES (?, ?, ?, ?, 'Belum dibayar', ?)");
            mysqli_stmt_bind_param($paymentStatement, "idsss", $tenant['id_penyewa'], $room['harga'], $periodLabel, $dueDate, $method);
            mysqli_stmt_execute($paymentStatement);
            mysqli_stmt_close($paymentStatement);
            mysqli_commit($koneksi);
        } catch (mysqli_sql_exception $error) {
            mysqli_rollback($koneksi);
            if ((int) $error->getCode() === 1062) {
                header("Location: kamar.php?error=already_booked");
                exit;
            }
            throw $error;
        }

        header("Location: pembayaran_user.php?success=booking");
        exit;
    }

    if ($action === 'konfirmasi_lunas') {
        require_admin();
        $paymentId = filter_input(INPUT_POST, 'id_pembayaran', FILTER_VALIDATE_INT);
        if (!$paymentId) {
            header("Location: pembayaran.php?error=invalid");
            exit;
        }
        $confirmStatement = mysqli_prepare($koneksi, "UPDATE pembayaran SET status = 'Lunas' WHERE id_pembayaran = ? AND status = 'Belum dibayar'");
        mysqli_stmt_bind_param($confirmStatement, "i", $paymentId);
        mysqli_stmt_execute($confirmStatement);
        $confirmed = mysqli_stmt_affected_rows($confirmStatement) === 1;
        mysqli_stmt_close($confirmStatement);
        header("Location: pembayaran.php?success=" . ($confirmed ? 'confirmed' : 'already_confirmed'));
        exit;
    }

    if ($action !== 'catat_pembayaran') {
        http_response_code($isAdmin ? 400 : 403);
        exit($isAdmin ? "Aksi pembayaran tidak valid." : "Akses ditolak.");
    }
    require_admin();

    $tenantId = filter_input(INPUT_POST, 'id_penyewa', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_FLOAT);
    $periodInput = $_POST['periode'] ?? '';
    $dateInput = $_POST['tanggal_bayar'] ?? '';
    $period = is_string($periodInput) ? trim($periodInput) : '';
    $date = is_string($dateInput) ? $dateInput : '';
    $status = $_POST['status'] ?? '';
    $dateValid = DateTime::createFromFormat('Y-m-d', $date);
    $dateErrors = DateTime::getLastErrors();
    if (
        !$tenantId
        || $amount === false
        || $amount === null
        || $amount <= 0
        || $amount > 9999999999.99
        || $period === ''
        || strlen($period) > 50
        || !$dateValid
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $dateValid->format('Y-m-d') !== $date
        || !is_string($status)
        || !in_array($status, ['Belum dibayar', 'Lunas'], true)
    ) {
        header("Location: pembayaran.php?error=invalid");
        exit;
    }

    $tenantCheck = mysqli_prepare($koneksi, "SELECT id_penyewa FROM penyewa WHERE id_penyewa = ? LIMIT 1");
    mysqli_stmt_bind_param($tenantCheck, "i", $tenantId);
    mysqli_stmt_execute($tenantCheck);
    $tenantExists = mysqli_fetch_assoc(mysqli_stmt_get_result($tenantCheck)) !== null;
    mysqli_stmt_close($tenantCheck);
    if (!$tenantExists) {
        header("Location: pembayaran.php?error=invalid");
        exit;
    }

    $statement = mysqli_prepare($koneksi, "INSERT INTO pembayaran (id_penyewa, jumlah, periode, tanggal_bayar, status) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($statement, "idsss", $tenantId, $amount, $period, $date, $status);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    header("Location: pembayaran.php?success=payment");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit("Metode permintaan tidak didukung.");
}

$methodNames = [
    'transfer_bank' => 'Transfer Bank',
    'ewallet' => 'E-Wallet',
    'qris' => 'QRIS',
];
$methodDetails = [
    'transfer_bank' => 'BCA, BRI, BNI, Mandiri',
    'ewallet' => 'DANA, GoPay, OVO, ShopeePay',
    'qris' => 'Pembayaran melalui QR Code',
];
$periodNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];
$periodLabel = $periodNames[(int) date('n')] . ' ' . date('Y');
$dueDateLabel = date('d', strtotime(date('Y-m-t'))) . ' ' . $periodNames[(int) date('n')] . ' ' . date('Y');
$bookingRoom = null;
$userHasRoom = false;
if (!$isAdmin) {
    $occupancyStatement = mysqli_prepare($koneksi, "SELECT id_kamar FROM kamar WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($occupancyStatement, "i", $userId);
    mysqli_stmt_execute($occupancyStatement);
    $userHasRoom = mysqli_fetch_assoc(mysqli_stmt_get_result($occupancyStatement)) !== null;
    mysqli_stmt_close($occupancyStatement);
}
if (!$isAdmin && isset($_GET['pesan'])) {
    $roomId = filter_input(INPUT_GET, 'pesan', FILTER_VALIDATE_INT);
    if ($roomId && !$userHasRoom) {
        $roomStatement = mysqli_prepare($koneksi, "SELECT id_kamar, nomor_kamar, harga FROM kamar WHERE id_kamar = ? AND status = 'Tersedia' AND id_user IS NULL AND harga > 0");
        mysqli_stmt_bind_param($roomStatement, "i", $roomId);
        mysqli_stmt_execute($roomStatement);
        $bookingRoom = mysqli_fetch_assoc(mysqli_stmt_get_result($roomStatement));
        mysqli_stmt_close($roomStatement);
    }
}

if ($isAdmin) {
    $tenants = mysqli_query($koneksi, "SELECT id_penyewa, nama FROM penyewa ORDER BY nama");
    $payments = mysqli_query($koneksi, "SELECT p.id_pembayaran, p.jumlah, p.periode, p.tanggal_bayar, p.status, p.metode, s.nama, k.nomor_kamar FROM pembayaran p LEFT JOIN penyewa s ON s.id_penyewa = p.id_penyewa LEFT JOIN kamar k ON k.id_user = s.id_user ORDER BY p.tanggal_bayar DESC, p.id_pembayaran DESC");
} else {
    $userId = (int) ($_SESSION['id_user'] ?? 0);
    $tenantStatement = mysqli_prepare($koneksi, "SELECT id_penyewa, nama FROM penyewa WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($tenantStatement, "i", $userId);
    mysqli_stmt_execute($tenantStatement);
    $tenantProfile = mysqli_fetch_assoc(mysqli_stmt_get_result($tenantStatement));
    mysqli_stmt_close($tenantStatement);

    $paymentStatement = mysqli_prepare($koneksi, "SELECT p.id_pembayaran, p.jumlah, p.periode, p.tanggal_bayar, p.status, p.metode, s.nama, k.nomor_kamar FROM pembayaran p INNER JOIN penyewa s ON s.id_penyewa = p.id_penyewa LEFT JOIN kamar k ON k.id_user = s.id_user WHERE s.id_user = ? ORDER BY p.tanggal_bayar DESC, p.id_pembayaran DESC");
    mysqli_stmt_bind_param($paymentStatement, "i", $userId);
    mysqli_stmt_execute($paymentStatement);
    $payments = mysqli_stmt_get_result($paymentStatement);
    mysqli_stmt_close($paymentStatement);
}
require_once "app_layout.php";
render_app_start('Pembayaran', 'pembayaran', strtoupper((string) ($_SESSION['role'] ?? 'user')));
?>
<div class="page-heading">
    <div>
        <h1><?php echo $isAdmin ? 'Pembayaran' : 'Pembayaran Kamar'; ?></h1>
        <p><?php echo $isAdmin ? 'Catat dan pantau pembayaran sewa' : 'Detail tagihan dan metode pembayaran kos'; ?></p>
    </div>
    <?php if ($isAdmin): ?><a class="btn" href="#catat-pembayaran"><i class="fas fa-plus"></i> Catat Pembayaran</a>
    <?php else: ?><a class="back-link" href="kamar.php"><i class="fas fa-arrow-left"></i> Pilih Kamar</a><?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <?php
    $successMessages = [
        'booking' => 'Kamar berhasil dipilih. Tagihan Anda sudah dibuat dan menunggu konfirmasi admin.',
        'confirmed' => 'Pembayaran berhasil dikonfirmasi lunas.',
        'already_confirmed' => 'Pembayaran sudah lunas atau tidak ditemukan.',
        'payment' => 'Pembayaran berhasil dicatat.',
    ];
    $successKey = $_GET['success'];
    ?>
    <?php if (is_string($successKey) && isset($successMessages[$successKey])): ?>
        <p class="notice" role="status"><?php echo e($successMessages[$successKey]); ?></p>
    <?php endif; ?>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?><p class="notice error" role="alert">Data pembayaran tidak valid.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'already_booked'): ?><p class="notice error" role="alert">Akun Anda sudah memiliki kamar. Hubungi pengelola jika perlu pindah kamar.</p><?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'unavailable'): ?><p class="notice error" role="alert">Kamar tersebut sudah tidak tersedia. Silakan pilih kamar lain.</p><?php endif; ?>

<?php if ($isAdmin): ?>
    <section class="form-panel" id="catat-pembayaran">
        <h2 style="margin:0 0 18px;font-size:20px">Catat Pembayaran</h2>
        <?php if (mysqli_num_rows($tenants) === 0): ?>
            <p>Tambahkan penyewa sebelum mencatat pembayaran.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="catat_pembayaran">
                <div class="form-grid">
                    <div class="form-field"><label for="penyewa">Penyewa <span aria-hidden="true">*</span></label><select id="penyewa" name="id_penyewa" required><?php while ($tenant = mysqli_fetch_assoc($tenants)): ?><option value="<?php echo (int) $tenant['id_penyewa']; ?>"><?php echo e($tenant['nama']); ?></option><?php endwhile; ?></select></div>
                    <div class="form-field"><label for="jumlah">Jumlah (Rp) <span aria-hidden="true">*</span></label><input id="jumlah" type="number" name="jumlah" min="1" step="0.01" required></div>
                    <div class="form-field"><label for="periode">Periode <span aria-hidden="true">*</span></label><input id="periode" name="periode" placeholder="Contoh: Oktober 2026" maxlength="50" required></div>
                    <div class="form-field"><label for="tanggal-bayar">Tanggal <span aria-hidden="true">*</span></label><input id="tanggal-bayar" type="date" name="tanggal_bayar" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-field"><label for="status-bayar">Status <span aria-hidden="true">*</span></label><select id="status-bayar" name="status">
                            <option>Belum dibayar</option>
                            <option>Lunas</option>
                        </select></div>
                </div>
                <div class="form-actions"><button class="btn" type="submit">Simpan</button></div>
            </form>
        <?php endif; ?>
    </section>
<?php elseif ($bookingRoom): ?>
    <div class="payment-layout">
        <section class="payment-primary">
            <article class="payment-summary">
                <div class="payment-summary-heading"><i class="far fa-house"></i><strong>Detail Tagihan</strong></div>
                <p>Nama Penghuni : <strong><?php echo e($tenantProfile['nama'] ?? $_SESSION['username']); ?></strong></p>
                <p>Nomor Kamar : <strong><?php echo e($bookingRoom['nomor_kamar']); ?></strong></p>
                <p>Periode : <strong><?php echo e($periodLabel); ?></strong></p>
                <p>Jatuh Tempo : <strong><?php echo e($dueDateLabel); ?></strong></p>
                <div class="total-due"><i class="far fa-file-lines"></i><strong>Total Tagihan</strong><b>Rp <?php echo number_format((float) $bookingRoom['harga'], 0, ',', '.'); ?></b><span class="status status-unpaid">Belum Dibayar</span></div>
            </article>

            <form class="payment-methods" method="post" id="room-booking">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="pesan_kamar">
                <input type="hidden" name="id_kamar" value="<?php echo (int) $bookingRoom['id_kamar']; ?>">
                <div class="payment-method-heading"><i class="far fa-credit-card"></i>
                    <div><strong>Metode Pembayaran</strong><small>Pilih metode pembayaran yang Anda inginkan</small></div>
                </div>
                <div class="payment-method-grid">
                    <?php foreach ($methodNames as $methodKey => $methodLabel): ?>
                        <label class="payment-method-option">
                            <input type="radio" name="metode" value="<?php echo e($methodKey); ?>" <?php echo $methodKey === 'transfer_bank' ? 'checked' : ''; ?> required>
                            <span class="payment-method-card">
                                <i class="<?php echo $methodKey === 'transfer_bank' ? 'fas fa-building-columns' : ($methodKey === 'ewallet' ? 'fas fa-wallet' : 'fas fa-qrcode'); ?>"></i>
                                <strong><?php echo e($methodLabel); ?></strong>
                                <small><?php echo e($methodDetails[$methodKey]); ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="payment-continue"><i class="far fa-credit-card"></i> Lanjut ke Pembayaran <i class="fas fa-arrow-right"></i></button>
                <p class="payment-note">Pilihan kamar akan langsung mengisi kamar dan membuat tagihan berstatus belum dibayar. Admin akan mengonfirmasi pembayaran.</p>
            </form>
        </section>
        <aside class="payment-room-card">
            <div class="payment-room-image"><img src="<?php echo e(room_image_path($bookingRoom['nomor_kamar'])); ?>" alt="Kamar <?php echo e($bookingRoom['nomor_kamar']); ?>"><span>Kamar <?php echo e($bookingRoom['nomor_kamar']); ?></span></div>
            <div class="payment-room-details">
                <h2>Detail Tagihan</h2>
                <p><span>Nama Penghuni :</span><strong><?php echo e($tenantProfile['nama'] ?? $_SESSION['username']); ?></strong></p>
                <p><span>Nomor Kamar :</span><strong><?php echo e($bookingRoom['nomor_kamar']); ?></strong></p>
                <p><span>Harga Bulanan :</span><strong>Rp <?php echo number_format((float) $bookingRoom['harga'], 0, ',', '.'); ?></strong></p>
                <p><span>Periode :</span><strong><?php echo e($periodLabel); ?></strong></p>
                <p><span>Jatuh Tempo :</span><strong><?php echo e($dueDateLabel); ?></strong></p>
            </div>
        </aside>
    </div>
<?php else: ?>
    <?php if (!$isAdmin && $userHasRoom && !empty($_GET['pesan'])): ?>
        <p class="notice error" role="alert">Akun Anda sudah memiliki kamar. Hubungi pengelola jika ingin pindah kamar.</p>
    <?php elseif (!$isAdmin && !empty($_GET['pesan'])): ?>
        <p class="notice error" role="alert">Kamar tidak tersedia atau harganya belum diatur. Silakan pilih kamar lain atau hubungi pengelola.</p>
    <?php endif; ?>
    <?php if (!$isAdmin && mysqli_num_rows($payments) === 0): ?>
        <p class="notice">Anda belum memiliki tagihan. Pilih kamar yang tersedia melalui menu Kamar untuk melanjutkan.</p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($isAdmin || !$bookingRoom): ?>
    <h2 class="form-section-title"><?php echo $isAdmin ? 'Riwayat Pembayaran' : 'Riwayat Tagihan Saya'; ?></h2>
    <section class="table-panel">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th><?php if ($isAdmin): ?><th>Nama Penghuni</th><?php endif; ?><?php if ($isAdmin || !$bookingRoom): ?><th>Nomor Kamar</th><?php endif; ?><th>Periode</th>
                    <th>Jumlah</th>
                    <th>Metode</th>
                    <th>Tanggal</th>
                    <th>Status</th><?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($payments) === 0): ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? 9 : 7; ?>" class="empty-state">Belum ada riwayat pembayaran.</td>
                    </tr>
                <?php else: ?>
                    <?php $index = 1;
                    while ($payment = mysqli_fetch_assoc($payments)): ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <?php if ($isAdmin): ?><td><?php echo e($payment['nama'] ?? 'Penyewa dihapus'); ?></td><?php endif; ?>
                            <?php if ($isAdmin || !$bookingRoom): ?><td><?php echo e($payment['nomor_kamar'] ?? '-'); ?></td><?php endif; ?>
                            <td><?php echo e($payment['periode']); ?></td>
                            <td>Rp <?php echo number_format((float) $payment['jumlah'], 0, ',', '.'); ?></td>
                            <td><?php echo e($methodNames[$payment['metode']] ?? '-'); ?></td>
                            <td><?php echo e($payment['tanggal_bayar']); ?></td>
                            <td><span class="status <?php echo $payment['status'] === 'Lunas' ? 'status-paid' : 'status-unpaid'; ?>"><?php echo e($payment['status']); ?></span></td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <?php if ($payment['status'] === 'Belum dibayar'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="konfirmasi_lunas">
                                            <input type="hidden" name="id_pembayaran" value="<?php echo (int) $payment['id_pembayaran']; ?>">
                                            <button class="btn" type="submit">Konfirmasi Lunas</button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
<?php render_app_end(); ?>