<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role
require_once '../../../config/ai_helper.php'; // Pastikan path helper AI benar

// Hanya Role 'guru' atau 'admin' yang bisa mengakses
if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';

// =======================================================================
// AJAX HANDLER: Minta Rakitan Materi ke AI
// =======================================================================
if (isset($_POST['minta_ai'])) {
    $judul      = $_POST['judul_ai'] ?? '';
    $isi_mentah = $_POST['isi_mentah'] ?? '';
    $file_template = '../../../template_ai.html';
    
    if (file_exists($file_template)) {
        $contoh_kode = file_get_contents($file_template);
    } else {
        $contoh_kode = "Gunakan struktur HTML slide interaktif standar.";
    }

    $prompt = "Tugasmu adalah merakit materi pelajaran menjadi HTML slide interaktif yang menarik.
               Gunakan gaya desain, struktur navigasi, dan animasi dari contoh kode ini:
               $contoh_kode
               
               Data Materi:
               Judul: $judul
               Konten mentah: $isi_mentah

               Ketentuan:
               1. Output hanya kode HTML UTUH (<!DOCTYPE html> sampai </html>).
               2. Jangan memberikan penjelasan teks apapun di luar kode.
               3. Pastikan navigasi slide (Next/Prev) berfungsi.
               4. Sesuaikan desain agar responsif untuk layar HP maupun TV/IFP.";

    $hasil_ai = panggil_gemini($prompt);
    echo (strpos($hasil_ai, 'CURL_ERROR') === false) ? $hasil_ai : "ERROR_TIMEOUT";
    exit();
}

$materi_id = $_GET['id'] ?? null;
$judul_page = "Tambah Materi Baru";
$materi = ['judul' => '', 'deskripsi' => '', 'level_kategori' => '', 'file_path' => '', 'konten_materi' => ''];
$pesan = "";

// =======================================================================
// 1. Handle POST Request (SIMPAN/UPDATE Materi)
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['minta_ai'])) {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $level_kategori = $_POST['level_kategori'] ?? '';
    $file_path = trim($_POST['file_path'] ?? '');
    $konten_materi = $_POST['konten_materi'] ?? ''; // Ambil konten dari rakitan AI
    $post_id = $_POST['materi_id'] ?? null;

    if (empty($judul) || empty($deskripsi) || empty($level_kategori)) {
        $pesan = "<div class='alert alert-danger'>Judul, Deskripsi, dan Level wajib diisi.</div>";
    } else {
        if ($post_id) {
            $stmt = $db_mapel->prepare("UPDATE materi SET judul = ?, deskripsi = ?, level_kategori = ?, file_path = ?, konten_materi = ? WHERE id = ? AND id_guru = ?");
            $stmt->bind_param("ssissii", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $post_id, $user_id);
            $pesan_sukses = "Materi berhasil diperbarui!";
        } else {
            $stmt = $db_mapel->prepare("INSERT INTO materi (judul, deskripsi, level_kategori, file_path, konten_materi, id_guru) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissi", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $user_id);
            $pesan_sukses = "Materi berhasil ditambahkan!";
        }

        if ($stmt->execute()) {
            $_SESSION['pesan_sukses'] = $pesan_sukses;
            header("Location: materi_list.php");
            exit();
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan data: " . $db_mapel->error . "</div>";
        }
    }
}

// =======================================================================
// 2. Fetch Data (Untuk Mode Edit)
// =======================================================================
if ($materi_id) {
    $judul_page = "Edit Materi";
    $stmt = $db_mapel->prepare("SELECT id, judul, deskripsi, level_kategori, file_path, konten_materi FROM materi WHERE id = ? AND id_guru = ?");
    $stmt->bind_param("ii", $materi_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $materi = $result->fetch_assoc();
    }
}
$levels = range(1, 6);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $judul_page; ?> | MATHFICTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f8ff; font-family: 'Poppins', sans-serif; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15); }
        .ai-zone { background: #eef2ff; border: 2px dashed #6366f1; border-radius: 15px; padding: 20px; }
        .preview-window { width: 100%; height: 500px; border: 2px solid #ddd; border-radius: 15px; background: white; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <strong>MATHFICTION | <?php echo strtoupper($role); ?></strong>
            </a>
            <a href="materi_list.php" class="btn btn-outline-light btn-sm">Kembali ke Daftar</a>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <header class="mb-4">
            <h1 class="text-primary fw-bold"><i class="fas fa-edit"></i> <?php echo $judul_page; ?></h1>
        </header>

        <?php echo $pesan; ?>

        <form method="POST" id="formMateri">
            <input type="hidden" name="materi_id" value="<?php echo htmlspecialchars($materi_id); ?>">

            <div class="row g-4">
                <div class="col-md-7">
                    <div class="card card-custom p-4 h-100">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Judul Bab/Materi</label>
                            <input type="text" class="form-control" name="judul" id="judul_form" 
                                   value="<?php echo htmlspecialchars($materi['judul']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi Singkat</label>
                            <textarea class="form-control" name="deskripsi" rows="2" required><?php echo htmlspecialchars($materi['deskripsi']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Target Kelas</label>
                                <select class="form-select" name="level_kategori" required>
                                    <option value="">Pilih Kelas</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level; ?>" <?php echo ($materi['level_kategori'] == $level) ? 'selected' : ''; ?>>Kelas <?php echo $level; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-danger">File Path (Opsional)</label>
                                <input type="text" class="form-control" name="file_path" value="<?php echo htmlspecialchars($materi['file_path']); ?>" placeholder="nama_file.html">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-primary">Konten Materi HTML (Hasil Rakitan AI)</label>
                            <textarea class="form-control" name="konten_materi" id="kode_html" rows="12" 
                                      style="font-family: monospace; font-size: 0.85rem;" oninput="updatePreview()"><?php echo htmlspecialchars($materi['konten_materi']); ?></textarea>
                            <small class="text-muted">Edit kode secara manual jika diperlukan.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm w-100 mt-3">
                            <i class="fas fa-save"></i> SIMPAN MATERI
                        </button>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="ai-zone shadow-sm h-100">
                        <h5 class="fw-bold text-indigo"><i class="fas fa-robot"></i> Asisten Rakit AI</h5>
                        <p class="small text-muted">Tempelkan ringkasan materi Anda di bawah, AI akan mengubahnya menjadi slide interaktif.</p>
                        
                        <textarea class="form-control mb-3" id="ai_mentah" rows="18" 
                                  placeholder="Contoh: Bab 1 Penjumlahan. 1. Konsep dasar. 2. Contoh soal..."></textarea>
                        
                        <button type="button" class="btn btn-indigo w-100 fw-bold py-3 text-white shadow" 
                                style="background: #6366f1;" id="btnAI">
                            <i class="fas fa-magic"></i> RAKIT MATERI OTOMATIS
                        </button>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card card-custom p-4">
                        <h5 class="fw-bold text-warning mb-3"><i class="fas fa-eye"></i> Pratinjau Langsung (Live Preview)</h5>
                        <iframe id="live_preview" class="preview-window"></iframe>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Fungsi untuk mengupdate tampilan iframe preview
        function updatePreview() {
            const kode = document.getElementById('kode_html').value;
            const previewFrame = document.getElementById('live_preview');
            const previewDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
            previewDoc.open();
            previewDoc.write(kode || '<body style="color:#ccc;text-align:center;padding-top:100px;font-family:sans-serif;">Tampilan materi akan muncul di sini...</body>');
            previewDoc.close();
        }

        // Jalankan preview saat halaman dimuat (untuk mode edit)
        window.onload = updatePreview;

        // Handler tombol AI
        document.getElementById('btnAI').onclick = function() {
            const judul = document.getElementById('judul_form').value;
            const mentah = document.getElementById('ai_mentah').value;

            if(!judul || !mentah) {
                return Swal.fire('Data Kurang', 'Isi dulu Judul dan Materi Mentah agar AI bisa bekerja.', 'info');
            }

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang Merakit...';
            this.disabled = true;

            const fd = new FormData();
            fd.append('minta_ai', 1);
            fd.append('judul_ai', judul);
            fd.append('isi_mentah', mentah);

            fetch('materi_form.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(d => {
                this.innerHTML = '<i class="fas fa-magic"></i> RAKIT MATERI OTOMATIS';
                this.disabled = false;

                if(d === "ERROR_TIMEOUT") {
                    Swal.fire('Error', 'AI tidak merespon (Timeout). Coba ringkas materi Anda.', 'error');
                } else {
                    document.getElementById('kode_html').value = d;
                    updatePreview();
                    Swal.fire('Berhasil!', 'Materi telah dirakit. Cek pratinjau di bawah.', 'success');
                }
            });
        };
    </script>
</body>
</html>