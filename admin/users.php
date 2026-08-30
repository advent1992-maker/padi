<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// Proteksi Halaman: Hanya Admin yang bisa masuk
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 1. Ambil Parameter dari URL
$role_aktif = $_GET['role'] ?? 'siswa';
$filter_kelas = $_GET['f_kelas'] ?? '';
$filter_guru = $_GET['f_guru'] ?? '';
$search = $_GET['q'] ?? ''; // Parameter pencarian baru

// 2. Logika Query untuk ROLE SISWA
if ($role_aktif === 'siswa') {
    $query = "SELECT u.*, g.nama_lengkap as nama_guru
              FROM users u
              LEFT JOIN users g ON u.id_guru = g.id
              WHERE u.role = 'siswa'";

    if ($filter_kelas != '') {
        $query .= " AND u.kelas = '" . $conn->real_escape_string($filter_kelas) . "'";
    }
    if ($filter_guru != '') {
        $query .= " AND u.id_guru = '" . $conn->real_escape_string($filter_guru) . "'";
    }
    if ($search != '') {
        $query .= " AND (u.nama_lengkap LIKE '%" . $conn->real_escape_string($search) . "%' 
                     OR u.username LIKE '%" . $conn->real_escape_string($search) . "%')";
    }
    // Mengutamakan is_verified = 0 agar pendaftaran baru muncul paling atas
    $query .= " ORDER BY u.is_verified ASC, u.kelas ASC, u.nama_lengkap ASC";
}
// 3. Logika Query untuk ROLE GURU
else {
    $query = "SELECT * FROM users WHERE role = 'guru'";
    if ($filter_kelas != '') {
        $query .= " AND (kelas = '$filter_kelas' OR FIND_IN_SET('$filter_kelas', kelas))";
    }
    if ($search != '') {
        $query .= " AND (nama_lengkap LIKE '%" . $conn->real_escape_string($search) . "%' 
                     OR username LIKE '%" . $conn->real_escape_string($search) . "%')";
    }
    $query .= " ORDER BY nama_lengkap ASC";
}

$result = $conn->query($query);

// 4. Hitung Jumlah yang Belum Diverifikasi (Untuk Notifikasi Judul)
$count_pending = $conn->query("SELECT COUNT(id) as total FROM users WHERE role = 'siswa' AND is_verified = 0")->fetch_assoc();

// 5. Ambil Daftar Guru untuk Dropdown Filter
$list_guru = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna | Portal Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 12px; }
        .table thead { background-color: #f1f3f5; }
        .btn-add { border-radius: 8px; font-weight: 600; }
        .badge-kelas { font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; }
        .pending-row { background-color: #fff8e1; } /* Warna kuning muda untuk baris yang belum diverifikasi */
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="dashboard.php" class="text-decoration-none text-muted mb-2 d-block">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
            <h2 class="fw-bold">
                Data Pengguna: <span class="text-primary"><?php echo ucfirst($role_aktif); ?></span>
                <?php if ($role_aktif === 'siswa' && $count_pending['total'] > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.9rem;">
                        <?php echo $count_pending['total']; ?> Baru
                    </span>
                <?php endif; ?>
            </h2>
        </div>
        <div class="d-flex gap-2">
            <a href="users.php?role=guru" class="btn <?php echo $role_aktif == 'guru' ? 'btn-primary' : 'btn-outline-primary'; ?>">Data Guru</a>
            <a href="users.php?role=siswa" class="btn <?php echo $role_aktif == 'siswa' ? 'btn-success' : 'btn-outline-success'; ?>">Data Siswa</a>
            <a href="users_tambah.php" class="btn btn-dark btn-add"><i class="fas fa-plus"></i> Tambah</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="role" value="<?php echo $role_aktif; ?>">
                
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Cari Nama / Username</label>
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Ketik nama atau username..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Filter Kelas</label>
                    <select name="f_kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Semua Kelas --</option>
                        <option value="4" <?php if($filter_kelas == '4') echo 'selected'; ?>>Kelas 4</option>
                        <option value="5" <?php if($filter_kelas == '5') echo 'selected'; ?>>Kelas 5</option>
                        <option value="6" <?php if($filter_kelas == '6') echo 'selected'; ?>>Kelas 6</option>
                    </select>
                </div>

                <?php if ($role_aktif === 'siswa'): ?>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Filter Guru Pembimbing</label>
                    <select name="f_guru" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Semua Guru --</option>
                        <?php 
                        $list_guru->data_seek(0); // Reset pointer
                        while($g = $list_guru->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $g['id']; ?>" <?php if($filter_guru == $g['id']) echo 'selected'; ?>>
                                <?php echo $g['nama_lengkap']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-2 d-flex align-items-end">
                    <a href="users.php?role=<?php echo $role_aktif; ?>" class="btn btn-light border w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" width="5%">No</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th class="text-center">Kelas</th>
                            <?php if($role_aktif == 'siswa'): ?>
                                <th>Status / Guru Bimbingan</th>
                            <?php else: ?>
                                <th>ID Guru (System)</th>
                            <?php endif; ?>
                            <th class="text-center" width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                // Tandai baris jika belum diverifikasi
                                $row_class = ($role_aktif == 'siswa' && $row['is_verified'] == 0) ? 'pending-row' : '';
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td class="ps-4"><?php echo $no++; ?></td>
                            <td>
                                <strong><?php echo $row['nama_lengkap']; ?></strong>
                                <?php if($role_aktif == 'siswa' && $row['is_verified'] == 0): ?>
                                    <span class="badge bg-warning text-dark ms-2 small" style="font-size: 0.65rem;">NEW</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo $row['username']; ?></td>
                            <td class="text-center">
                                <span class="badge bg-info badge-kelas text-dark">Kelas <?php echo $row['kelas']; ?></span>
                            </td>
                            <td>
                                <?php if($role_aktif == 'siswa'): ?>
                                    <?php if($row['is_verified'] == 1): ?>
                                        <span class="text-success small fw-bold">
                                            <i class="fas fa-check-circle"></i> <?php echo $row['nama_guru'] ?? '<span class="text-danger">Belum Diatur</span>'; ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="verifikasi.php?approve=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary py-0 px-2 shadow-sm" style="font-size: 0.75rem;">
                                            <i class="fas fa-user-check"></i> Verifikasi
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <code class="text-pink">ID: <?php echo $row['id']; ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="users_password.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white" title="Ganti Password">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="users_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="users_hapus.php?id=<?php echo $row['id']; ?>&role=<?php echo $row['role']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('PERINGATAN: Menghapus <?php echo $row['role']; ?> akan menghapus seluruh data terkait. Lanjutkan?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-search mb-3 d-block fa-2x"></i>
                                Data tidak ditemukan.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>