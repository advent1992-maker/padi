<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pengecekan Otorisasi: Hanya peran 'admin' yang boleh akses
if ($current_user_role !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// --- 1. Ambil Data Filter Options (Kelas dan Guru) ---

// Ambil semua daftar kelas unik dari siswa
$kelas_list = [];
$query_kelas = "SELECT DISTINCT kelas FROM users WHERE role = 'siswa' AND kelas IS NOT NULL ORDER BY kelas ASC";
$result_kelas = $db_mapel->query($query_kelas);
while ($row = $result_kelas->fetch_assoc()) {
    $kelas_list[] = htmlspecialchars($row['kelas']);
}

// Ambil semua daftar Guru. Kolom 'kelas' digunakan untuk filter dinamis di JavaScript.
$guru_list = [];
// *** KOREKSI: Menggunakan kolom 'kelas' untuk guru ***
$query_guru = "SELECT id, nama_lengkap, kelas FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC";
$result_guru = $db_mapel->query($query_guru);
while ($row = $result_guru->fetch_assoc()) {
    $guru_list[] = $row;
}

// --- 2. Penerapan Filter ---

$filter_kelas = $_GET['filter_kelas'] ?? '';
$filter_guru = $_GET['filter_guru'] ?? '';

// Konstruksi WHERE clause
$where_clauses = ["u.role = 'siswa'"];

if (!empty($filter_kelas)) {
    $where_clauses[] = "u.kelas = '" . mysqli_real_escape_string($db_mapel, $filter_kelas) . "'";
}

if (!empty($filter_guru)) {
    // Menggunakan u.id_guru
    $where_clauses[] = "u.id_guru = '" . mysqli_real_escape_string($db_mapel, $filter_guru) . "'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";


// --- 3. Ambil Data Siswa yang Difilter ---
$query_siswa = "
    SELECT
        u.id,
        u.username,
        u.nama_lengkap,
        u.email,
        u.created_at,
        u.is_verified,
        u.kelas,
        g.nama_lengkap AS nama_guru
    FROM users u
    -- Menggunakan u.id_guru untuk JOIN
    LEFT JOIN users g ON u.id_guru = g.id AND g.role = 'guru'
    {$where_sql}
    ORDER BY u.nama_lengkap ASC";

$result_siswa = $db_mapel->query($query_siswa);

// Ambil pesan notifikasi (jika ada)
$message = "";
if (isset($_SESSION['progres_siswa_message'])) {
    $message = '<div class="alert alert-info">' . $_SESSION['progres_siswa_message'] . '</div>';
    unset($_SESSION['progres_siswa_message']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progres Siswa | Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-chart-line me-2"></i> Progres Siswa</h1>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
    </div>

    <?php echo $message; ?>

    <p class="text-muted">
        Daftar semua siswa di sistem. Klik tombol 'Lihat Progres' untuk melihat detail nilai dan kemajuan mereka.
    </p>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="fas fa-filter me-2"></i> Filter Data Siswa</h5>
            <form method="GET" action="progres_siswa.php" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="filter_kelas" class="form-label">Filter Kelas</label>
                        <select id="filter_kelas" name="filter_kelas" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas ?>" <?= ($filter_kelas == $kelas) ? 'selected' : '' ?>>
                                    Kelas <?= $kelas ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="filter_guru" class="form-label">Filter Guru Pembimbing</label>
                        <select id="filter_guru" name="filter_guru" class="form-select">
                            <option value="">Semua Guru</option>
                            </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-search me-1"></i> Terapkan Filter</button>
                        <a href="progres_siswa.php" class="btn btn-outline-secondary"><i class="fas fa-undo"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped">
            <thead class="table-success">
                <tr>
                    <th>No.</th> <th>Nama Lengkap</th>
                    <th>Kelas</th>
                    <th>Guru Pembimbing</th>
                    <th>Email</th>
                    <th>Status Akun</th>
                    <th>Tanggal Daftar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_siswa->num_rows > 0): ?>
                    <?php $no_urut = 1; // Inisialisasi nomor urut
                    while ($user = $result_siswa->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $no_urut++; ?></td> <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($user['kelas'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['nama_guru'] ?? 'Belum Ditentukan'); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($user['is_verified'] == 1 ? 'success' : 'warning'); ?>">
                                    <?php echo ($user['is_verified'] == 1 ? 'Terverifikasi' : 'Menunggu Verifikasi'); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="progres_detail_siswa.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-eye me-1"></i> Lihat Progres
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada akun siswa terdaftar yang sesuai dengan filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Data guru yang diambil dari PHP
    const guruData = <?= json_encode($guru_list) ?>;
    const filterKelas = document.getElementById('filter_kelas');
    const filterGuru = document.getElementById('filter_guru');
    const currentFilterGuru = '<?= $filter_guru ?>'; // Nilai guru yang sedang aktif

    // Fungsi untuk memfilter dan mengisi dropdown Guru
    function updateGuruFilter() {
        const selectedKelas = filterKelas.value;

        // Kosongkan dan tambahkan opsi default
        filterGuru.innerHTML = '<option value="">Semua Guru</option>';

        if (selectedKelas === "") {
            // Jika "Semua Kelas" dipilih, tampilkan semua guru
            guruData.forEach(guru => {
                const option = document.createElement('option');
                option.value = guru.id;
                option.textContent = guru.nama_lengkap;
                // Pertahankan nilai yang sedang difilter
                if (guru.id == currentFilterGuru) {
                    option.selected = true;
                }
                filterGuru.appendChild(option);
            });
            return;
        }

        // Filter guru yang mengajar kelas yang dipilih
        guruData.forEach(guru => {
            // *** KOREKSI: Menggunakan kolom 'kelas' (bukan mengajar_kelas) ***
            // Asumsi: kolom 'kelas' menyimpan string seperti '5,6,7' atau hanya '5'
            const mengajarKelasString = guru.kelas || '';
            const mengajarKelasArray = mengajarKelasString.split(',').map(k => k.trim());

            if (mengajarKelasArray.includes(selectedKelas)) {
                const option = document.createElement('option');
                option.value = guru.id;
                option.textContent = guru.nama_lengkap;
                // Pertahankan nilai yang sedang difilter
                if (guru.id == currentFilterGuru) {
                    option.selected = true;
                }
                filterGuru.appendChild(option);
            }
        });

        // Cek jika guru yang sudah dipilih tidak lagi valid setelah perubahan kelas
        let found = false;
        for (let i = 0; i < filterGuru.options.length; i++) {
            if (filterGuru.options[i].value === currentFilterGuru && filterGuru.options[i].value !== '') {
                found = true;
                break;
            }
        }

        // Jika guru yang aktif sebelumnya tidak ditemukan di daftar baru (kecuali 'Semua Guru'), reset ke 'Semua Guru'
        if (currentFilterGuru && !found) {
            filterGuru.value = '';
        }

    }

    // Panggil fungsi saat DOM selesai dimuat untuk mengisi nilai awal
    updateGuruFilter();

    // Tambahkan event listener untuk memanggil fungsi saat filter kelas berubah
    filterKelas.addEventListener('change', function() {
        // Panggil fungsi pengisian dropdown
        updateGuruFilter();

        // Opsional: Jika ingin otomatis submit saat kelas diubah (tanpa perlu klik Terapkan)
        // document.getElementById('filterForm').submit();
    });
</script>
</body>
</html>