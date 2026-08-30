<?php
require_once 'config/koneksi.php';
require_once 'config/session.php';

// Proteksi: Hanya siswa yang bisa memberi penilaian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas = $_SESSION['kelas'] ?? '-';
$kode_app = 'PADI_PORTAL'; // Penanda bahwa ini penilaian aplikasi utama

// Instrumen Penilaian untuk Portal PADI (Umum)
$instrumen_padi = [
    'A. Kualitas Antarmuka (UI)' => [
        'Tampilan menu utama PADI mudah dipahami.',
        'Ikon dan warna aplikasi terlihat menarik dan ceria.',
        'Tulisan pada aplikasi mudah dibaca dengan jelas.'
    ],
    'B. Kemudahan Akses (UX)' => [
        'Saya mudah menemukan mata pelajaran yang ingin dipelajari.',
        'Proses pindah dari portal ke materi pelajaran sangat cepat.',
        'Aplikasi ini berjalan lancar tanpa kendala teknis.'
    ]
];

$pesan_status = '';
$sudah_submit = false;

// Cek apakah siswa sudah pernah memberikan penilaian untuk portal
$query_check = "SELECT COUNT(*) FROM hasil_uji_siswa WHERE id_user = ? AND kode_aplikasi = ?";
$stmt_check = $conn->prepare($query_check);
$stmt_check->bind_param("is", $user_id, $kode_app);
$stmt_check->execute();
$stmt_check->bind_result($count_submissions);
$stmt_check->fetch();
$stmt_check->close();

if ($count_submissions > 0) {
    $sudah_submit = true;
    $pesan_status = '<div class="alert alert-info text-center">Anda sudah memberikan penilaian untuk portal PADI. Terima kasih!</div>';
}

// ... bagian atas sama dengan kode sebelumnya ...

// Proses Simpan Data ke db_portal_pusat
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_padi']) && !$sudah_submit) {
    try {
        $conn->begin_transaction();

        // Menyesuaikan dengan struktur gambar: id, id_user, kelas, sekolah, indikator, skor_penilaian, tanggal_uji + kode_aplikasi
        $query = "INSERT INTO hasil_uji_siswa (id_user, kelas, sekolah, indikator, skor_penilaian, tanggal_uji, kode_aplikasi) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($query);

        $tanggal_sekarang = date("Y-m-d");
        $sekolah = "PADI User"; // Atau ambil dari session jika ada

        foreach ($instrumen_padi as $aspek => $indikator_list) {
            foreach ($indikator_list as $index => $indikator) {
                // Mengambil nilai skor dari radio button
                $aspek_key = preg_replace('/[^A-Za-z0-9]/', '', $aspek);
                $input_name = "skor_" . $aspek_key . "_" . $index;
                $skor = $_POST[$input_name] ?? 0;

                if ($skor > 0) {
                    // Bind param sesuai urutan kolom di gambar
                    $stmt_insert->bind_param("isssiss",
                        $user_id,
                        $level_kelas,
                        $sekolah,
                        $indikator,
                        $skor,
                        $tanggal_sekarang,
                        $kode_app
                    );
                    $stmt_insert->execute();
                }
            }
        }

        $conn->commit();
        $pesan_status = '<div class="alert alert-success text-center">Terima kasih! Penilaian Portal PADI telah tersimpan di pusat data.</div>';
        $sudah_submit = true;

    } catch (Exception $e) {
        $conn->rollback();
        $pesan_status = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penilaian Aplikasi PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; padding: 40px 0; }
        .card-form { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .btn-padi { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <h2 class="fw-bold">Penilaian APLIKASI PADI</h2>
                <p class="text-muted">Halo <strong><?= $nama_pengguna ?></strong>, ceritakan pengalamanmu menggunakan aplikasi ini.</p>
                <a href="dashboard.php" class="text-decoration-none small"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
            </div>

            <?= $pesan_status ?>

            <?php if (!$sudah_submit): ?>
            <div class="alert alert-warning border-warning mb-4">
    <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i>Petunjuk Pengisian:</h6>
    <ul class="list-unstyled small mb-0">
        <li><strong>5</strong> = Sangat Setuju / Sangat Mudah / Sangat Bagus</li>
        <li><strong>4</strong> = Setuju / Mudah / Bagus</li>
        <li><strong>3</strong> = Cukup / Biasa Saja</li>
        <li><strong>2</strong> = Tidak Setuju / Sulit / Kurang Bagus</li>
        <li><strong>1</strong> = Sangat Tidak Setuju / Sangat Sulit / Tidak Bagus</li>
    </ul>
</div>

                <div class="card card-form p-4">
                <form method="POST">
                    <?php foreach ($instrumen_padi as $aspek => $indikator_list): ?>
                        <h5 class="fw-bold text-primary mt-3"><?= $aspek ?></h5>
                        <table class="table table-hover mt-2">
                            <thead>
                                <tr class="small text-center">
                                    <th class="text-start">Pernyataan</th>
                                    <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($indikator_list as $index => $indikator): ?>
                                <?php $input_name = "skor_" . preg_replace('/[^A-Za-z0-9]/', '', $aspek) . "_" . $index; ?>
                                <tr>
                                    <td class="small"><?= $indikator ?></td>
                                    <?php for ($i=1; $i<=5; $i++): ?>
                                    <td class="text-center">
                                        <input type="radio" name="<?= $input_name ?>" value="<?= $i ?>" required>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>

                    <div class="d-grid mt-4">
                        <button type="submit" name="submit_padi" class="btn btn-padi btn-lg">Kirim Penilaian</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>