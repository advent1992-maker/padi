<?php
// FILE: guru/kuis_form.php (IPAS) - Logika Manual + Bantuan Upload
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
    $pembahasan = $_POST['pembahasan']; // Pastikan ini terisi dari textarea
    
    // Ambil nilai dari input text (logika manual)
    $gambar_url = $_POST['gambar_url'];
    $opsi_a_img = $_POST['opsi_a_gambar_url'];
    $opsi_b_img = $_POST['opsi_b_gambar_url'];
    $opsi_c_img = $_POST['opsi_c_gambar_url'];
    $opsi_d_img = $_POST['opsi_d_gambar_url'];

    // Jika user juga memilih file, timpa nilai text tadi dengan file baru
    $gambar_url = handleUpload('file_pertanyaan', $gambar_url);
    $opsi_a_img = handleUpload('file_a', $opsi_a_img);
    $opsi_b_img = handleUpload('file_b', $opsi_b_img);
    $opsi_c_img = handleUpload('file_c', $opsi_c_img);
    $opsi_d_img = handleUpload('file_d', $opsi_d_img);

    if (isset($_POST['update_soal'])) {
        // PERBAIKAN: Pastikan ada 12 kolom yang di-update + 1 (id) di WHERE
        // Total tanda tanya: 12 untuk SET, 1 untuk WHERE = 13 total.
        $sql = "UPDATE soal SET 
                pertanyaan=?, gambar_url=?, 
                opsi_a=?, opsi_a_gambar_url=?, 
                opsi_b=?, opsi_b_gambar_url=?, 
                opsi_c=?, opsi_c_gambar_url=?, 
                opsi_d=?, opsi_d_gambar_url=?, 
                jawaban_benar=?, pembahasan=? 
                WHERE id=?";
        
        $stmt = $db_mapel->prepare($sql);
        
        // "s" sebanyak 12 kali (untuk kolom teks) + "i" (untuk id_soal) = "ssssssssssssi"
        $stmt->bind_param("ssssssssssssi", 
            $pertanyaan, $gambar_url, 
            $_POST['opsi_a'], $opsi_a_img, 
            $_POST['opsi_b'], $opsi_b_img, 
            $_POST['opsi_c'], $opsi_c_img, 
            $_POST['opsi_d'], $opsi_d_img, 
            $jawaban_benar, $pembahasan, $edit_id
        );
        $stmt->execute();
        
    } else {
        // INSERT: Total 13 kolom (termasuk materi_id)
        $sql = "INSERT INTO soal (
                materi_id, pertanyaan, gambar_url, 
                opsi_a, opsi_a_gambar_url, 
                opsi_b, opsi_b_gambar_url, 
                opsi_c, opsi_c_gambar_url, 
                opsi_d, opsi_d_gambar_url, 
                jawaban_benar, pembahasan
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        
        $stmt = $db_mapel->prepare($sql);
        
        // "i" (id_materi) + "s" sebanyak 12 kali = "issssssssssss"
        $stmt->bind_param("issssssssssss", 
            $id_materi, $pertanyaan, $gambar_url, 
            $_POST['opsi_a'], $opsi_a_img, 
            $_POST['opsi_b'], $opsi_b_img, 
            $_POST['opsi_c'], $opsi_c_img, 
            $_POST['opsi_d'], $opsi_d_img, 
            $jawaban_benar, $pembahasan
        );
        $stmt->execute();
    }
    
    // Redirect setelah sukses
    header("Location: kuis_form.php?id_materi=$id_materi&msg=success"); 
    exit();
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
    <title>Kelola Soal IPAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .opsi-box { border: 1px solid #ddd; padding: 10px; border-radius: 8px; background: #fff; margin-bottom: 10px; }
        .pembahasan-box { border: 1px solid #0dcaf0; background: #f0fbff; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">
    <h3><i class="fas fa-leaf text-success"></i> Kelola Soal IPAS: <?= htmlspecialchars($materi['judul']) ?></h3>
    <a href="kuis_list.php" class="btn btn-secondary mb-4 btn-sm">Kembali</a>

    <?php if ($is_owner_or_admin): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="fw-bold">Pertanyaan</label>
                    <textarea name="pertanyaan" class="form-control" rows="3" required><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                </div>
                
                <div class="mb-3 p-3 bg-light border rounded">
                    <label class="fw-bold text-success">Gambar Pertanyaan</label>
                    <input type="text" name="gambar_url" class="form-control mb-2" placeholder="Nama file (contoh: mtk1.png)" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                </div>

                <div class="row">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box">
                            <label class="fw-bold">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" class="form-control mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>">
                            <input type="text" name="opsi_<?= $k ?>_gambar_url" class="form-control mb-2 form-control-sm" placeholder="Nama file gambar" value="<?= $edit_data['opsi_'.$k.'_gambar_url'] ?? '' ?>">
                            <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <label class="fw-bold text-danger">Kunci Jawaban</label>
                    <select name="jawaban_benar" class="form-select" required>
                        <?php foreach(['A','B','C','D'] as $o): ?>
                            <option value="<?= $o ?>" <?= (isset($edit_data['jawaban_benar']) && $edit_data['jawaban_benar'] == $o) ? 'selected' : '' ?>><?= $o ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 pembahasan-box">
                    <label class="fw-bold text-info"><i class="fas fa-lightbulb"></i> Pembahasan / Penjelasan Soal</label>
                    <textarea name="pembahasan" class="form-control" rows="4" placeholder="Tuliskan penjelasan jawaban di sini... (Mendukung MathJax $..$)"><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                    <small class="text-muted">Pembahasan ini akan muncul saat siswa meninjau kembali kuis mereka.</small>
                </div>

                <button type="submit" name="<?= $edit_id ? 'update_soal' : 'tambah_soal' ?>" class="btn btn-success w-100 fw-bold">
                    <?= $edit_id ? 'UPDATE SOAL IPAS' : 'SIMPAN SOAL IPAS' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h4 class="mb-3">Daftar Soal IPAS (Total: <?= $soal_list->num_rows ?>)</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-3 shadow-sm border-success border-start border-4">
            <div class="card-body">
                <p class="fw-bold"><?= $s['pertanyaan'] ?></p>
                <?php if($s['gambar_url']): ?>
                    <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-2 border" style="max-height: 120px;" onerror="this.src='https://placehold.co/120x80?text=Error+Gambar'">
                <?php endif; ?>
                
                <div class="row small mb-3">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                        <div class="col-6">
                            <span class="<?= ($s['jawaban_benar'] == strtoupper($k)) ? 'text-success fw-bold' : '' ?>">
                                <?= strtoupper($k) ?>. <?= $s['opsi_'.$k] ?>
                                <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                    <img src="<?= $base_url . $s['opsi_'.$k.'_gambar_url'] ?>" style="height: 30px;" class="ms-1 border">
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if(!empty($s['pembahasan'])): ?>
                <div class="bg-light p-2 rounded border small mb-2">
                    <strong class="text-info"><i class="fas fa-comment-dots"></i> Pembahasan:</strong><br>
                    <?= nl2br(htmlspecialchars($s['pembahasan'])) ?>
                </div>
                <?php endif; ?>

                <div class="text-end mt-2">
                    <span class="badge bg-success">Kunci: <?= $s['jawaban_benar'] ?></span>
                    <a href="?id_materi=<?= $id_materi ?>&edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-warning ms-2"><i class="fas fa-edit"></i></a>
                    <a href="kuis_action.php?action=hapus&id_materi=<?= $id_materi ?>&soal_id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>