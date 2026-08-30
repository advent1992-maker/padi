<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';
require_once '../../../config/ai_helper.php';

if (isset($_POST['minta_ai'])) {
    $judul      = $_POST['judul_ai'] ?? '';
    $isi_mentah = $_POST['isi_mentah'] ?? '';
    $file_template = '../../../template_ai.html';
    
    if (file_exists($file_template)) {
        $contoh_kode = file_get_contents($file_template);
    } else {
        $contoh_kode = "Gunakan struktur HTML slide interaktif yang standar.";
    }

    $prompt = "Tugasmu adalah merakit materi HTML slide interaktif.
               CONTOH GAYA DESAIN (Gunakan gaya seperti ini):
               $contoh_kode
               MATERI YANG HARUS DIRAKIT:
               Judul: $judul
               Isi: $isi_mentah
               KETENTUAN:
               1. Gunakan struktur dan gaya CSS yang mirip dengan contoh di atas.
               2. Pastikan navigasi slide berfungsi seperti pada $contoh_kode.
               3. Output hanya kode HTML UTUH (<!DOCTYPE html> sampai </html>).
               4. Jangan berikan penjelasan teks, hanya kode.
               5. sesuaikan tampilan agar dapat tampil di tampilan mobile dan juga IFP/tv seperti pada $contoh_kode";

    $hasil_ai = panggil_gemini($prompt);
    if (strpos($hasil_ai, 'CURL_ERROR') === false) {
        echo $hasil_ai; 
    } else {
        echo "ERROR_TIMEOUT";
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['minta_ai'])) {
    $post_id = $_POST['materi_id'] ?? 0;
    $judul = $_POST['judul'];
    $deskripsi = $_POST['deskripsi']; // Diambil dari input form deskripsi
    $file_path = $_POST['file_path']; 
    $konten_materi = $_POST['konten_materi']; 
    $level = $_POST['level_kategori'];
    $id_guru = $_SESSION['user_id'];

    if ($post_id > 0) {
        $stmt = $db_mapel->prepare("UPDATE materi SET judul=?, deskripsi=?, file_path=?, konten_materi=?, level_kategori=? WHERE id=? AND id_guru=?");
        $stmt->bind_param("ssssiii", $judul, $deskripsi, $file_path, $konten_materi, $level, $post_id, $id_guru);
    } else {
        $stmt = $db_mapel->prepare("INSERT INTO materi (judul, deskripsi, file_path, konten_materi, level_kategori, id_guru) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $judul, $deskripsi, $file_path, $konten_materi, $level, $id_guru);
    }
    $stmt->execute();
    header("Location: materi_list.php");
    exit();
}

$materi_id = $_GET['id'] ?? 0;
$materi = ['judul' => '', 'deskripsi' => '', 'file_path' => '', 'konten_materi' => '', 'level_kategori' => ''];
if ($materi_id > 0) {
    $stmt = $db_mapel->prepare("SELECT * FROM materi WHERE id = ?");
    $stmt->bind_param("i", $materi_id);
    $stmt->execute();
    $materi = $stmt->get_result()->fetch_assoc() ?: $materi;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penyusun Materi PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f7f6; font-family: 'Poppins', sans-serif; }
        .ai-zone { background: #e8f5e9; border: 2px dashed #2e7d32; border-radius: 15px; padding: 20px; }
        .preview-window { 
            width: 100%; 
            height: 650px; 
            border: 2px solid #ddd; 
            border-radius: 15px; 
            background: white; 
            transition: all 0.3s ease;
        }
        .preview-window:fullscreen {
            height: 100vh;
            width: 100vw;
            border: none;
            border-radius: 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-success mb-0">Penyusun Materi IPAS</h3>
                <p class="text-muted small">Buat materi interaktif dengan bantuan AI</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-home me-1"></i> Dashboard
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
            
            <div class="row g-4">
                <div class="col-md-7">
                    <div class="card p-4 shadow-sm border-0" style="border-radius:15px;">
                        <h5 class="fw-bold text-primary mb-3">Opsi 1 & 2: Input Manual</h5>
                        
                        <div class="mb-3">
                            <label class="fw-bold small">Judul Materi</label>
                            <input type="text" class="form-control" name="judul" id="judul_form" value="<?= htmlspecialchars($materi['judul']) ?>" placeholder="Contoh: Ekosistem Laut" required>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold small text-muted">Deskripsi Singkat</label>
                            <textarea class="form-control" name="deskripsi" rows="2" placeholder="Jelaskan sedikit tentang isi materi ini..."><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold small text-danger">File Path (.html)</label>
                            <input type="text" class="form-control" name="file_path" value="<?= htmlspecialchars($materi['file_path']) ?>" placeholder="ekosistem.html">
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold small text-primary">Paste Kode HTML / Hasil AI</label>
                            <textarea class="form-control" name="konten_materi" id="kode_html" rows="8" style="font-family: monospace;" oninput="updatePreview()"><?= htmlspecialchars($materi['konten_materi']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold small">Kelas</label>
                            <select class="form-select" name="level_kategori">
                                <?php for($i=1;$i<=6;$i++): ?>
                                    <option value="<?= $i ?>" <?= $materi['level_kategori']==$i?'selected':'' ?>>Kelas <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-3 rounded-pill shadow-sm">SIMPAN MATERI</button>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="ai-zone shadow-sm">
                        <h5 class="fw-bold text-success mb-3"><i class="fas fa-robot me-2"></i>Opsi 3: Asisten AI</h5>
                        <textarea class="form-control mb-3" id="ai_mentah" rows="14" placeholder="Tulis ringkasan materi di sini, AI akan merakitnya menjadi slide interaktif..."></textarea>
                        <button type="button" class="btn btn-success w-100 fw-bold py-3 rounded-pill shadow-sm" id="btnAI">RAKIT MATERI</button>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card p-4 border-0 shadow-sm" style="border-radius:15px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-warning mb-0">Pratinjau (Live Preview)</h5>
                            <button type="button" onclick="fullscreenPreview()" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                                <i class="fas fa-expand-alt me-1"></i> Perbesar Tampilan
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
            previewDoc.write(kode || '<body style="color:#ccc;text-align:center;padding-top:100px;font-family:sans-serif;">Pratinjau Kosong</body>');
            previewDoc.close();
        }
        
        function fullscreenPreview() {
            const iframe = document.getElementById('live_preview');
            if (iframe.requestFullscreen) {
                iframe.requestFullscreen();
            } else if (iframe.webkitRequestFullscreen) {
                iframe.webkitRequestFullscreen();
            } else if (iframe.msRequestFullscreen) {
                iframe.msRequestFullscreen();
            }
        }

        window.onload = updatePreview;

        document.getElementById('btnAI').onclick = function() {
            const judul = document.getElementById('judul_form').value;
            const mentah = document.getElementById('ai_mentah').value;
            if(!judul || !mentah) return Swal.fire('Lengkapi Data', 'Isi judul dan materi dulu Pak.', 'info');

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Merakit...';
            this.disabled = true;

            const fd = new FormData();
            fd.append('minta_ai', 1);
            fd.append('judul_ai', judul);
            fd.append('isi_mentah', mentah);

            fetch('materi_form.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(d => {
                this.innerHTML = 'RAKIT MATERI';
                this.disabled = false;
                if(d === "ERROR_TIMEOUT") {
                    Swal.fire('Error', 'Koneksi AI terputus. Coba lagi.', 'error');
                } else {
                    document.getElementById('kode_html').value = d;
                    updatePreview();
                    Swal.fire('Berhasil!', 'Cek pratinjau di bawah, lalu klik SIMPAN.', 'success');
                }
            });
        };
    </script>
</body>
</html>