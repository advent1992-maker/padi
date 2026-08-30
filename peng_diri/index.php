<?php

header("Location: pengembangan_diri.php");
exit;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembangan Diri | PADI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #764ba2; --bg-light: #f4f7fe; }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Poppins', sans-serif; 
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        .dev-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 40px;
            box-shadow: 0 20px 50px rgba(118, 75, 162, 0.1);
            max-width: 600px;
            width: 90%;
            position: relative;
        }

        .icon-rocket {
            font-size: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        h2 { color: var(--primary-color); font-weight: 700; }
        p { color: #6c757d; }

        .progress-mewah {
            height: 12px;
            border-radius: 50px;
            background: #eee;
            margin: 30px 0;
            overflow: hidden;
        }

        .progress-bar-animated {
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .feature-preview {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .badge-preview {
            background: #f0ebf7;
            color: var(--primary-color);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-back {
            margin-top: 30px;
            background: var(--primary-color);
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-back:hover {
            transform: scale(1.05);
            background: #667eea;
            box-shadow: 0 10px 20px rgba(118, 75, 162, 0.2);
        }
    </style>
</head>
<body>

    <div class="dev-container">
        <div class="icon-rocket">
            <i class="fas fa-rocket"></i>
        </div>
        
        <h2>Fitur Sedang Disiapkan!</h2>
        <p>Kami sedang meracik materi eksklusif untuk membantu kamu menjadi siswa yang lebih unggul.</p>

        <div class="progress-mewah">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 75%"></div>
        </div>

        <div class="feature-preview">
            <span class="badge-preview"><i class="fas fa-code me-1"></i> Coding</span>
            <span class="badge-preview"><i class="fas fa-microscope me-1"></i> STEM</span>
            <span class="badge-preview"><i class="fas fa-book-reader me-1"></i> Literasi</span>
            <span class="badge-preview"><i class="fas fa-calculator me-1"></i> Numerasi</span>
            <span class="badge-preview"><i class="fas fa-medal me-1"></i> OSN</span>
        </div>

      <button onclick="window.location.href='../dashboard.php'" class="btn btn-primary btn-back">
    <i class="fas fa-arrow-left me-2"></i> KEMBALI KE DASHBOARD
</button>
        <p class="mt-4 small text-muted">Estimasi rilis: Segera!</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>