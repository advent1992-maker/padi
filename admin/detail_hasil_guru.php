<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Ambil ID User dari URL
$id_user = isset($_GET['id_user']) ? mysqli_real_escape_string($conn, $_GET['id_user']) : 0;
$kode_app = 'PADI_PORTAL';

// Ambil data detail penilaian guru
$query = "SELECT h.*, u.nama_lengkap 
          FROM hasil_uji_guru h 
          JOIN users u ON h.id_user = u.id 
          WHERE h.id_user = '$id_user' AND h.kode_aplikasi = '$kode_app'
          LIMIT 1";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

// Jika data tidak ditemukan
if (!$data) {
    die("<div class='container mt-5 alert alert-danger'>Data penilaian untuk guru ini tidak ditemukan.</div>");
}

// Definisi teks instrumen (harus sama dengan yang ada di dashboard guru)
$instrumen = [
    1 => "Aplikasi PADI memiliki antarmuka yang ramah untuk anak SD.",
    2 => "Navigasi antar mata pelajaran sangat mudah dioperasikan.",
    3 => "Aplikasi berjalan dengan stabil pada perangkat smartphone.",
    4 => "Keamanan data pengguna (login) sudah terjamin dengan baik.",
    5 => "Materi yang disajikan relevan dengan tujuan pembelajaran kurikulum.",
    6 => "Fitur Asisten AI Kak PADI memberikan jawaban yang edukatif.",
    7 => "Media ini mampu memotivasi siswa untuk belajar mandiri.",
    8 => "Bahasa yang digunakan dalam materi mudah dipahami siswa.",
    9 => "Fitur rekap nilai membantu guru dalam evaluasi berkala.",
    10 => "Secara umum, aplikasi PADI sangat layak diimplementasikan di sekolah."
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penilaian: <?= htmlspecialchars($data['nama_lengkap']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card-detail { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .skor-number { 
            width: 45px; height: 45px; 
            line-height: 45px; text-align: center; 
            background: #764ba2; color: white; 
            border-radius: 12px; font-weight: bold; font-size: 1.2rem;
        }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <a href="rekap_guru_padi.php" class="btn btn-light rounded-pill px-4 text-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Rekap
                </a>
                <button onclick="window.print()" class="btn btn-dark rounded-pill px-4">
                    <i class="fas fa-print me-2"></i> Cetak Detail
                </button>
            </div>

            <div class="card card-detail p-4 mb-4 border-start border-primary border-5">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="text-uppercase text-muted small fw-bold mb-1">Responden Praktisi</h6>
                        <h3 class="fw-bold text-dark mb-0"><?= htmlspecialchars($data['nama_lengkap']) ?></h3>
                        <p class="text-muted mb-0">Diselesaikan pada: <?= date('d F Y, H:i', strtotime($data['tanggal_uji'])) ?> WIB</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <div class="d-inline-block text-center">
                            <h2 class="fw-bold text-primary mb-0"><?= number_format($data['skor'], 1) ?>%</h2>
                            <small class="text-muted fw-bold">SKOR TOTAL</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-detail p-4 mb-4">
                <h5 class="fw-bold mb-4 border-bottom pb-3 text-primary">
                    <i class="fas fa-list-check me-2"></i> Rincian Skor per Indikator
                </h5>
                
                <?php for($i=1; $i<=10; $i++): 
                    $skor_item = $data['q'.$i];
                    // Warna dinamis berdasarkan skor
                    $bg_color = ($skor_item >= 4) ? '#198754' : (($skor_item >= 3) ? '#f1c40f' : '#dc3545');
                ?>
                <div class="d-flex align-items-center justify-content-between mb-4 p-2 rounded-3 hover-shadow">
                    <div class="me-3">
                        <span class="text-muted d-block small">Pertanyaan <?= $i ?></span>
                        <span class="fw-medium text-dark"><?= $instrumen[$i] ?></span>
                    </div>
                    <div class="skor-number shadow-sm" style="background-color: <?= $bg_color ?>;">
                        <?= $skor_item ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="card card-detail p-4 bg-white">
                <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-comment-dots me-2"></i> Saran & Masukan</h5>
                <div class="p-4 rounded-4 bg-light border-0">
                    <p class="mb-0 fst-italic text-secondary" style="font-size: 1.1rem; line-height: 1.6;">
                        "<?= nl2br(htmlspecialchars($data['saran'])) ?>"
                    </p>
                </div>
            </div>

            <p class="text-center mt-5 text-muted small no-print">
                &copy; 2026 Portal PADI - Laporan Individual Guru
            </p>

        </div>
    </div>
</div>

</body>
</html>