<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role
require_once '../../../config/ai_helper.php'; // Panggil fungsi Gemini

// Hanya Role 'guru' atau 'admin' yang bisa mengakses
if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';

// =======================================================================
// 1. Handle AJAX Request (Proses Rakit AI)
// =======================================================================
if (isset($_POST['minta_ai'])) {
    $judul      = $_POST['judul_ai'] ?? '';
    $isi_mentah = $_POST['isi_mentah'] ?? '';
    $file_template = '../../../template_ai.html';
    
    $contoh_kode = file_exists($file_template) ? file_get_contents($file_template) : "Gunakan struktur HTML slide interaktif standar.";

    $prompt = "Tugasmu adalah merakit materi MATEMATIKA menjadi HTML slide interaktif.
               Gunakan gaya desain dari contoh ini: $contoh_kode
               
               MATERI:
               Judul: $judul
               Isi: $isi_mentah

               KETENTUAN:
               1. Gunakan CSS dan struktur yang sama dengan contoh.
               2. Sertakan navigasi slide yang berfungsi.
               3. Output hanya kode HTML UTUH tanpa penjelasan teks.";

    $hasil_ai = panggil_gemini($prompt);
    echo (strpos($hasil_ai, 'CURL_ERROR') === false) ? $hasil_ai : "ERROR_TIMEOUT";
    exit();
}

// =======================================================================
// 2. Handle POST Request (SIMPAN FINAL ke Database)
// =======================================================================
$pesan = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['minta_ai'])) {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $level_kategori = $_POST['level_kategori'] ?? '';
    $file_path = trim($_POST['file_path'] ?? '');
    $konten_materi = $_POST['konten_materi'] ?? ''; // Kolom baru
    $post_id = $_POST['materi_id'] ?? null;

    if ($post_id) {
        // UPDATE MATERI (Termasuk konten_materi)
        $stmt = $db_mapel->prepare("UPDATE materi SET judul=?, deskripsi=?, level_kategori=?, file_path=?, konten_materi=? WHERE id=? AND id_guru=?");
        $stmt->bind_param("ssissii", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $post_id, $user_id);
    } else {
        // INSERT MATERI BARU
        $stmt = $db_mapel->prepare("INSERT INTO materi (judul, deskripsi, level_kategori, file_path, konten_materi, id_guru) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissi", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['pesan_sukses'] = "Materi berhasil disimpan!";
        header("Location: materi_list.php");
        exit();
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal simpan: " . $db_mapel->error . "</div>";
    }
}

// =======================================================================
// 3. Fetch Data (Mode Edit)
// =======================================================================
$materi_id = $_GET['id'] ?? null;
$materi = ['judul' => '', 'deskripsi' => '', 'level_kategori' => '', 'file_path' => '', 'konten_materi' => ''];
if ($materi_id) {
    $stmt = $db_mapel->prepare("SELECT * FROM materi WHERE id = ? AND id_guru = ?");
    $stmt->bind_param("ii", $materi_id, $user_id);
    $stmt->execute();
    $materi = $stmt->get_result()->fetch_assoc() ?: $materi;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penyusun Materi Matematika | MATHFICTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f0f8ff; font-family: 'Poppins', sans-serif; }
        .card-custom { border-radius: 15px; box-shadow: 0 6px 15px rgba(0,0,0,0.1); border: none; }
        .ai-zone { background: #e3f2fd; border: 2px dashed #0d6efd; border-radius: 15px; padding: 20px; }
        .preview-window { width: 100%; height: 500px; border: 2px solid #ddd; border-radius: 15px; background: white; transition: 0.3s; }
        .preview-window:fullscreen { height: 100vh; width: 100vw; border: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">MATHFICTION GURU</a>
        <a href="materi_list.php" class="btn btn-outline-light btn-sm">Batal / Kembali</a>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <?= $pesan; ?>
    <form method="POST" id="formMateri">
        <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
        
        <div class="row g-4">
            <div class="col-md-7">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-file-alt"></i> Data Materi</h5>
                    
                    <div class="mb-3">
                        <label class="fw-bold small">Judul Bab/Materi</label>
                        <input type="text" class="form-control" name="judul" id="judul_form" value="<?= htmlspecialchars($materi['judul']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Deskripsi Singkat</label>
                        <textarea class="form-control" name="deskripsi" rows="2" required><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small">Target Kelas</label>
                            <select class="form-select" name="level_kategori" required>
                                <?php foreach(range(1,6) as $l): ?>
                                    <option value="<?= $l ?>" <?= $materi['level_kategori'] == $l ? 'selected' : '' ?>>Kelas <?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-danger">File Path (.html)</label>
                            <input type="text" class="form-control" name="file_path" value="<?= htmlspecialchars($materi['file_path']) ?>" placeholder="bab1.html">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-primary">Isi Konten HTML (AI / Manual)</label>
                        <textarea class="form-control" name="konten_materi" id="kode_html" rows="10" style="font-family: monospace;" oninput="updatePreview()"><?= htmlspecialchars($materi['konten_materi']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-3 rounded-pill shadow">SIMPAN SEMUA DATA</button>
                </div>
            </div>

            <div class="col-md-5">
                <div class="ai-zone shadow-sm">
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-robot"></i> Rakit Materi dengan AI</h5>
                    <p class="small text-muted">Masukkan ringkasan materi, AI akan merakitnya menjadi slide interaktif sesuai template Bapak.</p>
                    <textarea class="form-control mb-3" id="ai_mentah" rows="12" placeholder="Contoh: Materi tentang Penjumlahan Pecahan berpenyebut beda..."></textarea>
                    <button type="button" class="btn btn-primary w-100 fw-bold py-3 rounded-pill" id="btnAI">MULAI RAKIT AI</button>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-warning mb-0"><i class="fas fa-eye"></i> Pratinjau Langsung</h5>
                        <button type="button" onclick="fullscreenPreview()" class="btn btn-sm btn-outline-warning rounded-pill">
                            <i class="fas fa-expand-alt"></i> Layar Penuh
                        </button>
                    </div>
                    <iframe id="live_preview" class="preview-window"></iframe>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function updatePreview() {
        const kode = document.getElementById('kode_html').value;
        const previewFrame = document.getElementById('live_preview');
        const previewDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
        previewDoc.open();
        previewDoc.write(kode || '<body style="color:#ccc;text-align:center;padding-top:100px;">Pratinjau Materi Kosong</body>');
        previewDoc.close();
    }

    function fullscreenPreview() {
        const iframe = document.getElementById('live_preview');
        if (iframe.requestFullscreen) iframe.requestFullscreen();
        else if (iframe.webkitRequestFullscreen) iframe.webkitRequestFullscreen();
    }

    window.onload = updatePreview;

    document.getElementById('btnAI').onclick = function() {
        const judul = document.getElementById('judul_form').value;
        const mentah = document.getElementById('ai_mentah').value;
        if(!judul || !mentah) return Swal.fire('Lengkapi Data', 'Judul dan Materi Mentah wajib diisi.', 'info');

        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang Merakit...';
        this.disabled = true;

        const fd = new FormData();
        fd.append('minta_ai', 1);
        fd.append('judul_ai', judul);
        fd.append('isi_mentah', mentah);

        fetch('materi_form.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(d => {
            this.innerHTML = 'MULAI RAKIT AI';
            this.disabled = false;
            if(d === "ERROR_TIMEOUT") {
                Swal.fire('Error', 'AI butuh waktu terlalu lama. Coba materi yang lebih pendek.', 'error');
            } else {
                document.getElementById('kode_html').value = d;
                updatePreview();
                Swal.fire('Berhasil!', 'Materi Matematika telah dirakit. Silakan cek preview lalu klik Simpan.', 'success');
            }
        });
    };
</script>
</body>
</html>