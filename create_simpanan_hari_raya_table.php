<?php
require_once 'config/database.php';

try {
    // Create new table for simpanan_hari_raya
    $sql = "CREATE TABLE simpanan_hari_raya (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nasabah_id INT,
        jumlah DECIMAL(15,2) NOT NULL,
        tanggal DATE NOT NULL,
        keterangan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (nasabah_id) REFERENCES nasabah(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    echo "Tabel simpanan_hari_raya berhasil dibuat.\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
