<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

if (isset($_POST['simpan_adm'])) {
    $id_materi_padi = $_POST['id_materi_padi']; 
    $id_guru        = $_SESSION['user_id'];
    $folder         = $_POST['folder']; // Ini adalah nama folder (ipas, mtk, dll)
    $jenis          = $_POST['jenis'];
    $konten         = $_POST['konten'];
    $judul_materi   = $_POST['judul']; // Tambahan untuk menyimpan judul

    // Query INSERT dengan id_materi_padi agar tidak NULL lagi
    $query = "INSERT INTO guru_administrasi (id_guru, mapel_folder, id_materi_padi, jenis_adm, judul_materi, isi_konten) 
              VALUES ('$id_guru', '$folder', '$id_materi_padi', '$jenis', '$judul_materi', '$konten')";

    if (mysqli_query($conn, $query)) {
        // Redirect kembali ke halaman mapel dengan parameter yang benar
        header("Location: guru_adm_mapel.php?folder=$folder&status=sukses");
        exit();
    } else {
        echo "Gagal menyimpan: " . mysqli_error($conn);
    }
}
?>