<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Pastikan user_id ada di URL
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">ID Guru tidak valid atau tidak ditemukan.</div>';
    header("Location: progres_guru.php");
    exit();
}

$guru_id = $_GET['user_id'];
$guru_data = null;

// 1. Ambil data profil Guru
$stmt = $db_mapel->prepare("SELECT id, username, nama_lengkap, email, created_at, is_verified FROM users WHERE id = ? AND role = 'guru'");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $guru_data = $result->fetch_assoc();
} else {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">Guru tidak ditemukan atau bukan merupakan akun guru.</div>';
    header("Location: progres_guru.php");
    exit();
}
$stmt->close();

// 2. Ambil Daftar Materi yang dibuat Guru (DIFILTER menggunakan kolom id_guru)
$materi_query = "
    SELECT
        id,
        judul AS title,
        created_at
    FROM materi
    WHERE id_guru = ?
    ORDER BY created_at DESC
";
$stmt_materi = $db_mapel->prepare($materi_query);
$stmt_materi->bind_param("i", $guru_id);
$stmt_materi->execute();
$materi_list = $stmt_materi->get_result();
$total_materi_dibuat = $materi_list->num_rows;
$stmt_materi->close();

// 3. Ambil Daftar Kuis/Tryout yang dibuat Guru (DIFILTER menggunakan kolom id_guru)
$kuis_query = "
    SELECT
        id,
        judul AS title,
        jenis_ujian,
        kelas
    FROM tryout_master
    WHERE id_guru = ?
    ORDER BY id DESC
";
$stmt_kuis = $db_mapel->prepare($kuis_query);
$stmt_kuis->bind_param("i", $guru_id);
$stmt_kuis->execute();
$kuis_list = $stmt_kuis->get_result();
$total_kuis_dibuat = $kuis_list->num_rows;
$stmt_kuis->close();

$total_kontribusi = $total_materi_dibuat + $total_kuis_dibuat;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Progres Guru | Admin Mathfiction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .contribution-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .contribution-card:hover {
            transform: translateY(-5px);
        }
        .text-bg-info.text-white {
            background-color: #0dcaf0 !important; /* Warna info default Bootstrap */
        }
        .btn-action-group {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1><i class="fas fa-chalkboard-teacher me-2"></i> Detail Progres Guru</h1>
        <a href="progres_guru.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Guru</a>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> Data Profil Guru</h5>
        </div>
        <div class="card-body">
            <?php if ($guru_data): ?>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($guru_data['nama_lengkap']); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($guru_data['username']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($guru_data['email']); ?></p>
                    <p><strong>Status Akun:</strong>
                        <span class="badge bg-<?php echo ($guru_data['is_verified'] == 1 ? 'info' : 'warning'); ?>">
                            <?php echo ($guru_data['is_verified'] == 1 ? 'Terverifikasi' : 'Menunggu Verifikasi'); ?>
                        </span>
                    </p>
                    <p><strong>ID Guru:</strong> <?php echo $guru_data['id']; ?></p>
                    <p><strong>Terdaftar Sejak:</strong> <?php echo date('d M Y', strtotime($guru_data['created_at'])); ?></p>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-danger">Data guru tidak ditemukan.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card text-center text-bg-primary contribution-card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-boxes me-1"></i> Total Kontribusi</h5>
                    <p class="card-text fs-2 fw-bold"><?php echo $total_kontribusi; ?></p>
                    <small>Materi & Kuis</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center text-bg-info text-white contribution-card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book me-1"></i> Materi/kuis Dibuat</h5>
                    <p class="card-text fs-2 fw-bold"><?php echo $total_materi_dibuat; ?></p>
                    <small>Materi</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center text-bg-warning contribution-card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-puzzle-piece me-1"></i> tryout Dibuat</h5>
                    <p class="card-text fs-2 fw-bold"><?php echo $total_kuis_dibuat; ?></p>
                    <small>Tryout</small>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="kontribusiTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="materi-tab" data-bs-toggle="tab" data-bs-target="#materi" type="button" role="tab" aria-controls="materi" aria-selected="true">
                <i class="fas fa-book me-1"></i> Daftar Materi (<?php echo $total_materi_dibuat; ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="kuis-tab" data-bs-toggle="tab" data-bs-target="#kuis" type="button" role="tab" aria-controls="kuis" aria-selected="false">
                <i class="fas fa-puzzle-piece me-1"></i> Daftar tryout (<?php echo $total_kuis_dibuat; ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="kontribusiTabContent">
        <div class="tab-pane fade show active" id="materi" role="tabpanel" aria-labelledby="materi-tab">
            <div class="mt-3 table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-success text-white">
                        <tr>
                            <th>ID</th>
                            <th>Judul Materi</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($materi_list->num_rows > 0): ?>
                            <?php $materi_list->data_seek(0); while ($row = $materi_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo date('d M Y H:i:s', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-action-group">
                                            <!-- LINK DIUBAH ke view_materi_admin.php -->
                                            <a href="view_materi_admin.php?id=<?php echo $row['id']; ?>&user_id=<?php echo $guru_id; ?>"
                                               class="btn btn-sm btn-info text-white"
                                               target="_blank" title="Lihat dan Review Konten Materi">
                                                <i class="fas fa-eye me-1"></i> Lihat
                                            </a>
                                            <!-- Tombol Hapus Materi -->
                                            <a href="hapus_konten.php?type=materi&id=<?php echo $row['id']; ?>&user_id=<?php echo $guru_id; ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus materi: <?php echo htmlspecialchars($row['title']); ?>? Aksi ini TIDAK dapat dibatalkan.');"
                                               title="Hapus Konten Materi">
                                                <i class="fas fa-trash-alt me-1"></i> Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Guru ini belum membuat materi apa pun.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="kuis" role="tabpanel" aria-labelledby="kuis-tab">
            <div class="mt-3 table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-success text-white">
                        <tr>
                            <th>ID</th>
                            <th>Judul Kuis</th>
                            <th>Jenis Ujian</th>
                            <th>Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($kuis_list->num_rows > 0): ?>
                            <?php $kuis_list->data_seek(0); while ($row = $kuis_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['jenis_ujian']); ?></td>
                                    <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                                    <td>
                                        <div class="btn-action-group">
                                            <!-- Tombol Lihat Kuis (Sudah benar ke view_kuis.php di folder admin) -->
                                            <a href="view_kuis.php?id=<?php echo $row['id']; ?>&user_id=<?php echo $guru_id; ?>"
                                               class="btn btn-sm btn-info text-white"
                                               target="_blank" title="Lihat Detail Kuis">
                                                <i class="fas fa-eye me-1"></i> Lihat
                                            </a>
                                            <!-- Tombol Hapus Kuis -->
                                            <a href="hapus_konten.php?type=kuis&id=<?php echo $row['id']; ?>&user_id=<?php echo $guru_id; ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus kuis: <?php echo htmlspecialchars($row['title']); ?>? Aksi ini TIDAK dapat dibatalkan.');"
                                               title="Hapus Kuis">
                                                <i class="fas fa-trash-alt me-1"></i> Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Guru ini belum membuat kuis apa pun.</td>
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