<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil semua pengguna dengan peran 'guru'
$query = "SELECT id, username, nama_lengkap, email, created_at, is_verified FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC";
$result = $db_mapel->query($query);

// Ambil pesan notifikasi (jika ada)
$message = "";
if (isset($_SESSION['progres_guru_message'])) {
    $message = $_SESSION['progres_guru_message'];
    unset($_SESSION['progres_guru_message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progres Guru | Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-graduation-cap me-2"></i> Progres Guru</h1>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard</a>
    </div>

    <?php echo $message; ?>

    <p class="text-muted">
        Daftar semua guru di sistem. Klik tombol 'Lihat Konten' untuk melihat detail materi dan soal yang telah mereka buat.
    </p>

    <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead class="table-info text-white">
                <tr>
                    <th>ID</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Status Akun</th>
                    <th>Tanggal Daftar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($user['is_verified'] == 1 ? 'info' : 'warning'); ?>">
                                    <?php echo ($user['is_verified'] == 1 ? 'Terverifikasi' : 'Menunggu Verifikasi'); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="progres_detail_guru.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info text-white">
                                    <i class="fas fa-folder-open me-1"></i> Lihat Konten
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada akun guru terdaftar saat ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
