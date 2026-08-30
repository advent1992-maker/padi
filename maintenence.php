<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang Diperbarui | PADI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #f4f7fe; 
            font-family: 'Poppins', sans-serif; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            margin: 0;
        }
        .maintenance-card {
            max-width: 500px;
            width: 90%;
            background: white;
            border-radius: 30px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .icon-box {
            background: #fff3cd;
            color: #ffc107;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 25px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(255, 193, 7, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
        h2 { color: #1e3a8a; font-weight: 700; }
        p { color: #666; font-size: 0.95rem; line-height: 1.6; }
        .btn-refresh {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-refresh:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
            color: white;
        }
    </style>
</head>
<body>

<div class="maintenance-card">
    <div class="icon-box">
        <i class="fas fa-tools"></i>
    </div> 
    <h2>Sistem PADI sedang Diperbarui</h2>
    <p>Saat ini <strong>PADI Portal</strong> sedang persiapan pergantian semester.</p>
    <p>Tetap Semangat Anak-anak semua</p>
    
    <div class="alert alert-light border-0 py-2 small mb-4" style="background: #f8f9fa;">
        <i class="fas fa-clock me-1 text-primary"></i> Perkiraan selesai: <strong>-</strong>
    </div>

    <a href="index.php" class="btn btn-refresh shadow-sm">
        <i class="fas fa-sync-alt me-2"></i> Cek Lagi Nanti Ya
    </a>

    <div class="mt-4 pt-3 border-top">
        <small class="text-muted">PADI</small>
    </div>
</div>

</body>
</html>