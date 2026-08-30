<?php
require_once 'config/session.php';
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: index.php"); exit;
}

$my_id = $_SESSION['user_id'];

// --- 1. PROSES KIRIM PERMINTAAN ---
if (isset($_POST['kirim_request'])) {
    $id_penerima = (int)$_POST['id_penerima'];
    $mapel       = mysqli_real_escape_string($conn, $_POST['mapel']);

    $cek = mysqli_query($conn, "SELECT id FROM kolaborasi_akses WHERE id_pengaju = $my_id AND id_penerima = $id_penerima AND mapel = '$mapel' AND status = 'pending'");
    
    if (mysqli_num_rows($cek) == 0) {
        $query = "INSERT INTO kolaborasi_akses (id_pengaju, id_penerima, mapel, status) VALUES ($my_id, $id_penerima, '$mapel', 'pending')";
        if(mysqli_query($conn, $query)) {
            $_SESSION['notif_kolab'] = ["type" => "success", "msg" => "✅ Permintaan berhasil dikirim! Menunggu persetujuan."];
        }
    } else {
        $_SESSION['notif_kolab'] = ["type" => "warning", "msg" => "⚠️ Anda sudah mengirim permintaan ini sebelumnya."];
    }
    header("Location: dashboard_guru.php"); exit;
}

// --- 2. PROSES SETUJU / TOLAK ---
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id_kolaborasi = (int)$_GET['id'];
    $aksi = $_GET['aksi'];
    $status = ($aksi === 'setuju') ? 'disetujui' : 'ditolak';

    mysqli_query($conn, "UPDATE kolaborasi_akses SET status = '$status' WHERE id = $id_kolaborasi AND id_penerima = $my_id");
    
    $_SESSION['notif_kolab'] = ["type" => "info", "msg" => "✅ Permintaan telah di" . $status . "."];
    header("Location: dashboard_guru.php"); exit;
}