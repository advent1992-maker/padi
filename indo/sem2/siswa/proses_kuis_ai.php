<?php
// JANGAN DIHAPUS: Ini untuk melihat error aslinya di Console
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek apakah file-file ini benar ada di foldernya?
if (!file_exists('../config/koneksi.php')) { die("ERROR: File koneksi.php tidak ditemukan di jalur ../config/"); }
if (!file_exists('../config/ai_helper.php')) { die("ERROR: File ai_helper.php tidak ditemukan di jalur ../config/"); }

require_once '../../../config/koneksi.php';
require_once '../../../config/ai_helper.php';
require_once '../../../config/config_ai.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['html' => 'Data input kosong!']);
    exit;
}

// Cek apakah fungsi panggil_gemini tersedia?
if (!function_exists('panggil_gemini')) {
    echo json_encode(['html' => 'Fungsi panggil_gemini tidak ditemukan di ai_helper.php!']);
    exit;
}

$teks = $data['teks'] ?? 'Materi kosong';
$prompt = "Buat 1 soal pilihan ganda singkat dari teks: $teks";

// Panggil Gemini
$hasil = panggil_gemini($prompt, GEMINI_MODEL_CHAT);

echo json_encode(['html' => $hasil]);