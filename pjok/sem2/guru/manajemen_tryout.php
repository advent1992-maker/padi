<?php
// ======================================================================================
// MANAJEMEN_TRYOUT.PHP (SISI GURU) - Kelola Try Out (Master Ujian)
// ======================================================================================
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan yang mengakses adalah GURU
if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$kelas_diajar_str = $_SESSION['kelas'] ?? '';
// LOGIKA TOGGLE TAMPILKAN/SEMBUNYIKAN
if (isset($_GET['toggle_id'])) {
    $tid = (int)$_GET['toggle_id'];
    $status_sekarang = (int)$_GET['status'];
    $status_baru = ($status_sekarang == 1) ? 0 : 1;

    $stmt = $db_mapel->prepare("UPDATE " . tbl('tryout_master') . " SET tampilkan = ? WHERE id = ? AND id_guru = ?");
    $stmt->bind_param("iii", $status_baru, $tid, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manajemen_tryout.php");
    exit();
}

// --- FUNGSI HELPER UNTUK BIND PARAMETER ---
function bind_params_helper($stmt, $types, $params) {
    if (count($params) > 0) {
        $bind_params = array_merge([$types], $params);
        $ref_params = [];
        foreach ($bind_params as $key => $value) { $ref_params[$key] = &$bind_params[$key]; }
        return call_user_func_array([$stmt, 'bind_param'], $ref_params);
    }
    return true;
}

// --- FUNGSI HELPER UNTUK EKSEKUSI QUERY ---
function execute_query($db_mapel, $query, $params, $types) {
    $list = [];
    $stmt = $db_mapel->prepare($query);
    if ($stmt === false) die('Prepare failed: ' . htmlspecialchars($db_mapel->error));
    if (!empty($params) && !empty($types)) { bind_params_helper($stmt, $types, $params); }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $list[] = $row; }
    } else { die('Execute failed: ' . htmlspecialchars($stmt->error)); }
    $stmt->close();
    return $list;
}

// ===================================================================
// --- LOGIKA BARU: ADOPSI TRY OUT ---
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adopsi_tryout'])) {
    $original_id = $_POST['tryout_id'];

    // 1. Ambil data asli dari tryout_master
    $stmt_o = $db_mapel->prepare("SELECT * FROM " . tbl('tryout_master') . " WHERE id = ?");
    $stmt_o->bind_param("i", $original_id);
    $stmt_o->execute();
    $data_orig = $stmt_o->get_result()->fetch_assoc();
    $stmt_o->close();

    if ($data_orig) {
        // 2. Insert ke tryout_master baru (milik user saat ini)
        $new_judul = $data_orig['judul'];
        $stmt_i = $db_mapel->prepare("INSERT INTO " . tbl('tryout_master') . " (judul, jenis_ujian, kelas, waktu_alokasi, id_guru) VALUES (?, ?, ?, ?, ?)");
        $stmt_i->bind_param("ssiii", $new_judul, $data_orig['jenis_ujian'], $data_orig['kelas'], $data_orig['waktu_alokasi'], $user_id);

        if ($stmt_i->execute()) {
            $new_id = $db_mapel->insert_id;

            // 3. Salin SEMUA SOAL yang terkait ke tryout yang baru dibuat
            $q_soal = "INSERT INTO " . tbl('soal_tryout') . " (tryout_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar, gambar_url)
                       SELECT ?, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar, gambar_url
                       FROM " . tbl('soal_tryout') . " WHERE tryout_id = ?";
            $stmt_s = $db_mapel->prepare($q_soal);
            $stmt_s->bind_param("ii", $new_id, $original_id);
            $stmt_s->execute();
            $stmt_s->close();

            $_SESSION['success_message'] = "Try Out berhasil diadopsi menjadi milik Anda!";
        } else {
            $_SESSION['error_message'] = "Gagal mengadopsi Try Out.";
        }
        $stmt_i->close();
    }
    header("Location: manajemen_tryout.php");
    exit();
}

// ===================================================================
// --- LOGIKA CRUD TRY OUT (DELETE, EDIT, CREATE) ---
// ===================================================================

// --- LOGIKA TAMBAH TRY OUT (CREATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_tryout'])) {
    $judul = $_POST['judul'];
    $jenis_ujian = $_POST['jenis_ujian'];
    $kelas = $_POST['kelas'];
    $waktu_alokasi = $_POST['waktu_alokasi'];

    if (!empty($judul) && !empty($jenis_ujian) && !empty($kelas) && is_numeric($waktu_alokasi) && $waktu_alokasi > 0) {
        $query = "INSERT INTO " . tbl('tryout_master') . " (judul, jenis_ujian, kelas, waktu_alokasi, id_guru) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db_mapel->prepare($query);
        $stmt->bind_param("ssiii", $judul, $jenis_ujian, $kelas, $waktu_alokasi, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Try Out '{$judul}' berhasil ditambahkan!";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan Try Out.";
        }
        $stmt->close();
    }
    header("Location: manajemen_tryout.php");
    exit();
}

// --- LOGIKA EDIT TRY OUT (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tryout'])) {
    $id = $_POST['id_edit'];
    $judul = $_POST['judul_edit'];
    $jenis_ujian = $_POST['jenis_ujian_edit'];
    $kelas = $_POST['kelas_edit'];
    $waktu_alokasi = $_POST['waktu_alokasi_edit'];

    $query = "UPDATE " . tbl('tryout_master') . " SET judul=?, jenis_ujian=?, kelas=?, waktu_alokasi=? WHERE id=? AND id_guru=?";
    $stmt = $db_mapel->prepare($query);
    $stmt->bind_param("ssiiii", $judul, $jenis_ujian, $kelas, $waktu_alokasi, $id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Try Out diperbarui.";
    }
    $stmt->close();
    header("Location: manajemen_tryout.php");
    exit();
}

// --- LOGIKA HAPUS TRY OUT (DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_tryout'])) {
    $tryout_id = $_POST['tryout_id'];

    // Hapus Soal & Riwayat terkait dulu agar tidak error Foreign Key (jika ada)
    $db_mapel->query("DELETE FROM " . tbl('soal_tryout') . " WHERE tryout_id = $tryout_id");
    $db_mapel->query("DELETE FROM " . tbl('riwayat_tryout') . " WHERE tryout_id = $tryout_id");

    $query = "DELETE FROM " . tbl('tryout_master') . " WHERE id = ? AND id_guru = ?";
    $stmt = $db_mapel->prepare($query);
    $stmt->bind_param("ii", $tryout_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Try Out berhasil dihapus.";
    }
    $stmt->close();
    header("Location: manajemen_tryout.php");
    exit();
}

// ===================================================================
// --- LOGIKA FILTER DAN QUERY (READ) ---
// ===================================================================

$kelas_array = explode(',', $kelas_diajar_str);
$placeholders = implode(',', array_fill(0, count($kelas_array), '?'));

// 1. QUERY TRY OUT PRIBADI
$query_pribadi = "SELECT tm.*, COUNT(st.id) AS total_soal FROM " . tbl('tryout_master') . " tm LEFT JOIN " . tbl('soal_tryout') . " st ON tm.id = st.tryout_id WHERE tm.kelas IN ($placeholders) AND tm.id_guru = ? GROUP BY tm.id ORDER BY tm.id DESC";
$tryout_pribadi = execute_query($db_mapel, $query_pribadi, array_merge($kelas_array, [$user_id]), str_repeat("s", count($kelas_array))."i");

// Versi lebih ketat: Hanya tampilkan judul yang BELUM dimiliki oleh user login
$query_adopsi = "SELECT tm.*, COUNT(st.id) AS total_soal 
                 FROM " . tbl('tryout_master') . " tm 
                 LEFT JOIN " . tbl('soal_tryout') . " st ON tm.id = st.tryout_id 
                 WHERE tm.kelas IN ($placeholders) 
                 AND tm.id_guru != ? 
                 AND tm.judul NOT IN (SELECT judul FROM " . tbl('tryout_master') . " WHERE id_guru = ?)
                 GROUP BY tm.judul 
                 ORDER BY tm.id DESC";

// Sesuaikan parameter bindingnya (tambah satu lagi $user_id)
$params_adopsi = array_merge($kelas_array, [$user_id, $user_id]);
$types_adopsi = str_repeat("s", count($kelas_array)) . "ii";
$tryout_adopsi = execute_query($db_mapel, $query_adopsi, $params_adopsi, $types_adopsi);

$db_mapel->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Try Out | Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .table-custom th { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>

    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0"><i class="fas fa-tasks"></i> Manajemen Try Out</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
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

        <div class="card shadow mb-5 border-primary">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-plus-circle"></i> Tambah Ujian Try Out Baru
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="tambah_tryout" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="judul" class="form-label">Judul Ujian (Contoh: Penilaian Harian 1, UTS)</label>
                            <input type="text" name="judul" id="judul" class="form-control" placeholder="Contoh: Tugas Mandiri Bab 1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="kelas" class="form-label">Kelas Target</label>
                            <input type="number" name="kelas" id="kelas" class="form-control" min="1" placeholder="Cth: 5" required>
                        </div>
                        <div class="col-md-3">
                            <label for="waktu_alokasi" class="form-label">Waktu Alokasi (Menit)</label>
                            <input type="number" name="waktu_alokasi" id="waktu_alokasi" class="form-control" min="10" placeholder="Cth: 60" required>
                        </div>
                        <div class="col-md-6">
                            <label for="jenis_ujian" class="form-label">Jenis Ujian (Untuk Laporan)</label>
                            <select name="jenis_ujian" id="jenis_ujian" class="form-select" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="HARIAN">HARIAN</option>
                                <option value="UTS">UTS</option>
                                <option value="US">US</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100 fw-bold"><i class="fas fa-save"></i> Tambah Try Out</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <hr>

        <h2 class="h4 mt-4 mb-3 text-success">
            <i class="fas fa-user-check me-2"></i> Try Out Pribadi Anda (Dapat Dikelola)
        </h2>

        <?php if (empty($tryout_pribadi)): ?>
             <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle"></i> Anda belum membuat Try Out pribadi.
            </div>
        <?php else: ?>
            <div class="table-responsive shadow-lg rounded mb-5">
                <table class="table table-hover align-middle table-striped mb-0 table-custom">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">Judul Ujian</th>
                            <th width="8%">Kelas</th>
                            <th width="10%">Waktu (Min)</th>
                            <th width="10%">Jenis</th>
                            <th width="8%" class="text-center">Jml Soal</th>
                            <th width="10%" class="text-center">Status</th>
                            <th width="39%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($tryout_pribadi as $tryout): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($tryout['judul']); ?></td>
                            <td><span class="badge bg-success">Kelas <?php echo htmlspecialchars($tryout['kelas']); ?></span></td>
                            <td><?php echo htmlspecialchars($tryout['waktu_alokasi']); ?></td>
                            <td><span class="badge bg-<?php echo ($tryout['jenis_ujian'] == 'US' ? 'danger' : ($tryout['jenis_ujian'] == 'UTS' ? 'warning' : 'primary')); ?>"><?php echo htmlspecialchars($tryout['jenis_ujian']); ?></span></td>
                            <td class="text-center"><span class="badge bg-info text-dark fs-6"><?php echo htmlspecialchars($tryout['total_soal']); ?></span></td>
                            <td class="text-center">
    <?php if($tryout['tampilkan'] == 1): ?>
        <a href="?toggle_id=<?= $tryout['id'] ?>&status=1" 
           class="btn btn-sm btn-light border text-success fw-bold rounded-pill px-3 shadow-sm"
           title="Klik untuk sembunyikan">
            <i class="fas fa-eye me-1"></i> Tampil
        </a>
    <?php else: ?>
        <a href="?toggle_id=<?= $tryout['id'] ?>&status=0" 
           class="btn btn-sm btn-secondary fw-bold rounded-pill px-3 shadow-sm"
           title="Klik untuk tampilkan">
            <i class="fas fa-eye-slash me-1"></i> Sembunyi
        </a>
    <?php endif; ?>
</td>
                            <td class="text-center">
                                <a href="form_soal_tryout.php?tryout_id=<?php echo $tryout['id']; ?>" class="btn btn-sm btn-success mb-1"><i class="fas fa-pencil-alt"></i> Kelola Soal</a>

                                <a href="ai_generator_tryout_page.php?tryout_id=<?php echo $tryout['id']; ?>" class="btn btn-sm btn-info text-white mb-1"><i class="fas fa-robot"></i> Buat dengan AI</a>
                                <a href="ambil_kuis_proses.php?tryout_id=<?php echo $tryout['id']; ?>" class="btn btn-sm btn-info text-white mb-1">
    <i class="fas fa-sync-alt"></i> Ambil dari Kuis
</a>

                                <button type="button" class="btn btn-sm btn-warning text-white mb-1" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $tryout['id']; ?>" data-judul="<?php echo htmlspecialchars($tryout['judul']); ?>" data-kelas="<?php echo htmlspecialchars($tryout['kelas']); ?>" data-waktu="<?php echo htmlspecialchars($tryout['waktu_alokasi']); ?>" data-jenis="<?php echo htmlspecialchars($tryout['jenis_ujian']); ?>"><i class="fas fa-edit"></i> Edit</button>

                                <button type="button" class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-id="<?php echo $tryout['id']; ?>" data-judul="<?php echo htmlspecialchars($tryout['judul']); ?>"><i class="fas fa-trash"></i> Hapus</button>
                          <a href="preview_tryout_cetak.php?tryout_id=<?= $tryout['id'] ?>" class="btn btn-sm btn-info text-white fw-bold mb-1">
    <i class="fas fa-print"></i> Preview & Cetak
</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="h4 mt-4 mb-3 text-info">
            <i class="fas fa-star me-2"></i> Try Out Guru Lain (Dapat Dilihat & Ditugaskan)
        </h2>

        <?php if (empty($tryout_adopsi)): ?>
             <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Tidak ada Try Out dari guru lain yang tersedia.
            </div>
        <?php else: ?>
            <div class="table-responsive shadow-lg rounded">
                <table class="table table-hover align-middle table-striped mb-0 table-custom">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Judul Ujian</th>
                            <th width="10%">Kelas</th>
                            <th width="10%">Waktu (Min)</th>
                            <th width="10%">Jenis</th>
                            <th width="10%" class="text-center">Jml Soal</th>
                            <th width="30%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($tryout_adopsi as $tryout): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($tryout['judul']); ?></td>
                            <td><span class="badge bg-success">Kelas <?php echo htmlspecialchars($tryout['kelas']); ?></span></td>
                            <td><?php echo htmlspecialchars($tryout['waktu_alokasi']); ?></td>
                            <td><span class="badge bg-<?php echo ($tryout['jenis_ujian'] == 'US' ? 'danger' : ($tryout['jenis_ujian'] == 'UTS' ? 'warning' : 'primary')); ?>"><?php echo htmlspecialchars($tryout['jenis_ujian']); ?></span></td>
                            <td class="text-center"><span class="badge bg-info text-dark fs-6"><?php echo htmlspecialchars($tryout['total_soal']); ?></span></td>
                            <td class="text-center">
                                

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="adopsi_tryout" value="1">
                                    <input type="hidden" name="tryout_id" value="<?php echo $tryout['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary me-2 mb-1" onclick="return confirm('Adopsi Try Out ini?')"><i class="fas fa-copy"></i> Adopsi</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-warning text-white"><h5 class="modal-title">Edit Detail Try Out</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_tryout" value="1"><input type="hidden" name="id_edit" id="id_edit">
                        <div class="mb-3"><label class="form-label">Judul Ujian</label><input type="text" name="judul_edit" id="judul_edit" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Kelas Target</label><input type="number" name="kelas_edit" id="kelas_edit" class="form-control" min="1" required></div>
                        <div class="mb-3"><label class="form-label">Waktu Alokasi (Menit)</label><input type="number" name="waktu_alokasi_edit" id="waktu_alokasi_edit" class="form-control" min="10" required></div>
                        <div class="mb-3"><label class="form-label">Jenis Ujian</label>
                            <select name="jenis_ujian_edit" id="jenis_ujian_edit" class="form-select" required>
                                <option value="HARIAN">HARIAN</option><option value="UTS">UTS</option><option value="US">US</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-warning text-white">Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white"><h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>Hapus Try Out: <strong id="tryoutJudulHapus"></strong>?</p>
                        <input type="hidden" name="hapus_tryout" value="1"><input type="hidden" name="tryout_id" id="tryout_id_hapus">
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Hapus Permanen</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            var b = event.relatedTarget;
            document.getElementById('id_edit').value = b.getAttribute('data-id');
            document.getElementById('judul_edit').value = b.getAttribute('data-judul');
            document.getElementById('kelas_edit').value = b.getAttribute('data-kelas');
            document.getElementById('waktu_alokasi_edit').value = b.getAttribute('data-waktu');
            document.getElementById('jenis_ujian_edit').value = b.getAttribute('data-jenis');
        });
        var deleteConfirmModal = document.getElementById('deleteConfirmModal');
        deleteConfirmModal.addEventListener('show.bs.modal', function (event) {
            var b = event.relatedTarget;
            document.getElementById('tryout_id_hapus').value = b.getAttribute('data-id');
            document.getElementById('tryoutJudulHapus').textContent = b.getAttribute('data-judul');
        });
    </script>
</body>
</html>