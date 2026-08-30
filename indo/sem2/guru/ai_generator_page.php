<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$id_materi = isset($_GET['id_materi']) ? (int)$_GET['id_materi'] : 0;

// PASTIKAN NAMA VARIABEL ADALAH $materi (bukan $Smateri atau lainnya)
$query = "SELECT judul FROM materi WHERE id = $id_materi";
$result = $db_mapel->query($query);
$materi = $result->fetch_assoc(); // Baris ini yang membuat variabel $materi

// Jika materi tidak ditemukan di database
if (!$materi) {
    die("Materi tidak ditemukan! Periksa kembali ID materi di database.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Kak PADI AI Pembuat Soal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ai-card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .robot-icon { font-size: 50px; color: #0dcaf0; }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card ai-card p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-robot robot-icon mb-3"></i>
                    <h2>Asisten Kak PADI Pembuat Soal</h2>
                    <p class="text-muted">Materi: <strong><?= $materi['judul'] ?></strong></p>
                </div>

                <form action="ai_generator_soal.php" method="POST" id="aiForm">
                    <input type="hidden" name="id_materi" value="<?= $id_materi ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Apa fokus pembahasan soalnya?</label>
                        <textarea name="keyword" class="form-control" rows="3"
                            placeholder="Contoh: Fokus pada simbol warna di peta dan legenda..."></textarea>
                        <div class="form-text">Biarkan kosong jika ingin soal umum tentang materi ini.</div>
                    </div>
<div class="mb-4">
    <label class="form-label fw-bold">Jumlah Soal</label>
    <input type="number" name="jumlah" class="form-control"
           min="1" max="20" value="5" placeholder="Masukkan jumlah soal...">
    <div class="form-text">Masukkan angka (Saran: 1 - 20 soal agar proses cepat).</div>
</div>


<div class="mb-4">
    <label class="form-label fw-bold">Tingkat Kesulitan</label>
    <select name="kesulitan" class="form-select">
        <option value="Mudah">Mudah (Pemahaman Dasar)</option>
        <option value="Sedang" selected>Sedang (Penerapan)</option>
        <option value="HOTS">Sulit / HOTS (Analisis & Penalaran)</option>
    </select>
    <div class="form-text">HOTS (Higher Order Thinking Skills) akan menghasilkan soal yang lebih menantang.</div>
</div>

                    <button type="submit" class="btn btn-info btn-lg w-100 text-white fw-bold" id="btnGenerate">
                        <i class="fas fa-magic"></i> Buat Soal
                    </button>

                    <div id="loading" class="text-center mt-3 d-none">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2">Kak Padi AI sedang berpikir... Mohon tunggu.</p>
                    </div>
                </form>
            </div>
            <div class="text-center mt-3">
                <a href="kuis_list.php" class="text-decoration-none text-secondary">← Kembali ke Daftar Materi</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('aiForm').onsubmit = function() {
        document.getElementById('btnGenerate').classList.add('d-none');
        document.getElementById('loading').classList.remove('d-none');
    };
</script>
</body>
</html>