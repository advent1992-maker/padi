<?php
/**
 * File: proses_ai_asisten.php
 * Lokasi: Folder utama (padi/)
 */

error_reporting(0);
header('Content-Type: application/json');

require_once '../../../config/koneksi.php';
require_once '../../../config/session.php';
require_once '../../../config/ai_helper.php';

$user_id = $_SESSION['user_id'] ?? 0;
$nama_siswa = $_SESSION['nama_lengkap'] ?? 'Siswa';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $pertanyaan = isset($data['pesan']) ? trim($data['pesan']) : '';
    
    // 1. Ambil data mapel dari JavaScript (prioritas utama)
    $mapel_aktif = isset($data['mapel']) ? trim($data['mapel']) : '';

    // 2. Jika dari JS kosong, deteksi otomatis dari folder URL (Referer)
    // Sesuai dengan 9 Mapel yang ada di struktur folder Bapak
    if (empty($mapel_aktif)) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (strpos($referer, '/ipas/') !== false) $mapel_aktif = 'IPAS';
        elseif (strpos($referer, '/mtk/') !== false) $mapel_aktif = 'Matematika';
        elseif (strpos($referer, '/indo/') !== false) $mapel_aktif = 'Bahasa Indonesia';
        elseif (strpos($referer, '/englis/') !== false) $mapel_aktif = 'Bahasa Inggris';
        elseif (strpos($referer, '/panca/') !== false) $mapel_aktif = 'Pendidikan Pancasila';
        elseif (strpos($referer, '/pjok/') !== false) $mapel_aktif = 'PJOK';
        elseif (strpos($referer, '/pai/') !== false) $mapel_aktif = 'PAI';
        elseif (strpos($referer, '/mulok/') !== false) $mapel_aktif = 'Muatan Lokal';
        elseif (strpos($referer, '/seni/') !== false) $mapel_aktif = 'Seni Budaya';
        else $mapel_aktif = 'Portal Belajar';
    }

    if (empty($pertanyaan)) {
        echo json_encode(['jawaban' => 'Halo! Ada yang bisa Kak PADI bantu?']);
        exit;
    }

    // 3. PROMPT DENGAN PEMBATASAN KONTEKS KERAS
    $prompt = "Kamu adalah Kak PADI, asisten belajar SD yang pintar.
               SAAT INI SISWA BERADA DI KELAS: $mapel_aktif.

               ATURAN DISIPLIN:
               1. Kamu WAJIB menolak menjawab jika pertanyaan siswa TIDAK BERHUBUNGAN dengan materi $mapel_aktif.
               2. JANGAN berikan rumus, jawaban, atau penjelasan materi mapel lain.
               3. Jika siswa bertanya mapel lain (Contoh: tanya Matematika saat di kelas IPAS/Inggris), katakan: 'Wah $nama_siswa, pertanyaan itu untuk kelas lain ya. Sekarang kita di kelas $mapel_aktif, fokus di sini dulu yuk!'
               4. Balas sapaan dengan ramah dan tetap gunakan bahasa anak SD yang ceria.

               Pertanyaan Siswa: $pertanyaan";

    $jawaban_ai = panggil_gemini($prompt, GEMINI_MODEL_CHAT);

    // 4. Simpan Riwayat
    if ($conn) {
        $tanggal_sekarang = date('Y-m-d');
        $stmt_ins = $conn->prepare("INSERT INTO ai_chat_history (id_user, pertanyaan, jawaban, tanggal_tanya) VALUES (?, ?, ?, ?)");
        $stmt_ins->bind_param("isss", $user_id, $pertanyaan, $jawaban_ai, $tanggal_sekarang);
        $stmt_ins->execute();
    }

    echo json_encode(['jawaban' => $jawaban_ai]);

} catch (Exception $e) {
    echo json_encode(['jawaban' => 'Maaf, Kak PADI sedang istirahat sebentar.']);
}