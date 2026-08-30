<?php
require_once '../config/koneksi.php';
require_once '../config/session.php';

// Proteksi Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// PROSES SIMPAN OTOMATIS (AJAX)
if (isset($_POST['ajax_update'])) {
    $id_user = $_POST['id_user'];
    $kolom = $_POST['kolom']; 
    $nilai = $_POST['nilai']; 

    $allowed_cols = ['akses_osn', 'akses_stem', 'pembimbing_osn', 'pembimbing_stem'];
    if (!in_array($kolom, $allowed_cols)) { die("Error"); }

    $q = "UPDATE users SET $kolom = ? WHERE id = ?";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("ii", $nilai, $id_user);
    if ($stmt->execute()) { echo "success"; } else { echo "error"; }
    exit;
}

// Ambil Data
$res_guru = $conn->query("SELECT id, nama_lengkap, pembimbing_osn, pembimbing_stem FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");
$res_siswa = $conn->query("SELECT id, nama_lengkap, kelas, akses_osn, akses_stem FROM users WHERE role = 'siswa' ORDER BY kelas ASC, nama_lengkap ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pusat Kendali Akses | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Inter', sans-serif; padding-top: 30px; }
        .card-main { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: white; }
        .nav-pills .nav-link { color: #666; font-weight: 600; border-radius: 10px; margin: 0 5px; }
        .nav-pills .nav-link.active { background: #0d6efd; color: white; }
        .status-badge { transition: 0.3s; cursor: pointer; min-width: 80px; }
        #loading-toast { position: fixed; top: 20px; right: 20px; z-index: 9999; display: none; }
        .search-box { border-radius: 10px; border: 1px solid #ddd; padding-left: 40px; }
        .search-container { position: relative; }
        .search-container i { position: absolute; left: 15px; top: 12px; color: #aaa; }
    </style>
</head>
<body>

<div id="loading-toast" class="alert alert-info shadow">
    <i class="fas fa-spinner fa-spin me-2"></i> Memproses...
</div>

<div class="container mb-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold m-0">Pusat Kendali Akses</h2>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill mt-2"> Kembali ke Dashboard</a>
    </div>

    <ul class="nav nav-pills mb-4 justify-content-center" id="pills-tab">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-guru">GURU PEMBIMBING</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-siswa">TIKET SISWA</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-guru">
            <div class="card card-main p-4 text-center">
                <h6>Gunakan tab Siswa untuk filter pencarian nama.</h6>
                <table class="table table-hover align-middle mt-3">
                    <thead>
                        <tr>
                            <th>Nama Guru</th>
                            <th class="text-center">Pembimbing OSN</th>
                            <th class="text-center">Pembimbing STEM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($g = $res_guru->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold text-start"><?= $g['nama_lengkap'] ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm status-badge <?= $g['pembimbing_osn'] ? 'btn-warning' : 'btn-light border' ?>" 
                                        onclick="simpanPerubahan(this, <?= $g['id'] ?>, 'pembimbing_osn', <?= $g['pembimbing_osn'] ? 0 : 1 ?>)">
                                    <?= $g['pembimbing_osn'] ? 'Aktif' : 'Kunci' ?>
                                </button>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm status-badge <?= $g['pembimbing_stem'] ? 'btn-info text-white' : 'btn-light border' ?>" 
                                        onclick="simpanPerubahan(this, <?= $g['id'] ?>, 'pembimbing_stem', <?= $g['pembimbing_stem'] ? 0 : 1 ?>)">
                                    <?= $g['pembimbing_stem'] ? 'Aktif' : 'Kunci' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-siswa">
            <div class="card card-main p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6 search-container">
                        <i class="fas fa-search"></i>
                        <input type="text" id="inputCari" class="form-control search-box" placeholder="Cari nama siswa..." onkeyup="filterSiswa()">
                    </div>
                    <div class="col-md-6">
                        <select id="filterKelas" class="form-select shadow-sm" style="border-radius: 10px;" onchange="filterSiswa()">
                            <option value="">-- Semua Kelas --</option>
                            <option value="4">Kelas 4</option>
                            <option value="5">Kelas 5</option>
                            <option value="6">Kelas 6</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabelSiswa">
                        <thead class="table-light text-center">
                            <tr>
                                <th class="text-start">Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Tiket OSN</th>
                                <th>Tiket STEM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($s = $res_siswa->fetch_assoc()): ?>
                            <tr class="item-siswa" data-nama="<?= strtolower($s['nama_lengkap']) ?>" data-kelas="<?= $s['kelas'] ?>">
                                <td class="fw-bold"><?= $s['nama_lengkap'] ?></td>
                                <td class="text-center"><span class="badge bg-secondary">Kelas <?= $s['kelas'] ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm status-badge <?= $s['akses_osn'] ? 'btn-warning' : 'btn-light border' ?>" 
                                            onclick="simpanPerubahan(this, <?= $s['id'] ?>, 'akses_osn', <?= $s['akses_osn'] ? 0 : 1 ?>)">
                                        <?= $s['akses_osn'] ? 'Aktif' : 'Kunci' ?>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm status-badge <?= $s['akses_stem'] ? 'btn-info text-white' : 'btn-light border' ?>" 
                                            onclick="simpanPerubahan(this, <?= $s['id'] ?>, 'akses_stem', <?= $s['akses_stem'] ? 0 : 1 ?>)">
                                        <?= $s['akses_stem'] ? 'Aktif' : 'Kunci' ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// LOGIKA FILTER DAN PENCARIAN
function filterSiswa() {
    let input = document.getElementById("inputCari").value.toLowerCase();
    let kelas = document.getElementById("filterKelas").value;
    let items = document.getElementsByClassName("item-siswa");

    for (let i = 0; i < items.length; i++) {
        let nama = items[i].getAttribute("data-nama");
        let kls = items[i].getAttribute("data-kelas");

        let cocokNama = nama.includes(input);
        let cocokKelas = (kelas === "" || kls === kelas);

        if (cocokNama && cocokKelas) {
            items[i].style.display = "";
        } else {
            items[i].style.display = "none";
        }
    }
}

// LOGIKA SIMPAN AJAX (Sama seperti sebelumnya)
function simpanPerubahan(btn, id, kolom, nilaiBaru) {
    const toast = document.getElementById('loading-toast');
    toast.style.display = 'block';
    btn.disabled = true;

    let formData = new FormData();
    formData.append('ajax_update', true);
    formData.append('id_user', id);
    formData.append('kolom', kolom);
    formData.append('nilai', nilaiBaru);

    fetch('pusat_kendali.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(result => {
        toast.style.display = 'none';
        if (result.trim() === 'success') {
            if (nilaiBaru === 1) {
                btn.className = (kolom.includes('osn')) ? 'btn btn-sm status-badge btn-warning' : 'btn btn-sm status-badge btn-info text-white';
                btn.innerHTML = 'Aktif';
                btn.setAttribute('onclick', `simpanPerubahan(this, ${id}, '${kolom}', 0)`);
            } else {
                btn.className = 'btn btn-sm status-badge btn-light border';
                btn.innerHTML = 'Kunci';
                btn.setAttribute('onclick', `simpanPerubahan(this, ${id}, '${kolom}', 1)`);
            }
        }
        btn.disabled = false;
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>