<?php
require_once '../config/koneksi.php';
// Pastikan session_start() ada jika belum diatur di koneksi.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Halaman
if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$kelas_diajar_str = $_SESSION['kelas'] ?? ''; // Ambil daftar kelas guru (misal: "4,5,6")
$pesan = "";

// --- HANDLING PESAN NOTIFIKASI ---
if (isset($_GET['pesan'])) {
    $pesan = "<div class='alert alert-success'>" . htmlspecialchars($_GET['pesan']) . "</div>";
} elseif (isset($_SESSION['pesan_sukses'])) {
    $pesan = "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['pesan_sukses']) . "</div>";
    unset($_SESSION['pesan_sukses']);
} elseif (isset($_SESSION['pesan_error'])) {
    $pesan = "<div class='alert alert-danger'>" . htmlspecialchars($_SESSION['pesan_error']) . "</div>";
    unset($_SESSION['pesan_error']);
}

// --- FUNGSI HELPER UNTUK EKSEKUSI QUERY ---
function execute_query($db_mapel, $query, $params = [], $types = "") {
    $list = [];
    $stmt = $db_mapel->prepare($query);

    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($db_mapel->error) . ' | Query: ' . $query);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    } else {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }
    $stmt->close();
    return $list;
}

// --- LOGIKA HAPUS MATERI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $db_mapel->begin_transaction();
    try {
        // ... (kode pengecekan akses tetap sama) ...

        // 1. HAPUS RIWAYAT KUIS (Agar nilai di rekap kereset)
        $db_mapel->query("DELETE FROM " . tbl('riwayat_kuis') . " WHERE id_materi = $delete_id");

        // 2. Hapus Soal
        $db_mapel->query("DELETE FROM " . tbl('soal') . " WHERE materi_id = $delete_id");

        // 3. Hapus Materi Utama
        $stmt_del = $db_mapel->prepare("DELETE FROM " . tbl('materi') . " WHERE id = ?");
        $stmt_del->bind_param("i", $delete_id);
        $stmt_del->execute();

        $db_mapel->commit();
        $_SESSION['pesan_sukses'] = "Materi dan seluruh nilai siswa terkait berhasil direset!";
    } catch (Exception $e) {
        $db_mapel->rollback();
        $_SESSION['pesan_error'] = $e->getMessage();
    }
    header("Location: materi_list.php");
    exit();
}

// --- LOGIKA FILTER KELAS (DIPERBAIKI) ---
$kelas_filter_parts = [];
$kelas_params = [];
$kelas_types = "";

if (!empty($kelas_diajar_str)) {
    $kelas_array = array_map('trim', explode(',', $kelas_diajar_str));
    $placeholders = implode(',', array_fill(0, count($kelas_array), '?'));
    $kelas_filter_parts[] = "m.level_kategori IN ($placeholders)";
    foreach ($kelas_array as $k) {
        $kelas_params[] = $k;
        $kelas_types .= "s";
    }
}

// 1. Query Materi Pribadi (KEMBALIKAN KE SEMULA - TANPA GROUP BY JUDUL)
$filter_pribadi = $kelas_filter_parts;
$filter_pribadi[] = "m.id_guru = ?";
$params_pribadi = array_merge($kelas_params, [$user_id]);
$types_pribadi = $kelas_types . "i";

// Gunakan GROUP BY m.id (bukan judul) agar semua materi milik Anda muncul semua
$query_pribadi = "SELECT m.*, COUNT(s.id) AS jumlah_soal FROM " . tbl('materi') . " m
                  LEFT JOIN " . tbl('soal') . " s ON m.id = s.materi_id
                  WHERE " . implode(" AND ", $filter_pribadi) . "
                  GROUP BY m.id ORDER BY m.id DESC";

// 2. Query Materi Guru Lain (TETAP DISERDAHANAKAN)
$filter_adopsi = $kelas_filter_parts;
$filter_adopsi[] = "m.id_guru != ?";
$params_adopsi = array_merge($kelas_params, [$user_id]);
$types_adopsi = $kelas_types . "i";

// Di sini baru gunakan GROUP BY m.judul agar daftar guru lain tidak double
$query_adopsi = "SELECT m.*, COUNT(s.id) AS jumlah_soal FROM " . tbl('materi') . " m
                 LEFT JOIN " . tbl('soal') . " s ON m.id = s.materi_id
                 WHERE " . implode(" AND ", $filter_adopsi) . "
                 GROUP BY m.judul ORDER BY m.id DESC";

$materi_pribadi = execute_query($db_mapel, $query_pribadi, $params_pribadi, $types_pribadi);
$materi_adopsi = execute_query($db_mapel, $query_adopsi, $params_adopsi, $types_adopsi);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Materi | B.INDONESIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; }
        .card-custom { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark p-3">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><strong>B.INDONESIA | GURU</strong></a>
            <a href="../logout.php" class="btn btn-warning btn-sm fw-bold">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Daftar Materi</h2>
                <p class="text-muted">Mengelola materi untuk Kelas: <strong><?= $kelas_diajar_str ?></strong></p>
            </div>
            <a href="materi_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Materi</a>
        </div>

        <?= $pesan ?>

        <div class="card card-custom p-4 mb-5">
            <h4 class="text-success mb-3"><i class="fas fa-user-check"></i> Materi Pribadi Anda</h4>
            <?php if(empty($materi_pribadi)): ?>
                <div class="alert alert-light border text-center">Belum ada materi. <a href="materi_form.php">Buat sekarang</a></div>
            <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th><th>Kelas</th><th>Soal</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materi_pribadi as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['judul']) ?></strong></td>
                            <td><span class="badge bg-info">Kelas <?= $m['level_kategori'] ?></span></td>
                            <td><?= $m['jumlah_soal'] ?> Soal</td>
                            <td>
                                <a href="kuis_form.php?materi_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-question"></i></a>
                                <a href="materi_form.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $m['id'] ?>, '<?= $m['judul'] ?>')"><i class="fas fa-trash"></i></button>
                                 <a href="view_ifp.php?id=<?= $m['id']; ?>" target="_blank" class="btn btn-success btn-sm rounded-pill">
    <i class="fas fa-desktop me-1"></i> Mode IFP
</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card card-custom p-4">
            <h4 class="text-warning mb-3"><i class="fas fa-users"></i> Materi Guru Lain</h4>
            <?php if(empty($materi_adopsi)): ?>
                <div class="alert alert-light border text-center">Tidak ada materi dari guru lain di kelas Anda.</div>
            <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th><th>Kelas</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materi_adopsi as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['judul']) ?></td>
                            <td>Kelas <?= $a['level_kategori'] ?></td>
                            <td>

    <a href="materi_adopsi_proses.php?id_materi=<?= $a['id'] ?>"
       class="btn btn-sm btn-warning fw-bold"
       onclick="return confirm('Adopsi materi ini menjadi milik Anda? Anda akan bisa mengedit dan melihat progresnya sebagai materi pribadi.')">
        <i class="fas fa-copy"></i> Adopsi Jadi Milik Saya
    </a>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <form id="deleteForm" method="POST"><input type="hidden" name="delete_id" id="deleteId"></form>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmDelete(id, judul) {
            Swal.fire({
                title: 'Hapus Materi?',
                text: judul,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
    </script>
</body>
</html>