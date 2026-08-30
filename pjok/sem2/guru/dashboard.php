<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// 1. VALIDASI ROLE
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['guru', 'admin'])) {
    header("Location: ../../login.php");
    exit();
}

// 2. AMBIL DATA SESI & LOGIKA KOLABORASI
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';

// TANGKAP ID dari URL (saat pertama klik dari portal)
if (isset($_GET['id_guru_target'])) {
    $user_id = (int)$_GET['id_guru_target'];
    $_SESSION['id_guru_pilihan'] = $user_id; // Kunci di session
} else {
    // Jika tidak ada di URL, cek session, jika kosong baru pakai ID sendiri
    $user_id = (int)($_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
}

// Mode Kolaborasi
$is_kolaborasi = (isset($_SESSION['id_guru_pilihan']) && $_SESSION['id_guru_pilihan'] != $_SESSION['user_id']);

// Data Semester (Penyesuaian Dashboard IPAS)
$semester = $_SESSION['semester_aktif'] ?? "2";
$is_arsip = ($semester == "1");

// 3. INISIALISASI STATISTIK
$stats = [
    'total_materi' => 0,
    'total_kuis_dibuat' => 0,
    'total_siswa' => 0,
    'total_guru' => 0,
];

// 4. LOGIKA FILTER (ISOLASI DATA GURU)
$guru_filter = ($role === 'guru') ? "WHERE id_guru = $user_id" : "";
$guru_filter_join = ($role === 'guru') ? "WHERE t2.id_guru = $user_id" : "";

// Statistik Materi (Menggunakan tbl() seperti IPAS)
$res_materi = $db_mapel->query("SELECT COUNT(id) AS total FROM " . tbl('materi') . " $guru_filter");
$stats['total_materi'] = $res_materi->fetch_assoc()['total'] ?? 0;

// Statistik Kuis (Menggunakan tbl() seperti IPAS)
$tabel_soal   = tbl('soal');
$tabel_materi = tbl('materi');

$res_kuis = $db_mapel->query("
SELECT COUNT(DISTINCT t1.materi_id) AS total
FROM $tabel_soal t1
JOIN $tabel_materi t2 ON t1.materi_id=t2.id
$guru_filter_join
");
$stats['total_kuis_dibuat'] = $res_kuis->fetch_assoc()['total'] ?? 0;

// Statistik Guru
$res_guru = $conn->query("SELECT COUNT(id) AS total FROM users WHERE role = 'guru'");
$stats['total_guru'] = $res_guru->fetch_assoc()['total'] ?? 0;

// 5. AMBIL DAFTAR SISWA (DATABASE PUSAT - Menggunakan tbl_portal() seperti IPAS)
$daftar_siswa = [];
$tabel_users = tbl_portal('users');

$query_siswa = ($role === 'guru')
    ? "SELECT id, nama_lengkap, kelas
       FROM $tabel_users
       WHERE role='siswa' AND id_guru=$user_id
       ORDER BY kelas, nama_lengkap"
    : "SELECT id, nama_lengkap, kelas
       FROM $tabel_users
       WHERE role='siswa'
       ORDER BY kelas, nama_lengkap";

if ($result_siswa = $conn->query($query_siswa)) {
    $stats['total_siswa'] = $result_siswa->num_rows;
    while ($row = $result_siswa->fetch_assoc()) {
        $daftar_siswa[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?php echo ucfirst($role); ?> | PJOK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #1a1a1a; border-bottom: 3px solid #ffc107; }
        .stat-card { border: none; border-radius: 15px; transition: transform 0.3s; background: #fff; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .card-menu { border: none; border-radius: 20px; transition: all 0.3s; color: white; }
        .card-menu:hover { transform: scale(1.02); box-shadow: 0 15px 30px rgba(0,0,0,0.2); }
        .icon-large { font-size: 3rem; margin-bottom: 15px; opacity: 0.8; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <strong>PJOK | <?php echo strtoupper($role); ?></strong>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3 d-none d-md-block">Halo, <span class="text-warning fw-bold"><?= htmlspecialchars($nama_pengguna) ?></span></span>
            <div class="btn-group gap-2">
                <a href="../../../dashboard_guru.php" class="btn btn-outline-light btn-sm fw-bold"><i class="fas fa-th-large me-1"></i> Portal</a>
                <a href="../logout.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-sign-out-alt"></i> Keluar</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">

<?php if ($is_kolaborasi): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center mb-3">
        <span><i class="fas fa-user-friends me-2"></i> Anda sedang mengelola dashboard milik <strong>Guru Kelas</strong>.</span>
        <a href="reset_kolaborasi.php" class="btn btn-sm btn-dark rounded-pill px-3">Kembali ke Dashboard Saya</a>
    </div>
<?php endif; ?>

<?php if($is_arsip): ?>

<div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm">
    <div>
        <h6 class="mb-1">
            <i class="fas fa-box-archive"></i>
            Mode Arsip
        </h6>
        <small>
            Anda sedang melihat data Semester Arsip.
        </small>
    </div>
    <a href="../../../ganti_semester.php?semester=2"
       class="btn btn-success rounded-pill">
        <i class="fas fa-rotate-left me-1"></i>
        Semester Aktif
    </a>
</div>

<?php else: ?>

<div class="alert alert-success d-flex justify-content-between align-items-center shadow-sm">
    <div>
        <h6 class="mb-1">
            <i class="fas fa-circle-check"></i>
            Semester Aktif
        </h6>
        <small>
            Anda sedang mengelola data semester berjalan.
        </small>
    </div>
    <a href="../../../ganti_semester.php?semester=1"
       class="btn btn-warning rounded-pill">
        <i class="fas fa-box-archive me-1"></i>
        Lihat Arsip
    </a>
</div>

<?php endif; ?>

</div>

<div class="container mt-4 mb-5">
    <header class="mb-5">
        <h1 class="fw-bold text-dark">Area Kerja <?= ucfirst($role) ?> <i class="fas fa-tools text-primary"></i></h1>
        <p class="lead text-muted">Kelola konten materi dan pantau perkembangan siswa secara real-time.</p>
    </header>

    <div class="row text-center mb-5 g-4">
        <div class="col-md-3">
            <div class="card stat-card p-4 shadow-sm border-start border-primary border-5">
                <h6 class="text-muted fw-bold">TOTAL MATERI</h6>
                <h1 class="display-5 fw-bold text-primary"><?= $stats['total_materi'] ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-4 shadow-sm border-start border-info border-5">
                <h6 class="text-muted fw-bold">KUIS DIBUAT</h6>
                <h1 class="display-5 fw-bold text-info"><?= $stats['total_kuis_dibuat'] ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-4 shadow-sm border-start border-success border-5">
                <h6 class="text-muted fw-bold">MURID SAYA</h6>
                <h1 class="display-5 fw-bold text-success"><?= $stats['total_siswa'] ?></h1>
                <button class="btn btn-sm btn-success mt-2 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#siswaModal" <?= ($stats['total_siswa'] == 0) ? 'disabled' : '' ?>>
                    <i class="fas fa-search me-1"></i> Rincian
                </button>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-4 shadow-sm border-start border-danger border-5">
                <h6 class="text-muted fw-bold">TOTAL GURU</h6>
                <h1 class="display-5 fw-bold text-danger"><?= $stats['total_guru'] ?></h1>
            </div>
        </div>
    </div>

    <h4 class="mb-4 fw-bold"><i class="fas fa-th-list me-2"></i>Pengelolaan Konten</h4>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card card-menu bg-primary h-100 shadow">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-book-open icon-large"></i>
                    <h4 class="fw-bold">Kelola Materi</h4>
                    <p class="small opacity-75">Buat dan edit Materi.</p>
                    <a href="materi_list.php" class="btn btn-light text-primary fw-bold w-100 mt-3 rounded-pill">Akses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-menu bg-info h-100 shadow">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-question-circle icon-large"></i>
                    <h4 class="fw-bold">Kelola Kuis</h4>
                    <p class="small opacity-75">Manajemen bank soal dan kuis materi.</p>
                    <a href="kuis_list.php" class="btn btn-light text-info fw-bold w-100 mt-3 rounded-pill">Akses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-menu bg-dark h-100 shadow" style="background-color: #34495e !important;">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-rocket icon-large"></i>
                    <h4 class="fw-bold">Try Out</h4>
                    <p class="small opacity-75">Manajemen ujian harian, Tengah Semester, dan Ujian Semester.</p>
                    <a href="manajemen_tryout.php" class="btn btn-light text-dark fw-bold w-100 mt-3 rounded-pill">Akses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-menu bg-success h-100 shadow">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-chart-line icon-large"></i>
                    <h4 class="fw-bold">Laporan Progres</h4>
                    <p class="small opacity-75">Pantau nilai dan riwayat belajar siswa.</p>
                    <a href="laporan.php" class="btn btn-light text-success fw-bold w-100 mt-3 rounded-pill">Akses</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-menu border border-primary h-100 shadow-sm" style="background: white !important; color: #0d6efd;">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-trophy icon-large text-primary"></i>
                    <h4 class="fw-bold">Leaderboard</h4>
                    <p class="small text-muted">Analisis peringkat antar individu/kelas.</p>
                    <a href="leaderboard_guru.php" class="btn btn-primary fw-bold w-100 mt-3 rounded-pill text-white">Buka</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="siswaModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-graduate me-2"></i> Siswa yang Anda Bimbing</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Kelas</th>
                                <th>Nama Lengkap</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($daftar_siswa)): ?>
                                <?php $no = 1; foreach ($daftar_siswa as $siswa): ?>
                                    <tr>
                                        <td class="ps-4"><?= $no++ ?></td>
                                        <td><span class="badge bg-primary rounded-pill px-3">Kelas <?= htmlspecialchars($siswa['kelas']) ?></span></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($siswa['nama_lengkap']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center py-4">Data tidak ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary fw-bold rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$db_mapel->close();
$conn->close();
?>