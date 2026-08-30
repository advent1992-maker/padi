<?php
// File: admin/view_materi_admin.php - Halaman Peninjauan Konten Materi oleh Admin
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 1. Validasi Input
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">ID Materi atau ID Guru tidak valid!</div>';
    header("Location: progres_guru.php");
    exit();
}

$materi_id = $_GET['id'];
$guru_id = $_GET['user_id'];
$materi_data = null;
$guru_nama = 'Tidak Diketahui';
$materi_konten = ''; // Variabel untuk menampung konten file

// 2. Ambil detail Materi dan data Guru terkait
$stmt = $db_mapel->prepare("
    SELECT m.*, u.nama_lengkap
    FROM materi m
    JOIN users u ON m.id_guru = u.id
    WHERE m.id = ? AND m.id_guru = ?
");
$stmt->bind_param("ii", $materi_id, $guru_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $materi_data = $result->fetch_assoc();
    $guru_nama = $materi_data['nama_lengkap'];
}
$stmt->close();

if (!$materi_data) {
    $_SESSION['progres_guru_message'] = '<div class="alert alert-danger">Materi tidak ditemukan!</div>';
    header("Location: detail_progres_guru.php?user_id=$guru_id");
    exit();
}

// =========================================================================
// !!! PERBAIKAN UTAMA: Mengganti jalur pencarian file !!!
// Menggunakan jalur '../materi/' karena folder materi berada di root utama.
// =========================================================================
if (!empty($materi_data['file_path'])) {
    // Jalur absolut ke file konten, relatif dari file 'admin/'
    // Jika 'admin/' berada di root proyek, maka '../materi/' mengarah ke 'root/materi/'
    $file_path_full = '../materi/' . basename($materi_data['file_path']);

    // Cek apakah file ada dan dapat dibaca
    if (file_exists($file_path_full)) {
        // Ambil seluruh isi file (konten HTML)
        $materi_konten = file_get_contents($file_path_full);
    } else {
        // Pesan jika file tidak ditemukan di jalur yang diasumsikan
        $materi_konten = '<div class="alert alert-warning text-center">PERINGATAN: File konten (' . htmlspecialchars($materi_data['file_path']) . ') tidak ditemukan di server pada jalur ' . htmlspecialchars($file_path_full) . '!</div>';
    }
}
// =========================================================================

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Materi: <?php echo htmlspecialchars($materi_data['judul']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya dasar untuk simulasi tampilan materi siswa */
        .materi-container {
            padding: 30px;
            background-color: #f8f9fa; /* Light gray background */
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .materi-content h1, .materi-content h2 {
            border-bottom: 2px solid #ccc;
            padding-bottom: 5px;
            margin-top: 20px;
        }
        .materi-content p {
            line-height: 1.8;
            margin-bottom: 15px;
            text-align: justify;
        }
        .materi-content img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            margin: 15px 0;
            display: block;
        }
        /* Style untuk menampung konten file HTML/PHP */
        .materi-preview {
            border: 1px dashed #0d6efd;
            padding: 20px;
            background-color: #fff;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1><i class="fas fa-search me-2"></i> Review Materi</h1>
        <a href="progres_detail_guru.php?user_id=<?php echo $guru_id; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> Kembali ke Detail Guru</a>
    </div>

    <!-- Panel Informasi dan Aksi Admin -->
    <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Panel Administratif: Tindakan Admin</h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <p class="mb-1"><strong>Judul Materi:</strong> <?php echo htmlspecialchars($materi_data['judul']); ?></p>
                    <p class="mb-1"><strong>Dibuat oleh:</strong> <?php echo htmlspecialchars($guru_nama); ?></p>
                    <p class="mb-1"><strong>Tanggal Dibuat:</strong> <?php echo date('d M Y H:i:s', strtotime($materi_data['created_at'])); ?></p>
                    <p class="mb-1"><strong>File Konten:</strong>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($materi_data['file_path']); ?></span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <!-- Tombol Hapus Materi -->
                    <a href="hapus_konten.php?type=materi&id=<?php echo $materi_id; ?>&user_id=<?php echo $guru_id; ?>"
                       class="btn btn-danger btn-lg"
                       onclick="return confirm('PERINGATAN! Apakah Anda yakin ingin menghapus materi ini? Aksi ini akan menghapusnya dari akun siswa juga.');">
                       <i class="fas fa-trash-alt me-2"></i> Hapus Materi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tampilan Konten Materi (Simulasi Tampilan Siswa) -->
    <div class="card shadow-lg mb-5">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-book-open me-2"></i> Konten Materi (Tampilan Siswa)</h5>
        </div>
        <div class="card-body p-md-5">
            <div class="materi-container">
                <h1 class="text-center text-primary mb-5"><?php echo htmlspecialchars($materi_data['judul']); ?></h1>
                <hr>

                <div class="materi-content materi-preview">
                    <?php
                    // Tampilkan konten yang berhasil dimuat dari file
                    if (!empty($materi_konten)) {
                        echo $materi_konten;
                    } else {
                        // Konten file tidak ditemukan atau kolom file_path kosong
                        echo '<div class="alert alert-danger text-center">Konten materi kosong atau file tidak dapat dimuat. File Path: ' . htmlspecialchars($materi_data['file_path'] ?? 'KOSONG') . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>