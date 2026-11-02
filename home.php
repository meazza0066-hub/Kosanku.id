<?php
require_once "auth.php";
require_once "koneksi.php";
require_once "room_facilities.php";
require_once "room_images.php";
$availableRooms = mysqli_query($koneksi, "SELECT id_kamar, nomor_kamar, harga, fasilitas FROM kamar WHERE status = 'Tersedia' AND id_user IS NULL ORDER BY nomor_kamar LIMIT 3");
$featuredRooms = [];
while ($room = mysqli_fetch_assoc($availableRooms)) {
    $featuredRooms[] = $room;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KosanKu.id - Cari Kos Jadi Lebih Mudah</title>
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
            <a href="#kamar">Kamar</a>
            <a href="#kontak">Kontak</a>
            <a class="nav-dashboard" href="homeuser.php"><i class="fas fa-gauge-high" aria-hidden="true"></i> Dashboard</a>
            <a class="nav-username" href="profile.php"><i class="far fa-user" aria-hidden="true"></i> <?php echo e($_SESSION['username'] ?? 'User'); ?></a>
            <a class="nav-login" href="logout.php">Keluar</a>
        </nav>
    </header>

    <main>
        <section class="public-hero" aria-label="Kamar kos nyaman">
            <div class="hero-inner">
                <div class="hero-copy">
                    <span class="hero-eyebrow">SELAMAT DATANG DI KOSANKU.ID</span>
                    <h1>Temukan kamar nyaman untuk awal yang baru.</h1>
                    <p>Jelajahi pilihan kamar kos dan temukan tempat tinggal yang sesuai untukmu.</p>
                </div>
                <form class="room-search" action="pilih_kamar.php" method="get" aria-label="Cari kamar">
                    <label class="search-field">
                        <span><i class="fas fa-wallet" aria-hidden="true"></i> Kisaran harga</span>
                        <select name="harga" aria-label="Kisaran harga">
                            <option value="">Semua kisaran harga</option>
                            <option value="under_500">Di bawah Rp 500.000</option>
                            <option value="500_750">Rp 500.000–Rp 749.999</option>
                            <option value="750_1000">Rp 750.000–Rp 999.999</option>
                            <option value="over_1000">Rp 1.000.000 ke atas</option>
                        </select>
                    </label>
                    <label class="search-field">
                        <span><i class="fas fa-list-check" aria-hidden="true"></i> Fasilitas</span>
                        <select name="fasilitas" aria-label="Fasilitas">
                            <option value="">Semua fasilitas</option>
                            <?php foreach (room_facility_options() as $facilityKey => $facility): ?>
                                <option value="<?php echo htmlspecialchars($facilityKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars($facility['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit"><i class="fas fa-search" aria-hidden="true"></i> Cari Kamar</button>
                </form>
            </div>
        </section>
        <section class="featured-rooms" id="kamar">
            <div class="section-heading">
                <div>
                    <span class="section-eyebrow">PILIHAN UNTUKMU</span>
                    <h2>Kamar Pilihan</h2>
                    <p>Temukan kamar tersedia yang nyaman untuk aktivitas sehari-hari.</p>
                </div>
                <a class="btn featured-all-link" href="pilih_kamar.php">Lihat Semua <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="room-grid">
                <?php if (count($featuredRooms) === 0): ?>
                    <p class="empty-state">Belum ada kamar yang tersedia saat ini.</p>
                <?php else: ?>
                    <?php foreach ($featuredRooms as $room): ?>
                        <a class="room-card" href="detail_kamar.php?id=<?php echo (int) $room['id_kamar']; ?>" aria-label="Lihat deskripsi kamar <?php echo htmlspecialchars($room['nomor_kamar'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="room-card-photo">
                                <img src="<?php echo htmlspecialchars(room_image_path($room['nomor_kamar']), ENT_QUOTES, 'UTF-8'); ?>" alt="Foto ilustrasi kamar <?php echo htmlspecialchars($room['nomor_kamar'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="room-badge">Tersedia</span>
                            </div>
                            <div class="room-card-content">
                                <h3>Kamar <?php echo htmlspecialchars($room['nomor_kamar'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="room-price"><?php echo (float) $room['harga'] > 0 ? 'Rp ' . number_format((float) $room['harga'], 0, ',', '.') . ' / bulan' : 'Hubungi pengelola untuk informasi harga'; ?></p>
                                <?php $roomFacilities = room_facility_keys($room['fasilitas']); ?>
                                <?php if ($roomFacilities): ?>
                                    <div class="room-features" aria-label="Fasilitas kamar">
                                        <?php foreach ($roomFacilities as $facilityKey): ?>
                                            <?php $facility = room_facility_options()[$facilityKey]; ?>
                                            <span><i class="<?php echo htmlspecialchars($facility['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($facility['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="room-no-features">Informasi fasilitas belum tersedia</p>
                                <?php endif; ?>
                            </div>
                        </a>
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
