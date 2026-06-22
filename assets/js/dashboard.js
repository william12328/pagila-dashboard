const IDR_TO_USD_RATE = 15500;
const toUsd = value => Number(value || 0) / IDR_TO_USD_RATE;
const formatUsd = value => '$' + toUsd(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
});

const setText = (id, value) => {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
};

const chartElement = id => document.getElementById(id);

fetch("../api/dashboard_kpi.php")
    .then(response => response.json())
    .then(data => {
        setText("revenueCard", formatUsd(data.revenue));
        setText("rentalCard", data.rental);
        setText("customerCard", data.customer);
        setText("avgCard", formatUsd(data.avg_transaction));
    });

fetch("../api/revenue_month.php")
    .then(response => response.json())
    .then(data => {
        const labels = data.map(item => item.bulan || item.month_name);
        const revenue = data.map(item => toUsd(item.revenue));
        const chart = chartElement("revenueChart");
        const monthlyChart = chartElement("monthlyChart");

        if (chart) {
            new Chart(chart, {
                type: "line",
                data: { labels, datasets: [{ label: "Revenue", data: revenue, fill: true, borderWidth: 3, tension: .4 }] }
            });
        }

        if (monthlyChart) {
            new Chart(monthlyChart, {
                type: "bar",
                data: { labels, datasets: [{ label: "Monthly Revenue", data: revenue }] }
            });
        }
    });

fetch("../api/revenue_store.php")
    .then(response => response.json())
    .then(data => {
        const chart = chartElement("storeChart");
        if (!chart) return;

        new Chart(chart, {
            type: "doughnut",
            data: {
                labels: data.map(item => "Store " + item.store_key),
                datasets: [{ data: data.map(item => toUsd(item.revenue)) }]
            }
        });
    });

fetch("../api/top_film.php")
    .then(response => response.json())
    .then(data => {
        const chart = chartElement("filmChart");
        if (!chart) return;

        new Chart(chart, {
            type: "bar",
            data: {
                labels: data.map(item => item.title),
                datasets: [{ label: "Revenue", data: data.map(item => toUsd(item.revenue)) }]
            },
            options: { indexAxis: "y" }
        });
    });

fetch("../api/customer_segment.php")
    .then(response => response.json())
    .then(data => {
        const chart = chartElement("customerChart");
        if (!chart) return;

        new Chart(chart, {
            type: "pie",
            data: {
                labels: data.map(item => item.risk),
                datasets: [{ data: data.map(item => parseInt(item.total, 10)) }]
            }
        });
    });

fetch("../api/top_customer.php")
    .then(response => response.json())
    .then(data => {
        const chart = chartElement("topCustomerChart");
        if (!chart) return;

        new Chart(chart, {
            type: "bar",
            data: {
                labels: data.map(item => item.city ? ("City " + item.city) : ("Customer " + item.customer_key)),
                datasets: [{ label: "Customer Lifetime Value", data: data.map(item => toUsd(item.customer_lifetime_value)) }]
            }
        });
    });
