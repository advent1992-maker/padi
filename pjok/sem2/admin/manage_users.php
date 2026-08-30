<?php
require_once '../config/koneksi.php';    // koneksi database
require_once '../config/session.php';    // session config
require_once '../config/auth_check.php'; // cek user login & role

// Ambil ID user yang sedang login
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// 1. Pengecekan Otorisasi Admin
if ($current_user_role !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'read';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0; 
$message = "";

// Ambil pesan notifikasi dari session
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

// ===============================================
// LOGIKA CRUD
// ===============================================

// Aksi DELETE
if ($action === 'delete' && $id > 0) {
    if ($id == $current_user_id) {
        $_SESSION['admin_message'] = "<div class='alert alert-danger'>Anda tidak dapat menghapus akun Anda sendiri!</div>";
    } else {
        $query = "DELETE FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("i", $id); 
            if ($stmt->execute()) {
                $_SESSION['admin_message'] = "<div class='alert alert-success'>Akun ID $id berhasil dihapus.</div>";
            } else {
                $_SESSION['admin_message'] = "<div class='alert alert-danger'>Gagal menghapus akun.</div>";
            }
            $stmt->close();
        }
    }
    header("Location: manage_users.php");
    exit();
}

// Aksi EDIT/UPDATE (POST) - MENGGUNAKAN DUA QUERY TERPISAH
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'edit' && $id > 0) {
    // Sanitasi dan ambil data
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    
    $kelas = ($role === 'siswa') ? (isset($_POST['kelas']) && !empty($_POST['kelas']) ? intval($_POST['kelas']) : NULL) : NULL;
    
    $new_password = $_POST['password']; 
    $success = false;
    $error_message = "";

    // 1. UPDATE DATA PENGGUNA (NON-PASSWORD)
    $query_data = "UPDATE users SET username = ?, nama_lengkap = ?, email = ?, role = ?, kelas = ? WHERE id = ?";
    
    if ($stmt_data = $conn->prepare($query_data)) {
        // Tipe binding: ssssi (5 string/integer/null) + i (ID)
        $stmt_data->bind_param("ssssii", $username, $nama_lengkap, $email, $role, $kelas, $id);
        
        if ($stmt_data->execute()) {
            $success = true;
        } else {
            $error_message = "Gagal update data pengguna: " . $stmt_data->error;
            $success = false;
        }
        $stmt_data->close();
    } else {
        $error_message = "Error sistem (data): Gagal mempersiapkan statement UPDATE.";
        $success = false;
    }


    // 2. UPDATE PASSWORD (JIKA DIISI)
    if ($success && !empty($new_password)) {
        $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $query_pass = "UPDATE users SET password = ? WHERE id = ?";

        if ($stmt_pass = $conn->prepare($query_pass)) {
            $stmt_pass->bind_param("si", $new_password_hashed, $id);
            
            if (!$stmt_pass->execute()) {
                $error_message = "Gagal update password: " . $stmt_pass->error;
                $success = false; 
            }
            $stmt_pass->close();
        } else {
            $error_message = "Error sistem (password): Gagal mempersiapkan statement UPDATE.";
            $success = false;
        }
    }


    // 3. SET PESAN AKHIR
    if ($success) {
        $_SESSION['admin_message'] = "<div class='alert alert-success'>Data pengguna **$nama_lengkap** berhasil diperbarui!</div>";
    } else {
        $_SESSION['admin_message'] = "<div class='alert alert-danger'>Gagal memperbarui data. " . $error_message . "</div>";
    }

    header("Location: manage_users.php");
    exit();
}


// Aksi EDIT (untuk menampilkan form dengan data)
$user_data = null;
if ($action === 'edit' && $id > 0) {
    $query_fetch = "SELECT id, username, nama_lengkap, email, role, kelas FROM users WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($query_fetch)) {
        $stmt_fetch->bind_param("i", $id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $user_data = $result_fetch->fetch_assoc();
        } else {
             $_SESSION['admin_message'] = "<div class='alert alert-danger'>Pengguna tidak ditemukan.</div>";
             header("Location: manage_users.php");
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
    <title><?php echo ($action === 'edit' ? 'Edit Pengguna' : 'Kelola Pengguna'); ?> | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h1>Kelola Akun Pengguna (Admin View) 👥</h1>
    <p><a href="dashboard.php" class="btn btn-secondary btn-sm">← Kembali ke Dashboard</a></p>
    <hr>
    
    <?php echo $message; ?>

    <?php if ($action === 'edit'): ?>
        
        <div class="card p-4 mb-4">
            <h3>Edit Pengguna: <?php echo htmlspecialchars($user_data['nama_lengkap']); ?></h3>
            
            <form method="POST" action="manage_users.php?action=edit&id=<?php echo $id; ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                           value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Peran</label>
                    <select class="form-select" id="role" name="role" required onchange="toggleKelasForm(this.value)">
                        <option value="siswa" <?php echo ($user_data['role'] == 'siswa' ? 'selected' : ''); ?>>Siswa</option>
                        <option value="guru" <?php echo ($user_data['role'] == 'guru' ? 'selected' : ''); ?>>Guru</option>
                        <option value="admin" <?php echo ($user_data['role'] == 'admin' ? 'selected' : ''); ?>>Admin</option>
                    </select>
                </div>
                
                <div class="mb-3" id="kelas-group-form" style="display: <?php echo ($user_data['role'] == 'guru' || $user_data['role'] == 'admin' ? 'none' : 'block'); ?>;">
                    <label for="kelas" class="form-label">Kelas (Khusus Siswa)</label>
                    <select class="form-select" id="kelas" name="kelas">
                        <option value="" disabled <?php echo ($user_data['kelas'] === null ? 'selected' : ''); ?>>Pilih Kelas</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (intval($user_data['kelas']) == $i ? 'selected' : ''); ?>>Kelas <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Ganti Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Isi password baru jika ingin mengganti">
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary btn-lg">Simpan Perubahan</button>
                    <a href="manage_users.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>

        <script>
            function toggleKelasForm(role) {
                var kelasGroup = document.getElementById('kelas-group-form');
                if (role === 'siswa') {
                    kelasGroup.style.display = 'block';
                } else {
                    kelasGroup.style.display = 'none';
                }
            }
        </script>

    <?php else: 
        // ===================================
        // TAMPILAN DAFTAR PENGGUNA (ACTION=read)
        // ===================================
        
        // Ambil semua pengguna terverifikasi (is_verified = 1)
        $query_read = "SELECT id, username, nama_lengkap, email, role, kelas, is_verified 
                       FROM users 
                       WHERE is_verified = 1
                       ORDER BY role, nama_lengkap ASC";
        $result_read = $conn->query($query_read);
        $row_number = 1; // Untuk nomor urut visual
    ?>
    
        <h3>Daftar Pengguna Aktif</h3>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Nama Lengkap</th>
                        <th>Peran</th>
                        <th>Email</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_read->num_rows > 0): ?>
                        <?php while($user = $result_read->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row_number++; ?></td>
                            <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                            <td>
                                <?php 
                                    $badge_class = ($user['role'] == 'admin' ? 'danger' : ($user['role'] == 'guru' ? 'info' : 'success'));
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo strtoupper($user['role']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ($user['kelas'] ? 'Kelas ' . $user['kelas'] : '-'); ?></td>
                            <td>
                                <span class="badge bg-success">Terverifikasi</span>
                            </td>
                            <td>
                                <a href="manage_users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm me-2">Edit</a>
                                
                                <?php if ($user['id'] != $current_user_id): // Admin tidak bisa menghapus akunnya sendiri ?>
                                    <a href="manage_users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('YAKIN hapus akun <?php echo htmlspecialchars($user['nama_lengkap']); ?>?')">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Belum ada pengguna aktif yang terverifikasi.</td>
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