<?php

require_once(__DIR__ . "/db.php");
require_once(__DIR__ . "/currency.php");

include(__DIR__ . "/../includes/header.php");

include(__DIR__ . "/../includes/sidebar.php");

$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM public.fact_sales")->fetchColumn();

$totalRental = $conn->query("SELECT COUNT(*) FROM public.fact_rental")->fetchColumn();

$totalCustomer = $conn->query("SELECT COUNT(DISTINCT customer_key) FROM public.fact_sales")->fetchColumn();

$avgTransaction = $conn->query("SELECT COALESCE(ROUND(AVG(amount), 2), 0) FROM public.fact_sales")->fetchColumn();

?>

<div class="content">

<h2>

Movie Rental Dashboard

</h2>

<div class="row">

<div class="col-md-3">

<div class="card-dashboard">

Revenue

<h2>

<?=dollar($totalRevenue)?>

</h2>

</div>

</div>

<div class="col-md-3">

<div class="card-dashboard">

Rental

<h2>

<?=$totalRental?>

</h2>

</div>

</div>

<div class="col-md-3">

<div class="card-dashboard">

Customer

<h2>

<?=$totalCustomer?>

</h2>

</div>

</div>

<div class="col-md-3">

<div class="card-dashboard">

Avg Transaction

<h2>

<?=dollar($avgTransaction)?>

</h2>

</div>

</div>

</div>

<div class="row">

<div class="col-md-8">

<div class="chart-box">

<canvas id="revenueChart">

</canvas>

</div>

</div>

<div class="col-md-4">

<div class="chart-box">

<canvas id="pieChart">

</canvas>

</div>

</div>

</div>

</div>

<script src="../assets/js/dashboard.js">

</script>

</body>

</html>
