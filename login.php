<?php
session_start();
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Koperasi Sekolah</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css"> <!-- Untuk CSS kustom Anda -->
</head>
<body>
    <div class="container-fluid login-container">
        <div class="row h-100 justify-content-center align-items-center">
            <div class="col-md-8 login-box p-0 shadow-lg rounded">
                <div class="row no-gutters h-100">
                    <!-- Bagian Kiri (Informasi Koperasi) -->
                    <div class="col-md-7 p-5 d-flex flex-column justify-content-between left-panel rounded-left">
                        <div>
                            <img src="assets/img/logo.png" alt="Logo Koperasi Sekolah" class="koperasi-logo mb-3">
                            <h2 class="text-white">Koperasi Sekolah</h2>
                            <h3 class="text-white-50">SMP Negeri 2 Rasau Jaya</h3>
                            <p class="mt-4 text-white-75">Sistem Manajemen Koperasi Sekolah</p>
                        </div>
                        <div class="illustration-placeholder">
                            <!-- Di sini Anda bisa menempatkan ilustrasi atau gambar dekoratif lainnya -->
                            <img src="assets/img/illustration.png" alt="Ilustrasi Koperasi" class="img-fluid">
                        </div>
                        
                    </div>

                    <!-- Bagian Kanan (Form Login) -->
                    <div class="col-md-5 p-5 bg-white right-panel rounded-right d-flex flex-column justify-content-center">
                        <div class="text-center mb-4">
                            <div class="login-icon mx-auto mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                                    <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                </svg>
                            </div>
                            <h4>Selamat datang kembali,</h4>
                            <p class="text-muted">silahkan masuk</p>
                        </div>
                        <form action="login.php" method="POST">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block mt-4">Login</button>

                            <?php
                            // PHP untuk proses login
                            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                                $username = $_POST['username'];
                                $password = $_POST['password'];

                                // Validasi dengan database
                                try {
                                    global $conn;
                                    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
                                    $stmt->execute([$username]);
                                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if ($user && password_verify($password, $user['password'])) {
                                        // Login berhasil
                                        $_SESSION['user_id'] = $user['id'];
                                        $_SESSION['username'] = $username;
                                        $_SESSION['role'] = $user['role'];

                                        echo '<div class="alert alert-success mt-3" role="alert">Login Berhasil! Mengalihkan...</div>';

                                        // Redirect berdasarkan role
                                        if ($user['role'] === 'admin') {
                                            header("Location: admin/dashboard.php");
                                        } else {
                                            header("Location: nasabah/dashboard.php");
                                        }
                                        exit();
                                    } else {
                                        echo '<div class="alert alert-danger mt-3" role="alert">Username atau Password salah.</div>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<div class="alert alert-danger mt-3" role="alert">Terjadi kesalahan: ' . $e->getMessage() . '</div>';
                                }
                            }
                            ?>
                        </form>
                    </div>                    
                </div>                
            </div> 
        </div>        
        <p class="copyright-text">&copy; 2025 Koperasi Sekolah SMP Negeri 2 Rasau Jaya. All rights reserved.</p>          
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>