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

// Query simpanan sukarela for logged in nasabah
$stmt = $conn->prepare("SELECT s.* FROM simpanan s WHERE s.jenis = 'sukarela' AND s.nasabah_id = ? ORDER BY s.tanggal DESC, s.created_at DESC");
$stmt->execute([$nasabah['id']]);
$simpanan_sukarela_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query simpanan wajib for logged in nasabah
$stmt = $conn->prepare("SELECT s.* FROM simpanan s WHERE s.jenis = 'wajib' AND s.nasabah_id = ? ORDER BY s.tanggal DESC, s.created_at DESC");
$stmt->execute([$nasabah['id']]);
$simpanan_wajib_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query simpanan pokok for logged in nasabah
$stmt = $conn->prepare("SELECT s.* FROM simpanan s WHERE s.jenis = 'pokok' AND s.nasabah_id = ? ORDER BY s.tanggal DESC, s.created_at DESC");
$stmt->execute([$nasabah['id']]);
$simpanan_pokok_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_sukarela = 0;
foreach($simpanan_sukarela_list as $simpanan) {
    $total_sukarela += $simpanan['jumlah'];
}

$total_wajib = 0;
foreach($simpanan_wajib_list as $simpanan) {
    $total_wajib += $simpanan['jumlah'];
}

$total_pokok = 0;
foreach($simpanan_pokok_list as $simpanan) {
    $total_pokok += $simpanan['jumlah'];
}
?>

<?php include '../includes/header.php'; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ringkasan Simpanan</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>Rp <?= number_format($total_pokok, 0, ',', '.') ?></h3>
                                <p>Simpanan Pokok</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-piggy-bank"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>Rp <?= number_format($total_wajib, 0, ',', '.') ?></h3>
                                <p>Simpanan Wajib</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>Rp <?= number_format($total_sukarela, 0, ',', '.') ?></h3>
                                <p>Simpanan Sukarela</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Simpanan Pokok -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Simpanan Pokok</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($simpanan_pokok_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada data simpanan pokok</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($simpanan_pokok_list as $index => $simpanan): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= date('d/m/Y', strtotime($simpanan['tanggal'])) ?></td>
                                    <td>
                                        <?php if($simpanan['jumlah'] > 0): ?>
                                            <span class="badge badge-success">Debit</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Kredit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>Rp <?= number_format(abs($simpanan['jumlah']), 0, ',', '.') ?></td>
                                    <td><?= $simpanan['keterangan'] ?: '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Simpanan Wajib -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Simpanan Wajib</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($simpanan_wajib_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada data simpanan wajib</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($simpanan_wajib_list as $index => $simpanan): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= date('d/m/Y', strtotime($simpanan['tanggal'])) ?></td>
                                    <td>
                                        <?php if($simpanan['jumlah'] > 0): ?>
                                            <span class="badge badge-success">Debit</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Kredit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>Rp <?= number_format(abs($simpanan['jumlah']), 0, ',', '.') ?></td>
                                    <td><?= $simpanan['keterangan'] ?: '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Simpanan Sukarela -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Simpanan Sukarela</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($simpanan_sukarela_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Belum ada data simpanan sukarela</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($simpanan_sukarela_list as $index => $simpanan): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= date('d/m/Y', strtotime($simpanan['tanggal'])) ?></td>
                                    <td>
                                        <?php if($simpanan['jumlah'] > 0): ?>
                                            <span class="badge badge-success">Debit</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Kredit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>Rp <?= number_format(abs($simpanan['jumlah']), 0, ',', '.') ?></td>
                                    <td><?= $simpanan['keterangan'] ?: '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
