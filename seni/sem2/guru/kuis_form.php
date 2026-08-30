<?php
// FILE: guru/kuis_form.php (SENI BUDAYA)
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// Konfigurasi Path Gambar
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$parent_dir = dirname($current_dir); 
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $parent_dir . "/aset/";
$upload_dir = "../aset/"; 

$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$id_materi = $_GET['id_materi'] ?? null;
$edit_id = $_GET['edit_id'] ?? null;

// 1. Ambil Data Materi Seni
$stmt_materi = $db_mapel->prepare("SELECT judul, id_guru FROM materi WHERE id = ?");
$stmt_materi->bind_param("i", $id_materi);
$stmt_materi->execute();
$materi = $stmt_materi->get_result()->fetch_assoc();
$stmt_materi->close();

$is_owner_or_admin = ($_SESSION['role'] === 'admin' || $user_id == $materi['id_guru']);

// Fungsi Unggah Media Seni
function handleUpload($file_input, $current_value) {
    global $upload_dir;
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $filename = "art_" . time() . "_" . basename($_FILES[$file_input]['name']);
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
    $pembahasan = $_POST['pembahasan'];
    
    $gambar_url = handleUpload('file_pertanyaan', $_POST['gambar_url']);
    $opsi_a_img = handleUpload('file_a', $_POST['opsi_a_gambar_url']);
    $opsi_b_img = handleUpload('file_b', $_POST['opsi_b_gambar_url']);
    $opsi_c_img = handleUpload('file_c', $_POST['opsi_c_gambar_url']);
    $opsi_d_img = handleUpload('file_d', $_POST['opsi_d_gambar_url']);

    if (isset($_POST['update_soal'])) {
        $sql = "UPDATE soal SET pertanyaan=?, gambar_url=?, opsi_a=?, opsi_a_gambar_url=?, opsi_b=?, opsi_b_gambar_url=?, opsi_c=?, opsi_c_gambar_url=?, opsi_d=?, opsi_d_gambar_url=?, jawaban_benar=?, pembahasan=? WHERE id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("ssssssssssssi", $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan, $edit_id);
        $stmt->execute();
    } else {
        $sql = "INSERT INTO soal (materi_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar, pembahasan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("issssssssssss", $id_materi, $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan);
        $stmt->execute();
    }
    header("Location: kuis_form.php?id_materi=$id_materi&msg=success"); 
    exit();
}

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
    <title> Seni | Kurasi Pertanyaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #fdfdfd; font-family: 'Poppins', sans-serif; color: #444; }
        .art-navbar { background: linear-gradient(45deg, #6c5ce7, #a29bfe); border-bottom: 5px solid #4834d4; }
        .opsi-box { border: 2px solid #f1f2f6; padding: 15px; border-radius: 15px; background: #fff; transition: 0.3s; margin-bottom: 15px; }
        .opsi-box:focus-within { border-color: #6c5ce7; box-shadow: 0 5px 15px rgba(108, 92, 231, 0.1); }
        .pembahasan-box { border: none; background: #e3f2fd; border-left: 5px solid #0097e6; padding: 20px; border-radius: 10px; }
        .img-preview { max-height: 150px; object-fit: contain; border-radius: 10px; border: 2px solid #eee; }
        .art-card { border: none; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .btn-save { background: #6c5ce7; color: white; border-radius: 50px; padding: 12px; }
        .btn-save:hover { background: #4834d4; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark art-navbar p-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-palette me-2"></i>  SENI</a>
        <span class="text-white small d-none d-md-block"> <b><?= htmlspecialchars($nama_pengguna) ?></b></span>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-file-signature text-primary"></i> Kelola Butir Soal</h2>
            <p class="text-muted">Topik: <span class="badge bg-light text-primary border border-primary"><?= htmlspecialchars($materi['judul']) ?></span></p>
        </div>
        <a href="kuis_list.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
    </div>

    <?php if ($is_owner_or_admin): ?>
    <div class="card art-card mb-5">
        <div class="card-body p-4">
            <h5 class="mb-4 fw-bold"><i class="fas fa-plus-circle me-2"></i><?= $edit_id ? 'Revisi Karya Pertanyaan' : 'Tambah Pertanyaan Baru' ?></h5>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold mb-2">Narasi Pertanyaan / Instruksi</label>
                    <textarea name="pertanyaan" class="form-control border-0 bg-light" rows="3" placeholder="Tuliskan pertanyaan seni di sini..." required><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-4 p-3 bg-light rounded-3 border-dashed border-2">
                    <label class="fw-bold text-primary small"><i class="fas fa-image me-1"></i> Gambar Referensi (Visual Art)</label>
                    <input type="text" name="gambar_url" class="form-control form-control-sm mb-2" placeholder="Nama file gambar (opsional)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <h6 class="mb-3 fw-bold text-secondary">Pilihan Jawaban (Apresiasi)</h6>
                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box">
                            <label class="fw-bold small text-muted">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>" placeholder="Teks jawaban...">
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" name="opsi_<?= $k ?>_gambar_url" class="form-control form-control-sm" placeholder="File gambar opsi" value="<?= $edit_data['opsi_'.$k.'_gambar_url'] ?? '' ?>">
                                <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 mb-4">
                        <label class="fw-bold text-danger">Kunci Jawaban Benar</label>
                        <select name="jawaban_benar" class="form-select border-danger-subtle" required>
                            <option value="">-- Pilih Kunci --</option>
                            <?php foreach(['A','B','C','D'] as $o): ?>
                                <option value="<?= $o ?>" <?= (isset($edit_data['jawaban_benar']) && $edit_data['jawaban_benar'] == $o) ? 'selected' : '' ?>>Opsi <?= $o ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 mb-4">
                        <div class="pembahasan-box">
                            <label class="fw-bold text-info small"><i class="fas fa-lightbulb"></i> Analisis & Pembahasan (Feedback Siswa)</label>
                            <textarea name="pembahasan" class="form-control border-0 shadow-sm" rows="3" placeholder="Jelaskan alasan estetis di balik jawaban ini..."><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-save w-100 fw-bold shadow">
                    <i class="fas fa-save me-2"></i> <?= $edit_id ? 'PERBARUI PERTANYAAN' : 'SIMPAN KE DAFTAR SOAL' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-4 fw-bold"><i class="fas fa-layer-group me-2"></i> Galeri Soal Terdaftar (<?= $soal_list->num_rows ?>)</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-4 art-card border-start border-primary border-5">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-9 border-end">
                        <p class="fw-bold fs-5 mb-3"><?= $s['pertanyaan'] ?></p>
                        <?php if($s['gambar_url']): ?>
                            <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-3 d-block img-preview shadow-sm">
                        <?php endif; ?>
                        
                        <div class="row g-3">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                                <div class="col-6">
                                    <div class="p-2 rounded <?= ($s['jawaban_benar'] == strtoupper($k)) ? 'bg-success-subtle border border-success' : 'bg-light' ?>">
                                        <span class="fw-bold me-2"><?= strtoupper($k) ?>.</span> 
                                        <?= htmlspecialchars($s['opsi_'.$k]) ?>
                                        <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                            <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 40px;" class="ms-2 border rounded">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if(!empty($s['pembahasan'])): ?>
                            <div class="mt-4 small p-3 rounded bg-info-subtle text-dark border-start border-info border-3">
                                <strong><i class="fas fa-comment-alt me-1"></i> Catatan Kurator:</strong><br>
                                <?= nl2br(htmlspecialchars($s['pembahasan'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 text-center d-flex flex-column justify-content-center">
                        <div class="mb-3">
                            <small class="text-muted d-block">Kunci Jawaban</small>
                            <span class="badge bg-primary fs-4 px-4 py-2 rounded-circle shadow-sm"><?= $s['jawaban_benar'] ?></span>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-warning text-white btn-sm rounded-pill shadow-sm">
                                <i class="fas fa-edit me-1"></i> Ubah
                            </a>
                            <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" class="btn btn-outline-danger btn-sm rounded-pill" onclick="return confirm('Hapus pertanyaan ini dari koleksi?')">
                                <i class="fas fa-trash me-1"></i> Hapus
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

</body>
</html>