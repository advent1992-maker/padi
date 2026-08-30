<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// Proteksi Admin/Guru
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'guru')) {
    header("Location: ../index.php");
    exit;
}

$id_user = $_GET['id_user'] ?? 0;
$kode_app = $_GET['app'] ?? 'PADI_PORTAL';

// Ambil Identitas Siswa
$q_identitas = "SELECT nama_lengkap, kelas FROM users WHERE id = '$id_user'";
$siswa = $conn->query($q_identitas)->fetch_assoc();

if (!$siswa) die("Siswa tidak ditemukan.");

// Ambil Detail Jawaban
$q_detail = "SELECT indikator, skor_penilaian 
             FROM hasil_uji_siswa 
             WHERE id_user = '$id_user' AND kode_aplikasi = '$kode_app'";
$res_detail = mysqli_query($conn, $q_detail);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Penilaian Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-detail { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .skor-box { width: 40px; height: 40px; line-height: 40px; text-align: center; background: #764ba2; color: white; border-radius: 10px; font-weight: bold; }
    </style>
</head>
<body>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold mb-0">Rincian Penilaian Siswa</h3>
                    <p class="text-muted">Nama: <?= htmlspecialchars($siswa['nama_lengkap']) ?> | Kelas: <?= htmlspecialchars($siswa['kelas']) ?></p>
                </div>
                <a href="rekap_siswa_padi.php?app=<?= $kode_app ?>" class="btn btn-secondary rounded-pill px-4">Kembali</a>
            </div>

            <div class="card card-detail p-4">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Indikator Penilaian</th>
                            <th width="100" class="text-center">Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($res_detail)): ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars($row['indikator']) ?></td>
                            <td class="text-center">
                                <div class="skor-box mx-auto"><?= $row['skor_penilaian'] ?></div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center mt-4">
                <button onclick="window.print()" class="btn btn-outline-dark px-4"><i class="fas fa-print me-2"></i>Cetak Rincian</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>