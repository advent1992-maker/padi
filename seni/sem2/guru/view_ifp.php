<?php
require_once '../../../config/koneksi.php';
require_once '../config/koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Koneksi ke database seni
$db_name = $prefix . "db_seni_sm2";
$user_db = $prefix . "senirupa";
$db_seni = @mysqli_connect($host, $user_db, $pass, $db_name);

if (!$db_seni) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$query = mysqli_query($db_seni, "SELECT * FROM " . tbl('materi') . " WHERE id = $id");
$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Materi tidak ditemukan.");
}

$konten = $data['konten_materi'];
$tipe = "";

// 1. CEK APAKAH YOUTUBE
if (strpos($konten, 'youtube.com') !== false || strpos($konten, 'youtu.be') !== false) {
    $tipe = "video";
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $konten, $match);
    $video_id = isset($match[1]) ? $match[1] : "";
} 
// 2. CEK APAKAH KODE HTML (Biasanya mengandung tag html seperti <div>, <iframe>, <script>, atau <html>)
elseif (preg_match('/<[a-z][\s\S]*>/i', $konten)) {
    $tipe = "html_code";
}
// 3. CEK APAKAH GAMBAR (Jika hanya teks nama file dengan ekstensi gambar)
elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $konten)) {
    $tipe = "gambar";
}
// 4. DEFAULT SEBAGAI TEKS BIASA
else {
    $tipe = "teks";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['judul']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html { height: 100%; margin: 0; background: #000; color: white; overflow-x: hidden; }
        .top-bar { 
            position: fixed; top: 0; left: 0; right: 0; height: 60px; 
            background: rgba(0,0,0,0.7); backdrop-filter: blur(10px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px; z-index: 1000; border-bottom: 1px solid #333;
        }
        .content-wrapper { 
            padding-top: 60px; min-height: 100vh; width: 100vw; 
            display: flex; align-items: center; justify-content: center; 
        }
        iframe { border: none; width: 100%; height: calc(100vh - 60px); }
        img { max-width: 100%; max-height: calc(100vh - 100px); object-fit: contain; }
        .html-container { width: 100%; height: calc(100vh - 60px); background: white; color: black; overflow: auto; }
    </style>
</head>
<body>

    <div class="top-bar">
        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($data['judul']) ?></h5>
        <button onclick="window.close()" class="btn btn-danger btn-sm">Tutup</button>
    </div>

    <div class="content-wrapper">
        <?php if ($tipe == "video"): ?>
            <iframe src="https://www.youtube.com/embed/<?= $video_id ?>?autoplay=1" allowfullscreen allow="autoplay"></iframe>

        <?php elseif ($tipe == "html_code"): ?>
            <div class="html-container">
                <?= $konten // Menampilkan kode HTML interaktif secara langsung ?>
            </div>

        <?php elseif ($tipe == "gambar"): ?>
            <img src="../uploads/materi/<?= $konten ?>" alt="Materi Seni">

        <?php else: ?>
            <div class="container text-center">
                <div class="p-4 bg-dark rounded shadow border border-secondary">
                    <p class="lead"><?= nl2br(htmlspecialchars($konten)) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>