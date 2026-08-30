<?php
// FILE: guru/kuis_form.php (PJOK) - Logika Manual + Bantuan Upload + Instruksi Teknik
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// --- LOGIKA PATH OTOMATIS ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$parent_dir = dirname($current_dir); 
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $parent_dir . "/aset/";
$upload_dir = "../aset/"; 

$role = $_SESSION['role'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];

$id_materi = $_GET['id_materi'] ?? null;
$edit_id = $_GET['edit_id'] ?? null;

if (!$id_materi || !is_numeric($id_materi)) {
    header("Location: kuis_list.php");
    exit();
}

// 1. Ambil Judul Materi
$stmt_materi = $db_mapel->prepare("SELECT judul, id_guru FROM materi WHERE id = ?");
$stmt_materi->bind_param("i", $id_materi);
$stmt_materi->execute();
$materi = $stmt_materi->get_result()->fetch_assoc();
$stmt_materi->close();

if (!$materi) {
    header("Location: kuis_list.php");
    exit();
}
$judul_materi = htmlspecialchars($materi['judul']);
$is_owner_or_admin = ($role === 'admin' || $user_id == $materi['id_guru']);

// --- FUNGSI BANTUAN UPLOAD ---
function handleUpload($file_input, $current_value) {
    global $upload_dir;
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $filename = "pjok_" . time() . "_" . basename($_FILES[$file_input]['name']);
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
    $pembahasan = $_POST['pembahasan']; // Kolom Instruksi Teknik
    
    $gambar_url = $_POST['gambar_url'];
    $opsi_a_img = $_POST['opsi_a_gambar_url'];
    $opsi_b_img = $_POST['opsi_b_gambar_url'];
    $opsi_c_img = $_POST['opsi_c_gambar_url'];
    $opsi_d_img = $_POST['opsi_d_gambar_url'];

    $gambar_url = handleUpload('file_pertanyaan', $gambar_url);
    $opsi_a_img = handleUpload('file_a', $opsi_a_img);
    $opsi_b_img = handleUpload('file_b', $opsi_b_img);
    $opsi_c_img = handleUpload('file_c', $opsi_c_img);
    $opsi_d_img = handleUpload('file_d', $opsi_d_img);

    if (isset($_POST['update_soal'])) {
        $sql = "UPDATE soal SET pertanyaan=?, gambar_url=?, opsi_a=?, opsi_a_gambar_url=?, opsi_b=?, opsi_b_gambar_url=?, opsi_c=?, opsi_c_gambar_url=?, opsi_d=?, opsi_d_gambar_url=?, jawaban_benar=?, pembahasan=? WHERE id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("ssssssssssssi", $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan, $edit_id);
        $stmt->execute();
        $_SESSION['pesan_sukses'] = "Soal PJOK berhasil diperbarui!";
    } else {
        $sql = "INSERT INTO soal (materi_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar, pembahasan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("issssssssssss", $id_materi, $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan);
        $stmt->execute();
        $_SESSION['pesan_sukses'] = "Soal PJOK berhasil ditambahkan!";
    }
    header("Location: kuis_form.php?id_materi=$id_materi"); exit();
}

// 3. LOGIKA EDIT MODE
$edit_data = null;
if ($edit_id) {
    $res = $db_mapel->query("SELECT * FROM soal WHERE id = " . (int)$edit_id);
    $edit_data = $res->fetch_assoc();
}

$soal_list = $db_mapel->query("SELECT * FROM soal WHERE materi_id = $id_materi ORDER BY id ASC");
$pesan_sukses = $_SESSION['pesan_sukses'] ?? null;
unset($_SESSION['pesan_sukses']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal PJOK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #fff8f0; font-family: 'Inter', sans-serif; }
        .opsi-box { border: 1px solid #ffe8cc; padding: 15px; border-radius: 12px; background: #fff; margin-bottom: 10px; transition: 0.2s; }
        .opsi-box:hover { border-color: #fd7e14; }
        .card-header-custom { background: linear-gradient(45deg, #fd7e14, #ff922b); color: white; font-weight: bold; border: none; }
        .btn-pjok { background-color: #fd7e14; color: white; border: none; }
        .btn-pjok:hover { background-color: #e8590c; color: white; }
        .instruksi-box { background-color: #fff4e6; border-left: 5px solid #fd7e14; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-medal me-2 text-warning"></i> PJOK | GURU</a>
        <span class="text-white">Halo, <?= htmlspecialchars($nama_pengguna) ?></span>
    </div>
</nav>

<div class="container mt-4 mb-5 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-running text-warning"></i> Modul: <?= $judul_materi ?></h3>
        <a href="kuis_list.php" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0">
            <i class="fas fa-check-circle me-2"></i><?= $pesan_sukses ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow-sm mb-5 rounded-4 border-0">
        <div class="card-header card-header-custom p-3 rounded-top-4 text-center">
            <i class="fas <?= $edit_id ? 'fa-user-edit' : 'fa-plus-circle' ?> me-2"></i> 
            <?= $edit_id ? "MODIFIKASI SOAL PJOK" : "INPUT SOAL PJOK BARU" ?>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold mb-2">Pertanyaan / Deskripsi Gerakan</label>
                    <textarea name="pertanyaan" class="form-control border-2" rows="3" required placeholder="Contoh: Bagaimana posisi kaki saat melakukan start jongkok?"><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-4 p-3 border rounded-3 bg-white">
                    <label class="fw-bold text-muted mb-2"><i class="fas fa-camera-retro me-1"></i> Gambar Peraga Gerakan</label>
                    <input type="text" name="gambar_url" class="form-control mb-2" placeholder="Nama file (contoh: bola_basket.png)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <h5 class="mb-3 fw-bold text-dark"><i class="fas fa-th-list me-2"></i> Opsi Jawaban</h5>
                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box shadow-sm">
                            <label class="fw-bold text-warning">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2 border-0 bg-light" value="<?= $edit_data['opsi_'.$k] ?? '' ?>" placeholder="...">
                            <div class="d-flex gap-1">
                                <input type="text" name="opsi_<?= $k ?>_gambar_url" class="form-control form-control-sm" placeholder="File gambar" value="<?= $edit_data['opsi_'.$k.'_gambar_url'] ?? '' ?>">
                                <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 mb-4">
                        <label class="fw-bold text-success"><i class="fas fa-check-double me-1"></i> Kunci Benar</label>
                        <select name="jawaban_benar" class="form-select border-success" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach(['A','B','C','D'] as $o): ?>
                                <option value="<?= $o ?>" <?= (isset($edit_data['jawaban_benar']) && $edit_data['jawaban_benar'] == $o) ? 'selected' : '' ?>><?= $o ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 mb-4">
                        <label class="fw-bold text-primary"><i class="fas fa-info-circle me-1"></i> Instruksi Teknik / Penjelasan</label>
                        <textarea name="pembahasan" class="form-control" rows="2" placeholder="Contoh: Pandangan lurus ke depan, tangan membentuk huruf V terbalik..."><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                    </div>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-pjok w-100 fw-bold py-3 shadow rounded-pill text-uppercase">
                    <?= $edit_id ? 'Simpan Perubahan' : 'Tambahkan Ke Daftar Soal' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-4 text-dark border-bottom pb-2">Daftar Latihan Soal PJOK</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-4 shadow-sm border-0 border-start border-warning border-5 rounded-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-3"><?= htmlspecialchars($s['pertanyaan']) ?></h5>
                        
                        

                        <?php if($s['gambar_url']): ?>
                            <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-3 d-block border rounded shadow-sm" style="max-height: 140px;" onerror="this.style.display='none'">
                        <?php endif; ?>
                        
                        <div class="row g-3">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                                <div class="col-md-6">
                                    <div class="p-2 px-3 border rounded-pill <?= ($s['jawaban_benar'] == strtoupper($k)) ? 'bg-warning text-white border-warning fw-bold' : 'bg-white' ?>">
                                        <?= strtoupper($k) ?>. <?= htmlspecialchars($s['opsi_'.$k]) ?>
                                        <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                            <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 30px;" class="ms-2 border rounded-circle">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if(!empty($s['pembahasan'])): ?>
                            <div class="instruksi-box mt-4 small">
                                <span class="badge bg-warning mb-2">Tips Teknik:</span><br>
                                <?= nl2br(htmlspecialchars($s['pembahasan'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ms-4 text-end">
                        <span class="badge bg-dark px-3 py-2 mb-3">ID: <?= $s['id'] ?></span>
                        <div class="d-flex flex-column gap-2">
                            <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning shadow-sm"><i class="fas fa-edit"></i> Edit</a>
                            <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm" onclick="return confirm('Hapus soal ini?')"><i class="fas fa-trash"></i> Hapus</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>