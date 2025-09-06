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
        // Tambah nasabah baru
        $username = $_POST['username'];
        $password = $_POST['password'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $NIK = $_POST['NIK'];
        $NIP_NUPTK = $_POST['NIP_NUPTK'];
        $alamat = $_POST['alamat'];
        $no_telp = $_POST['no_telp'];

        try {
            $conn->beginTransaction();

            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'nasabah')");
            $stmt->execute([$username, $hashed_password]);
            $user_id = $conn->lastInsertId();

            // Insert nasabah
            $stmt = $conn->prepare("INSERT INTO nasabah (user_id, nama_lengkap, NIK, NIP_NUPTK, alamat, no_telp) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $nama_lengkap, $NIK, $NIP_NUPTK, $alamat, $no_telp]);

            $conn->commit();
            $success = "Data nasabah berhasil ditambahkan!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }

if(isset($_POST['edit'])) {
    // Edit nasabah
    $id = $_POST['id'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $NIK = $_POST['NIK'];
    $NIP_NUPTK = $_POST['NIP_NUPTK'];
    $alamat = $_POST['alamat'];
    $no_telp = $_POST['no_telp'];

    try {
        $conn->beginTransaction();

        // Update username in users table
        $stmt = $conn->prepare("SELECT user_id FROM nasabah WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetch(PDO::FETCH_ASSOC)['user_id'];

        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$username, $user_id]);

        // Update password if provided
        if(!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
        }

        // Update nasabah details
        $stmt = $conn->prepare("UPDATE nasabah SET nama_lengkap = ?, NIK = ?, NIP_NUPTK = ?, alamat = ?, no_telp = ? WHERE id = ?");
        $stmt->execute([$nama_lengkap, $NIK, $NIP_NUPTK, $alamat, $no_telp, $id]);

        $conn->commit();
        $success = "Data nasabah berhasil diupdate!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
    
    if(isset($_POST['hapus'])) {
        // Hapus nasabah
        $id = $_POST['id'];
        
        // Hapus juga user terkait
        $stmt = $conn->prepare("SELECT user_id FROM nasabah WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetch(PDO::FETCH_ASSOC)['user_id'];
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $success = "Data nasabah berhasil dihapus!";
    }
}

// Ambil data nasabah
$stmt = $conn->query("SELECT n.*, u.username FROM nasabah n JOIN users u ON n.user_id = u.id ORDER BY n.nama_lengkap");
$nasabah_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h5><i class="icon fas fa-ban"></i> Error!</h5>
        <?= $error ?>
    </div>
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

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Anggota</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahModal">
                        <i class="fas fa-plus"></i> Tambah Anggota
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-nasabah">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-nik">NIK</th>
                        <th class="col-nama">Nama Lengkap</th>
                        <th class="col-nip">NIP/NUPTK</th>
                        <th class="col-telp">No Telp</th>
                        <th class="col-saldo">Saldo</th>
                        <th class="col-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($nasabah_list as $index => $nasabah): ?>
                    <tr>
                        <td class="col-no"><?= $index + 1 ?></td>
                        <td class="col-nik"><?= $nasabah['NIK'] ?></td>
                        <td class="col-nama"><?= $nasabah['nama_lengkap'] ?></td>
                        <td class="col-nip"><?= $nasabah['NIP_NUPTK'] ?></td>
                        <td class="col-telp"><?= $nasabah['no_telp'] ?></td>
                        <td class="col-saldo">Rp <?= number_format($nasabah['saldo'], 0, ',', '.') ?></td>
                        <td class="col-aksi">
                            <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?= $nasabah['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="d-inline delete-form">
                                <input type="hidden" name="id" value="<?= $nasabah['id'] ?>">
                                <button type="button" class="btn btn-danger btn-sm delete-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal<?= $nasabah['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Anggota</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
<form method="POST">
    <div class="modal-body">
        <input type="hidden" name="id" value="<?= $nasabah['id'] ?>">
        <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control" value="<?= $nasabah['username'] ?>" required>
        </div>
        <div class="mb-3">
            <label>Password (kosongkan jika tidak ingin mengubah)</label>
            <input type="password" name="password" class="form-control" value="">
        </div>
        <div class="mb-3">
            <label>Nama Lengkap</label>
            <input type="text" name="nama_lengkap" class="form-control" value="<?= $nasabah['nama_lengkap'] ?>" required>
        </div>
        <div class="mb-3">
            <label>NIK</label>
            <input type="text" name="NIK" class="form-control" value="<?= $nasabah['NIK'] ?>" required>
        </div>
        <div class="mb-3">
            <label>NIP/NUPTK</label>
            <input type="text" name="NIP_NUPTK" class="form-control" value="<?= $nasabah['NIP_NUPTK'] ?>" required>
        </div>
        <div class="mb-3">
            <label>Alamat</label>
            <textarea name="alamat" class="form-control"><?= $nasabah['alamat'] ?></textarea>
        </div>
        <div class="mb-3">
            <label>No Telp</label>
            <input type="text" name="no_telp" class="form-control" value="<?= $nasabah['no_telp'] ?>">
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
                <h5 class="modal-title">Tambah Anggota</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>NIK</label>
                        <input type="text" name="NIK" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>NIP/NUPTK</label>
                        <input type="text" name="NIP_NUPTK" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Alamat</label>
                        <textarea name="alamat" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>No Telp</label>
                        <input type="text" name="no_telp" class="form-control">
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
                text: 'Data nasabah akan dihapus secara permanen!',
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
});
</script>

<?php include '../includes/footer.php'; ?>
