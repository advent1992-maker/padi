<?php
require_once '../config/session.php';
require_once '../config/koneksi.php';

$id_user = $_GET['id'] ?? null;
$role_user = $_GET['role'] ?? null;

// Pastikan koneksi utama tersedia
if (!isset($conn)) {
    die("Koneksi database pusat gagal.");
}

if (!$id_user || !$role_user) {
    die("Parameter tidak lengkap.");
}

if ($role_user === 'guru') {
    // Daftar mapel sesuai database fisik (Pancasila gabung di Mulok)
    $mapels = [
        ['folder' => 'ipas'], ['folder' => 'mtk'], ['folder' => 'indo'],
        ['folder' => 'englis'], ['folder' => 'pjok'], ['folder' => 'pai'], 
        ['folder' => 'mulok'], ['folder' => 'seni']
    ];

    foreach ($mapels as $m) {
        $db_target = $prefix . "db_" . $m['folder'] . "_sm" . $semester;
        
        // Gunakan kredensial yang sesuai dari config
        $temp_db = @mysqli_connect($host, $user, $pass, $db_target);

        if ($temp_db) {
            // Tentukan prefix tabel jika folder adalah mulok (untuk pancasila)
            // Namun karena ini menghapus user guru, kita sapu bersih kedua jenis tabel
            $prefixes = ($m['folder'] == 'mulok') ? ['', 'panca_'] : [''];

            foreach ($prefixes as $pf) {
                // 1. Ambil ID materi untuk hapus soal
                $res_materi = mysqli_query($temp_db, "SELECT id FROM {$pf}materi WHERE id_guru = '$id_user'");
                while ($materi = mysqli_fetch_assoc($res_materi)) {
                    $materi_id = $materi['id'];
                    mysqli_query($temp_db, "DELETE FROM {$pf}soal WHERE materi_id = '$materi_id'");
                }

                // 2. Hapus materi & tryout guru
                mysqli_query($temp_db, "DELETE FROM {$pf}materi WHERE id_guru = '$id_user'");
                mysqli_query($temp_db, "DELETE FROM {$pf}tryout_master WHERE id_guru = '$id_user'");

                // 3. Bersihkan riwayat semua siswa bimbingan guru ini
                $res_siswa = $conn->query("SELECT id FROM users WHERE id_guru = '$id_user'");
                while ($siswa = $res_siswa->fetch_assoc()) {
                    $id_s = $siswa['id'];
                    mysqli_query($temp_db, "DELETE FROM {$pf}riwayat_kuis WHERE id_user = '$id_s'");
                    mysqli_query($temp_db, "DELETE FROM {$pf}riwayat_tryout WHERE id_user = '$id_s'");
                    
                    if ($m['folder'] == 'seni') {
                        mysqli_query($temp_db, "DELETE FROM praktek_siswa WHERE id_siswa = '$id_s'");
                    }
                }
            }
            mysqli_close($temp_db);
        }
    }
}

// Terakhir hapus user dari database pusat
$stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt_del->bind_param("i", $id_user);

if ($stmt_del->execute()) {
    header("Location: users.php?role=$role_user&pesan=hapus_berhasil");
} else {
    echo "Error hapus user: " . $conn->error;
}
?>