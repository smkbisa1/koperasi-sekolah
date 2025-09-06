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

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'nasabah') {
    header("Location: ../login.php");
    exit();
}

// Get member data
$stmt = $conn->prepare("SELECT * FROM nasabah WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current interest rate from admin settings
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'interest_rate'");
$stmt->execute();
$current_interest_rate = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'];



// Check for success/error messages from GET parameters
if(isset($_GET['success']) && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_GET['error']) && isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get member's loan history
$stmt = $conn->prepare("SELECT * FROM pinjaman WHERE nasabah_id = ? ORDER BY created_at DESC");
$stmt->execute([$nasabah['id']]);
$pinjaman_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get loan statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pinjaman WHERE nasabah_id = ?");
$stmt->execute([$nasabah['id']]);
$total_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as aktif FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
$stmt->execute([$nasabah['id']]);
$pinjaman_aktif = $stmt->fetch(PDO::FETCH_ASSOC)['aktif'];

$stmt = $conn->prepare("SELECT SUM(jumlah) as total_pinjam FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
$stmt->execute([$nasabah['id']]);
$total_pinjam = $stmt->fetch(PDO::FETCH_ASSOC)['total_pinjam'];
?>

<?php
$page_title = "Pinjaman Saya";
include '../includes/header.php';
?>

<?php if(isset($error_message)): ?>
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- <div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3 class="mb-4">Statistik Pinjaman</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $total_pinjaman; ?></h3>
                                <p>Total Pengajuan</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo $pinjaman_aktif; ?></h3>
                                <p>Pinjaman Aktif</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>Rp <?php echo number_format($total_pinjam, 0, ',', '.'); ?></h3>
                                <p>Total Pinjaman</p>
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
                <h3 class="card-title">Riwayat Pinjaman</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>No. Pinjaman</th>
                            <th>Jasa Pinjaman Tetap</th>
                            <th>Jumlah Pinjaman</th>
                            <th>Bunga (%)</th>
                            <th>Jumlah Jasa Pinjaman</th>
                            <th>Sisa Pinjaman</th>
                            <th>Status</th>
                            <th>Bulan</th>
                            <!-- <th>Aksi</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($pinjaman_list)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Belum ada data pinjaman</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($pinjaman_list as $pinjaman): ?>
                        <?php
                        // Get angsuran history for this loan
                        $stmt_angsuran = $conn->prepare("SELECT * FROM angsuran WHERE pinjaman_id = ? ORDER BY tanggal_bayar DESC");
                        $stmt_angsuran->execute([$pinjaman['id']]);
                        $angsuran_list = $stmt_angsuran->fetchAll(PDO::FETCH_ASSOC);

// Calculate total paid (include all payments: regular angsuran and angsuran jasa)
$total_paid = 0;
foreach($angsuran_list as $angsuran) {
    $total_paid += $angsuran['jumlah'];
}
                        $remaining = $pinjaman['total_angsuran'] - $total_paid;
                        ?>
                        <tr>
                            <td><?php echo $pinjaman['id']; ?></td>
                            <td><?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?></td>
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
                            <!-- <td>
                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#historyModal<?php echo $pinjaman['id']; ?>">
                                    <i class="fas fa-history"></i> Riwayat
                                </button>
                            </td> -->
                        </tr>

                        <!-- Angsuran History Row -->
                        <?php if($pinjaman['status'] == 'disetujui' || $pinjaman['status'] == 'lunas'): ?>
                        <tr>
                            <td colspan="10" class="p-0">
                                <div class="card m-2">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <i class="fas fa-history"></i> Riwayat Pembayaran Angsuran
                                            <button class="btn btn-sm btn-outline-primary float-right" type="button" data-toggle="collapse" data-target="#angsuranDetail<?php echo $pinjaman['id']; ?>">
                                                <i class="fas fa-chevron-down"></i> Detail
                                            </button>
                                        </h6>
                                    </div>
                                    <div class="collapse" id="angsuranDetail<?php echo $pinjaman['id']; ?>">
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <strong>No. Pinjaman:</strong><br>
                                                    <?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Total Pinjaman:</strong><br>
                                                    Rp <?php echo number_format($pinjaman['total_angsuran'], 0, ',', '.'); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Total Dibayar:</strong><br>
                                                    Rp <?php echo number_format($total_paid, 0, ',', '.'); ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Sisa:</strong><br>
                                                    Rp <?php echo number_format($remaining, 0, ',', '.'); ?>
                                                </div>
                                            </div>

                                            <?php if(empty($angsuran_list)): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i> Belum ada pembayaran angsuran untuk pinjaman ini.
                                            </div>
                                            <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped">
                                                    <thead>
                                                        <tr>
                                                    <th>No</th>
                                                    <th>Tanggal Bayar</th>
                                                    <th>Jumlah Angsuran</th>
                                                    <th>Total</th>
                                                    <th>Keterangan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $no = 1; foreach($angsuran_list as $angsuran): ?>
                                                        <tr>
                                                            <td><?php echo $no++; ?></td>
                                                            <td><?php echo date('d/m/Y', strtotime($angsuran['tanggal_bayar'])); ?></td>
                                                        <td>Rp <?php echo number_format($angsuran['jumlah'], 0, ',', '.'); ?></td>
                                                        <td>Rp <?php echo number_format($angsuran['jumlah'], 0, ',', '.'); ?></td>
                                                        <td><?php echo htmlspecialchars($angsuran['keterangan']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<?php foreach($pinjaman_list as $pinjaman): ?>
<?php
// Get angsuran history for this loan
$stmt_angsuran = $conn->prepare("SELECT * FROM angsuran WHERE pinjaman_id = ? ORDER BY tanggal_bayar DESC");
$stmt_angsuran->execute([$pinjaman['id']]);
$angsuran_list = $stmt_angsuran->fetchAll(PDO::FETCH_ASSOC);

// Calculate total paid (include all payments: regular angsuran and angsuran jasa)
$total_paid = 0;
foreach($angsuran_list as $angsuran) {
    $total_paid += $angsuran['jumlah'];
}
$remaining = $pinjaman['total_angsuran'] - $total_paid;
?>
<div class="modal fade" id="historyModal<?php echo $pinjaman['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel<?php echo $pinjaman['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel<?php echo $pinjaman['id']; ?>">
                    <i class="fas fa-history"></i> Riwayat Pembayaran Angsuran - <?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>No. Pinjaman:</strong><br>
                        <?php echo htmlspecialchars($pinjaman['no_pinjaman']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total Pinjaman:</strong><br>
                        Rp <?php echo number_format($pinjaman['total_angsuran'], 0, ',', '.'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Total Dibayar:</strong><br>
                        Rp <?php echo number_format($total_paid, 0, ',', '.'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Sisa:</strong><br>
                        Rp <?php echo number_format($remaining, 0, ',', '.'); ?>
                    </div>
                </div>

                <?php if(empty($angsuran_list)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Belum ada pembayaran angsuran untuk pinjaman ini.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal Bayar</th>
                                <th>Jumlah Angsuran</th>
                                <th>Total</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach($angsuran_list as $angsuran): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($angsuran['tanggal_bayar'])); ?></td>
                                <td>Rp <?php echo number_format($angsuran['jumlah'], 0, ',', '.'); ?></td>
                                <td>Rp <?php echo number_format($angsuran['jumlah'], 0, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($angsuran['keterangan']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>
