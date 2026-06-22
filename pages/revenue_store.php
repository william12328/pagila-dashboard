<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    s.store_key,
    SUM(f.amount) AS revenue
FROM public.fact_sales f
JOIN public.dim_store s ON f.store_key = s.store_key
GROUP BY s.store_key
ORDER BY revenue DESC
";

$data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');

echo json_encode($data);

?>
