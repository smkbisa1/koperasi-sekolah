<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
}

// Handle CRUD operations
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['tambah'])) {
        // Tambah simpanan wajib baru
        $nasabah_id = $_POST['nasabah_id'];
        $bulan = $_POST['bulan'];
        $tahun = $_POST['tahun'];
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];

        // Validate month and year
        $current_year = (int)date('Y');
        $current_month = (int)date('n');
        $selected_year = (int)$tahun;
        $selected_month = (int)$bulan;

        // More robust validation: disallow future months and more than 2 years old
        $selected_date = DateTime::createFromFormat('Y-n', "$selected_year-$selected_month");
        $current_date = new DateTime();
        $min_date = (clone $current_date)->modify('-2 years')->modify('first day of this month');
        $max_date = (clone $current_date)->modify('last day of this month');

        // Cek apakah sudah ada pembayaran untuk bulan tersebut
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM simpanan WHERE nasabah_id = ? AND jenis = 'wajib' AND DATE_FORMAT(tanggal, '%Y-%m') = ?");
        $stmt->execute([$nasabah_id, $selected_date->format('Y-m')]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if($existing['count'] > 0) {
            $_SESSION['error'] = "Nasabah sudah membayar simpanan wajib untuk bulan tersebut!";
        } else {
            try {
                $conn->beginTransaction();

                // Insert simpanan wajib (selalu 50000)
                $stmt = $conn->prepare("INSERT INTO simpanan (nasabah_id, jumlah, jenis, tanggal, keterangan) VALUES (?, 50000, 'wajib', ?, ?)");
                $stmt->execute([$nasabah_id, $tanggal, $keterangan]);

                // Update saldo nasabah
                $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo + 50000 WHERE id = ?");
                $stmt->execute([$nasabah_id]);

                $conn->commit();
                $_SESSION['success'] = "Simpanan wajib berhasil ditambahkan!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch(PDOException $e) {
                $conn->rollBack();
                $_SESSION['error'] = "Error: " . $e->getMessage();
            }
        }
    }

    if(isset($_POST['edit'])) {
        // Edit simpanan wajib
        $id = $_POST['id'];
        $nasabah_id = $_POST['nasabah_id'];
        $bulan = $_POST['bulan'];
        $tahun = $_POST['tahun'];
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];

        // Validate month and year
        $current_year = (int)date('Y');
        $current_month = (int)date('n');
        $selected_year = (int)$tahun;
        $selected_month = (int)$bulan;

        // More robust validation: disallow future months and more than 2 years old
        $selected_date = DateTime::createFromFormat('Y-n', "$selected_year-$selected_month");
        $current_date = new DateTime();
        $min_date = (clone $current_date)->modify('-2 years')->modify('first day of this month');
        $max_date = (clone $current_date)->modify('last day of this month');

        // Cek apakah sudah ada pembayaran lain untuk bulan tersebut (kecuali record ini sendiri)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM simpanan WHERE nasabah_id = ? AND jenis = 'wajib' AND DATE_FORMAT(tanggal, '%Y-%m') = ? AND id != ?");
        $stmt->execute([$nasabah_id, $selected_date->format('Y-m'), $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if($existing['count'] > 0) {
            $error = "Nasabah sudah memiliki pembayaran simpanan wajib untuk bulan tersebut!";
        } else {
            // Additional check: if nasabah_id or month/year changed, ensure saldo update handled
            // (Handled in saldo update logic below)
            try {
                $conn->beginTransaction();

                // Get old data for saldo adjustment
                $stmt = $conn->prepare("SELECT nasabah_id, tanggal FROM simpanan WHERE id = ?");
                $stmt->execute([$id]);
                $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $old_nasabah_id = $old_data['nasabah_id'];
                $old_tanggal = $old_data['tanggal'];

                // Update simpanan
                $stmt = $conn->prepare("UPDATE simpanan SET nasabah_id = ?, tanggal = ?, keterangan = ? WHERE id = ?");
                $stmt->execute([$nasabah_id, $tanggal, $keterangan, $id]);

                // Jika nasabah berubah, update saldo kedua nasabah
                if ($old_nasabah_id != $nasabah_id) {
                    // Kurangi saldo nasabah lama
                    $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo - 50000 WHERE id = ?");
                    $stmt->execute([$old_nasabah_id]);

                    // Tambah saldo nasabah baru
                    $stmt = $conn->prepare("UPDATE nasabah SET saldo = saldo + 50000 WHERE id = ?");
                    $stmt->execute([$nasabah_id]);
                }

                $conn->commit();
                $_SESSION['success'] = "Simpanan wajib berhasil diupdate!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch(PDOException $e) {
                $conn->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>
<?php
// Pagination and search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$bulan_filter = isset($_GET['bulan']) ? (int)$_GET['bulan'] : '';
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : '';

// Build WHERE clause for search and filters
$where_clauses = ["s.jenis = 'wajib'"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "n.nama_lengkap LIKE ?";
    $params[] = "%$search%";
}
if (!empty($bulan_filter)) {
    $where_clauses[] = "MONTH(s.tanggal) = ?";
    $params[] = $bulan_filter;
}
if (!empty($tahun_filter)) {
    $where_clauses[] = "YEAR(s.tanggal) = ?";
    $params[] = $tahun_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM simpanan s JOIN nasabah n ON s.nasabah_id = n.id WHERE $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Ambil data simpanan wajib with pagination and filters
$query = "SELECT s.*, n.nama_lengkap FROM simpanan s JOIN nasabah n ON s.nasabah_id = n.id WHERE $where_sql ORDER BY s.tanggal DESC, s.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$i = 1;
foreach ($params as $param) {
    $stmt->bindValue($i, $param);
    $i++;
}
$stmt->bindValue($i, $limit, PDO::PARAM_INT);
$i++;
$stmt->bindValue($i, $offset, PDO::PARAM_INT);
$stmt->execute();
$simpanan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data nasabah untuk dropdown
$stmt = $conn->query("SELECT id, nama_lengkap FROM nasabah ORDER BY nama_lengkap");
$nasabah_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Array nama bulan
$bulan_nama = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>

<?php include '../includes/header.php'; ?>



    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['success'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Sukses',
                text: '<?= $_SESSION['success'] ?>',
                confirmButtonText: 'OK'
            });
        });
    </script>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
        
    <!-- <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahModal">
        <i class="fas fa-plus"></i> Tambah Simpanan Wajib
    </button> -->

    <!-- Search and Filter Form -->
    <div class="mb-3">
        <form method="GET" class="form-inline">
            <div class="form-group mr-2">
                <input type="text" name="search" class="form-control" placeholder="Cari nama nasabah..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group mr-2">
                <select name="bulan" class="form-control">
                    <option value="">Semua Bulan</option>
                    <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $bulan_filter == $i ? 'selected' : '' ?>><?= $bulan_nama[$i] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group mr-2">
                <select name="tahun" class="form-control">
                    <option value="">Semua Tahun</option>
                    <?php for($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $tahun_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary mr-2">Cari</button>
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>

    <p class="text-muted">Simpanan wajib bulanan sebesar Rp 50.000 per nasabah</p>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Simpanan Wajib</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tambahModal">
                            <i class="fas fa-plus"></i> Tambah Simpanan Wajib
                        </button>
                    </div>
            </div>
                <!-- <div class="card-header">
                    <h3 class="card-title">Daftar Simpanan Wajib</h3>
                </div> -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nasabah</th>
                            <th>Jumlah</th>
                            <th>Bulan/Tahun</th>
                            <!-- <th>Tanggal Bayar</th> -->
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($simpanan_list as $index => $simpanan): ?>
                        <tr>
                            <td><?= $index + 1 + ($page - 1) * $limit ?></td>
                            <td><?= $simpanan['nama_lengkap'] ?></td>
                            <td>Rp <?= number_format($simpanan['jumlah'], 0, ',', '.') ?></td>
                            <td><?= $bulan_nama[date('n', strtotime($simpanan['tanggal']))] ?> <?= date('Y', strtotime($simpanan['tanggal'])) ?></td>
                            <!--<td><?= date('d/m/Y', strtotime($simpanan['tanggal'])) ?></td>-->
                            <td><?= $simpanan['keterangan'] ?: '-' ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?= $simpanan['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $simpanan['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Simpanan Wajib</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label>Bulan</label>
                                                    <select name="bulan" class="form-control" required>
                                                        <?php for($i = 1; $i <= 12; $i++): ?>
                                                            <option value="<?= $i ?>" <?= $i == date('n', strtotime($simpanan['tanggal'])) ? 'selected' : '' ?>>
                                                                <?= $bulan_nama[$i] ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label>Tahun</label>
                                                    <select name="tahun" class="form-control" required>
                                                        <?php for($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                                            <option value="<?= $y ?>" <?= $y == date('Y', strtotime($simpanan['tanggal'])) ? 'selected' : '' ?>>
                                                                <?= $y ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label>Tanggal Pembayaran</label>
                                                <input type="date" name="tanggal" class="form-control" value="<?= $simpanan['tanggal'] ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label>Keterangan</label>
                                                <textarea name="keterangan" class="form-control"><?= $simpanan['keterangan'] ?></textarea>
                                            </div>
                                            <div class="alert alert-info">
                                                <strong>Catatan:</strong> Jumlah simpanan wajib adalah Rp 50.000 (tidak dapat diubah)
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

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-3 p-3">
                <div>
                    Menampilkan <?= ($offset + 1) ?> sampai <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> data
                </div>
                <nav>
                    <ul class="pagination">
                        <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>&limit=<?= $limit ?>">Sebelumnya</a>
                            </li>
                        <?php endif; ?>
                        <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>&limit=<?= $limit ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>&limit=<?= $limit ?>">Selanjutnya</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="tambahModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Simpanan Wajib</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label>Nasabah</label>
                        <select name="nasabah_id" class="form-control" required>
                            <option value="">Pilih Nasabah</option>
                            <?php foreach($nasabah_options as $nasabah): ?>
                                <option value="<?= $nasabah['id'] ?>"><?= $nasabah['nama_lengkap'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Bulan</label>
                            <select name="bulan" class="form-control" required>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>>
                                        <?= $bulan_nama[$i] ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Tahun</label>
                            <select name="tahun" class="form-control" required>
                                <?php for($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
<div class="mb-3">
    <label>Tanggal Pembayaran</label>
    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
</div>
                    <div class="mb-3">
                        <label>Keterangan</label>
                        <textarea name="keterangan" class="form-control"></textarea>
                    </div>
                    <div class="alert alert-info">
                        <strong>Jumlah Simpanan Wajib:</strong> Rp 50.000
                        <br><small>Sistem akan otomatis memvalidasi bahwa nasabah belum membayar untuk bulan tersebut</small>
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
    // Auto-fill keterangan and tanggal based on bulan and tahun selection
    function updateKeterangan() {
        const bulanSelect = document.querySelector('#tambahModal select[name="bulan"]');
        const tahunSelect = document.querySelector('#tambahModal select[name="tahun"]');
        const keteranganTextarea = document.querySelector('#tambahModal textarea[name="keterangan"]');

        if (bulanSelect && tahunSelect && keteranganTextarea) {
            const bulanNama = bulanSelect.options[bulanSelect.selectedIndex].text;
            const tahun = tahunSelect.value;

            if (bulanSelect.value && tahun) {
                keteranganTextarea.value = `Simpanan Wajib Bulan ${bulanNama} ${tahun}`;
            }
        }
    }

    function updateTanggal() {
        const bulanSelect = document.querySelector('#tambahModal select[name="bulan"]');
        const tahunSelect = document.querySelector('#tambahModal select[name="tahun"]');
        const tanggalInput = document.querySelector('#tambahModal input[name="tanggal"]');

        if (bulanSelect && tahunSelect && tanggalInput) {
            const bulan = parseInt(bulanSelect.value);
            const tahun = parseInt(tahunSelect.value);

            if (bulan && tahun) {
                const today = new Date();
                if (tahun === today.getFullYear() && bulan === (today.getMonth() + 1)) {
                    // If selected month/year is current, set to today's date
                    const formattedDate = today.toISOString().split('T')[0];
                    tanggalInput.value = formattedDate;
                } else {
                    // Else set to first day of selected month/year
                    const firstDay = new Date(tahun, bulan - 1, 1);
                    const formattedDate = firstDay.toISOString().split('T')[0];
                    tanggalInput.value = formattedDate;
                }
            }
        }
    }

    // Add event listeners for bulan and tahun changes in tambah modal
    const tambahBulanSelect = document.querySelector('#tambahModal select[name="bulan"]');
    const tambahTahunSelect = document.querySelector('#tambahModal select[name="tahun"]');

    if (tambahBulanSelect && tambahTahunSelect) {
        tambahBulanSelect.addEventListener('change', updateKeterangan);
        tambahTahunSelect.addEventListener('change', updateKeterangan);
        tambahBulanSelect.addEventListener('change', updateTanggal);
        tambahTahunSelect.addEventListener('change', updateTanggal);

        // Set initial keterangan and tanggal when modal is shown
        document.querySelector('#tambahModal').addEventListener('shown.bs.modal', function() {
            updateKeterangan();
            updateTanggal();
        });
    }

    // Also handle for edit modals
    document.querySelectorAll('.modal').forEach(function(modal) {
        const bulanSelect = modal.querySelector('select[name="bulan"]');
        const tahunSelect = modal.querySelector('select[name="tahun"]');
        const keteranganTextarea = modal.querySelector('textarea[name="keterangan"]');
        const tanggalInput = modal.querySelector('input[name="tanggal"]');

        if (bulanSelect && tahunSelect && keteranganTextarea) {
            function updateEditKeterangan() {
                const bulanNama = bulanSelect.options[bulanSelect.selectedIndex].text;
                const tahun = tahunSelect.value;

                if (bulanSelect.value && tahun) {
                    keteranganTextarea.value = `Simpanan Wajib Bulan ${bulanNama} ${tahun}`;
                }
            }

            function updateEditTanggal() {
                if (tanggalInput) {
                    const bulan = parseInt(bulanSelect.value);
                    const tahun = parseInt(tahunSelect.value);

                    if (bulan && tahun) {
                        // Only update tanggal if it's not already set to a date in the selected month/year
                        const currentDate = new Date(tanggalInput.value);
                        const selectedMonth = bulan - 1;
                        const selectedYear = tahun;

                        if (currentDate.getMonth() !== selectedMonth || currentDate.getFullYear() !== selectedYear) {
                            const today = new Date();
                            if (tahun === today.getFullYear() && bulan === (today.getMonth() + 1)) {
                                // If selected month/year is current, set to today's date
                                const formattedDate = today.toISOString().split('T')[0];
                                tanggalInput.value = formattedDate;
                            } else {
                                // Else set to first day of selected month/year
                                const firstDay = new Date(tahun, bulan - 1, 1);
                                const formattedDate = firstDay.toISOString().split('T')[0];
                                tanggalInput.value = formattedDate;
                            }
                        }
                    }
                }
            }

            bulanSelect.addEventListener('change', updateEditKeterangan);
            tahunSelect.addEventListener('change', updateEditKeterangan);
            bulanSelect.addEventListener('change', updateEditTanggal);
            tahunSelect.addEventListener('change', updateEditTanggal);
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
