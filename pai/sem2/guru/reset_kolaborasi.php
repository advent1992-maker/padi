<?php
require_once '../config/session.php';

// Hapus session pilihan agar variabel $user_id kembali merujuk ke ID login asli
if (isset($_SESSION['id_guru_pilihan'])) {
    unset($_SESSION['id_guru_pilihan']);
}

// Kembalikan ke halaman dashboard mapel
header("Location: ../../../dashboard_guru.php");
exit();