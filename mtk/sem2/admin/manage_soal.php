<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role

$current_user_role = $_SESSION['role'];

// Pengecekan Otorisasi Admin dan Guru
if ($current_user_role !== 'admin' && $current_user_role !== 'guru') {
    header("Location: ../login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'read';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$message = "";

if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

// ===============================================
// LOGIKA CRUD SOAL
// ===============================================

// Aksi CREATE/UPDATE (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action === 'add' || $action === 'edit')) {
    $id_materi = intval($_POST['id_materi']);
    $soal_text = trim($_POST['soal_text']);
    $pilihan_a = trim($_POST['pilihan_a']);
    $pilihan_b = trim($_POST['pilihan_b']);
    $pilihan_c = trim($_POST['pilihan_c']);
    $pilihan_d = trim($_POST['pilihan_d']);
    $jawaban_benar = trim($_POST['jawaban_benar']);

    if ($action === 'add') {
        $query = "INSERT INTO soal (id_materi, soal_text, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $db_mapel->prepare($query)) {
            $stmt->bind_param("issssss", $id_materi, $soal_text, $pilihan_a, $pilihan_b, $pilihan_c, $pilihan_d, $jawaban_benar);
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "<div class='alert alert-success'>✅ Soal baru berhasil ditambahkan!</div>";
            } else {
                $_SESSION['admin_message'] = "<div class='alert alert-danger'>❌ Gagal menambah soal. Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    } elseif ($action === 'edit' && $id > 0) {
        $query = "UPDATE soal SET id_materi=?, soal_text=?, pilihan_a=?, pilihan_b=?, pilihan_c=?, pilihan_d=?, jawaban_benar=? WHERE id=?";
        if ($stmt = $db_mapel->prepare($query)) {
            $stmt->bind_param("issssssi", $id_materi, $soal_text, $pilihan_a, $pilihan_b, $pilihan_c, $pilihan_d, $jawaban_benar, $id);
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "<div class='alert alert-success'>✅ Soal ID $id berhasil diperbarui!</div>";
            } else {
                $_SESSION['admin_message'] = "<div class='alert alert-danger'>❌ Gagal memperbarui soal. Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
    header("Location: manage_soal.php");
    exit();
}

// Aksi DELETE
if ($action === 'delete' && $id > 0) {
    $query = "DELETE FROM soal WHERE id = ?";
    if ($stmt = $db_mapel->prepare($query)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = "<div class='alert alert-success'>✅ Soal ID $id berhasil dihapus.</div>";
        } else {
            $_SESSION['admin_message'] = "<div class='alert alert-danger'>❌ Gagal menghapus soal.</div>";
        }
        $stmt->close();
    }
    header("Location: manage_soal.php");
    exit();
}

// Aksi EDIT (Fetch Data)
$soal_data = null;
if ($action === 'edit' && $id > 0) {
    $query_fetch = "SELECT * FROM soal WHERE id = ?";
    if ($stmt_fetch = $db_mapel->prepare($query_fetch)) {
        $stmt_fetch->bind_param("i", $id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $soal_data = $result_fetch->fetch_assoc();
        } else {
             $_SESSION['admin_message'] = "<div class='alert alert-danger'>Soal tidak ditemukan.</div>";
             header("Location: manage_soal.php");
             exit();
        }
        $stmt_fetch->close();
    }
}

// Ambil data materi untuk dropdown di form (TERINTEGRASI DENGAN level_kategori)
$materi_list = [];
$query_materi = "SELECT id, judul, level_kategori FROM materi ORDER BY judul ASC";
$result_materi = $db_mapel->query($query_materi);
if ($result_materi) {
    while ($materi = $result_materi->fetch_assoc()) {
        $materi_list[] = $materi;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Soal Quiz | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h1>Kelola Soal Quiz 🧠 (Akses Admin)</h1>
    <p><a href="dashboard.php" class="btn btn-secondary btn-sm">← Kembali ke Dashboard</a></p>
    <hr>

    <?php echo $message; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>

        <div class="card p-4 mb-4">
            <h3><?php echo ($action === 'add' ? 'Tambah Soal Baru' : 'Edit Soal ID: ' . $id); ?></h3>

            <form method="POST" action="manage_soal.php?action=<?php echo $action; ?><?php echo ($action === 'edit' ? '&id=' . $id : ''); ?>">

                <div class="mb-3">
                    <label for="id_materi" class="form-label">Terkait Bab Cerita (Level)</label>
                    <select class="form-select" id="id_materi" name="id_materi">
                        <option value="0" selected>-- (0) Soal Umum / Tidak Terkait Bab --</option>
                        <?php foreach ($materi_list as $materi): ?>
                            <option value="<?php echo $materi['id']; ?>"
                                <?php echo (isset($soal_data) && $soal_data['id_materi'] == $materi['id'] ? 'selected' : ''); ?>>
                                [<?php echo htmlspecialchars($materi['level_kategori']); ?>] <?php echo htmlspecialchars($materi['judul']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="soal_text" class="form-label">Teks Soal</label>
                    <textarea class="form-control" id="soal_text" name="soal_text" rows="4" required><?php echo isset($soal_data) ? htmlspecialchars($soal_data['soal_text']) : ''; ?></textarea>
                </div>

                <div class="row">
                    <?php
                    $pilihan_labels = ['a' => 'Pilihan A', 'b' => 'Pilihan B', 'c' => 'Pilihan C', 'd' => 'Pilihan D'];
                    foreach ($pilihan_labels as $key => $label):
                        $pilihan_key = 'pilihan_' . $key;
                    ?>
                    <div class="col-md-6 mb-3">
                        <label for="<?php echo $pilihan_key; ?>" class="form-label"><?php echo $label; ?></label>
                        <input type="text" class="form-control" id="<?php echo $pilihan_key; ?>" name="<?php echo $pilihan_key; ?>"
                               value="<?php echo isset($soal_data) ? htmlspecialchars($soal_data[$pilihan_key]) : ''; ?>" required>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4">
                    <label for="jawaban_benar" class="form-label">Kunci Jawaban Benar</label>
                    <select class="form-select" id="jawaban_benar" name="jawaban_benar" required>
                        <option value="" disabled selected>Pilih Kunci Jawaban</option>
                        <?php
                        $opsi_jawaban = ['A', 'B', 'C', 'D'];
                        foreach ($opsi_jawaban as $opsi):
                        ?>
                            <option value="<?php echo $opsi; ?>"
                                <?php echo (isset($soal_data) && $soal_data['jawaban_benar'] == $opsi ? 'selected' : ''); ?>>
                                <?php echo $opsi; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary btn-lg">Simpan Soal</button>
                    <a href="manage_soal.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>

    <?php else:
        // ===================================
        // TAMPILAN DAFTAR SOAL (ACTION=read)
        // ===================================

        $query_read = "SELECT s.*, m.judul AS nama_materi, m.level_kategori
                       FROM soal s
                       LEFT JOIN materi m ON s.id_materi = m.id
                       ORDER BY s.id DESC";
        $result_read = $db_mapel->query($query_read);
        $row_number = 1;
    ?>

        <p><a href="manage_soal.php?action=add" class="btn btn-success">➕ Tambah Soal Baru</a></p>
        <h3>Daftar Soal Tersedia</h3>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>ID</th>
                        <th>Soal</th>
                        <th>Bab/Level</th>
                        <th>Kunci</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_read && $result_read->num_rows > 0): ?>
                        <?php while($soal = $result_read->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row_number++; ?></td>
                            <td><?php echo $soal['id']; ?></td>
                            <td><?php echo nl2br(htmlspecialchars(substr($soal['soal_text'], 0, 100))) . (strlen($soal['soal_text']) > 100 ? '...' : ''); ?></td>
                            <td>
                                <?php if ($soal['nama_materi']): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($soal['level_kategori']); ?></span><br>
                                    <small><?php echo htmlspecialchars($soal['nama_materi']); ?></small>
                                <?php else: ?>
                                    Umum
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-success"><?php echo $soal['jawaban_benar']; ?></span></td>
                            <td>
                                <a href="manage_soal.php?action=edit&id=<?php echo $soal['id']; ?>" class="btn btn-warning btn-sm me-2">Edit</a>
                                <a href="manage_soal.php?action=delete&id=<?php echo $soal['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('YAKIN hapus soal ID <?php echo $soal['id']; ?>?')">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada soal yang ditambahkan.</td>
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