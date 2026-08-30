<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// Proteksi: Hanya Guru/Admin yang boleh masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'siswa') {
    die("Akses ditolak! Halaman ini khusus Guru.");
}

// Proses Update Akses via AJAX/Post
if (isset($_POST['update_akses'])) {
    $id_siswa = $_POST['id_siswa'];
    $kolom = $_POST['kolom']; // akses_osn atau akses_stem
    $nilai = $_POST['nilai']; // 1 atau 0

    $q_up = "UPDATE users SET $kolom = ? WHERE id = ?";
    $stmt = $conn->prepare($q_up);
    $stmt->bind_param("ii", $nilai, $id_siswa);
    $stmt->execute();
    echo "success"; exit;
}

// Ambil daftar siswa (Hanya siswa dari Guru yang bersangkutan jika sistemnya kolaborasi)
$id_guru = $_SESSION['user_id'];
$q_siswa = "SELECT id, nama_lengkap, kelas, akses_osn, akses_stem FROM users WHERE role = 'siswa' AND id_guru = ?";
$stmt_s = $conn->prepare($q_siswa);
$stmt_s->bind_param("i", $id_guru);
$stmt_s->execute();
$res_siswa = $stmt_s->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panel Pengelola Program | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .card-akses { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .btn-toggle { cursor: pointer; transition: 0.3s; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold">Manajemen Pengembangan Diri</h2>
            <p class="text-muted">Kelola Tiket Siswa & Konten Pembelajaran</p>
        </div>
        <a href="../dashboard.php" class="btn btn-outline-secondary">Kembali</a>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card card-akses p-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-ticket-alt text-warning me-2"></i>Daftar Tiket Akses Siswa</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nama Siswa</th>
                                <th class="text-center">Akses OSN</th>
                                <th class="text-center">Akses STEM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($s = $res_siswa->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?= $s['nama_lengkap'] ?></span><br>
                                    <small class="text-muted">Kelas: <?= $s['kelas'] ?></small>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm <?= $s['akses_osn'] ? 'btn-success' : 'btn-light border' ?>" 
                                            onclick="updateAkses(<?= $s['id'] ?>, 'akses_osn', <?= $s['akses_osn'] ? 0 : 1 ?>)">
                                        <?= $s['akses_osn'] ? '<i class="fas fa-check-circle"></i> Aktif' : '<i class="fas fa-lock"></i> Kunci' ?>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm <?= $s['akses_stem'] ? 'btn-info text-white' : 'btn-light border' ?>" 
                                            onclick="updateAkses(<?= $s['id'] ?>, 'akses_stem', <?= $s['akses_stem'] ? 0 : 1 ?>)">
                                        <?= $s['akses_stem'] ? '<i class="fas fa-check-circle"></i> Aktif' : '<i class="fas fa-lock"></i> Kunci' ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card card-akses p-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-edit text-primary me-2"></i>Kelola Soal & Materi</h5>
                <div class="list-group list-group-flush">
                    <?php 
                    $kats = [
                        ['id' => 'osn', 'nama' => 'Materi & Soal OSN', 'color' => 'warning', 'icon' => 'trophy'],
                        ['id' => 'stem', 'nama' => 'Materi & Soal STEM', 'color' => 'info', 'icon' => 'microscope'],
                        ['id' => 'literasi', 'nama' => 'Bank Literasi', 'color' => 'primary', 'icon' => 'book-open'],
                        ['id' => 'numerasi', 'nama' => 'Bank Numerasi', 'color' => 'success', 'icon' => 'percentage'],
                        ['id' => 'coding', 'nama' => 'Modul Coding', 'color' => 'dark', 'icon' => 'code']
                    ];
                    foreach($kats as $k): ?>
                    <a href="input_soal.php?kat=<?= $k['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <span><i class="fas fa-<?= $k['icon'] ?> text-<?= $k['color'] ?> me-2"></i> <?= $k['nama'] ?></span>
                        <i class="fas fa-chevron-right small text-muted"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateAkses(idSiswa, kolom, nilai) {
    const formData = new FormData();
    formData.append('update_akses', true);
    formData.append('id_siswa', idSiswa);
    formData.append('kolom', kolom);
    formData.append('nilai', nilai);

    fetch('kelola_akses_dan_materi.php', {
        method: 'POST',
        body: formData
    }).then(() => location.reload());
}
</script>

</body>
</html>