<?php
require_once "auth.php";
require_once "koneksi.php";
require_once "room_facilities.php";
require_once "room_images.php";

$priceRanges = [
    'under_500' => 'harga > 0 AND harga < 500000',
    '500_750' => 'harga >= 500000 AND harga < 750000',
    '750_1000' => 'harga >= 750000 AND harga < 1000000',
    'over_1000' => 'harga >= 1000000',
];
$selectedPriceRange = $_GET['harga'] ?? '';
if (!is_string($selectedPriceRange) || !isset($priceRanges[$selectedPriceRange])) {
    $selectedPriceRange = '';
}
$selectedFacility = $_GET['fasilitas'] ?? '';
$facilityOptions = room_facility_options();
if (!is_string($selectedFacility) || !isset($facilityOptions[$selectedFacility])) {
    $selectedFacility = '';
}

$roomConditions = ["status = 'Tersedia'", 'id_user IS NULL'];
if ($selectedPriceRange !== '') {
    $roomConditions[] = $priceRanges[$selectedPriceRange];
}
$roomQuery = "SELECT id_kamar, nomor_kamar, harga, fasilitas FROM kamar WHERE " . implode(' AND ', $roomConditions);
if ($selectedFacility !== '') {
    $roomQuery .= " AND FIND_IN_SET(?, fasilitas) > 0";
    $roomStatement = mysqli_prepare($koneksi, $roomQuery . " ORDER BY nomor_kamar");
    mysqli_stmt_bind_param($roomStatement, "s", $selectedFacility);
    mysqli_stmt_execute($roomStatement);
    $availableRooms = mysqli_stmt_get_result($roomStatement);
} else {
    $availableRooms = mysqli_query($koneksi, $roomQuery . " ORDER BY nomor_kamar");
}
$rooms = [];
while ($room = mysqli_fetch_assoc($availableRooms)) {
    $rooms[] = $room;
}
if (isset($roomStatement)) {
    mysqli_stmt_close($roomStatement);
}
$filterActive = $selectedPriceRange !== '' || $selectedFacility !== '';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $isAdmin ? 'Kelola Kamar' : 'Pilih Kamar'; ?> - KosanKu.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=20261022-8">
</head>
<body class="public-page">
    <header class="public-header">
        <a class="public-brand" href="home.php">
            <i class="fas fa-house"></i>
            <span><strong>KosanKu<span>.id</span></strong><small>Cari Kos Jadi Lebih Mudah</small></span>
        </a>
        <nav class="public-nav" aria-label="Navigasi utama">
            <a href="home.php">Beranda</a>
            <a href="#daftar-kamar">Kamar</a>
            <a href="#kontak">Kontak</a>
            <a class="nav-dashboard" href="homeuser.php"><i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
            <a class="nav-username" href="profile.php"><i class="far fa-user" aria-hidden="true"></i> <?php echo e($_SESSION['username'] ?? 'User'); ?></a>
            <a class="nav-login" href="logout.php">Keluar</a>
        </nav>
    </header>

    <main>
        <section class="featured-rooms room-selection-page" id="daftar-kamar">
            <div class="section-heading">
                <div>
                    <span class="section-eyebrow"><?php echo $isAdmin ? 'PENGELOLAAN KAMAR' : 'PILIHAN UNTUKMU'; ?></span>
                    <h1><?php echo $isAdmin ? 'Kelola Kamar' : 'Pilih Kamar'; ?></h1>
                    <p><?php echo $isAdmin ? 'Pilih kamar yang ingin diperbarui.' : 'Bandingkan kamar yang tersedia dan pilih yang sesuai untukmu.'; ?></p>
                </div>
                <span class="room-selection-count"><?php echo count($rooms); ?> kamar tersedia</span>
            </div>
            <form class="room-search room-search-results" action="pilih_kamar.php" method="get" aria-label="Filter kamar">
                <label class="search-field">
                    <span><i class="fas fa-wallet" aria-hidden="true"></i> Kisaran harga</span>
                    <select name="harga" aria-label="Kisaran harga">
                        <option value="">Semua kisaran harga</option>
                        <option value="under_500" <?php echo $selectedPriceRange === 'under_500' ? 'selected' : ''; ?>>Di bawah Rp 500.000</option>
                        <option value="500_750" <?php echo $selectedPriceRange === '500_750' ? 'selected' : ''; ?>>Rp 500.000–Rp 749.999</option>
                        <option value="750_1000" <?php echo $selectedPriceRange === '750_1000' ? 'selected' : ''; ?>>Rp 750.000–Rp 999.999</option>
                        <option value="over_1000" <?php echo $selectedPriceRange === 'over_1000' ? 'selected' : ''; ?>>Rp 1.000.000 ke atas</option>
                    </select>
                </label>
                <label class="search-field">
                    <span><i class="fas fa-list-check" aria-hidden="true"></i> Fasilitas</span>
                    <select name="fasilitas" aria-label="Fasilitas">
                        <option value="">Semua fasilitas</option>
                        <?php foreach ($facilityOptions as $facilityKey => $facility): ?>
                            <option value="<?php echo htmlspecialchars($facilityKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $selectedFacility === $facilityKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($facility['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit"><i class="fas fa-search" aria-hidden="true"></i> Cari Kamar</button>
                <?php if ($filterActive): ?>
                    <a class="room-search-reset" href="pilih_kamar.php">Reset</a>
                <?php endif; ?>
            </form>
            <div class="room-grid">
                <?php if (count($rooms) === 0): ?>
                    <p class="empty-state"><?php echo $filterActive ? 'Tidak ada kamar yang cocok dengan filter. Coba ubah kisaran harga atau fasilitas.' : 'Saat ini belum ada kamar yang tersedia.'; ?></p>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <article class="room-card">
                            <a class="room-card-photo room-picker-photo" href="detail_kamar.php?id=<?php echo (int) $room['id_kamar']; ?>" aria-label="Lihat detail kamar <?php echo htmlspecialchars($room['nomor_kamar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <img src="<?php echo htmlspecialchars(room_image_path($room['nomor_kamar']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Foto ilustrasi kamar <?php echo htmlspecialchars($room['nomor_kamar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <span class="room-badge">Tersedia</span>
                            </a>
                            <div class="room-card-content">
                                <h2>Kamar <?php echo htmlspecialchars($room['nomor_kamar'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
                                <p class="room-price"><?php echo (float) $room['harga'] > 0 ? 'Rp ' . number_format((float) $room['harga'], 0, ',', '.') . ' / bulan' : 'Hubungi pengelola untuk informasi harga'; ?></p>
                                <?php $roomFacilities = room_facility_keys($room['fasilitas']); ?>
                                <?php if ($roomFacilities): ?>
                                    <div class="room-features" aria-label="Fasilitas kamar">
                                        <?php foreach ($roomFacilities as $facilityKey): ?>
                                            <?php $facility = $facilityOptions[$facilityKey]; ?>
                                            <span><i class="<?php echo htmlspecialchars($facility['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($facility['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="room-no-features">Informasi fasilitas belum tersedia</p>
                                <?php endif; ?>
                                <div class="room-picker-actions">
                                    <a class="btn btn-small room-detail-link" href="detail_kamar.php?id=<?php echo (int) $room['id_kamar']; ?>">Lihat Detail</a>
                                    <?php if ($isAdmin): ?>
                                        <a class="btn btn-small" href="kamar.php#kamar-<?php echo (int) $room['id_kamar']; ?>">Edit Kamar</a>
                                    <?php elseif ((float) $room['harga'] > 0): ?>
                                        <a class="btn btn-small" href="login.php?pesan=<?php echo (int) $room['id_kamar']; ?>">Pilih Kamar</a>
                                    <?php else: ?>
                                        <a class="btn btn-small" href="https://wa.me/6285742338421" target="_blank" rel="noopener noreferrer">Tanya Harga</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="public-footer" id="kontak">
        <a class="brand-lockup" href="home.php">
            <i class="fas fa-house"></i>
            <span><strong>KosanKu.id</strong><small>Cari Kos Jadi Lebih Mudah</small></span>
        </a>
        <div class="footer-copy">&copy; <?php echo date('Y'); ?> KosanKu.id <span>Sistem manajemen kos</span></div>
        <div class="footer-contact">
            <span>Ikuti &amp; hubungi kami</span>
            <a href="https://www.instagram.com/_putriaa0?exln=eHhpbzU3a2xkd25r" target="_blank" rel="noopener noreferrer" aria-label="Instagram KosanKu.id"><i class="fab fa-instagram" aria-hidden="true"></i></a>
            <a href="https://www.tiktok.com/@yesyes26_1?_r=1&amp;_t=ZS-9AknOBmphen" target="_blank" rel="noopener noreferrer" aria-label="TikTok KosanKu.id"><i class="fab fa-tiktok" aria-hidden="true"></i></a>
            <a href="https://wa.me/6285742338421" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp KosanKu.id"><i class="fab fa-whatsapp" aria-hidden="true"></i></a>
        </div>
    </footer>
</body>
</html>
