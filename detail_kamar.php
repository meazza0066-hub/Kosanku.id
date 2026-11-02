<?php
require_once "auth.php";
require_once "koneksi.php";
require_once "room_facilities.php";
require_once "room_images.php";

$roomId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$room = null;

if ($roomId !== false && $roomId !== null && $roomId > 0) {
    $roomStatement = mysqli_prepare($koneksi, "SELECT id_kamar, nomor_kamar, status, harga, fasilitas FROM kamar WHERE id_kamar = ? AND status = 'Tersedia' AND id_user IS NULL LIMIT 1");
    mysqli_stmt_bind_param($roomStatement, "i", $roomId);
    mysqli_stmt_execute($roomStatement);
    $room = mysqli_fetch_assoc(mysqli_stmt_get_result($roomStatement));
    mysqli_stmt_close($roomStatement);
}

if (!$room) {
    http_response_code(404);
}

$roomNumber = $room ? (string) $room['nomor_kamar'] : '';
$roomFacilities = $room ? room_facility_keys($room['fasilitas']) : [];
$roomPrice = $room && (float) $room['harga'] > 0
    ? 'Rp ' . number_format((float) $room['harga'], 0, ',', '.') . ' / Bulan'
    : 'Hubungi pengelola untuk informasi harga';
$whatsappMessage = rawurlencode('Halo KosanKu.id, saya tertarik dengan Kamar ' . $roomNumber . '. Apakah kamar ini masih tersedia?');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $room ? 'Kamar ' . htmlspecialchars($roomNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Kamar tidak ditemukan'; ?> - KosanKu.id</title>
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
            <a href="home.php#kamar">Kamar</a>
            <a href="#kontak">Kontak</a>
            <a class="nav-dashboard" href="homeuser.php"><i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
            <a class="nav-username" href="profile.php"><i class="far fa-user" aria-hidden="true"></i> <?php echo e($_SESSION['username'] ?? 'User'); ?></a>
            <a class="nav-login" href="logout.php">Keluar</a>
        </nav>
    </header>

    <main class="room-detail-main">
        <?php if ($room): ?>
            <a class="room-detail-back" href="home.php#kamar"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke daftar kamar</a>
            <section class="room-detail-layout" aria-label="Deskripsi kamar">
                <div class="room-detail-gallery">
                    <figure class="room-detail-photo">
                        <img src="<?php echo htmlspecialchars(room_image_path($roomNumber), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Foto ilustrasi kamar <?php echo htmlspecialchars($roomNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <span class="room-detail-badge"><i class="fas fa-circle" aria-hidden="true"></i> Tersedia</span>
                    </figure>
                    <p class="room-photo-caption"><i class="far fa-image" aria-hidden="true"></i> Foto ilustrasi kamar</p>
                </div>
                <div class="room-detail-info">
                    <span class="room-detail-eyebrow">KAMAR KOSANKU.ID</span>
                    <h1>Kamar <?php echo htmlspecialchars($roomNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
                    <p class="room-detail-price"><?php echo htmlspecialchars($roomPrice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>

                    <?php if ($roomFacilities): ?>
                        <ul class="room-amenities" aria-label="Fasilitas kamar">
                            <?php foreach ($roomFacilities as $facilityKey): ?>
                                <?php $facility = room_facility_options()[$facilityKey]; ?>
                                <li><i class="<?php echo htmlspecialchars($facility['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-hidden="true"></i><span><?php echo htmlspecialchars($facility['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="room-no-features">Informasi fasilitas belum tersedia.</p>
                    <?php endif; ?>

                    <div class="room-description">
                        <h2>Deskripsi</h2>
                        <p>Kamar <?php echo htmlspecialchars($roomNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> nyaman untuk beristirahat, belajar, maupun bekerja. Hubungi pengelola untuk memastikan ketersediaan kamar.</p>
                    </div>

                    <a class="room-contact-button" href="https://wa.me/6285742338421?text=<?php echo $whatsappMessage; ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i><span>Hubungi kami</span>
                    </a>
                </div>
            </section>
        <?php else: ?>
            <section class="room-not-found">
                <span class="room-not-found-icon"><i class="fas fa-bed" aria-hidden="true"></i></span>
                <h1>Kamar tidak ditemukan</h1>
                <p>Kamar mungkin sudah tidak tersedia atau tautan yang dibuka tidak valid.</p>
                <a class="btn" href="home.php#kamar">Kembali ke daftar kamar</a>
            </section>
        <?php endif; ?>
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
