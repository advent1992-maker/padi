<?php
// FILE: guru/kuis_form.php (MATEMATIKA) - Logika Manual + Bantuan Upload + Pembahasan
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// --- LOGIKA PATH ASLI BAPAK ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$parent_dir = dirname($current_dir); 
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $parent_dir . "/aset/";
$upload_dir = "../aset/"; 

$user_id = $_SESSION['user_id'] ?? 0;
$id_materi = $_GET['id_materi'] ?? null;
$edit_id = $_GET['edit_id'] ?? null;

// 1. Ambil Data Materi
$stmt_materi = $db_mapel->prepare("SELECT judul, id_guru FROM materi WHERE id = ?");
$stmt_materi->bind_param("i", $id_materi);
$stmt_materi->execute();
$materi = $stmt_materi->get_result()->fetch_assoc();
$stmt_materi->close();

$is_owner_or_admin = ($_SESSION['role'] === 'admin' || $user_id == $materi['id_guru']);

// --- FUNGSI BANTUAN UPLOAD ---
function handleUpload($file_input, $current_value) {
    global $upload_dir;
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $filename = time() . "_" . basename($_FILES[$file_input]['name']);
        if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $upload_dir . $filename)) {
            return $filename; 
        }
    }
    return $current_value; 
}

// 2. PROSES SIMPAN / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_owner_or_admin) {
    $pertanyaan = $_POST['pertanyaan'];
    $jawaban_benar = $_POST['jawaban_benar'];
    $pembahasan = $_POST['pembahasan']; // Ambil Pembahasan
    
    // Ambil nilai dari input text
    $gambar_url = $_POST['gambar_url'];
    $opsi_a_img = $_POST['opsi_a_gambar_url'];
    $opsi_b_img = $_POST['opsi_b_gambar_url'];
    $opsi_c_img = $_POST['opsi_c_gambar_url'];
    $opsi_d_img = $_POST['opsi_d_gambar_url'];

    // Timpa jika ada upload file baru
    $gambar_url = handleUpload('file_pertanyaan', $gambar_url);
    $opsi_a_img = handleUpload('file_a', $opsi_a_img);
    $opsi_b_img = handleUpload('file_b', $opsi_b_img);
    $opsi_c_img = handleUpload('file_c', $opsi_c_img);
    $opsi_d_img = handleUpload('file_d', $opsi_d_img);

    if (isset($_POST['update_soal'])) {
        // SQL UPDATE (12 Kolom + 1 ID)
        $sql = "UPDATE soal SET pertanyaan=?, gambar_url=?, opsi_a=?, opsi_a_gambar_url=?, opsi_b=?, opsi_b_gambar_url=?, opsi_c=?, opsi_c_gambar_url=?, opsi_d=?, opsi_d_gambar_url=?, jawaban_benar=?, pembahasan=? WHERE id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("ssssssssssssi", $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan, $edit_id);
        $stmt->execute();
    } else {
        // SQL INSERT (13 Kolom)
        $sql = "INSERT INTO soal (materi_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar, pembahasan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("issssssssssss", $id_materi, $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan);
        $stmt->execute();
    }
    header("Location: kuis_form.php?id_materi=$id_materi&status=success"); exit();
}

// 3. Ambil data edit jika ada
$edit_data = null;
if ($edit_id) {
    $res = $db_mapel->query("SELECT * FROM soal WHERE id = $edit_id");
    $edit_data = $res->fetch_assoc();
}

$soal_list = $db_mapel->query("SELECT * FROM soal WHERE materi_id = $id_materi ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal Matematika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
    window.MathJax = {
      tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$']] },
      svg: { fontCache: 'global' }
    };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <style>
        .opsi-box { border: 1px solid #ddd; padding: 15px; border-radius: 10px; background: #fff; margin-bottom: 10px; }
        .pembahasan-box { border: 2px solid #fd7e14; background: #fffcf9; padding: 15px; border-radius: 10px; }
        .img-preview { max-height: 120px; border: 1px solid #ccc; margin-top: 5px; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark p-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-square-root-variable me-2"></i> MATEMATIKA | DASHBOARD GURU</a>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h3><i class="fas fa-calculator text-primary"></i> Kelola Soal: <?= htmlspecialchars($materi['judul']) ?></h3>
    <a href="kuis_list.php" class="btn btn-outline-secondary mb-4 btn-sm rounded-pill"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow mb-5 rounded-4 border-0">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold">Pertanyaan (Gunakan $...$ untuk rumus LaTeX)</label>
                    <textarea name="pertanyaan" class="form-control" rows="3" required placeholder="Contoh: Berapa hasil dari $\frac{1}{2} + 0.5$?"><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-4 p-3 bg-light border rounded-3">
                    <label class="fw-bold text-primary">Gambar Soal (Opsional)</label>
                    <input type="text" name="gambar_url" class="form-control mb-2" placeholder="Nama file (contoh: grafik1.png)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <h5 class="mb-3 fw-bold">Opsi Jawaban</h5>
                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box">
                            <label class="fw-bold text-primary">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>" placeholder="Teks atau Rumus">
                            <input type="text" name="opsi_<?= $k ?>_gambar_url" class="form-control mb-2 form-control-sm" placeholder="Nama file gambar" value="<?= $edit_data['opsi_'.$k.'_gambar_url'] ?? '' ?>">
                            <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4">
                    <label class="fw-bold text-danger">Kunci Jawaban</label>
                    <select name="jawaban_benar" class="form-select" required>
                        <option value="">-- Pilih Kunci --</option>
                        <?php foreach(['A','B','C','D'] as $o): ?>
                            <option value="<?= $o ?>" <?= (isset($edit_data['jawaban_benar']) && $edit_data['jawaban_benar'] == $o) ? 'selected' : '' ?>><?= $o ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4 pembahasan-box">
                    <label class="fw-bold text-orange" style="color: #fd7e14;"><i class="fas fa-brain"></i> Langkah-Langkah Pembahasan</label>
                    <textarea name="pembahasan" class="form-control" rows="4" placeholder="Tuliskan cara pengerjaannya di sini..."><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-primary w-100 fw-bold py-2 shadow">
                    <i class="fas fa-save me-2"></i> <?= $edit_id ? 'UPDATE SOAL MATEMATIKA' : 'SIMPAN SOAL BARU' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-3">Daftar Soal (Total: <?= $soal_list->num_rows ?>)</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-3 shadow-sm border-0 border-start border-primary border-4 rounded-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-2"><?= $s['pertanyaan'] ?></div>
                        <?php if($s['gambar_url']): ?>
                            <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-3 d-block img-preview rounded shadow-sm">
                        <?php endif; ?>
                        
                        <div class="row g-2 small">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                                <div class="col-6">
                                    <span class="text-muted fw-bold"><?= strtoupper($k) ?>:</span> <?= $s['opsi_'.$k] ?>
                                    <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                        <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 35px;" class="ms-1 border rounded">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if(!empty($s['pembahasan'])): ?>
                            <div class="mt-3 p-3 bg-light rounded border-start border-warning border-3 small">
                                <strong class="text-warning"><i class="fas fa-lightbulb"></i> Pembahasan:</strong><br>
                                <?= nl2br($s['pembahasan']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ms-3 text-end">
                        <span class="badge bg-primary px-3 py-2 mb-2 shadow-sm">Kunci: <?= $s['jawaban_benar'] ?></span>
                        <div class="btn-group d-block">
                            <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit text-white"></i></a>
                            <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus soal matematika ini?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<footer class="text-center text-muted p-4">
    <small>&copy; <?= date("Y"); ?> Mathfiction Portal - Math Specialist</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>