<?php
require_once 'config/database.php';

try {
    // Alter the simpanan table to add 'hari_raya' to the ENUM
    $sql = "ALTER TABLE simpanan MODIFY COLUMN jenis ENUM('wajib', 'sukarela', 'pokok', 'hari_raya') NOT NULL";
    $conn->exec($sql);
    echo "Tabel simpanan berhasil diubah untuk mendukung jenis 'hari_raya'.\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
