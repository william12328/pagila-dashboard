<?php

include(__DIR__ . "/../config/db.php");

$sql = "
SELECT
    CASE
        WHEN churn_risk_score < 30 THEN 'Low'
        WHEN churn_risk_score < 70 THEN 'Medium'
        ELSE 'High'
    END AS risk,
    COUNT(*) AS total
FROM public.fact_customer_activity
GROUP BY risk
ORDER BY risk
";

$data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type:application/json");

echo json_encode($data);

?>
