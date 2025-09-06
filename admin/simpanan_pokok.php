<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle CRUD operations
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['tambah'])) {
        // Tambah simpanan pokok baru
        $nasabah_id = $_POST['nasabah_id'];
        $jenis_transaksi = $_POST['jenis_transaksi']; // 'debit' or 'credit'
        $jumlah = $_POST['jumlah'];
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];

        // Set jumlah based on debit/credit (reversed logic)
        $jumlah_db = ($jenis_transaksi == 'debit') ? -$jumlah : $jumlah;

        try {
            $conn->beginTransaction();

            // Insert simpanan
            $stmt = $conn->prepare("INSERT INTO simpanan (nasabah_id, jumlah, jenis, tanggal, keterangan) VALUES (?, ?, 'pokok', ?, ?)");
            $stmt->execute([$nasabah_id, $jumlah_db, $tanggal, $keterangan]);

            // Update saldo nasabah
            $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo + ? WHERE id = ?");
            $stmt->execute([$jumlah_db, $nasabah_id]);

            $conn->commit();
            $success = "Simpanan pokok berhasil ditambahkan!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }

    if(isset($_POST['edit'])) {
        // Edit simpanan pokok
        $id = $_POST['id'];
        $nasabah_id = $_POST['nasabah_id'];
        $jenis_transaksi = $_POST['jenis_transaksi'];
        $jumlah = $_POST['jumlah'];
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];

        $jumlah_db = ($jenis_transaksi == 'debit') ? -$jumlah : $jumlah;

        try {
            $conn->beginTransaction();

            // Get old data for saldo adjustment
            $stmt = $conn->prepare("SELECT nasabah_id, jumlah FROM simpanan WHERE id = ?");
            $stmt->execute([$id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Reverse old effect on saldo
            $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo - ? WHERE id = ?");
            $stmt->execute([$old_data['jumlah'], $old_data['nasabah_id']]);

            // Update simpanan
            $stmt = $conn->prepare("UPDATE simpanan SET nasabah_id = ?, jumlah = ?, tanggal = ?, keterangan = ? WHERE id = ?");
            $stmt->execute([$nasabah_id, $jumlah_db, $tanggal, $keterangan, $id]);

            // Apply new effect on saldo
            $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo + ? WHERE id = ?");
            $stmt->execute([$jumlah_db, $nasabah_id]);

            $conn->commit();
            $success = "Simpanan pokok berhasil diupdate!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }

    if(isset($_POST['hapus'])) {
        // Hapus simpanan pokok
        $id = $_POST['id'];

        try {
            $conn->beginTransaction();

            // Get data for saldo reversal
            $stmt = $conn->prepare("SELECT nasabah_id, jumlah FROM simpanan WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Reverse effect on saldo
            $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo - ? WHERE id = ?");
            $stmt->execute([$data['jumlah'], $data['nasabah_id']]);

            // Delete simpanan
            $stmt = $conn->prepare("DELETE FROM simpanan WHERE id = ?");
            $stmt->execute([$id]);

            $conn->commit();
            $success = "Simpanan pokok berhasil dihapus!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Ambil data simpanan pokok
$stmt = $conn->query("SELECT s.*, n.nama_lengkap FROM simpanan s JOIN nasabah n ON s.nasabah_id = n.id WHERE s.jenis = 'pokok' ORDER BY s.tanggal DESC, s.created_at DESC");
$simpanan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data nasabah untuk dropdown
$stmt = $conn->query("SELECT id, nama_lengkap FROM nasabah ORDER BY nama_lengkap");
$nasabah_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>


    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if(isset($success)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Sukses',
                text: '<?= $success ?>',
                confirmButtonText: 'OK'
            });
        });
    </script>
    <?php endif; ?>

    <!-- <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahModal">
        <i class="fas fa-plus"></i> Tambah Simpanan Pokok
    </button> -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                <h3 class="card-title">Daftar Simpanan Pokok</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahModal">
                        <i class="fas fa-plus"></i> Tambah Simpanan Pokok
                    </button>
                </div>
    </div>
            <!-- </div>
                <div class="card-header">
                    <h3 class="card-title">Daftar Simpanan Pokok</h3>
                </div> -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nasabah">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nasabah</th>
                            <th>Jenis</th>
                            <th>Pengeluaran</th>
                            <th>Pemasukkan</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($simpanan_list as $index => $simpanan): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $simpanan['nama_lengkap'] ?></td>
                            <td>
                            <?php if($simpanan['jumlah'] < 0): ?>
                                    <span class="badge badge-danger">Debit</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Kredit</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Show pengeluaran (debit) only, else 0
                                if ($simpanan['jumlah'] < 0) {
                                    echo 'Rp ' . number_format(abs($simpanan['jumlah']), 0, ',', '.');
                                } else {
                                    echo 'Rp '.'0';
                                }
                                ?>
                            </td>
                                <td>
                                    <?php
                                    if ($simpanan['jumlah'] > 0) {
                                        echo 'Rp ' . number_format($simpanan['jumlah'], 0, ',', '.');
                                    } else {
                                        echo 'Rp '.'0';
                                    }
                                    ?>
                                </td>
                            <td><?= date('d/m/Y', strtotime($simpanan['tanggal'])) ?></td>
                            <td><?= $simpanan['keterangan'] ?: '-' ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?= $simpanan['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="id" value="<?= $simpanan['id'] ?>">
                                    <button type="button" class="btn btn-danger btn-sm delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $simpanan['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Simpanan Pokok</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?= $simpanan['id'] ?>">
                                            <div class="mb-3">
                                                <label>Nasabah</label>
                                                <select name="nasabah_id" class="form-control" required>
                                                    <?php foreach($nasabah_options as $nasabah): ?>
                                                        <option value="<?= $nasabah['id'] ?>" <?= $nasabah['id'] == $simpanan['nasabah_id'] ? 'selected' : '' ?>>
                                                            <?= $nasabah['nama_lengkap'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Jenis Transaksi</label>
                                                <select name="jenis_transaksi" class="form-control" required>
                                                    <option value="debit" <?= $simpanan['jumlah'] < 0 ? 'selected' : '' ?>>Debit (Pengurangan)</option>
                                                    <option value="credit" <?= $simpanan['jumlah'] > 0 ? 'selected' : '' ?>>Kredit (Penambahan)</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Jumlah</label>
                                                <input type="number" name="jumlah" class="form-control" value="<?= abs($simpanan['jumlah']) ?>" min="0" step="0.01" required>
                                            </div>
                                            <div class="mb-3">
                                                <label>Tanggal</label>
                                                <input type="date" name="tanggal" class="form-control" value="<?= $simpanan['tanggal'] ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label>Keterangan</label>
                                                <textarea name="keterangan" class="form-control"><?= $simpanan['keterangan'] ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                            <button type="submit" name="edit" class="btn btn-primary">Simpan</button>
                                        </div>
                                    </form>
        </div>
    </div>
        </div>
    </div>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="tambahModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Simpanan Pokok</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nasabah</label>
                        <select name="nasabah_id" class="form-control" required>
                            <option value="">Pilih Nasabah</option>
                            <?php foreach($nasabah_options as $nasabah): ?>
                                <option value="<?= $nasabah['id'] ?>"><?= $nasabah['nama_lengkap'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Jenis Transaksi</label>
                        <select name="jenis_transaksi" class="form-control" required>
                            <option value="debit">Debit (Pengurangan Saldo)</option>
                            <option value="credit">Kredit (Penambahan Saldo)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Jumlah</label>
                        <input type="number" name="jumlah" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Keterangan</label>
                        <textarea name="keterangan" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete button clicks
    document.querySelectorAll('.delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = this.closest('.delete-form');

            Swal.fire({
                title: 'Yakin ingin menghapus?',
                text: 'Data simpanan pokok akan dihapus dan saldo nasabah akan dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Add the hapus input to the form and submit
                    var hapusInput = document.createElement('input');
                    hapusInput.type = 'hidden';
                    hapusInput.name = 'hapus';
                    hapusInput.value = '1';
                    form.appendChild(hapusInput);
                    form.submit();
                }
            });
        });
    });

    // Handle jenis_transaksi change to clear jumlah if debit
    document.querySelectorAll('select[name="jenis_transaksi"]').forEach(function(select) {
        select.addEventListener('change', function() {
            var form = this.closest('form');
            var jumlahInput = form.querySelector('input[name="jumlah"]');
            if (this.value === 'debit') {
                jumlahInput.value = '';
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
