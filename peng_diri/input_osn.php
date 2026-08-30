<?php
// 1. Laporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/session.php';
require_once '../config/koneksi.php'; // Database portal utama

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../index.php");
    exit;
}

// 2. Ambil paket_id dari URL
$paket_id = $_GET['paket_id'] ?? null;

if (!$paket_id) {
    echo "<script>alert('Pilih paket terlebih dahulu!'); window.location='paket_list.php?kat=osn';</script>";
    exit;
}

// 3. Konfigurasi Path & Koneksi
$upload_dir = "../aset/"; 
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))) . "/aset/";

$conn_pd = $conn; 

// --- FUNGSI UPLOAD (TAMBAHAN PERBAIKAN) ---
function uploadFile($inputName, $oldFile = '') {
    global $upload_dir;
    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$inputName]['tmp_name'];
        $fileName = time() . '_' . basename($_FILES[$inputName]['name']);
        $targetPath = $upload_dir . $fileName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            return $fileName;
        }
    }
    return $oldFile;
}
// ------------------------------------------

// PROSES HAPUS
if (isset($_GET['hapus_id'])) {
    $hid = intval($_GET['hapus_id']);
    mysqli_query($conn_pd, "DELETE FROM osn WHERE id = '$hid' AND paket_id = '$paket_id'");
    header("Location: input_osn.php?paket_id=$paket_id");
    exit;
}

// AMBIL DATA EDIT
$id_edit = $_GET['id_edit'] ?? null;
$edit_data = null;
if ($id_edit) {
    $q_edit = mysqli_query($conn_pd, "SELECT * FROM osn WHERE id = '$id_edit' AND paket_id = '$paket_id'");
    $edit_data = mysqli_fetch_assoc($q_edit);
}

// PROSES SIMPAN (INSERT/UPDATE)
if (isset($_POST['simpan'])) {
    $id_soal    = $_POST['id_soal'] ?? null;
    $judul      = mysqli_real_escape_string($conn_pd, $_POST['judul']);
    $pertanyaan = mysqli_real_escape_string($conn_pd, $_POST['pertanyaan']);
    $tipe_soal  = $_POST['tipe_soal'];
    $kunci      = mysqli_real_escape_string($conn_pd, $_POST['kunci_jawaban']);
    $pembahasan = mysqli_real_escape_string($conn_pd, $_POST['pembahasan']);

    // Sekarang fungsi uploadFile sudah bisa dipanggil
    $gambar_url = uploadFile('file_pertanyaan', $_POST['gambar_url_old'] ?? '');
    $img_a = uploadFile('file_a', $_POST['img_a_old'] ?? '');
    $img_b = uploadFile('file_b', $_POST['img_b_old'] ?? '');
    $img_c = uploadFile('file_c', $_POST['img_c_old'] ?? '');
    $img_d = uploadFile('file_d', $_POST['img_d_old'] ?? '');

    $opsi_a = mysqli_real_escape_string($conn_pd, $_POST['opsi_a'] ?? '');
    $opsi_b = mysqli_real_escape_string($conn_pd, $_POST['opsi_b'] ?? '');
    $opsi_c = mysqli_real_escape_string($conn_pd, $_POST['opsi_c'] ?? '');
    $opsi_d = mysqli_real_escape_string($conn_pd, $_POST['opsi_d'] ?? '');

    if ($id_soal) {
        $query = "UPDATE osn SET 
                  judul = '$judul', pertanyaan = '$pertanyaan', gambar_url = '$gambar_url', 
                  tipe_soal = '$tipe_soal', opsi_a = '$opsi_a', opsi_b = '$opsi_b', 
                  opsi_c = '$opsi_c', opsi_d = '$opsi_d', 
                  img_a = '$img_a', img_b = '$img_b', img_c = '$img_c', img_d = '$img_d',
                  kunci_jawaban = '$kunci', pembahasan = '$pembahasan' 
                  WHERE id = '$id_soal'";
    } else {
        $query = "INSERT INTO osn (paket_id, judul, pertanyaan, gambar_url, tipe_soal, opsi_a, opsi_b, opsi_c, opsi_d, img_a, img_b, img_c, img_d, kunci_jawaban, pembahasan) 
                  VALUES ('$paket_id', '$judul', '$pertanyaan', '$gambar_url', '$tipe_soal', '$opsi_a', '$opsi_b', '$opsi_c', '$opsi_d', '$img_a', '$img_b', '$img_c', '$img_d', '$kunci', '$pembahasan')";
    }

    if (mysqli_query($conn_pd, $query)) {
        echo "<script>alert('Berhasil Disimpan!'); window.location='input_osn.php?paket_id=$paket_id';</script>";
    } else {
        echo "Error Database: " . mysqli_error($conn_pd);
    }
}

$soal_list = mysqli_query($conn_pd, "SELECT * FROM osn WHERE paket_id = '$paket_id' ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Soal OSN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/core-js/3.30.2/minified.min.js"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true
            }
        };
    </script>

    <style>
        body { background: #f4f7fe; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .opsi-box { background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; border-radius: 10px; margin-bottom: 10px; }
        .pembahasan-area { background: #fffdf0 !important; border: 2px solid #ffeeba !important; }
        .img-preview-mini { max-height: 50px; border-radius: 5px; margin-top: 5px; }
        .btn-ai { background: #1a1a1a; color: #fff; transition: 0.3s; }
        .btn-ai:hover { background: #333; color: #0dcaf0; }
        #preview_pembahasan { min-height: 60px; font-size: 0.9rem; line-height: 1.6; }
        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); display: none; z-index: 9999;
            flex-direction: column; align-items: center; justify-content: center; color: white;
        }
    </style>
</head>
<body class="pb-5">

<div id="loadingOverlay">
    <div class="spinner-border text-info mb-3" role="status"></div>
    <h5 class="fw-bold">AI sedang bekerja...</h5>
</div>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-primary"><i class="fas fa-microscope me-2"></i>Kelola Soal OSN</h4>
        <a href="paket_list.php?kat=osn" class="btn btn-secondary btn-sm rounded-pill px-3">Kembali ke Paket</a>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card p-4 text-center mb-3">
                <p class="small text-muted mb-2">Pilih Metode Generator AI:</p>
                <div class="d-grid gap-2">
                    <a href="ai_generator_osn.php?paket_id=<?= $paket_id ?>" class="btn btn-ai rounded-pill py-2 shadow-sm">
                        <i class="fas fa-robot me-2 text-info"></i> 1 Soal (Tinjau Dulu)
                    </a>
                    <a href="ai_generator_osn_massal.php?paket_id=<?= $paket_id ?>" class="btn btn-primary rounded-pill py-2 shadow-sm">
                        <i class="fas fa-layer-group me-2"></i> Banyak soal
                    </a>
                </div>
            </div>

            <div class="card p-4">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_soal" value="<?= $edit_data['id'] ?? '' ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Topik / Sub-Materi</label>
                        <input type="text" name="judul" class="form-control form-control-sm" value="<?= $edit_data['judul'] ?? '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Tipe Soal</label>
                        <select name="tipe_soal" id="tipe_soal" class="form-select form-select-sm" onchange="toggleTipe()" required>
                            <option value="pg" <?= (isset($edit_data['tipe_soal']) && $edit_data['tipe_soal'] == 'pg') ? 'selected' : '' ?>>Pilihan Ganda (PG)</option>
                            <option value="isian" <?= (isset($edit_data['tipe_soal']) && $edit_data['tipe_soal'] == 'isian') ? 'selected' : '' ?>>Isian Singkat</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Pertanyaan</label>
                        <textarea name="pertanyaan" class="form-control" rows="3" required><?= $edit_data['pertanyaan'] ?? '' ?></textarea>
                    </div>

                    <div class="mb-4 p-3 bg-light border rounded-3">
                        <label class="fw-bold small text-success"><i class="fas fa-image me-1"></i> Gambar Pertanyaan</label>
                        <input type="file" name="file_pertanyaan" class="form-control form-control-sm">
                        <input type="hidden" name="gambar_url_old" value="<?= $edit_data['gambar_url'] ?? '' ?>">
                    </div>

                    <div id="section_pg" style="<?= (isset($edit_data['tipe_soal']) && $edit_data['tipe_soal'] == 'isian') ? 'display:none;' : '' ?>">
                        <div class="row g-2 mb-3">
                            <?php foreach(['a','b','c','d'] as $k): ?>
                            <div class="col-12">
                                <div class="opsi-box">
                                    <label class="fw-bold small">Opsi <?= strtoupper($k) ?></label>
                                    <input type="text" name="opsi_<?= $k ?>" class="form-control form-control-sm mb-2" value="<?= $edit_data['opsi_'.$k] ?? '' ?>">
                                    <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                                    <input type="hidden" name="img_<?= $k ?>_old" value="<?= $edit_data['img_'.$k] ?? '' ?>">
                                    <?php if(!empty($edit_data['img_'.$k])): ?>
                                        <img src="<?= $base_url . $edit_data['img_'.$k] ?>" class="img-preview-mini">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger">Kunci Jawaban</label>
                        <input type="text" name="kunci_jawaban" class="form-control form-control-sm" value="<?= $edit_data['kunci_jawaban'] ?? '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-success">Pembahasan (Gunakan $...$ untuk rumus)</label>
                        <div id="preview_pembahasan" class="p-2 mb-2 border rounded bg-white overflow-auto shadow-sm">
                            <span class="text-muted small italic">Pratinjau rumus...</span>
                        </div>
                        <textarea name="pembahasan" id="input_pembahasan" class="form-control pembahasan-area" rows="8" oninput="updatePreview()"><?= $edit_data['pembahasan'] ?? '' ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="simpan" class="btn <?= $id_edit ? 'btn-success' : 'btn-primary' ?> w-100 fw-bold rounded-pill">
                            <?= $id_edit ? 'Update Soal' : 'Simpan Soal' ?>
                        </button>
                        <?php if($id_edit): ?>
                            <a href="input_osn.php?paket_id=<?= $paket_id ?>" class="btn btn-outline-secondary rounded-pill">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <h5 class="fw-bold mb-3">Bank Soal Paket Ini</h5>
            <div id="soal_container">
                <?php if(mysqli_num_rows($soal_list) > 0): ?>
                    <?php while($s = mysqli_fetch_assoc($soal_list)): ?>
                    <div class="card mb-3 p-3 border-start border-primary border-4 shadow-sm">
                        <div class="d-flex justify-content-between">
                            <div class="flex-grow-1">
                                <div class="mb-2 fw-bold text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($s['pertanyaan']) ?></div>
                                
                                <?php if($s['gambar_url']): ?>
                                    <img src="<?= $base_url . $s['gambar_url'] ?>" class="mb-2 border rounded" style="max-height: 120px;">
                                <?php endif; ?>
                                
                                <?php if($s['tipe_soal'] == 'pg'): ?>
                                    <div class="row g-2 small text-muted mb-3">
                                        <div class="col-6">A: <?= $s['opsi_a'] ?></div>
                                        <div class="col-6">B: <?= $s['opsi_b'] ?></div>
                                        <div class="col-6">C: <?= $s['opsi_c'] ?></div>
                                        <div class="col-6">D: <?= $s['opsi_d'] ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <span class="badge bg-success">Kunci: <?= $s['kunci_jawaban'] ?></span>
                                    <span class="badge bg-secondary text-uppercase"><?= $s['tipe_soal'] ?></span>
                                </div>

                                <?php if(!empty($s['pembahasan'])): ?>
                                <div class="p-2 rounded" style="background-color: #fffdf0; border: 1px dashed #ffeeba;">
                                    <small class="fw-bold text-success d-block mb-1"><i class="fas fa-lightbulb me-1"></i> Pembahasan:</small>
                                    <div class="small text-dark">
                                        <?= nl2br($s['pembahasan']) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="ms-3 d-flex flex-column gap-2">
                                <a href="input_osn.php?paket_id=<?= $paket_id ?>&id_edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="input_osn.php?paket_id=<?= $paket_id ?>&hapus_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus soal ini?')" title="Hapus"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted bg-white rounded-4 shadow-sm">
                        <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                        <p>Belum ada soal dalam paket ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleTipe() {
        const tipe = document.getElementById('tipe_soal').value;
        const sectionPg = document.getElementById('section_pg');
        sectionPg.style.display = (tipe === 'isian') ? 'none' : 'block';
    }

    let timer;
    function updatePreview() {
        const input = document.getElementById('input_pembahasan').value;
        const preview = document.getElementById('preview_pembahasan');
        
        if(input.trim() === "") {
            preview.innerHTML = '<span class="text-muted small italic">Pratinjau rumus...</span>';
            return;
        }

        preview.innerHTML = input.replace(/\n/g, '<br>');
        
        // Debounce to improve performance
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (window.MathJax) {
                MathJax.typesetPromise([preview]).catch((err) => console.log(err.message));
            }
        }, 600);
    }

    window.addEventListener('load', function() {
        updatePreview();
        if (window.MathJax) {
            MathJax.typeset();
        }
    });
</script>

</body>
</html>