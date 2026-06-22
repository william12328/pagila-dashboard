<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    COALESCE(df.title, 'Film Key ' || fp.film_key::text) AS title,
    SUM(fp.rental_revenue) AS revenue
FROM public.fact_film_performance fp
JOIN public.dim_film df ON fp.film_key = df.film_key
GROUP BY df.title, fp.film_key
ORDER BY revenue DESC
LIMIT 10
";

$data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type: application/json");

echo json_encode($data);

?>
