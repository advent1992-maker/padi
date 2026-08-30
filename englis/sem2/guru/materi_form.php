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
// 1. Handle AJAX Request (Proses Rakit AI English)
// =======================================================================
if (isset($_POST['minta_ai'])) {
    $judul      = $_POST['judul_ai'] ?? '';
    $isi_mentah = $_POST['isi_mentah'] ?? '';
    $file_template = '../../../template_ai.html';
    
    $contoh_kode = file_exists($file_template) ? file_get_contents($file_template) : "Use standard interactive slide structure.";

    $prompt = "You are a professional English Teacher. Your task is to assemble ENGLISH material into an interactive HTML slide.
               Use the design style from this example: $contoh_kode
               
               MATERIAL DATA:
               Title: $judul
               Content: $isi_mentah

               REQUIREMENTS:
               1. Use English for the instructions and slides if possible.
               2. Use CSS and structure similar to the example.
               3. Include working slide navigation.
               4. Output ONLY the complete HTML code without explanation.";

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
    $konten_materi = $_POST['konten_materi'] ?? '';
    $post_id = $_POST['materi_id'] ?? null;

    if ($post_id) {
        // UPDATE MATERI
        $stmt = $db_mapel->prepare("UPDATE materi SET judul=?, deskripsi=?, level_kategori=?, file_path=?, konten_materi=? WHERE id=? AND id_guru=?");
        $stmt->bind_param("ssissii", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $post_id, $user_id);
    } else {
        // INSERT MATERI BARU
        $stmt = $db_mapel->prepare("INSERT INTO materi (judul, deskripsi, level_kategori, file_path, konten_materi, id_guru) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissi", $judul, $deskripsi, $level_kategori, $file_path, $konten_materi, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['pesan_sukses'] = "English material saved successfully!";
        header("Location: materi_list.php");
        exit();
    } else {
        $pesan = "<div class='alert alert-danger'>Error: " . $db_mapel->error . "</div>";
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
    <title>English Material Composer | GLOBAL EDUCATION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f8faff; font-family: 'Poppins', sans-serif; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        /* Tema English: Ungu/Indigo */
        .ai-zone { background: #f3e5f5; border: 2px dashed #7b1fa2; border-radius: 15px; padding: 20px; }
        .preview-window { width: 100%; height: 500px; border: 2px solid #ddd; border-radius: 15px; background: white; transition: 0.3s; }
        .btn-indigo { background-color: #6a1b9a; color: white; }
        .btn-indigo:hover { background-color: #4a148c; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-language me-2"></i> ENGLISH TEACHER</a>
        <div class="d-flex align-items-center">
            <span class="text-white me-3 d-none d-md-block">Welcome, <b><?= htmlspecialchars($nama_pengguna) ?></b></span>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">Dashboard</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <?= $pesan; ?>
    <form method="POST">
        <input type="hidden" name="materi_id" value="<?= $materi_id ?>">
        
        <div class="row g-4">
            <div class="col-md-7">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-indigo mb-3"><i class="fas fa-edit"></i> Material Details</h5>
                    
                    <div class="mb-3">
                        <label class="fw-bold small">Topic Title</label>
                        <input type="text" class="form-control" name="judul" id="judul_form" value="<?= htmlspecialchars($materi['judul']) ?>" placeholder="e.g. Present Continuous Tense" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Short Description</label>
                        <textarea class="form-control" name="deskripsi" rows="2" placeholder="Brief explanation for students..." required><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small">Target Grade</label>
                            <select class="form-select" name="level_kategori" required>
                                <?php foreach(range(1,6) as $l): ?>
                                    <option value="<?= $l ?>" <?= $materi['level_kategori'] == $l ? 'selected' : '' ?>>Grade <?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-danger">File Path (.html)</label>
                            <input type="text" class="form-control" name="file_path" value="<?= htmlspecialchars($materi['file_path']) ?>" placeholder="lesson1.html">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-primary">HTML Content (AI Result / Manual)</label>
                        <textarea class="form-control" name="konten_materi" id="kode_html" rows="10" style="font-family: monospace;" oninput="updatePreview()"><?= htmlspecialchars($materi['konten_materi']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-indigo w-100 fw-bold py-3 rounded-pill shadow">SAVE ENGLISH MATERIAL</button>
                </div>
            </div>

            <div class="col-md-5">
                <div class="ai-zone shadow-sm">
                    <h5 class="fw-bold text-indigo mb-3"><i class="fas fa-robot"></i> Assemble with AI</h5>
                    <p class="small text-muted">Input your raw text or summary, and AI will build the interactive slides for you.</p>
                    <textarea class="form-control mb-3" id="ai_mentah" rows="14" placeholder="Paste your lesson material here..."></textarea>
                    <button type="button" class="btn btn-indigo w-100 fw-bold py-3 rounded-pill" id="btnAI">GENERATE SLIDES</button>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-warning mb-3"><i class="fas fa-eye"></i> Live Preview</h5>
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
        previewDoc.write(kode || '<body style="color:#ccc;text-align:center;padding-top:100px;font-family:sans-serif;">Preview is empty</body>');
        previewDoc.close();
    }
    window.onload = updatePreview;

    document.getElementById('btnAI').onclick = function() {
        const judul = document.getElementById('judul_form').value;
        const mentah = document.getElementById('ai_mentah').value;
        if(!judul || !mentah) return Swal.fire('Incomplete Data', 'Please fill the Title and Raw Material first.', 'info');

        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assembling...';
        this.disabled = true;

        const fd = new FormData();
        fd.append('minta_ai', 1);
        fd.append('judul_ai', judul);
        fd.append('isi_mentah', mentah);

        fetch('materi_form.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(d => {
            this.innerHTML = 'GENERATE SLIDES';
            this.disabled = false;
            if(d === "ERROR_TIMEOUT") {
                Swal.fire('Timeout', 'AI is taking too long. Try a shorter material.', 'error');
            } else {
                document.getElementById('kode_html').value = d;
                updatePreview();
                Swal.fire('Success!', 'English material assembled. Please check the preview and click Save.', 'success');
            }
        });
    };
</script>
</body>
</html>