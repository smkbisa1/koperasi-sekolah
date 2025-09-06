-- Database: koperasi_sekolah
CREATE DATABASE IF NOT EXISTS koperasi_sekolah;
USE koperasi_sekolah;

-- Tabel users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'nasabah') NOT NULL,
    name VARCHAR(100),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel nasabah
CREATE TABLE nasabah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    nama_lengkap VARCHAR(100) NOT NULL,
    NIK VARCHAR(20) UNIQUE NOT NULL,
    NIP_NUPTK VARCHAR(20),
    alamat TEXT,
    no_telp VARCHAR(15),
    saldo DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel simpanan
CREATE TABLE simpanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nasabah_id INT,
    jumlah DECIMAL(15,2) NOT NULL,
    jenis ENUM('wajib', 'sukarela', 'pokok') NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nasabah_id) REFERENCES nasabah(id) ON DELETE CASCADE
);

-- Tabel pinjaman
CREATE TABLE pinjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_pinjaman VARCHAR(50) UNIQUE,
    nasabah_id INT,
    jumlah DECIMAL(15,2) NOT NULL,
    bunga DECIMAL(5,2) DEFAULT 2.00,
    lama_angsuran INT NOT NULL,
    jasa_angsuran_per_bulan DECIMAL(15,2) DEFAULT 0.00,
    total_angsuran DECIMAL(15,2) NOT NULL,
    angsuran_per_bulan DECIMAL(15,2) NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    status ENUM('pending', 'disetujui', 'ditolak', 'lunas') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nasabah_id) REFERENCES nasabah(id) ON DELETE CASCADE
);

-- Tabel angsuran
CREATE TABLE angsuran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pinjaman_id INT,
    jumlah DECIMAL(15,2) NOT NULL,
    tanggal_bayar DATE NOT NULL,
    denda DECIMAL(15,2) DEFAULT 0.00,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pinjaman_id) REFERENCES pinjaman(id) ON DELETE CASCADE
);

-- Tabel transaksi
CREATE TABLE transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nasabah_id INT,
    jenis ENUM('simpanan', 'penarikan', 'pinjaman', 'angsuran') NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nasabah_id) REFERENCES nasabah(id) ON DELETE CASCADE
);

-- Tabel settings (new)
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default interest_rate setting if not exists
INSERT INTO settings (setting_key, setting_value)
SELECT 'interest_rate', '2.00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'interest_rate');

-- Insert admin default
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert nasabah default
INSERT INTO users (username, password, role) VALUES
('nasabah1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nasabah');

INSERT INTO nasabah (user_id, nama_lengkap, NIK, NIP_NUPTK, alamat, no_telp, saldo) VALUES
(2, 'Budi Santoso', '1234567890123456', '123456789012345678', 'Jl. Merdeka No. 10', '081234567890', 500000);

-- Drop denda column from angsuran table (Remove Penalty)
ALTER TABLE angsuran DROP COLUMN denda;
