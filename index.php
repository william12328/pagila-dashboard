<?php
require_once(__DIR__ . "/config/db.php");
require_once(__DIR__ . "/config/currency.php");

$conn = koneksiDB();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validDateParam($value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function fetchRows(PDO $conn, $sql, array $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function scalarValue(PDO $conn, $sql, array $params = [], $default = 0) {
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    } catch (PDOException $e) {
        return $default;
    }
}

function tableExists(PDO $conn, $table) {
    $exists = scalarValue(
        $conn,
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = :table
        )",
        [':table' => $table],
        false
    );

    return $exists === true || $exists === 1 || $exists === '1' || $exists === 't';
}

function percent($value, $decimals = 1) {
    return number_format((float)$value, $decimals, ',', '.') . '%';
}

$allowedPages = ['overview', 'insights', 'catalog', 'orders', 'customers', 'marketing', 'films', 'stores', 'rental'];
$page = isset($_GET['page']) && in_array($_GET['page'], $allowedPages, true) ? $_GET['page'] : 'overview';
$dateFrom = validDateParam($_GET['date_from'] ?? '');
$dateTo = validDateParam($_GET['date_to'] ?? '');
$statusFilter = isset($_GET['status']) && preg_match('/^[a-z_]+$/', (string)$_GET['status']) ? $_GET['status'] : '';
$categoryFilter = isset($_GET['category']) && preg_match('/^[A-Za-z0-9 &-]+$/', (string)$_GET['category']) ? $_GET['category'] : '';
$customerSearch = isset($_GET['customer_search']) ? trim((string)$_GET['customer_search']) : '';
$customerSearch = mb_strlen($customerSearch) <= 120 ? $customerSearch : mb_substr($customerSearch, 0, 120);
$countryFilter = isset($_GET['country']) ? trim((string)$_GET['country']) : '';
$countryFilter = mb_strlen($countryFilter) <= 120 ? $countryFilter : mb_substr($countryFilter, 0, 120);

$stagingReady = tableExists($conn, 'staging_customer')
    && tableExists($conn, 'staging_film')
    && tableExists($conn, 'staging_store')
    && tableExists($conn, 'stg_rental')
    && tableExists($conn, 'stg_payment')
    && tableExists($conn, 'stg_inventory');

$stagingCustomerTable = tableExists($conn, 'staging_customers') ? 'staging_customers' : (tableExists($conn, 'staging_customer') ? 'staging_customer' : '');
$customerSourceLabel = $stagingCustomerTable !== '' ? $stagingCustomerTable : 'staging_customer';
$customerSourceSql = $stagingCustomerTable !== ''
    ? "
        SELECT customer_id,
               COALESCE(NULLIF(INITCAP(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))), ''), email, 'Customer #' || customer_id::text) AS full_name,
               COALESCE(email, '-') AS email,
               COALESCE(phone, '-') AS phone,
               COALESCE(city, '-') AS city,
               COALESCE(country, '-') AS country,
               COALESCE(active, false) AS is_active,
               create_date::date AS registered_at
        FROM public." . $stagingCustomerTable . "
    "
    : "
        SELECT NULL::integer AS customer_id,
               '-' AS full_name,
               '-' AS email,
               '-' AS phone,
               '-' AS city,
               '-' AS country,
               false AS is_active,
               NULL::date AS registered_at
        WHERE false
    ";
$customerSourceAvailable = $stagingCustomerTable !== '';

$customerFilters = [];
$customerParams = [];
if ($customerSearch !== '') {
    $customerFilters[] = "(
        c.full_name ILIKE :customer_search
        OR c.email ILIKE :customer_search
        OR c.phone ILIKE :customer_search
        OR c.city ILIKE :customer_search
        OR c.country ILIKE :customer_search
    )";
    $customerParams[':customer_search'] = '%' . $customerSearch . '%';
}
if ($countryFilter !== '') {
    $customerFilters[] = "c.country = :customer_country";
    $customerParams[':customer_country'] = $countryFilter;
}
$customerWhere = $customerFilters ? ' WHERE ' . implode(' AND ', $customerFilters) : '';

$orderFilters = [];
$orderParams = [];
if ($dateFrom !== '') {
    $orderFilters[] = "o.order_date::date >= :date_from";
    $orderParams[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $orderFilters[] = "o.order_date::date <= :date_to";
    $orderParams[':date_to'] = $dateTo;
}
if ($statusFilter !== '') {
    $orderFilters[] = "o.status = :status";
    $orderParams[':status'] = $statusFilter;
}
$orderWhere = $orderFilters ? ' WHERE ' . implode(' AND ', $orderFilters) : '';

$productFilters = [];
$productParams = [];
if ($categoryFilter !== '') {
    $productFilters[] = "p.category = :category";
    $productParams[':category'] = $categoryFilter;
}
$productWhere = $productFilters ? ' WHERE ' . implode(' AND ', $productFilters) : '';

$paymentFilters = [];
$paymentParams = [];
if ($dateFrom !== '') {
    $paymentFilters[] = "p.payment_date::date >= :payment_date_from";
    $paymentParams[':payment_date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $paymentFilters[] = "p.payment_date::date <= :payment_date_to";
    $paymentParams[':payment_date_to'] = $dateTo;
}
$paymentWhere = $paymentFilters ? ' WHERE ' . implode(' AND ', $paymentFilters) : '';

$rentalFilters = [];
$rentalParams = [];
if ($dateFrom !== '') {
    $rentalFilters[] = "r.rental_date::date >= :rental_date_from";
    $rentalParams[':rental_date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $rentalFilters[] = "r.rental_date::date <= :rental_date_to";
    $rentalParams[':rental_date_to'] = $dateTo;
}
if ($statusFilter !== '') {
    $rentalFilters[] = "(
        CASE
            WHEN r.return_date IS NULL THEN 'open'
            WHEN r.return_date > r.rental_date + INTERVAL '7 days' THEN 'overdue'
            ELSE 'returned'
        END
    ) = :rental_status";
    $rentalParams[':rental_status'] = $statusFilter;
}
if ($categoryFilter !== '') {
    $rentalFilters[] = "COALESCE(sf.category, 'Uncategorized') = :rental_category";
    $rentalParams[':rental_category'] = $categoryFilter;
}
$rentalWhere = $rentalFilters ? ' WHERE ' . implode(' AND ', $rentalFilters) : '';

$filmFilters = [];
$filmParams = [];
if ($categoryFilter !== '') {
    $filmFilters[] = "COALESCE(sf.category, 'Uncategorized') = :film_category";
    $filmParams[':film_category'] = $categoryFilter;
}
$filmWhere = $filmFilters ? ' WHERE ' . implode(' AND ', $filmFilters) : '';

$statusOptions = [
    ['status' => 'open'],
    ['status' => 'returned'],
    ['status' => 'overdue'],
];
$categoryOptions = tableExists($conn, 'staging_film') ? fetchRows($conn, "
    SELECT DISTINCT COALESCE(category, 'Uncategorized') AS category
    FROM public.staging_film
    ORDER BY category
") : [];
$countryOptions = $customerSourceAvailable ? fetchRows($conn, "
    SELECT DISTINCT country
    FROM (" . $customerSourceSql . ") c
    WHERE country <> '-'
    ORDER BY country
") : [];

$rentalPaymentFilters = $rentalFilters;
$rentalPaymentParams = $rentalParams;
if ($dateFrom !== '') {
    $rentalPaymentFilters[] = "p.payment_date::date >= :payment_date_from";
    $rentalPaymentParams[':payment_date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $rentalPaymentFilters[] = "p.payment_date::date <= :payment_date_to";
    $rentalPaymentParams[':payment_date_to'] = $dateTo;
}
$rentalPaymentWhere = $rentalPaymentFilters ? ' WHERE ' . implode(' AND ', $rentalPaymentFilters) : '';

$gmv = scalarValue($conn, "
    SELECT COALESCE(SUM(p.amount), 0)
    FROM public.stg_payment p
    LEFT JOIN public.stg_rental r ON r.rental_id = p.rental_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN public.staging_film sf ON sf.film_id = i.film_id
    " . $rentalPaymentWhere,
    $rentalPaymentParams
);
$paidRevenue = $gmv;
$totalOrders = scalarValue($conn, "
    SELECT COUNT(*)
    FROM public.stg_rental r
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN public.staging_film sf ON sf.film_id = i.film_id
    " . $rentalWhere,
    $rentalParams
);
$activeCustomers = scalarValue($conn, "
    SELECT COUNT(DISTINCT p.customer_id)
    FROM public.stg_payment p
    LEFT JOIN public.stg_rental r ON r.rental_id = p.rental_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN public.staging_film sf ON sf.film_id = i.film_id
    " . $rentalPaymentWhere,
    $rentalPaymentParams
);
$avgOrder = $totalOrders > 0 ? $gmv / $totalOrders : 0;
$cancelledOrders = scalarValue($conn, "SELECT COUNT(*) FROM public.stg_rental WHERE return_date > rental_date + INTERVAL '7 days'", [], 0);
$cancelRate = $totalOrders > 0 ? ($cancelledOrders / $totalOrders) * 100 : 0;
$lowStock = scalarValue($conn, "
    SELECT COUNT(*)
    FROM (
        SELECT f.film_id, COUNT(i.inventory_id) AS inventory_count
        FROM public.staging_film f
        LEFT JOIN public.stg_inventory i ON i.film_id = f.film_id
        GROUP BY f.film_id
    ) stock
    WHERE inventory_count <= 2
", [], 0);
$activeProducts = scalarValue($conn, "SELECT COUNT(*) FROM public.staging_film", [], 0);
$lowStockRate = $activeProducts > 0 ? ($lowStock / $activeProducts) * 100 : 0;

$pagilaRevenue = scalarValue($conn, "
    SELECT COALESCE(SUM(p.amount), 0)
    FROM public.stg_payment p
    LEFT JOIN public.stg_rental r ON r.rental_id = p.rental_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN public.staging_film sf ON sf.film_id = i.film_id
    " . $rentalPaymentWhere,
    $rentalPaymentParams,
    0
);
$pagilaRentals = scalarValue($conn, "SELECT COUNT(*) FROM public.stg_rental", [], 0);
$pagilaFilmCount = tableExists($conn, 'staging_film') ? scalarValue($conn, "SELECT COUNT(*) FROM public.staging_film", [], 0) : 0;

$filmBiRows = tableExists($conn, 'staging_film') ? fetchRows($conn, "
    WITH film_stats AS (
        SELECT i.film_id,
               COUNT(DISTINCT i.inventory_id) AS inventory_count,
               COUNT(DISTINCT r.rental_id) AS rented_copies,
               COALESCE(SUM(p.amount), 0) AS rental_revenue,
               COUNT(DISTINCT r.customer_id) AS unique_customers
        FROM public.stg_inventory i
        LEFT JOIN public.stg_rental r ON r.inventory_id = i.inventory_id
        LEFT JOIN public.stg_payment p ON p.rental_id = r.rental_id
        GROUP BY i.film_id
    )
    SELECT sf.title,
           sf.film_id AS film_key,
           COALESCE(fs.inventory_count, 0) AS inventory_count,
           COALESCE(fs.rented_copies, 0) AS rented_copies,
           CASE WHEN COALESCE(fs.inventory_count, 0) > 0 THEN fs.rented_copies::numeric / fs.inventory_count ELSE 0 END AS utilization_rate,
           COALESCE(fs.rental_revenue, 0) AS rental_revenue,
           CASE WHEN sf.replacement_cost > 0 THEN (COALESCE(fs.rental_revenue, 0) / sf.replacement_cost) * 100 ELSE 0 END AS roi_percent
    FROM public.staging_film sf
    LEFT JOIN film_stats fs ON fs.film_id = sf.film_id
    " . $filmWhere . "
    ORDER BY rental_revenue DESC
    LIMIT 12
") : [];

$storeBiRows = tableExists($conn, 'staging_store') ? fetchRows($conn, "
    WITH store_stats AS (
        SELECT i.store_id,
               COUNT(DISTINCT r.rental_id) AS total_transactions,
               COALESCE(SUM(p.amount), 0) AS total_revenue,
               COUNT(DISTINCT r.customer_id) AS unique_customers,
               COUNT(DISTINCT i.film_id) AS total_films_rented
        FROM public.stg_inventory i
        LEFT JOIN public.stg_rental r ON r.inventory_id = i.inventory_id
        LEFT JOIN public.stg_payment p ON p.rental_id = r.rental_id
        GROUP BY i.store_id
    )
    SELECT ss.store_id AS store_key,
           COALESCE(st.total_revenue, 0) AS total_revenue,
           COALESCE(st.total_transactions, 0) AS total_transactions,
           COALESCE(st.unique_customers, 0) AS unique_customers,
           COALESCE(st.total_revenue * 0.65, 0) AS net_profit,
           CASE WHEN COALESCE(st.total_revenue, 0) > 0 THEN 65 ELSE 0 END AS profit_margin_percent,
           0 AS customer_satisfaction_score,
           0 AS low_stock_alerts,
           COALESCE(st.total_films_rented, 0) AS total_films_rented
    FROM public.staging_store ss
    LEFT JOIN store_stats st ON st.store_id = ss.store_id
    ORDER BY total_revenue DESC
") : [];

$storeTotalRevenue = array_sum(array_map(fn($row) => (float)$row['total_revenue'], $storeBiRows));
$storeTotalProfit = array_sum(array_map(fn($row) => (float)$row['net_profit'], $storeBiRows));
$storeTotalTransactions = array_sum(array_map(fn($row) => (int)$row['total_transactions'], $storeBiRows));
$storeAvgMargin = count($storeBiRows) > 0 ? array_sum(array_map(fn($row) => (float)$row['profit_margin_percent'], $storeBiRows)) / count($storeBiRows) : 0;

$salesTrend = fetchRows($conn, "
    SELECT TO_CHAR(p.payment_date, 'YYYY-MM') AS period_label,
           COALESCE(SUM(p.amount), 0) AS revenue,
           COUNT(DISTINCT p.rental_id) AS orders
    FROM public.stg_payment p
    LEFT JOIN public.stg_rental r ON r.rental_id = p.rental_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN public.staging_film sf ON sf.film_id = i.film_id
    " . $rentalPaymentWhere . "
    GROUP BY period_label
    ORDER BY period_label
", $rentalPaymentParams);

$categoryRows = fetchRows($conn, "
    SELECT COALESCE(sf.category, 'Uncategorized') AS category,
           COALESCE(SUM(p.amount), 0) AS revenue,
           COUNT(DISTINCT r.rental_id) AS units
    FROM public.staging_film sf
    LEFT JOIN public.stg_inventory i ON i.film_id = sf.film_id
    LEFT JOIN public.stg_rental r ON r.inventory_id = i.inventory_id
    LEFT JOIN public.stg_payment p ON p.rental_id = r.rental_id
    " . $filmWhere . "
    GROUP BY COALESCE(sf.category, 'Uncategorized')
    ORDER BY revenue DESC
", $filmParams);

$productRows = fetchRows($conn, "
    WITH film_stats AS (
        SELECT i.film_id,
               COUNT(DISTINCT i.inventory_id) AS inventory_count,
               COUNT(DISTINCT r.rental_id) AS rentals,
               COALESCE(SUM(p.amount), 0) AS revenue
        FROM public.stg_inventory i
        LEFT JOIN public.stg_rental r ON r.inventory_id = i.inventory_id
        LEFT JOIN public.stg_payment p ON p.rental_id = r.rental_id
        GROUP BY i.film_id
    )
    SELECT sf.film_id AS product_id,
           sf.title AS product_name,
           COALESCE(sf.category, 'Uncategorized') AS category,
           COALESCE(sf.rating, 'Rental Film') AS format_type,
           sf.rental_rate AS price,
           COALESCE(fs.inventory_count, 0) AS stock_qty,
           2 AS reorder_level,
           COALESCE(fs.rentals, 0) AS units_sold,
           COALESCE(fs.revenue, 0) AS revenue
    FROM public.staging_film sf
    LEFT JOIN film_stats fs ON fs.film_id = sf.film_id
    " . $filmWhere . "
    ORDER BY revenue DESC, product_name ASC
    LIMIT 14
", $filmParams);

$orderRows = fetchRows($conn, "
    SELECT 'RNT-' || r.rental_id::text AS order_number,
           r.rental_date AS order_date,
           CASE
               WHEN r.return_date IS NULL THEN 'open'
               WHEN r.return_date > r.rental_date + INTERVAL '7 days' THEN 'overdue'
               ELSE 'returned'
           END AS status,
           'Store ' || COALESCE(i.store_id::text, st.store_id::text, '-') AS channel,
           'Staging Rental' AS payment_method,
           COALESCE(p.total_amount, 0) AS total_amount,
           c.full_name,
           1 AS item_count
    FROM public.stg_rental r
    LEFT JOIN (" . $customerSourceSql . ") c ON c.customer_id = r.customer_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN public.staging_store st ON st.store_id = i.store_id
    LEFT JOIN public.staging_film sf ON sf.film_id = i.film_id
    LEFT JOIN (
        SELECT rental_id, COALESCE(SUM(amount), 0) AS total_amount
        FROM public.stg_payment
        GROUP BY rental_id
    ) p ON p.rental_id = r.rental_id
    " . $rentalWhere . "
    ORDER BY order_date DESC NULLS LAST, r.rental_id DESC
    LIMIT 12
", $rentalParams);

$customerRows = $customerSourceAvailable ? fetchRows($conn, "
    SELECT c.customer_id,
           c.full_name,
           c.email,
           c.phone,
           c.city,
           c.country,
           c.is_active,
           CASE
               WHEN c.is_active = false THEN 'At Risk'
               WHEN COALESCE(s.lifetime_value, 0) >= 50 THEN 'VIP'
               WHEN COALESCE(s.orders_count, 0) > 0 THEN 'Loyal'
               ELSE 'New'
           END AS segment,
           COALESCE(s.orders_count, 0) AS orders_count,
           COALESCE(s.lifetime_value, 0) AS lifetime_value,
           s.last_order AS last_order,
           c.registered_at
    FROM (" . $customerSourceSql . ") c
    LEFT JOIN (
        SELECT customer_id,
               COUNT(DISTINCT rental_id) AS orders_count,
               COALESCE(SUM(amount), 0) AS lifetime_value,
               MAX(payment_date)::date AS last_order
        FROM public.stg_payment
        GROUP BY customer_id
    ) s ON s.customer_id = c.customer_id
    " . $customerWhere . "
    ORDER BY lifetime_value DESC, orders_count DESC, c.customer_id ASC
    LIMIT 10
", $customerParams) : [];

$stagingTotalCustomers = $customerSourceAvailable ? scalarValue($conn, "SELECT COUNT(*) FROM (" . $customerSourceSql . ") c", [], 0) : 0;
$stagingFilteredCustomers = $customerSourceAvailable ? scalarValue($conn, "SELECT COUNT(*) FROM (" . $customerSourceSql . ") c" . $customerWhere, $customerParams, 0) : 0;
$stagingActiveCustomers = $customerSourceAvailable ? scalarValue($conn, "SELECT COUNT(*) FROM (" . $customerSourceSql . ") c WHERE c.is_active = true", [], 0) : 0;
$stagingInactiveCustomers = max(0, $stagingTotalCustomers - $stagingActiveCustomers);
$stagingCountryCount = $customerSourceAvailable ? scalarValue($conn, "SELECT COUNT(DISTINCT NULLIF(country, '-')) FROM (" . $customerSourceSql . ") c", [], 0) : 0;
$stagingCityCount = $customerSourceAvailable ? scalarValue($conn, "SELECT COUNT(DISTINCT NULLIF(city, '-')) FROM (" . $customerSourceSql . ") c", [], 0) : 0;
$stagingActiveRate = $stagingTotalCustomers > 0 ? ($stagingActiveCustomers / $stagingTotalCustomers) * 100 : 0;

$customerCountryRows = $customerSourceAvailable ? fetchRows($conn, "
    SELECT country,
           COUNT(*) AS total_customers,
           COUNT(*) FILTER (WHERE is_active = true) AS active_customers,
           COUNT(DISTINCT city) AS city_count
    FROM (" . $customerSourceSql . ") c
    GROUP BY country
    ORDER BY total_customers DESC, country ASC
    LIMIT 10
") : [];

$oltpSampleRows = fetchRows($conn, "
    SELECT 'RNT-' || r.rental_id::text AS order_number,
           r.rental_date AS order_date,
           CASE
               WHEN r.return_date IS NULL THEN 'open'
               WHEN r.return_date > r.rental_date + INTERVAL '7 days' THEN 'overdue'
               ELSE 'returned'
           END AS status,
           'Store ' || COALESCE(i.store_id::text, '-') AS channel,
           COALESCE(p.total_amount, 0) AS total_amount,
           c.full_name,
           1 AS item_count
    FROM public.stg_rental r
    LEFT JOIN (" . $customerSourceSql . ") c ON c.customer_id = r.customer_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    LEFT JOIN (
        SELECT rental_id, COALESCE(SUM(amount), 0) AS total_amount
        FROM public.stg_payment
        GROUP BY rental_id
    ) p ON p.rental_id = r.rental_id
    ORDER BY order_date DESC NULLS LAST, r.rental_id DESC
    LIMIT 5
");

$olapSampleRows = tableExists($conn, 'stg_payment') ? fetchRows($conn, "
    SELECT TO_CHAR(p.payment_date, 'YYYY-MM') AS period_label,
           COALESCE(i.store_id, 0) AS store_key,
           COALESCE(SUM(p.amount), 0) AS revenue,
           COUNT(DISTINCT p.payment_id) AS payment_count,
           COUNT(*) AS staging_rows
    FROM public.stg_payment p
    LEFT JOIN public.stg_rental r ON r.rental_id = p.rental_id
    LEFT JOIN public.stg_inventory i ON i.inventory_id = r.inventory_id
    GROUP BY period_label, i.store_id
    ORDER BY period_label DESC, revenue DESC
    LIMIT 5
") : [];

$commerceShare = $pagilaRevenue > 0 ? ($paidRevenue / $pagilaRevenue) * 100 : 0;
$aovTarget = 180000;
$cancelRateTarget = 8;
$lowStockTarget = 20;
$activeCustomerTarget = 95;

$benchmarkRows = [
    [
        'label' => 'Avg Rental Value',
        'actual' => dollar($avgOrder),
        'target' => dollar($aovTarget),
        'status' => $avgOrder >= $aovTarget ? 'Di atas target' : 'Perlu bundle upsell',
        'class' => $avgOrder >= $aovTarget ? 'status-good' : 'status-watch',
        'delta' => $avgOrder >= $aovTarget ? '+' . dollar($avgOrder - $aovTarget) : '-' . dollar($aovTarget - $avgOrder),
    ],
    [
        'label' => 'Cancel Rate',
        'actual' => percent($cancelRate),
        'target' => '< ' . percent($cancelRateTarget),
        'status' => $cancelRate <= $cancelRateTarget ? 'Sehat' : 'Audit rental overdue',
        'class' => $cancelRate <= $cancelRateTarget ? 'status-good' : 'status-risk',
        'delta' => number_format($cancelRate - $cancelRateTarget, 1, ',', '.') . ' poin',
    ],
    [
        'label' => 'Low Stock Ratio',
        'actual' => percent($lowStockRate),
        'target' => '< ' . percent($lowStockTarget),
        'status' => $lowStockRate <= $lowStockTarget ? 'Stok aman' : 'Restock prioritas',
        'class' => $lowStockRate <= $lowStockTarget ? 'status-good' : 'status-risk',
        'delta' => number_format($lowStockRate - $lowStockTarget, 1, ',', '.') . ' poin',
    ],
    [
        'label' => 'Staging Active Rate',
        'actual' => percent($stagingActiveRate),
        'target' => '> ' . percent($activeCustomerTarget),
        'status' => $stagingActiveRate >= $activeCustomerTarget ? 'Customer aktif sehat' : 'Perlu validasi customer',
        'class' => $stagingActiveRate >= $activeCustomerTarget ? 'status-good' : 'status-watch',
        'delta' => number_format($stagingActiveRate - $activeCustomerTarget, 1, ',', '.') . ' poin',
    ],
];

$benchmarkChartLabels = ['AOV', 'Cancel Rate', 'Low Stock', 'Active Customer'];
$benchmarkActual = [
    $aovTarget > 0 ? round(($avgOrder / $aovTarget) * 100, 1) : 0,
    $cancelRateTarget > 0 ? round(($cancelRate / $cancelRateTarget) * 100, 1) : 0,
    $lowStockTarget > 0 ? round(($lowStockRate / $lowStockTarget) * 100, 1) : 0,
    $activeCustomerTarget > 0 ? round(($stagingActiveRate / $activeCustomerTarget) * 100, 1) : 0,
];
$benchmarkTarget = [100, 100, 100, 100];
$timeCompareLabels = ['OLTP transaksi', 'ETL cepat', 'ETL harian', 'OLAP agregasi'];
$timeCompareValues = [1, 300, 86400, 5];

$trendLabels = array_column($salesTrend, 'period_label');
$trendRevenue = array_map('dollar_value', array_column($salesTrend, 'revenue'));
$trendOrders = array_map('intval', array_column($salesTrend, 'orders'));
$categoryLabels = array_column($categoryRows, 'category');
$categoryRevenue = array_map('dollar_value', array_column($categoryRows, 'revenue'));
$productLabels = array_column($productRows, 'product_name');
$productRevenue = array_map('dollar_value', array_column($productRows, 'revenue'));
$filmBiLabels = array_column($filmBiRows, 'title');
$filmBiRevenue = array_map('dollar_value', array_column($filmBiRows, 'rental_revenue'));
$storeBiLabels = array_map(fn($row) => 'Toko #' . $row['store_key'], $storeBiRows);
$storeBiRevenue = array_map('dollar_value', array_column($storeBiRows, 'total_revenue'));

$bestProduct = $productRows[0] ?? null;
$pageTitles = [
    'overview' => 'Rental Fact Command Center',
    'insights' => 'Business Insights',
    'catalog' => 'Katalog Produk Rental Film',
    'orders' => 'Rental Transactions',
    'customers' => 'Customer Activity',
    'marketing' => 'Marketing Performance',
    'films' => 'Film Rental Analytics',
    'stores' => 'Store Operations',
    'rental' => 'Pagila Rental BI',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagila Rental Facts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #172026;
            --muted: #65717c;
            --line: #d9e1e8;
            --surface: #ffffff;
            --soft: #f4f7f9;
            --dark: #101719;
            --brand: #0f766e;
            --blue: #2563eb;
            --amber: #b7791f;
            --red: #be123c;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: var(--soft);
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, sans-serif;
            overflow-x: hidden;
        }
        .app-shell { display: flex; min-height: 100vh; width: 100%; }
        .sidebar {
            width: 272px;
            flex: 0 0 272px;
            background: var(--dark);
            color: #edf5f2;
            display: flex;
            flex-direction: column;
        }
        .brand {
            padding: 22px 20px;
            border-bottom: 1px solid rgba(255,255,255,.09);
        }
        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: #10b981;
            color: #06201a;
            display: grid;
            place-items: center;
            font-size: 1.15rem;
        }
        .nav-link-commerce {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #a8b5b1;
            text-decoration: none;
            padding: 13px 20px;
            font-size: .92rem;
            font-weight: 700;
            border-left: 4px solid transparent;
        }
        .nav-link-commerce:hover,
        .nav-link-commerce.active {
            color: #fff;
            background: rgba(255,255,255,.065);
            border-left-color: #10b981;
        }
        .main {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        .title-block h1 {
            font-size: 1.48rem;
            line-height: 1.2;
            font-weight: 900;
            margin: 0;
            letter-spacing: 0;
        }
        .title-block p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: .9rem;
        }
        .filter-bar,
        .panel,
        .metric-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(23,32,38,.045);
        }
        .filter-bar { padding: 14px; margin-bottom: 16px; }
        .commerce-hero {
            background: linear-gradient(135deg, #101719 0%, #0f766e 54%, #b7791f 100%);
            color: #fff;
            border-radius: 8px;
            padding: 22px;
            margin-bottom: 16px;
        }
        .commerce-hero .eyebrow,
        .metric-card .label {
            font-size: .72rem;
            text-transform: uppercase;
            font-weight: 900;
            letter-spacing: 0;
        }
        .commerce-hero .eyebrow { color: rgba(255,255,255,.78); }
        .commerce-hero h2 {
            font-size: 1.72rem;
            line-height: 1.2;
            font-weight: 900;
            margin: 6px 0 8px;
        }
        .metric-card { padding: 16px; min-height: 126px; }
        .metric-card .label { color: var(--muted); }
        .metric-card .value {
            font-size: 1.48rem;
            font-weight: 900;
            margin-top: 6px;
            line-height: 1.12;
        }
        .metric-card .note { color: var(--muted); font-size: .82rem; margin-top: 8px; line-height: 1.35; }
        .panel { padding: 16px; margin-bottom: 16px; }
        .panel-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .panel-title h2 { font-size: .98rem; margin: 0; font-weight: 900; }
        .chart-box { position: relative; height: 280px; }
        .chart-box-sm { position: relative; height: 230px; }
        .commerce-table { font-size: .88rem; margin: 0; }
        .commerce-table th {
            color: var(--muted);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: 0;
            white-space: nowrap;
        }
        .commerce-table td { vertical-align: middle; }
        .status-pill {
            display: inline-flex;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .status-good { background: #dcfce7; color: #166534; }
        .status-watch { background: #fef3c7; color: #92400e; }
        .status-risk { background: #ffe4e6; color: #be123c; }
        .empty-state {
            border: 1px dashed #b8c4cf;
            border-radius: 8px;
            color: var(--muted);
            padding: 18px;
            background: #f8fafc;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .info-box {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
            background: #fff;
            min-height: 100%;
        }
        .info-box h3 {
            font-size: .95rem;
            font-weight: 900;
            margin: 0 0 8px;
        }
        .info-box p {
            color: var(--muted);
            font-size: .88rem;
            line-height: 1.45;
            margin: 0 0 10px;
        }
        .info-list {
            margin: 0;
            padding-left: 18px;
            color: var(--ink);
            font-size: .86rem;
            line-height: 1.55;
        }
        .architecture-flow {
            display: grid;
            grid-template-columns: 1fr 64px 1fr 64px 1fr;
            gap: 12px;
            align-items: stretch;
            margin: 14px 0;
        }
        .flow-node {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
            min-height: 132px;
        }
        .flow-node .node-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            margin-bottom: 10px;
            font-size: .95rem;
        }
        .node-oltp .node-icon { background: #dcfce7; color: #166534; }
        .node-etl .node-icon { background: #e0f2fe; color: #075985; }
        .node-olap .node-icon { background: #fef3c7; color: #92400e; }
        .flow-node h3 {
            font-size: .92rem;
            font-weight: 900;
            margin: 0 0 6px;
        }
        .flow-node p {
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.42;
            margin: 0;
        }
        .flow-arrow {
            display: grid;
            place-items: center;
            color: var(--muted);
            font-size: 1.25rem;
        }
        .compare-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-size: .86rem;
        }
        .compare-table th,
        .compare-table td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }
        .compare-table tr:last-child td { border-bottom: 0; }
        .compare-table th {
            background: #f8fafc;
            color: var(--muted);
            font-size: .72rem;
            text-transform: uppercase;
            font-weight: 900;
            white-space: nowrap;
        }
        .compare-table td:first-child {
            width: 170px;
            font-weight: 900;
            color: var(--ink);
            background: #fbfcfd;
        }
        .decision-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .decision-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 13px;
        }
        .decision-item .label {
            color: var(--muted);
            font-size: .7rem;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 5px;
        }
        .decision-item .value {
            font-size: .9rem;
            font-weight: 900;
            line-height: 1.25;
        }
        .time-compare {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            margin-top: 14px;
        }
        .time-compare h3 {
            font-size: .95rem;
            font-weight: 900;
            margin: 0 0 12px;
        }
        .time-flow {
            display: grid;
            grid-template-columns: 1fr 46px 1fr 46px 1fr;
            gap: 10px;
            align-items: stretch;
            margin-bottom: 14px;
        }
        .time-node {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 13px;
        }
        .time-node .time-label {
            color: var(--muted);
            font-size: .68rem;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 5px;
        }
        .time-node .time-value {
            font-size: .95rem;
            font-weight: 900;
            line-height: 1.25;
        }
        .time-node p {
            color: var(--muted);
            font-size: .8rem;
            line-height: 1.38;
            margin: 7px 0 0;
        }
        .time-arrow {
            display: grid;
            place-items: center;
            color: var(--muted);
        }
        .time-matrix {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .time-metric {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
            background: #fff;
        }
        .time-metric .label {
            color: var(--muted);
            font-size: .68rem;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 5px;
        }
        .time-metric .value {
            font-size: .88rem;
            font-weight: 900;
            line-height: 1.3;
        }
        .benchmark-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }
        .benchmark-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 14px;
        }
        .benchmark-card .label {
            color: var(--muted);
            font-size: .7rem;
            text-transform: uppercase;
            font-weight: 900;
            margin-bottom: 6px;
        }
        .benchmark-card .actual {
            font-size: 1.25rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .benchmark-card .target {
            color: var(--muted);
            font-size: .82rem;
            margin: 8px 0 10px;
        }
        .insight-board {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 14px;
        }
        .insight-list {
            display: grid;
            gap: 10px;
        }
        .insight-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 13px;
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 10px;
        }
        .insight-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: #e0f2fe;
            color: #075985;
        }
        .insight-item h3 {
            font-size: .9rem;
            font-weight: 900;
            margin: 0 0 4px;
        }
        .insight-item p {
            color: var(--muted);
            font-size: .84rem;
            line-height: 1.42;
            margin: 0;
        }
        .benchmark-summary {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fafc;
            padding: 16px;
        }
        .benchmark-summary .big-number {
            font-size: 1.65rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .benchmark-summary p {
            color: var(--muted);
            font-size: .86rem;
            line-height: 1.45;
            margin: 8px 0 0;
        }
        @media (max-width: 960px) {
            .app-shell { display: block; }
            .sidebar { width: 100%; min-height: auto; display: block; }
            .nav-list { display: flex; overflow-x: auto; padding-bottom: 8px; }
            .nav-link-commerce { white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
            .nav-link-commerce.active { border-bottom-color: #10b981; }
            .main { height: auto; padding: 16px; }
            .topbar { display: block; }
            .info-grid { grid-template-columns: 1fr; }
            .architecture-flow { grid-template-columns: 1fr; }
            .flow-arrow { min-height: 24px; transform: rotate(90deg); }
            .decision-strip { grid-template-columns: 1fr; }
            .time-flow { grid-template-columns: 1fr; }
            .time-arrow { min-height: 22px; transform: rotate(90deg); }
            .time-matrix { grid-template-columns: 1fr; }
            .benchmark-grid { grid-template-columns: 1fr; }
            .insight-board { grid-template-columns: 1fr; }
            .compare-table { min-width: 720px; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand d-flex align-items-center gap-3">
            <div class="brand-mark"><i class="fa-solid fa-bag-shopping"></i></div>
            <div>
                <div class="fw-bold">Pagila Rental Facts</div>
                <div style="font-size:.78rem;color:#a8b5b1;">Film rental analytics</div>
            </div>
        </div>
        <nav class="nav-list pt-3">
            <a class="nav-link-commerce <?= $page === 'overview' ? 'active' : ''; ?>" href="index.php?page=overview"><i class="fa-solid fa-gauge-high"></i> Overview</a>
            <a class="nav-link-commerce <?= $page === 'insights' ? 'active' : ''; ?>" href="index.php?page=insights"><i class="fa-solid fa-chart-simple"></i> Insights</a>
            <a class="nav-link-commerce <?= $page === 'catalog' ? 'active' : ''; ?>" href="index.php?page=catalog"><i class="fa-solid fa-clapperboard"></i> Catalog</a>
            <a class="nav-link-commerce <?= $page === 'orders' ? 'active' : ''; ?>" href="index.php?page=orders"><i class="fa-solid fa-receipt"></i> Orders</a>
            <a class="nav-link-commerce <?= $page === 'customers' ? 'active' : ''; ?>" href="index.php?page=customers"><i class="fa-solid fa-users"></i> Customers</a>
            <a class="nav-link-commerce <?= $page === 'marketing' ? 'active' : ''; ?>" href="index.php?page=marketing"><i class="fa-solid fa-bullhorn"></i> Marketing</a>
            <a class="nav-link-commerce <?= $page === 'films' ? 'active' : ''; ?>" href="index.php?page=films"><i class="fa-solid fa-film"></i> Films BI</a>
            <a class="nav-link-commerce <?= $page === 'stores' ? 'active' : ''; ?>" href="index.php?page=stores"><i class="fa-solid fa-store"></i> Stores</a>
            <a class="nav-link-commerce <?= $page === 'rental' ? 'active' : ''; ?>" href="index.php?page=rental"><i class="fa-solid fa-database"></i> Rental BI</a>
        </nav>
        <div class="mt-auto p-3">
            <div class="p-3 rounded" style="background:rgba(16,185,129,.1);color:#a7f3d0;font-size:.84rem;">
                <i class="fa-solid fa-server me-1"></i> Database: Pagila staging tables
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <h1><?= h($pageTitles[$page]); ?></h1>
                <p>Dashboard profesional berbasis tabel staging rental film Pagila dari PgAdmin.</p>
            </div>
            <div class="badge text-bg-light border px-3 py-2">
                <i class="fa-solid fa-circle <?= $stagingReady ? 'text-success' : 'text-warning'; ?> me-1"></i>
                <?= $stagingReady ? 'Staging data aktif' : 'Staging table belum lengkap'; ?>
            </div>
        </div>

        <?php if (!$stagingReady): ?>
            <div class="empty-state mb-3">
                <strong>Data staging belum lengkap.</strong>
                Pastikan tabel <code>staging_customer</code>, <code>staging_film</code>, <code>staging_store</code>, <code>stg_rental</code>, <code>stg_payment</code>, dan <code>stg_inventory</code> tersedia di PgAdmin.
            </div>
        <?php endif; ?>

        <form class="filter-bar" method="get">
            <input type="hidden" name="page" value="<?= h($page); ?>">
            <div class="row g-2 align-items-end">
                <div class="col-xl-2 col-md-6">
                    <label class="form-label small fw-bold text-muted" for="date_from">Tanggal mulai</label>
                    <input class="form-control form-control-sm" id="date_from" name="date_from" type="date" value="<?= h($dateFrom); ?>">
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label small fw-bold text-muted" for="date_to">Tanggal akhir</label>
                    <input class="form-control form-control-sm" id="date_to" name="date_to" type="date" value="<?= h($dateTo); ?>">
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label small fw-bold text-muted" for="status">Status order</label>
                    <select class="form-select form-select-sm" id="status" name="status">
                        <option value="">Semua status</option>
                        <?php foreach ($statusOptions as $option): ?>
                            <option value="<?= h($option['status']); ?>" <?= $option['status'] === $statusFilter ? 'selected' : ''; ?>><?= h(ucfirst($option['status'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-3 col-md-6">
                    <label class="form-label small fw-bold text-muted" for="category">Kategori</label>
                    <select class="form-select form-select-sm" id="category" name="category">
                        <option value="">Semua kategori</option>
                        <?php foreach ($categoryOptions as $option): ?>
                            <option value="<?= h($option['category']); ?>" <?= $option['category'] === $categoryFilter ? 'selected' : ''; ?>><?= h($option['category']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-3 col-md-6 d-flex gap-2">
                    <button class="btn btn-sm btn-dark flex-fill" type="submit"><i class="fa-solid fa-filter me-1"></i> Terapkan</button>
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=<?= h($page); ?>" title="Reset filter"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
                <?php if (in_array($page, ['customers', 'marketing'], true)): ?>
                    <div class="col-xl-6 col-md-6">
                        <label class="form-label small fw-bold text-muted" for="customer_search">Cari customer staging</label>
                        <input class="form-control form-control-sm" id="customer_search" name="customer_search" type="search" value="<?= h($customerSearch); ?>" placeholder="Nama, email, phone, kota, negara">
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label small fw-bold text-muted" for="country">Negara</label>
                        <select class="form-select form-select-sm" id="country" name="country">
                            <option value="">Semua negara</option>
                            <?php foreach ($countryOptions as $option): ?>
                                <option value="<?= h($option['country']); ?>" <?= $option['country'] === $countryFilter ? 'selected' : ''; ?>><?= h($option['country']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($page === 'overview'): ?>
            <section class="commerce-hero">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-7">
                        <div class="eyebrow">Rental Fact Snapshot</div>
                        <h2>Pagila film rental fact command center</h2>
                        <div style="color:rgba(255,255,255,.84);max-width:680px;">Pantau transaksi rental, performa film, store, customer activity, dan sales fact Pagila dalam satu layar kerja.</div>
                    </div>
                    <div class="col-lg-5">
                        <div class="row g-2">
                            <div class="col-4"><div class="eyebrow">Top item</div><div class="fw-bold"><?= h($bestProduct['product_name'] ?? 'Belum ada'); ?></div></div>
                            <div class="col-4"><div class="eyebrow">Fact sales</div><div class="fw-bold"><?= dollar($paidRevenue); ?></div></div>
                            <div class="col-4"><div class="eyebrow">Low stock</div><div class="fw-bold"><?= number_format($lowStock); ?></div></div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-3 mb-3">
                <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="label">Rental Sales Value</div><div class="value text-primary"><?= dollar($gmv); ?></div><div class="note">Total nilai dari <code>stg_payment</code>.</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="label">Total Rentals</div><div class="value text-success"><?= number_format($totalOrders); ?></div><div class="note">Jumlah transaksi dari <code>stg_rental</code>.</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="label">Active Buyers</div><div class="value" style="color:var(--amber);"><?= number_format($activeCustomers); ?></div><div class="note">Customer unik pada filter aktif.</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="label">Avg Rental Value</div><div class="value" style="color:var(--red);"><?= dollar($avgOrder); ?></div><div class="note">Overdue rate <?= percent($cancelRate); ?>.</div></div></div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="panel">
                        <div class="panel-title"><h2><i class="fa-solid fa-chart-line me-1"></i> Tren Sales & Rental</h2><span class="badge text-bg-light border">Bulanan</span></div>
                        <div class="chart-box"><canvas id="trendChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="panel">
                        <div class="panel-title"><h2><i class="fa-solid fa-layer-group me-1"></i> Revenue per Kategori</h2><span class="badge text-bg-light border">Catalog</span></div>
                        <div class="chart-box"><canvas id="categoryChart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="panel">
                        <div class="panel-title"><h2><i class="fa-solid fa-ranking-star me-1"></i> Produk Rental Terlaris</h2><a class="btn btn-sm btn-outline-dark" href="index.php?page=catalog">Detail</a></div>
                        <?php include __DIR__ . '/includes/commerce_product_table.php'; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel">
                        <div class="panel-title"><h2><i class="fa-solid fa-clock-rotate-left me-1"></i> Rental Terbaru</h2><a class="btn btn-sm btn-outline-dark" href="index.php?page=orders">Kelola</a></div>
                        <?php include __DIR__ . '/includes/commerce_order_table.php'; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'insights'): ?>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-chart-simple me-1"></i> Benchmark & Insight Bisnis</h2><span class="badge text-bg-light border">Bagian benchmark dan insight bisnis</span></div>
                <div class="row g-3 mb-3">
                    <div class="col-lg-7">
                        <div class="panel m-0">
                            <div class="panel-title"><h2><i class="fa-solid fa-scale-balanced me-1"></i> Grafik Benchmark Aktual vs Target</h2><span class="badge text-bg-light border">Target = 100%</span></div>
                            <div class="chart-box-sm"><canvas id="benchmarkChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="panel m-0">
                            <div class="panel-title"><h2><i class="fa-solid fa-stopwatch me-1"></i> Grafik Perbandingan Waktu</h2><span class="badge text-bg-light border">Skala log</span></div>
                            <div class="chart-box-sm"><canvas id="timeCompareChart"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="benchmark-grid">
                    <?php foreach ($benchmarkRows as $benchmark): ?>
                        <div class="benchmark-card">
                            <div class="label"><?= h($benchmark['label']); ?></div>
                            <div class="actual"><?= h($benchmark['actual']); ?></div>
                            <div class="target">Benchmark: <?= h($benchmark['target']); ?> | Selisih: <?= h($benchmark['delta']); ?></div>
                            <span class="status-pill <?= h($benchmark['class']); ?>"><?= h($benchmark['status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="insight-board">
                    <div class="insight-list">
                        <div class="insight-item">
                            <div class="insight-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                            <div>
                                <h3>Prioritaskan restock produk rental populer</h3>
                                <p>Low stock ratio berada di <?= percent($lowStockRate); ?> dari <?= number_format($activeProducts); ?> produk aktif. Produk dengan stok di bawah reorder level perlu masuk daftar pembelian ulang.</p>
                            </div>
                        </div>
                        <div class="insight-item">
                            <div class="insight-icon"><i class="fa-solid fa-basket-shopping"></i></div>
                            <div>
                                <h3>Dorong AOV lewat bundle dan membership</h3>
                                <p>AOV saat ini <?= dollar($avgOrder); ?> dibanding benchmark <?= dollar($aovTarget); ?>. Bundle weekend dan membership Gold bisa dipakai sebagai upsell utama.</p>
                            </div>
                        </div>
                        <div class="insight-item">
                            <div class="insight-icon"><i class="fa-solid fa-bullseye"></i></div>
                            <div>
                                <h3>Gunakan staging customer sebagai basis audience</h3>
                                <p>Data staging memuat <?= number_format($stagingTotalCustomers); ?> customer dari <?= number_format($stagingCountryCount); ?> negara dan <?= number_format($stagingCityCount); ?> kota. Segmentasi marketing sebaiknya mengikuti persebaran customer aktual di PgAdmin.</p>
                            </div>
                        </div>
                    </div>
                    <div class="benchmark-summary">
                        <div class="text-muted small fw-bold text-uppercase">Executive Insight</div>
                        <div class="big-number mt-2"><?= percent($commerceShare); ?></div>
                        <p>Kontribusi paid commerce terhadap revenue rental Pagila historis. Angka ini membantu melihat apakah channel digital mulai menjadi mesin pendapatan tambahan atau masih perlu akselerasi.</p>
                        <hr>
                        <div class="text-muted small fw-bold text-uppercase">Rekomendasi cepat</div>
                        <p>Fokus minggu ini: pantau film low-stock, prioritaskan film top revenue, dan audit rental overdue dari data fact.</p>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-code-compare me-1"></i> Perbandingan OLTP dan OLAP</h2><span class="badge text-bg-light border">Arsitektur data</span></div>
                <div class="architecture-flow">
                    <div class="flow-node node-oltp">
                        <div class="node-icon"><i class="fa-solid fa-cash-register"></i></div>
                        <h3>OLTP Transaction Layer</h3>
                        <p>Order commerce, pelanggan, item order, pembayaran, dan stok dicatat sebagai transaksi detail yang cepat berubah.</p>
                    </div>
                    <div class="flow-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
                    <div class="flow-node node-etl">
                        <div class="node-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
                        <h3>ETL / Data Processing</h3>
                        <p>Data transaksi dibersihkan, digabung, dihitung, lalu dipindahkan ke model analitik yang lebih siap dibaca dashboard.</p>
                    </div>
                    <div class="flow-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
                    <div class="flow-node node-olap">
                        <div class="node-icon"><i class="fa-solid fa-chart-column"></i></div>
                        <h3>OLAP Analytics Layer</h3>
                        <p>Fact dan dimension dipakai untuk KPI, tren revenue, performa film, segmentasi customer, dan laporan manajemen.</p>
                    </div>
                </div>

                <div class="time-compare">
                    <h3><i class="fa-solid fa-clock me-1"></i> Compare Waktu OLTP vs OLAP</h3>
                    <div class="chart-box-sm mb-3"><canvas id="timeDetailChart"></canvas></div>
                    <div class="time-flow">
                        <div class="time-node">
                            <div class="time-label">T+0 detik</div>
                            <div class="time-value">Transaksi masuk ke OLTP</div>
                            <p>Order baru, pembayaran, dan perubahan stok harus langsung tersimpan agar operasional berjalan real-time.</p>
                        </div>
                        <div class="time-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
                        <div class="time-node">
                            <div class="time-label">T+5 menit sampai harian</div>
                            <div class="time-value">ETL / sinkronisasi data</div>
                            <p>Data operasional dibersihkan, digabung, dan dihitung menjadi data analitik yang stabil untuk laporan.</p>
                        </div>
                        <div class="time-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
                        <div class="time-node">
                            <div class="time-label">Near real-time / batch</div>
                            <div class="time-value">Analisis tersedia di OLAP</div>
                            <p>Dashboard membaca data agregat untuk tren, benchmark, insight bisnis, dan keputusan manajemen.</p>
                        </div>
                    </div>
                    <div class="time-matrix">
                        <div class="time-metric">
                            <div class="label">Kecepatan OLTP</div>
                            <div class="value">Milidetik sampai detik per transaksi</div>
                        </div>
                        <div class="time-metric">
                            <div class="label">Kecepatan OLAP</div>
                            <div class="value">Detik untuk query agregasi besar</div>
                        </div>
                        <div class="time-metric">
                            <div class="label">Data freshness</div>
                            <div class="value">OLTP paling baru, OLAP tergantung jadwal ETL</div>
                        </div>
                        <div class="time-metric">
                            <div class="label">Contoh bisnis</div>
                            <div class="value">Checkout sekarang vs laporan revenue bulanan</div>
                        </div>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-box">
                        <h3>OLTP: Sistem Transaksi Harian</h3>
                        <p>OLTP dipakai untuk mencatat aktivitas operasional yang sering berubah, seperti rental, customer, pembayaran, dan status pengembalian film.</p>
                        <ul class="info-list">
                            <li>Contoh tabel operasional yang dibaca dashboard: <code>stg_rental</code>, <code>stg_payment</code>, dan <code>staging_customer</code>.</li>
                            <li>Fokus: insert, update, validasi transaksi, dan data detail per kejadian.</li>
                            <li>Cocok untuk halaman transaksi rental, customer, dan pengelolaan katalog film.</li>
                        </ul>
                    </div>
                    <div class="info-box">
                        <h3>OLAP: Analisis dan Dashboard</h3>
                        <p>OLAP dipakai untuk membaca data yang sudah diringkas atau dimodelkan agar cepat dianalisis sebagai laporan bisnis rental film Pagila.</p>
                        <ul class="info-list">
                            <li>Contoh tabel: <code>stg_payment</code>, <code>staging_film</code>, <code>staging_store</code>.</li>
                            <li>Fokus: agregasi revenue, tren rental, performa film, customer, dan cabang.</li>
                            <li>Cocok untuk chart, KPI, ranking produk, dan pengambilan keputusan manajemen.</li>
                        </ul>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="info-box">
                            <h3>Sample OLTP dari PgAdmin</h3>
                            <p>Contoh baris transaksi rental dari <code>stg_rental</code> yang diperkaya dengan customer staging dan nilai payment.</p>
                            <div class="table-responsive">
                                <table class="table commerce-table table-hover">
                                    <thead><tr><th>Order</th><th>Customer</th><th>Tanggal</th><th>Channel</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($oltpSampleRows)): ?>
                                            <tr><td colspan="7" class="text-muted text-center">Belum ada data OLTP di database.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($oltpSampleRows as $sample): ?>
                                                <tr>
                                                    <td><strong><?= h($sample['order_number']); ?></strong></td>
                                                    <td><?= h($sample['full_name']); ?></td>
                                                    <td><?= h($sample['order_date']); ?></td>
                                                    <td><?= h($sample['channel']); ?></td>
                                                    <td><?= number_format($sample['item_count']); ?></td>
                                                    <td class="fw-bold"><?= dollar($sample['total_amount']); ?></td>
                                                    <td><?= h(ucfirst($sample['status'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="info-box">
                            <h3>Sample OLAP dari PgAdmin</h3>
                            <p>Contoh agregasi analitik dari <code>stg_payment</code> berdasarkan periode dan store.</p>
                            <div class="table-responsive">
                                <table class="table commerce-table table-hover">
                                    <thead><tr><th>Periode</th><th>Store</th><th>Revenue</th><th>Payments</th><th>Fact Rows</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($olapSampleRows)): ?>
                                            <tr><td colspan="5" class="text-muted text-center">Belum ada data OLAP di database.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($olapSampleRows as $sample): ?>
                                                <tr>
                                                    <td><strong><?= h($sample['period_label']); ?></strong></td>
                                                    <td><?= h($sample['store_key'] ?? '-'); ?></td>
                                                    <td class="fw-bold"><?= dollar($sample['revenue']); ?></td>
                                                    <td><?= number_format($sample['payment_count']); ?></td>
                                                    <td><?= number_format($sample['staging_rows']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="compare-table">
                        <thead>
                            <tr>
                                <th>Aspek</th>
                                <th>OLTP</th>
                                <th>OLAP</th>
                                <th>Contoh di Web Ini</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Tujuan</td>
                                <td>Menjalankan proses bisnis harian secara akurat dan real-time.</td>
                                <td>Menganalisis performa bisnis dari data historis dan agregat.</td>
                                <td>Order management memakai OLTP, chart revenue memakai OLAP.</td>
                            </tr>
                            <tr>
                                <td>Bentuk Data</td>
                                <td>Detail transaksi per order, item, customer, pembayaran, dan stok.</td>
                                <td>Data fact/dimension yang sudah diringkas untuk analisis cepat.</td>
                                <td><code>stg_rental</code> dibandingkan dengan agregasi <code>stg_payment</code>.</td>
                            </tr>
                            <tr>
                                <td>Operasi Utama</td>
                                <td>Banyak insert dan update kecil dengan validasi ketat.</td>
                                <td>Banyak query baca, agregasi, filter periode, dan grouping.</td>
                                <td>Status rental vs KPI revenue, tren sales, dan ranking film.</td>
                            </tr>
                            <tr>
                                <td>Pengguna</td>
                                <td>Admin toko, kasir, customer service, dan operator rental.</td>
                                <td>Owner, manager, analis bisnis, dan tim marketing.</td>
                                <td>Halaman Orders untuk operasional, Overview untuk manajemen.</td>
                            </tr>
                            <tr>
                                <td>Keputusan Bisnis</td>
                                <td>Apakah order dibayar, dikirim, dibatalkan, atau stok perlu dikurangi.</td>
                                <td>Film mana yang paling laku, wilayah customer mana yang dominan, dan customer mana yang bernilai tinggi.</td>
                                <td>Low stock alert, top rental product, audience staging, CLV customer.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="decision-strip">
                    <div class="decision-item">
                        <div class="label">Gunakan OLTP untuk</div>
                        <div class="value">Transaksi cepat dan data operasional detail</div>
                    </div>
                    <div class="decision-item">
                        <div class="label">Gunakan OLAP untuk</div>
                        <div class="value">Dashboard, laporan, dan analisis historis</div>
                    </div>
                    <div class="decision-item">
                        <div class="label">Nilai bisnis</div>
                        <div class="value">Operasi rapi, keputusan manajemen lebih cepat</div>
                    </div>
                    <div class="decision-item">
                        <div class="label">Model ideal</div>
                        <div class="value">OLTP mencatat, ETL mengolah, OLAP menganalisis</div>
                    </div>
                </div>
            </div>
        <?php elseif ($page === 'catalog'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="metric-card"><div class="label">Film tracked</div><div class="value text-primary"><?= number_format(count($productRows)); ?></div><div class="note">Film dari <code>staging_film</code>.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Low stock</div><div class="value" style="color:var(--red);"><?= number_format($lowStock); ?></div><div class="note">Perlu replenishment inventory.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Top product</div><div class="value text-success" style="font-size:1rem;"><?= h($bestProduct['product_name'] ?? '-'); ?></div><div class="note"><?= $bestProduct ? dollar($bestProduct['revenue']) : 'Belum ada data'; ?></div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Pagila rentals</div><div class="value" style="color:var(--amber);"><?= number_format($pagilaRentals); ?></div><div class="note">Basis demand rental film.</div></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-clapperboard me-1"></i> Film Performance Catalog</h2><span class="badge text-bg-light border">staging_film + stg_rental</span></div>
                <div class="chart-box-sm mb-3"><canvas id="productChart"></canvas></div>
                <?php include __DIR__ . '/includes/commerce_product_table.php'; ?>
            </div>
        <?php elseif ($page === 'orders'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="metric-card"><div class="label">Staging payments</div><div class="value text-primary"><?= dollar($gmv); ?></div><div class="note">Nilai dari <code>stg_payment</code>.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Rental revenue</div><div class="value text-success"><?= dollar($paidRevenue); ?></div><div class="note">Revenue rental tercatat.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Avg rental value</div><div class="value" style="color:var(--amber);"><?= dollar($avgOrder); ?></div><div class="note">Rata-rata nilai per rental.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Overdue rate</div><div class="value" style="color:var(--red);"><?= percent($cancelRate); ?></div><div class="note"><?= number_format($cancelledOrders); ?> rental overdue.</div></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-receipt me-1"></i> Recent Rentals</h2><span class="badge text-bg-light border">stg_rental</span></div>
                <?php include __DIR__ . '/includes/commerce_order_table.php'; ?>
            </div>
        <?php elseif ($page === 'customers'): ?>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-users me-1"></i> Customer Lifetime Value</h2><span class="badge text-bg-light border"><?= h($customerSourceLabel); ?></span></div>
                <div class="empty-state mb-3">
                    Menampilkan <?= number_format(count($customerRows)); ?> sample teratas dari <?= number_format($stagingFilteredCustomers); ?> customer staging yang cocok dengan filter. Total staging di PgAdmin: <?= number_format($stagingTotalCustomers); ?> customer.
                </div>
                <div class="table-responsive">
                    <table class="table commerce-table table-hover">
                        <thead><tr><th>Customer</th><th>Email</th><th>Kota</th><th>Negara</th><th>Status</th><th>Segment</th><th>Order</th><th>Lifetime Value</th><th>Last Order</th></tr></thead>
                        <tbody>
                            <?php foreach ($customerRows as $customer): ?>
                                <tr>
                                    <td><strong><?= h($customer['full_name']); ?></strong><br><span class="text-muted"><?= h($customer['phone']); ?></span></td>
                                    <td><?= h($customer['email']); ?></td>
                                    <td><?= h($customer['city']); ?></td>
                                    <td><?= h($customer['country']); ?></td>
                                    <td><span class="status-pill <?= ($customer['is_active'] === true || $customer['is_active'] === '1' || $customer['is_active'] === 't') ? 'status-good' : 'status-risk'; ?>"><?= ($customer['is_active'] === true || $customer['is_active'] === '1' || $customer['is_active'] === 't') ? 'Active' : 'Inactive'; ?></span></td>
                                    <td><span class="status-pill <?= $customer['segment'] === 'At Risk' ? 'status-risk' : ($customer['segment'] === 'New' ? 'status-watch' : 'status-good'); ?>"><?= h($customer['segment']); ?></span></td>
                                    <td><?= number_format($customer['orders_count']); ?></td>
                                    <td class="fw-bold"><?= dollar($customer['lifetime_value']); ?></td>
                                    <td><?= h($customer['last_order'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($page === 'marketing'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="metric-card"><div class="label">Staging Customers</div><div class="value text-primary"><?= number_format($stagingTotalCustomers); ?></div><div class="note">Semua customer dari <code><?= h($customerSourceLabel); ?></code>.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Active Customers</div><div class="value text-success"><?= number_format($stagingActiveCustomers); ?></div><div class="note">Active rate <?= percent($stagingActiveRate); ?>.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Countries</div><div class="value" style="color:var(--amber);"><?= number_format($stagingCountryCount); ?></div><div class="note">Cakupan negara di staging.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Cities</div><div class="value" style="color:var(--red);"><?= number_format($stagingCityCount); ?></div><div class="note">Cakupan kota di staging.</div></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-earth-asia me-1"></i> Customer Audience by Country</h2><span class="badge text-bg-light border"><?= h($customerSourceLabel); ?></span></div>
                <div class="table-responsive">
                    <table class="table commerce-table table-hover">
                        <thead><tr><th>Negara</th><th>Total Customer</th><th>Active</th><th>Kota</th><th>Active Rate</th></tr></thead>
                        <tbody>
                            <?php if (empty($customerCountryRows)): ?>
                                <tr><td colspan="5" class="text-muted text-center">Tidak ada data customer staging.</td></tr>
                            <?php else: ?>
                                <?php foreach ($customerCountryRows as $row): ?>
                                    <?php $countryActiveRate = (int)$row['total_customers'] > 0 ? ((int)$row['active_customers'] / (int)$row['total_customers']) * 100 : 0; ?>
                                    <tr>
                                        <td><strong><?= h($row['country']); ?></strong></td>
                                        <td><?= number_format($row['total_customers']); ?></td>
                                        <td><?= number_format($row['active_customers']); ?></td>
                                        <td><?= number_format($row['city_count']); ?></td>
                                        <td><span class="status-pill <?= $countryActiveRate >= 95 ? 'status-good' : 'status-watch'; ?>"><?= percent($countryActiveRate); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($page === 'films'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="metric-card"><div class="label">Film master</div><div class="value text-primary"><?= number_format($pagilaFilmCount); ?></div><div class="note">Jumlah film di <code>staging_film</code>.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Rental revenue</div><div class="value text-success"><?= dollar($pagilaRevenue); ?></div><div class="note">Total revenue dari <code>stg_payment</code>.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Rental volume</div><div class="value" style="color:var(--amber);"><?= number_format($pagilaRentals); ?></div><div class="note">Total transaksi rental historis.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Top film</div><div class="value text-success" style="font-size:1rem;"><?= h($filmBiRows[0]['title'] ?? '-'); ?></div><div class="note"><?= isset($filmBiRows[0]) ? dollar($filmBiRows[0]['rental_revenue']) : 'Belum ada data'; ?></div></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-film me-1"></i> Film Rental Performance</h2><span class="badge text-bg-light border">Pagila OLAP</span></div>
                <div class="chart-box-sm mb-3"><canvas id="filmBiChart"></canvas></div>
                <div class="table-responsive">
                    <table class="table commerce-table table-hover">
                        <thead><tr><th>Film</th><th>Inventory</th><th>Disewa</th><th>Utilisasi</th><th>Revenue</th><th>ROI</th></tr></thead>
                        <tbody>
                            <?php foreach ($filmBiRows as $film): ?>
                                <tr>
                                    <td><strong><?= h($film['title']); ?></strong><br><span class="text-muted">Film #<?= h($film['film_key']); ?></span></td>
                                    <td><?= number_format($film['inventory_count']); ?></td>
                                    <td><?= number_format($film['rented_copies']); ?></td>
                                    <td><?= percent((float)$film['utilization_rate'] * 100); ?></td>
                                    <td class="fw-bold"><?= dollar($film['rental_revenue']); ?></td>
                                    <td><span class="status-pill <?= (float)$film['roi_percent'] >= 100 ? 'status-good' : 'status-watch'; ?>"><?= percent($film['roi_percent']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($page === 'stores'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="metric-card"><div class="label">Store revenue</div><div class="value text-primary"><?= dollar($storeTotalRevenue); ?></div><div class="note">Akumulasi semua cabang.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Net profit</div><div class="value text-success"><?= dollar($storeTotalProfit); ?></div><div class="note">Profit operasional cabang.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Transactions</div><div class="value" style="color:var(--amber);"><?= number_format($storeTotalTransactions); ?></div><div class="note">Total transaksi store.</div></div></div>
                <div class="col-md-3"><div class="metric-card"><div class="label">Avg margin</div><div class="value" style="color:var(--red);"><?= percent($storeAvgMargin); ?></div><div class="note">Rata-rata margin cabang.</div></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-store me-1"></i> Store Operations Scorecard</h2><span class="badge text-bg-light border">staging_store + stg_payment</span></div>
                <div class="chart-box-sm mb-3"><canvas id="storeBiChart"></canvas></div>
                <div class="table-responsive">
                    <table class="table commerce-table table-hover">
                        <thead><tr><th>Toko</th><th>Revenue</th><th>Transaksi</th><th>Pelanggan</th><th>Profit</th><th>Margin</th><th>Kepuasan</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($storeBiRows as $store): ?>
                                <?php $healthy = (float)$store['profit_margin_percent'] >= 20 && (float)$store['low_stock_alerts'] <= 3; ?>
                                <tr>
                                    <td><strong>Toko #<?= h($store['store_key']); ?></strong></td>
                                    <td class="fw-bold"><?= dollar($store['total_revenue']); ?></td>
                                    <td><?= number_format($store['total_transactions']); ?></td>
                                    <td><?= number_format($store['unique_customers']); ?></td>
                                    <td><?= dollar($store['net_profit']); ?></td>
                                    <td><?= percent($store['profit_margin_percent']); ?></td>
                                    <td><?= number_format((float)$store['customer_satisfaction_score'], 1, ',', '.'); ?>/5</td>
                                    <td><span class="status-pill <?= $healthy ? 'status-good' : 'status-watch'; ?>"><?= $healthy ? 'Sehat' : 'Pantau'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($page === 'rental'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="metric-card"><div class="label">Pagila rental revenue</div><div class="value text-primary"><?= dollar($pagilaRevenue); ?></div><div class="note">Dari <code>stg_payment</code> database rental film.</div></div></div>
                <div class="col-md-4"><div class="metric-card"><div class="label">Pagila rental transactions</div><div class="value text-success"><?= number_format($pagilaRentals); ?></div><div class="note">Volume transaksi rental historis.</div></div></div>
                <div class="col-md-4"><div class="metric-card"><div class="label">Staging payment revenue</div><div class="value" style="color:var(--amber);"><?= dollar($gmv); ?></div><div class="note">Akumulasi dari <code>stg_payment</code>.</div></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><h2><i class="fa-solid fa-database me-1"></i> Integrasi Pagila</h2><span class="badge text-bg-light border">Tetap rental film</span></div>
                <p class="text-muted mb-0">Halaman ini memakai sumber data rental film Pagila melalui tabel staging seperti <code>staging_customer</code>, <code>staging_film</code>, <code>staging_store</code>, <code>stg_rental</code>, <code>stg_payment</code>, dan <code>stg_inventory</code>.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const trendLabels = <?= json_encode($trendLabels); ?>;
    const trendRevenue = <?= json_encode($trendRevenue); ?>;
    const trendOrders = <?= json_encode($trendOrders); ?>;
    const categoryLabels = <?= json_encode($categoryLabels); ?>;
    const categoryRevenue = <?= json_encode($categoryRevenue); ?>;
    const productLabels = <?= json_encode($productLabels); ?>;
    const productRevenue = <?= json_encode($productRevenue); ?>;
    const filmBiLabels = <?= json_encode($filmBiLabels); ?>;
    const filmBiRevenue = <?= json_encode($filmBiRevenue); ?>;
    const storeBiLabels = <?= json_encode($storeBiLabels); ?>;
    const storeBiRevenue = <?= json_encode($storeBiRevenue); ?>;
    const benchmarkChartLabels = <?= json_encode($benchmarkChartLabels); ?>;
    const benchmarkActual = <?= json_encode($benchmarkActual); ?>;
    const benchmarkTarget = <?= json_encode($benchmarkTarget); ?>;
    const timeCompareLabels = <?= json_encode($timeCompareLabels); ?>;
    const timeCompareValues = <?= json_encode($timeCompareValues); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const trendChart = document.getElementById('trendChart');
        if (trendChart) {
            new Chart(trendChart, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        { label: 'Sales', data: trendRevenue, borderColor: '#0f766e', backgroundColor: 'rgba(15,118,110,.08)', fill: true, tension: .25, pointRadius: 3 },
                        { label: 'Orders', data: trendOrders, borderColor: '#b7791f', yAxisID: 'y1', tension: .25, pointRadius: 3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: value => '$' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
                    }
                }
            });
        }

        const categoryChart = document.getElementById('categoryChart');
        if (categoryChart) {
            new Chart(categoryChart, {
                type: 'doughnut',
                data: { labels: categoryLabels, datasets: [{ data: categoryRevenue, backgroundColor: ['#0f766e', '#2563eb', '#b7791f', '#be123c', '#475569'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
            });
        }

        const productChart = document.getElementById('productChart');
        if (productChart) {
            new Chart(productChart, {
                type: 'bar',
                data: { labels: productLabels, datasets: [{ label: 'Revenue', data: productRevenue, backgroundColor: '#2563eb' }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
            });
        }

        const filmBiChart = document.getElementById('filmBiChart');
        if (filmBiChart) {
            new Chart(filmBiChart, {
                type: 'bar',
                data: { labels: filmBiLabels, datasets: [{ label: 'Rental Revenue', data: filmBiRevenue, backgroundColor: '#0f766e' }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
            });
        }

        const storeBiChart = document.getElementById('storeBiChart');
        if (storeBiChart) {
            new Chart(storeBiChart, {
                type: 'bar',
                data: { labels: storeBiLabels, datasets: [{ label: 'Store Revenue', data: storeBiRevenue, backgroundColor: '#b7791f' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }

        const benchmarkChart = document.getElementById('benchmarkChart');
        if (benchmarkChart) {
            new Chart(benchmarkChart, {
                type: 'bar',
                data: {
                    labels: benchmarkChartLabels,
                    datasets: [
                        { label: 'Aktual (% target)', data: benchmarkActual, backgroundColor: '#0f766e' },
                        { label: 'Benchmark', data: benchmarkTarget, backgroundColor: '#d9e1e8' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                    scales: { y: { beginAtZero: true, ticks: { callback: value => value + '%' } } }
                }
            });
        }

        const timeChartOptions = {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const value = Number(context.raw);
                            if (value >= 86400) return 'Waktu: 1 hari / batch harian';
                            if (value >= 60) return 'Waktu: ' + Math.round(value / 60) + ' menit';
                            return 'Waktu: ' + value + ' detik';
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'logarithmic',
                    min: 1,
                    ticks: {
                        callback: value => {
                            if (value === 1) return '1 dtk';
                            if (value === 10) return '10 dtk';
                            if (value === 100) return '~2 mnt';
                            if (value === 1000) return '~17 mnt';
                            if (value === 10000) return '~3 jam';
                            return '';
                        }
                    }
                }
            }
        };

        const timeCompareChart = document.getElementById('timeCompareChart');
        if (timeCompareChart) {
            new Chart(timeCompareChart, {
                type: 'bar',
                data: { labels: timeCompareLabels, datasets: [{ data: timeCompareValues, backgroundColor: ['#0f766e', '#2563eb', '#b7791f', '#be123c'] }] },
                options: timeChartOptions
            });
        }

        const timeDetailChart = document.getElementById('timeDetailChart');
        if (timeDetailChart) {
            new Chart(timeDetailChart, {
                type: 'bar',
                data: { labels: timeCompareLabels, datasets: [{ data: timeCompareValues, backgroundColor: ['#0f766e', '#2563eb', '#b7791f', '#be123c'] }] },
                options: timeChartOptions
            });
        }
    });
</script>
</body>
</html>
