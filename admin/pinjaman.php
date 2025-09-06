<?php
session_start();
require_once '../config/database.php';

// Array for Indonesian month names
$bulanIndo = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle loan approval/rejection
if(isset($_POST['action']) && isset($_POST['pinjaman_id'])) {
    $pinjaman_id = $_POST['pinjaman_id'];
    $action = $_POST['action'];

    if($action == 'approve') {
        $status = 'disetujui';
    } elseif($action == 'reject') {
        $status = 'ditolak';
    }

    $stmt = $conn->prepare("UPDATE pinjaman SET status = ? WHERE id = ?");
    $stmt->execute([$status, $pinjaman_id]);

    header("Location: pinjaman.php?success=1");
    exit();
}

// Handle angsuran input
if(isset($_POST['submit_angsuran'])) {
    $pinjaman_id = $_POST['pinjaman_id'];
    $jumlah_principal = $_POST['jumlah']; // Principal payment amount
    $jasa_angsuran = $_POST['jasa_angsuran'] ?? 0; // Interest/service fee payment
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $keterangan = $_POST['keterangan'] ?? '';

    try {
        // Get current loan details
        $stmt = $conn->prepare("SELECT * FROM pinjaman WHERE id = ?");
        $stmt->execute([$pinjaman_id]);
        $pinjaman = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$pinjaman) {
            throw new Exception("Pinjaman tidak ditemukan");
        }

        // Handle interest/service fee payment (jasa_angsuran)
        if($jasa_angsuran > 0) {
            // This is a service fee payment, add to lama_angsuran (service fee total)
            $new_lama_angsuran = $pinjaman['lama_angsuran'] + $jasa_angsuran;

            // Update pinjaman table for service fee
            $stmt = $conn->prepare("UPDATE pinjaman SET lama_angsuran = ? WHERE id = ?");
            $stmt->execute([$new_lama_angsuran, $pinjaman_id]);

            // Insert angsuran record for service fee
            $keterangan_jasa = $keterangan . " Pembayaran Jasa Pinjaman";
            $stmt = $conn->prepare("INSERT INTO angsuran (pinjaman_id, jumlah, tanggal_bayar, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pinjaman_id, $jasa_angsuran, $tanggal_bayar, $keterangan_jasa]);
        }

        // Handle principal payment (pokok pinjaman)
        if($jumlah_principal > 0) {
            // Get current remaining loan amount
            $stmt = $conn->prepare("SELECT total_angsuran FROM pinjaman WHERE id = ?");
            $stmt->execute([$pinjaman_id]);
            $current_loan = $stmt->fetch(PDO::FETCH_ASSOC);

            $current_remaining = $current_loan['total_angsuran'];
            $new_total_angsuran = $current_remaining - $jumlah_principal;

            // Ensure total doesn't go below 0
            if($new_total_angsuran <= 0) {
                $new_total_angsuran = 0;
                $status = 'lunas';
            } else {
                $status = 'disetujui'; // Keep as approved
            }

            // Update pinjaman table for principal payment
            $stmt = $conn->prepare("UPDATE pinjaman SET total_angsuran = ?, status = ? WHERE id = ?");
            $stmt->execute([$new_total_angsuran, $status, $pinjaman_id]);

            // Insert angsuran record for principal payment
            $keterangan_principal = $keterangan . " Pinjaman Pokok";
            $stmt = $conn->prepare("INSERT INTO angsuran (pinjaman_id, jumlah, tanggal_bayar, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pinjaman_id, $jumlah_principal, $tanggal_bayar, $keterangan_principal]);
        }

        $_SESSION['success_message'] = "Angsuran berhasil dicatat!";
        header("Location: pinjaman.php?success=1");
        exit();

    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
        header("Location: pinjaman.php?error=1");
        exit();
    } catch(Exception $e) {
        $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: pinjaman.php?error=1");
        exit();
    }
}

// Get current interest rate from admin settings
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'interest_rate'");
$stmt->execute();
$current_interest_rate = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'];

// Handle loan application
if(isset($_POST['submit_pinjaman'])) {
    // Sanitize and validate inputs
    $nasabah_id = filter_var($_POST['nasabah_id'], FILTER_SANITIZE_NUMBER_INT);
    $no_pinjaman = trim($_POST['no_pinjaman']);
    $jumlah = filter_var($_POST['jumlah'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $bunga = $current_interest_rate; // Use admin-set interest rate
    $tanggal_pinjam = date('Y-m-d');

    // Validation
    $errors = [];

    if(empty($nasabah_id)) {
        $errors[] = "Silakan pilih nasabah";
    }

    if(empty($no_pinjaman)) {
        $errors[] = "No. Pinjaman harus diisi";
    } else {
        // Check if no_pinjaman is unique
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pinjaman WHERE no_pinjaman = ?");
        $stmt->execute([$no_pinjaman]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if($count > 0) {
            $errors[] = "No. Pinjaman sudah digunakan, silakan gunakan nomor lain.";
        }
    }

    if(empty($jumlah) || $jumlah < 100000) {
        $errors[] = "Jumlah pinjaman minimal Rp 100.000";
    }

    if($jumlah > 50000000) { // Max 50 million
        $errors[] = "Jumlah pinjaman maksimal Rp 50.000.000";
    }

    // Check if member has pending loan applications
    $stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM pinjaman WHERE nasabah_id = ? AND status = 'pending'");
    $stmt->execute([$nasabah_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

    if($pending_count > 0) {
        $errors[] = "Nasabah masih memiliki pengajuan pinjaman yang belum diproses. Harap tunggu approval dari admin.";
    }

    if(empty($errors)) {
        // Calculate loan details or use manual jasa_layanan if provided
        $manual_jasa_layanan = filter_var($_POST['jasa_layanan'] ?? null, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if($manual_jasa_layanan !== null && is_numeric($manual_jasa_layanan) && $manual_jasa_layanan >= 0) {
            $jasa_layanan = $manual_jasa_layanan;
        } else {
            $bunga_decimal = $bunga / 100;
            $jasa_layanan = $jumlah * $bunga_decimal;
        }

        // Get jasa_angsuran_per_bulan from user input
        $jasa_angsuran_per_bulan = filter_var($_POST['jasa_angsuran_per_bulan'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if($jasa_angsuran_per_bulan === null || !is_numeric($jasa_angsuran_per_bulan) || $jasa_angsuran_per_bulan < 0) {
            $jasa_angsuran_per_bulan = 0; // Default to 0 if invalid
        }

        $total_angsuran = $jumlah; // Only principal amount, separate from jasa layanan
        $angsuran_per_bulan = $total_angsuran; // One-time payment for principal

        try {
            $stmt = $conn->prepare("INSERT INTO pinjaman (no_pinjaman, nasabah_id, jumlah, bunga, lama_angsuran, jasa_angsuran_per_bulan, total_angsuran, angsuran_per_bulan, tanggal_pinjam) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$no_pinjaman, $nasabah_id, $jumlah, $bunga, $jasa_layanan, $jasa_angsuran_per_bulan, $total_angsuran, $angsuran_per_bulan, $tanggal_pinjam]);

            if($result) {
                $_SESSION['success_message'] = "Pengajuan pinjaman berhasil dikirim! Menunggu approval dari admin.";
                header("Location: pinjaman.php?success=1");
                exit();
            } else {
                $_SESSION['error_message'] = "Gagal menyimpan data pinjaman ke database.";
                header("Location: pinjaman.php?error=1");
                exit();
            }
        } catch(PDOException $e) {
            error_log("Database Error in pinjaman.php: " . $e->getMessage());
            $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan data: " . $e->getMessage();
            header("Location: pinjaman.php?error=1");
            exit();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header("Location: pinjaman.php?error=1");
        exit();
    }
}

// Check for success/error messages from GET parameters
if(isset($_GET['success']) && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_GET['error']) && isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get all loans with member details
$stmt = $conn->query("
    SELECT p.*, n.nama_lengkap
    FROM pinjaman p
    JOIN nasabah n ON p.nasabah_id = n.id
    ORDER BY p.created_at DESC
");
$pinjaman_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate loan statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM pinjaman");
$total_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT COUNT(*) as pending FROM pinjaman WHERE status = 'pending'");
$pending_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];

$stmt = $conn->query("SELECT COUNT(*) as approved FROM pinjaman WHERE status = 'disetujui'");
$approved_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['approved'];

$stmt = $conn->query("SELECT SUM(jumlah) as total_amount FROM pinjaman WHERE status = 'disetujui'");
$total_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'];

// Get list of nasabah for dropdown
$stmt = $conn->query("SELECT id, nama_lengkap FROM nasabah ORDER BY nama_lengkap ASC");
$nasabah_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = "Kelola Pinjaman";
include '../includes/header.php';
?>

<!-- <div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3 class="mb-4">Statistik Pinjaman</h3>
                <div class="row">
                    <div class="col-md-3">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $total_pinjaman; ?></h3>
                                <p>Total Pinjaman</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?php echo $pending_pinjaman; ?></h3>
                                <p>Menunggu Approval</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo $approved_pinjaman; ?></h3>
                                <p>Disetujui</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></h3>
                                <p>Total Nilai Pinjaman</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> -->

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ajukan Pinjaman Baru</h3>
            </div>
            <div class="card-body">
                <?php if(isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="pinjamanForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nasabah_id">Pilih Anggota <span class="text-danger">*</span></label>
                                <select class="form-control" id="nasabah_id" name="nasabah_id" required>
                <option value="">-- Pilih Anggota --</option>
                <?php foreach($nasabah_list as $nasabah): ?>
                <option value="<?php echo $nasabah['id']; ?>">
                    <?php echo htmlspecialchars($nasabah['nama_lengkap']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="no_pinjaman">No. Pinjaman <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="no_pinjaman" name="no_pinjaman" required>
            <small class="form-text text-muted">Masukkan nomor pinjaman secara manual</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="jumlah">Jumlah Pinjaman (Rp) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="jumlah" name="jumlah" min="100000" max="50000000" step="10000" required>
            <small class="form-text text-muted">Minimal: Rp 100.000 | Maksimal: Rp 50.000.000</small>
        </div>
    </div>
</div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bunga</label>
                                <input type="text" class="form-control" value="<?php echo $current_interest_rate; ?>%" readonly>
                                <small class="form-text text-muted">Bunga sesuai pengaturan admin</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jasa Pinjaman</label>
                            <input type="number" class="form-control" id="jasa_layanan" name="jasa_layanan" min="0" step="0.01" placeholder="Masukkan Jasa Layanan" required>
                            <small class="form-text text-muted">Masukkan jasa layanan secara manual</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="jasa_angsuran_per_bulan">Jasa Pinjaman Per Bulan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="jasa_angsuran_per_bulan" name="jasa_angsuran_per_bulan" min="0" step="0.01" placeholder="Masukkan Jasa Angsuran Per Bulan" required>
                                <small class="form-text text-muted">Jasa angsuran yang dibayar setiap bulan</small>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="calculationResult" style="display: none;">
                        <div class="col-md-12">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Perhitungan Pinjaman</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <h6>Jumlah Pinjaman</h6>
                                                <p class="text-primary font-weight-bold" id="displayJumlah">-</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <h6>Jasa Pinjaman</h6>
                                                <p class="text-warning font-weight-bold" id="displayJasaLayanan">-</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <h6>Total Pinjaman</h6>
                                                <p class="text-success font-weight-bold" id="displayTotal">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" id="calculateBtn">
                                <i class="fas fa-calculator"></i> Hitung Simulasi
                            </button>
                            <button type="button" class="btn btn-success" id="submitBtn" data-toggle="modal" data-target="#confirmModal" disabled>
                                <i class="fas fa-paper-plane"></i> Ajukan Pinjaman
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Pinjaman</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
<th>ID</th>
<th>No. Pinjaman</th>
<th>Nama Anggota</th>
<th>Jasa Pinjaman Tetap</th>
<th>Jumlah Pinjaman</th>
<th>Bunga (%)</th>
<th>Jumlah Jasa Pinjaman</th>
<th>Sisa Pinjaman</th>
<th>Status</th>
<th>Bulan</th>
<th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pinjaman_list as $pinjaman): ?>
                        <?php
                        // Remaining amount is now stored in total_angsuran for approved loans
                        $remaining = $pinjaman['total_angsuran'];
                        ?>
                        <tr>
<td><?php echo $pinjaman['id']; ?></td>
<td><?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?></td>
<td><?php echo htmlspecialchars($pinjaman['nama_lengkap']); ?></td>
<td>Rp <?php echo number_format($pinjaman['jasa_angsuran_per_bulan'], 0, ',', '.'); ?></td>
<td>Rp <?php echo number_format($pinjaman['jumlah'], 0, ',', '.'); ?></td>
<td><?php echo $pinjaman['bunga']; ?>%</td>
<td>Rp <?php echo number_format($pinjaman['lama_angsuran'], 0, ',', '.'); ?></td>
<td>Rp <?php echo number_format($remaining, 0, ',', '.'); ?></td>
<td>
    <?php
    $status_class = '';
    $status_text = '';
    switch($pinjaman['status']) {
        case 'pending':
            $status_class = 'badge-warning';
            $status_text = 'Menunggu';
            break;
        case 'disetujui':
            $status_class = 'badge-success';
            $status_text = 'Disetujui';
            break;
        case 'ditolak':
            $status_class = 'badge-danger';
            $status_text = 'Ditolak';
            break;
        case 'lunas':
            $status_class = 'badge-info';
            $status_text = 'Lunas';
            break;
    }
    ?>
    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
</td>
<td>
    <?php
        $monthNum = date('n', strtotime($pinjaman['tanggal_pinjam']));
        echo $bulanIndo[$monthNum];
    ?>
</td>
<td>
                                <?php if($pinjaman['status'] == 'pending'): ?>
                                <form method="POST" class="d-inline" id="actionForm<?php echo $pinjaman['id']; ?>">
                                    <input type="hidden" name="pinjaman_id" value="<?php echo $pinjaman['id']; ?>">
                                    <input type="hidden" name="action" id="actionInput<?php echo $pinjaman['id']; ?>" value="">
                                    <button type="button" class="btn btn-success btn-sm action-btn" data-action="approve" data-pinjaman-id="<?php echo $pinjaman['id']; ?>">
                                        <i class="fas fa-check"></i> Setujui
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm action-btn" data-action="reject" data-pinjaman-id="<?php echo $pinjaman['id']; ?>">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                </form>
                                <?php elseif($pinjaman['status'] == 'disetujui'): ?>
                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#historyModal<?php echo $pinjaman['id']; ?>">
                                    <i class="fas fa-history"></i> Riwayat
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#angsuranModal<?php echo $pinjaman['id']; ?>">
                                    <i class="fas fa-plus"></i> Input Angsuran
                                </button>
                                <?php else: ?>
                                <span class="text-muted">Sudah diproses</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Angsuran History and Input Modals -->
<?php foreach($pinjaman_list as $pinjaman): ?>
<?php
// Get angsuran history for this loan
$stmt = $conn->prepare("SELECT * FROM angsuran WHERE pinjaman_id = ? ORDER BY tanggal_bayar DESC");
$stmt->execute([$pinjaman['id']]);
$angsuran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total paid
$total_paid = 0;
foreach($angsuran_list as $angsuran) {
    $total_paid += $angsuran['jumlah'];
}
$remaining = $pinjaman['total_angsuran'];
?>

<!-- Angsuran History Modal -->
<div class="modal fade" id="historyModal<?php echo $pinjaman['id']; ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Riwayat Pembayaran Pinjaman #<?php echo htmlspecialchars($pinjaman['nama_lengkap']); ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Jasa Pinjaman:</strong> Rp <?php echo number_format($pinjaman['jasa_angsuran_per_bulan'], 0, ',', '.'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Jumlah Pinjaman Pokok:</strong> Rp <?php echo number_format($pinjaman['jumlah'], 0, ',', '.'); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Total Jasa Pinjaman:</strong> Rp <?php echo number_format($pinjaman['lama_angsuran'], 0, ',', '.'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Sisa Pinjaman Pokok:</strong> Rp <?php echo number_format($remaining, 0, ',', '.'); ?>
                    </div>
                </div>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tanggal Bayar</th>
                            <th>Jumlah Pinjaman</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($angsuran_list)): ?>
                        <tr>
                            <td colspan="3" class="text-center">Belum ada pembayaran angsuran</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($angsuran_list as $angsuran): ?>
                        <tr>
<td>
    <?php
        $monthNum = date('n', strtotime($angsuran['tanggal_bayar']));
        echo $bulanIndo[$monthNum];
    ?>
</td>
                            <td>Rp <?php echo number_format($angsuran['jumlah'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($angsuran['keterangan']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Angsuran Input Modal -->
<div class="modal fade" id="angsuranModal<?php echo $pinjaman['id']; ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Input Pembayaran Pinjaman #<?php echo $pinjaman['id']; ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="pinjaman_id" value="<?php echo $pinjaman['id']; ?>">

                    <div class="form-group">
                        <label for="jumlah">Jumlah Pinjaman (Rp)</label>
                        <input type="number" class="form-control" id="jumlah" name="jumlah" min="0" step="0.01" required>
                        <small class="form-text text-muted">Angsuran per bulan: Rp <?php echo number_format($pinjaman['angsuran_per_bulan'], 0, ',', '.'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="jasa_angsuran">Jasa Angsuran (Rp)</label>
                        <input type="number" class="form-control" id="jasa_angsuran" name="jasa_angsuran" min="0" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_bayar">Tanggal Bayar</label>
                        <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                 

                    <div class="form-group">
                        <label for="keterangan">Keterangan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <strong>Sisa Pinjaman:</strong><br>
                            <span class="text-danger font-weight-bold">Rp <?php echo number_format($remaining, 0, ',', '.'); ?></span>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="submit" name="submit_angsuran" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Angsuran
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endforeach; ?>

<!-- Confirmation Modal for Loan Approval/Rejection -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" role="dialog" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center">
        <div class="mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 64 64">
            <circle cx="32" cy="32" r="30" stroke="#f6a95a" stroke-width="4"/>
            <path stroke="#f6a95a" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M32 18v18M32 46h.01"/>
          </svg>
        </div>
        <h4 class="font-weight-bold mb-2">Yakin ingin menghapus?</h4>
        <p class="text-muted mb-4">Data nasabah akan dihapus secara permanen!</p>
        <div>
          <button type="button" class="btn btn-danger mr-2" data-dismiss="modal">Ya, Hapus!</button>
          <button type="button" class="btn btn-primary" data-dismiss="modal">Batal</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Confirmation Modal for Loan Application -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center">
        <div class="mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 64 64">
            <circle cx="32" cy="32" r="30" stroke="#28a745" stroke-width="4"/>
            <path stroke="#28a745" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20 32l8 8 16-16"/>
          </svg>
        </div>
        <h4 class="font-weight-bold mb-2">Konfirmasi Pengajuan Pinjaman</h4>
        <p class="text-muted mb-4">Apakah Anda yakin ingin mengajukan pinjaman ini? Data akan disimpan dan menunggu approval dari admin.</p>
        <div>
          <button type="button" class="btn btn-success mr-2" id="confirmSubmitBtn">
            <i class="fas fa-paper-plane"></i> Ya, Ajukan Pinjaman
          </button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPinjamanId = null;
    let currentAction = null;

    const confirmModal = $('#confirmActionModal');

    document.querySelectorAll('.action-btn').forEach(button => {
        button.addEventListener('click', function() {
            currentPinjamanId = this.getAttribute('data-pinjaman-id');
            currentAction = this.getAttribute('data-action');

            let modalTitle = '';
            let modalMessage = '';
            let confirmButtonText = '';
            let confirmButtonClass = '';

            if(currentAction === 'approve') {
                modalTitle = 'Yakin ingin menyetujui?';
                modalMessage = 'Data pinjaman akan disetujui secara permanen!';
                confirmButtonText = 'Ya, Setuju!';
                confirmButtonClass = 'btn btn-success mr-2';
            } else if(currentAction === 'reject') {
                modalTitle = 'Yakin ingin menolak?';
                modalMessage = 'Data pinjaman akan ditolak secara permanen!';
                confirmButtonText = 'Ya, Tolak!';
                confirmButtonClass = 'btn btn-danger mr-2';
            }

            // Update modal content dynamically
            const modalBody = confirmModal.find('.modal-body');
            modalBody.html(`
                <div class="mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="none" viewBox="0 0 64 64">
                        <circle cx="32" cy="32" r="30" stroke="#f6a95a" stroke-width="4"/>
                        <path stroke="#f6a95a" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M32 18v18M32 46h.01"/>
                    </svg>
                </div>
                <h4 class="font-weight-bold mb-2">${modalTitle}</h4>
                <p class="text-muted mb-4">${modalMessage}</p>
                <div>
                    <button type="button" class="${confirmButtonClass}" id="confirmActionBtn">${confirmButtonText}</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Batal</button>
                </div>
            `);

            // Attach event listener to the confirm button dynamically
            modalBody.find('#confirmActionBtn').on('click', function() {
                if(currentPinjamanId && currentAction) {
                    const form = document.getElementById(`actionForm${currentPinjamanId}`);
                    const actionInput = document.getElementById(`actionInput${currentPinjamanId}`);
                    actionInput.value = currentAction;
                    form.submit();
                }
            });

            confirmModal.modal('show');
        });
    });

    // Loan Application Form Handling
    const pinjamanForm = document.getElementById('pinjamanForm');
    const calculateBtn = document.getElementById('calculateBtn');
    const submitBtn = document.getElementById('submitBtn');
    const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
    const calculationResult = document.getElementById('calculationResult');

    // Calculate loan details
    calculateBtn.addEventListener('click', function() {
        const jumlah = parseFloat(document.getElementById('jumlah').value);
        const jasaLayananInput = document.getElementById('jasa_layanan').value;
        const bungaRate = <?php echo $current_interest_rate; ?>; // Get from PHP

        if (!jumlah) {
            alert('Silakan isi jumlah pinjaman!');
            return;
        }

        let jasaLayanan = 0;
        if(jasaLayananInput && !isNaN(jasaLayananInput) && jasaLayananInput >= 0) {
            jasaLayanan = parseFloat(jasaLayananInput);
        } else {
            jasaLayanan = jumlah * (bungaRate / 100);
        }

        const totalPembayaran = jumlah + jasaLayanan;

        // Display results
        document.getElementById('displayJumlah').textContent = 'Rp ' + formatNumber(jumlah);
        document.getElementById('displayJasaLayanan').textContent = 'Rp ' + formatNumber(jasaLayanan);
        document.getElementById('displayTotal').textContent = 'Rp ' + formatNumber(totalPembayaran);

        // Show calculation result
        calculationResult.style.display = 'block';

        // Enable submit button
        submitBtn.disabled = false;
    });

    // Handle form submission confirmation
    confirmSubmitBtn.addEventListener('click', function() {
        // Add hidden input for submit_pinjaman
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'submit_pinjaman';
        hiddenInput.value = '1';
        pinjamanForm.appendChild(hiddenInput);

        // Submit the form
        pinjamanForm.submit();
    });

    // Format number function
    function formatNumber(num) {
        return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    // Reset calculation when form inputs change
    document.getElementById('jumlah').addEventListener('input', resetCalculation);

    function resetCalculation() {
        calculationResult.style.display = 'none';
        submitBtn.disabled = true;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
