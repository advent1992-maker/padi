<?php
require_once '../config/koneksi.php'; // sesuaikan path koneksi kamu

if (isset($_POST['upload'])) {
    $file = $_FILES['file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        // Lewati header CSV
        fgetcsv($handle);

        $row = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $username = trim($data[0]);
            $password_plain = trim($data[1]);
            $email = trim($data[2]);
            $nama_lengkap = trim($data[3]);
            $role = trim($data[4]);
            $is_verified = trim($data[5]);
            $kelas = trim($data[6]);

            // Hash password sebelum simpan
            $password = password_hash($password_plain, PASSWORD_DEFAULT);

            $query = "INSERT INTO users
                      (username, password, email, nama_lengkap, role, is_verified, kelas)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db_mapel->prepare($query);
            $stmt->bind_param("sssssis", $username, $password, $email, $nama_lengkap, $role, $is_verified, $kelas);
            $stmt->execute();
            $row++;
        }

        fclose($handle);
        echo "<script>alert('Berhasil mengimpor $row data user!');</script>";
    } else {
        echo "<script>alert('Gagal membuka file CSV!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Upload Data Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5 bg-light">
<div class="container">
    <div class="card shadow-sm p-4">
        <h4 class="mb-3">Upload Data Users (CSV)</h4>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Pilih File CSV:</label>
                <input type="file" name="file" accept=".csv" class="form-control" required>
            </div>
            <button type="submit" name="upload" class="btn btn-primary">Upload</button>
        </form>
        <hr>
        <p class="text-muted mt-3">Pastikan urutan kolom CSV:
            <b>username, password, email, nama_lengkap, role, is_verified, kelas</b>
        </p>
    </div>
</div>
</body>
</html>
