<?php
/**
 * FILE: materi_adopsi_proses.php
 * FUNGSI: Mengadopsi Materi DAN Soal Kuis secara otomatis
 */
require_once '../config/koneksi.php';
require_once '../config/session.php';

if (!in_array($_SESSION['role'] ?? '', ['guru', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id_guru_pilihan'] ?? $_SESSION['user_id'];
$id_materi_asal = isset($_GET['id_materi']) ? (int)$_GET['id_materi'] : 0;

if ($id_materi_asal > 0) {
    $db_mapel->begin_transaction();
    try {
        // 1. Ambil data materi asli (DITAMBAHKAN: konten_materi)
        $stmt = $db_mapel->prepare("SELECT judul, deskripsi, level_kategori, file_path, konten_materi FROM panca_materi WHERE id = ?");
        $stmt->bind_param("i", $id_materi_asal);
        $stmt->execute();
        $materi_asal = $stmt->get_result()->fetch_assoc();

        if (!$materi_asal) throw new Exception("Materi asal tidak ditemukan.");

        // 2. Simpan materi baru atas nama Anda (DITAMBAHKAN: konten_materi)
        $stmt_ins = $db_mapel->prepare("INSERT INTO panca_materi (judul, deskripsi, level_kategori, file_path, konten_materi, id_guru) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("sssssi",
            $materi_asal['judul'],
            $materi_asal['deskripsi'],
            $materi_asal['level_kategori'],
            $materi_asal['file_path'],
            $materi_asal['konten_materi'], // Data AI tercopy di sini
            $user_id
        );
        $stmt_ins->execute();
        $id_materi_baru = $db_mapel->insert_id;

        // 3. ADOPSI SOAL OTOMATIS (Tetap sama seperti kode Bapak)
        $query_soal = "INSERT INTO panca_soal (
                            materi_id, pertanyaan, gambar_url,
                            opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar,
                            opsi_a_gambar_url, opsi_b_gambar_url, opsi_c_gambar_url, opsi_d_gambar_url
                       )
                       SELECT
                            ?, pertanyaan, gambar_url,
                            opsi_a, opsi_b, opsi_c, opsi_d, jawaban_benar,
                            opsi_a_gambar_url, opsi_b_gambar_url, opsi_c_gambar_url, opsi_d_gambar_url
                       FROM panca_soal WHERE materi_id = ?";

        $stmt_soal = $db_mapel->prepare($query_soal);
        $stmt_soal->bind_param("ii", $id_materi_baru, $id_materi_asal);
        $stmt_soal->execute();

        $db_mapel->commit();
        $_SESSION['pesan_sukses'] = "✅ Berhasil! Materi '" . $materi_asal['judul'] . "' telah diadopsi.";
        
        header("Location: materi_list.php");
        exit();

    } catch (Exception $e) {
        $db_mapel->rollback();
        $_SESSION['pesan_error'] = "❌ Gagal mengadopsi: " . $e->getMessage();
        header("Location: materi_list.php");
        exit();
    }
} else {
    header("Location: materi_list.php");
    exit();
}