<?php
// FILE: guru/form_soal_tryout.php - VERSI DENGAN LATEX & GAMBAR (FINAL DENGAN BASE IMAGE URL KOREKSI)

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$tryout_id = $_GET['tryout_id'] ?? null;

// --- KONFIGURASI BASE URL GAMBAR KOREKSI ---
// PATH DIKOREKSI menjadi '../aset/' agar sesuai dengan input Anda 'aset/pp1.png'
// Jika file gambar Anda berada di folder 'aset/' yang setara dengan folder 'guru/', ini adalah path yang benar.
$BASE_IMAGE_URL = 'http://' . $_SERVER['HTTP_HOST'];
// Sesuaikan '/mathfiction/' dengan folder root proyek Anda di localhost
$path_prefix = '/portal_belajar/mathfiction/sem2/aset/';
$BASE_IMAGE_URL .= $path_prefix;

/**
 * Fungsi pembantu untuk menghasilkan URL gambar.
 * Menggabungkan fragmen URL dengan Base URL jika input adalah nama file/path relatif,
 * atau mengembalikan URL lengkap jika sudah mengandung http/https.
 */
function generateImageUrl($url_fragment, $base_url) {
    // Jika input kosong atau NULL, kembalikan string kosong
    if (empty($url_fragment)) {
        return '';
    }
    // Jika input sudah merupakan URL lengkap (misal: http/https), kembalikan langsung
    if (filter_var($url_fragment, FILTER_VALIDATE_URL)) {
        return $url_fragment;
    }
    // Jika input mengandung 'aset/', hapus 'aset/' di awal karena sudah ditambahkan oleh $base_url
    if (strpos(strtolower($url_fragment), 'aset/') === 0) {
        $url_fragment = substr($url_fragment, 5);
    }

    // Jika hanya fragmen/nama file, gabungkan dengan base URL
    return rtrim($base_url, '/') . '/' . ltrim($url_fragment, '/');
}
// --- AKHIR KONFIGURASI BASE URL GAMBAR ---

if (!$tryout_id || !is_numeric($tryout_id)) {
    $_SESSION['error_message'] = "ID Try Out tidak valid.";
    header("Location: manajemen_tryout.php");
    exit();
}

// 1. Ambil Detail Try Out Master
$query_master = "SELECT judul, kelas FROM tryout_master WHERE id = ?";
$stmt_master = $db_mapel->prepare($query_master);
$stmt_master->bind_param("i", $tryout_id);
$stmt_master->execute();
$result_master = $stmt_master->get_result();
$master_data = $result_master->fetch_assoc();
$stmt_master->close();

if (!$master_data) {
    $_SESSION['error_message'] = "Try Out tidak ditemukan.";
    header("Location: manajemen_tryout.php");
    exit();
}
$judul_tryout = $master_data['judul'];
$kelas_tryout = $master_data['kelas'];


// --- LOGIKA HAPUS SOAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_soal'])) {
    $soal_id = $_POST['soal_id'];

    $query = "DELETE FROM soal_tryout WHERE id = ? AND tryout_id = ?";
    $stmt = $db_mapel->prepare($query);
    $stmt->bind_param("ii", $soal_id, $tryout_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Soal berhasil dihapus.";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus soal: " . $stmt->error;
    }
    $stmt->close();
    header("Location: form_soal_tryout.php?tryout_id=" . $tryout_id);
    exit();
}

// --- LOGIKA TAMBAH/EDIT SOAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_soal']) || isset($_POST['edit_soal']))) {
    // Ambil data dari form
    $pertanyaan = $_POST['pertanyaan'] ?? '';
    $gambar_url = $_POST['gambar_url'] ?? '';
    $opsi_a = $_POST['opsi_a'] ?? '';
    $opsi_b = $_POST['opsi_b'] ?? '';
    $opsi_c = $_POST['opsi_c'] ?? '';
    $opsi_d = $_POST['opsi_d'] ?? '';
    $opsi_a_gambar_url = $_POST['opsi_a_gambar_url'] ?? '';
    $opsi_b_gambar_url = $_POST['opsi_b_gambar_url'] ?? '';
    $opsi_c_gambar_url = $_POST['opsi_c_gambar_url'] ?? '';
    $opsi_d_gambar_url = $_POST['opsi_d_gambar_url'] ?? '';
    $jawaban_benar = $_POST['jawaban_benar'] ?? '';

    $mode = isset($_POST['tambah_soal']) ? 'tambah' : 'edit';
    $soal_id = $_POST['soal_id_edit'] ?? null;

    // Bersihkan URL: jika string kosong, set menjadi NULL. (MENYIMPAN NILAI MENTAH)
    $gambar_url_to_save = empty($gambar_url) ? NULL : $gambar_url;
    $opsi_a_img_to_save = empty($opsi_a_gambar_url) ? NULL : $opsi_a_gambar_url;
    $opsi_b_img_to_save = empty($opsi_b_gambar_url) ? NULL : $opsi_b_gambar_url;
    $opsi_c_img_to_save = empty($opsi_c_gambar_url) ? NULL : $opsi_c_gambar_url;
    $opsi_d_img_to_save = empty($opsi_d_gambar_url) ? NULL : $opsi_d_gambar_url;

    // Validasi sederhana
    if (empty($pertanyaan) || empty($jawaban_benar)) {
        $_SESSION['error_message'] = "Field Pertanyaan dan Kunci Jawaban harus diisi.";
    } else if (!in_array($jawaban_benar, ['A', 'B', 'C', 'D'])) {
        $_SESSION['error_message'] = "Kunci Jawaban harus A, B, C, atau D.";
    } else {
        if ($mode == 'tambah') {
            // Tambah Soal Baru
            $query = "
                INSERT INTO soal_tryout (
                    tryout_id, pertanyaan, gambar_url,
                    opsi_a, opsi_a_gambar_url,
                    opsi_b, opsi_b_gambar_url,
                    opsi_c, opsi_c_gambar_url,
                    opsi_d, opsi_d_gambar_url,
                    jawaban_benar
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $db_mapel->prepare($query);
            // Binding: i, s, s, s, s, s, s, s, s, s, s, s (12 params)
            $stmt->bind_param(
                "isssssssssss",
                $tryout_id, $pertanyaan, $gambar_url_to_save,
                $opsi_a, $opsi_a_img_to_save,
                $opsi_b, $opsi_b_img_to_save,
                $opsi_c, $opsi_c_img_to_save,
                $opsi_d, $opsi_d_img_to_save,
                $jawaban_benar
            );

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Soal baru berhasil ditambahkan.";
            } else {
                $_SESSION['error_message'] = "Gagal menambahkan soal: " . $stmt->error;
            }
        } elseif ($mode == 'edit' && $soal_id) {
            // Edit Soal
            $query = "
                UPDATE soal_tryout SET
                    pertanyaan=?, gambar_url=?,
                    opsi_a=?, opsi_a_gambar_url=?,
                    opsi_b=?, opsi_b_gambar_url=?,
                    opsi_c=?, opsi_c_gambar_url=?,
                    opsi_d=?, opsi_d_gambar_url=?,
                    jawaban_benar=?
                WHERE id=? AND tryout_id=?
            ";
            $stmt = $db_mapel->prepare($query);
            // Binding: 11x string, 2x integer (13 params)
            $stmt->bind_param(
                "sssssssssssii",
                $pertanyaan, $gambar_url_to_save,
                $opsi_a, $opsi_a_img_to_save,
                $opsi_b, $opsi_b_img_to_save,
                $opsi_c, $opsi_c_img_to_save,
                $opsi_d, $opsi_d_img_to_save,
                $jawaban_benar,
                $soal_id, $tryout_id
            );

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Soal berhasil diperbarui.";
            } else {
                $_SESSION['error_message'] = "Gagal memperbarui soal: " . $stmt->error;
            }
        }
        $stmt->close();
    }

    header("Location: form_soal_tryout.php?tryout_id=" . $tryout_id);
    exit();
}

// 2. Ambil Daftar Soal yang Sudah Ada
$query_soal_list = "SELECT * FROM soal_tryout WHERE tryout_id = ? ORDER BY id ASC";
$stmt_list = $db_mapel->prepare($query_soal_list);
$stmt_list->bind_param("i", $tryout_id);
$stmt_list->execute();
$result_list = $stmt_list->get_result();
$soal_list = $result_list->fetch_all(MYSQLI_ASSOC);
$stmt_list->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Soal: <?php echo htmlspecialchars($judul_tryout); ?></title>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .opsi-container { border: 1px dashed #ced4da; padding: 10px; border-radius: 8px; background-color: #f8f9fa; }
        .kunci-jawaban-badge { background-color: #0d6efd; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        /* Gaya untuk MathJax */
        .MathJax_Display { overflow-x: auto; overflow-y: hidden; padding: 5px 0; }
        .soal-card { border-left: 5px solid #198754; border-radius: 0.5rem; }
    </style>
    <script>
        // Konfigurasi MathJax untuk rumus inline dan display
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']]
            },
            svg: {
                fontCache: 'global'
            }
        };
    </script>
</head>
<body>

<div class="container mt-5">
    <a href="manajemen_tryout.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Kembali ke Manajemen Try Out</a>

    <div class="alert alert-info shadow-sm">
        <h3 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Kelola Soal Ujian: <?php echo htmlspecialchars($judul_tryout); ?></h3>
        <p class="mb-0">Kelas: <?php echo htmlspecialchars($kelas_tryout); ?> | Total Soal Saat Ini: <?php echo count($soal_list); ?></p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-5">
        <div class="card-header bg-success text-white fw-bold">
            <i class="fas fa-plus-circle"></i> Tambah Soal Baru (Dukungan LaTeX & Gambar)
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="tambah_soal" value="1">

                <div class="mb-4">
                    <label for="pertanyaan" class="form-label fw-bold text-success">1. Pertanyaan Soal (Teks & Rumus LaTeX)</label>
                    <textarea class="form-control mb-2" id="pertanyaan" name="pertanyaan" rows="4" required
                        placeholder="Masukkan pertanyaan. Untuk rumus, gunakan notasi LaTeX, contoh: $\frac{1}{2}x^2 + 5x = 10$"></textarea>

                    <label for="gambar_url" class="form-label fw-bold text-secondary mt-2">URL Gambar/Grafik Pertanyaan (Opsional)</label>
                    <input type="text" class="form-control" id="gambar_url" name="gambar_url"
                        placeholder="Masukkan URL gambar lengkap (http://...) atau nama file (pp1.png)">
                    <small class="form-text text-danger d-block">Gunakan URL gambar yang sudah diunggah. Kami tidak mendukung upload file langsung. Jika Anda hanya memasukkan nama file (misal: `pp1.png` atau `aset/pp1.png`), sistem akan menggunakan path **`<?php echo $BASE_IMAGE_URL; ?>`** di depannya saat ditampilkan.</small>
                </div>

                <h5 class="mt-4 mb-3 fw-bold text-primary">2. Opsi Jawaban (A-D)</h5>

                <div class="row g-3">
                    <?php
                    $opsi_keys = ['a', 'b', 'c', 'd'];
                    foreach ($opsi_keys as $key):
                    ?>
                    <div class="col-md-6">
                        <div class="opsi-container">
                            <label for="opsi_<?php echo $key; ?>" class="form-label fw-bold">Opsi <?php echo strtoupper($key); ?> (Teks/Rumus)</label>
                            <textarea class="form-control mb-2" id="opsi_<?php echo $key; ?>" name="opsi_<?php echo $key; ?>" rows="2"
                                        placeholder="Teks atau Rumus Opsi <?php echo strtoupper($key); ?>"></textarea>

                            <label for="opsi_<?php echo $key; ?>_gambar_url" class="form-label fw-bold text-secondary">URL Gambar Opsi <?php echo strtoupper($key); ?> (Opsional)</label>
                            <input type="text" class="form-control" id="opsi_<?php echo $key; ?>_gambar_url" name="opsi_<?php echo $key; ?>_gambar_url"
                                        placeholder="URL lengkap atau nama file">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 mb-3">
                        <label for="jawaban_benar" class="form-label fw-bold text-danger">3. Kunci Jawaban Benar</label>
                        <select name="jawaban_benar" id="jawaban_benar" class="form-select" required>
                            <option value="">-- Pilih Jawaban --</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 py-2"><i class="fas fa-plus-circle"></i> Simpan Soal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <hr>

    <h4 class="mt-5 mb-3"><i class="fas fa-clipboard-list"></i> Daftar Soal (Total: <?php echo count($soal_list); ?> Soal)</h4>

    <?php if (empty($soal_list)): ?>
        <div class="alert alert-warning text-center">
            Belum ada soal untuk Try Out **<?php echo htmlspecialchars($judul_tryout); ?>**.
        </div>
    <?php else: ?>
        <?php $no_soal = 1; foreach ($soal_list as $soal): ?>
        <div class="card shadow-sm mb-3 soal-card">
            <div class="card-header bg-light fw-bold">Soal No. <?php echo $no_soal++; ?> | Jawaban: <span class="kunci-jawaban-badge"><?php echo htmlspecialchars($soal['jawaban_benar']); ?></span></div>
            <div class="card-body">
                <p class="card-text fw-bold soal-pertanyaan">
                    <?php echo $soal['pertanyaan']; ?>
                </p>

                <?php $soal_gambar_url = generateImageUrl($soal['gambar_url'], $BASE_IMAGE_URL); ?>
                <?php if (!empty($soal_gambar_url)): ?>
                    <div class="mb-3 text-center p-2 border rounded bg-white">
                        <img src="<?php echo htmlspecialchars($soal_gambar_url); ?>" alt="Gambar Soal"
                            class="img-fluid rounded shadow-sm" style="max-height: 150px; object-fit: contain;"
                            onerror="this.onerror=null;this.src='https://placehold.co/150x100/f0f8ff/444?text=Gambar+Soal';"
                        >
                    </div>
                <?php endif; ?>

                <ul class="list-unstyled row g-2 small">
                    <?php
                    $opsi_list = [
                        'A' => ['text' => $soal['opsi_a'], 'url' => $soal['opsi_a_gambar_url']],
                        'B' => ['text' => $soal['opsi_b'], 'url' => $soal['opsi_b_gambar_url']],
                        'C' => ['text' => $soal['opsi_c'], 'url' => $soal['opsi_c_gambar_url']],
                        'D' => ['text' => $soal['opsi_d'], 'url' => $soal['opsi_d_gambar_url']],
                    ];
                    foreach ($opsi_list as $key => $opsi):
                    ?>
                    <li class="col-md-6">
                        <span class="fw-bold me-2"><?php echo $key; ?>.</span>
                        <?php $opsi_gambar_url = generateImageUrl($opsi['url'], $BASE_IMAGE_URL); ?>
                        <?php if (!empty($opsi_gambar_url)): ?>
                            <img src="<?php echo htmlspecialchars($opsi_gambar_url); ?>" alt="Opsi <?php echo $key; ?>"
                                class="img-fluid border rounded" style="max-height: 50px; display: inline-block; object-fit: contain;"
                                onerror="this.onerror=null;this.src='https://placehold.co/50x50/e9ecef/495057?text=X';"
                            >
                        <?php elseif (!empty($opsi['text'])): ?>
                            <?php echo $opsi['text']; ?>
                        <?php else: ?>
                            <small class="text-danger">Opsi Kosong</small>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-footer text-end">
                <button type="button" class="btn btn-sm btn-warning text-white me-2"
                        data-bs-toggle="modal"
                        data-bs-target="#editSoalModal"
                        data-id="<?php echo $soal['id']; ?>"
                        data-pertanyaan="<?php echo htmlspecialchars($soal['pertanyaan']); ?>"
                        data-gambarurl="<?php echo htmlspecialchars($soal['gambar_url'] ?? ''); ?>"
                        data-a="<?php echo htmlspecialchars($soal['opsi_a']); ?>"
                        data-aurl="<?php echo htmlspecialchars($soal['opsi_a_gambar_url'] ?? ''); ?>"
                        data-b="<?php echo htmlspecialchars($soal['opsi_b']); ?>"
                        data-burl="<?php echo htmlspecialchars($soal['opsi_b_gambar_url'] ?? ''); ?>"
                        data-c="<?php echo htmlspecialchars($soal['opsi_c']); ?>"
                        data-curl="<?php echo htmlspecialchars($soal['opsi_c_gambar_url'] ?? ''); ?>"
                        data-d="<?php echo htmlspecialchars($soal['opsi_d']); ?>"
                        data-durl="<?php echo htmlspecialchars($soal['opsi_d_gambar_url'] ?? ''); ?>"
                        data-jawaban="<?php echo htmlspecialchars($soal['jawaban_benar']); ?>">
                    <i class="fas fa-edit"></i> Edit Soal
                </button>

                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus soal No. <?php echo $no_soal-1; ?>?');">
                    <input type="hidden" name="hapus_soal" value="1">
                    <input type="hidden" name="soal_id" value="<?php echo $soal['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Hapus</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="modal fade" id="editSoalModal" tabindex="-1" aria-labelledby="editSoalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editSoalModalLabel">Edit Detail Soal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_soal" value="1">
                        <input type="hidden" name="soal_id_edit" id="soal_id_edit">

                        <div class="mb-4">
                            <label for="pertanyaan_edit" class="form-label fw-bold">Pertanyaan/Soal (Teks & LaTeX)</label>
                            <textarea name="pertanyaan" id="pertanyaan_edit" class="form-control" rows="4" required></textarea>

                            <label for="gambar_url_edit" class="form-label fw-bold text-secondary mt-2">URL Gambar Pertanyaan (Opsional)</label>
                            <input type="text" class="form-control" id="gambar_url_edit" name="gambar_url" placeholder="URL Gambar Pertanyaan">
                        </div>

                        <h5 class="mt-4 mb-3 fw-bold text-primary">Opsi Jawaban (A-D)</h5>
                        <div class="row g-3">
                            <?php
                            $opsi_keys = ['a', 'b', 'c', 'd'];
                            foreach ($opsi_keys as $key):
                            ?>
                            <div class="col-md-6">
                                <div class="opsi-container">
                                    <label for="opsi_<?php echo $key; ?>_edit" class="form-label fw-bold">Opsi <?php echo strtoupper($key); ?> (Teks/Rumus)</label>
                                    <textarea name="opsi_<?php echo $key; ?>" id="opsi_<?php echo $key; ?>_edit" class="form-control mb-2" rows="2"></textarea>

                                    <label for="opsi_<?php echo $key; ?>_gambar_url_edit" class="form-label fw-bold text-secondary">URL Gambar Opsi <?php echo strtoupper($key); ?> (Opsional)</label>
                                    <input type="text" class="form-control" id="opsi_<?php echo $key; ?>_gambar_url_edit" name="opsi_<?php echo $key; ?>_gambar_url" placeholder="URL Gambar Opsi <?php echo strtoupper($key); ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3 mt-4">
                            <label for="jawaban_benar_edit" class="form-label fw-bold text-danger">Kunci Jawaban Benar</label>
                            <select name="jawaban_benar" id="jawaban_benar_edit" class="form-select" required>
                                <option value="">-- Pilih Jawaban --</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning text-dark"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script untuk mengisi data ke Modal Edit Soal
    var editSoalModal = document.getElementById('editSoalModal');
    editSoalModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;

        // Ambil Data
        var id = button.getAttribute('data-id');
        var pertanyaan = button.getAttribute('data-pertanyaan');
        // Mengambil data mentah (URL lengkap atau fragmen/nama file)
        var gambarurl = button.getAttribute('data-gambarurl');

        var a = button.getAttribute('data-a');
        var aurl = button.getAttribute('data-aurl');
        var b = button.getAttribute('data-b');
        var burl = button.getAttribute('data-burl');
        var c = button.getAttribute('data-c');
        var curl = button.getAttribute('data-curl');
        var d = button.getAttribute('data-d');
        var durl = button.getAttribute('data-durl');

        var jawaban = button.getAttribute('data-jawaban');

        // Isi ke Modal
        editSoalModal.querySelector('#soal_id_edit').value = id;
        editSoalModal.querySelector('#pertanyaan_edit').value = pertanyaan;
        // Memasukkan nilai mentah ke input field
        editSoalModal.querySelector('#gambar_url_edit').value = gambarurl;

        editSoalModal.querySelector('#opsi_a_edit').value = a;
        editSoalModal.querySelector('#opsi_a_gambar_url_edit').value = aurl;
        editSoalModal.querySelector('#opsi_b_edit').value = b;
        editSoalModal.querySelector('#opsi_b_gambar_url_edit').value = burl;
        editSoalModal.querySelector('#opsi_c_edit').value = c;
        editSoalModal.querySelector('#opsi_c_gambar_url_edit').value = curl;
        editSoalModal.querySelector('#opsi_d_edit').value = d;
        editSoalModal.querySelector('#opsi_d_gambar_url_edit').value = durl;

        editSoalModal.querySelector('#jawaban_benar_edit').value = jawaban;

        // Memaksa MathJax untuk merender ulang konten di modal setelah data terisi
        if (window.MathJax) {
             MathJax.typesetPromise([editSoalModal]);
        }
    });

    // Memuat ulang MathJax setelah dokumen dimuat untuk memastikan semua soal ter-render
    document.addEventListener('DOMContentLoaded', function () {
        if (window.MathJax) {
            MathJax.typesetPromise();
        }
    });
</script>
</body>
</html>