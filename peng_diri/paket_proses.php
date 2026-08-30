<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// Koneksi ke DB Peng Diri
$conn_pusat = $conn;

if (!$conn_pusat) {
    die("Koneksi Gagal: " . mysqli_connect_error());
}

// ==========================================
// 1. PROSES HAPUS (DELETE)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id_paket = (int)$_GET['id'];
    $kategori = mysqli_real_escape_string($conn_pusat, $_GET['kat']);

    // A. Hapus Riwayat Nilai (Kolomnya: id_materi)
    // Sesuai foto Bapak, kolom penghubung adalah id_materi
    mysqli_query($conn_pusat, "DELETE FROM riwayat_kuis WHERE id_materi = $id_paket");

    // B. Hapus Soal (Asumsi kolom di tabel soal juga id_materi atau paket_id)
    // Jika di tabel OSN kolomnya juga id_materi, silakan ganti paket_id di bawah jadi id_materi
    mysqli_query($conn_pusat, "DELETE FROM $kategori WHERE paket_id = $id_paket");

    // C. Hapus Paket Utama
    $query_hapus = "DELETE FROM paket_peng_diri WHERE id = $id_paket";
    
    if (mysqli_query($conn_pusat, $query_hapus)) {
        header("Location: paket_list.php?kat=$kategori&msg=deleted");
        exit;
    } else {
        echo "Gagal menghapus: " . mysqli_error($conn_pusat);
    }
}

// ==========================================
// 2. PROSES SIMPAN PAKET BARU
// ==========================================
if (isset($_POST['simpan_paket'])) {
    $nama_paket = mysqli_real_escape_string($conn_pusat, $_POST['nama_paket']);
    $kategori   = mysqli_real_escape_string($conn_pusat, $_POST['kategori']);
    $mapel      = mysqli_real_escape_string($conn_pusat, $_POST['mapel']); 
    $id_guru    = $_SESSION['user_id'];
    $durasi      = intval($_POST['durasi_menit'] ?? 60);

    $query = "INSERT INTO paket_peng_diri (nama_paket, kategori, id_guru, mapel, durasi_menit, tanggal_buat) 
              VALUES ('$nama_paket', '$kategori', '$id_guru', '$mapel', '$durasi', NOW())";
    
    if (mysqli_query($conn_pusat, $query)) {
        header("Location: paket_list.php?kat=$kategori&msg=success");
        exit;
    }
}

// ==========================================
// 3. PROSES UPDATE (EDIT) PAKET
// ==========================================
if (isset($_POST['update_paket'])) {
    $id_paket   = (int)$_POST['id_paket'];
    $nama_paket = mysqli_real_escape_string($conn_pusat, $_POST['nama_paket']);
    $mapel      = mysqli_real_escape_string($conn_pusat, $_POST['mapel']); 
    $kategori   = $_POST['kategori'];
    $durasi     = intval($_POST['durasi_menit'] ?? 30);

    $query = "UPDATE paket_peng_diri SET nama_paket = '$nama_paket', mapel = '$mapel', durasi_menit = '$durasi' WHERE id = $id_paket";
    
    if (mysqli_query($conn_pusat, $query)) {
        header("Location: paket_list.php?kat=$kategori&msg=updated");
        exit;
    }
}

header("Location: paket_list.php");
exit;
?>