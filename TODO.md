# TODO - Peta Persebaran Pelanggan & Top 5 State

- [ ] Tambahkan routing `customer_map` di `index.php` (agar tidak jatuh ke Executive Summary `else`).
- [ ] Buat query untuk Top 5 state/region berdasarkan pelanggan terbanyak:
  - sumber: `fact_customer_activity`
  - dimensi: `dim_geography.region`
- [ ] Render “peta” persebaran pelanggan di halaman `customer_map`:
  - gunakan `map-panel` + `map-bubble` grid (deterministic positioning)
  - tampilkan label state dan jumlah pelanggan
- [ ] Tampilkan tabel Top 5 state berdasarkan pelanggan terbanyak.
- [ ] Validasi halaman bisa diakses via: `index.php?page=customer_map`

