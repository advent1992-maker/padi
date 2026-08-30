<?php
// header("Location: maintenence.php");
// exit;
require_once 'config/session.php';
require_once 'config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

if (isset($_SESSION['user_id'])) {
    $target = ($_SESSION['role'] === 'admin') ? 'admin/dashboard.php' :
              (($_SESSION['role'] === 'guru') ? 'dashboard_guru.php' : 'dashboard.php');
    header("Location: $target");
    exit;
}

$message = "";

if (isset($_GET['registered'])) {
    $message = "<div class='alert-custom alert-success-custom'>✅ Pendaftaran berhasil! Tunggu verifikasi admin.</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username         = $_POST['username'] ?? '';
    $password_input   = $_POST['password'] ?? '';
    $mode_login       = $_POST['mode_login'] ?? 'siswa_guru';
    $semester_pilihan = $_POST['semester'] ?? 'aktif';

    if (!empty($username) && !empty($password_input)) {
        // Query selalu membaca tabel users utama (seperti index lama)
        $query = "SELECT id, password, role, is_verified, username, nama_lengkap, id_guru, kelas 
                  FROM users WHERE username = ?";

        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password_input, $user['password']) || $password_input === $user['password']) {
                    if ($user['is_verified'] == 1) {
                        if ($mode_login === 'admin' && $user['role'] !== 'admin') {
                            $message = "<div class='alert-custom alert-danger-custom'>❌ Akun ini bukan akun Admin!</div>";
                        } elseif ($mode_login === 'siswa_guru' && $user['role'] === 'admin') {
                            $message = "<div class='alert-custom alert-danger-custom'>❌ Gunakan tab Admin untuk login!</div>";
                        } else {
                            $_SESSION['user_id']        = $user['id'];
                            $_SESSION['role']           = $user['role'];
                            $_SESSION['username']       = $user['username'];
                            $_SESSION['nama_lengkap']   = $user['nama_lengkap'];
                            $_SESSION['id_guru']        = $user['id_guru'];
                            $_SESSION['kelas']          = $user['kelas'];
                            $_SESSION['semester_aktif'] = $semester_pilihan;

                            $loc = ($user['role'] === 'admin') ? "admin/dashboard.php" :
                                   (($user['role'] === 'guru') ? "dashboard_guru.php" : "dashboard.php");
                            header("Location: $loc");
                            exit;
                        }
                    } else {
                        $message = "<div class='alert-custom alert-warning-custom'>⚠️ Akun menunggu verifikasi Admin.</div>";
                    }
                } else {
                    $message = "<div class='alert-custom alert-danger-custom'>❌ Password salah!</div>";
                }
            } else {
                $message = "<div class='alert-custom alert-danger-custom'>❌ Username tidak ditemukan!</div>";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PADI - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(160deg, #0f2027, #203a43, #2c5364);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        /* CARD FORM UTAMA (Membalut Logo + Form) */
        .card-form {
            background: white;
            border-radius: 24px;
            padding: 28px 22px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        /* HEADER LOGO (Di dalam Card) */
        .logo-area {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #2ecc71, #1a9c50);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: white;
            margin: 0 auto 10px;
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.35);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-4px); }
        }

        .logo-area h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: 3px;
            line-height: 1.1;
        }

        .logo-area p {
            font-size: 0.72rem;
            color: #64748b;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* ALERT */
        .alert-custom {
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 16px;
            text-align: center;
        }
        .alert-danger-custom  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success-custom { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-warning-custom { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

        /* TAB LOGIN UKURAN DIPERKECEIL */
        .tab-login {
            display: flex;
            background: #f1f5f9;
            border-radius: 10px;
            padding: 3px;
            margin-bottom: 18px;
            gap: 3px;
        }

        .tab-btn {
            flex: 1; 
            padding: 7px 6px;
            border: none; 
            background: transparent;
            border-radius: 8px; 
            font-weight: 600;
            font-size: 0.75rem; 
            color: #64748b;
            cursor: pointer; 
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .tab-btn.active {
            background: white;
            color: #16a34a;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .tab-btn i { font-size: 0.85rem; }

        /* FORM LABEL */
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 5px;
            display: block;
            letter-spacing: 0.3px;
        }

        .mb-4 { margin-bottom: 14px; }

        /* INPUT */
        .input-wrapper { position: relative; }

        .input-icon {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.85rem;
            pointer-events: none;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 11px 12px 11px 36px;
            border-radius: 10px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            font-size: 0.85rem;
            font-family: 'Poppins', sans-serif;
            color: #1e293b;
            transition: all 0.25s;
            appearance: none;
            -webkit-appearance: none;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #2ecc71;
            background: white;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.12);
        }

        .form-select { padding-right: 30px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }

        /* Semester style */
        .sem-aktif { border-color: #2ecc71 !important; background: #f0fdf4 !important; }
        .sem-arsip  { border-color: #f59e0b !important; background: #fffbeb !important; }

        /* Semester badge info */
        .sem-info {
            font-size: 0.7rem;
            margin-top: 5px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        .sem-info.aktif { background: #dcfce7; color: #16a34a; }
        .sem-info.arsip { background: #fef3c7; color: #d97706; }

        /* BUTTON */
        .btn-login {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.88rem;
            color: white;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.25s;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .btn-utama {
            background: linear-gradient(135deg, #2ecc71, #16a34a);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .btn-utama:active { transform: scale(0.98); }

        .btn-admin-style {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-admin-style:active { transform: scale(0.98); }

        /* FOOTER LINK */
        .footer-link {
            text-align: center;
            margin-top: 18px;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.7);
        }

        .footer-link a, .footer-link button {
            color: #2ecc71;
            text-decoration: none;
            font-weight: 600;
            background: none; border: none;
            cursor: pointer; padding: 0;
            font-family: 'Poppins', sans-serif;
            font-size: 0.78rem;
        }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            align-items: flex-end;
            justify-content: center;
            backdrop-filter: blur(3px);
        }

        .modal-overlay.show { display: flex; }

        .modal-sheet {
            background: white;
            border-radius: 20px 20px 0 0;
            padding: 25px 20px;
            width: 100%;
            max-width: 480px;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to   { transform: translateY(0); }
        }

        .modal-handle {
            width: 36px; height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin: 0 auto 15px;
        }

        .modal-sheet h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .akun-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid #2ecc71;
        }

        .akun-box.guru { border-left-color: #3b82f6; }

        .akun-box h6 {
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .akun-box p {
            font-size: 0.78rem;
            color: #64748b;
            line-height: 1.5;
            margin: 0;
        }

        .akun-box strong { color: #1e293b; }

        .btn-close-modal {
            width: 100%;
            padding: 12px;
            background: #f1f5f9;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            cursor: pointer;
            margin-top: 5px;
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body>

<div class="login-container">

    <!-- CARD FORM UTAMA -->
    <div class="card-form">

        <!-- LOGO TERBUNGKUS DI DALAM CARD -->
        <div class="logo-area">
            <div class="logo-icon"><i class="fa-solid fa-seedling"></i></div>
            <h1>PADI</h1>
            <p>Pembelajaran Anak Digital</p>
        </div>

        <?= $message ?>

        <!-- TAB UKURAN KECIL -->
        <div class="tab-login">
            <button class="tab-btn active" id="tab-siswa" onclick="switchTab('siswa')">
                <i class="fas fa-users"></i> Siswa & Guru
            </button>
            <button class="tab-btn" id="tab-admin" onclick="switchTab('admin')">
                <i class="fas fa-user-shield"></i> Admin
            </button>
        </div>

        <!-- FORM SISWA & GURU -->
        <form method="POST" id="form-siswa">
            <input type="hidden" name="mode_login" value="siswa_guru">

            <div class="mb-4">
                <label class="form-label">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Pilih Semester</label>
                <div class="input-wrapper">
                    <i class="fas fa-calendar-alt input-icon"></i>
                    <select name="semester" class="form-select sem-aktif" id="semester-select" onchange="updateSemesterStyle(this)">
                        <option value="2">📚 Semester 1 TP 2026/2027 (Aktif)</option>
                        <option value="1">📦 Semester 2 TP 2025/2026 (Arsip)</option>
                    </select>
                </div>
                <span class="sem-info aktif" id="sem-badge">✅ Mode Aktif — Data semester berjalan</span>
            </div>

            <button type="submit" class="btn-login btn-utama">
                <i class="fas fa-sign-in-alt me-1"></i> MASUK
            </button>
        </form>

        <!-- FORM ADMIN -->
        <form method="POST" id="form-admin" style="display:none;">
            <input type="hidden" name="mode_login" value="admin">
            <input type="hidden" name="semester" value="aktif">

            <div class="mb-4">
                <label class="form-label">Username Admin</label>
                <div class="input-wrapper">
                    <i class="fas fa-user-shield input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Username admin" required autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn-login btn-admin-style">
                <i class="fas fa-user-shield me-1"></i> MASUK SEBAGAI ADMIN
            </button>
        </form>

    </div>

    <!-- FOOTER LINK -->
    <div class="footer-link">
        Belum punya akun? <a href="register.php">Daftar</a>
        &nbsp;·&nbsp;
        <button onclick="document.getElementById('modalPanduan').classList.add('show')">Petunjuk Akses</button>
    </div>

</div>

<!-- MODAL BOTTOM SHEET -->
<div class="modal-overlay" id="modalPanduan" onclick="if(event.target===this) this.classList.remove('show')">
    <div class="modal-sheet">
        <div class="modal-handle"></div>
        <h5><i class="fas fa-info-circle me-1" style="color:#2ecc71"></i> Petunjuk Akses Uji Coba</h5>

        <div class="akun-box guru">
            <h6><i class="fas fa-chalkboard-teacher me-1"></i> Akun Guru</h6>
            <p>Username: <strong>guru</strong><br>Password: <strong>12345</strong></p>
        </div>

        <div class="akun-box">
            <h6><i class="fas fa-user-graduate me-1"></i> Akun Siswa</h6>
            <p>Username: <strong>peserta1 — peserta5</strong><br>Password: <strong>12345</strong></p>
        </div>

        <button class="btn-close-modal" onclick="document.getElementById('modalPanduan').classList.remove('show')">
            Tutup
        </button>
    </div>
</div>

<script>
function switchTab(mode) {
    document.getElementById('form-siswa').style.display = 'none';
    document.getElementById('form-admin').style.display = 'none';
    document.getElementById('tab-siswa').classList.remove('active');
    document.getElementById('tab-admin').classList.remove('active');

    if (mode === 'siswa') {
        document.getElementById('form-siswa').style.display = 'block';
        document.getElementById('tab-siswa').classList.add('active');
    } else {
        document.getElementById('form-admin').style.display = 'block';
        document.getElementById('tab-admin').classList.add('active');
    }
}

function updateSemesterStyle(select) {
    const badge = document.getElementById('sem-badge');
    select.classList.remove('sem-aktif', 'sem-arsip');
    badge.classList.remove('aktif', 'arsip');

    if (select.value === '1') {
        select.classList.add('sem-arsip');
        badge.classList.add('arsip');
        badge.textContent = '📦 Mode Arsip — Data semester lalu (hanya lihat)';
    } else {
        select.classList.add('sem-aktif');
        badge.classList.add('aktif');
        badge.textContent = '✅ Mode Aktif — Data semester berjalan';
    }
}
</script>
</body>
</html>