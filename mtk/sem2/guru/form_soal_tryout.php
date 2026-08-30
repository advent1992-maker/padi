<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$tryout_id = $_GET['tryout_id'] ?? null;

// --- LOGIKA PATH & FOLDER UPLOAD ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host_url = $_SERVER['HTTP_HOST'];
$current_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$parent_path = dirname($current_path); 
$BASE_IMAGE_URL = $protocol . "://" . $host_url . $parent_path . "/aset/";
$upload_dir = "../aset/"; 

// --- FUNGSI BANTUAN UPLOAD ---
function handleUpload($file_input, $current_value) {
    global $upload_dir;
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $filename = time() . "_" . str_replace(' ', '_', basename($_FILES[$file_input]['name']));
        if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $upload_dir . $filename)) {
            return $filename; 
        }
    }
    return $current_value; 
}

function generateImageUrl($url_fragment, $base_url) {
    if (empty($url_fragment)) return '';
    if (filter_var($url_fragment, FILTER_VALIDATE_URL)) return $url_fragment;
    return rtrim($base_url, '/') . '/' . ltrim($url_fragment, '/');
}

// Ambil Detail Master
$stmt_master = $db_mapel->prepare("SELECT judul, kelas FROM tryout_master WHERE id = ?");
$stmt_master->bind_param("i", $tryout_id);
$stmt_master->execute();
$master_data = $stmt_master->get_result()->fetch_assoc();
$stmt_master->close();

if (!$master_data) { header("Location: manajemen_tryout.php"); exit; }

// --- LOGIKA HAPUS SOAL ---
if (isset($_POST['hapus_soal'])) {
    $id_h = $_POST['soal_id'];
    $db_mapel->query("DELETE FROM soal_tryout WHERE id = $id_h AND tryout_id = $tryout_id");
    header("Location: form_soal_tryout.php?tryout_id=$tryout_id"); exit();
}

// --- LOGIKA TAMBAH/EDIT SOAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_soal']) || isset($_POST['edit_soal']))) {
    $pertanyaan = $_POST['pertanyaan'];
    $jawaban_benar = $_POST['jawaban_benar'];
    $mode = isset($_POST['tambah_soal']) ? 'tambah' : 'edit';
    
    $gambar_url = handleUpload('file_pertanyaan', $_POST['gambar_url']);
    $opsi_a_img = handleUpload('file_a', $_POST['opsi_a_gambar_url']);
    $opsi_b_img = handleUpload('file_b', $_POST['opsi_b_gambar_url']);
    $opsi_c_img = handleUpload('file_c', $_POST['opsi_c_gambar_url']);
    $opsi_d_img = handleUpload('file_d', $_POST['opsi_d_gambar_url']);

    if ($mode == 'tambah') {
        $sql = "INSERT INTO soal_tryout (tryout_id, pertanyaan, gambar_url, opsi_a, opsi_a_gambar_url, opsi_b, opsi_b_gambar_url, opsi_c, opsi_c_gambar_url, opsi_d, opsi_d_gambar_url, jawaban_benar) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("isssssssssss", $tryout_id, $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar);
    } else {
        $soal_id = $_POST['soal_id_edit'];
        $sql = "UPDATE soal_tryout SET pertanyaan=?, gambar_url=?, opsi_a=?, opsi_a_gambar_url=?, opsi_b=?, opsi_b_gambar_url=?, opsi_c=?, opsi_c_gambar_url=?, opsi_d=?, opsi_d_gambar_url=?, jawaban_benar=? WHERE id=? AND tryout_id=?";
        $stmt = $db_mapel->prepare($sql);
        $stmt->bind_param("sssssssssssii", $pertanyaan, $gambar_url, $_POST['opsi_a'], $opsi_a_img, $_POST['opsi_b'], $opsi_b_img, $_POST['opsi_c'], $opsi_c_img, $_POST['opsi_d'], $opsi_d_img, $jawaban_benar, $soal_id, $tryout_id);
    }
    $stmt->execute();
    header("Location: form_soal_tryout.php?tryout_id=$tryout_id"); exit();
}

$soal_list = $db_mapel->query("SELECT * FROM soal_tryout WHERE tryout_id = $tryout_id ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Soal: <?= htmlspecialchars($master_data['judul']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .opsi-box { background: #fff; border: 1px solid #e0e0e0; padding: 15px; border-radius: 12px; margin-bottom: 10px; transition: 0.3s; }
        .header-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; }
        .btn-edit-mode { background-color: #ffc107 !important; color: #000 !important; }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <div class="header-gradient shadow-sm d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0"><i class="fas fa-edit me-2"></i> Kelola Soal Try Out</h2>
            <p class="mb-0 opacity-75"><?= htmlspecialchars($master_data['judul']) ?> | Kelas <?= $master_data['kelas'] ?></p>
        </div>
        <a href="manajemen_tryout.php" class="btn btn-light rounded-pill px-4 fw-bold text-primary">Kembali</a>
    </div>

    <div class="card mb-5 border-start border-4 border-success" id="form-container">
        <div class="card-body p-4">
            <h5 class="fw-bold text-success mb-4" id="form-title"><i class="fas fa-plus-circle me-2"></i> Form Input Soal</h5>
            <form method="POST" enctype="multipart/form-data" id="main-form">
                <input type="hidden" name="soal_id_edit" id="soal_id_edit">

                <div class="mb-4">
                    <label class="fw-bold small">Pertanyaan (Teks / LaTeX)</label>
                    <textarea name="pertanyaan" id="pertanyaan" class="form-control" rows="3" placeholder="Masukkan pertanyaan..." required></textarea>
                    <div class="mt-2 p-2 bg-light rounded border">
                        <label class="small fw-bold text-muted">Gambar Stimulus</label>
                        <div class="row g-2">
                            <div class="col-md-6"><input type="text" name="gambar_url" id="gambar_url" class="form-control form-control-sm" placeholder="Nama file manual"></div>
                            <div class="col-md-6"><input type="file" name="file_pertanyaan" class="form-control form-control-sm"></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                    <div class="col-md-6">
                        <div class="opsi-box">
                            <label class="fw-bold small">Opsi <?= strtoupper($k) ?></label>
                            <input type="text" name="opsi_<?= $k ?>" id="opsi_<?= $k ?>" class="form-control mb-2" placeholder="Teks opsi...">
                            <div class="p-2 bg-light rounded border">
                                <input type="text" name="opsi_<?= $k ?>_gambar_url" id="opsi_<?= $k ?>_img" class="form-control form-control-sm mb-1" placeholder="Nama file manual">
                                <input type="file" name="file_<?= $k ?>" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold text-danger small">Kunci Jawaban</label>
                        <select name="jawaban_benar" id="jawaban_benar" class="form-select shadow-sm" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach(['A','B','C','D'] as $o): ?> <option value="<?= $o ?>"><?= $o ?></option> <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3 d-flex gap-2">
                        <button type="submit" name="tambah_soal" id="btn-submit" class="btn btn-success btn-lg px-5 rounded-pill shadow fw-bold w-100">SIMPAN SOAL</button>
                        <button type="button" id="btn-batal" class="btn btn-secondary btn-lg px-4 rounded-pill shadow fw-bold d-none" onclick="resetForm()">BATAL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h4 class="fw-bold mb-4"><i class="fas fa-list me-2"></i> Daftar Soal</h4>
    <?php while($s = $soal_list->fetch_assoc()): ?>
        <div class="card mb-3 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <p class="fw-bold text-primary mb-2">Pertanyaan:</p>
                    <span class="badge bg-primary px-3 py-2 rounded-pill">Kunci: <?= $s['jawaban_benar'] ?></span>
                </div>
                <p><?= $s['pertanyaan'] ?></p>
                <?php if($s['gambar_url']): ?>
                    <img src="<?= generateImageUrl($s['gambar_url'], $BASE_IMAGE_URL) ?>" class="mb-3 border rounded shadow-sm" style="max-height: 120px;">
                <?php endif; ?>

                <div class="row g-2 small mt-2">
                    <?php foreach(['a','b','c','d'] as $k): ?>
                        <div class="col-md-6 border-bottom py-1">
                            <strong><?= strtoupper($k) ?>.</strong> <?= $s['opsi_'.$k] ?>
                            <?php if($s['opsi_'.$k.'_gambar_url']): ?>
                                <img src="<?= generateImageUrl($s['opsi_'.$k.'_gambar_url'], $BASE_IMAGE_URL) ?>" style="height: 30px;" class="ms-2 border rounded">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-end mt-3">
                    <button type="button" class="btn btn-sm btn-warning px-4 rounded-pill fw-bold btn-edit" 
                        data-id="<?= $s['id'] ?>"
                        data-pertanyaan="<?= htmlspecialchars($s['pertanyaan']) ?>"
                        data-gambar="<?= htmlspecialchars($s['gambar_url'] ?? '') ?>"
                        data-opsi_a="<?= htmlspecialchars($s['opsi_a']) ?>"
                        data-opsi_a_img="<?= htmlspecialchars($s['opsi_a_gambar_url'] ?? '') ?>"
                        data-opsi_b="<?= htmlspecialchars($s['opsi_b']) ?>"
                        data-opsi_b_img="<?= htmlspecialchars($s['opsi_b_gambar_url'] ?? '') ?>"
                        data-opsi_c="<?= htmlspecialchars($s['opsi_c']) ?>"
                        data-opsi_c_img="<?= htmlspecialchars($s['opsi_c_gambar_url'] ?? '') ?>"
                        data-opsi_d="<?= htmlspecialchars($s['opsi_d']) ?>"
                        data-opsi_d_img="<?= htmlspecialchars($s['opsi_d_gambar_url'] ?? '') ?>"
                        data-kunci="<?= $s['jawaban_benar'] ?>">
                        <i class="fas fa-edit me-1"></i> Edit
                    </button>
                    
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus soal?')">
                        <input type="hidden" name="hapus_soal" value="1">
                        <input type="hidden" name="soal_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger px-4 rounded-pill ms-1">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<script>
    const formTitle = document.getElementById('form-title');
    const btnSubmit = document.getElementById('btn-submit');
    const btnBatal = document.getElementById('btn-batal');
    const formContainer = document.getElementById('form-container');
    const mainForm = document.getElementById('main-form');

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            // Ubah tampilan form ke mode Edit
            formTitle.innerHTML = '<i class="fas fa-edit me-2"></i> Edit Soal';
            btnSubmit.name = 'edit_soal';
            btnSubmit.innerText = 'UPDATE SOAL';
            btnSubmit.classList.replace('btn-success', 'btn-warning');
            btnBatal.classList.remove('d-none');
            formContainer.classList.replace('border-success', 'border-warning');

            // Isi Data
            document.getElementById('soal_id_edit').value = this.dataset.id;
            document.getElementById('pertanyaan').value = this.dataset.pertanyaan;
            document.getElementById('gambar_url').value = this.dataset.gambar;
            document.getElementById('opsi_a').value = this.dataset.opsi_a;
            document.getElementById('opsi_a_img').value = this.dataset.opsi_a_img;
            document.getElementById('opsi_b').value = this.dataset.opsi_b;
            document.getElementById('opsi_b_img').value = this.dataset.opsi_b_img;
            document.getElementById('opsi_c').value = this.dataset.opsi_c;
            document.getElementById('opsi_c_img').value = this.dataset.opsi_c_img;
            document.getElementById('opsi_d').value = this.dataset.opsi_d;
            document.getElementById('opsi_d_img').value = this.dataset.opsi_d_img;
            document.getElementById('jawaban_benar').value = this.dataset.kunci;

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    function resetForm() {
        mainForm.reset();
        document.getElementById('soal_id_edit').value = '';
        formTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i> Form Input Soal';
        btnSubmit.name = 'tambah_soal';
        btnSubmit.innerText = 'SIMPAN SOAL';
        btnSubmit.classList.replace('btn-warning', 'btn-success');
        btnBatal.classList.add('d-none');
        formContainer.classList.replace('border-warning', 'border-success');
    }
</script>
</body>
</html>