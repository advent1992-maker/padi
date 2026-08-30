<?php
// FILE: guru/kuis_form.php (BAHASA INDONESIA) - Updated with Pembahasan
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// Konfigurasi Path
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$parent_dir = dirname($current_dir); 
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $parent_dir . "/aset/";
$upload_dir = "../aset/"; 

$user_id = $_SESSION['user_id'] ?? 0;
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$id_materi = $_GET['id_materi'] ?? null;
$edit_id = $_GET['edit_id'] ?? null;

if (!$id_materi || !is_numeric($id_materi)) {
    header("Location: kuis_list.php");
    exit();
}

// 1. Ambil Judul Materi
$stmt_materi = $db_mapel->prepare("SELECT judul, level_kategori, id_guru FROM materi WHERE id = ?");
$stmt_materi->bind_param("i", $id_materi);
$stmt_materi->execute();
$materi = $stmt_materi->get_result()->fetch_assoc();
$stmt_materi->close();

if (!$materi) {
    header("Location: kuis_list.php");
    exit();
}

$is_owner_or_admin = ($_SESSION['role'] === 'admin' || $user_id == $materi['id_guru']);

// =======================================================================
// FUNGSI UPLOAD GAMBAR
// =======================================================================
function uploadGambar($file_input_name, $old_filename = null) {
    global $upload_dir;
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == 4) {
        return $old_filename; 
    }
    $file = $_FILES[$file_input_name];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = "img_" . uniqid() . "." . $ext;
    $target = $upload_dir . $new_filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $new_filename;
    }
    return $old_filename;
}

// =======================================================================
// LOGIKA EDIT MODE
// =======================================================================
$soal_yang_diedit = null;
if ($edit_id && is_numeric($edit_id) && $is_owner_or_admin) {
    $stmt_edit = $db_mapel->prepare("SELECT * FROM soal WHERE id = ? AND materi_id = ?");
    $stmt_edit->bind_param("ii", $edit_id, $id_materi);
    $stmt_edit->execute();
    $soal_yang_diedit = $stmt_edit->get_result()->fetch_assoc();
    $stmt_edit->close();
}

// =======================================================================
// PROSES SIMPAN / UPDATE
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_owner_or_admin) {
    $pertanyaan = $_POST['pertanyaan'];
    $jawaban_benar = $_POST['jawaban_benar'];
    $pembahasan = $_POST['pembahasan']; // Kolom baru
    $opsi_a = $_POST['opsi_a']; $opsi_b = $_POST['opsi_b'];
    $opsi_c = $_POST['opsi_c']; $opsi_d = $_POST['opsi_d'];

    $img_soal = uploadGambar('img_soal', $_POST['old_img_soal'] ?? null);
    $img_a = uploadGambar('img_a', $_POST['old_img_a'] ?? null);
    $img_b = uploadGambar('img_b', $_POST['old_img_b'] ?? null);
    $img_c = uploadGambar('img_c', $_POST['old_img_c'] ?? null);
    $img_d = uploadGambar('img_d', $_POST['old_img_d'] ?? null);

    if (isset($_POST['tambah_soal'])) {
        // SQL INSERT (13 Kolom)
        $sql = "INSERT INTO soal (materi_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar, pembahasan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("issssssssssss", $id_materi, $pertanyaan, $img_soal, $opsi_a, $img_a, $opsi_b, $img_b, $opsi_c, $img_c, $opsi_d, $img_d, $jawaban_benar, $pembahasan);
        $stmt->execute();
        $_SESSION['pesan_sukses'] = "Soal berhasil ditambahkan!";
    } elseif (isset($_POST['update_soal'])) {
        // SQL UPDATE (12 Kolom + 2 WHERE)
        $sql = "UPDATE soal SET pertanyaan=?, gambar_url=?, opsi_a=?, opsi_a_gambar_url=?, opsi_b=?, opsi_b_gambar_url=?, opsi_c=?, opsi_c_gambar_url=?, opsi_d=?, opsi_d_gambar_url=?, jawaban_benar=?, pembahasan=? WHERE id=? AND materi_id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("ssssssssssssii", $pertanyaan, $img_soal, $opsi_a, $img_a, $opsi_b, $img_b, $opsi_c, $img_c, $opsi_d, $img_d, $jawaban_benar, $pembahasan, $edit_id, $id_materi);
        $stmt->execute();
        $_SESSION['pesan_sukses'] = "Soal berhasil diupdate!";
    }
    header("Location: kuis_form.php?id_materi=" . $id_materi);
    exit();
}

// 4. Ambil Daftar Soal
$soal_list = [];
$stmt_soal = $db_mapel->prepare("SELECT * FROM soal WHERE materi_id = ? ORDER BY id ASC");
$stmt_soal->bind_param("i", $id_materi);
$stmt_soal->execute();
$result_soal = $stmt_soal->get_result();
while ($row = $result_soal->fetch_assoc()) { $soal_list[] = $row; }
$stmt_soal->close();

$pesan_sukses = $_SESSION['pesan_sukses'] ?? null;
unset($_SESSION['pesan_sukses']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal B. Indonesia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <style>
        body { background-color: #f0f8ff; font-family: 'Inter', sans-serif; }
        .opsi-container { border: 1px solid #dee2e6; padding: 15px; border-radius: 10px; background: white; margin-bottom: 10px; }
        .pembahasan-box { border: 2px dashed #198754; background: #f0fff4; padding: 15px; border-radius: 10px; }
        .img-preview { max-height: 100px; margin-top: 10px; border-radius: 5px; border: 1px solid #ddd; }
        .kunci-jawaban-badge { background-color: #0d6efd; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-success p-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-book me-2"></i> B. INDONESIA | TEACHER</a>
        <span class="text-white">Halo, <?= htmlspecialchars($nama_pengguna) ?></span>
    </div>
</nav>

<div class="container mt-4 mb-5 pt-4">
    <header class="mb-4">
        <h1 class="text-success"><i class="fas fa-edit"></i> Kelola Soal Kuis</h1>
        <p class="lead text-muted">Materi: <strong><?= htmlspecialchars($materi['judul']) ?></strong></p>
    </header>

    <a href="kuis_list.php" class="btn btn-outline-secondary mb-4 rounded-pill"><i class="fas fa-arrow-left"></i> Kembali</a>

    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $pesan_sukses ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow mb-5 rounded-4 border-0">
        <div class="card-header bg-success text-white fw-bold py-3">
            <i class="fas <?= $soal_yang_diedit ? 'fa-edit' : 'fa-plus-circle' ?>"></i> <?= $soal_yang_diedit ? "Edit Soal ID: ".$edit_id : "Tambah Soal Baru" ?>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="old_img_soal" value="<?= $soal_yang_diedit['gambar_url'] ?? '' ?>">
                <input type="hidden" name="old_img_a" value="<?= $soal_yang_diedit['opsi_a_gambar_url'] ?? '' ?>">
                <input type="hidden" name="old_img_b" value="<?= $soal_yang_diedit['opsi_b_gambar_url'] ?? '' ?>">
                <input type="hidden" name="old_img_c" value="<?= $soal_yang_diedit['opsi_c_gambar_url'] ?? '' ?>">
                <input type="hidden" name="old_img_d" value="<?= $soal_yang_diedit['opsi_d_gambar_url'] ?? '' ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold text-success">1. Pertanyaan Soal</label>
                    <textarea class="form-control" name="pertanyaan" rows="4" required placeholder="Contoh: Kalimat manakah yang termasuk kalimat efektif?"><?= $soal_yang_diedit['pertanyaan'] ?? '' ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">Upload Gambar Pertanyaan (Opsional)</label>
                    <input type="file" class="form-control" name="img_soal" accept="image/*">
                    <?php if (!empty($soal_yang_diedit['gambar_url'])): ?>
                        <img src="<?= $base_url . $soal_yang_diedit['gambar_url'] ?>" class="img-preview d-block border">
                    <?php endif; ?>
                </div>

                <h5 class="mt-4 mb-3 fw-bold text-primary">2. Opsi Jawaban (A-D)</h5>
                <div class="row">
                    <?php foreach (['a', 'b', 'c', 'd'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-container">
                            <label class="fw-bold">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" class="form-control mb-2" name="opsi_<?= $k ?>" value="<?= $soal_yang_diedit['opsi_'.$k] ?? '' ?>" placeholder="Teks Opsi">
                            <input type="file" class="form-control" name="img_<?= $k ?>" accept="image/*">
                            <?php if (!empty($soal_yang_diedit['opsi_'.$k.'_gambar_url'])): ?>
                                <img src="<?= $base_url . $soal_yang_diedit['opsi_'.$k.'_gambar_url'] ?>" class="img-preview d-block border">
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4 mt-3">
                    <label class="form-label fw-bold text-danger">3. Kunci Jawaban</label>
                    <select class="form-select" name="jawaban_benar" required>
                        <option value="">-- Pilih Jawaban --</option>
                        <?php foreach (['A','B','C','D'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= (isset($soal_yang_diedit['jawaban_benar']) && $soal_yang_diedit['jawaban_benar'] == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 pembahasan-box">
                    <label class="form-label fw-bold text-success"><i class="fas fa-lightbulb"></i> Pembahasan / Penjelasan Jawaban</label>
                    <textarea class="form-control" name="pembahasan" rows="4" placeholder="Jelaskan alasan jawaban tersebut benar..."><?= $soal_yang_diedit['pembahasan'] ?? '' ?></textarea>
                </div>

                <button type="submit" name="<?= $soal_yang_diedit ? 'update_soal' : 'tambah_soal' ?>" class="btn <?= $soal_yang_diedit ? 'btn-warning text-dark' : 'btn-success' ?> w-100 fw-bold py-2 shadow-lg mt-3">
                    <i class="fas fa-save"></i> <?= $soal_yang_diedit ? 'Simpan Perubahan' : 'Simpan Soal Baru' ?>
                </button>
                <?php if ($soal_yang_diedit): ?>
                    <a href="kuis_form.php?id_materi=<?= $id_materi ?>" class="btn btn-outline-secondary w-100 mt-2">Batalkan Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="text-primary mb-3"><i class="fas fa-list-alt"></i> Daftar Soal</h2>
    <?php if (empty($soal_list)): ?>
        <div class="alert alert-info text-center">Belum ada soal kuis.</div>
    <?php else: ?>
        <?php foreach ($soal_list as $no => $s): ?>
            <div class="card shadow-sm mb-3 border-start border-success border-5 rounded-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="flex-grow-1">
                            <p class="fw-bold mb-2"><?= ($no+1) . ". " . $s['pertanyaan'] ?></p>
                            <?php if ($s['gambar_url']): ?>
                                <img src="<?= $base_url . $s['gambar_url'] ?>" style="max-height: 150px;" class="mb-3 d-block border rounded shadow-sm">
                            <?php endif; ?>
                            <div class="row g-2">
                                <?php foreach (['a','b','c','d'] as $k): ?>
                                    <div class="col-6 small">
                                        <span class="<?= ($s['jawaban_benar'] == strtoupper($k)) ? 'text-success fw-bold' : '' ?>">
                                            <?= strtoupper($k) ?>. <?= htmlspecialchars($s['opsi_'.$k]) ?>
                                        </span>
                                        <?php if ($s['opsi_'.$k.'_gambar_url']): ?>
                                            <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 40px;" class="ms-1 border rounded">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($s['pembahasan'])): ?>
                                <div class="mt-3 p-2 bg-light rounded border-start border-info border-3 small">
                                    <strong class="text-info"><i class="fas fa-info-circle"></i> Pembahasan:</strong><br>
                                    <?= nl2br(htmlspecialchars($s['pembahasan'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ms-3 text-end">
                            <span class="badge bg-success mb-2">Kunci: <?= $s['jawaban_benar'] ?></span>
                            <?php if ($is_owner_or_admin): ?>
                                <div class="mt-4">
                                    <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-info text-white rounded-pill mb-1"><i class="fas fa-pen"></i></a>
                                    <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" 
                                       class="btn btn-sm btn-danger rounded-pill mb-1" 
                                       onclick="return confirm('Hapus soal ini?');">
                                         <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>