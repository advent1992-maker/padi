<?php
// ======================================================================================
// HAPUS_PENUGASAN_TRYOUT.PHP (SISI GURU) - Handler Pembatalan Penugasan Try Out
// Menghapus baris dari riwayat_tryout yang berstatus 'DIBUAT'
// ======================================================================================
require_once '../config/koneksi.php';
require_once '../config/session.php';
require_once '../config/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'guru') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['riwayat_ids_to_delete'])) {
    header("Location: manajemen_tryout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ids_str = $_POST['riwayat_ids_to_delete'];
$id_tryout_redirect = (int)$_POST['id_tryout_redirect'];

$ids_array = array_map('intval', explode(',', $ids_str));
$ids_array = array_filter($ids_array, function($id) {
    return $id > 0;
});

if (empty($ids_array)) {
    $_SESSION['error_message'] = "Tidak ada ID riwayat yang valid untuk dibatalkan.";
    header("Location: penugasan_tryout.php?id_tryout={$id_tryout_redirect}");
    exit();
}

$placeholders = implode(',', array_fill(0, count($ids_array), '?'));
$types = str_repeat('i', count($ids_array)) . 'i'; // Tambah satu 'i' untuk $user_id
$params = array_merge($ids_array, [$user_id]);

// Query untuk menghapus penugasan (riwayat)
// HANYA jika statusnya 'DIBUAT' (belum dikerjakan) dan guru yang menghapus adalah pembuatnya
$query = "
    DELETE FROM riwayat_tryout
    WHERE id IN ({$placeholders})
    AND id_guru = ?
    AND status = 'DIBUAT'
";

$stmt = $db_mapel->prepare($query);

// Menggunakan call_user_func_array untuk bind_param
if ($stmt) {
    $bind_params = array_merge([$types], $params);
    $ref_params = [];
    foreach ($bind_params as $key => $value) {
        $ref_params[$key] = &$bind_params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $ref_params);

    if ($stmt->execute()) {
        $rows_affected = $stmt->affected_rows;
        if ($rows_affected > 0) {
            $_SESSION['success_message'] = "Berhasil membatalkan {$rows_affected} penugasan (riwayat) Try Out.";
        } else {
            $_SESSION['error_message'] = "Gagal membatalkan. Mungkin Try Out sudah mulai dikerjakan atau Anda tidak memiliki izin.";
        }
    } else {
        $_SESSION['error_message'] = "Kesalahan eksekusi database: " . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Kesalahan persiapan query: " . $db_mapel->error;
}

$db_mapel->close();

header("Location: penugasan_tryout.php?id_tryout={$id_tryout_redirect}");
exit();
?>