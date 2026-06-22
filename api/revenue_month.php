<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    EXTRACT(YEAR FROM TO_DATE(f.date_key::text, 'YYYYMMDD'))::int AS year,
    EXTRACT(MONTH FROM TO_DATE(f.date_key::text, 'YYYYMMDD'))::int AS month,
    TO_CHAR(TO_DATE(f.date_key::text, 'YYYYMMDD'), 'FMMonth') AS month_name,
    SUM(f.amount) AS revenue
FROM public.fact_sales f
GROUP BY year, month, month_name
ORDER BY year, month
";

$data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type: application/json");

echo json_encode($data);

?>
