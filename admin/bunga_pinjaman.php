<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Update interest rate
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_interest_rate'])) {
    $interest_rate = $_POST['interest_rate'];

    if(is_numeric($interest_rate) && $interest_rate >= 0 && $interest_rate <= 50) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'interest_rate'");
        $stmt->execute([$interest_rate]);
        $success_interest = "Bunga pinjaman berhasil diupdate!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error_interest = "Bunga harus berupa angka antara 0-50%!";
    }
}

// Get current interest rate
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'interest_rate'");
$stmt->execute();
$current_interest_rate = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'];
?>

<?php
$page_title = "Pengaturan Bunga Pinjaman";
include '../includes/header.php';
?>

    <?php if(isset($success_interest)): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php echo $success_interest; ?>
        </div>
    <?php endif; ?>

    <?php if(isset($error_interest)): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php echo $error_interest; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Pengaturan Bunga</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="interest_rate">Bunga Pinjaman per Tahun (%)</label>
                            <input type="number" class="form-control" id="interest_rate" name="interest_rate"
                                   step="0.01" min="0" max="50" value="<?php echo htmlspecialchars($current_interest_rate); ?>" required>
                            <small class="form-text text-muted">
                                Bunga yang akan diterapkan pada semua pengajuan pinjaman baru.<br>
                                Rentang: 0% - 50%
                            </small>
                        </div>
                        <button type="submit" name="update_interest_rate" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Bunga
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Informasi</h5>
                </div>
                <div class="card-body">
                    <h6>Bunga Saat Ini: <span class="badge badge-primary"><?php echo htmlspecialchars($current_interest_rate); ?>%</span></h6>
                    <hr>
                    <p><strong>Catatan:</strong></p>
                    <ul>
                        <li>Bunga ini akan digunakan untuk menghitung total pinjaman pada pengajuan baru</li>
                        <li>Perubahan bunga tidak akan mempengaruhi pinjaman yang sudah disetujui</li>
                        <li>Pastikan untuk menginformasikan perubahan bunga kepada nasabah</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>


<?php include '../includes/footer.php'; ?>
