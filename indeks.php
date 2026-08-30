<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

if (isset($_SESSION['user_id'])) {
    $target = ($_SESSION['role'] === 'admin') ? 'admin/dashboard.php' :
              (($_SESSION['role'] === 'guru') ? 'dashboard_guru.php' : 'dashboard.php');
    header("Location: $target");
    exit;
}

$message = "";

if (isset($_GET['registered'])) {
    $message = "<div class='alert alert-success text-center'>✅ Pendaftaran berhasil! Tunggu verifikasi admin untuk login.</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password_input = $_POST['password'] ?? '';
    $semester_pilihan = $_POST['semester'] ?? '2';

    if (!empty($username) && !empty($password_input)) {
        $query = "SELECT id, password, role, is_verified, username, nama_lengkap, id_guru, kelas FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password_input, $user['password']) || $password_input === $user['password']) {
                    if ($user['is_verified'] == 1) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['id_guru'] = $user['id_guru'];
                        $_SESSION['kelas'] = $user['kelas'];
                        $_SESSION['semester_aktif'] = $semester_pilihan;

                        $loc = ($user['role'] === 'admin') ? "admin/dashboard.php" : (($user['role'] === 'guru') ? "dashboard_guru.php" : "dashboard.php");
                        header("Location: $loc");
                        exit;
                    } else {
                        $message = "<div class='alert alert-warning text-center'>⚠️ Akun Anda sedang menunggu verifikasi Admin.</div>";
                    }
                } else {
                    $message = "<div class='alert alert-danger text-center'>❌ Password salah!</div>";
                }
            } else {
                $message = "<div class='alert alert-danger text-center'>❌ Username tidak ditemukan!</div>";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PADI - Login Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Poppins', sans-serif; }
        .login-card { background: #ffffff; padding: 40px; border-radius: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        .logo-icon { font-size: 3.5rem; color: #2ecc71; text-align: center; }
        .app-name { font-weight: 700; font-size: 2.2rem; color: #333; text-align: center; margin-bottom: 0; }
        .app-tagline { font-size: 0.85rem; color: #777; text-align: center; margin-bottom: 30px; letter-spacing: 1px; }
        .form-control, .form-select { border-radius: 12px; padding: 12px; background-color: #f1f3f7; border: none; }
        .btn-login { background: #764ba2; border: none; padding: 14px; border-radius: 12px; font-weight: 600; color: white; width: 100%; margin-top: 15px; }
        .register-link { text-align: center; margin-top: 20px; font-size: 0.9rem; color: #666; }
        .register-link a, .btn-link-custom { color: #764ba2; text-decoration: none; font-weight: 600; background: none; border: none; padding: 0; }
        .modal-content { border-radius: 20px; border: none; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-area">
        <div class="logo-icon"><i class="fa-solid fa-seedling"></i></div>
        <h1 class="app-name">PADI</h1>
        <p class="app-tagline">Pembelajaran Anak Digital</p>
    </div>

    <?= $message ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold small">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Username Anda" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold small">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Password Anda" required>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold small text-primary">Pilih Semester</label>
            <select name="semester" class="form-select border-primary">
                <option value="2" selected>Semester 2 (Jan - Jun 2026)</option>
                <option value="1">Semester 1 (Jul - Des 2025)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-login shadow">MASUK KE PORTAL</button>
    </form>

    <div class="register-link">
        Belum punya akun? <br>
        <a href="register.php">Daftar Sekarang</a> | 
        <button type="button" class="btn-link-custom" data-bs-toggle="modal" data-bs-target="#modalPanduan">
            Petunjuk Akses
        </button>
    </div>
</div>

<div class="modal fade" id="modalPanduan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0">
                <h5 class="fw-bold"><i class="fas fa-info-circle text-primary me-2"></i> Petunjuk Akses Uji Coba Aplikasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-4">Silakan gunakan akun berikut untuk menguji coba fitur aplikasi PADI:</p>
                
                <div class="alert alert-primary border-0 shadow-sm mb-3">
                    <h6 class="fw-bold mb-2"><i class="fas fa-chalkboard-teacher me-2"></i> AKUN GURU</h6>
                    <div class="small">
                        Username: <strong>guru</strong><br>
                        Password: <strong>12345</strong>
                    </div>
                </div>

                <div class="alert alert-success border-0 shadow-sm">
                    <h6 class="fw-bold mb-2"><i class="fas fa-user-graduate me-2"></i> AKUN SISWA</h6>
                    <div class="small">
                        Username: <strong>peserta1, peserta2, peserta3, peserta4, peserta5</strong><br>
                        Password: <strong>12345</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary w-100 rounded-pill" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>