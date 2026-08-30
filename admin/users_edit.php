<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Proses Update Data
if (isset($_POST['update'])) {
    $nama = $_POST['nama_lengkap'];
    $user = $_POST['username'];
    $role = $_POST['role'];
    $kelas = $_POST['kelas'] ?? '';
    $id_guru = $_POST['id_guru'] ?? 0;

    $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, username=?, role=?, kelas=?, id_guru=? WHERE id=?");
    $stmt->bind_param("ssssii", $nama, $user, $role, $kelas, $id_guru, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui!'); window.location='users.php?role=$role';</script>";
    }
}

// Ambil data user yang akan diedit
$user_data = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();

// Ambil daftar guru untuk pilihan dropdown (hanya jika user yang diedit adalah siswa)
$daftar_guru = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Pengguna | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Edit Data Pengguna</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?php echo $user_data['nama_lengkap']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo $user_data['username']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="guru" <?php if($user_data['role']=='guru') echo 'selected'; ?>>Guru</option>
                                <option value="siswa" <?php if($user_data['role']=='siswa') echo 'selected'; ?>>Siswa</option>
                            </select>
                        </div>

                        <?php if($user_data['role'] == 'siswa' || $user_data['role'] == 'guru'): ?>
<div class="mb-3">
    <label class="form-label">Kelas</label>
    <input type="text" name="kelas" class="form-control" value="<?php echo $user_data['kelas']; ?>">
</div>
                        <div class="mb-3">
                            <label class="form-label">Pilih Guru Bimbingan</label>
                            <select name="id_guru" class="form-select">
                                <option value="0">-- Pilih Guru --</option>
                                <?php while($g = $daftar_guru->fetch_assoc()): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php if($user_data['id_guru'] == $g['id']) echo 'selected'; ?>>
                                        <?php echo $g['nama_lengkap']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Penting: Siswa harus punya guru agar laporannya muncul di dashboard guru.</small>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between">
                            <a href="users.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>