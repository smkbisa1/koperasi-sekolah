<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Hitung statistik untuk admin
if($_SESSION['role'] == 'admin') {
    $stmt = $conn->query("SELECT COUNT(*) as total_nasabah FROM nasabah");
    $total_nasabah = $stmt->fetch(PDO::FETCH_ASSOC)['total_nasabah'];
    
    $stmt = $conn->query("SELECT SUM(saldo) as total_simpanan FROM nasabah");
    $total_simpanan = $stmt->fetch(PDO::FETCH_ASSOC)['total_simpanan'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total_pinjaman FROM pinjaman WHERE status = 'disetujui'");
    $total_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['total_pinjaman'];

    // New query to get total jasa layanan (interest/service fee)
    $stmt = $conn->query("SELECT SUM(lama_angsuran) as total_jasa_layanan FROM pinjaman WHERE status = 'disetujui'");
    $total_jasa_layanan = $stmt->fetch(PDO::FETCH_ASSOC)['total_jasa_layanan'];

    // Query for monthly savings trend (last 6 months)
    $stmt = $conn->query("
        SELECT
            DATE_FORMAT(tanggal, '%Y-%m') as bulan,
            SUM(jumlah) as total_simpanan
        FROM simpanan
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
        ORDER BY bulan ASC
    ");
    $monthly_savings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Data untuk nasabah
        $stmt = $conn->prepare("SELECT * FROM nasabah WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("SELECT SUM(jumlah) as total_simpanan FROM simpanan WHERE nasabah_id = ? AND jenis = 'wajib'");
        $stmt->execute([$nasabah['id']]);
        $total_simpanan = $stmt->fetch(PDO::FETCH_ASSOC)['total_simpanan'];
        
        $stmt = $conn->prepare("SELECT SUM(jumlah) as total_pinjaman FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
        $stmt->execute([$nasabah['id']]);
        $total_pinjaman = $stmt->fetch(PDO::FETCH_ASSOC)['total_pinjaman'];

        // New query to get total jasa layanan for nasabah
        $stmt = $conn->prepare("SELECT SUM(lama_angsuran) as total_jasa_layanan FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
        $stmt->execute([$nasabah['id']]);
        $total_jasa_layanan = $stmt->fetch(PDO::FETCH_ASSOC)['total_jasa_layanan'];

        // Additional queries for nasabah statistics
        $stmt = $conn->prepare("SELECT COUNT(*) as total_pengajuan FROM pinjaman WHERE nasabah_id = ?");
        $stmt->execute([$nasabah['id']]);
        $total_pengajuan = $stmt->fetch(PDO::FETCH_ASSOC)['total_pengajuan'];

        $stmt = $conn->prepare("SELECT COUNT(*) as pinjaman_aktif FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
        $stmt->execute([$nasabah['id']]);
        $pinjaman_aktif = $stmt->fetch(PDO::FETCH_ASSOC)['pinjaman_aktif'];

        $stmt = $conn->prepare("SELECT SUM(jumlah) as total_pinjam FROM pinjaman WHERE nasabah_id = ? AND status = 'disetujui'");
        $stmt->execute([$nasabah['id']]);
        $total_pinjam = $stmt->fetch(PDO::FETCH_ASSOC)['total_pinjam'];


    }
?>
<?php include 'includes/header.php'; ?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card bg-gradient-primary text-white shadow-lg-custom p-4">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h2 class="card-title mb-2"><i class="fas fa-user-circle me-3"></i> Selamat Datang, <strong><?= $_SESSION['username'] ?></strong>!</h2>
                    <!-- <p class="mb-0">Anda login sebagai <strong> <i class="fas fa-shield-alt me-1"></i><?= ucfirst($_SESSION['role']) ?></strong></p> -->
                </div>
                <div class="text-end">
                    <i class="fas fa-handshake fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if($_SESSION['role'] == 'admin'): ?>
        <!-- Main Statistics -->
        <div class="row mt-4">
            <div class="col-lg-3 col-md-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?= number_format($total_nasabah) ?></h3>
                        <p>Total Anggota</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <a href="admin/nasabah.php" class="small-box-footer">
                        Lihat Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>Rp <?= number_format($total_simpanan, 0, ',', '.') ?></h3>
                        <p>Total Simpanan</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <a href="admin/simpanan_pokok.php" class="small-box-footer">
                        Lihat Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?= number_format($total_pinjaman) ?></h3>
                        <p>Pinjaman Disetujui</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <a href="admin/pinjaman.php" class="small-box-footer">
                        Lihat Detail <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>Rp <?= number_format($total_jasa_layanan, 0, ',', '.'); ?></h3>
                        <p>Total Jasa Layanan</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="small-box-footer">&nbsp;</div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card shadow-lg-custom" style="border-radius: 20px;">
                    <div class="card-header bg-gradient-primary">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Tren Simpanan Bulanan</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="savingsChart" width="400" height="200" style="max-width: 100%; height: auto;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Admin -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card shadow-lg-custom" style="border-radius: 20px; transition: transform 0.3s ease;">
                    <div class="card-header bg-gradient-primary">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Menu Admin</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item">
                                        <a href="admin/nasabah.php" class="text-decoration-none">
                                            <i class="fas fa-users me-2"></i> Kelola Data Nasabah
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="admin/pinjaman.php" class="text-decoration-none">
                                            <i class="fas fa-hand-holding-usd me-2"></i> Kelola Pinjaman
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="admin/settings.php" class="text-decoration-none">
                                            <i class="fas fa-cog me-2"></i> Pengaturan Akun
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item">
                                        <a href="admin/simpanan_pokok.php" class="text-decoration-none">
                                            <i class="fas fa-piggy-bank me-2"></i> Kelola Simpanan Pokok
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="admin/simpanan_wajib.php" class="text-decoration-none">
                                            <i class="fas fa-calendar-check me-2"></i> Kelola Simpanan Wajib
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="admin/simpanan_sukarela.php" class="text-decoration-none">
                                            <i class="fas fa-circle nav-icon"></i> Kelola Simpanan Sukarela
                                        </a>
                                    </li>
                                    <li class="list-group-item text-muted">
                                        <i class="fas fa-chart-bar me-2"></i> Laporan Keuangan
                                        <small class="text-muted">(Segera Hadir)</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Personal Statistics -->
        <div class="row mt-4">
            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-info shadow-lg-custom">
                    <div class="inner">
                        <h3>Rp <?= number_format($total_simpanan, 0, ',', '.') ?></h3>
                        <p>Total Simpanan Wajib</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-warning shadow-lg-custom">
                    <div class="inner">
                        <h3>Rp <?= number_format($total_pinjaman, 0, ',', '.') ?></h3>
                        <p>Total Pinjaman Disetujui</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-info shadow-lg-custom">
                    <div class="inner">
                        <h3>Rp <?= number_format($total_jasa_layanan, 0, ',', '.'); ?></h3>
                        <p>Total Jasa Layanan</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="small-box bg-info shadow-lg-custom">
                    <div class="inner">
                        <h3><?= $total_pengajuan; ?></h3>
                        <p>Total Pengajuan</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-success shadow-lg-custom">
                    <div class="inner">
                        <h3><?= $pinjaman_aktif; ?></h3>
                        <p>Pinjaman Aktif</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-primary shadow-lg-custom">
                    <div class="inner">
                        <h3>Rp <?= number_format($total_pinjam, 0, ',', '.'); ?></h3>
                        <p>Total Nilai Pinjaman</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Nasabah -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Menu Nasabah</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item">
                                        <a href="nasabah/profil.php" class="text-decoration-none">
                                            <i class="fas fa-user me-2"></i> Profil Saya
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <a href="nasabah/pinjaman.php" class="text-decoration-none">
                                            <i class="fas fa-hand-holding-usd me-2"></i> Pinjaman Saya
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item">
                                        <a href="nasabah/simpanan.php" class="text-decoration-none">
                                            <i class="fas fa-piggy-bank me-2"></i> Riwayat Simpanan
                                        </a>
                                    </li>
                                    <li class="list-group-item text-muted">
                                        <i class="fas fa-history me-2"></i> Riwayat Transaksi
                                        <small class="text-muted">(Segera Hadir)</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    <?php endif; ?>

    <!-- Chart Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Savings Line Chart
            const savingsCtx = document.getElementById('savingsChart').getContext('2d');
            const savingsData = <?php echo json_encode($monthly_savings); ?>;
            const labels = savingsData.map(item => {
                const date = new Date(item.bulan + '-01');
                return date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
            });
            const data = savingsData.map(item => parseFloat(item.total_simpanan));

            const savingsChart = new Chart(savingsCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Simpanan',
                        data: data,
                        borderColor: 'rgba(0, 123, 255, 1)',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</div>

<?php include 'includes/footer.php'; ?>
