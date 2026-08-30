<?php
// FILE: admin/user_tambah.php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// 1. Proteksi Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 2. Pastikan Koneksi Tersedia
if (!isset($conn) || !$conn instanceof mysqli) {
    $conn = new mysqli("localhost", "root", "", "db_portal_pusat");
}

// 3. Proses Simpan Data
if (isset($_POST['simpan'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $kelas = $_POST['kelas'] ?? '';
    $id_guru = ($role === 'siswa') ? ($_POST['id_guru'] ?? 0) : 0;
    $is_verified = 1; // Langsung aktif karena ditambah Admin

    // Cek apakah username sudah ada
    $cek = $conn->query("SELECT id FROM users WHERE username = '$user'");
    if ($cek->num_rows > 0) {
        echo "<script>alert('Username sudah digunakan, cari yang lain!');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (nama_lengkap, username, password, role, kelas, id_guru, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssii", $nama, $user, $pass, $role, $kelas, $id_guru, $is_verified);

        if ($stmt->execute()) {
            echo "<script>alert('User $role berhasil ditambahkan!'); window.location='users.php?role=$role';</script>";
        } else {
            echo "<script>alert('Gagal menambah user: " . $conn->error . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body { background-color: #f4f7f6; }
        .card { border-radius: 15px; }
    </style>

    <script>
        // Fungsi untuk mengatur tampilan field berdasarkan role
        function toggleRoleFields() {
            var role = document.getElementById("role").value;
            var kelasField = document.getElementById("kelasField");
            var guruBimbinganField = document.getElementById("guruBimbinganField");

            if (role === "siswa") {
                // Siswa butuh input Kelas dan Pilih Guru
                kelasField.style.display = "block";
                guruBimbinganField.style.display = "block";
            } else if (role === "guru") {
                // Guru butuh input Kelas (untuk penempatan tugas), tapi tidak butuh Guru Bimbingan
                kelasField.style.display = "block";
                guruBimbinganField.style.display = "none";
                document.getElementById("selectGuru").value = "0"; // Reset guru
            } else {
                // Jika admin atau lainnya
                kelasField.style.display = "none";
                guruBimbinganField.style.display = "none";
            }
        }

        // Fungsi AJAX untuk memfilter guru berdasarkan kelas yang diinput
        function filterGuruByKelas() {
            var kelas = document.getElementById("inputKelas").value;
            var selectGuru = document.getElementById("selectGuru");
            var role = document.getElementById("role").value;

            // Hanya jalankan filter guru jika yang didaftarkan adalah SISWA
            if (role === "siswa" && kelas !== "") {
                fetch('get_guru.php?kelas=' + kelas)
                    .then(response => response.text())
                    .then(data => {
                        selectGuru.innerHTML = data;
                    });
            } else {
                selectGuru.innerHTML = '<option value="0">-- Pilih Guru --</option>';
            }
        }
    </script>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white p-3">
                    <h5 class="mb-0 text-center"><i class="fas fa-user-plus me-2"></i> Tambah Pengguna Baru</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama asli" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Untuk login" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Role Pengguna</label>
                            <select name="role" id="role" class="form-select" onchange="toggleRoleFields()" required>
                                <option value="">-- Pilih Role --</option>
                                <option value="guru">Guru</option>
                                <option value="siswa">Siswa</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div id="kelasField" class="mb-3" style="display: none;">
                            <label class="form-label fw-bold">Kelas</label>
                            <input type="number" id="inputKelas" name="kelas" class="form-control"
                                   placeholder="Contoh: 5" onkeyup="filterGuruByKelas()">
                            <small class="text-muted">Guru akan mengampu kelas ini, Siswa akan belajar di kelas ini.</small>
                        </div>

                        <div id="guruBimbinganField" class="mb-3" style="display: none;">
                            <label class="form-label fw-bold">Guru Bimbingan</label>
                            <select name="id_guru" id="selectGuru" class="form-select">
                                <option value="0">-- Pilih Guru --</option>
                            </select>
                            <small class="text-muted">Akan muncul otomatis jika angka Kelas sudah diisi.</small>
                        </div>

                        <hr>

                        <div class="d-grid gap-2">
                            <button type="submit" name="simpan" class="btn btn-primary btn-lg rounded-pill">
                                <i class="fas fa-save me-2"></i> Simpan Data
                            </button>
                            <a href="users.php" class="btn btn-light rounded-pill text-muted">Kembali ke Daftar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>