<?php
include('config/koneksi.php');
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    // Cek email ada di database
    $query = "SELECT id, username FROM users WHERE email = ?";
    $stmt = $db_mapel->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $token = rand(100000, 999999); // kode 6 digit
        $expired = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        // Simpan token ke database
        $update = $db_mapel->prepare("UPDATE users SET reset_token=?, reset_expired=? WHERE email=?");
        $update->bind_param("sss", $token, $expired, $email);
        $update->execute();

        $message = "<div class='alert alert-success'>
                        ✅ Kode reset berhasil dibuat! <br>
                        Gunakan kode berikut untuk mereset password Anda:<br>
                        <b style='font-size:18px'>$token</b><br>
                        Berlaku 10 menit.
                    </div>
                    <div class='alert alert-warning'>
                        <a href='reset_password.php'>Klik di sini untuk mereset password</a>
                    </div>";

    } else {
        $message = "<div class='alert alert-danger'>❌ Email tidak ditemukan.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="col-md-4 bg-white p-4 rounded shadow">
        <h4 class="text-center text-info mb-3">Lupa Password 🔐</h4>

        <?php echo $message; ?>

        <form method="POST">
            <label class="form-label">Masukkan Email Anda</label>
            <input type="email" name="email" class="form-control mb-3" required>

            <button type="submit" class="btn btn-info w-100 text-white">Kirim Kode Reset</button>
        </form>

        <p class="text-center mt-3">
            <a href="login.php" class="text-info">Kembali ke Login</a>
        </p>
    </div>
</div>

</body>
</html>
