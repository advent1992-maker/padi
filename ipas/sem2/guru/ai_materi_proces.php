<?php
require_once '../config/koneksi.php';
require_once '../../../config/ai_helper.php';
require_once '../config/session.php';

// Cek proteksi
if (!isset($_SESSION['user_id'])) { die("Sesi habis."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data yang dikirim dari Javascript
    $id_materi = (int)($_POST['id_materi'] ?? 0);
    $judul     = $_POST['judul'] ?? '';
    $tipe      = $_POST['tipe'] ?? '';

    if ($id_materi === 0 || empty($judul)) {
        echo "Error: ID Materi atau Judul tidak terbaca.";
        exit();
    }

    if ($tipe == 'materi_html') {
        // 1. Minta konten ke Gemini
       $prompt = "Ubahlah materi mentah berikut menjadi sebuah file HTML slide interaktif yang utuh.

               MATERI MENTAH:
               Judul: $judul
               Isi: $isi_mentah

               KETENTUAN FORMAT KODE:
               1. Gunakan struktur SLIDE (minimal 3-4 slide).
               2. Sertakan CSS Lengkap di dalam <style>:
                  - body { font-family: 'Segoe UI'; background: transparent; }
                  - .slide-container { background: white; border-radius: 15px; border: 1px solid #ddd; }
                  - .slide { display: none; padding: 20px; animation: fadeIn 0.4s; min-height: 400px; }
                  - .active { display: block; }
                  - .nav-btn { background: #2e7d32; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: bold; }
               3. Sertakan Javascript Navigasi: Fungsi changeSlide(n) untuk pindah slide.
               4. Berikan output kode HTML UTUH (dari <!DOCTYPE html> sampai </html>).
               5. Pastikan desainnya responsif untuk HP.
               6. TANPA teks penjelasan, TANPA komentar kode (seperti //hapus class).";

        $hasil_ai = panggil_gemini($prompt);

        // 2. Langsung UPDATE ke kolom konten_materi
        $stmt = $db_mapel->prepare("UPDATE materi SET konten_materi = ? WHERE id = ?");
        $stmt->bind_param("si", $hasil_ai, $id_materi);

        if ($stmt->execute()) {
            echo "SUKSES: Materi berhasil dibuat dan disimpan otomatis ke database!";
        } else {
            echo "Error Database: " . $db_mapel->error;
        }
        $stmt->close();
    } else {
        // Jika pilih RPM, cukup tampilkan teksnya saja (tidak simpan ke materi)
        echo panggil_gemini("Buatkan RPM untuk materi '$judul' kelas 5 SD.");
    }
}