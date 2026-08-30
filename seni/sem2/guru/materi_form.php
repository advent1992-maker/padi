<?php
require_once '../config/koneksi.php';    
require_once '../config/session.php';    
require_once '../config/auth_check.php'; 
require_once '../../../config/ai_helper.php'; 

// Mengambil ID Guru yang sedang dibantu (kolaborasi) atau ID sendiri jika sedang mandiri
$user_id_session = $_SESSION['user_id']; 
$id_guru_tujuan = $_SESSION['id_guru_pilihan'] ?? $user_id_session;

// --- 1. LOGIKA AI (AJAX) ---
if (isset($_POST['minta_ai'])) {
    $judul = $_POST['judul_ai'] ?? '';
    $isi = $_POST['isi_mentah'] ?? '';
    $prompt = "Buat materi seni interaktif HTML. Judul: $judul. Konten: $isi. Berikan kode HTML saja.";
    echo panggil_gemini($prompt);
    exit();
}

// --- 2. SIMPAN DATA (INSERT/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['minta_ai'])) {
    $final_guru_id = $_POST['id_guru_target'] ?? $id_guru_tujuan;
    $post_id          = $_POST['materi_id'] ?: null;
    $judul            = $_POST['judul'] ?? '';
    $deskripsi        = $_POST['deskripsi'] ?? '';
    $level_kategori   = $_POST['level_kategori'] ?? ''; 
    $file_path        = $_POST['file_path'] ?? '';
    $tipe_materi      = $_POST['tipe_materi'] ?? 'teori';
    $konten_materi    = $_POST['konten_materi'] ?? '';
    
    $pakai_kuis       = isset($_POST['pakai_kuis']) ? 1 : 0;
    $pakai_praktek    = isset($_POST['pakai_praktek']) ? 1 : 0;
    $tampilkan_kuis   = $pakai_kuis; 

    // --- LOGIKA KHUSUS TIPI MATERI ---
    if ($tipe_materi == 'gambar' && !empty($_FILES['file_gambar']['name'])) {
        $target_dir = "../uploads/materi/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $nama_file = "img_" . time() . "_" . basename($_FILES["file_gambar"]["name"]);
        if (move_uploaded_file($_FILES["file_gambar"]["tmp_name"], $target_dir . $nama_file)) {
            $konten_materi = $nama_file;
        }
    } elseif ($tipe_materi == 'video') {
        // Otomatis ubah link YouTube biasa menjadi format Embed agar bisa tampil di iframe
        $url = $_POST['video_url'] ?? '';
        if (preg_match('/(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&?\/]+)/', $url, $match)) {
            $konten_materi = "https://www.youtube.com/embed/" . $match[2];
        } else {
            $konten_materi = $url;
        }
    }

    if ($post_id) {
        $sql = "UPDATE materi SET judul=?, deskripsi=?, level_kategori=?, file_path=?, konten_materi=?, tampilkan_kuis=?, pakai_kuis=?, pakai_praktek=? WHERE id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("sssssiiii", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $tampilkan_kuis, $pakai_kuis, $pakai_praktek, $post_id);
    } else {
        $sql = "INSERT INTO materi (judul, deskripsi, level_kategori, file_path, konten_materi, tampilkan_kuis, id_guru, pakai_kuis, pakai_praktek) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("sssssiiii", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $tampilkan_kuis, $final_guru_id, $pakai_kuis, $pakai_praktek);
    }
    if ($stmt->execute()) { header("Location: materi_list.php"); exit(); }
    
}


// Ambil data untuk form
$materi_id = $_GET['id'] ?? null;
$materi = ['judul'=>'','deskripsi'=>'','level_kategori'=>'','file_path'=>'','konten_materi'=>'','pakai_kuis'=>0,'pakai_praktek'=>0];
if ($materi_id) {
    $stmt = $db_mapel->prepare("SELECT * FROM materi WHERE id = ?");
    $stmt->bind_param("i", $materi_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if($res) $materi = $res;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Materi Seni</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #fffaf5; font-family: 'Poppins', sans-serif; }
        .navbar-art { background: #2d3436; color: white; padding: 15px 0; margin-bottom: 30px; }
        .card-art { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .tipe-materi-box { display: none; }
        .tipe-materi-box.active { display: block; }
        .ai-zone { background: #fff3e0; border: 2px dashed #ff9800; border-radius: 20px; padding: 20px; }
        #panel_kanan { display: none; }
        #panel_kanan.show-ai { display: block; }
        .preview-window { width: 100%; height: 300px; border-radius: 15px; background: white; border: 1px solid #ddd; margin-top: 15px; }
    </style>
</head>
<body>

<nav class="navbar-art shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold"><i class="fas fa-paint-brush me-2 text-danger"></i> STUDIO MATERI SENI</h5>
        <a href="materi_list.php" class="btn btn-outline-warning btn-sm rounded-pill px-4">Kembali</a>
    </div>
</nav>

<div class="container mb-5">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_guru_target" value="<?= (isset($materi['id_guru']) && $materi['id_guru'] != 0) ? $materi['id_guru'] : $id_guru_tujuan ?>">
        <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
        
        
        <div class="row g-4">
            <div id="panel_kiri" class="col-lg-12">
                <div class="card card-art p-4 shadow-sm">
                    <h5 class="text-danger mb-4 fw-bold"><i class="fas fa-feather-alt"></i> Detail Karya Materi</h5>
                    
                    <div class="mb-3">
                        <label class="fw-bold small">Judul Materi Seni</label>
                        <input type="text" name="judul" id="judul_form" class="form-control" value="<?= htmlspecialchars($materi['judul']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Deskripsi (Tujuan Pembelajaran)</label>
                        <textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-primary">Jenis Konten</label>
                        <select name="tipe_materi" id="select_tipe" class="form-select fw-bold" onchange="toggleTipe()">
                            <option value="teori" <?= (strpos($materi['konten_materi'] ?? '', 'http') === false && strpos($materi['konten_materi'] ?? '', '.jpg') === false) ? 'selected' : '' ?>>📖 Teori & Slide AI</option>
                            <option value="gambar" <?= (preg_match('/\.(jpg|jpeg|png|webp)$/i', $materi['konten_materi'] ?? '')) ? 'selected' : '' ?>>🎨 Gambar / Lukisan</option>
                            <option value="video" <?= (strpos($materi['konten_materi'] ?? '', 'youtube') !== false) ? 'selected' : '' ?>>🎥 Video Tutorial</option>
                        </select>
                    </div>

                    <div id="tipe_teori" class="tipe-materi-box">
                        <label class="fw-bold small text-danger">Kanvas Kode HTML</label>
                        <textarea name="konten_materi" id="kode_html" class="form-control" rows="8" style="font-family:monospace; background:#2d3436; color:#fab1a0;"><?= ($materi['konten_materi'] && strpos($materi['konten_materi'], 'http') === false) ? htmlspecialchars($materi['konten_materi']) : '' ?></textarea>
                    </div>

                    <div id="tipe_gambar" class="tipe-materi-box">
                        <label class="fw-bold small">Upload Gambar Seni</label>
                        <input type="file" name="file_gambar" class="form-control">
                        <?php if(strpos($materi['konten_materi'], '.jpg')): ?>
                            <small class="text-muted">File saat ini: <?= $materi['konten_materi'] ?></small>
                        <?php endif; ?>
                    </div>

                    <div id="tipe_video" class="tipe-materi-box">
                        <label class="fw-bold small">Link YouTube</label>
                        <input type="text" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." value="<?= (strpos($materi['konten_materi'], 'youtube') !== false) ? htmlspecialchars($materi['konten_materi']) : '' ?>">
                        <small class="text-muted">Tempel link video YouTube biasa di sini.</small>
                    </div>

                    <div class="bg-light p-3 rounded-4 mt-4 mb-4">
                        <h6 class="fw-bold small mb-3"><i class="fas fa-tasks"></i> Konfigurasi Aktivitas Siswa</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pakai_kuis" <?= $materi['pakai_kuis'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold small">Aktifkan Kuis</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pakai_praktek" <?= $materi['pakai_praktek'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold small">Aktifkan Praktek</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6"><label class="fw-bold small">Tingkatan Kelas</label><input type="text" name="level_kategori" class="form-control" value="<?= htmlspecialchars($materi['level_kategori']) ?>"></div>
                        <div class="col-6"><label class="fw-bold small">Path File (.html)</label><input type="text" name="file_path" class="form-control" value="<?= htmlspecialchars($materi['file_path']) ?>"></div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 py-3 mt-4 fw-bold rounded-pill shadow-sm">SIMPAN MATERI</button>
                </div>
            </div>

            <div id="panel_kanan" class="col-lg-5">
                <div class="ai-zone shadow-sm">
                    <h6 class="text-warning fw-bold mb-3"><i class="fas fa-magic"></i> Rakit Materi dengan AI</h6>
                    <textarea id="ai_mentah" class="form-control mb-3" rows="6" placeholder="Tempel bahan materi seni di sini..."></textarea>
                    <button type="button" id="btnAI" class="btn btn-warning w-100 fw-bold text-white rounded-pill">MULAI MERAKIT SLIDE</button>
                    <iframe id="preview_ai" class="preview-window"></iframe>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleTipe() {
    const tipe = document.getElementById('select_tipe').value;
    const kiri = document.getElementById('panel_kiri');
    const kanan = document.getElementById('panel_kanan');

    document.querySelectorAll('.tipe-materi-box').forEach(b => b.classList.remove('active'));
    document.getElementById('tipe_' + tipe).classList.add('active');

    if (tipe === 'teori') {
        kanan.classList.add('show-ai');
        kiri.classList.replace('col-lg-12', 'col-lg-7');
    } else {
        kanan.classList.remove('show-ai');
        kiri.classList.replace('col-lg-7', 'col-lg-12');
    }
}
window.onload = toggleTipe;

document.getElementById('btnAI').onclick = function() {
    const judul = document.getElementById('judul_form').value;
    const isi = document.getElementById('ai_mentah').value;
    
    if(!isi) { alert("Isi bahan materi dulu!"); return; }

    this.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Merakit...";
    this.disabled = true;

    const fd = new FormData();
    fd.append('minta_ai', 1); 
    fd.append('judul_ai', judul); 
    fd.append('isi_mentah', isi);

    fetch('materi_form.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(html => {
        this.innerHTML = "MULAI MERAKIT SLIDE";
        this.disabled = false;
        
        // Membersihkan kode dari markdown AI (seperti ```html ... ```)
        let cleanHTML = html.replace(/```html/g, "").replace(/```/g, "").trim();
        
        document.getElementById('kode_html').value = cleanHTML;
        
        const previewFrame = document.getElementById('preview_ai');
        const doc = previewFrame.contentWindow.document;
        doc.open();
        doc.write(cleanHTML);
        doc.close();
    })
    .catch(err => {
        console.error(err);
        this.innerHTML = "Gagal, Coba Lagi";
        this.disabled = false;
    });
};
</script>
</body>
</html>