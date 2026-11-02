<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "kosanku_id";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $koneksi = mysqli_connect($host, $username, $password, $database);
    mysqli_set_charset($koneksi, "utf8mb4");
} catch (mysqli_sql_exception $error) {
    error_log("Koneksi database gagal: " . $error->getMessage());
    http_response_code(500);
    if (in_array((int) $error->getCode(), [2002, 2003], true)) {
        exit("MySQL belum berjalan. Buka XAMPP Control Panel, klik Start pada MySQL, lalu muat ulang halaman ini.");
    }
    if ((int) $error->getCode() === 1049) {
        exit("Database kosanku_id belum ada. Buka phpMyAdmin, pilih Import, lalu impor file db_kosanku.sql dari folder proyek.");
    }
    if ((int) $error->getCode() === 1045) {
        exit("Login MySQL ditolak. Periksa username dan password database di koneksi.php.");
    }
    exit("Koneksi database gagal. Periksa layanan MySQL dan konfigurasi database di koneksi.php.");
}
?>