<?php
// Sembunyikan error agar tidak merusak format JSON
error_reporting(0);
header('Content-Type: application/json');

// Jalur naik 3 tingkat ke config portal
require_once '../../../config/koneksi.php';
require_once '../../../config/session.php';
require_once '../../../config/ai_helper.php';

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

try {
    // 1. Ambil 3 Materi & Kuis Terbaru
    $materi_list = [];
    $q_materi = $db_mapel->query("SELECT judul FROM materi ORDER BY id DESC LIMIT 3");
    if($q_materi) {
        while($r = $q_materi->fetch_assoc()) { $materi_list[] = $r['judul']; }
    }

    // 2. Ambil 3 Tryout Terbaru
    $tryout_list = [];
    $q_to = $db_mapel->query("SELECT judul FROM tryout_master ORDER BY id DESC LIMIT 3");
    if($q_to) {
        while($r = $q_to->fetch_assoc()) { $tryout_list[] = $r['judul']; }
    }

    // 3. Rakit Konteks untuk AI
    $konteks = "Informasi Terbaru di Kelas:\n";
    $konteks .= "- Materi & Kuis Baru: " . (empty($materi_list) ? "Belum ada" : implode(", ", $materi_list)) . "\n";
    $konteks .= "- Tryout Baru: " . (empty($tryout_list) ? "Belum ada" : implode(", ", $tryout_list)) . "\n";

    // 4. Ambil Input Siswa
    $data = json_decode(file_get_contents('php://input'), true);
    $pertanyaan = isset($data['pesan']) ? trim($data['pesan']) : '';

    if (empty($pertanyaan)) {
        echo json_encode(['jawaban' => 'Ketik sesuatu dulu ya...']);
        exit;
    }

    // 5. Prompt AI (Kepribadian "Kak PADI")
    $prompt = "Kamu adalah Kak PADI, asisten belajar SD yang ceria, pintar, dan sangat ramah.
DATA KELAS SAAT INI (Gunakan hanya jika relevan dengan pertanyaan siswa):
$konteks

ATURAN MENJAWAB:
1. Jika siswa menyapa atau bertanya kabar, balas dengan ramah dan ceria.
2. Jika siswa bertanya 'ada materi/tugas apa?', baru jelaskan daftar materi di atas.
3. Jika siswa minta bantuan belajar, tawarkan bantuan untuk menjelaskan materi atau menjawab pertanyaan sulit.
4. Gunakan bahasa anak-anak yang menyemangati (yuk, hebat, kamu pasti bisa).
5. JANGAN langsung memberikan daftar materi jika siswa tidak memintanya.

Pertanyaan Siswa: $pertanyaan";

    // 6. Panggil Gemini
    $jawaban_ai = panggil_gemini($prompt, GEMINI_MODEL_CHAT);

    // --- PERBAIKAN DI SINI: Penanganan API Error / High Demand ---
    if (!$jawaban_ai || strpos($jawaban_ai, 'API_ERROR') !== false || strpos($jawaban_ai, 'high demand') !== false || strpos($jawaban_ai, 'overloaded') !== false) {
        $jawaban_error = "Waduh, Kak PADI lagi agak pusing nih karena banyak teman-teman yang tanya sekaligus. 😊 Coba kirim ulang pesanmu sebentar lagi ya!";
        echo json_encode(['jawaban' => $jawaban_error]);
        exit;
    }

    // 7. Simpan ke Database Portal (Hanya jika jawaban sukses)
    if ($conn) {
        $stmt_ins = $conn->prepare("INSERT INTO ai_chat_history (id_user, pertanyaan, jawaban, tanggal_tanya) VALUES (?, ?, ?, ?)");
        $stmt_ins->bind_param("isss", $user_id, $pertanyaan, $jawaban_ai, $tanggal_sekarang);
        $stmt_ins->execute();
    }

    echo json_encode(['jawaban' => $jawaban_ai]);

} catch (Exception $e) {
    echo json_encode(['jawaban' => 'Maaf ya, Kak PADI sedang istirahat sebentar. Coba lagi nanti!']);
}