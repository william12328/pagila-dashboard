BEGIN;

DROP TABLE IF EXISTS public.commerce_order_items;
DROP TABLE IF EXISTS public.commerce_orders;
DROP TABLE IF EXISTS public.commerce_campaigns;
DROP TABLE IF EXISTS public.commerce_products;
DROP TABLE IF EXISTS public.commerce_customers;

CREATE TABLE public.commerce_customers (
    customer_id SERIAL PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    phone VARCHAR(40),
    city VARCHAR(80) NOT NULL,
    segment VARCHAR(40) NOT NULL CHECK (segment IN ('VIP', 'Loyal', 'New', 'At Risk')),
    registered_at DATE NOT NULL DEFAULT CURRENT_DATE
);

CREATE TABLE public.commerce_products (
    product_id SERIAL PRIMARY KEY,
    film_key INTEGER,
    sku VARCHAR(40) NOT NULL UNIQUE,
    product_name VARCHAR(180) NOT NULL,
    category VARCHAR(80) NOT NULL,
    format_type VARCHAR(60) NOT NULL,
    price NUMERIC(14,2) NOT NULL CHECK (price >= 0),
    cost_price NUMERIC(14,2) NOT NULL CHECK (cost_price >= 0),
    stock_qty INTEGER NOT NULL DEFAULT 0,
    reorder_level INTEGER NOT NULL DEFAULT 5,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE public.commerce_orders (
    order_id SERIAL PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL REFERENCES public.commerce_customers(customer_id),
    order_date TIMESTAMP NOT NULL,
    status VARCHAR(30) NOT NULL CHECK (status IN ('paid', 'processing', 'shipped', 'completed', 'cancelled')),
    channel VARCHAR(40) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    shipping_fee NUMERIC(14,2) NOT NULL DEFAULT 0,
    discount_amount NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_amount NUMERIC(14,2) NOT NULL DEFAULT 0
);

CREATE TABLE public.commerce_order_items (
    order_item_id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES public.commerce_orders(order_id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES public.commerce_products(product_id),
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(14,2) NOT NULL CHECK (unit_price >= 0)
);

CREATE TABLE public.commerce_campaigns (
    campaign_id SERIAL PRIMARY KEY,
    campaign_date DATE NOT NULL,
    campaign_name VARCHAR(120) NOT NULL,
    channel VARCHAR(60) NOT NULL,
    spend NUMERIC(14,2) NOT NULL DEFAULT 0,
    revenue_attributed NUMERIC(14,2) NOT NULL DEFAULT 0,
    conversion_rate NUMERIC(6,2) NOT NULL DEFAULT 0,
    return_rate NUMERIC(6,2) NOT NULL DEFAULT 0
);

INSERT INTO public.commerce_customers (full_name, email, phone, city, segment, registered_at) VALUES
('Alya Prameswari', 'alya.prameswari@example.com', '0812-1100-2101', 'Jakarta', 'VIP', '2025-09-10'),
('Raka Mahendra', 'raka.mahendra@example.com', '0812-1100-2102', 'Bandung', 'Loyal', '2025-10-04'),
('Nadia Kirana', 'nadia.kirana@example.com', '0812-1100-2103', 'Surabaya', 'New', '2026-01-16'),
('Bima Santoso', 'bima.santoso@example.com', '0812-1100-2104', 'Yogyakarta', 'Loyal', '2025-11-22'),
('Clara Wijaya', 'clara.wijaya@example.com', '0812-1100-2105', 'Medan', 'VIP', '2025-08-28'),
('Dimas Putra', 'dimas.putra@example.com', '0812-1100-2106', 'Semarang', 'At Risk', '2025-12-12'),
('Elena Hartono', 'elena.hartono@example.com', '0812-1100-2107', 'Denpasar', 'Loyal', '2026-02-03'),
('Fajar Nugroho', 'fajar.nugroho@example.com', '0812-1100-2108', 'Makassar', 'New', '2026-03-01'),
('Gita Lestari', 'gita.lestari@example.com', '0812-1100-2109', 'Jakarta', 'VIP', '2025-07-19'),
('Hendra Wibowo', 'hendra.wibowo@example.com', '0812-1100-2110', 'Bandung', 'Loyal', '2026-01-07');

INSERT INTO public.commerce_products (film_key, sku, product_name, category, format_type, price, cost_price, stock_qty, reorder_level) VALUES
(1, 'PG-RENT-001', 'Academy Dinosaur - 3 Day Rental', 'Movie Rental', 'DVD Rental', 35000, 9000, 18, 6),
(2, 'PG-RENT-002', 'Ace Goldfinger - 3 Day Rental', 'Movie Rental', 'DVD Rental', 39000, 11000, 11, 6),
(3, 'PG-RENT-003', 'Adaptation Holes - 5 Day Rental', 'Movie Rental', 'DVD Rental', 45000, 13000, 8, 7),
(4, 'PG-RENT-004', 'Affair Prejudice - 5 Day Rental', 'Movie Rental', 'DVD Rental', 42000, 12000, 13, 6),
(5, 'PG-DIG-001', 'African Egg - Digital Access', 'Digital Access', 'Streaming Pass', 59000, 18000, 100, 20),
(6, 'PG-DIG-002', 'Agent Truman - Digital Access', 'Digital Access', 'Streaming Pass', 62000, 19000, 100, 20),
(7, 'PG-BDL-001', 'Action Weekend Bundle', 'Rental Bundle', 'Bundle', 129000, 41000, 21, 8),
(8, 'PG-BDL-002', 'Family Movie Night Bundle', 'Rental Bundle', 'Bundle', 119000, 38000, 7, 8),
(9, 'PG-MEM-001', 'Pagila Silver Membership', 'Membership', 'Monthly Plan', 99000, 25000, 500, 50),
(10, 'PG-MEM-002', 'Pagila Gold Membership', 'Membership', 'Monthly Plan', 179000, 45000, 500, 50),
(11, 'PG-MER-001', 'Pagila Collector Card', 'Merchandise', 'Physical', 49000, 17000, 34, 10),
(12, 'PG-MER-002', 'Pagila Film Journal', 'Merchandise', 'Physical', 79000, 31000, 5, 10);

INSERT INTO public.commerce_orders (order_number, customer_id, order_date, status, channel, payment_method, shipping_fee, discount_amount, total_amount) VALUES
('PGC-202606-1001', 1, '2026-06-01 10:18:00', 'completed', 'Website', 'Credit Card', 12000, 15000, 260000),
('PGC-202606-1002', 2, '2026-06-02 13:42:00', 'completed', 'Marketplace', 'E-Wallet', 10000, 0, 139000),
('PGC-202606-1003', 3, '2026-06-03 09:31:00', 'shipped', 'Website', 'Virtual Account', 12000, 10000, 200000),
('PGC-202606-1004', 4, '2026-06-04 16:06:00', 'paid', 'Mobile App', 'E-Wallet', 0, 0, 99000),
('PGC-202606-1005', 5, '2026-06-05 11:49:00', 'completed', 'Website', 'Credit Card', 12000, 25000, 374000),
('PGC-202606-1006', 6, '2026-06-06 18:15:00', 'processing', 'Marketplace', 'COD', 10000, 0, 98000),
('PGC-202606-1007', 7, '2026-06-08 12:20:00', 'completed', 'Mobile App', 'E-Wallet', 0, 20000, 293000),
('PGC-202606-1008', 8, '2026-06-09 14:56:00', 'cancelled', 'Website', 'Virtual Account', 0, 0, 0),
('PGC-202606-1009', 9, '2026-06-11 20:11:00', 'completed', 'Website', 'Credit Card', 12000, 30000, 418000),
('PGC-202606-1010', 10, '2026-06-12 08:44:00', 'shipped', 'Marketplace', 'E-Wallet', 10000, 10000, 158000),
('PGC-202606-1011', 1, '2026-06-14 15:25:00', 'completed', 'Mobile App', 'Credit Card', 0, 15000, 223000),
('PGC-202606-1012', 3, '2026-06-15 10:09:00', 'processing', 'Website', 'Virtual Account', 12000, 0, 136000),
('PGC-202606-1013', 5, '2026-06-16 17:32:00', 'completed', 'Website', 'Credit Card', 12000, 20000, 345000),
('PGC-202606-1014', 7, '2026-06-18 12:41:00', 'paid', 'Mobile App', 'E-Wallet', 0, 10000, 183000),
('PGC-202606-1015', 9, '2026-06-20 19:18:00', 'shipped', 'Marketplace', 'Credit Card', 12000, 25000, 295000);

INSERT INTO public.commerce_order_items (order_id, product_id, quantity, unit_price) VALUES
(1, 10, 1, 179000), (1, 11, 1, 49000), (1, 1, 1, 35000),
(2, 7, 1, 129000),
(3, 8, 1, 119000), (3, 12, 1, 79000),
(4, 9, 1, 99000),
(5, 10, 1, 179000), (5, 7, 1, 129000), (5, 12, 1, 79000),
(6, 2, 1, 39000), (6, 11, 1, 49000),
(7, 9, 1, 99000), (7, 10, 1, 179000), (7, 1, 1, 35000),
(8, 8, 1, 119000),
(9, 10, 1, 179000), (9, 7, 1, 129000), (9, 11, 1, 49000), (9, 12, 1, 79000),
(10, 8, 1, 119000), (10, 2, 1, 39000),
(11, 10, 1, 179000), (11, 5, 1, 59000),
(12, 6, 2, 62000),
(13, 10, 1, 179000), (13, 7, 1, 129000), (13, 3, 1, 45000),
(14, 9, 1, 99000), (14, 5, 1, 59000), (14, 1, 1, 35000),
(15, 7, 1, 129000), (15, 10, 1, 179000);

INSERT INTO public.commerce_campaigns (campaign_date, campaign_name, channel, spend, revenue_attributed, conversion_rate, return_rate) VALUES
('2026-06-01', 'Mid Year Movie Pass', 'Meta Ads', 850000, 3260000, 3.80, 1.10),
('2026-06-03', 'Marketplace Rental Booster', 'Marketplace Ads', 720000, 2410000, 2.95, 1.80),
('2026-06-05', 'VIP Member Premiere', 'Email', 260000, 1970000, 6.40, 0.80),
('2026-06-08', 'Mobile App Movie Night', 'Push Notification', 310000, 1680000, 5.75, 1.10),
('2026-06-11', 'Search Film Intent', 'Google Ads', 1190000, 3920000, 3.25, 1.35),
('2026-06-15', 'Weekend Bundle Promo', 'TikTok Ads', 640000, 2010000, 3.10, 2.05),
('2026-06-18', 'Loyalty Winback Rental', 'WhatsApp', 180000, 1190000, 4.95, 0.85),
('2026-06-20', 'Cart Recovery Movie Pass', 'Email', 220000, 1395000, 5.30, 0.95);

CREATE INDEX idx_commerce_orders_date ON public.commerce_orders(order_date);
CREATE INDEX idx_commerce_orders_status ON public.commerce_orders(status);
CREATE INDEX idx_commerce_order_items_order ON public.commerce_order_items(order_id);
CREATE INDEX idx_commerce_order_items_product ON public.commerce_order_items(product_id);
CREATE INDEX idx_commerce_products_category ON public.commerce_products(category);
CREATE INDEX idx_commerce_campaigns_date ON public.commerce_campaigns(campaign_date);

COMMIT;
