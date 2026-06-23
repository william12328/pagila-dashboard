BEGIN;

-- Refresh dashboard-friendly staging tables from the Pagila raw staging tables
-- that already exist in PgAdmin. Keep stg_payment, stg_rental, and
-- stg_inventory intact so rental revenue stays aligned with Pagila amounts.

DROP TABLE IF EXISTS public.staging_payment CASCADE;
DROP TABLE IF EXISTS public.staging_store CASCADE;
DROP TABLE IF EXISTS public.staging_film CASCADE;
DROP TABLE IF EXISTS public.staging_customer CASCADE;

CREATE TABLE public.staging_customer (
    customer_id INTEGER PRIMARY KEY,
    first_name VARCHAR(80),
    last_name VARCHAR(80),
    email VARCHAR(160),
    address VARCHAR(180),
    city VARCHAR(80),
    country VARCHAR(80),
    phone VARCHAR(40),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    create_date TIMESTAMP,
    last_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_processed BOOLEAN DEFAULT FALSE
);

CREATE TABLE public.staging_film (
    film_id INTEGER PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT,
    release_year INTEGER,
    rental_duration INTEGER,
    rental_rate NUMERIC(14,2) NOT NULL DEFAULT 0,
    length INTEGER,
    replacement_cost NUMERIC(14,2) NOT NULL DEFAULT 0,
    rating VARCHAR(30),
    category VARCHAR(120),
    last_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_processed BOOLEAN DEFAULT FALSE
);

CREATE TABLE public.staging_store (
    store_id INTEGER PRIMARY KEY,
    manager_staff_id INTEGER,
    address VARCHAR(180),
    city VARCHAR(80),
    country VARCHAR(80),
    last_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_processed BOOLEAN DEFAULT FALSE
);

CREATE TABLE public.staging_payment (
    payment_id INTEGER PRIMARY KEY,
    customer_id INTEGER,
    staff_id INTEGER,
    rental_id INTEGER,
    amount NUMERIC(14,2) NOT NULL DEFAULT 0,
    payment_date TIMESTAMP NOT NULL,
    last_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_processed BOOLEAN DEFAULT FALSE
);

INSERT INTO public.staging_customer (
    customer_id,
    first_name,
    last_name,
    email,
    address,
    city,
    country,
    phone,
    active,
    create_date
)
SELECT c.customer_id,
       c.first_name,
       c.last_name,
       c.email,
       a.address,
       ci.city,
       co.country,
       a.phone,
       COALESCE(c.activebool, c.active = 1, TRUE) AS active,
       c.create_date::timestamp AS create_date
FROM public.stg_customer c
LEFT JOIN public.stg_address a ON a.address_id = c.address_id
LEFT JOIN public.stg_city ci ON ci.city_id = a.city_id
LEFT JOIN public.stg_country co ON co.country_id = ci.country_id;

INSERT INTO public.staging_film (
    film_id,
    title,
    description,
    release_year,
    rental_duration,
    rental_rate,
    length,
    replacement_cost,
    rating,
    category
)
SELECT f.film_id,
       f.title,
       f.description,
       f.release_year::integer AS release_year,
       f.rental_duration,
       f.rental_rate,
       f.length,
       f.replacement_cost,
       f.rating,
       COALESCE(STRING_AGG(DISTINCT cat.name, ', ' ORDER BY cat.name), 'Uncategorized') AS category
FROM public.stg_film f
LEFT JOIN public.stg_film_category fc ON fc.film_id = f.film_id
LEFT JOIN public.stg_category cat ON cat.category_id = fc.category_id
GROUP BY f.film_id,
         f.title,
         f.description,
         f.release_year,
         f.rental_duration,
         f.rental_rate,
         f.length,
         f.replacement_cost,
         f.rating;

INSERT INTO public.staging_store (
    store_id,
    manager_staff_id,
    address,
    city,
    country
)
SELECT s.store_id,
       s.manager_staff_id,
       a.address,
       ci.city,
       co.country
FROM public.stg_store s
LEFT JOIN public.stg_address a ON a.address_id = s.address_id
LEFT JOIN public.stg_city ci ON ci.city_id = a.city_id
LEFT JOIN public.stg_country co ON co.country_id = ci.country_id;

INSERT INTO public.staging_payment (
    payment_id,
    customer_id,
    staff_id,
    rental_id,
    amount,
    payment_date
)
SELECT p.payment_id,
       p.customer_id,
       p.staff_id,
       p.rental_id,
       p.amount,
       p.payment_date::timestamp AS payment_date
FROM public.stg_payment p;

CREATE INDEX idx_staging_customer_city ON public.staging_customer(city);
CREATE INDEX idx_staging_customer_country ON public.staging_customer(country);
CREATE INDEX idx_staging_film_category ON public.staging_film(category);
CREATE INDEX idx_staging_store_city ON public.staging_store(city);
CREATE INDEX idx_staging_payment_date ON public.staging_payment(payment_date);
CREATE INDEX idx_staging_payment_rental ON public.staging_payment(rental_id);

COMMIT;
