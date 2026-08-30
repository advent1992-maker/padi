<?php
// FILE: guru/kuis_form.php (PENDIDIKAN PANCASILA) - Logika Manual + Bantuan Upload + Pembahasan
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

$user_id = $_SESSION['user_id'] ?? 0;
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$id_materi = $_GET['id_materi'] ?? null;
$edit_id = $_GET['edit_id'] ?? null;

// 1. Ambil Data Materi
$stmt_materi = $db_mapel->prepare("SELECT judul, id_guru FROM panca_materi WHERE id = ?");
$stmt_materi->bind_param("i", $id_materi);
$stmt_materi->execute();
$materi = $stmt_materi->get_result()->fetch_assoc();
$stmt_materi->close();

if (!$materi) {
    header("Location: kuis_list.php");
    exit();
}

$is_owner_or_admin = ($_SESSION['role'] === 'admin' || $user_id == $materi['id_guru']);

// --- FUNGSI BANTUAN UPLOAD ---
function handleUpload($file_input, $current_value) {
    global $upload_dir;
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $filename = "pp_" . time() . "_" . basename($_FILES[$file_input]['name']);
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
    $pembahasan = $_POST['pembahasan']; // Menangkap data pembahasan
    
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
        $sql = "UPDATE panca_soal SET pertanyaan=?, gambar_url=?, opsi_a=?, opsi_a_gambar_url=?, opsi_b=?, opsi_b_gambar_url=?, opsi_c=?, opsi_c_gambar_url=?, opsi_d=?, opsi_d_gambar_url=?, jawaban_benar=?, pembahasan=? WHERE id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("ssssssssssssi", $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan, $edit_id);
        $stmt->execute();
    } else {
        $sql = "INSERT INTO panca_soal (materi_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar, pembahasan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("issssssssssss", $id_materi, $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $pembahasan);
        $stmt->execute();
    }
    header("Location: kuis_form.php?id_materi=$id_materi"); exit();
}

// 3. Ambil data edit jika ada
$edit_data = null;
if ($edit_id) {
    $res = $db_mapel->query("SELECT * FROM panca_soal WHERE id = " . (int)$edit_id);
    $edit_data = $res->fetch_assoc();
}

$soal_list = $db_mapel->query("SELECT * FROM panca_soal WHERE materi_id = $id_materi ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal Pend. Pancasila</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .opsi-box { border: 1px solid #dee2e6; padding: 15px; border-radius: 10px; background: #fff; margin-bottom: 10px; transition: 0.3s; }
        .opsi-box:hover { border-color: #dc3545; }
        .card-header-pp { background: linear-gradient(45deg, #dc3545, #b02a37); color: white; font-weight: bold; }
        .pembahasan-panel { background-color: #fff5f5; border-left: 4px solid #dc3545; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-shield-alt"></i> PANCASILA | GURU</a>
        <span class="navbar-text text-white">Selamat Datang, <?= htmlspecialchars($nama_pengguna) ?></span>
    </div>
</nav>

<div class="container mt-4 mb-5 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-flag text-danger"></i> Materi: <?= htmlspecialchars($materi['judul']) ?></h3>
        <a href="kuis_list.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-chevron-left"></i> Kembali</a>
    </div>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow-sm mb-5 border-0 rounded-4">
        <div class="card-header card-header-pp p-3 rounded-top-4">
            <i class="fas <?= $edit_id ? 'fa-pen-square' : 'fa-plus-circle' ?> me-1"></i> 
            <?= $edit_id ? "Edit Soal (ID: $edit_id)" : "Tambah Soal Baru" ?>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold mb-1">Butir Pertanyaan</label>
                    <textarea name="pertanyaan" class="form-control" rows="3" required placeholder="Contoh: Apa lambang sila kedua Pancasila?"><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-4 p-3 bg-light border rounded">
                    <label class="fw-bold text-danger mb-1"><i class="fas fa-image"></i> Gambar Ilustrasi</label>
                    <input type="text" name="gambar_url" class="form-control mb-2" placeholder="Nama file (contoh: rantai.png)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box shadow-sm">
                            <label class="fw-bold">Pilihan <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>" placeholder="Teks jawaban...">
                            <input type="text" name="opsi_<?= $k ?>_gambar_url" class="form-control mb-2 form-control-sm" placeholder="Nama file gambar" value="<?= $edit_data['opsi_'.$k.'_gambar_url'] ?? '' ?>">
                            <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="fw-bold text-primary">Kunci Jawaban</label>
                            <select name="jawaban_benar" class="form-select border-primary" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach(['A','B','C','D'] as $o): ?>
                                    <option value="<?= $o ?>" <?= (isset($edit_data['jawaban_benar']) && $edit_data['jawaban_benar'] == $o) ? 'selected' : '' ?>><?= $o ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                  <div class="col-md-8">
    <div class="mb-3">
        <label class="fw-bold text-dark">Pembahasan / Dasar Hukum / Nilai Pancasila</label>
        <textarea name="pembahasan" class="form-control shadow-sm border-danger" rows="10" style="resize: vertical;" placeholder="Sertakan alasan atau pasal terkait jika perlu..."><?= $edit_data['pembahasan'] ?? '' ?></textarea>
    </div>
</div>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-danger w-100 fw-bold py-2 shadow-sm">
                    <i class="fas fa-save me-1"></i> <?= $edit_id ? 'UPDATE DATA SOAL' : 'SIMPAN DATA SOAL' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-4 border-bottom pb-2">Daftar Soal Tersimpan</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-4 shadow-sm border-0 border-start border-danger border-5 rounded-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <p class="fw-bold mb-3"><?= $s['pertanyaan'] ?></p>
                        
                        

                        <?php if($s['gambar_url']): ?>
                            <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-3 border rounded shadow-sm" style="max-height: 150px;" onerror="this.src='https://placehold.co/150x100?text=Gambar+Hilang'">
                        <?php endif; ?>
                        
                        <div class="row g-2">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                                <div class="col-md-6 small">
                                    <div class="p-2 border rounded <?= ($s['jawaban_benar'] == strtoupper($k)) ? 'bg-light fw-bold border-success text-success' : 'bg-white' ?>">
                                        <?= strtoupper($k) ?>. <?= htmlspecialchars($s['opsi_'.$k]) ?>
                                        <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                            <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 30px;" class="ms-1 border shadow-sm">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if(!empty($s['pembahasan'])): ?>
                            <div class="pembahasan-panel mt-3 small">
                                <i class="fas fa-lightbulb text-warning"></i> <strong>Penjelasan:</strong><br>
                                <?= nl2br(htmlspecialchars($s['pembahasan'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ms-3 text-end">
                        <span class="badge bg-danger px-3 py-2 mb-2">Kunci: <?= $s['jawaban_benar'] ?></span>
                        <div class="d-flex flex-column gap-1">
                            <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit text-white"></i></a>
                            <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus soal ini?')"><i class="fas fa-trash"></i></a>
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