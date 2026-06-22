<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Rental BI Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Mengamankan layout dasar langsung di sini jika file css/style.css kamu bermasalah */
        body {
            background-color: #f8f9fa;
        }
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }
        .sidebar-wrapper {
            min-width: 250px;
            max-width: 250px;
            background: #212529;
            color: #fff;
            min-height: 100vh;
        }
        .sidebar-wrapper a {
            color: rgba(255,255,255,.75);
            text-decoration: none;
        }
        .sidebar-wrapper a:hover {
            color: #fff;
            background: rgba(255,255,255,.1);
        }
        .main-content {
            width: 100%;
            padding: 20px;
        }
        .card-dashboard {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .chart-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="wrapper">