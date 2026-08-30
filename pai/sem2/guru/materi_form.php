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
$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';

// =======================================================================
// 1. Handle AJAX Request (Proses Rakit AI PAI)
// =======================================================================
if (isset($_POST['minta_ai'])) {
    $judul      = $_POST['judul_ai'] ?? '';
    $isi_mentah = $_POST['isi_mentah'] ?? '';
    $file_template = '../../../template_ai.html';
    
    $contoh_kode = file_exists($file_template) ? file_get_contents($file_template) : "Gunakan struktur HTML slide interaktif standar.";

    $prompt = "Tugasmu adalah merakit materi PENDIDIKAN AGAMA ISLAM menjadi HTML slide interaktif.
               Gunakan gaya desain dari contoh ini: $contoh_kode
               
               MATERI:
               Judul: $judul
               Isi: $isi_mentah

               KETENTUAN KHUSUS PAI:
               1. Sajikan teks dengan sopan dan tipografi yang jelas (mudah dibaca).
               2. Jika ada ayat atau dalil, pastikan formatnya rapi.
               3. Sertakan navigasi slide yang berfungsi.
               4. Output hanya kode HTML UTUH tanpa penjelasan teks.";

    $hasil_ai = panggil_gemini($prompt);
    echo (strpos($hasil_ai, 'CURL_ERROR') === false) ? $hasil_ai : "ERROR_TIMEOUT";
    exit();
}

// =======================================================================
// 2. Handle POST Request (SIMPAN/UPDATE Materi)
// =======================================================================
$pesan = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['minta_ai'])) {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $level_kategori = $_POST['level_kategori'] ?? '';
    $file_path = trim($_POST['file_path'] ?? '');
    $konten_materi = $_POST['konten_materi'] ?? '';
    $post_id = $_POST['materi_id'] ?? null;

    if (empty($judul) || empty($deskripsi) || empty($level_kategori)) {
        $pesan = "<div class='alert alert-danger'>Judul, Deskripsi, dan Level wajib diisi.</div>";
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
            $_SESSION['pesan_sukses'] = "Materi PAI berhasil disimpan!";
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyusun Materi PAI | ISLAMI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f1f8e9; font-family: 'Poppins', sans-serif; }
        .card-custom { border-radius: 15px; box-shadow: 0 6px 15px rgba(0,0,0,0.1); border: none; }
        .ai-zone { background: #e8f5e9; border: 2px dashed #2e7d32; border-radius: 15px; padding: 20px; }
        .preview-window { width: 100%; height: 550px; border: 2px solid #ddd; border-radius: 15px; background: white; }
        .btn-pai { background-color: #2e7d32; color: white; }
        .btn-pai:hover { background-color: #1b5e20; color: white; }
        .text-pai { color: #2e7d32 !important; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-mosque me-2"></i> PAI | GURU</a>
        <a href="materi_list.php" class="btn btn-outline-light btn-sm">Batal</a>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <?= $pesan; ?>
    <form method="POST">
        <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
        
        <div class="row g-4">
            <div class="col-md-7">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-pai mb-3"><i class="fas fa-book-open"></i> Detail Materi PAI</h5>
                    
                    <div class="mb-3">
                        <label class="fw-bold small text-muted">Judul Materi</label>
                        <input type="text" class="form-control" name="judul" id="judul_form" value="<?= htmlspecialchars($materi['judul']) ?>" placeholder="Contoh: Sejarah Khulafaur Rasyidin" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-muted">Deskripsi Singkat (Peta Belajar)</label>
                        <textarea class="form-control" name="deskripsi" rows="2" required><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-muted">Target Kelas</label>
                            <select class="form-select" name="level_kategori" required>
                                <?php foreach(range(1,6) as $l): ?>
                                    <option value="<?= $l ?>" <?= $materi['level_kategori'] == $l ? 'selected' : '' ?>>Kelas <?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-danger">File Path (.html)</label>
                            <input type="text" class="form-control" name="file_path" value="<?= htmlspecialchars($materi['file_path']) ?>" placeholder="materi_pai.html">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-pai">Isi Konten HTML (Hasil AI / Manual)</label>
                        <textarea class="form-control" name="konten_materi" id="kode_html" rows="10" style="font-family: monospace;" oninput="updatePreview()"><?= htmlspecialchars($materi['konten_materi']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-pai w-100 fw-bold py-3 rounded-pill shadow">SIMPAN MATERI PAI</button>
                </div>
            </div>

            <div class="col-md-5">
                <div class="ai-zone shadow-sm">
                    <h5 class="fw-bold text-pai mb-3"><i class="fas fa-magic"></i> Asisten AI Islami</h5>
                    <p class="small text-muted">Masukkan ringkasan materi atau poin-poin pelajaran di sini, biarkan AI menyusun slidenya.</p>
                    <textarea class="form-control mb-3" id="ai_mentah" rows="14" placeholder="Contoh: Jelaskan rukun iman secara singkat..."></textarea>
                    <button type="button" class="btn btn-pai w-100 fw-bold py-3 rounded-pill shadow-sm" id="btnAI">RAKIT SLIDE OTOMATIS</button>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-warning mb-3"><i class="fas fa-eye"></i> Pratinjau Materi</h5>
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
        previewDoc.write(kode || '<body style="color:#ccc;text-align:center;padding-top:100px;font-family:sans-serif;">Pratinjau Materi Akan Tampil Di Sini</body>');
        previewDoc.close();
    }
    window.onload = updatePreview;

    document.getElementById('btnAI').onclick = function() {
        const judul = document.getElementById('judul_form').value;
        const mentah = document.getElementById('ai_mentah').value;
        if(!judul || !mentah) return Swal.fire('Data Kurang', 'Judul dan isi materi belum diisi, Pak.', 'info');

        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang Merakit...';
        this.disabled = true;

        const fd = new FormData();
        fd.append('minta_ai', 1);
        fd.append('judul_ai', judul);
        fd.append('isi_mentah', mentah);

        fetch('materi_form.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(d => {
            this.innerHTML = 'RAKIT SLIDE OTOMATIS';
            this.disabled = false;
            if(d === "ERROR_TIMEOUT") {
                Swal.fire('Error', 'AI sedang sibuk, mohon coba lagi nanti.', 'error');
            } else {
                document.getElementById('kode_html').value = d;
                updatePreview();
                Swal.fire('Berhasil!', 'Materi PAI sudah dirakit oleh AI.', 'success');
            }
        });
    };
</script>
</body>
</html>