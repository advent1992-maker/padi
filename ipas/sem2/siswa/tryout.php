<?php
// FILE: siswa/tryout.php - Menggabungkan START dan PROSES Try Out

require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// Pastikan hanya siswa yang bisa mengakses
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$siswa_id = $_SESSION['user_id'];
$tryout_id = $_GET['tryout_id'] ?? null;
$session_id = $_GET['session_id'] ?? null;

// --- KONFIGURASI BASE URL GAMBAR & FUNGSI BANTUAN ---
// BASE URL DARI PERSPEKTIF FILE INI (siswa/tryout.php) KE FOLDER 'aset/'
$BASE_IMAGE_URL = "../aset/";

/**
 * Fungsi pembantu untuk menghasilkan URL gambar.
 * Menggabungkan Base URL dengan fragmen URL dari database.
 */
function generateImageUrl($url_fragment, $base_url) {
    if (empty($url_fragment)) {
        return '';
    }
    // Jika input sudah merupakan URL lengkap (misal: http/https), kembalikan langsung
    if (filter_var($url_fragment, FILTER_VALIDATE_URL)) {
        return $url_fragment;
    }
    // Jika input mengandung 'aset/' di awal (kasus aset/pp1.png), hapus 'aset/'
    if (strpos(strtolower($url_fragment), 'aset/') === 0) {
        $url_fragment = substr($url_fragment, 5);
    }

    // Jika hanya fragmen/nama file, gabungkan dengan base URL
    return rtrim($base_url, '/') . '/' . ltrim($url_fragment, '/');
}
// --- AKHIR KONFIGURASI BASE URL GAMBAR & FUNGSI BANTUAN ---


// --- A. LOGIKA UTAMA TRY OUT (Mode Pengerjaan) ---
if ($session_id) {

    // 1. Ambil Data Sesi & Master Try Out
    $query_session = "
        SELECT
            ts.id, ts.tryout_id, ts.waktu_mulai, ts.waktu_selesai_target, ts.status,
            tm.judul, tm.waktu_alokasi AS waktu_durasi
        FROM tryout_session ts
        JOIN tryout_master tm ON ts.tryout_id = tm.id
        WHERE ts.id = ? AND ts.siswa_id = ? AND ts.status = 'ongoing'
    ";
    $stmt_session = $db_mapel->prepare($query_session);
    $stmt_session->bind_param("ii", $session_id, $siswa_id);
    $stmt_session->execute();
    $session_data = $stmt_session->get_result()->fetch_assoc();
    $stmt_session->close();

    // Validasi Sesi
    if (!$session_data) {
        $_SESSION['error_message'] = "Sesi ujian tidak ditemukan atau sudah selesai. Silakan cek riwayat Anda.";
        header("Location: dashboard.php");
        exit();
    }

    $tryout_id = $session_data['tryout_id'];
    $waktu_selesai_target = new DateTime($session_data['waktu_selesai_target']);
    $current_time = new DateTime();

    // Cek apakah waktu sudah habis
    if ($current_time > $waktu_selesai_target) {
        // Redirect ke script submit untuk penyelesaian otomatis
        header("Location: submit_tryout.php?session_id=" . $session_id . "&auto=1");
        exit();
    }

    // Hitung sisa waktu
    $interval = $current_time->diff($waktu_selesai_target);
    $sisa_detik = $interval->h * 3600 + $interval->i * 60 + $interval->s;


    // 2. Ambil Daftar Soal dan Jawaban Siswa
    $query_soal = "
        SELECT
            st.id, st.pertanyaan, st.gambar_url, st.opsi_a, st.opsi_b, st.opsi_c, st.opsi_d,
            st.opsi_a_gambar_url, st.opsi_b_gambar_url, st.opsi_c_gambar_url, st.opsi_d_gambar_url,
            tj.jawaban_siswa
        FROM soal_tryout st
        LEFT JOIN tryout_jawaban tj ON st.id = tj.soal_id AND tj.session_id = ?
        WHERE st.tryout_id = ?
        ORDER BY st.id ASC
    ";
    $stmt_soal = $db_mapel->prepare($query_soal);
    $stmt_soal->bind_param("ii", $session_id, $tryout_id);
    $stmt_soal->execute();
    $soal_list = $stmt_soal->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_soal->close();

    // 3. LOGIKA PENYIMPANAN JAWABAN (AJAX/POST Submission)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soal_id'])) {
        $soal_id = $_POST['soal_id'];
        $jawaban_siswa = $_POST['jawaban_siswa'] ?? null;

        // Query INSERT ON DUPLICATE KEY UPDATE untuk menyimpan/memperbarui jawaban
        $query_save = "
            INSERT INTO tryout_jawaban (session_id, soal_id, jawaban_siswa)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE jawaban_siswa = VALUES(jawaban_siswa)
        ";
        $stmt_save = $db_mapel->prepare($query_save);
        $stmt_save->bind_param("iis", $session_id, $soal_id, $jawaban_siswa);

        if ($stmt_save->execute()) {
            echo json_encode(['status' => 'success', 'soal_id' => $soal_id, 'jawaban' => $jawaban_siswa]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan jawaban.']);
        }
        $stmt_save->close();
        exit(); // Hentikan PHP setelah AJAX response
    }

    // 4. TAMPILKAN HALAMAN PENGERJAAN (HTML Part Bawah)
    $mode = 'proses';

}
// --- B. LOGIKA START TRY OUT (Mode Konfirmasi) ---
else {

    if (!$tryout_id || !is_numeric($tryout_id)) {
        $_SESSION['error_message'] = "ID Try Out tidak valid.";
        header("Location: dashboard.php");
        exit();
    }

    // 1. Ambil Detail Try Out Master
    $query_master = "
        SELECT
            tm.id, tm.judul, tm.kelas, tm.waktu_alokasi,
            COUNT(st.id) AS total_soal
        FROM tryout_master tm
        LEFT JOIN soal_tryout st ON tm.id = st.tryout_id
        WHERE tm.id = ? AND tm.kelas = ?
        GROUP BY tm.id
    ";
    $stmt_master = $db_mapel->prepare($query_master);
    $stmt_master->bind_param("is", $tryout_id, $_SESSION['kelas']);
    $stmt_master->execute();
    $result_master = $stmt_master->get_result();
    $tryout_data = $result_master->fetch_assoc();
    $stmt_master->close();

    if (!$tryout_data) {
        $_SESSION['error_message'] = "Try Out tidak ditemukan atau tidak sesuai dengan kelas Anda.";
        header("Location: dashboard.php");
        exit();
    }

    $judul_tryout = $tryout_data['judul'];
    $waktu_durasi = $tryout_data['waktu_alokasi'];
    $total_soal = $tryout_data['total_soal'];
    $errors = [];

    // 2. Cek Validasi (Soal Kosong dan Sesi Sebelumnya)
    if ($total_soal == 0) {
        $errors[] = "Try Out ini belum memiliki soal.";
    }

    // PERBAIKAN ERROR: Menggunakan tryout_session untuk memeriksa sesi siswa
    $query_check = "
        SELECT id, waktu_selesai
        FROM tryout_session
        WHERE tryout_id = ? AND siswa_id = ?
        ORDER BY id DESC
        LIMIT 1
    ";
    $stmt_check = $db_mapel->prepare($query_check);
    $stmt_check->bind_param("ii", $tryout_id, $siswa_id);
    $stmt_check->execute();
    $session_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($session_check) {
        if (!empty($session_check['waktu_selesai'])) {
            // Jika sudah ada sesi yang selesai, kita cek batas upaya di riwayat
            $query_upaya = "SELECT COUNT(id) AS total_upaya FROM riwayat_tryout WHERE tryout_id = ? AND id_user = ?";
            $stmt_upaya = $db_mapel->prepare($query_upaya);
            $stmt_upaya->bind_param("ii", $tryout_id, $siswa_id);
            $stmt_upaya->execute();
            $total_upaya = $stmt_upaya->get_result()->fetch_assoc()['total_upaya'];
            $stmt_upaya->close();

            if ($total_upaya >= 2) {
                $errors[] = "Anda **sudah mencapai batas** maksimal pengerjaan (2 kali) Try Out ini.";
            } else {
                // Di sini, kita TIDAK menampilkan error, tapi memberi opsi untuk memulai baru
                // Karena logika ulangi sudah di handle di review_tryout.php,
                // kita biarkan siswa melihat halaman konfirmasi start, kecuali jika upaya habis.
            }

        } else {
            // Lanjutkan sesi yang tertunda
            $_SESSION['success_message'] = "Anda melanjutkan sesi Try Out yang belum selesai.";
            header("Location: tryout.php?session_id=" . $session_check['id']);
            exit();
        }
    }

    // 3. LOGIKA POST UNTUK MEMULAI (Pindah ke Mode Pengerjaan)
    if (empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_tryout'])) {
        $waktu_mulai = date("Y-m-d H:i:s");
        $waktu_selesai_target = date("Y-m-d H:i:s", strtotime("+$waktu_durasi minutes")); // Menggunakan $waktu_durasi (dari waktu_alokasi)

        $query_insert = "
            INSERT INTO tryout_session (tryout_id, siswa_id, waktu_mulai, waktu_selesai_target, status)
            VALUES (?, ?, ?, ?, 'ongoing')
        ";
        $stmt_insert = $db_mapel->prepare($query_insert);
        $stmt_insert->bind_param("iiss", $tryout_id, $siswa_id, $waktu_mulai, $waktu_selesai_target);

        if ($stmt_insert->execute()) {
            $new_session_id = $db_mapel->insert_id;
            $_SESSION['success_message'] = "Waktu Anda dimulai!";

            // Pindah ke mode pengerjaan di halaman ini sendiri
            header("Location: tryout.php?session_id=" . $new_session_id);
            exit();
        } else {
            $_SESSION['error_message'] = "Gagal memulai sesi Try Out. (" . $db_mapel->error . ")";
            header("Location: dashboard.php");
            exit();
        }
    }

    // 4. TAMPILKAN HALAMAN KONFIRMASI (HTML Part Bawah)
    $mode = 'start';
}

// --------------------------------------------------------------------------------------------------
// HTML DISPLAY
// --------------------------------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode == 'proses' ? 'Proses Try Out' : 'Mulai Try Out'; ?> | <?php echo htmlspecialchars($session_data['judul'] ?? $judul_tryout); ?></title>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .tryout-header { background-color: #0d6efd; color: white; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; }
        .soal-card { margin-bottom: 1.5rem; border-left: 5px solid #198754; }
        .jawaban-radio:checked + label { background-color: #d1e7dd !important; border-color: #198754 !important; font-weight: bold; }
        .jawaban-label { cursor: pointer; border: 1px solid #dee2e6; border-radius: 0.5rem; padding: 10px; margin-bottom: 0.5rem; display: block; }
        .countdown { font-size: 1.5rem; font-weight: bold; }
        .kunci-jawaban-badge { background-color: #0d6efd; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .img-soal { max-height: 200px; object-fit: contain; }
        .img-opsi { max-height: 60px; object-fit: contain; display: inline-block; vertical-align: middle; }
        .list-opsi .col-12 { padding-top: 5px; padding-bottom: 5px; }

        /* Fixed sidebar for pengerjaan mode */
        <?php if ($mode == 'proses'): ?>
        .fixed-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: 280px;
            padding: 15px;
            background-color: #fff;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1030;
        }
        main {
            margin-right: 290px; /* Offset main content */
        }

        /* --- PERBAIKAN TAMPILAN MOBILE --- */
        @media (max-width: 768px) {
            /* Menonaktifkan fixed sidebar pada layar <= 768px (ukuran tablet/mobile) */
            .fixed-sidebar {
                position: static;
                width: 100%;
                padding: 15px 0; /* Sesuaikan padding */
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                border-bottom: 1px solid #ddd;
                height: auto; /* Biarkan tinggi menyesuaikan konten */
            }
            /* Menghilangkan offset margin pada main content */
            main {
                margin-right: 0 !important;
            }
            .container-fluid {
                padding: 0 !important;
            }
            .d-flex {
                flex-direction: column; /* Tumpuk sidebar di atas main content */
            }
            .row.row-cols-5 {
                justify-content: center;
            }
            .btn-soal {
                font-size: 0.75rem;
                padding: 0.4rem 0.2rem;
            }
        }
        /* --- AKHIR PERBAIKAN TAMPILAN MOBILE --- */

        <?php endif; ?>
    </style>
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\(', '\\)']] },
            svg: { fontCache: 'global' }
        };
    </script>
</head>
<body>

<div class="container-fluid py-4">

    <?php if ($mode == 'start'): // Tampilan Konfirmasi Mulai Try Out ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <a href="dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>

            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white text-center rounded-top-4 p-4">
                    <h1 class="h3 mb-0"><i class="fas fa-play-circle me-2"></i> Konfirmasi Mulai Try Out</h1>
                </div>
                <div class="card-body p-5">
                    <h2 class="card-title text-center text-primary mb-4"><?php echo htmlspecialchars($judul_tryout); ?></h2>

                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Kelas Target:</span>
                            <span class="badge bg-info text-dark fs-6"><?php echo htmlspecialchars($_SESSION['kelas']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Durasi Ujian:</span>
                            <span class="badge bg-danger fs-6"><?php echo htmlspecialchars($waktu_durasi); ?> Menit</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Total Soal:</span>
                            <span class="badge bg-success fs-6"><?php echo htmlspecialchars($total_soal); ?> Soal</span>
                        </li>
                    </ul>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm rounded-3">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Gagal Memulai!</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning shadow-sm rounded-3 text-center">
                            <p class="lead mb-2"><i class="fas fa-clock"></i> **PERINGATAN!**</p>
                            <p class="mb-3">Setelah Anda menekan tombol di bawah, waktu akan segera dihitung mundur selama **<?php echo htmlspecialchars($waktu_durasi); ?> menit**.</p>
                            <p class="fw-bold text-danger">Pastikan koneksi internet Anda stabil sebelum memulai!</p>

                            <form method="POST">
                                <input type="hidden" name="start_tryout" value="1">
                                <button type="submit" class="btn btn-primary btn-lg w-100 mt-3 rounded-pill shadow">
                                    <i class="fas fa-gavel"></i> Saya Siap, Mulai Try Out Sekarang!
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="card-footer text-muted text-center">
                    ID Ujian: #<?php echo htmlspecialchars($tryout_id); ?>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($mode == 'proses'): // Tampilan Pengerjaan Soal ?>

    <div class="d-flex">

        <div class="fixed-sidebar">
            <div class="text-center mb-4 p-2 border-bottom">
                <p class="mb-1 text-danger fw-bold">Waktu Tersisa:</p>
                <div id="countdown" class="countdown text-danger">--:--:--</div>
            </div>

            <p class="fw-bold text-primary">Navigasi Soal</p>
            <div class="row row-cols-5 g-2" id="navigasi-soal">
                <?php foreach ($soal_list as $index => $soal): ?>
                <?php
                    $no = $index + 1;
                    $class = !empty($soal['jawaban_siswa']) ? 'btn-success' : 'btn-outline-secondary';
                ?>
                <div class="col">
                    <a href="#soal-<?php echo $no; ?>" class="btn btn-sm <?php echo $class; ?> w-100 btn-soal" data-soal-id="<?php echo $soal['id']; ?>" id="btn-nav-<?php echo $soal['id']; ?>">
                        <?php echo $no; ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-danger w-100 mt-5 shadow-lg" data-bs-toggle="modal" data-bs-target="#modalSubmit">
                <i class="fas fa-paper-plane"></i> Selesai & Kirim Jawaban
            </button>
        </div>

        <main class="flex-grow-1 container">
            <div class="tryout-header shadow-sm">
                <h2 class="h4 mb-0"><?php echo htmlspecialchars($session_data['judul']); ?></h2>
                <p class="mb-0 small">Sesi ID: <?php echo htmlspecialchars($session_id); ?></p>
            </div>

            <?php foreach ($soal_list as $index => $soal): ?>
            <?php $no = $index + 1; ?>
            <div class="card soal-card shadow" id="soal-<?php echo $no; ?>" data-soal-id="<?php echo $soal['id']; ?>">
                <div class="card-header fw-bold bg-white">Soal No. <?php echo $no; ?></div>
                <div class="card-body">

                    <div class="mb-3">
                        <p class="card-text fw-bold soal-pertanyaan" style="white-space: pre-wrap;"><?php echo htmlspecialchars(trim($soal['pertanyaan'])); ?></p>
                        <?php $soal_gambar_url = generateImageUrl($soal['gambar_url'], $BASE_IMAGE_URL); ?>
                        <?php if (!empty($soal_gambar_url)): ?>
                            <div class="text-center p-2 border rounded bg-light mb-3">
                                <img src="<?php echo htmlspecialchars($soal_gambar_url); ?>" alt="Gambar Soal"
                                    class="img-fluid rounded img-soal"
                                    onerror="this.onerror=null;this.src='https://placehold.co/200x150?text=Error+Gambar';"
                                >
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="list-opsi row g-3">
                        <?php
                        $opsi_list = [
                            'A' => ['text' => $soal['opsi_a'], 'url' => $soal['opsi_a_gambar_url']],
                            'B' => ['text' => $soal['opsi_b'], 'url' => $soal['opsi_b_gambar_url']],
                            'C' => ['text' => $soal['opsi_c'], 'url' => $soal['opsi_c_gambar_url']],
                            'D' => ['text' => $soal['opsi_d'], 'url' => $soal['opsi_d_gambar_url']],
                        ];
                        foreach ($opsi_list as $key => $opsi):
                        ?>
                        <div class="col-12">
                            <input type="radio"
                                id="jawaban-<?php echo $soal['id']; ?>-<?php echo $key; ?>"
                                name="jawaban-<?php echo $soal['id']; ?>"
                                class="form-check-input d-none jawaban-radio"
                                value="<?php echo $key; ?>"
                                data-soal-id="<?php echo $soal['id']; ?>"
                                <?php echo ($soal['jawaban_siswa'] == $key) ? 'checked' : ''; ?>
                            >
                            <label for="jawaban-<?php echo $soal['id']; ?>-<?php echo $key; ?>" class="jawaban-label bg-white <?php echo ($soal['jawaban_siswa'] == $key) ? 'jawaban-terpilih' : ''; ?>">
                                <span class="fw-bold me-2 text-primary"><?php echo $key; ?>.</span>
                                <?php $opsi_gambar_url = generateImageUrl($opsi['url'], $BASE_IMAGE_URL); ?>
                                <?php if (!empty($opsi_gambar_url)): ?>
                                    <img src="<?php echo htmlspecialchars($opsi_gambar_url); ?>" alt="Opsi <?php echo $key; ?>"
                                        class="img-fluid border rounded img-opsi"
                                        onerror="this.onerror=null;this.src='https://placehold.co/60x40?text=Error+Gambar';"
                                    >
                                <?php elseif (!empty($opsi['text'])): ?>
                                    <?php echo $opsi['text']; ?>
                                <?php else: ?>
                                    <small class="text-danger">Opsi Kosong</small>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>

            <div class="text-center my-5">
                <button type="button" class="btn btn-danger btn-lg rounded-pill shadow-lg" data-bs-toggle="modal" data-bs-target="#modalSubmit">
                    <i class="fas fa-check-circle"></i> Selesai dan Kirim Jawaban
                </button>
            </div>
        </main>

    </div>

    <div class="modal fade" id="modalSubmit" tabindex="-1" aria-labelledby="modalSubmitLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalSubmitLabel"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Selesai Ujian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin **mengakhiri** Try Out ini sekarang?</p>
                    <p class="fw-bold">Jawaban yang sudah Anda pilih akan segera disimpan. Anda tidak dapat kembali mengubahnya.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal, Lanjutkan Mengerjakan</button>
                    <a href="submit_tryout.php?session_id=<?php echo $session_id ?? ''; ?>" class="btn btn-danger fw-bold">Ya, Selesai dan Kirim!</a>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Hanya jalankan skrip pengerjaan jika mode 'proses'
    <?php if ($mode == 'proses'): ?>

        // 1. TIMER HITUNG MUNDUR
        let sisaDetik = <?php echo $sisa_detik; ?>;
        const countdownElement = document.getElementById('countdown');

        function formatTime(totalSeconds) {
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            return [hours, minutes, seconds]
                .map(t => String(t).padStart(2, '0'))
                .join(':');
        }

        const timer = setInterval(() => {
            sisaDetik--;
            countdownElement.textContent = formatTime(sisaDetik);

            if (sisaDetik <= 0) {
                clearInterval(timer);
                alert("Waktu Try Out telah habis! Jawaban Anda akan otomatis dikirim.");
                window.location.href = 'submit_tryout.php?session_id=<?php echo $session_id; ?>&auto=1';
            }
        }, 1000);

        // 2. LOGIKA PENYIMPANAN JAWABAN ASYNCHRONOUS (AJAX)
        document.querySelectorAll('.jawaban-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const soalId = this.getAttribute('data-soal-id');
                const jawabanSiswa = this.value;
                const buttonNav = document.getElementById(`btn-nav-${soalId}`);

                // Hilangkan class terpilih pada label yang lain di soal yang sama
                document.querySelectorAll(`input[name="jawaban-${soalId}"] + label`).forEach(label => {
                    label.classList.remove('jawaban-terpilih', 'bg-success', 'text-white');
                    label.classList.add('bg-white');
                });

                // Tambahkan class terpilih pada label yang baru
                this.nextElementSibling.classList.add('jawaban-terpilih', 'bg-success', 'text-white');
                this.nextElementSibling.classList.remove('bg-white');

                // Update status navigasi
                if (buttonNav) {
                    buttonNav.classList.remove('btn-outline-secondary', 'btn-warning');
                    buttonNav.classList.add('btn-success');
                }

                // Kirim jawaban ke server
                fetch('tryout.php?session_id=<?php echo $session_id; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `soal_id=${soalId}&jawaban_siswa=${jawabanSiswa}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        console.error('Gagal menyimpan jawaban:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saat koneksi ke server:', error);
                    // Peringatan ke siswa jika penyimpanan gagal
                    alert('Jawaban gagal disimpan ke server. Cek koneksi internet Anda!');
                });
            });
        });

        // 3. Memuat ulang MathJax
        document.addEventListener('DOMContentLoaded', function () {
            if (window.MathJax) {
                MathJax.typesetPromise();
            }
        });

    <?php endif; ?>
</script>
</body>
</html>