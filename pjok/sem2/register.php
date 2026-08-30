<?php
// Pastikan koneksi database ada di file ini.
// Ganti 'config/koneksi.php' sesuai lokasi file koneksi Anda
include('config/koneksi.php');

$message = "";
$hasError = false;
$currentRole = $_POST['role'] ?? 'siswa'; // Pertahankan peran yang dipilih setelah submit

// ==========================================================
// 1. LOGIKA PHP: MENGAMBIL DAFTAR GURU & MENGELOMPOKKAN BERDASARKAN KELAS
// ==========================================================
$query_guru = "
    SELECT id, nama_lengkap, kelas
    FROM users
    WHERE role = 'guru' AND kelas IS NOT NULL AND kelas != ''
    ORDER BY nama_lengkap ASC;
";
$result_guru = $conn->query($query_guru);
$guru_by_kelas = [];

if ($result_guru && $result_guru->num_rows > 0) {
    while($row = $result_guru->fetch_assoc()) {
        // Karena kolom 'kelas' guru bisa berupa string '1,5,6'
        $kelas_guru = explode(',', $row['kelas']);

        $guru_data = [
            'id' => $row['id'],
            'nama' => $row['nama_lengkap'],
            'kelas' => $row['kelas'] // Simpan kelas dalam string (misal: "5" atau "4,5")
        ];

        // Kelompokkan guru berdasarkan setiap kelas yang mereka ajar
        foreach ($kelas_guru as $k) {
            $k = trim($k);
            if (!isset($guru_by_kelas[$k])) {
                $guru_by_kelas[$k] = [];
            }
            $guru_by_kelas[$k][] = $guru_data;
        }
    }
}

// Konversi array PHP ke JSON agar bisa digunakan di JavaScript
$guru_by_kelas_json = json_encode($guru_by_kelas);
// ==========================================================


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil dan bersihkan input
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password_input = $_POST['password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $role     = isset($_POST['role']) ? $_POST['role'] : 'siswa';

    $kelas = null;
    $id_guru = null;

    // ==========================================================
    // LOGIKA PENGAMBILAN KELAS & ID_GURU
    // ==========================================================
    if ($role === 'siswa') {
        $kelas = isset($_POST['kelas_siswa']) ? strval(intval($_POST['kelas_siswa'])) : null;
        $id_guru = isset($_POST['id_guru']) ? intval($_POST['id_guru']) : null;

        // VALIDASI KRITIS: Siswa harus memilih guru (karena required di HTML sudah dihapus)
        if (empty($id_guru) || $id_guru === 0) {
            $message = "<div class='alert alert-danger'>❌ **Pilih Guru Pembimbing.** Siswa harus memilih guru.</div>";
            $hasError = true;
        }

    } elseif ($role === 'guru') {
        $kelas_array = isset($_POST['kelas_guru']) ? $_POST['kelas_guru'] : [];
        if (is_array($kelas_array)) {
            $valid_kelas = array_filter($kelas_array, function($k) {
                return is_numeric($k) && $k >= 1 && $k <= 6;
            });
            // Untuk Guru, gunakan string kosong "" jika tidak ada kelas, bukan NULL.
            $kelas = empty($valid_kelas) ? "" : implode(',', $valid_kelas);
        }
        // id_guru tetap NULL (akan disetel eksplisit di binding)
    }
    // ==========================================================

    // 2. VALIDASI: Cek Duplikasi Username dan Email
    if (!$hasError) {
        $check_query = "SELECT username, email FROM users WHERE username = ? OR email = ?";
        if ($stmt_check = $conn->prepare($check_query)) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $existing_user = $result_check->fetch_assoc();

                if ($existing_user['username'] === $username) {
                    $message = "<div class='alert alert-danger'>❌ **Username ($username) sudah digunakan.** Mohon gunakan username lain.</div>";
                    $hasError = true;
                }
                if ($existing_user['email'] === $email) {
                    $message = "<div class='alert alert-danger'>❌ **Email ($email) sudah terdaftar.** Mohon gunakan email lain.</div>";
                    $hasError = true;
                }
            }
            $stmt_check->close();
        } else {
            $message = "<div class='alert alert-danger'>❌ Error sistem: Gagal melakukan validasi data.</div>";
            $hasError = true;
        }
    }


    // 3. Lanjutkan Proses INSERT
    if (!$hasError) {

        $password_hashed = password_hash($password_input, PASSWORD_DEFAULT);

        $query_insert = "INSERT INTO users
                         (username, email, password, nama_lengkap, role, kelas, id_guru, is_verified, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";

        if ($stmt = $conn->prepare($query_insert)) {

            if ($role === 'siswa') {
                // Siswa: binding 7 parameter (6 string, 1 int)
                $stmt->bind_param("ssssssi", $username, $email, $password_hashed, $nama_lengkap, $role, $kelas, $id_guru);
            } else {
                // Guru/Admin: set id_guru ke NULL eksplisit.
                $id_guru_val_for_teacher = NULL;

                // Tipe binding: sssssss (6 string + 1 'string' untuk NULL)
                $bind_type = "sssssss";

                $stmt->bind_param($bind_type, $username, $email, $password_hashed, $nama_lengkap, $role, $kelas, $id_guru_val_for_teacher);
            }

            if ($stmt->execute()) {

                // Pendaftaran Sukses
                $message = "<div class='alert alert-success'>✅ Akun **$username** berhasil didaftarkan! Akun Anda menunggu verifikasi Admin. Anda akan dialihkan ke halaman Login dalam 3 detik...</div>";
                $message .= "<script>
                                setTimeout(function() {
                                    window.location.href = 'login.php';
                                }, 3000);
                            </script>";

            } else {
                // Tampilkan error SQL yang spesifik
                $message = "<div class='alert alert-danger'>❌ Gagal membuat akun. Silakan coba lagi. Error: " . $stmt->error . "</div>";
            }
            $stmt->close();

        } else {
            $message = "<div class='alert alert-danger'>❌ Error sistem: Gagal mempersiapkan statement INSERT.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Numerasi Cerdas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #e9ecef;
            height: 100vh;
            margin: 0;
            padding: 0;
            position: relative;
        }
        .center-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
        }
        .form-container {
            max-width: 450px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-radius: 10px;
            background-color: #ffffff;
            margin: auto;
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="container">
        <div class="form-container">
            <h3 class="mb-4 text-center text-info">Daftar Akun Numerasi Cerdas 📚</h3>

            <?php echo $message; ?>

            <form method="POST">

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan nama pengguna" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap Anda" value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="contoh@domain.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Pilih Peran</label>
                    <select class="form-select" id="role" name="role" required onchange="toggleKelas(this.value)">
                        <option value="guru" <?php echo ($currentRole === 'guru' ? 'selected' : ''); ?>>Guru</option>
                        <option value="siswa" <?php echo ($currentRole === 'siswa' ? 'selected' : ''); ?>>Siswa</option>
                    </select>
                </div>

                <div class="mb-4" id="kelas-container">

                    <div id="kelas-siswa-group" style="display: none;">
                        <label for="kelas_siswa" class="form-label">Pilih Kelas (Khusus Siswa)</label>
                        <select class="form-select" id="kelas_siswa" name="kelas_siswa">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (($_POST['kelas_siswa'] ?? 5) == $i ? 'selected' : ''); ?>>Kelas <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div id="guru-pembimbing-group" style="display: none;">
                        <label for="id_guru" class="form-label">Pilih Guru Pembimbing</label>
                        <select class="form-select mb-3" id="id_guru" name="id_guru">
                            <option value="">-- Pilih Guru Anda --</option>
                        </select>
                    </div>

                    <div id="kelas-guru-group" style="display: none;">
                        <label for="kelas_guru" class="form-label">Pilih Kelas yang Diajar (Khusus Guru)</label>
                        <select class="form-select" id="kelas_guru" name="kelas_guru[]" multiple="multiple">
                            <?php
                            $selected_kelas_guru = $_POST['kelas_guru'] ?? [];
                            for ($i = 1; $i <= 6; $i++):
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo (in_array($i, $selected_kelas_guru) ? 'selected' : ''); ?>>Kelas <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text">Tahan **CTRL** (Windows/Linux) atau **CMD** (Mac) untuk memilih lebih dari satu kelas.</div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-info text-white btn-lg">
                        Daftar Akun
                    </button>
                </div>
            </form>

            <p class="text-center mt-3">
                Sudah punya akun? <a href="login.php" class="text-info">Masuk di sini</a>
            </p>
        </div>
    </div>
</div>

<script>
    // Data Guru yang dikelompokkan dari PHP (dikonversi dari JSON)
    const GURU_DATA = <?php echo $guru_by_kelas_json; ?>;

    function toggleKelas(role) {
        var siswaGroup = document.getElementById('kelas-siswa-group');
        var guruGroup = document.getElementById('kelas-guru-group');
        var guruPembimbingGroup = document.getElementById('guru-pembimbing-group');

        if (role === 'siswa') {
            siswaGroup.style.display = 'block';
            guruPembimbingGroup.style.display = 'block';
            guruGroup.style.display = 'none';

            // Panggil filter saat beralih ke Siswa
            filterGuruPembimbing();

        } else { // Role Guru atau lainnya
            siswaGroup.style.display = 'none';
            guruPembimbingGroup.style.display = 'none';
            guruGroup.style.display = 'block';
        }
    }

    // Fungsi BARU untuk memfilter Guru berdasarkan Kelas yang dipilih
    function filterGuruPembimbing() {
        const kelasSelect = document.getElementById('kelas_siswa');
        const guruSelect = document.getElementById('id_guru');
        const selectedKelas = kelasSelect.value;

        // Simpan nilai guru yang sedang dipilih (jika ada)
        const currentSelectedGuruId = guruSelect.value;

        // Bersihkan opsi guru yang ada
        guruSelect.innerHTML = '<option value="">-- Pilih Guru Anda --</option>';

        // Jika kelas dipilih dan data guru tersedia untuk kelas tersebut
        if (selectedKelas && GURU_DATA[selectedKelas]) {
            const availableGurus = GURU_DATA[selectedKelas];

            availableGurus.forEach(guru => {
                const option = document.createElement('option');
                option.value = guru.id;
                option.textContent = `${guru.nama} (Kelas ${guru.kelas})`;

                // Pertahankan pilihan guru jika pilihan sebelumnya valid untuk kelas baru
                if (guru.id == currentSelectedGuruId) {
                    option.selected = true;
                }

                guruSelect.appendChild(option);
            });
        }
    }

    // Panggil saat halaman dimuat untuk menyesuaikan status awal
    document.addEventListener('DOMContentLoaded', function() {
        const initialRole = document.getElementById('role').value || 'siswa';
        toggleKelas(initialRole);

        // Pasang event listener pada dropdown Kelas Siswa
        const kelasSelect = document.getElementById('kelas_siswa');
        kelasSelect.addEventListener('change', filterGuruPembimbing);

        // Panggil filter pada load agar opsi guru yang benar muncul saat awal
        filterGuruPembimbing();
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>