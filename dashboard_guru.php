<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
require_once 'config/session.php';
require_once 'config/koneksi.php'; 

$id_user_login = $_SESSION['user_id'];
$cek_uji = mysqli_query($conn, "SELECT id FROM hasil_uji_guru WHERE id_user = '$id_user_login' AND kode_aplikasi = 'PADI_PORTAL'");
$sudah_mengisi = mysqli_num_rows($cek_uji) > 0;


// Proteksi: Hanya Guru yang boleh masuk
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: index.php");
    exit;
}

$my_id = $_SESSION['user_id'];
$namaGuru = $_SESSION['nama_lengkap'];
$semester = $_SESSION['semester_aktif'];
$folder_sem = "sem2";
$label_semester = ($semester == "2")
    ? "Semester 1 TP : 2026/2027"
    : "Semester 2 TP 2025/2026 (Arsip)";

// ==========================================
// --- LOGIKA: MANAJEMEN GAME (INSERT & UPDATE) ---
// ==========================================
if (isset($_POST['tambah_game'])) {
    $game_id = (int)$_POST['game_id']; 
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $kelas = $_POST['kelas'];
    $tipe = $_POST['tipe_game'];
    
    // Inisialisasi variabel
    $file_path = mysqli_real_escape_string($conn, $_POST['old_file'] ?? ""); 
    $link_url = "";
    $konten_html = "";

    if ($tipe == 'upload') {
        if (!empty($_FILES['file_html']['name'])) {
            // Hapus file lama jika ada upload baru
            if (!empty($file_path) && file_exists("games_storage/" . $file_path)) {
                unlink("games_storage/" . $file_path);
            }
            $nama_file = $_FILES['file_html']['name'];
            $tmp_file = $_FILES['file_html']['tmp_name'];
            $file_path = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $nama_file);
            move_uploaded_file($tmp_file, "games_storage/" . $file_path);
        }
    } elseif ($tipe == 'link') {
        $link_url = mysqli_real_escape_string($conn, $_POST['link_url']);
    } elseif ($tipe == 'tulis') {
        $konten_html = mysqli_real_escape_string($conn, $_POST['konten_html']);
    }

    if ($game_id > 0) {
        // MODE UPDATE
        $query = "UPDATE ifp_games SET judul='$judul', deskripsi='$deskripsi', kelas='$kelas', 
                  tipe_game='$tipe', file_path='$file_path', link_url='$link_url', konten_html='$konten_html' 
                  WHERE id = $game_id";
        $msg = "Game berhasil diperbarui!";
    } else {
        // MODE INSERT
        $query = "INSERT INTO ifp_games (judul, deskripsi, kelas, tipe_game, file_path, link_url, konten_html) 
                  VALUES ('$judul', '$deskripsi', '$kelas', '$tipe', '$file_path', '$link_url', '$konten_html')";
        $msg = "Game berhasil ditambahkan!";
    }

    mysqli_query($conn, $query);
    $_SESSION['notif_game'] = ['type' => 'success', 'msg' => $msg];
    header("Location: dashboard_guru.php"); exit;
}

if (isset($_GET['hapus_game'])) {
    $id = (int)$_GET['hapus_game'];
    $cek = mysqli_query($conn, "SELECT file_path FROM ifp_games WHERE id = $id");
    $data = mysqli_fetch_assoc($cek);
    if (!empty($data['file_path']) && file_exists("games_storage/" . $data['file_path'])) {
        unlink("games_storage/" . $data['file_path']);
    }
    mysqli_query($conn, "DELETE FROM ifp_games WHERE id = $id");
    $_SESSION['notif_game'] = ['type' => 'warning', 'msg' => 'Game telah dihapus.'];
    header("Location: dashboard_guru.php"); exit;
}
$res_game_list = mysqli_query($conn, "SELECT * FROM ifp_games ORDER BY id DESC");

// --- LOGIKA KOLABORASI & AKSES ---
$q_cek_akses = "SELECT pembimbing_osn, pembimbing_stem FROM users WHERE id = $my_id";
$res_cek = mysqli_query($conn, $q_cek_akses);
$data_akses = mysqli_fetch_assoc($res_cek);
$is_pembimbing = ($data_akses['pembimbing_osn'] == 1 || $data_akses['pembimbing_stem'] == 1);

$q_daftar_guru = "SELECT id, nama_lengkap, kelas FROM users WHERE role='guru' AND id != $my_id ORDER BY kelas ASC";
$res_daftar_guru = mysqli_query($conn, $q_daftar_guru);

$q_notif = "SELECT k.*, u.nama_lengkap FROM kolaborasi_akses k JOIN users u ON k.id_pengaju = u.id WHERE k.id_penerima = $my_id AND k.status = 'pending'";
$res_notif = mysqli_query($conn, $q_notif);

$q_akses_diterima = "SELECT k.*, u.nama_lengkap, u.kelas FROM kolaborasi_akses k JOIN users u ON k.id_penerima = u.id WHERE k.id_pengaju = $my_id AND k.status = 'disetujui'";
$res_akses_diterima = mysqli_query($conn, $q_akses_diterima);

function getMappingUser($folder, $prefix) {
    $map = ['ipas'=>'hari','mtk'=>'advent','indo'=>'harrieya','panca'=>'adventgool','englis'=>'kris','pjok'=>'derry','pai'=>'arq','mulok'=>'kristian','seni'=>'senirupa'];
    return isset($map[$folder]) ? $prefix . $map[$folder] : $prefix . "admin";
}

$mapels = [
    ['folder' => 'ipas', 'nama' => 'IPAS', 'icon' => 'fa-flask', 'color' => 'text-success'],
    ['folder' => 'mtk', 'nama' => 'Matematika', 'icon' => 'fa-calculator', 'color' => 'text-primary'],
    ['folder' => 'indo', 'nama' => 'B. Indonesia', 'icon' => 'fa-book-open', 'color' => 'text-warning'],
    ['folder' => 'panca', 'nama' => 'Pancasila', 'icon' => 'fa-shield-halved', 'color' => 'text-danger'],
    ['folder' => 'englis', 'nama' => 'B. Inggris', 'icon' => 'fa-language', 'color' => 'text-info'],
    ['folder' => 'pjok', 'nama' => 'PJOK', 'icon' => 'fa-volleyball', 'color' => 'text-success'],
    ['folder' => 'pai', 'nama' => 'PAI', 'icon' => 'fa-mosque', 'color' => 'text-primary'],
    ['folder' => 'mulok', 'nama' => 'Mulok (B. Komering)', 'icon' => 'fa-map-location-dot', 'color' => 'text-secondary'],
    ['folder' => 'seni', 'nama' => 'Seni', 'icon' => 'fa-palette', 'color' => 'text-danger'],
];
// HANYA TAMBAHKAN PENGEMBANGAN DIRI JIKA PUNYA AKSES
if ($data_akses['pembimbing_osn'] == 1 || $data_akses['pembimbing_stem'] == 1) {
    $mapels[] = ['folder' => 'peng_diri', 'nama' => 'Pengembangan Diri', 'icon' => 'fa-lightbulb', 'color' => 'text-warning'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PADI | Dashboard Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .hero-guru { background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%); color: white; padding: 60px 0 50px 0; border-radius: 0 0 40px 40px; position: relative; }
        .nav-top-guru { position: absolute; top: 15px; right: 15px; display: flex; gap: 8px; }
        .btn-nav-custom { border-radius: 50px; padding: 6px 15px; border: 1px solid rgba(255,255,255,0.4); color: white; text-decoration: none; transition: 0.3s; font-size: 0.8rem; font-weight: 600; background: rgba(255,255,255,0.1); }
        .btn-nav-custom:hover { background: rgba(255,255,255,0.2); color: white; }
        .stat-card { border: none; border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .card-penilaian { border: none; border-radius: 25px; cursor: pointer; background: white; overflow: hidden; box-shadow: 0 10px 20px rgba(0,0,0,0.05); transition: 0.3s; }
        .card-penilaian:hover { transform: scale(1.02); }
        .guide-card { border: none; border-radius: 20px; border-left: 6px solid #ffc107; }
    </style>
</head>
<body>

<div class="hero-guru text-center">
    <div class="nav-top-guru">
        <a href="tentang.php" class="btn-nav-custom"><i class="fas fa-info-circle"></i> <span>Tentang</span></a>
        <a href="logout.php" class="btn-nav-custom"><i class="fas fa-sign-out-alt"></i> <span>Keluar</span></a>
    </div>
    <div class="container px-4">
        <h1 class="fw-bold">Panel Kendali Guru</h1>
        <p class="lead small">Selamat Datang, Bapak/Ibu <?= htmlspecialchars($namaGuru) ?></p>
       <span class="badge <?= ($semester === 'arsip') ? 'bg-warning text-dark' : 'bg-light text-dark'; ?> px-4 py-2 rounded-pill shadow-sm">
    <i class="fas fa-calendar-check me-2"></i> <?= ($semester === 'arsip') ? 'Mode Arsip' :  $label_semester; ?>
</span>
    </div>
</div>

<div class="container my-4">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card stat-card shadow-sm p-4 h-100">
                <h6 class="fw-bold text-primary"><i class="fas fa-paper-plane me-2"></i> Ajukan Akses Kolaborasi</h6>
                <form action="proses_kolaborasi.php" method="POST">
                    <select name="id_penerima" class="form-select mb-2" required>
                        <option value="">Pilih Guru Kelas...</option>
                        <?php mysqli_data_seek($res_daftar_guru, 0); while($g = mysqli_fetch_assoc($res_daftar_guru)): ?>
                            <option value="<?= $g['id'] ?>">Kelas <?= $g['kelas'] ?> - <?= $g['nama_lengkap'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select name="mapel" class="form-select mb-3" required>
                        <option value="">Pilih Mata Pelajaran...</option>
                        <option value="englis">Bahasa Inggris</option>
                        <option value="pai">PAI</option>
                        <option value="pjok">PJOK</option>
                        <option value="seni">Seni</option>
                    </select>
                    <button type="submit" name="kirim_request" class="btn btn-primary btn-sm w-100 rounded-pill">Kirim Permintaan</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card stat-card shadow-sm p-4 h-100">
                <h6 class="fw-bold text-success"><i class="fas fa-bell me-2"></i> Permintaan Masuk</h6>
                <?php if(mysqli_num_rows($res_notif) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <?php while($n = mysqli_fetch_assoc($res_notif)): ?>
                            <tr>
                                <td class="small"><strong><?= $n['nama_lengkap'] ?></strong> ingin akses <strong><?= strtoupper($n['mapel']) ?></strong></td>
                                <td class="text-end">
                                    <a href="proses_kolaborasi.php?aksi=setuju&id=<?= $n['id'] ?>" class="btn btn-success btn-sm rounded-pill"><i class="fa fa-check"></i></a>
                                    <a href="proses_kolaborasi.php?aksi=tolak&id=<?= $n['id'] ?>" class="btn btn-danger btn-sm rounded-pill"><i class="fa fa-times"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4">Belum ada permintaan kolaborasi.</p>
                <?php endif; ?>
                <div class="card stat-card shadow-sm p-4 mt-4 bg-light border-start border-primary border-4">
                <h6 class="fw-bold text-primary"><i class="fas fa-door-open me-2"></i> Akses Kelas Kolaborasi</h6>
                <?php if(mysqli_num_rows($res_akses_diterima) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle table-borderless">
                            <?php while($acc = mysqli_fetch_assoc($res_akses_diterima)): 
                                // Membuat link sakti yang membawa ID Bapak (penerima)
                                $link_masuk = $acc['mapel'] . "/" . $folder_sem . "/guru/dashboard.php?id_guru_target=" . $acc['id_penerima'];
                            ?>
                            <tr class="border-bottom">
                                <td class="small py-2">
                                    Kelas <strong><?= $acc['kelas'] ?></strong> (<?= $acc['nama_lengkap'] ?>)<br>
                                    <span class="badge bg-info" style="font-size: 0.6rem;"><?= strtoupper($acc['mapel']) ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= $link_masuk ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" style="font-size: 0.7rem;">
                                        Masuk Kelas <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-2">Belum ada akses kelas lain.</p>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card guide-card shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-info-circle text-warning me-2"></i> Petunjuk</h5>
                            <p class="text-muted mb-0 small">Kelola materi dan pantau nilai siswa bimbingan Anda.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <button class="btn btn-warning btn-sm fw-bold px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPanduan">
                                <i class="fas fa-book-open me-2"></i> PANDUAN
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Manajemen Mapel</h5>
            <a href="rekap_semua_mapel.php" class="btn btn-success btn-sm shadow-sm rounded-pill px-3">
                <i class="fas fa-file-invoice me-1"></i> Rekap Nilai
            </a>
        </div>
        <div class="col-12">
            <div class="card stat-card shadow-sm p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>Semester</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                           
<?php foreach ($mapels as $index => $m) : 
    // Sekarang semua mapel dianggap 'boleh_akses' karena database sudah terpusat
    $boleh_akses = true;
    
    // URL tetap dinamis mengikuti struktur folder Bapak
   if ($m['folder'] == 'peng_diri') {
        // Jalur langsung untuk Pengembangan Diri (Tanpa folder semester & tanpa parameter mode)
        $url_kelola = $m['folder'] . "/dashboard.php";
        $url_nilai  = $m['folder'] . "/guru/monitor_nilai.php";
    } else {
        // Jalur dinamis untuk mapel lainnya mengikuti Mode Aktif / Mode Arsip
        $folder_target = ($semester === 'arsip') ? 'sem_arsip' : $folder_sem;
        $param_mode    = ($semester === 'arsip') ? '?mode=arsip' : '';

        $url_kelola = $m['folder'] . "/" . $folder_target . "/guru/dashboard.php" . $param_mode;
        $url_nilai  = $m['folder'] . "/" . $folder_target . "/guru/monitor_nilai.php" . $param_mode;
    }
    
    // Status Aktif: Kita cek apakah koneksi utama ($conn) tersedia
    $is_active = ($conn) ? true : false;
?>
<tr>
    <td><?= $index + 1 ?></td>
    <td><i class="fas <?= $m['icon'] ?> <?= $m['color'] ?> me-2"></i> <strong><?= $m['nama'] ?></strong></td>
    <td><?= ($m['folder'] == 'peng_diri') ? '-' : 'Sem '.$semester ?></td>
    <td>
        <?php if ($is_active) : ?>
            <span class="badge rounded-pill bg-success">Aktif</span>
        <?php else : ?>
            <span class="badge rounded-pill bg-danger">Sistem Down</span>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <!--<a href="adm_guru/guru_adm_mapel.php?folder=<?= $m['folder'] ?>&mapel=<?= urlencode($m['nama']) ?>" -->
        <!--   class="btn btn-sm btn-dark px-3 rounded-pill" -->
        <!--   style="background-color: #4a5568; border: none;">-->
        <!--   <i class="fas fa-file-signature me-1"></i> ADM-->
        <!--</a>-->

        <a href="<?= $url_kelola ?>" class="btn btn-sm btn-primary px-3 rounded-pill">Kelola</a>
        <a href="<?= $url_nilai ?>" class="btn btn-sm btn-outline-dark px-3 rounded-pill">Nilai</a>
    </td>
</tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-5">
            <div class="card stat-card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-primary" id="labelFormGame"><i class="fas fa-plus-circle me-2"></i>Tambah Game Interaktif</h6>
                </div>
                <div class="card-body">
                    <?php if(isset($_SESSION['notif_game'])): ?>
                        <div class="alert alert-<?= $_SESSION['notif_game']['type'] ?> small py-2 mb-3"><?= $_SESSION['notif_game']['msg'] ?></div>
                        <?php unset($_SESSION['notif_game']); ?>
                    <?php endif; ?>
                    
                    <form action="" method="POST" enctype="multipart/form-data" id="formGame">
                        <input type="hidden" name="game_id" id="game_id" value="0">
                        <input type="hidden" name="old_file" id="old_file" value="">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Judul Game</label>
                            <input type="text" name="judul" id="judul" class="form-control form-control-sm" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Kelas</label>
                                <select name="kelas" id="kelas" class="form-select form-select-sm">
                                    <?php for($i=1; $i<=6; $i++) echo "<option value='$i'>Kelas $i</option>"; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Tipe</label>
                                <select name="tipe_game" id="tipe_game" class="form-select form-select-sm" onchange="toggleGameInput()">
                                    <option value="upload">Upload HTML</option>
                                    <option value="link">Link URL</option>
                                    <option value="tulis">Tulis Kode HTML</option>
                                </select>
                            </div>
                        </div>
                        <div id="box_upload" class="mb-3">
                            <label class="form-label small fw-bold">File HTML (.html)</label>
                            <input type="file" name="file_html" class="form-control form-control-sm" accept=".html,.htm">
                        </div>
                        <div id="box_link" class="mb-3 d-none">
                            <label class="form-label small fw-bold">URL Game</label>
                            <input type="url" name="link_url" id="link_url" class="form-control form-control-sm" placeholder="https://...">
                        </div>
                        <div id="box_tulis" class="mb-3 d-none">
                            <label class="form-label small fw-bold text-success">Kode HTML</label>
                            <textarea name="konten_html" id="konten_html" class="form-control form-control-sm" rows="8" style="font-family: monospace;" oninput="updateGamePreview()"></textarea>
                        </div>
                        <div id="box_preview" class="mb-3 d-none">
                            <label class="form-label small fw-bold text-warning">Live Preview</label>
                            <iframe id="live_preview_game" style="width: 100%; height: 250px; border: 1px solid #ddd; border-radius: 8px; background: white;"></iframe>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Deskripsi</label>
                            <textarea name="deskripsi" id="deskripsi" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="tambah_game" id="btnSimpanGame" class="btn btn-primary btn-sm w-100 rounded-pill">Simpan Game</button>
                            <button type="button" id="btnBatalEdit" class="btn btn-secondary btn-sm rounded-pill d-none" onclick="resetFormGame()">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card stat-card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-tasks me-2"></i>Koleksi Game</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Judul</th>
                                    <th>Kelas</th>
                                    <th>Tipe</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($res_game_list) > 0): ?>
                                    <?php while($g = mysqli_fetch_assoc($res_game_list)): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><?= htmlspecialchars($g['judul']) ?></td>
                                        <td>Kls <?= $g['kelas'] ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= strtoupper($g['tipe_game']) ?></span></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm text-primary" onclick='editGame(<?= json_encode($g) ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="dashboard_guru.php?hapus_game=<?= $g['id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('Hapus?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada game.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center my-5">
    <?php if (!$sudah_mengisi): ?>
        <button class="btn btn-primary rounded-pill px-5 py-3 shadow-lg fw-bold" data-bs-toggle="modal" data-bs-target="#modalPenilaian">
            <i class="fas fa-edit me-2"></i> Isi Instrumen Penilaian PADI
        </button>
    <?php endif; ?>
</div>

<?php if (!$sudah_mengisi): ?>
<div class="modal fade" id="modalPenilaian" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="proses_uji_padi.php" method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Instrumen Validasi Praktisi (Guru)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info small">Silakan berikan penilaian Bapak/Ibu terhadap aplikasi PADI (Skor 1-5).</div>
                
                <?php 
                $instrumen = [
                    "Aplikasi PADI memiliki antarmuka yang ramah untuk anak SD.",
                    "Navigasi antar mata pelajaran sangat mudah dioperasikan.",
                    "Aplikasi berjalan dengan stabil pada perangkat IFP maupun mobile.",
                    "Keamanan data pengguna (login) sudah terjamin dengan baik.",
                    "Materi yang disajikan relevan dengan tujuan pembelajaran kurikulum.",
                    "Fitur Asisten AI Kak PADI memberikan jawaban yang edukatif.",
                    "Media ini mampu memotivasi siswa untuk belajar mandiri.",
                    "Bahasa yang digunakan dalam materi mudah dipahami siswa.",
                    "Fitur rekap nilai membantu guru dalam evaluasi berkala.",
                    "Secara umum, aplikasi PADI dapat diimplementasikan di sekolah."
                ];
                foreach($instrumen as $i => $tanya): $n = $i + 1; ?>
                <div class="mb-4 border-bottom pb-2">
                    <p class="mb-2 fw-bold text-dark"><?= $n; ?>. <?= $tanya; ?></p>
                    <div class="d-flex justify-content-between">
                        <?php for($val=1; $val<=5; $val++): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="q<?= $n; ?>" id="q<?= $n; ?>_<?= $val; ?>" value="<?= $val; ?>" required>
                            <label class="form-check-label" for="q<?= $n; ?>_<?= $val; ?>"><?= $val; ?></label>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="mb-3">
                    <label class="fw-bold">Saran & Masukan:</label>
                    <textarea name="saran" class="form-control" rows="3" placeholder="Tuliskan masukan Bapak/Ibu di sini..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" class="btn btn-primary px-5">Kirim Penilaian</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<footer class="text-center pb-4 text-muted" style="font-size: 0.7rem;">&copy; 2026 Portal PADI</footer>


<script>
function toggleGameInput() {
    let tipe = document.getElementById('tipe_game').value;
    document.getElementById('box_upload').classList.add('d-none');
    document.getElementById('box_link').classList.add('d-none');
    document.getElementById('box_tulis').classList.add('d-none');
    document.getElementById('box_preview').classList.add('d-none');

    if (tipe === 'upload') {
        document.getElementById('box_upload').classList.remove('d-none');
    } else if (tipe === 'link') {
        document.getElementById('box_link').classList.remove('d-none');
    } else if (tipe === 'tulis') {
        document.getElementById('box_tulis').classList.remove('d-none');
        document.getElementById('box_preview').classList.remove('d-none');
        updateGamePreview();
    }
}

function updateGamePreview() {
    const kode = document.getElementById('konten_html').value;
    const previewFrame = document.getElementById('live_preview_game');
    const previewDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
    previewDoc.open();
    previewDoc.write(kode || '<body style="color:#ccc;text-align:center;padding-top:50px;font-family:sans-serif;">Ketik kode HTML untuk melihat pratinjau</body>');
    previewDoc.close();
}

function editGame(data) {
    document.getElementById('labelFormGame').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Game';
    document.getElementById('btnSimpanGame').innerText = "Update Game";
    document.getElementById('btnSimpanGame').classList.replace('btn-primary', 'btn-success');
    document.getElementById('btnBatalEdit').classList.remove('d-none');

    document.getElementById('game_id').value = data.id;
    document.getElementById('judul').value = data.judul;
    document.getElementById('kelas').value = data.kelas;
    document.getElementById('tipe_game').value = data.tipe_game;
    document.getElementById('old_file').value = data.file_path;
    document.getElementById('deskripsi').value = data.deskripsi;
    document.getElementById('link_url').value = data.link_url || '';
    document.getElementById('konten_html').value = data.konten_html || '';

    toggleGameInput();
    window.scrollTo({ top: document.getElementById('formGame').offsetTop - 100, behavior: 'smooth' });
}

function resetFormGame() {
    document.getElementById('formGame').reset();
    document.getElementById('game_id').value = "0";
    document.getElementById('labelFormGame').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Tambah Game Interaktif';
    document.getElementById('btnSimpanGame').innerText = "Simpan Game";
    document.getElementById('btnSimpanGame').classList.replace('btn-success', 'btn-primary');
    document.getElementById('btnBatalEdit').classList.add('d-none');
    toggleGameInput();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div class="modal fade" id="modalPanduan" tabindex="-1" aria-labelledby="modalPanduanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalPanduanLabel"><i class="fas fa-book text-warning me-2"></i> Panduan Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="p-3 bg-primary bg-opacity-10 border-start border-primary border-4 rounded-3 mb-2">
                            <h6 class="fw-bold text-primary"><i class="fas fa-edit me-2"></i> Mengelola Materi & Kuis</h6>
                            <p class="small text-dark mb-0">
                                Klik tombol <strong>"Kelola"</strong> pada tabel Manajemen Mapel. Di dalamnya, Anda dapat menambah Bab, Sub-Materi, serta menginput soal kuis dan gambar pendukung.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <h6 class="fw-bold text-success"><i class="fas fa-chart-line me-2"></i> Monitoring Nilai</h6>
                            <p class="small text-muted">Klik tombol <strong>Nilai</strong> untuk melihat skor, jumlah percobaan, dan detail riwayat jawaban siswa.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <h6 class="fw-bold text-danger"><i class="fas fa-users-cog me-2"></i> Akses Kolaborasi</h6>
                            <p class="small text-muted">Gunakan <strong>Ajukan Akses</strong> untuk guru mapel yang ingin masuk ke kelas guru lain.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>