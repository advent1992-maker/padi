<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

if ($_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }

// Logika Verifikasi
if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    $conn->query("UPDATE users SET is_verified = 1 WHERE id = $id");
    header("Location: verifikasi.php?msg=success");
}

$pending_users = $conn->query("SELECT u.*, g.nama_lengkap as nama_guru FROM users u LEFT JOIN users g ON u.id_guru = g.id WHERE u.is_verified = 0 AND u.role = 'siswa'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi Pendaftar | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Pendaftar Menunggu Verifikasi</h3>
            <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Kelas</th>
                            <th>Guru Bimbingan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($pending_users->num_rows > 0): ?>
                            <?php while($u = $pending_users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $u['nama_lengkap'] ?></td>
                                <td><?= $u['username'] ?></td>
                                <td>Kelas <?= $u['kelas'] ?></td>
                                <td><?= $u['nama_guru'] ?></td>
                                <td>
                                    <a href="?approve=<?= $u['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Setujui siswa ini?')">Verifikasi</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">Tidak ada pendaftar baru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>