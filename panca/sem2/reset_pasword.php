<?php
include('config/koneksi.php');
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'];

    // Cek apakah password cocok
    if ($password1 !== $password2) {
        $message = "<div class='alert alert-danger'>❌ Password tidak cocok.</div>";
    } else {
        // Cek token dan email di database
        $query = "SELECT id, reset_expired FROM users WHERE email=? AND reset_token=?";
        $stmt = $db_mapel->prepare($query);
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $now = date("Y-m-d H:i:s");

            // Cek apakah token masih berlaku
            if ($now <= $user['reset_expired']) {
                // Hash password baru
                $new_password = password_hash($password1, PASSWORD_DEFAULT);

                // Update password + hapus token
                $update = $db_mapel->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expired=NULL WHERE email=?");
                $update->bind_param("ss", $new_password, $email);

                if ($update->execute()) {
                    $message = "<div class='alert alert-success'>
                                    ✅ Password berhasil direset!<br>
                                    Anda akan dialihkan ke halaman login dalam 3 detik...
                                </div>";
                    // Auto redirect
                    $message .= "<script>
                                    setTimeout(function() {
                                        window.location.href = 'login.php';
                                    }, 3000);
                                 </script>";
                } else {
                    $message = "<div class='alert alert-danger'>❌ Terjadi kesalahan saat memperbarui password.</div>";
                }

            } else {
                $message = "<div class='alert alert-danger'>⏳ Token sudah kadaluarsa, silakan minta ulang.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>❌ Email atau kode reset salah.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="col-md-4 bg-white p-4 rounded shadow">
        <h4 class="text-center text-info mb-3">Reset Password 🔐</h4>

        <?php echo $message; ?>

        <form method="POST">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control mb-3" required>

            <label class="form-label">Kode Reset</label>
            <input type="text" name="token" class="form-control mb-3" placeholder="Masukkan kode 6 digit" required>

            <label class="form-label">Password Baru</label>
            <input type="password" name="password1" class="form-control mb-3" required>

            <label class="form-label">Ulangi Password Baru</label>
            <input type="password" name="password2" class="form-control mb-3" required>

            <button type="submit" class="btn btn-info w-100 text-white">Perbarui Password</button>
        </form>

        <p class="text-center mt-3">
            <a href="login.php" class="text-info">Kembali ke Login</a>
        </p>
    </div>
</div>

</body>
</html>
