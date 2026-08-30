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
// 1. Handle AJAX Request (Proses Rakit AI Mulok)
// =======================================================================
if (isset($_POST['minta_ai'])) {
    $judul      = $_POST['judul_ai'] ?? '';
    $isi_mentah = $_POST['isi_mentah'] ?? '';
    $file_template = '../../../template_ai.html';
    
    $contoh_kode = file_exists($file_template) ? file_get_contents($file_template) : "Gunakan struktur slide interaktif standar.";

    $prompt = "Anda adalah seorang Guru Muatan Lokal yang ahli dalam kebudayaan daerah. 
               Tugas Anda adalah merakit materi MULOK menjadi slide HTML interaktif yang menarik.
               Gunakan gaya desain dari contoh ini: $contoh_kode
               
               DATA MATERI:
               Judul: $judul
               Isi Materi: $isi_mentah

               KETENTUAN:
               1. Sajikan materi dengan bahasa yang mudah dipahami namun tetap melestarikan nilai budaya.
               2. Gunakan navigasi slide (Next/Prev) yang berfungsi baik.
               3. Output HANYA kode HTML lengkap (tanpa teks penjelasan tambahan).";

    $hasil_ai = panggil_gemini($prompt);
    echo (strpos($hasil_ai, 'CURL_ERROR') === false) ? $hasil_ai : "ERROR_TIMEOUT";
    exit();
}

// =======================================================================
// 2. Handle POST Request (SIMPAN/UPDATE ke Database)
// =======================================================================
$pesan = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['minta_ai'])) {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $level_kategori = $_POST['level_kategori'] ?? '';
    $file_path = trim($_POST['file_path'] ?? '');
    $konten_materi = $_POST['konten_materi'] ?? ''; // Konten hasil rakitan AI
    $post_id = $_POST['materi_id'] ?? null;

    if (empty($judul) || empty($deskripsi) || empty($level_kategori)) {
        $pesan = "<div class='alert alert-danger'>Judul, Deskripsi, dan Kelas wajib diisi.</div>";
    } else {
        if ($post_id) {
            // UPDATE
            $stmt = $db_mapel->prepare("UPDATE materi SET judul=?, deskripsi=?, level_kategori=?, file_path=?, konten_materi=? WHERE id=? AND id_guru=?");
            $stmt->bind_param("ssissii", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $post_id, $user_id);
        } else {
            // INSERT
            $stmt = $db_mapel->prepare("INSERT INTO materi (judul, deskripsi, level_kategori, file_path, konten_materi, id_guru) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissi", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['pesan_sukses'] = "Materi Mulok '{$judul}' berhasil disimpan!";
            header("Location: materi_list.php");
            exit();
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan: " . $db_mapel->error . "</div>";
        }
    }
}

// =======================================================================
// 3. Fetch Data (Untuk Mode Edit)
// =======================================================================
$materi_id = $_GET['id'] ?? null;
$materi = ['judul' => '', 'deskripsi' => '', 'level_kategori' => '', 'file_path' => '', 'konten_materi' => ''];
if ($materi_id) {
    $stmt = $db_mapel->prepare("SELECT * FROM materi WHERE id = ? AND id_guru = ?");
    $stmt->bind_param("ii", $materi_id, $user_id);
    $stmt->execute();
    $materi = $stmt->get_result()->fetch_assoc() ?: $materi;
}
$levels = range(1, 6);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penyusun Materi Mulok | KEARIFAN LOKAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #fdfaf6; font-family: 'Poppins', sans-serif; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        /* Warna Cokelat/Mulok */
        .ai-zone { background: #efebe9; border: 2px dashed #795548; border-radius: 15px; padding: 20px; }
        .preview-window { width: 100%; height: 500px; border: 2px solid #d7ccc8; border-radius: 15px; background: white; }
        .btn-mulok { background-color: #5d4037; color: white; }
        .btn-mulok:hover { background-color: #3e2723; color: white; }
        .text-mulok { color: #5d4037; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-landmark me-2"></i> MULOK | GURU</a>
        <a href="materi_list.php" class="btn btn-outline-light btn-sm rounded-pill">Batal</a>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <?= $pesan; ?>
    <form method="POST">
        <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
        
        <div class="row g-4">
            <div class="col-md-7">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-mulok mb-3"><i class="fas fa-pen-nib"></i> Detail Materi Lokal</h5>
                    
                    <div class="mb-3">
                        <label class="fw-bold small">Judul Bab/Materi</label>
                        <input type="text" class="form-control" name="judul" id="judul_form" value="<?= htmlspecialchars($materi['judul']) ?>" placeholder="Contoh: Kesenian Daerah Kita" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Deskripsi Singkat</label>
                        <textarea class="form-control" name="deskripsi" rows="2" placeholder="Jelaskan sedikit tentang materi ini..." required><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small">Target Kelas</label>
                            <select class="form-select" name="level_kategori" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach($levels as $l): ?>
                                    <option value="<?= $l ?>" <?= $materi['level_kategori'] == $l ? 'selected' : '' ?>>Kelas <?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-danger">File Path (Opsional)</label>
                            <input type="text" class="form-control" name="file_path" value="<?= htmlspecialchars($materi['file_path']) ?>" placeholder="mulok1.html">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-primary">Isi Kode HTML (AI/Manual)</label>
                        <textarea class="form-control" name="konten_materi" id="kode_html" rows="10" style="font-family: monospace; font-size: 0.8rem;" oninput="updatePreview()"><?= htmlspecialchars($materi['konten_materi']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-mulok w-100 fw-bold py-3 rounded-pill shadow">SIMPAN MATERI MULOK</button>
                </div>
            </div>

            <div class="col-md-5">
                <div class="ai-zone shadow-sm h-100">
                    <h5 class="fw-bold text-mulok mb-3"><i class="fas fa-magic"></i> Rakit Otomatis dengan AI</h5>
                    <p class="small text-muted">Tempelkan catatan materi Anda di bawah, dan biarkan AI menyusunnya menjadi slide menarik.</p>
                    <textarea class="form-control mb-3" id="ai_mentah" rows="15" placeholder="Tuliskan isi materi lokal di sini..."></textarea>
                    <button type="button" class="btn btn-mulok w-100 fw-bold py-3 rounded-pill" id="btnAI">BUAT SLIDE SEKARANG</button>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-warning mb-3"><i class="fas fa-eye"></i> Pratinjau Tampilan</h5>
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
        previewDoc.write(kode || '<body style="color:#999;text-align:center;padding-top:100px;font-family:sans-serif;">Tampilan akan muncul di sini...</body>');
        previewDoc.close();
    }
    window.onload = updatePreview;

    document.getElementById('btnAI').onclick = function() {
        const judul = document.getElementById('judul_form').value;
        const mentah = document.getElementById('ai_mentah').value;
        if(!judul || !mentah) return Swal.fire('Data Belum Lengkap', 'Isi Judul dan Materi Mentah terlebih dahulu ya Pak.', 'info');

        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang Merakit...';
        this.disabled = true;

        const fd = new FormData();
        fd.append('minta_ai', 1);
        fd.append('judul_ai', judul);
        fd.append('isi_mentah', mentah);

        fetch('materi_form.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(d => {
            this.innerHTML = 'BUAT SLIDE SEKARANG';
            this.disabled = false;
            if(d === "ERROR_TIMEOUT") {
                Swal.fire('Waktu Habis', 'AI sedang sibuk, silakan coba lagi.', 'error');
            } else {
                document.getElementById('kode_html').value = d;
                updatePreview();
                Swal.fire('Berhasil!', 'Materi Mulok telah dirakit. Jangan lupa klik Simpan.', 'success');
            }
        });
    };
</script>
</body>
</html>