<?php
function render_app_start($title, $active, $identity = 'ADMIN')
{
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    $identity = (string) ($_SESSION['username'] ?? $identity);
    $navigation = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-house', 'href' => 'homeuser.php'],
        ['key' => 'kamar', 'label' => $isAdmin ? 'Data Kamar' : 'Pilih Kamar', 'icon' => 'far fa-file-lines', 'href' => 'kamar.php'],
        ['key' => 'pembayaran', 'label' => $isAdmin ? 'Data Pembayaran' : 'Pembayaran', 'icon' => 'far fa-credit-card', 'href' => $isAdmin ? 'pembayaran.php' : 'pembayaran_user.php'],
        ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'far fa-file-lines', 'href' => 'laporan.php'],
    ];
    if ($isAdmin) {
        array_splice($navigation, 2, 0, [[
            'key' => 'penyewa',
            'label' => 'Data Penghuni',
            'icon' => 'far fa-user',
            'href' => 'penyewa.php',
        ]]);
    }
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo e($title); ?> - KosanKu.id</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="style.css?v=20261022-7">
    </head>

    <body class="app-page">
        <header class="app-topbar">
            <a class="brand-lockup" href="home.php">
                <i class="fas fa-house"></i>
                <span><strong>KosanKu.id</strong><small>Cari Kos Jadi Lebih Mudah</small></span>
            </a>
            <a class="topbar-identity" href="profile.php"><i class="far fa-user"></i><?php echo e($identity); ?></a>
        </header>
        <aside class="app-sidebar">
            <nav class="sidebar-nav" aria-label="Navigasi utama">
                <?php foreach ($navigation as $item): ?>
                    <a href="<?php echo e($item['href']); ?>" class="<?php echo $active === $item['key'] ? 'active' : ''; ?>" <?php echo $active === $item['key'] ? ' aria-current="page"' : ''; ?>>
                        <i class="<?php echo e($item['icon']); ?>"></i><span><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <a class="sidebar-logout" href="logout.php"><i class="fas fa-arrow-right-from-bracket"></i><span>Log out</span></a>
        </aside>
        <main class="app-main">
        <?php
    }

    function render_app_end()
    {
        ?>
        </main>
    </body>

    </html>
<?php
    }
?>