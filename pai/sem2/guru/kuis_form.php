<?php
// FILE: guru/kuis_form.php (PAI) - Logika Manual + Bantuan Upload + Pembahasan/Dalil
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

$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
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
    $pembahasan = $_POST['pembahasan']; // Kolom Pembahasan/Dalil
    
    // Ambil nilai dari input text
    $gambar_url = $_POST['gambar_url'];
    $opsi_a_img = $_POST['opsi_a_gambar_url'];
    $opsi_b_img = $_POST['opsi_b_gambar_url'];
    $opsi_c_img = $_POST['opsi_c_gambar_url'];
    $opsi_d_img = $_POST['opsi_d_gambar_url'];

    // Timpa jika ada file baru
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
    } else {
        $sql = "INSERT INTO soal (materi_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar, pembahasan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("issssssssssss", $id_materi, $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan);
        $stmt->execute();
    }
    header("Location: kuis_form.php?id_materi=$id_materi&msg=success"); exit();
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
    <title>Kelola Soal PAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f8e9; font-family: 'Inter', sans-serif; }
        .opsi-box { border: 1px solid #c8e6c9; padding: 15px; border-radius: 10px; background: #fff; margin-bottom: 10px; }
        .card-header-pai { background-color: #2e7d32; color: white; font-weight: bold; }
        .pembahasan-pai { background-color: #e8f5e9; border: 1px solid #a5d6a7; padding: 15px; border-radius: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-star-and-crescent me-2"></i> PAI | DASHBOARD GURU</a>
        <span class="text-white"><?= htmlspecialchars($nama_pengguna) ?></span>
    </div>
</nav>

<div class="container mt-4 mb-5 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-mosque text-success"></i> Kelola Soal: <?= htmlspecialchars($materi['judul']) ?></h3>
        <a href="kuis_list.php" class="btn btn-outline-success btn-sm rounded-pill px-3"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow-sm mb-5 rounded-4 border-0">
        <div class="card-header card-header-pai p-3 rounded-top-4 text-center">
            <i class="fas <?= $edit_id ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i> 
            <?= $edit_id ? "MODIFIKASI SOAL (ID: $edit_id)" : "INPUT SOAL PAI BARU" ?>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold">Pertanyaan (Bisa Sertakan Ayat/Hadits)</label>
                    <textarea name="pertanyaan" class="form-control" rows="3" required placeholder="Tuliskan butir soal di sini..."><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-4 p-3 bg-light border rounded-3">
                    <label class="fw-bold text-success">Media Gambar (Opsional)</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-white"><i class="fas fa-link text-muted"></i></span>
                        <input type="text" name="gambar_url" class="form-control" placeholder="Nama file manual (contoh: Kaabah.jpg)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    </div>
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <h5 class="mb-3 fw-bold text-dark"><i class="fas fa-list-ul me-2"></i> Pilihan Jawaban</h5>
                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box shadow-sm">
                            <label class="fw-bold text-success">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>" placeholder="Teks Jawaban">
                            <input type="text" name="opsi_<?= $k ?>_gambar_url" class="form-control mb-2 form-control-sm" placeholder="Nama file gambar" value="<?= $edit_data['opsi_'.$k.'_gambar_url'] ?? '' ?>">
                            <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 mb-4">
                        <label class="fw-bold text-danger"><i class="fas fa-key me-1"></i> Kunci Jawaban</label>
                        <select name="jawaban_benar" class="form-select border-danger" required>
                            <option value="">-- Pilih Kunci --</option>
                            <?php foreach(['A','B','C','D'] as $o): ?>
                                <option value="<?= $o ?>" <?= (isset($edit_data['jawaban_benar']) && $edit_data['jawaban_benar'] == $o) ? 'selected' : '' ?>><?= $o ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 mb-4">
                        <div class="pembahasan-pai shadow-sm">
                            <label class="fw-bold text-success"><i class="fas fa-book-open me-1"></i> Penjelasan / Dalil Jawaban</label>
                            <textarea name="pembahasan" class="form-control" rows="3" placeholder="Sertakan referensi dalil atau penjelasan singkat..."><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-success w-100 fw-bold py-3 shadow rounded-pill">
                    <i class="fas fa-save me-2"></i> <?= $edit_id ? 'SIMPAN PERUBAHAN SOAL' : 'PUBLIKASIKAN SOAL' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-4 text-dark border-bottom pb-2">Daftar Soal Aktif (Total: <?= $soal_list->num_rows ?>)</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-4 shadow-sm border-0 border-start border-success border-5 rounded-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between">
                    <div class="flex-grow-1">
                        <h5 class="fw-bold text-dark mb-3"><?= $s['pertanyaan'] ?></h5>
                        <?php if($s['gambar_url']): ?>
                            <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-3 d-block border rounded shadow-sm" style="max-height: 150px;" onerror="this.style.display='none'">
                        <?php endif; ?>
                        
                        <div class="row g-3">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                                <div class="col-md-6">
                                    <div class="p-2 border rounded <?= ($s['jawaban_benar'] == strtoupper($k)) ? 'bg-success text-white border-success' : 'bg-white' ?>">
                                        <span class="fw-bold"><?= strtoupper($k) ?>.</span> <?= htmlspecialchars($s['opsi_'.$k]) ?>
                                        <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                            <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 35px;" class="ms-1 border rounded">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if(!empty($s['pembahasan'])): ?>
                            <div class="mt-4 p-3 bg-light rounded border-start border-3 border-info">
                                <small class="text-info fw-bold"><i class="fas fa-info-circle"></i> Referensi/Penjelasan:</small><br>
                                <span class="small italic"><?= nl2br(htmlspecialchars($s['pembahasan'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ms-4 text-end">
                        <span class="badge bg-success px-3 py-2 mb-3 shadow-sm">KUNCI: <?= $s['jawaban_benar'] ?></span>
                        <div class="d-flex flex-column gap-2">
                            <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-warning btn-sm shadow-sm"><i class="fas fa-edit me-1 text-white"></i></a>
                            <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" class="btn btn-danger btn-sm shadow-sm" onclick="return confirm('Hapus soal ini?')"><i class="fas fa-trash me-1"></i></a>
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