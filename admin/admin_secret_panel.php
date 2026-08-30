<?php
// 1. Tambahkan ini di paling atas untuk melacak eror jika muncul lagi
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Perbaiki jalur koneksi (Sesuaikan dengan lokasi file Anda)
// Jika file ini di dalam folder 'admin', gunakan '../config/koneksi.php'
// Jika file ini di luar, gunakan 'config/koneksi.php'
if (file_exists('config/koneksi.php')) {
    require_once 'config/koneksi.php';
} elseif (file_exists('../config/koneksi.php')) {
    require_once '../config/koneksi.php';
} else {
    die("Error: File koneksi.php tidak ditemukan!");
}

// 3. Pastikan koneksi database tersedia
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Fitur Reset Password
if (isset($_GET['reset_id'])) {
    $id_target = mysqli_real_escape_string($conn, $_GET['reset_id']);
    $query_reset = "UPDATE users SET password = '123456' WHERE id = '$id_target'";
    if(mysqli_query($conn, $query_reset)) {
        header("Location: admin_secret_panel.php?status=success");
        exit;
    }
}

// Ambil data User
$q_all = "SELECT id, nama_lengkap, role, username, password, kelas FROM users ORDER BY role DESC, nama_lengkap ASC";
$res = mysqli_query($conn, $q_all);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PRIVATE ADMIN - USER MANAGER</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #1a202c; color: #e2e8f0; padding: 20px; }
        .panel { max-width: 1000px; margin: auto; background: #2d3748; border-radius: 12px; padding: 25px; box-shadow: 0 10px 15px rgba(0,0,0,0.5); }
        h1 { color: #f6ad55; margin-top: 0; border-bottom: 2px solid #4a5568; padding-bottom: 10px; }
        .alert { background: #48bb78; color: white; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #4a5568; color: #cbd5e0; padding: 12px; text-align: left; text-transform: uppercase; font-size: 12px; }
        td { padding: 12px; border-bottom: 1px solid #4a5568; }
        .pass-text { background: #1a202c; color: #48bb78; padding: 3px 6px; border-radius: 4px; font-family: monospace; }
        .hash-text { color: #a0aec0; font-size: 11px; font-style: italic; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .GURU { background: #805ad5; }
        .SISWA { background: #3182ce; }
        .btn-reset { background: #e53e3e; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; transition: 0.2s; }
        .btn-reset:hover { background: #c53030; }
    </style>
</head>
<body>

<div class="panel">
    <h1>🔑 Private Access: User Database</h1>
    
    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert">✅ Password berhasil direset menjadi: 123456</div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Nama Pengguna</th>
                    <th>Username</th>
                    <th>Password Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if($res): ?>
                    <?php while($row = mysqli_fetch_assoc($res)): 
                        $is_hash = (strlen($row['password']) > 20 || strpos($row['password'], '$2y$') === 0);
                    ?>
                    <tr>
                        <td><span class="badge <?= strtoupper($row['role']) ?>"><?= strtoupper($row['role']) ?></span></td>
                        <td><b><?= $row['nama_lengkap'] ?></b><br><small><?= $row['kelas'] ? "Kelas ".$row['kelas'] : "-" ?></small></td>
                        <td><code><?= $row['username'] ?></code></td>
                        <td>
                            <?php if($is_hash): ?>
                                <span class="hash-text">Terenskripsi (Hash)</span>
                            <?php else: ?>
                                <span class="pass-text"><?= $row['password'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?reset_id=<?= $row['id'] ?>" class="btn-reset" onclick="return confirm('Reset password <?= $row['nama_lengkap'] ?>?')">Reset</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">Data tidak ditemukan atau query bermasalah.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>