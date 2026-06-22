<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    COALESCE((SELECT SUM(amount) FROM public.fact_sales), 0) AS revenue,
    COALESCE((SELECT COUNT(*) FROM public.fact_rental), 0) AS rental,
    COALESCE((SELECT COUNT(DISTINCT customer_key) FROM public.fact_sales), 0) AS customer,
    COALESCE((SELECT ROUND(AVG(amount), 2) FROM public.fact_sales), 0) AS avg_transaction
";

$data = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);

header("Content-Type: application/json");

echo json_encode($data);

?>
