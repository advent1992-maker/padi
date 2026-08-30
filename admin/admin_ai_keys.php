<?php
// TAMPILKAN ERROR (Hapus baris ini jika sudah normal)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/koneksi.php';
require_once '../config/session.php';

// CEK KONEKSI: PADI biasanya pakai $conn untuk Pusat
$db_pusat = $conn; 

if (!$db_pusat) {
    die("Koneksi Database Gagal: Variabel \$conn tidak terbaca. Periksa config/koneksi.php");
}

// LOGIKA TAMBAH KEY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_key'])) {
    $key = trim($_POST['new_key']);
    if (!empty($key)) {
        $stmt = $db_pusat->prepare("INSERT INTO ai_api_keys (api_key, status) VALUES (?, 'active')");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_ai_keys.php");
    exit();
}

// LOGIKA RESET
if (isset($_GET['action']) && $_GET['action'] == 'reset') {
    $db_pusat->query("UPDATE ai_api_keys SET status = 'active'");
    header("Location: admin_ai_keys.php");
    exit();
}

$keys = $db_pusat->query("SELECT * FROM ai_api_keys ORDER BY last_used DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>AI Key Manager - PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h2 class="mb-4">Manajemen API Key Gemini</h2>
        
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-8">
                        <input type="text" name="new_key" class="form-control" placeholder="Tempel API Key Baru di sini..." required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="add_key" class="btn btn-primary w-100">Simpan Key</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <a href="?action=reset" class="btn btn-sm btn-outline-warning" onclick="return confirm('Aktifkan kembali semua key?')">Reset Semua Status ke Active</a>
        </div>

        <table class="table table-hover bg-white shadow-sm rounded">
            <thead class="table-dark">
                <tr>
                    <th>API Key (Sensor)</th>
                    <th>Status</th>
                    <th>Terakhir Digunakan</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $keys->fetch_assoc()): ?>
                <tr>
                    <td><code><?php echo substr($row['api_key'], 0, 8) . '...' . substr($row['api_key'], -4); ?></code></td>
                    <td>
                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'danger'; ?>">
                            <?php echo strtoupper($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $row['last_used'] ?? '-'; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>