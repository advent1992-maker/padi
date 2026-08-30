<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$tryout_id = isset($_GET['tryout_id']) ? (int)$_GET['tryout_id'] : 0;

// Ambil info tryout
$query = "SELECT judul, kelas FROM tryout_master WHERE id = $tryout_id";
$result = $db_mapel->query($query);
$tryout = $result->fetch_assoc();

if (!$tryout) { die("Data Try Out tidak ditemukan!"); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Kak PADI AI Soal Tryout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ai-card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .robot-head { font-size: 60px; color: #0dcaf0; animation: float 3s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card ai-card p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-graduation-cap robot-head mb-3"></i>
                    <h2>Kak Padi AI Pembuat Soal Tryout</h2>
                    <p class="text-muted">Ujian: <strong><?= htmlspecialchars($tryout['judul']) ?></strong> (Kelas <?= $tryout['kelas'] ?>)</p>
                </div>

                <form action="ai_generator_tryout_proses.php" method="POST" id="aiForm">
                    <input type="hidden" name="tryout_id" value="<?= $tryout_id ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Cakupan Materi Try Out</label>
                        <textarea name="keyword" class="form-control" rows="3" required
                            placeholder="Contoh: Campuran materi Peta, Kenampakan Alam, dan Kerajaan Hindu-Buddha..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jumlah Soal</label>
                        <input type="number" name="jumlah" class="form-control" min="1" max="40" value="15">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Tingkat Kesulitan</label>
                        <select name="kesulitan" class="form-select">
                            <option value="Mudah">Mudah</option>
                            <option value="Sedang" selected>Sedang (Standar)</option>
                            <option value="HOTS">HOTS (Analisis Tinggi)</option>
                            <option value="Campuran">Campuran (Mudah ke HOTS)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-info btn-lg w-100 text-white fw-bold" id="btnGenerate">
                        <i class="fas fa-magic"></i> Buat Soal Try Out
                    </button>

                    <div id="loading" class="text-center mt-3 d-none">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2 text-info fw-bold">Kak Padi AI sedang merakit soal ujian... <br>Harap tunggu sebentar.</p>
                    </div>
                </form>
            </div>
            <div class="text-center mt-3">
                <a href="manajemen_tryout.php" class="text-secondary text-decoration-none">← Batal & Kembali</a>
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