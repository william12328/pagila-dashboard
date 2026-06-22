<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    TO_CHAR(TO_DATE(f.date_key::text, 'YYYYMMDD'), 'FMMonth') AS bulan,
    SUM(f.amount) AS revenue
FROM public.fact_sales f
GROUP BY EXTRACT(YEAR FROM TO_DATE(f.date_key::text, 'YYYYMMDD')),
         EXTRACT(MONTH FROM TO_DATE(f.date_key::text, 'YYYYMMDD')),
         bulan
ORDER BY EXTRACT(YEAR FROM TO_DATE(f.date_key::text, 'YYYYMMDD')),
         EXTRACT(MONTH FROM TO_DATE(f.date_key::text, 'YYYYMMDD'))
";

$data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');

echo json_encode($data);

?>
