<?php
require_once 'config/koneksi.php';

$message_type = ""; // Untuk menyimpan status sukses/gagal

// 1. Logika Simpan Data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $nama = $conn->real_escape_string($_POST['nama_lengkap']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $kelas = $_POST['kelas'];
    $id_guru = $_POST['id_guru'];

    // Cek duplikasi username
    $cek = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($cek->num_rows > 0) {
        $message_type = "error_username";
    } else {
        $sql = "INSERT INTO users (nama_lengkap, username, password, role, kelas, id_guru, is_verified)
                VALUES ('$nama', '$username', '$password', 'siswa', '$kelas', '$id_guru', 0)";
        if ($conn->query($sql)) {
            $message_type = "success";
        } else {
            $message_type = "error_db";
        }
    }
}

// 2. Logika AJAX untuk ambil daftar guru berdasarkan kelas
if (isset($_GET['get_guru_by_kelas'])) {
    $kelas_pilihan = $_GET['get_guru_by_kelas'];
    $query_guru = $conn->query("SELECT id, nama_lengkap FROM users
                               WHERE role = 'guru'
                               AND (kelas = '$kelas_pilihan' OR FIND_IN_SET('$kelas_pilihan', kelas))
                               ORDER BY nama_lengkap ASC");

    $options = "<option value=''>-- Pilih Guru --</option>";
    while ($g = $query_guru->fetch_assoc()) {
        $options .= "<option value='{$g['id']}'>{$g['nama_lengkap']}</option>";
    }
    echo $options;
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .reg-card { background: white; padding: 40px; border-radius: 25px; width: 100%; max-width: 500px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .form-control, .form-select { border-radius: 12px; padding: 12px; background-color: #f1f3f7; border: none; }
        .btn-register { background: #007bff; border: none; padding: 14px; border-radius: 12px; font-weight: 600; color: white; width: 100%; margin-top: 15px; }
    </style>
</head>
<body>

<div class="reg-card">
    <h3 class="fw-bold text-center mb-1">Daftar Akun Baru</h3>
    <p class="text-center text-muted small mb-4">Silakan isi data diri dengan benar</p>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label small fw-bold">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama asli" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Untuk login" required>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-bold">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Password minimal 6 karakter" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label small fw-bold text-primary">Kelas</label>
                <select name="kelas" id="kelas" class="form-select border-primary" required onchange="updateGuru()">
                    <option value="">-- Pilih Kelas --</option>
                    <option value="4">Kelas 4</option>
                    <option value="5">Kelas 5</option>
                    <option value="6">Kelas 6</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label small fw-bold text-primary">Guru Pembimbing</label>
                <select name="id_guru" id="id_guru" class="form-select border-primary" required disabled>
                    <option value="">Pilih kelas dulu...</option>
                </select>
            </div>
        </div>

        <button type="submit" name="register" class="btn btn-register shadow">Daftar Sekarang</button>

        <div class="text-center mt-4 small">
            Sudah punya akun? <a href="login.php" class="text-decoration-none fw-bold">Login di sini</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// 1. Notifikasi Pop-up Berdasarkan Response PHP
<?php if ($message_type === "success"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Pendaftaran Berhasil!',
        text: 'Akun Anda telah dibuat. Silakan tunggu verifikasi admin.',
        timer: 3000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = 'login.php';
    });
<?php elseif ($message_type === "error_username"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: 'Username sudah digunakan, coba yang lain!'
    });
<?php elseif ($message_type === "error_db"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Terjadi Kesalahan',
        text: 'Gagal menyimpan data ke database.'
    });
<?php endif; ?>

// 2. Fungsi Filter Guru
function updateGuru() {
    const kelas = document.getElementById('kelas').value;
    const guruSelect = document.getElementById('id_guru');

    if (kelas === "") {
        guruSelect.innerHTML = "<option value=''>Pilih kelas dulu...</option>";
        guruSelect.disabled = true;
        return;
    }

    fetch(`register.php?get_guru_by_kelas=${kelas}`)
        .then(response => response.text())
        .then(data => {
            guruSelect.innerHTML = data;
            guruSelect.disabled = false;
        })
        .catch(err => console.error('Gagal mengambil data guru:', err));
}
</script>

</body>
</html>