<?php
// Pastikan tidak ada karakter atau spasi sebelum tag pembuka <?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Validasi user_id dari URL
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    $_SESSION['progres_siswa_message'] = '<div class="alert alert-danger">ID Siswa tidak valid atau tidak ditemukan.</div>';
    header("Location: progres_siswa.php");
    exit();
}

$siswa_id = $_GET['user_id'];
$siswa_data = null;

// 1. Ambil data profil Siswa (Tabel: users)
$stmt = $conn->prepare("SELECT id, username, nama_lengkap, email, kelas FROM users WHERE id = ? AND role = 'siswa'");
if (!$stmt) {
    die("Error menyiapkan query data pengguna: " . $conn->error);
}
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $siswa_data = $result->fetch_assoc();
} else {
    $_SESSION['progres_siswa_message'] = '<div class="alert alert-danger">Siswa tidak ditemukan atau bukan merupakan akun siswa.</div>';
    header("Location: progres_siswa.php");
    exit();
}
$stmt->close();

// 2. Ambil Riwayat Kuis per Bab Materi (Tabel: riwayat_kuis, materi)
// FIX: Menghapus JOIN ke tryout_master karena kolom Foreign Key (tryout_id) tidak ada di tabel riwayat_kuis.
$kuis_query = "
    SELECT
        rk.skor AS nilai,
        rk.persentase,
        rk.tanggal_dikerjakan AS created_at,
        'Kuis Materi' AS kuis_title,                /* Memberi Judul Default */
        m.judul AS judul_materi
    FROM riwayat_kuis rk
    JOIN materi m ON rk.id_materi = m.id
    WHERE rk.id_user = ?
    ORDER BY rk.tanggal_dikerjakan DESC
";
$stmt_kuis = $conn->prepare($kuis_query);
if (!$stmt_kuis) {
    die("Error menyiapkan query riwayat kuis: " . $conn->error);
}
$stmt_kuis->bind_param("i", $siswa_id);
$stmt_kuis->execute();
$riwayat_kuis = $stmt_kuis->get_result();
$stmt_kuis->close();

// 3. Ambil Riwayat Tryout/Ujian (Tabel: riwayat_tryout, tryout_master)
// Kueri ini menggunakan kolom 'tryout_id' dan 'id_user' yang ADA di tabel riwayat_tryout.
$tryout_query = "
    SELECT
        rt.persentase,
        rt.tanggal_dikerjakan AS created_at,
        tm.judul AS tryout_title
    FROM riwayat_tryout rt
    JOIN tryout_master tm ON rt.tryout_id = tm.id
    WHERE rt.id_user = ?
    ORDER BY rt.tanggal_dikerjakan DESC
";
$stmt_tryout = $conn->prepare($tryout_query);
if (!$stmt_tryout) {
    die("Error menyiapkan query riwayat tryout: " . $conn->error);
}
$stmt_tryout->bind_param("i", $siswa_id);
$stmt_tryout->execute();
$riwayat_tryout = $stmt_tryout->get_result();
$stmt_tryout->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Progres Siswa | <?php echo htmlspecialchars($siswa_data['nama_lengkap']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card-header-custom {
            background-color: #28a745;
            color: white;
            padding: 15px;
            border-radius: 0.25rem 0.25rem 0 0;
            font-weight: bold;
        }
        .text-info-custom {
            color: #17a2b8;
        }
        .table thead th {
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1><i class="fas fa-chart-line me-2"></i> Detail Progres Siswa</h1>
        <a href="progres_siswa.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Progres</a>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i> Data Profil Siswa</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($siswa_data['nama_lengkap']); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($siswa_data['username']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($siswa_data['email']); ?></p>
                    <p><strong>Kelas:</strong> <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($siswa_data['kelas']); ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="fas fa-book-open me-2"></i> Riwayat Kuis Per Bab</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-info text-info-custom">
                        <tr>
                            <th>#</th>
                            <th>Nama Materi (Bab)</th>
                            <th>Nama Kuis</th>
                            <th>Skor (Nilai)</th>
                            <th>Nilai (Persentase)</th>
                            <th>Waktu Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat_kuis->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $riwayat_kuis->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['judul_materi']); ?></td>
                                <td><?php echo htmlspecialchars($row['kuis_title']); ?></td>
                                <td><?php echo htmlspecialchars($row['nilai']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($row['persentase'] >= 75 ? 'success' : 'danger'); ?>">
                                        <?php echo htmlspecialchars($row['persentase']); ?>%
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, H:i:s', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Belum ada riwayat kuis yang diselesaikan oleh siswa ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="fas fa-pencil-alt me-2"></i> Riwayat Tryout / Ujian</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-info text-info-custom">
                        <tr>
                            <th>#</th>
                            <th>Nama Ujian (Tryout)</th>
                            <th>Nilai (Persentase)</th>
                            <th>Waktu Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat_tryout->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $riwayat_tryout->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['tryout_title']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($row['persentase'] >= 75 ? 'success' : 'danger'); ?>">
                                        <?php echo htmlspecialchars($row['persentase']); ?>%
                                    </span>
                                </td>
                                <td><?php echo date('d M Y, H:i:s', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada riwayat tryout/ujian yang ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>