<?php
// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Koperasi Sekolah - <?php echo ucfirst($current_page); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/css/OverlayScrollbars.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <!-- overlayScrollbars -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/js/jquery.overlayScrollbars.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <!-- <li class="nav-item d-none d-sm-inline-block">
                <a href="/appkopsis/dashboard.php" class="nav-link">Home</a>
            </li> -->
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-user"> </i><?php echo $_SESSION['nama_lengkap'] ?? $_SESSION['username']; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-user mr-2"></i> Profile
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="/appkopsis/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="/appkopsis/dashboard.php" class="brand-link">
            <img src="/appkopsis/assets/img/logo.png" alt="Koperasi Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">Koperasi Sekolah</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="/appkopsis/assets/img/user.png" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="#" class="d-block"><?php echo $_SESSION['username']; ?></a>
                    <small class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></small>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="/appkopsis/dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    <?php if($_SESSION['role'] == 'admin'): ?>
                        <!-- Admin Menu -->
                        <li class="nav-header">MENU</li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/anggota.php" class="nav-link <?php echo ($current_page == 'anggota' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Data Anggota</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/pinjaman.php" class="nav-link <?php echo ($current_page == 'pinjaman' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-hand-holding-usd"></i>
                                <p>Data Pinjaman</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/simpanan_pokok.php" class="nav-link <?php echo ($current_page == 'simpanan_pokok' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-piggy-bank"></i>
                                <p>Simpanan Pokok</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/simpanan_wajib.php" class="nav-link <?php echo ($current_page == 'simpanan_wajib' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-calendar-check"></i>
                                <p>Simpanan Wajib</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/simpanan_sukarela.php" class="nav-link <?php echo ($current_page == 'simpanan_sukarela' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-circle"></i>
                                <p>Simpanan Sukarela</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/simpanan_hari_raya.php" class="nav-link <?php echo ($current_page == 'simpanan_hari_raya' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-gift"></i>
                                <p>Simpanan Hari Raya</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/bunga_pinjaman.php" class="nav-link <?php echo ($current_page == 'bunga_pinjaman' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-percentage"></i>
                                <p>Pengaturan Bunga</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/admin/settings.php" class="nav-link <?php echo ($current_page == 'settings' && $current_dir == 'admin') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-cog"></i>
                                <p>Pengaturan</p>
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Nasabah Menu -->
                        <li class="nav-header">MENU</li>
                        <li class="nav-item">
                            <a href="/appkopsis/nasabah/profil.php" class="nav-link <?php echo ($current_page == 'profil' && $current_dir == 'nasabah') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-user"></i>
                                <p>Profil Saya</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/nasabah/pinjaman.php" class="nav-link <?php echo ($current_page == 'pinjaman' && $current_dir == 'nasabah') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-hand-holding-usd"></i>
                                <p>Pinjaman Saya</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/appkopsis/nasabah/simpanan.php" class="nav-link <?php echo ($current_page == 'simpanan' && $current_dir == 'nasabah') ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-piggy-bank"></i>
                                <p>Riwayat Simpanan</p>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><strong><?php echo ucwords(str_replace('_', ' ', $current_page)); ?></strong></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active"><?php echo ucwords(str_replace('_', ' ', $current_page)); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
