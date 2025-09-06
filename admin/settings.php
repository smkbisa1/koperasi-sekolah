<?php
session_start();
require_once '../config/database.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil data admin
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Update profil
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmt->execute([$name, $email, $_SESSION['user_id']]);

    $success = "Profil berhasil diupdate!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();

    // Refresh data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

require_once '../includes/password_validation.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Cek password lama
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(password_verify($current_password, $user['password'])) {
        // Check if new passwords match
        if($new_password != $confirm_password) {
            $error_password = "Password baru tidak cocok!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            $success_password = "Password berhasil diupdate!";
            // Do not redirect immediately, so the success message can be shown
            // header("Location: " . $_SERVER['PHP_SELF']);
            // exit();
        }
    } else {
        $error_password = "Password lama salah!";
    }
}
?>

<?php include '../includes/header.php'; ?>


    <h2>Pengaturan Akun Admin</h2>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <script>
            alert("<?= $success ?>");
        </script>
    <?php endif; ?>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Data Diri</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>
                        <button type="submit" name="update_profil" class="btn btn-primary">Update Profil</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Update Password</h5>
                </div>
                <div class="card-body">
                    <?php if(isset($error_password)): ?>
                        <div class="alert alert-danger"><?= $error_password ?></div>
                    <?php endif; ?>

<?php if(isset($success_password)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Sukses',
                text: '<?= $success_password ?>',
                confirmButtonText: 'OK'
            });
        });
    </script>
<?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label>Password Lama</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password Baru</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-warning">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


<?php include '../includes/footer.php'; ?>
