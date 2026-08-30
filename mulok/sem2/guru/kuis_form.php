<?php
// FILE: guru/kuis_form.php (MULOK) - Updated with Pembahasan
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
    $pembahasan = $_POST['pembahasan']; // Kolom baru
    
    // Ambil nilai dari input text
    $gambar_url = $_POST['gambar_url'];
    $opsi_a_img = $_POST['opsi_a_gambar_url'];
    $opsi_b_img = $_POST['opsi_b_gambar_url'];
    $opsi_c_img = $_POST['opsi_c_gambar_url'];
    $opsi_d_img = $_POST['opsi_d_gambar_url'];

    // Timpa jika ada upload file
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
    header("Location: kuis_form.php?id_materi=$id_materi"); exit();
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
    <title>Kelola Soal Mulok</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #fdfaf6; font-family: 'Inter', sans-serif; }
        .opsi-box { border: 1px solid #d7ccc8; padding: 15px; border-radius: 10px; background: #fff; margin-bottom: 10px; }
        .card-header-custom { background-color: #5d4037; color: white; font-weight: bold; }
        .pembahasan-area { background-color: #efebe9; border: 1px dashed #5d4037; padding: 15px; border-radius: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark p-3 shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-map-marked-alt me-2"></i> MULOK | DASHBOARD GURU</a>
        <span class="text-white">Halo, <?= htmlspecialchars($nama_pengguna) ?></span>
    </div>
</nav>

<div class="container mt-4 mb-5 pt-4">
    <h3><i class="fas fa-landmark text-secondary"></i> Kelola Soal Mulok: <?= htmlspecialchars($materi['judul']) ?></h3>
    <a href="kuis_list.php" class="btn btn-outline-secondary mb-4 btn-sm rounded-pill"><i class="fas fa-arrow-left"></i> Kembali</a>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow-sm mb-4 rounded-4 border-0">
        <div class="card-header card-header-custom p-3 rounded-top-4">
            <i class="fas <?= $edit_id ? 'fa-edit' : 'fa-plus-circle' ?>"></i> <?= $edit_id ? "Edit Soal ID: ".$edit_id : "Tambah Soal Baru" ?>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold">Pertanyaan Budaya/Lokal</label>
                    <textarea name="pertanyaan" class="form-control" rows="10" required placeholder="Contoh: Apa nama rumah adat daerah..."><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-4 p-3 bg-light border rounded-3">
                    <label class="fw-bold text-dark">Gambar Ilustrasi</label>
                    <input type="text" name="gambar_url" class="form-control mb-2" placeholder="Nama file (contoh: tari_piring.jpg)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <h5 class="mb-3 fw-bold">Opsi Jawaban (A-D)</h5>
                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box">
                            <label class="fw-bold text-brown">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>" placeholder="Teks Opsi">
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

                <div class="mb-4 pembahasan-area">
                    <label class="fw-bold text-brown"><i class="fas fa-info-circle"></i> Penjelasan / Pembahasan Budaya</label>
                    <textarea name="pembahasan" class="form-control" rows="3" placeholder="Berikan penjelasan tambahan mengenai materi lokal ini..."><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-dark w-100 fw-bold py-2 shadow">
                    <i class="fas fa-save me-2"></i> <?= $edit_id ? 'SIMPAN PERUBAHAN' : 'TAMBAH SOAL MULOK' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-3">Daftar Soal Muatan Lokal (Total: <?= $soal_list->num_rows ?>)</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-3 shadow-sm border-0 border-start border-brown border-4 rounded-3" style="border-left-color: #5d4037 !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="flex-grow-1">
                        <p class="fw-bold mb-2 text-break" style="white-space: pre-line;"><?= nl2br(htmlspecialchars($s['pertanyaan'])) ?></p>
                        <?php if($s['gambar_url']): ?>
                            <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-3 d-block border rounded shadow-sm" style="max-height: 120px;" onerror="this.src='../aset/no-image.png'">
                        <?php endif; ?>
                        
                        <div class="row g-2 small">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                                <div class="col-6">
                                    <span class="<?= ($s['jawaban_benar'] == strtoupper($k)) ? 'text-success fw-bold' : 'text-muted' ?>">
                                        <?= strtoupper($k) ?>. <?= htmlspecialchars($s['opsi_'.$k]) ?>
                                    </span>
                                    <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                        <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 35px;" class="ms-1 border rounded">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if(!empty($s['pembahasan'])): ?>
                            <div class="mt-3 p-2 bg-white rounded border border-brown small shadow-sm">
                                <strong style="color: #5d4037;"><i class="fas fa-scroll"></i> Info Tambahan:</strong><br>
                                <?= nl2br(htmlspecialchars($s['pembahasan'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ms-3 text-end">
                        <span class="badge bg-dark px-3 py-2 mb-2">Kunci: <?= $s['jawaban_benar'] ?></span>
                        <div class="d-flex flex-column">
                            <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning mb-1"><i class="fas fa-edit"></i></a>
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