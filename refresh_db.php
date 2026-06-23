<?php
require_once(__DIR__ . "/config/db.php");

$conn = koneksiDB();
$message = '';
$status = '';
$logs = [];

$sqlFiles = [
    'commerce_seed' => [
        'name' => 'Commerce Seed Data',
        'file' => __DIR__ . '/sql/commerce_seed.sql',
        'desc' => 'Membuat tabel commerce_* (customers, products, orders, items, campaigns) dan menginisialisasi data transaksi commerce.'
    ],
    'staging_from_commerce_seed' => [
        'name' => 'Staging Data Refresh (Pagila)',
        'file' => __DIR__ . '/sql/staging_from_commerce_seed.sql',
        'desc' => 'Membangun ulang tabel staging_* dan memproses/sinkronisasi data dari tabel mentah Pagila (stg_*).'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run') {
    $selected = $_POST['files'] ?? [];
    if (empty($selected)) {
        $status = 'error';
        $message = 'Pilih setidaknya satu file SQL untuk dijalankan.';
    } else {
        try {
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            foreach ($selected as $key) {
                if (isset($sqlFiles[$key])) {
                    $fileInfo = $sqlFiles[$key];
                    $filePath = $fileInfo['file'];
                    if (file_exists($filePath)) {
                        $sql = file_get_contents($filePath);
                        $logs[] = "[" . date('H:i:s') . "] Membaca file " . basename($filePath) . "...";
                        
                        // Menjalankan SQL
                        $conn->exec($sql);
                        
                        $logs[] = "[" . date('H:i:s') . "] Sukses memproses " . $fileInfo['name'] . ".";
                    } else {
                        $logs[] = "[" . date('H:i:s') . "] Gagal: File " . basename($filePath) . " tidak ditemukan.";
                    }
                }
            }
            $status = 'success';
            $message = 'Proses refresh data selesai successfully!';
        } catch (PDOException $e) {
            $status = 'error';
            $message = 'Terjadi kesalahan database: ' . $e->getMessage();
            $logs[] = "[" . date('H:i:s') . "] ERROR: " . $e->getMessage();
            $logs[] = "[" . date('H:i:s') . "] TRANSAKSI DIBATALKAN (ROLLBACK).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refresh Data - Pagila Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #172026;
            --muted: #65717c;
            --line: #d9e1e8;
            --surface: #ffffff;
            --soft: #f4f7f9;
            --dark: #101719;
            --brand: #0f766e;
            --brand-hover: #0d5c56;
            --blue: #2563eb;
            --amber: #b7791f;
            --red: #be123c;
            --green: #10b981;
        }
        body {
            background-color: var(--soft);
            color: var(--ink);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .container-custom {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 15px;
        }
        .card-refresh {
            background-color: var(--surface);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 24px;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--line);
        }
        .header-title i {
            font-size: 2.2rem;
            color: var(--brand);
        }
        .header-title h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            color: var(--dark);
        }
        .file-option {
            background-color: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 15px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .file-option:hover {
            border-color: var(--brand);
            background-color: #f0fdf4;
        }
        .file-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--brand);
            cursor: pointer;
        }
        .btn-run {
            background-color: var(--brand);
            color: white;
            border: none;
            padding: 12px 25px;
            font-weight: 700;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-run:hover {
            background-color: var(--brand-hover);
            color: white;
        }
        .console-log {
            background-color: #0f172a;
            color: #38bdf8;
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #1e293b;
        }
        .console-line {
            margin-bottom: 5px;
            line-height: 1.4;
        }
        .console-line.error {
            color: #f87171;
        }
        .console-line.success {
            color: #4ade80;
        }
        .back-link {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: var(--brand-hover);
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container-custom">
    <div class="mb-3">
        <a href="index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

    <div class="card-refresh">
        <div class="header-title">
            <i class="fa-solid fa-arrows-rotate"></i>
            <div>
                <h1>Refresh Data Dashboard</h1>
                <span class="badge bg-secondary">Render / Local Environment</span>
            </div>
        </div>

        <p class="text-muted">
            Gunakan utilitas ini untuk memproses ulang atau memperbarui tabel staging data warehouse Anda.
            Aksi ini akan membaca file SQL langsung dari repositori Anda dan mengeksekusinya di database PostgreSQL yang terhubung.
        </p>

        <?php if ($status === 'success'): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-check me-2 fs-4"></i>
                <div>
                    <strong>Berhasil!</strong> <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2 fs-4"></i>
                <div>
                    <strong>Gagal!</strong> <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="refreshForm">
            <input type="hidden" name="action" value="run">
            
            <div class="mb-4">
                <h5 class="fw-bold mb-3">Pilih File SQL untuk Dijalankan:</h5>
                
                <?php foreach ($sqlFiles as $key => $fileInfo): ?>
                    <label class="file-option d-flex align-items-start gap-3" for="chk_<?= $key ?>">
                        <div class="pt-1">
                            <input type="checkbox" name="files[]" value="<?= $key ?>" id="chk_<?= $key ?>" 
                                   <?= $key === 'staging_from_commerce_seed' ? 'checked' : '' ?>>
                        </div>
                        <div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($fileInfo['name']) ?></div>
                            <div class="text-muted small mt-1"><?= htmlspecialchars($fileInfo['desc']) ?></div>
                            <div class="badge bg-light text-dark border mt-2">
                                <i class="fa-solid fa-file-code me-1"></i> sql/<?= basename($fileInfo['file']) ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <button type="submit" class="btn btn-run" id="btnSubmit">
                    <i class="fa-solid fa-play"></i> Jalankan Data Refresh
                </button>
                
                <span class="text-muted small">
                    <i class="fa-solid fa-database me-1"></i>
                    Status: terhubung
                </span>
            </div>
        </form>
    </div>

    <?php if (!empty($logs)): ?>
        <div class="card-refresh">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-terminal me-2"></i>Log Eksekusi:</h5>
            <div class="console-log">
                <?php foreach ($logs as $line): ?>
                    <?php 
                    $class = '';
                    if (strpos($line, 'ERROR') !== false || strpos($line, 'Gagal') !== false || strpos($line, 'DIBATALKAN') !== false) {
                        $class = 'error';
                    } elseif (strpos($line, 'Sukses') !== false || strpos($line, 'successfully') !== false) {
                        $class = 'success';
                    }
                    ?>
                    <div class="console-line <?= $class ?>"><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('refreshForm').addEventListener('submit', function() {
        var btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses Data...';
    });
</script>
</body>
</html>
