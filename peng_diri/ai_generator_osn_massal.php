<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$paket_id = isset($_GET['paket_id']) ? (int)$_GET['paket_id'] : 0;

// Samakan nama variabel dengan file input agar tidak bingung
$conn_pd = $conn;

// Ambil info paket menggunakan variabel $conn_pd
$query = "SELECT nama_paket FROM paket_peng_diri WHERE id = $paket_id";
$result = mysqli_query($conn_pd, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $paket = mysqli_fetch_assoc($result);
} else { 
    die("Data Paket OSN tidak ditemukan! ID: " . $paket_id); 
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>AI Generator OSN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ai-card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card ai-card p-4 border-0">
                <div class="text-center mb-4">
                    <i class="fas fa-robot text-info mb-3" style="font-size: 50px;"></i>
                    <h2>Generator Soal OSN</h2>
                    <p class="text-muted">Paket: <strong><?= $paket['nama_paket'] ?></strong></p>
                </div>

                <form action="ai_osn_massal.php" method="POST" id="aiForm">
                    <input type="hidden" name="paket_id" value="<?= $paket_id ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Topik Spesifik OSN</label>
                        <textarea name="topik" class="form-control" rows="3" required 
                            placeholder="Contoh: Klasifikasi Makhluk Hidup atau Gerak Lurus..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-info btn-lg w-100 text-white fw-bold" id="btnSubmit">
                        <i class="fas fa-magic"></i> Buat Soal
                    </button>

                    <div id="loading" class="text-center mt-3 d-none">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2 fw-bold text-info">AI sedang merakit soal...<br>
                        <small class="text-muted">Proses ini butuh waktu sekitar 1 menit.</small></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Hanya untuk memunculkan efek loading saat tombol ditekan
    document.getElementById('aiForm').onsubmit = function() {
        document.getElementById('btnSubmit').classList.add('d-none');
        document.getElementById('loading').classList.remove('d-none');
    };
</script>
</body>
</html>