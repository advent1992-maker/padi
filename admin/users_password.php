<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

if ($_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }

$id = intval($_GET['id']);
$user = $conn->query("SELECT nama_lengkap, username FROM users WHERE id = $id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pass_baru = password_hash($_POST['password_baru'], PASSWORD_DEFAULT);
    $update = $conn->query("UPDATE users SET password = '$pass_baru' WHERE id = $id");

    if ($update) {
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Ganti Password | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold">Ganti Password User</h5>
                    <p class="text-muted small">User: <?php echo $user['nama_lengkap']; ?> (<?php echo $user['username']; ?>)</p>
                    <hr>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" required placeholder="Masukkan password minimal 6 karakter">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Password Baru</button>
                        <a href="users.php" class="btn btn-link w-100 mt-2 text-decoration-none text-muted">Batal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($success)): ?>
<script>
    Swal.fire('Berhasil!', 'Password telah diperbarui.', 'success').then(() => {
        window.location.href = 'users.php';
    });
</script>
<?php endif; ?>
</body>
</html>