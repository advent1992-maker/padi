<?php
require_once '../config/koneksi.php'; // koneksi database
require_once '../config/session.php'; // session config
require_once '../config/auth_check.php'; // cek user login & role

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// =======================================================================
// TAMBAHAN: LOGIC REKAPITULASI UJI COBA
// =======================================================================

// --- 1. REKAPITULASI SISWA ---
$rata_rata_siswa = 0;
$total_responden_siswa = 0;

$query_rekap_siswa = "SELECT AVG(skor_penilaian) as rata_rata_skor, COUNT(DISTINCT kelas) as total_kelas FROM hasil_uji_siswa";
$data_siswa = $db_mapel->query($query_rekap_siswa)->fetch_assoc();

if ($data_siswa && $data_siswa['rata_rata_skor'] !== null) {
    $rata_rata_siswa = number_format($data_siswa['rata_rata_skor'], 2);
    // Menggunakan total kelas sebagai proxy responden unik
    $total_responden_siswa = $data_siswa['total_kelas'];
}

// --- 2. REKAPITULASI GURU ---
$rata_rata_guru = 0;
$total_responden_guru = 0;

$query_rekap_guru = "SELECT AVG(skor_penilaian) as rata_rata_skor, COUNT(DISTINCT nama_guru) as total_guru FROM hasil_uji_guru";
$data_guru = $db_mapel->query($query_rekap_guru)->fetch_assoc();

if ($data_guru && $data_guru['rata_rata_skor'] !== null) {
    $rata_rata_guru = number_format($data_guru['rata_rata_skor'], 2);
    $total_responden_guru = $data_guru['total_guru'];
}

// =======================================================================
// AKHIR LOGIC REKAPITULASI UJI COBA
// =======================================================================
// Ambil daftar pengguna yang belum terverifikasi (is_verified = 0)
$query = "SELECT id, username, nama_lengkap, email, role, created_at FROM users WHERE is_verified = 0 ORDER BY created_at ASC";
$result = $db_mapel->query($query);

// Ambil data user yang sedang login untuk sapaan
$admin_name = $_SESSION['username'];

// Ambil pesan notifikasi dari session (untuk aksi verifikasi, dll.)
$message = "";
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | MATHFICTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tambahkan Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="container mt-5">
    <?php echo $message; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Dashboard Admin 👋</h1>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <div class="alert alert-primary">
        Selamat datang, <strong><?php echo htmlspecialchars($admin_name); ?></strong>. Pilih menu manajemen di bawah ini untuk memulai.
    </div>

    <h2 class="mt-5">1. Menu Utama</h2>
    <hr>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-body">
                    <h5 class="card-title text-primary"><i class="fas fa-users me-2"></i> Kelola Pengguna</h5>
                    <p class="card-text">Lihat, Edit, dan Hapus <strong>semua akun</strong> Guru dan Siswa. Gunakan ini untuk mengganti password user.</p>
                    <a href="manage_users.php" class="btn btn-primary">Akses Kelola Pengguna</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100 border-success">
                <div class="card-body">
                    <h5 class="card-title text-success"><i class="fas fa-chart-line me-2"></i> Progres Siswa</h5>
                    <p class="card-text">Lihat kemajuan, nilai kuis, dan riwayat belajar seluruh siswa.</p>
                    <a href="progres_siswa.php" class="btn btn-success">Akses Progres Siswa</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100 border-info">
                <div class="card-body">
                    <h5 class="card-title text-info"><i class="fas fa-graduation-cap me-2"></i> Progres Guru</h5>
                    <p class="card-text">Pantau materi dan soal yang telah dibuat oleh setiap guru.</p>
                    <a href="progres_guru.php" class="btn btn-info text-white">Akses Progres Guru</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm h-100 border-info">
                <div class="card-body">
                    <h5 class="card-title text-info"><i class="fas fa-trophy-cap me-2"></i> Leaderboard Siswa</h5>
                    <p class="card-text">Pantau Peringkat Kelas.</p>
                    <a href="leaderboard_admin.php" class="btn btn-info text-white">Lihat Peringkat</a>
                </div>
            </div>
        </div>
    </div>
<h2 class="mt-5">2. Rekapitulasi Hasil Uji Coba</h2>
    <hr>

    <div class="row mb-5">

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100 border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning"><i class="fas fa-poll me-2"></i> Hasil Uji Coba SISWA</h5>
                    <p class="card-text mb-1">Total Kelas Responden: <strong><?php echo $total_responden_siswa; ?></strong></p>
                    <p class="card-text mb-3">Rata-rata Skor Global:
                        <strong class="fs-4 text-warning"><?php echo $rata_rata_siswa; ?> / 5.00</strong>
                    </p>
                    <a href="rekap_uji_siswa.php" class="btn btn-warning btn-sm text-white">
                        <i class="fas fa-eye me-1"></i> Lihat Detail Rekap Siswa
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm h-100 border-success">
                <div class="card-body">
                    <h5 class="card-title text-success"><i class="fas fa-chalkboard-teacher me-2"></i> Hasil Uji Coba GURU</h5>
                    <p class="card-text mb-1">Total Guru Responden: <strong><?php echo $total_responden_guru; ?></strong></p>
                    <p class="card-text mb-3">Rata-rata Skor Global:
                        <strong class="fs-4 text-success"><?php echo $rata_rata_guru; ?> / 5.00</strong>
                    </p>
                    <a href="rekap_uji_guru.php" class="btn btn-success btn-sm">
                        <i class="fas fa-eye me-1"></i> Lihat Detail Rekap Guru
                    </a>
                </div>
            </div>
        </div>
    </div>
    <h2 class="mt-5">3. Tugas Utama: Verifikasi Akun</h2>
    <p class="text-muted">Total: <?php echo $result->num_rows; ?> akun menunggu verifikasi.</p>

    <div class="table-responsive mb-5">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Peran</th>
                    <th>Tanggal Daftar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="badge bg-<?php echo ($user['role'] == 'guru' ? 'info' : 'success'); ?>"><?php echo strtoupper($user['role']); ?></span></td>
                            <td><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="verify_user.php?id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Yakin ingin memverifikasi akun ini?')">Verifikasi</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada akun yang perlu diverifikasi saat ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>