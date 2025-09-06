<?php
session_start();
require_once '../config/database.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'nasabah') {
    header("Location: ../login.php");
    exit();
}

// Ambil data nasabah
$stmt = $conn->prepare("SELECT * FROM nasabah WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

// Update profil
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama_lengkap = $_POST['nama_lengkap'];
    $NIP_NUPTK = $_POST['NIP_NUPTK'];
    $alamat = $_POST['alamat'];
    $no_telp = $_POST['no_telp'];

    $stmt = $conn->prepare("UPDATE nasabah SET nama_lengkap = ?, NIP_NUPTK = ?, alamat = ?, no_telp = ? WHERE user_id = ?");
    $stmt->execute([$nama_lengkap, $NIP_NUPTK, $alamat, $no_telp, $_SESSION['user_id']]);
    
    $success = "Profil berhasil diupdate!";
    
    // Refresh data
    $stmt = $conn->prepare("SELECT * FROM nasabah WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Update password
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Cek password lama
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(password_verify($current_password, $user['password'])) {
        if($new_password != $confirm_password) {
            $error_password = "Password baru tidak cocok!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            $success_password = "Password berhasil diupdate!";
        }
    } else {
        $error_password = "Password lama salah!";
    }
}
?>

<?php include '../includes/header.php'; ?>

<?php if(isset($success)): ?>
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h5><i class="icon fas fa-check"></i> Sukses!</h5>
        <?= $success ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-3"> <!-- Make this col-md-6 and add mb-3 for spacing -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Data Diri</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>NIK</label>
                        <input type="text" class="form-control" value="<?= $nasabah['NIK'] ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= $nasabah['nama_lengkap'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>NIP/NUPTK</label>
                        <input type="text" name="NIP_NUPTK" class="form-control" value="<?= $nasabah['NIP_NUPTK'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="alamat" class="form-control" rows="3"><?= $nasabah['alamat'] ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>No Telp</label>
                        <input type="text" name="no_telp" class="form-control" value="<?= $nasabah['no_telp'] ?>">
                    </div>
                    <button type="submit" name="update_profil" class="btn btn-primary">Update Profil</button>
                </form>
            </div>
        </div>
    </div>            

    <div class="col-md-6"> <!-- This column will hold the password and stats cards -->
        <div class="row"> <!-- Nested row for password and stats cards -->
            <div class="col-md-12 mb-3"> <!-- Take full width of its parent col-md-6 -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Update Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if(isset($success_password)): ?>
                            <script>
                                alert("<?= $success_password ?>");
                            </script>
                        <?php endif; ?>

                        <?php if(isset($error_password)): ?>
                            <div class="alert alert-danger alert-dismissible">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <i class="icon fas fa-ban"></i> Error!
                                <?= $error_password ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-group">
                                <label>Password Lama</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Password Baru</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" name="update_password" class="btn btn-warning">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-12"> <!-- Take full width of its parent col-md-6 -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Statistik Saya</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="description-block">
                                    <h5 class="description-header">Rp <?= number_format($nasabah['saldo'], 0, ',', '.') ?></h5>
                                    <span class="description-text">SALDO SIMPANAN</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <?php
                                // Hitung total pinjaman
                                $stmt = $conn->prepare("SELECT SUM(jumlah) as total FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
                                $stmt->execute([$nasabah['id']]);
                                $total_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                ?>
                                <div class="description-block">
                                    <h5 class="description-header">Rp <?= number_format($total_pinjaman, 0, ',', '.') ?></h5>
                                    <span class="description-text">TOTAL PINJAMAN</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- End of nested row -->
    </div> <!-- End of col-md-6 for password and stats -->
</div> <!-- End of main row -->

<?php include '../includes/footer.php'; ?>