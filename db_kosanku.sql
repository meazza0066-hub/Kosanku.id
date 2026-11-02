CREATE DATABASE IF NOT EXISTS kosanku_id CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE kosanku_id;

CREATE TABLE IF NOT EXISTS users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS kamar (
    id_kamar INT AUTO_INCREMENT PRIMARY KEY,
    nomor_kamar VARCHAR(50) NOT NULL,
    status VARCHAR(30) DEFAULT 'Tersedia',
    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
    fasilitas VARCHAR(255) NOT NULL DEFAULT '',
    id_user INT DEFAULT NULL UNIQUE,
    CONSTRAINT fk_kamar_user FOREIGN KEY (id_user)
        REFERENCES users (id_user) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS penyewa (
    id_penyewa INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    no_hp VARCHAR(50) DEFAULT NULL,
    id_user INT DEFAULT NULL UNIQUE,
    CONSTRAINT fk_penyewa_user FOREIGN KEY (id_user)
        REFERENCES users (id_user) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
    id_penyewa INT DEFAULT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    periode VARCHAR(50) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    status ENUM('Belum dibayar','Lunas') NOT NULL DEFAULT 'Belum dibayar',
    metode VARCHAR(30) DEFAULT NULL,
    CONSTRAINT fk_pembayaran_penyewa FOREIGN KEY (id_penyewa)
        REFERENCES penyewa (id_penyewa) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS laporan (
    id_laporan INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelapor VARCHAR(150) NOT NULL,
    nomor_kamar VARCHAR(50) NOT NULL,
    isi TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, role)
SELECT 'admin', MD5('admin123'), 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

INSERT INTO kamar (nomor_kamar, status, fasilitas)
SELECT 'A101', 'Tersedia', 'fan,wifi,bathroom'
WHERE NOT EXISTS (SELECT 1 FROM kamar WHERE nomor_kamar = 'A101');

INSERT INTO kamar (nomor_kamar, status, fasilitas)
SELECT 'A102', 'Terisi', 'fan,wifi,bathroom'
WHERE NOT EXISTS (SELECT 1 FROM kamar WHERE nomor_kamar = 'A102');
