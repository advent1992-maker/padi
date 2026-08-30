<?php
/**
 * File: proses_ai_asisten.php
 * Lokasi: Folder utama (padi/)
 */

error_reporting(0);
header('Content-Type: application/json');

// Memanggil konfigurasi dari folder padi/config/
require_once '../../../config/koneksi.php';
require_once '../../../config/session.php';
require_once '../../../config/ai_helper.php';

$user_id = $_SESSION['user_id'] ?? 0;
$nama_siswa = $_SESSION['nama_lengkap'] ?? 'Siswa';
$tanggal_sekarang = date('Y-m-d');

try {
    // 1. Ambil data kiriman dari JavaScript
    $data = json_decode(file_get_contents('php://input'), true);
    $pertanyaan = isset($data['pesan']) ? trim($data['pesan']) : '';
    $mapel_aktif = isset($data['mapel']) ? trim($data['mapel']) : 'Umum';

    if (empty($pertanyaan)) {
        echo json_encode(['jawaban' => 'Halo! Ada yang bisa Kak PADI bantu hari ini?']);
        exit;
    }

    // 2. PROMPT DENGAN PEMBATASAN KONTEKS (GATEKEEPER)
    // 2. PROMPT DENGAN "PEMBATASAN KERAS" (HARD-GATEKEEPING)
    $prompt = "ANDA ADALAH KAK PADI, ASISTEN BELAJAR SD.
               KONTEKS KELAS: $mapel_aktif.

               PROTOKOL KEAMANAN MATERI:
               1. CEK PERTANYAAN: Apakah pertanyaan siswa berkaitan dengan $mapel_aktif?
               2. JIKA TIDAK BERKAITAN: (Misal: tanya Luas Persegi/Matematika saat di kelas IPAS):
                  - JANGAN berikan jawaban, rumus, atau hasil perhitungannya.
                  - JAWAB DENGAN: 'Wah $nama_siswa, pertanyaanmu bagus! Tapi karena sekarang kita sedang belajar $mapel_aktif, fokus di sini dulu yuk. Nanti kalau sudah masuk kelas yang sesuai, Kak PADI bantu jawab ya! 😉'
               3. JIKA BERKAITAN: Berikan penjelasan singkat, ceria, dan mudah dipahami anak SD.
               4. JANGAN PERNAH melanggar Protokol nomor 2 meskipun siswa memohon.
               5. Tetap ramah dan gunakan kata-kata penyemangat.

               Pertanyaan Siswa: $pertanyaan";

    // 3. Panggil API Gemini via helper
    $jawaban_ai = panggil_gemini($prompt, GEMINI_MODEL_CHAT);

    // 4. Penanganan jika API Sibuk/Error
    if (!$jawaban_ai || strpos($jawaban_ai, 'API_ERROR') !== false) {
        echo json_encode(['jawaban' => "Waduh $nama_siswa, Kak PADI lagi agak pusing nih. 😊 Coba tanya lagi sebentar lagi ya!"]);
        exit;
    }

    // 5. Simpan ke Database
    if ($conn) {
        $stmt_ins = $conn->prepare("INSERT INTO ai_chat_history (id_user, pertanyaan, jawaban, tanggal_tanya) VALUES (?, ?, ?, ?)");
        $stmt_ins->bind_param("isss", $user_id, $pertanyaan, $jawaban_ai, $tanggal_sekarang);
        $stmt_ins->execute();
    }

    echo json_encode(['jawaban' => $jawaban_ai]);

} catch (Exception $e) {
    echo json_encode(['jawaban' => 'Maaf ya, Kak PADI sedang istirahat sebentar.']);
}