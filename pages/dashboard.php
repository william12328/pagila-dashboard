<?php
include(__DIR__ . "/../config/db.php");
include(__DIR__ . "/../config/currency.php");
include(__DIR__ . "/../includes/header.php");
include(__DIR__ . "/../includes/sidebar.php");
include(__DIR__ . "/../includes/navbar.php");

// ===============================
// KPI Dashboard (Menggunakan PDO)
// ===============================

// Total Revenue
$stmt = $conn->query("SELECT COALESCE(SUM(amount),0) total FROM fact_sales");
$totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total Rental
$stmtRental = $conn->query("SELECT COUNT(*) AS total FROM fact_rental");
$totalRental = $stmtRental->fetch(PDO::FETCH_ASSOC)['total'];

// Total Customer
$stmtCustomer = $conn->query("SELECT COUNT(DISTINCT customer_key) AS total FROM fact_sales");
$totalCustomer = $stmtCustomer->fetch(PDO::FETCH_ASSOC)['total'];

// Average Transaction
$stmtAvg = $conn->query("SELECT COALESCE(ROUND(AVG(amount),2),0) AS avg FROM fact_sales");
$avgTransaction = $stmtAvg->fetch(PDO::FETCH_ASSOC)['avg'];
?>

<div class="content">
    <h2 class="mb-4">Movie Rental Business Intelligence Dashboard</h2>

    <div class="row g-4">
        <div class="col-lg-3 col-md-6">
            <div class="card-dashboard">
                <h6>Total Revenue</h6>
                <h2><?= dollar($totalRevenue); ?></h2>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card-dashboard">
                <h6>Total Rental</h6>
                <h2><?= number_format($totalRental); ?></h2>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card-dashboard">
                <h6>Total Customer</h6>
                <h2><?= number_format($totalCustomer); ?></h2>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card-dashboard">
                <h6>Average Transaction</h6>
                <h2><?= dollar($avgTransaction); ?></h2>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <h5 class="mb-3">Revenue Trend</h5>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <h5 class="mb-3">Revenue by Store</h5>
                <canvas id="storeChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="chart-card">
                <h5>Top 10 Film Revenue</h5>
                <canvas id="filmChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <h5>Monthly Revenue</h5>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="chart-card">
                <h5>Customer Churn Risk</h5>
                <canvas id="customerChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <h5>Top Customer Lifetime Value</h5>
                <canvas id="topCustomerChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="chart-card">
                <h5 class="mb-3">Revenue Store Table</h5>
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Store</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="storeTable">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
include(__DIR__ . "/../includes/footer.php");
?>
