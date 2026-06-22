# Deploy ke Alwaysdata

## 1. Buat akun dan database

1. Login ke Alwaysdata.
2. Buka **Databases > PostgreSQL**.
3. Buat database PostgreSQL dan user database.
4. Catat host, port, database, user, dan password.

Host PostgreSQL Alwaysdata biasanya:

```text
postgresql-[account].alwaysdata.net
```

Port default:

```text
5432
```

## 2. Upload file project

Upload semua file project ke folder website Alwaysdata, misalnya:

```text
www/
```

Pastikan `index.php` berada di folder root website.

## 3. Buat file konfigurasi database

Buat file ini langsung di server Alwaysdata:

```text
config/db.local.php
```

Isi dengan data PostgreSQL Alwaysdata:

```php
<?php
return [
    'host' => 'postgresql-NAMA_AKUN.alwaysdata.net',
    'port' => '5432',
    'dbname' => 'NAMA_DATABASE',
    'user' => 'NAMA_USER',
    'password' => 'PASSWORD_DATABASE',
];
```

File `config/db.local.php` sengaja tidak masuk GitHub karena berisi password.

## 4. Import database

Import SQL project ke database PostgreSQL Alwaysdata lewat phpPgAdmin atau terminal SSH.

Minimal jalankan:

```text
sql/commerce_seed.sql
```

Jika dashboard memakai tabel DWH lain seperti `fact_sales`, `dim_film`, atau `staging_customer`, import juga dump database PostgreSQL lokal kamu.

## 5. Cek website

Buka domain/subdomain Alwaysdata kamu. Jika muncul error koneksi database, cek ulang isi `config/db.local.php` dan permission user PostgreSQL.
