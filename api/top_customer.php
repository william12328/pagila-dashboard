<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    fa.customer_key,
    MAX(fa.customer_lifetime_value) AS customer_lifetime_value,
    MAX(ct.city) AS city
FROM public.fact_customer_activity fa
LEFT JOIN public.stg_city ct ON ct.city_id = fa.geography_key
GROUP BY fa.customer_key
ORDER BY customer_lifetime_value DESC
LIMIT 10
";

$data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type:application/json");

echo json_encode($data);

?>
