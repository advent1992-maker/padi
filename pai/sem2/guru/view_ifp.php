<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("ID Materi Tidak Valid."); }

$query = "SELECT judul, file_path, konten_materi FROM " . tbl('materi') . " WHERE id = $id";
$result = mysqli_query($db_mapel, $query);
$m = mysqli_fetch_assoc($result);

if (!$m) { die("Materi tidak ditemukan."); }

$path_materi = "../materi/" . $m['file_path'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>IFP: <?= htmlspecialchars($m['judul']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #000; font-family: 'Poppins', sans-serif; }
        
        /* Header tetap muncul tipis */
        .ifp-header { 
            position: absolute; top: 0; left: 0; right: 0; height: 60px;
            background: white; display: flex; justify-content: space-between; 
            align-items: center; padding: 0 20px; z-index: 1000; border-bottom: 3px solid #764ba2;
        }

        /* Frame Utama */
        .materi-frame { 
            width: 100%; height: calc(100% - 60px); 
            margin-top: 60px; border: none; background: transparent;
        }

        /* Tombol Navigasi Mengambang di Pojok Bawah */
        .floating-nav {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 9999; /* Sangat penting agar tidak tertutup materi */
            display: flex;
            gap: 10px;
        }

        .btn-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 15px rgba(0,0,0,0.3);
            border: none;
            transition: 0.3s;
        }
        
        .btn-circle:hover { transform: scale(1.1); }

        /* Sembunyikan header saat mode fullscreen diaktifkan via JS */
        .fullscreen-active .ifp-header { display: none; }
        .fullscreen-active .materi-frame { height: 100%; margin-top: 0; }
    </style>
</head>
<body>

<div class="ifp-header">
    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-chalkboard-teacher text-primary me-2"></i> <?= htmlspecialchars($m['judul']) ?></h5>
    <button onclick="window.close()" class="btn btn-sm btn-danger rounded-pill px-3">Tutup</button>
</div>

<?php 
if (!empty($m['file_path'])) {
    if (file_exists($path_materi)) {
        // Menambahkan atribut allowfullscreen agar frame diizinkan masuk mode layar penuh
        echo '<iframe src="'.$path_materi.'" class="materi-frame" id="ifpFrame" allowfullscreen></iframe>';
    } else {
        echo '<div class="materi-frame p-5 text-center"><h1>⚠️ File Tidak Ditemukan</h1></div>';
    }
} else {
    echo '<div class="materi-frame p-5" style="overflow-y:auto;">'.$m['konten_materi'].'</div>';
}
?>

<div class="floating-nav">
    <button onclick="toggleFullScreen()" class="btn btn-primary btn-circle" id="btnFS" title="Layar Penuh">
        <i class="fas fa-expand"></i>
    </button>
</div>

<script>
    function toggleFullScreen() {
        const doc = window.document;
        const docEl = doc.documentElement;

        const requestFullScreen = docEl.requestFullscreen || docEl.mozRequestFullScreen || docEl.webkitRequestFullScreen || docEl.msRequestFullscreen;
        const cancelFullScreen = doc.exitFullscreen || doc.mozCancelFullScreen || doc.webkitExitFullscreen || doc.msExitFullscreen;

        if (!doc.fullscreenElement && !doc.mozFullScreenElement && !doc.webkitFullscreenElement && !doc.msFullscreenElement) {
            requestFullScreen.call(docEl);
            document.body.classList.add('fullscreen-active');
            document.getElementById('btnFS').innerHTML = '<i class="fas fa-compress"></i>';
        } else {
            cancelFullScreen.call(doc);
            document.body.classList.remove('fullscreen-active');
            document.getElementById('btnFS').innerHTML = '<i class="fas fa-expand"></i>';
        }
    }

    // Listener otomatis jika user menekan tombol ESC di keyboard
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            document.body.classList.remove('fullscreen-active');
            document.getElementById('btnFS').innerHTML = '<i class="fas fa-expand"></i>';
        }
    });
</script>

</body>
</html>