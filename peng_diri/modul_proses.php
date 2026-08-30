<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

// Koneksi ke DB Peng Diri
$conn_pusat = $conn;

$my_id = $_SESSION['user_id'];

// A. PROSES SIMPAN MODUL BARU (DARI MODAL)
if (isset($_POST['simpan_modul'])) {
    $judul = mysqli_real_escape_string($conn_pusat, $_POST['judul_materi']);
    $mapel = mysqli_real_escape_string($conn_pusat, $_POST['mapel']);
    $kat = mysqli_real_escape_string($conn_pusat, $_POST['kategori']);

    $q = "INSERT INTO materi_peng_diri (id_guru, kategori, mapel, judul_materi, isi_materi) 
          VALUES ('$my_id', '$kat', '$mapel', '$judul', '')";
    
    if (mysqli_query($conn_pusat, $q)) {
        $new_id = mysqli_insert_id($conn_pusat);
        // Langsung arahkan ke editor setelah buat judul
        header("Location: modul_editor.php?id=$new_id&msg=created");
    }
}

// B. PROSES UPDATE ISI MODUL (DARI EDITOR)
if (isset($_POST['update_isi_modul'])) {
    $id = $_POST['id_modul'];
    $isi = mysqli_real_escape_string($conn_pusat, $_POST['isi_materi']);
    $judul = mysqli_real_escape_string($conn_pusat, $_POST['judul_materi']);
    $kat = $_POST['kategori'];

    $q = "UPDATE materi_peng_diri SET judul_materi='$judul', isi_materi='$isi' WHERE id='$id'";
    
    if (mysqli_query($conn_pusat, $q)) {
        header("Location: modul_list.php?kat=$kat&msg=updated");
    }
}

// C. PROSES HAPUS
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = $_GET['id'];
    $kat = $_GET['kat'];
    
    $q = "DELETE FROM materi_peng_diri WHERE id='$id' AND id_guru='$my_id'";
    if (mysqli_query($conn_pusat, $q)) {
        header("Location: modul_list.php?kat=$kat&msg=deleted");
    }
}