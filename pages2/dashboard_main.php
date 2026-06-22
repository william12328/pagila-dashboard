<?php
require_once(__DIR__ . "/../config/currency.php");

// Ambil Data KPI & Grafik Menggunakan PDO khusus untuk Dashboard Utama
$storeData = []; $storeLabels = []; $storeValues = [];
$trendLabels = []; $trendValues = []; $filmLabels = []; $filmValues = []; $churnLabels = []; $churnValues = []; $clvLabels = []; $clvValues = [];
$totalRevenue = 0; $totalRental = 0; $totalCustomer = 0; $avgTransaction = 0;

try {
    $stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM public.fact_sales");
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtRental = $conn->query("SELECT COUNT(*) AS total FROM public.fact_rental");
    $totalRental = $stmtRental->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtCustomer = $conn->query("SELECT COUNT(DISTINCT customer_key) AS total FROM public.fact_sales");
    $totalCustomer = $stmtCustomer->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtAvg = $conn->query("SELECT COALESCE(ROUND(AVG(amount), 2), 0) AS avg FROM public.fact_sales");
    $avgTransaction = $stmtAvg->fetch(PDO::FETCH_ASSOC)['avg'];

    $stmtStore = $conn->query("SELECT store_key, SUM(amount) as revenue FROM public.fact_sales GROUP BY store_key ORDER BY revenue DESC");
    $storeData = $stmtStore->fetchAll(PDO::FETCH_ASSOC);
    foreach($storeData as $row) {
        $storeLabels[] = "Store " . $row['store_key'];
        $storeValues[] = dollar_value($row['revenue']);
    }

    $stmtTrend = $conn->query("SELECT SUBSTRING(date_key::text FROM 1 FOR 4) || '-' || SUBSTRING(date_key::text FROM 5 FOR 2) as bulan, SUM(amount) as total_bulanan FROM public.fact_sales GROUP BY bulan ORDER BY bulan ASC");
    foreach($stmtTrend->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $trendLabels[] = $row['bulan'];
        $trendValues[] = dollar_value($row['total_bulanan']);
    }

    $stmtFilm = $conn->query("
        SELECT COALESCE(df.title, 'Film Key ' || fp.film_key::text) AS title,
               SUM(fp.rental_revenue) AS revenue
        FROM public.fact_film_performance fp
        JOIN public.dim_film df ON fp.film_key = df.film_key
        GROUP BY df.title, fp.film_key
        ORDER BY revenue DESC
        LIMIT 10
    ");
    foreach($stmtFilm->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $filmLabels[] = $row['title'];
        $filmValues[] = dollar_value($row['revenue']);
    }

    $stmtChurn = $conn->query("SELECT CASE WHEN churn_risk_score >= 0.7 THEN 'High Risk' WHEN churn_risk_score >= 0.4 THEN 'Medium Risk' ELSE 'Low Risk' END as status_risk, COUNT(*) as total_cust FROM public.fact_customer_activity GROUP BY status_risk");
    foreach($stmtChurn->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $churnLabels[] = $row['status_risk'];
        $churnValues[] = (int)$row['total_cust'];
    }

    $stmtCLV = $conn->query("SELECT customer_key, MAX(customer_lifetime_value) as clv FROM public.fact_customer_activity GROUP BY customer_key ORDER BY clv DESC LIMIT 5");
    foreach($stmtCLV->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clvLabels[] = "Cust " . $row['customer_key'];
        $clvValues[] = dollar_value($row['clv']);
    }
} catch (PDOException $e) {
    echo "<script>console.error('SQL Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Movie Rental Business Intelligence Dashboard</h2>

    <div class="row g-4">
        <div class="col-lg-3 col-md-6"><div class="card-dashboard p-3 bg-white border rounded shadow-sm"><h6>Total Revenue</h6><h2><?= dollar($totalRevenue); ?></h2></div></div>
        <div class="col-lg-3 col-md-6"><div class="card-dashboard p-3 bg-white border rounded shadow-sm"><h6>Total Rental</h6><h2><?= number_format($totalRental); ?></h2></div></div>
        <div class="col-lg-3 col-md-6"><div class="card-dashboard p-3 bg-white border rounded shadow-sm"><h6>Total Customer</h6><h2><?= number_format($totalCustomer); ?></h2></div></div>
        <div class="col-lg-3 col-md-6"><div class="card-dashboard p-3 bg-white border rounded shadow-sm"><h6>Average Transaction</h6><h2><?= dollar($avgTransaction); ?></h2></div></div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5 class="mb-3">Revenue Trend</h5>
                <div style="position: relative; height:300px;"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5 class="mb-3">Revenue by Store</h5>
                <div style="position: relative; height:300px;"><canvas id="storeChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5>Top 10 Film Revenue</h5>
                <div style="position: relative; height:250px;"><canvas id="filmChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5>Monthly Revenue</h5>
                <div style="position: relative; height:250px;"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5>Customer Churn Risk</h5>
                <div style="position: relative; height:250px;"><canvas id="customerChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5>Top Customer Lifetime Value</h5>
                <div style="position: relative; height:250px;"><canvas id="topCustomerChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-4">
        <div class="col-lg-12">
            <div class="chart-card p-3 bg-white border rounded shadow-sm">
                <h5 class="mb-3">Revenue Store Table</h5>
                <table class="table table-hover table-striped border">
                    <thead class="table-dark"><tr><th>Store ID</th><th>Total Revenue</th></tr></thead>
                    <tbody>
                        <?php if(empty($storeData)): ?>
                            <tr><td colspan="2" class="text-center text-muted">Belum ada data transaksi store.</td></tr>
                        <?php else: ?>
                            <?php foreach($storeData as $row): ?>
                                <tr><td><strong>Store <?= $row['store_key']; ?></strong></td><td><?= dollar($row['revenue']); ?></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const storeLabels = <?= json_encode($storeLabels); ?>;
    const storeValues = <?= json_encode($storeValues); ?>;
    const trendLabels = <?= json_encode($trendLabels); ?>;
    const trendValues = <?= json_encode($trendValues); ?>;
    const filmLabels  = <?= json_encode($filmLabels); ?>;
    const filmValues  = <?= json_encode($filmValues); ?>;
    const churnLabels = <?= json_encode($churnLabels); ?>;
    const churnValues = <?= json_encode($churnValues); ?>;
    const clvLabels   = <?= json_encode($clvLabels); ?>;
    const clvValues   = <?= json_encode($clvValues); ?>;

    const optionsConfig = { responsive: true, maintainAspectRatio: false };

    document.addEventListener("DOMContentLoaded", function() {
        try {
            if(document.getElementById('storeChart')) {
                new Chart(document.getElementById('storeChart'), { type: 'doughnut', data: { labels: storeLabels, datasets: [{ data: storeValues, backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545'] }] }, options: optionsConfig });
            }
            if(document.getElementById('revenueChart')) {
                new Chart(document.getElementById('revenueChart'), { type: 'line', data: { labels: trendLabels, datasets: [{ label: 'Pendapatan ($)', data: trendValues, borderColor: '#0d6efd', fill: true, backgroundColor: 'rgba(13,110,253,0.05)', tension: 0.2 }] }, options: optionsConfig });
            }
            if(document.getElementById('filmChart')) {
                new Chart(document.getElementById('filmChart'), { type: 'bar', data: { labels: filmLabels, datasets: [{ label: 'Revenue ($)', data: filmValues, backgroundColor: '#6f42c1' }] }, options: { ...optionsConfig, indexAxis: 'y' } });
            }
            if(document.getElementById('monthlyChart')) {
                new Chart(document.getElementById('monthlyChart'), { type: 'bar', data: { labels: trendLabels, datasets: [{ label: 'Total Bulanan', data: trendValues, backgroundColor: '#198754' }] }, options: optionsConfig });
            }
            if(document.getElementById('customerChart')) {
                new Chart(document.getElementById('customerChart'), { type: 'pie', data: { labels: churnLabels, datasets: [{ data: churnValues, backgroundColor: ['#dc3545', '#198754', '#ffc107'] }] }, options: optionsConfig });
            }
            if(document.getElementById('topCustomerChart')) {
                new Chart(document.getElementById('topCustomerChart'), { type: 'bar', data: { labels: clvLabels, datasets: [{ label: 'Nilai CLV ($)', data: clvValues, backgroundColor: '#fd7e14' }] }, options: optionsConfig });
            }
        } catch (error) {
            console.error("Gagal menggambar chart:", error);
        }
    });
</script>
