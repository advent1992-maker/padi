<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role

$current_user_role = $_SESSION['role'];

// Pengecekan Otorisasi Guru dan Admin (hanya peran ini yang boleh mengelola materi)
if ($current_user_role !== 'guru' && $current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'read';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$message = "";

// Menggunakan sesi pesan untuk guru
if (isset($_SESSION['guru_message'])) {
    $message = $_SESSION['guru_message'];
    unset($_SESSION['guru_message']);
}

// ===============================================
// LOGIKA CRUD MATERI
// ===============================================

// Aksi CREATE/UPDATE (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action === 'add' || $action === 'edit')) {
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $file_path = trim($_POST['file_path']);
    $level_kategori = trim($_POST['level_kategori']);

    if ($action === 'add') {
        // Aksi CREATE
        $query = "INSERT INTO materi (judul, deskripsi, file_path, level_kategori) VALUES (?, ?, ?, ?)";
        if ($stmt = $db_mapel->prepare($query)) {
            $stmt->bind_param("ssss", $judul, $deskripsi, $file_path, $level_kategori);
            if ($stmt->execute()) {
                $_SESSION['guru_message'] = "<div class='alert alert-success'>✅ Materi <b>$judul</b> berhasil ditambahkan!</div>";
            } else {
                $_SESSION['guru_message'] = "<div class='alert alert-danger'>❌ Gagal menambah materi. Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    } elseif ($action === 'edit' && $id > 0) {
        // Aksi UPDATE
        $query = "UPDATE materi SET judul=?, deskripsi=?, file_path=?, level_kategori=? WHERE id=?";
        if ($stmt = $db_mapel->prepare($query)) {
            $stmt->bind_param("ssssi", $judul, $deskripsi, $file_path, $level_kategori, $id);
            if ($stmt->execute()) {
                $_SESSION['guru_message'] = "<div class='alert alert-success'>✅ Materi <b>$judul</b> berhasil diperbarui!</div>";
            } else {
                $_SESSION['guru_message'] = "<div class='alert alert-danger'>❌ Gagal memperbarui materi. Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
    // Redirect ke halaman kelola_materi.php di folder guru
    header("Location: kelola_materi.php");
    exit();
}

// Aksi DELETE
if ($action === 'delete' && $id > 0) {
    // Hapus Soal terkait dan Materi (Menggunakan Transaction)
    $db_mapel->begin_transaction();
    try {
        // Hapus Soal yang terkait
        $stmt_soal = $db_mapel->prepare("DELETE FROM soal WHERE id_materi = ?");
        $stmt_soal->bind_param("i", $id);
        $stmt_soal->execute();
        $stmt_soal->close();

        // Hapus Materi
        $stmt_materi = $db_mapel->prepare("DELETE FROM materi WHERE id = ?");
        $stmt_materi->bind_param("i", $id);
        $stmt_materi->execute();
        $stmt_materi->close();

        $db_mapel->commit();
        $_SESSION['guru_message'] = "<div class='alert alert-success'>✅ Materi ID $id dan soal terkait berhasil dihapus!</div>";
    } catch (Exception $e) {
        $db_mapel->rollback();
        $_SESSION['guru_message'] = "<div class='alert alert-danger'>❌ Gagal menghapus materi.</div>";
    }

    header("Location: kelola_materi.php");
    exit();
}

// Aksi EDIT (Fetch Data)
$materi_data = null;
if ($action === 'edit' && $id > 0) {
    $query_fetch = "SELECT * FROM materi WHERE id = ?";
    if ($stmt_fetch = $db_mapel->prepare($query_fetch)) {
        $stmt_fetch->bind_param("i", $id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $materi_data = $result_fetch->fetch_assoc();
        } else {
             $_SESSION['guru_message'] = "<div class='alert alert-danger'>Materi tidak ditemukan.</div>";
             header("Location: kelola_materi.php");
             exit();
        }
        $stmt_fetch->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Materi Mathfiction | Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h1>Kelola Materi Cerita 📚 (Akses Guru)</h1>
    <p><a href="dashboard.php" class="btn btn-secondary btn-sm">← Kembali ke Dashboard Guru</a></p>
    <hr>

    <?php echo $message; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>

        <div class="card p-4 mb-4">
            <h3><?php echo ($action === 'add' ? 'Tambah Bab Cerita Baru' : 'Edit Materi ID: ' . $id); ?></h3>

            <form method="POST" action="kelola_materi.php?action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $id : ''); ?>">

                <div class="mb-3">
                    <label for="judul" class="form-label">Judul Bab/Materi</label>
                    <input type="text" class="form-control" id="judul" name="judul"
                           value="<?php echo isset($materi_data) ? htmlspecialchars($materi_data['judul']) : ''; ?>" required>
                </div>

                <div class="mb-3">
                    <label for="level_kategori" class="form-label">Level/Kategori Cerita</label>
                    <input type="text" class="form-control" id="level_kategori" name="level_kategori"
                           value="<?php echo isset($materi_data) ? htmlspecialchars($materi_data['level_kategori']) : ''; ?>"
                           placeholder="Contoh: Angka Ajaib - Level 1" required>
                </div>

                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi Singkat (Sinopsis)</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="2" required><?php echo isset($materi_data) ? htmlspecialchars($materi_data['deskripsi']) : ''; ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="file_path" class="form-label">Path File Konten HTML/PHP</label>
                    <input type="text" class="form-control" id="file_path" name="file_path"
                           value="<?php echo isset($materi_data) ? htmlspecialchars($materi_data['file_path']) : ''; ?>"
                           placeholder="Contoh: ../konten/bab_1_penjumlahan.html" required>
                    <small class="form-text text-muted">Jalur relatif file dari folder 'guru'. Pastikan file berada di folder `konten/`.</small>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary btn-lg">Simpan Materi</button>
                    <a href="kelola_materi.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>

    <?php else:
        // ===================================
        // TAMPILAN DAFTAR MATERI (ACTION=read)
        // ===================================

        $query_read = "SELECT id, judul, level_kategori, deskripsi, file_path FROM materi ORDER BY id ASC";
        $result_read = $db_mapel->query($query_read);
        $row_number = 1;
    ?>

        <p><a href="kelola_materi.php?action=add" class="btn btn-success">➕ Tambah Bab Cerita Baru</a></p>
        <h3>Daftar Bab Cerita (Materi Mathfiction)</h3>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>ID</th>
                        <th>Judul Materi</th>
                        <th>Level</th>
                        <th>Sinopsis</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_read && $result_read->num_rows > 0): ?>
                        <?php while($materi = $result_read->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row_number++; ?></td>
                            <td><?php echo $materi['id']; ?></td>
                            <td>**<?php echo htmlspecialchars($materi['judul']); ?>**</td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($materi['level_kategori']); ?></span></td>
                            <td><?php echo htmlspecialchars(substr($materi['deskripsi'], 0, 70)) . (strlen($materi['deskripsi']) > 70 ? '...' : ''); ?></td>
                            <td>
                                <a href="kelola_materi.php?action=edit&id=<?php echo $materi['id']; ?>" class="btn btn-warning btn-sm me-2">Edit</a>
                                <a href="kelola_materi.php?action=delete&id=<?php echo $materi['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('YAKIN hapus materi dan semua soal terkait?')">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada materi cerita yang ditambahkan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>