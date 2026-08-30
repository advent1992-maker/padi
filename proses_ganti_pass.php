<?php
// 1. Tampilkan error jika ada
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/session.php';
require_once 'config/koneksi.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Proses Ganti Password</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; background-color: #f4f7fe; }</style>
</head>
<body>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pass'])) {
    $id_user = $_SESSION['user_id'];
    $pass_baru = $_POST['n_pass'];
    $konf_pass = $_POST['c_pass'];

    if (empty($pass_baru)) {
        // ... (Script SweetAlert Warning Tetap Sama) ...
        exit;
    }

    if ($pass_baru !== $konf_pass) {
        // ... (Script SweetAlert Error Tetap Sama) ...
        exit;
    }

    // --- PERBAIKAN DI SINI ---
    // Enkripsi password menggunakan algoritma BCRYPT yang kuat
    $pass_hash = password_hash($pass_baru, PASSWORD_DEFAULT);

    $query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Gunakan $pass_hash, bukan $pass_baru
        $stmt->bind_param("si", $pass_hash, $id_user);

        if ($stmt->execute()) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: '<h2 style=\"font-weight:700\">Berhasil!</h2>',
                    html: '<p style=\"font-size:1.1rem\">Password kamu berhasil diganti dan diamankan. <br> Silakan login kembali.</p>',
                    confirmButtonColor: '#764ba2',
                    confirmButtonText: 'Sip, Login Kembali',
                    width: '550px',
                    padding: '3em',
                    allowOutsideClick: false,
                    backdrop: `rgba(118, 75, 162, 0.2)`
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href='logout.php';
                    }
                });
            </script>";
        } else {
            // ... (Script Error Handling Tetap Sama) ...
        }
        $stmt->close();
    }
} else {
    header("Location: dashboard.php");
    exit;
}
?>
</body>
</html>