<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

// --- LOGIKA PENANDA ARSIP ---
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

// Ambil Nama Guru Pembimbing
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

// Inisialisasi default statistik
$total_bab_lulus = 0;
$total_bab_dicoba = 0;
$rata_rata_gabungan = 0;

if ($id_guru_siswa > 0) {
    // 1. HITUNG RATA-RATA KUIS PER BAB (Dibulatkan per Bab)
    $list_bab_kuis = [];
    $q_k = $db_mapel->prepare("SELECT id_materi, ROUND(AVG(persentase)) as nilai_bulat FROM riwayat_kuis WHERE id_user = ? GROUP BY id_materi");
    $q_k->bind_param("i", $user_id);
    $q_k->execute();
    $res_k = $q_k->get_result();
    while($row = $res_k->fetch_assoc()) {
        $list_bab_kuis[$row['id_materi']] = $row['nilai_bulat'];
    }
    $q_k->close();

    // 2. HITUNG NILAI PRAKTEK PER BAB (Hanya yang sudah dinilai)
    $list_bab_praktek = [];
    $q_p = $db_mapel->prepare("SELECT materi_id, nilai_angka FROM praktek_siswa WHERE id_siswa = ? AND status_dinilai = 1");
    $q_p->bind_param("i", $user_id);
    $q_p->execute();
    $res_p = $q_p->get_result();
    while($row = $res_p->fetch_assoc()) {
        $list_bab_praktek[$row['materi_id']] = $row['nilai_angka'];
    }
    $q_p->close();

    // 3. GABUNGKAN KUIS & PRAKTEK MENJADI NILAI MATERI (Dibulatkan per Bab)
    $total_skor_materi_bulat = 0;
    $count_materi = 0;
    
    // Ambil semua ID materi unik dari kuis dan praktek
    $all_materi_ids = array_unique(array_merge(array_keys($list_bab_kuis), array_keys($list_bab_praktek)));

    foreach ($all_materi_ids as $id_m) {
        $n_kuis = $list_bab_kuis[$id_m] ?? null;
        $n_praktek = $list_bab_praktek[$id_m] ?? null;

        if ($n_kuis !== null && $n_praktek !== null) {
            $skor_bab = round(($n_kuis + $n_praktek) / 2);
        } else {
            $skor_bab = $n_kuis ?? $n_praktek; // Ambil salah satu jika yang lain kosong
        }

        if ($skor_bab !== null) {
            $total_skor_materi_bulat += $skor_bab;
            $count_materi++;
        }
    }
    $avg_materi_seni = ($count_materi > 0) ? round($total_skor_materi_bulat / $count_materi) : 0;

    // 4. HITUNG RATA-RATA TRYOUT (Berjenjang: Bulatkan per Judul dulu)
    $list_to = [];
    $q_to = $db_mapel->prepare("SELECT ROUND(AVG(persentase)) as nilai_to_bulat FROM riwayat_tryout WHERE id_user = ? GROUP BY tryout_id");
    $q_to->bind_param("i", $user_id);
    $q_to->execute();
    $res_to = $q_to->get_result();
    while($row = $res_to->fetch_assoc()) {
        $list_to[] = $row['nilai_to_bulat'];
    }
    $q_to->close();

    $avg_tryout_final = (count($list_to) > 0) ? round(array_sum($list_to) / count($list_to)) : 0;

    // 5. FINAL GABUNGAN (Materi Seni + Tryout)
    $total_final = 0;
    $pembagi_final = 0;

    if ($avg_materi_seni > 0) { $total_final += $avg_materi_seni; $pembagi_final++; }
    if ($avg_tryout_final > 0) { $total_final += $avg_tryout_final; $pembagi_final++; }

    $rata_rata_gabungan = ($pembagi_final > 0) ? round($total_final / $pembagi_final) : 0;

    // Statistik Box Dashboard
    $q_stat = $db_mapel->prepare("SELECT 
        COUNT(DISTINCT id_materi) as total_materi_kuis,
        COUNT(DISTINCT CASE WHEN status_lulus = 'LULUS' THEN id_materi END) as lulus_materi
        FROM riwayat_kuis WHERE id_user = ?");
    $q_stat->bind_param("i", $user_id);
    $q_stat->execute();
    $res_s = $q_stat->get_result()->fetch_assoc();
    
    $total_bab_lulus = $res_s['lulus_materi'] ?? 0;
    
    $q_p_count = $db_mapel->prepare("SELECT COUNT(*) as jml_p FROM praktek_siswa WHERE id_siswa = ?");
    $q_p_count->bind_param("i", $user_id);
    $q_p_count->execute();
    $jml_p = $q_p_count->get_result()->fetch_assoc()['jml_p'] ?? 0;

    $total_bab_dicoba = $res_s['total_materi_kuis'] + count($list_to) + $jml_p;
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
    <title>Dashboard Siswa | Seni Rupa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    /* 1. DASAR & TYPOGRAPHY */
    body { 
        background-color: #f8f9fa; 
        font-family: 'Poppins', sans-serif; 
    }
    
    /* 2. TEMA WARNA SENI RUPA (Pink/Magenta) */
    .bg-primary { background-color: #e91e63 !important; }
    .btn-primary { background-color: #e91e63 !important; border-color: #e91e63 !important; }
    .text-primary { color: #e91e63 !important; }

    /* 3. HERO CARD (DENGAN LOGIKA ARSIP) */
    .hero-card {
        /* Menggunakan variabel PHP $is_archive yang sudah ada di kode Bapak */
        background: <?= $is_archive ? 'linear-gradient(135deg, #6c757d, #495057)' : 'linear-gradient(135deg, #f06292, #e91e63)' ?>;
        color: white; 
        padding: 40px; 
        border-radius: 15px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    /* 4. STATISTIC CARDS */
    .stat-card {
        background: white; 
        border-radius: 10px; 
        padding: 20px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
        height: 100%; /* Agar tinggi kartu sama */
    }
    .stat-card:hover { 
        transform: translateY(-5px); 
    }

    /* 5. BANNER PANDUAN */
    .guide-banner-seni { 
        background: #fff; 
        border: none; 
        border-radius: 15px; 
        border-left: 6px solid #e91e63; 
    }

    /* 6. KAK PADI AI CHAT INTERFACE (FIXED & RESPONSIVE) */
    #ai-chat-container {
        position: fixed; 
        bottom: 20px; 
        right: 20px; 
        z-index: 9999;
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        gap: 8px;
    }
    
    #ai-chat-button {
        width: 60px; 
        height: 60px; 
        border-radius: 50%;
        background: linear-gradient(135deg, #f06292 0%, #e91e63 100%);
        border: none; 
        color: white; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        cursor: pointer; 
        transition: 0.3s; 
        display: flex; 
        align-items: center; 
        justify-content: center;
    }
    
    #ai-chat-button:hover { 
        transform: scale(1.1); 
    }

    .ai-label {
        background: white; 
        padding: 4px 12px; 
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        font-weight: bold;
        color: #e91e63; 
        font-size: 0.75rem; 
        border: 1px solid #eee;
        text-align: center; 
        white-space: nowrap; 
        pointer-events: none;
    }

    #ai-chat-window {
        width: 350px; 
        height: 450px; 
        background: white; 
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
        display: none; /* Muncul via JS */
        flex-direction: column; 
        overflow: hidden; 
        position: absolute;
        bottom: 100px; 
        right: 0;
    }

    .chat-header { 
        background: linear-gradient(135deg, #f06292 0%, #e91e63 100%); 
        padding: 15px; 
        color: white; 
        font-weight: bold; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
    }

    .chat-body { 
        flex: 1; 
        padding: 15px; 
        overflow-y: auto; 
        background: #f8f9fa; 
        display: flex; 
        flex-direction: column; 
        gap: 10px; 
    }

    .chat-footer { 
        padding: 10px; 
        background: white; 
        border-top: 1px solid #eee; 
        display: flex; 
        gap: 5px; 
    }

    /* 7. BUBBLE CHAT STYLING */
    .msg { 
        max-width: 80%; 
        padding: 10px 15px; 
        border-radius: 15px; 
        font-size: 0.9rem; 
        line-height: 1.4; 
    }
    
    .msg-ai { 
        background: #e9ecef; 
        align-self: flex-start; 
        border-bottom-left-radius: 2px; 
        color: #333; 
    }
    
    .msg-user { 
        background: #e91e63; 
        color: white; 
        align-self: flex-end; 
        border-bottom-right-radius: 2px; 
    }

    .typing { 
        font-style: italic; 
        font-size: 0.8rem; 
        color: #888; 
        margin-bottom: 5px; 
        display: none; 
    }
</style>
</head>
<body>

<?php if ($is_archive): ?>
    <div style="background: #ffc107; color: black; text-align: center; font-weight: bold; padding: 5px;">⚠️ MODE ARSIP SEMESTER 1</div>
<?php endif; ?>

<nav class="navbar navbar-dark <?= $is_archive ? 'bg-secondary' : 'bg-primary' ?> shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-palette me-2"></i> SENI RUPA | SISWA</a>
        <span class="navbar-text text-white">
            Halo, <b class="text-warning"><?php echo htmlspecialchars($nama_pengguna); ?></b> (Kelas <?php echo htmlspecialchars($level_kelas); ?>)
        </span>
        <a href="../logout.php" class="btn btn-warning fw-bold">Keluar</a>
    </div>
</nav>

<div class="container mt-5 mb-5">
    <div class="hero-card text-center mb-5">
        <h1 class="display-5 fw-bold"><i class="fas fa-paint-brush"></i> Ruang Kreativitas Seni</h1>
        <p class="lead mt-3">Selamat datang, <?php echo htmlspecialchars($nama_pengguna); ?>! Mari jelajahi dunia seni hari ini bersama <b>Guru <?php echo htmlspecialchars($nama_guru_pembimbing); ?></b>.</p>
        <p class="fs-4">Anda berada di <b>Kelas <?php echo htmlspecialchars($level_kelas); ?></b>.</p>
        <a href="../../../dashboard.php" class="btn btn-light mt-2 fw-bold text-primary border-2 shadow-sm">
            <i class="fas fa-th-large"></i> Pilih Mata Pelajaran
        </a>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card guide-banner-seni shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="fw-bold mb-1 text-primary"><i class="fas fa-info-circle me-2"></i> Panduan Belajar Seni</h5>
                        <p class="text-muted mb-0 small">Klik tombol di samping untuk petunjuk materi, kuis, dan bantuan AI.</p>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 rounded-pill mt-3 mt-md-0 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPanduanSeni">
                        <i class="fas fa-map me-2"></i> LIHAT PETUNJUK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row text-center mb-5">
        <div class="col-md-4 mb-3">
            <div class="stat-card h-100">
                <h5 class="card-title text-success">Materi Selesai (Lulus)</h5>
                <h1 class="text-success my-3 fw-bold"><?php echo $lulus_tampil; ?></h1>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card h-100">
                <h5 class="card-title text-primary">Tugas & Kuis Dikerjakan</h5>
                <h1 class="text-primary my-3 fw-bold"><?php echo $total_bab_dicoba; ?></h1>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card h-100">
                <h5 class="card-title text-success">Nilai Rata-Rata</h5>
                <h1 class="text-success my-3 fw-bold"><?php echo $skor_rata_rata_tampil; ?></h1>
            </div>
        </div>
    </div>

    <h3 class="text-center text-secondary mb-4">Aktivitas Belajar</h3>
    <div class="row g-4">
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4>Eksplorasi Materi Seni</h4>
                <p>Pelajari teknik menggambar, teori warna, dan sejarah seni di sini.</p>
                <a href="materi.php" class="btn btn-primary w-100 btn-lg">
                    <i class="fas fa-book"></i> Buka Materi
                </a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4>Riwayat & Progres</h4>
                <p>Lihat hasil karya kuis dan perkembangan nilaimu.</p>
                <a href="riwayat_progress.php" class="btn btn-success w-100 btn-lg">
                    <i class="fas fa-history"></i> Cek Riwayat
                </a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4>Uji Kompetensi (Try Out)</h4>
                <p>Kerjakan ujian untuk mengukur pemahaman senimu.</p>
                <a href="daftar_tryout.php" class="btn btn-primary w-100 btn-lg mt-2">
                    <i class="fas fa-file-alt"></i> Mulai Ujian
                </a>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="stat-card p-4 h-100">
                <h4>Papan Peringkat</h4>
                <p>Lihat siapa yang menjadi seniman terbaik di kelas!</p>
                <a href="leaderboard.php" class="btn btn-warning w-100 btn-lg mt-2 text-white">
                    <i class="fas fa-medal"></i> Buka Peringkat
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPanduanSeni" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold text-primary"><i class="fas fa-palette me-2"></i> Langkah Belajar Seni</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex mb-4">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; flex-shrink: 0; font-weight: bold;">1</div>
                    <div class="ms-3">
                        <h6 class="fw-bold mb-1">Pelajari Materi</h6>
                        <p class="small text-muted mb-0">Baca materi seni rupa dengan teliti di menu <b>Buka Materi</b>.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; flex-shrink: 0; font-weight: bold; background-color: #f06292 !important;">2</div>
                    <div class="ms-3">
                        <h6 class="fw-bold mb-1">Tanya Kak PADI AI</h6>
                        <p class="small text-muted mb-0">Butuh ide atau bingung istilah seni? Tanya asisten AI di pojok bawah.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; flex-shrink: 0; font-weight: bold;">3</div>
                    <div class="ms-3">
                        <h6 class="fw-bold mb-1">Selesaikan Tugas</h6>
                        <p class="small text-muted mb-0">Kerjakan kuis dan lihat namamu di <b>Papan Peringkat</b>.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-primary w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">SAYA MENGERTI!</button>
            </div>
        </div>
    </div>
</div>

<div id="ai-chat-container">
    <div id="ai-chat-window" style="display:none; flex-direction:column; position:absolute; bottom:100px; right:0; width:350px; height:450px; background:white; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
        <div class="chat-header" style="background: linear-gradient(135deg, #f06292 0%, #e91e63 100%); padding: 15px; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-robot me-2"></i> Asisten Seni PADI</span>
            <button class="btn-close btn-close-white" onclick="toggleChat()"></button>
        </div>
        <div class="chat-body" id="chatBody" style="flex: 1; padding: 15px; overflow-y: auto; background: #f8f9fa; display: flex; flex-direction: column; gap: 10px;">
            <div class="msg msg-ai" style="background: #e9ecef; padding: 10px 15px; border-radius: 15px; align-self: flex-start; border-bottom-left-radius: 2px;">Halo <strong><?= htmlspecialchars($nama_pengguna) ?></strong>! Ada yang bisa saya bantu seputar Seni Rupa? 😊</div>
            <div id="typingIndicator" class="typing" style="display:none; font-style:italic; font-size:0.8rem; color:#888;">AI sedang berpikir...</div>
        </div>
        <div class="chat-footer" style="padding: 10px; background: white; border-top: 1px solid #eee; display: flex; gap: 5px;">
            <input type="text" id="chatInput" class="form-control form-control-sm" placeholder="Tanya tentang seni..." onkeypress="handleKeyPress(event)">
            <button class="btn btn-sm btn-primary" onclick="sendMessage()" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    <button id="ai-chat-button" onclick="toggleChat()" style="width: 60px; height: 60px; border-radius: 50%; border: none; color: white; box-shadow: 0 5px 15px rgba(0,0,0,0.3); cursor: pointer; display: flex; align-items: center; justify-content: center;">
        <i class="fas fa-comment-dots fa-2x"></i>
    </button>
    <div class="ai-label shadow-sm" id="aiLabel" style="background:white; padding:4px 12px; border-radius:10px; margin-top:8px; font-weight:bold; color:#e91e63; font-size:0.75rem; border:1px solid #eee;">Tanya Kak PADI AI</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. FUNGSI BUKA/TUTUP CHAT
    function toggleChat() {
        const windowChat = document.getElementById('ai-chat-window');
        const label = document.getElementById('aiLabel');
        
        // Menggunakan 'flex' agar struktur di dalam window tetap rapi
        if (windowChat.style.display === 'flex') {
            windowChat.style.display = 'none';
            label.style.display = 'block';
        } else {
            windowChat.style.display = 'flex';
            label.style.display = 'none';
        }
    }

    // 2. FUNGSI PENGIRIMAN PESAN
    function handleKeyPress(e) { 
        if (e.key === 'Enter') sendMessage(); 
    }

    async function sendMessage() {
        const input = document.getElementById('chatInput');
        const body = document.getElementById('chatBody');
        const sendBtn = document.getElementById('sendBtn');
        const typing = document.getElementById('typingIndicator');
        const pesan = input.value.trim();
        
        if (!pesan) return;
        
        // Tampilkan pesan user di layar
        appendMessage('user', pesan);
        
        // Reset input dan matikan tombol sementara (loading)
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        typing.style.display = 'block';
        body.scrollTop = body.scrollHeight;

        try {
            // Mengirim data ke backend
            const response = await fetch('proses_ai_asisten.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    pesan: pesan,
                    mapel: 'Seni Rupa' // Memberitahu AI konteks mata pelajarannya
                })
            });
            
            const data = await response.json();
            typing.style.display = 'none';
            
            // Tampilkan jawaban dari Kak PADI AI
            appendMessage('ai', data.jawaban);
            
        } catch (error) {
            typing.style.display = 'none';
            appendMessage('ai', "Waduh, koneksi terputus. Coba tanya lagi ya!");
        } finally {
            // Aktifkan kembali input
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    // 3. FUNGSI MENAMPILKAN BUBBLE CHAT
    function appendMessage(sender, text) {
        const body = document.getElementById('chatBody');
        const msgDiv = document.createElement('div');
        
        // Menentukan class berdasarkan pengirim (msg-user atau msg-ai)
        // Style-nya diambil dari CSS yang kita buat tadi
        msgDiv.className = `msg msg-${sender}`;
        msgDiv.innerHTML = text;
        
        // Masukkan pesan sebelum indikator "AI sedang berpikir"
        body.insertBefore(msgDiv, document.getElementById('typingIndicator'));
        
        // Auto-scroll ke bawah agar pesan terbaru kelihatan
        body.scrollTop = body.scrollHeight;
    }
</script>
</body>
</html>