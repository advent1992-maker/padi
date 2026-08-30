<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$paket_id = isset($_GET['paket_id']) ? (int)$_GET['paket_id'] : 0;

// Gunakan nama variabel yang konsisten dengan input_osn.php
$conn_pd = $conn; 

// Ambil info paket - Gunakan $conn_pd yang sudah didefinisikan
$query = "SELECT nama_paket FROM paket_peng_diri WHERE id = $paket_id";
$result = mysqli_query($conn_pd, $query);

// Cek apakah query berhasil dan ada datanya
if ($result && mysqli_num_rows($result) > 0) {
    $paket = mysqli_fetch_assoc($result);
} else { 
    die("Data Paket OSN tidak ditemukan! ID: " . $paket_id); 
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>OSN AI Question Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .ai-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .robot-icon { font-size: 50px; color: #0d6efd; }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card ai-card p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-robot robot-icon mb-3"></i>
                    <h3 class="fw-bold">OSN AI Generator</h3>
                    <p class="text-muted">Paket: <strong><?= htmlspecialchars($paket['nama_paket']) ?></strong></p>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Topik Spesifik OSN</label>
                    <textarea id="prompt_ai" class="form-control" rows="3" 
                        placeholder="Contoh: Mekanika Fluida untuk OSN Fisika tingkat SMA dengan tingkat kesulitan tinggi..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small">Tingkat Kesulitan</label>
                    <select id="level_ai" class="form-select">
                        <option value="HOTS">HOTS (Analisis Tinggi)</option>
                        <option value="Olimpiade Nasional">Olimpiade Nasional (Sangat Sulit)</option>
                        <option value="Internasional">Level Internasional</option>
                    </select>
                </div>

                <button type="button" onclick="generateSoal()" class="btn btn-primary btn-lg w-100 fw-bold shadow" id="btnGen">
                    <i class="fas fa-magic me-2"></i> Rancang Soal Sekarang
                </button>

                <div id="loading" class="text-center mt-3 d-none">
                    <div class="spinner-grow text-primary" role="status"></div>
                    <p class="mt-2 text-primary fw-bold">AI sedang berpikir keras... <br><small class="text-muted">Ini membutuhkan waktu sekitar 10-20 detik.</small></p>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="input_osn.php?paket_id=<?= $paket_id ?>" class="text-secondary text-decoration-none small">← Kembali ke Input Manual</a>
            </div>
        </div>
    </div>
</div>

<script>
function generateSoal() {
    const prompt = document.getElementById('prompt_ai').value;
    const level = document.getElementById('level_ai').value;

    if(!prompt) { alert("Tuliskan topik soalnya dulu!"); return; }

    document.getElementById('btnGen').classList.add('d-none');
    document.getElementById('loading').classList.remove('d-none');

   // Cari bagian ini di ai_generator_osn.php dan sesuaikan:
fetch('ai_osn_proses.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    // TAMBAHKAN paket_id di sini
    body: 'prompt=' + encodeURIComponent(prompt) + 
          '&level=' + encodeURIComponent(level) + 
          '&paket_id=<?= $paket_id ?>' 
})
.then(response => response.json())
.then(data => {
    if(data.status === 'success') {
        // Langsung arahkan kembali ke list soal, datanya sudah masuk DB
        window.location.href = 'input_osn.php?paket_id=<?= $paket_id ?>';
    } else {
        alert("Gagal: " + data.message);
        location.reload();
    }
})
    .catch(err => {
        console.error(err);
        alert("Gagal terhubung ke server AI.");
        location.reload();
    });
}
</script>
</body>
</html>