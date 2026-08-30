<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// --- LOGIKA PENANDA ARSIP (TETAP SAMA) ---
$current_dir = basename(dirname(dirname(__FILE__)));
$is_archive = (strpos($current_dir, 'sm1') !== false);

// Pengamanan Role
if (($_SESSION['role'] ?? '') !== 'siswa') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? 'Siswa';
$level_kelas = $_SESSION['kelas'] ?? 0;
$id_guru_siswa = $_SESSION['id_guru'] ?? 0;

// Sinkronisasi data user
if ($level_kelas == 0 || $id_guru_siswa == 0) {
    $stmt_user = $db_mapel->prepare("SELECT kelas, id_guru FROM users WHERE id = ? AND role = 'siswa'");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();
        $_SESSION['kelas'] = $user_data['kelas'];
        $level_kelas = $user_data['kelas'];
        $_SESSION['id_guru'] = $user_data['id_guru'];
        $id_guru_siswa = $user_data['id_guru'];
    } else {
        session_destroy();
        header("Location: ../login.php?error=user_data_missing");
        exit();
    }
    $stmt_user->close();
}
// --- LOGIKA BARU: Ambil Nama Guru Pembimbing dari Database Portal ---
$nama_guru_pembimbing = "N/A";
if ($id_guru_siswa > 0) {
    $stmt_g = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ? AND role = 'guru'");
    $stmt_g->bind_param("i", $id_guru_siswa);
    $stmt_g->execute();
    $res_g = $stmt_g->get_result();
    if ($row_g = $res_g->fetch_assoc()) {
        $nama_guru_pembimbing = $row_g['nama_lengkap'];
    }
    $stmt_g->close();
}

// Inisialisasi default
$total_bab_lulus = 0;
$total_bab_dicoba = 0;
$rata_rata_gabungan = 0;

if ($id_guru_siswa > 0) {
    // 1. STATISTIK KUIS (Dibulatkan per Materi dulu)
    // Kita ambil rata-rata per id_materi, lalu kita rata-ratakan lagi hasil bulatnya di PHP
    $list_nilai_kuis = [];
    $q_kuis_per_mapel = $db_mapel->prepare("SELECT ROUND(AVG(persentase)) as nilai_bulat_materi FROM riwayat_kuis WHERE id_user = ? GROUP BY id_materi");
    $q_kuis_per_mapel->bind_param("i", $user_id);
    $q_kuis_per_mapel->execute();
    $res_k = $q_kuis_per_mapel->get_result();
    while($row = $res_k->fetch_assoc()) {
        $list_nilai_kuis[] = $row['nilai_bulat_materi'];
    }
    $q_kuis_per_mapel->close();

    // Hitung rata-rata kuis yang sudah bulat
    $avg_kuis_final = count($list_nilai_kuis) > 0 ? round(array_sum($list_nilai_kuis) / count($list_nilai_kuis)) : 0;

    // 2. STATISTIK TRYOUT (Dibulatkan per Judul Tryout dulu)
    $list_nilai_to = [];
    $q_to_per_judul = $db_mapel->prepare("SELECT ROUND(AVG(persentase)) as nilai_bulat_to FROM riwayat_tryout WHERE id_user = ? GROUP BY tryout_id");
    $q_to_per_judul->bind_param("i", $user_id);
    $q_to_per_judul->execute();
    $res_t = $q_to_per_judul->get_result();
    while($row = $res_t->fetch_assoc()) {
        $list_nilai_to[] = $row['nilai_bulat_to'];
    }
    $q_to_per_judul->close();

    // Hitung rata-rata tryout yang sudah bulat
    $avg_to_final = count($list_nilai_to) > 0 ? round(array_sum($list_nilai_to) / count($list_nilai_to)) : 0;

    // 3. RATA-RATA GABUNGAN (Kuis Bulat + TO Bulat)
    $total_avg = 0;
    $count_valid = 0;

    if ($avg_kuis_final > 0) {
        $total_avg += $avg_kuis_final;
        $count_valid++;
    }
    if ($avg_to_final > 0) {
        $total_avg += $avg_to_final;
        $count_valid++;
    }

    $rata_rata_gabungan = ($count_valid > 0) ? round($total_avg / $count_valid) : 0;

    // Statistik Tambahan untuk Box Dashboard
    $q_stat = $db_mapel->prepare("SELECT 
        COUNT(DISTINCT id_materi) as total_materi,
        COUNT(DISTINCT CASE WHEN status_lulus = 'LULUS' THEN id_materi ELSE NULL END) as lulus_materi 
        FROM riwayat_kuis WHERE id_user = ?");
    $q_stat->bind_param("i", $user_id);
    $q_stat->execute();
    $res_stat = $q_stat->get_result()->fetch_assoc();
    
    $total_bab_lulus = $res_stat['lulus_materi'] ?? 0;
    $total_bab_dicoba_kuis = $res_stat['total_materi'] ?? 0;
    
    $q_to_count = $db_mapel->prepare("SELECT COUNT(DISTINCT tryout_id) as total_to FROM riwayat_tryout WHERE id_user = ?");
    $q_to_count->bind_param("i", $user_id);
    $q_to_count->execute();
    $total_to_done = $q_to_count->get_result()->fetch_assoc()['total_to'] ?? 0;
    
    $total_bab_dicoba = $total_bab_dicoba_kuis + $total_to_done;
}

$skor_rata_rata_tampil = ($rata_rata_gabungan > 0) ? $rata_rata_gabungan : 'N/A';
$lulus_tampil = $total_bab_lulus;

$db_mapel->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa | B.Komering</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; }

    /* Warna Abu-abu Gelap Sesuai Ikon Mapel Komering */
    .bg-primary { background-color: #4b4b4b !important; }
    .btn-primary { background-color: #4b4b4b !important; border-color: #4b4b4b !important; }
    .text-primary { color: #4b4b4b !important; }

    .hero-card {
        background: <?= $is_archive ? 'linear-gradient(135deg, #6c757d, #495057)' : 'linear-gradient(135deg, #4b4b4b, #2c2c2c)' ?>;
        color: white; padding: 40px; border-radius: 15px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .stat-card {
        background: white; border-radius: 10px; padding: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
    }
    .stat-card:hover { transform: translateY(-5px); }

    /* Warna Biru Mathfiction untuk Tulisan Pilih Mapel */
    .text-biru-math { color: #0d6efd !important; }

    /* Style Banner Panduan Mulok */
    .guide-banner-mulok { background: #fff; border: none; border-radius: 15px; border-left: 6px solid #4b4b4b; }

    /* STYLE AI CHAT */
    #ai-chat-container {
        position: fixed; bottom: 20px; right: 20px; z-index: 9999;
        display: flex; flex-direction: column; align-items: center; gap: 8px;
    }
    #ai-chat-button {
        width: 60px; height: 60px; border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none; color: white; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        cursor: pointer; transition: 0.3s; display: center; align-items: center; justify-content: center;
    }
    #ai-chat-button:hover { transform: scale(1.1); }
    .ai-label {
        background: white; padding: 4px 12px; border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1); font-weight: bold;
        color: #764ba2; font-size: 0.75rem; border: 1px solid #eee;
        text-align: center; white-space: nowrap; pointer-events: none;
    }
    #ai-chat-window {
        width: 350px; height: 450px; background: white; border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: none;
        flex-direction: column; overflow: hidden; position: absolute;
        bottom: 100px; right: 0;
    }
    .chat-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
    .chat-body { flex: 1; padding: 15px; overflow-y: auto; background: #f8f9fa; display: flex; flex-direction: column; gap: 10px; }
    .chat-footer { padding: 10px; background: white; border-top: 1px solid #eee; display: flex; gap: 5px; }
    .msg { max-width: 80%; padding: 10px 15px; border-radius: 15px; font-size: 0.9rem; line-height: 1.4; }
    .msg-ai { background: #e9ecef; align-self: flex-start; border-bottom-left-radius: 2px; color: #333; }
    .msg-user { background: #764ba2; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
    .typing { font-style: italic; font-size: 0.8rem; color: #888; margin-bottom: 5px; display: none; }
</style>
</head>
<body>

<?php if ($is_archive): ?>
    <div style="background: #ffc107; color: black; text-align: center; font-weight: bold; padding: 5px;">⚠️ MODE ARSIP SEMESTER 1</div>
<?php endif; ?>

<nav class="navbar navbar-dark <?= $is_archive ? 'bg-secondary' : 'bg-primary' ?> shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">B.Komering | SISWA</a>
        <span class="navbar-text text-white">
            Halo, <b class="text-warning"><?php echo htmlspecialchars($nama_pengguna); ?></b> (Kelas <?php echo htmlspecialchars($level_kelas); ?>)
        </span>
        <a href="../logout.php" class="btn btn-warning fw-bold">Keluar</a>
    </div>
</nav>

<div class="container mt-5 mb-5 text-start">
    <div class="hero-card text-center mb-5">
        <h1 class="display-5 fw-bold"><i class="fas fa-map-marked-alt"></i> Peta Pembelajaran Anda</h1>
        <p class="lead mt-3">Selamat datang kembali, <?php echo htmlspecialchars($nama_pengguna); ?>! Mari selesaikan materi b.komering hari ini bersama <b>Guru <?php echo htmlspecialchars($nama_guru_pembimbing); ?></b>.</p>
        <p class="fs-4">Anda berada di <b>Kelas <?php echo htmlspecialchars($level_kelas); ?></b>.</p>
        <a href="../../../dashboard.php" class="btn btn-light mt-2 fw-bold text-biru-math border-2 shadow-sm">
            <i class="fas fa-th-large"></i> Pilih Mata Pelajaran
        </a>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card guide-banner-mulok shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="fw-bold mb-1 text-primary"><i class="fas fa-info-circle me-2"></i> Petunjuk Belajar Bahasa Komering</h5>
                        <p class="text-muted mb-0 small">Pelajari cara mengakses materi, kuis, dan gunakan asisten AI untuk kosa kata sulit.</p>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 rounded-pill mt-3 mt-md-0 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPanduanMulok">
                        <i class="fas fa-map me-2"></i> LIHAT PETUNJUK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row text-center mb-5">
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <h5 class="card-title text-success">Materi Selesai (Lulus)</h5>
                <h1 class="text-success my-3 fw-bold"><?php echo $lulus_tampil; ?></h1>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <h5 class="card-title text-primary">Total Kuis dan Tryout dikerjakan</h5>
                <h1 class="text-primary my-3 fw-bold"><?php echo $total_bab_dicoba; ?></h1>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card">
                <h5 class="card-title text-success">Nilai Rata-Rata</h5>
                <h1 class="text-success my-3 fw-bold"><?php echo $skor_rata_rata_tampil; ?></h1>
            </div>
        </div>
    </div>

    <h3 class="text-center text-secondary mb-4">Mulai Pembelajaran</h3>
    <div class="row g-4 text-start">
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4>Lihat Daftar Materi dan Kuis</h4>
                <p>Pilih materi Bahasa Komering yang ingin Anda taklukkan hari ini.</p>
                <a href="materi.php" class="btn btn-primary w-100 btn-lg rounded-pill">
                    <i class="fas fa-book"></i> Lihat Materi
                </a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4>Lihat Riwayat & Pembahasan</h4>
                <p>Cek semua upaya kuis Anda, skor, dan Pembahasan.</p>
                <a href="riwayat_progress.php" class="btn btn-success w-100 btn-lg rounded-pill" style="background-color: #28a745; border-color: #28a745;">
                    <i class="fas fa-history"></i> Lihat Riwayat
                </a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4><i class="fas fa-pen-fancy"></i> Mulai Try Out</h4>
                <p>Pilih kategori ujian: Harian, Tengah Semester, atau Semester.</p>
                <a href="daftar_tryout.php" class="btn btn-primary w-100 btn-lg mt-2 rounded-pill">
                    <i class="fas fa-file-alt"></i> Pilih Kategori Ujian
                </a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4><i class="fas fa-trophy"></i> LEADERBOARD</h4>
                <p>Yukk Periksa Peringkatmu!</p>
                <a href="leaderboard.php" class="btn btn-warning w-100 btn-lg mt-2 text-white rounded-pill">
                    <i class="fas fa-medal"></i> BUKA
                </a>
            </div>
        </div>
        <!--<div class="col-md-12 mb-3 text-center">-->
        <!--    <div class="stat-card p-4">-->
        <!--        <h4><i class="fas fa-star"></i> BERIKAN PENILAIAN</h4>-->
        <!--        <p>Bantu kami meningkatkan media dengan memberikan penilaian jujur Anda.</p>-->
        <!--        <a href="../penilaian_materi.php" class="btn btn-warning btn-lg px-5 text-white rounded-pill mt-2">-->
        <!--            <i class="fas fa-edit me-2"></i> Isi Penilaian-->
        <!--        </a>-->
        <!--    </div>-->
        <!--</div>-->
    </div>
</div>

<div class="modal fade" id="modalPanduanMulok" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header border-0 p-4 pb-0 text-start">
                <h5 class="fw-bold text-primary"><i class="fas fa-map-marked-alt me-2"></i> Belajar Bahasa Komering</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-start">
                <div class="d-flex mb-4">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; flex-shrink: 0; font-weight: bold;">1</div>
                    <div class="ms-3">
                        <h6 class="fw-bold mb-1">Materi Muatan Lokal</h6>
                        <p class="small text-muted mb-0">Klik menu <b>Lihat Materi</b> untuk membaca bab tentang bahasa dan budaya Komering.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; flex-shrink: 0; font-weight: bold; background-color: #764ba2 !important;">2</div>
                    <div class="ms-3">
                        <h6 class="fw-bold mb-1">Tanya Kosa Kata</h6>
                        <p class="small text-muted mb-0">Bingung dengan arti kata? Tanya <b>Kak PADI AI</b> di pojok kanan bawah dashboard.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; flex-shrink: 0; font-weight: bold;">3</div>
                    <div class="ms-3">
                        <h6 class="fw-bold mb-1">Ujian & Skor Akhir</h6>
                        <p class="small text-muted mb-0">Selesaikan <b>Tryout</b> dan cek posisimu di daftar <b>Leaderboard</b>.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-primary w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">SAYA MENGERTI</button>
            </div>
        </div>
    </div>
</div>

<div id="ai-chat-container">
    <div id="ai-chat-window">
        <div class="chat-header">
            <span><i class="fas fa-robot me-2"></i> Asisten PADI Mulok</span>
            <button class="btn-close btn-close-white" onclick="toggleChat()"></button>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="msg msg-ai">Halo <strong><?= htmlspecialchars($nama_pengguna) ?></strong>! Ada kata bahasa Komering yang sulit? Bapak bantu ya! 😊</div>
            <div id="typingIndicator" class="typing">PADI AI sedang berpikir...</div>
        </div>
        <div class="chat-footer">
            <input type="text" id="chatInput" class="form-control form-control-sm" placeholder="Tanya tentang B. Komering..." onkeypress="handleKeyPress(event)">
            <button class="btn btn-sm btn-primary" onclick="sendMessage()" id="sendBtn" style="background: #764ba2; border: none;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    <button id="ai-chat-button" onclick="toggleChat()">
        <i class="fas fa-comment-dots fa-2x"></i>
    </button>
    <div class="ai-label shadow-sm" id="aiLabel">Tanya Kak PADI AI</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleChat() {
        const windowChat = document.getElementById('ai-chat-window');
        const label = document.getElementById('aiLabel');
        if (windowChat.style.display === 'flex') {
            windowChat.style.display = 'none';
            label.style.display = 'block';
        } else {
            windowChat.style.display = 'flex';
            label.style.display = 'none';
        }
    }
    function handleKeyPress(e) { if (e.key === 'Enter') sendMessage(); }
    async function sendMessage() {
    const input = document.getElementById('chatInput');
    const body = document.getElementById('chatBody');
    const sendBtn = document.getElementById('sendBtn');
    const typing = document.getElementById('typingIndicator');
    const pesan = input.value.trim();
    if (!pesan) return;
    appendMessage('user', pesan);
    input.value = '';
    input.disabled = true;
    sendBtn.disabled = true;
    typing.style.display = 'block';
    body.scrollTop = body.scrollHeight;
    try {
        const response = await fetch('proses_ai_asisten.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                pesan: pesan,
                mapel: 'Mulok' // TAMBAHKAN BARIS INI AGAR AI TAHU LOKASINYA
            })
        });
        const data = await response.json();
        typing.style.display = 'none';
        appendMessage('ai', data.jawaban);
    } catch (error) {
        typing.style.display = 'none';
        appendMessage('ai', "Sorry, there's a connection issue. Please try again later.");
    } finally {
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
    }
}
    function appendMessage(sender, text) {
        const body = document.getElementById('chatBody');
        const msgDiv = document.createElement('div');
        msgDiv.className = `msg msg-${sender}`;
        msgDiv.innerHTML = text;
        body.insertBefore(msgDiv, document.getElementById('typingIndicator'));
        body.scrollTop = body.scrollHeight;
    }
</script>
</body>
</html>