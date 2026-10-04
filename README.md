# Budget Harian (Daily Budget per Divisi)

Webapp PHP + MySQL untuk menampilkan *daily budget* toko per divisi (pengganti sheet REPORT di MASTER_SHARE_BUDGET.xlsx).
Tanpa composer/vendor — cukup upload folder ini ke shared hosting (hostdata.id).

## Instalasi di hostdata.id
1. cPanel → MySQL Databases: buat database + user, beri ALL PRIVILEGES.
2. phpMyAdmin → pilih database → tab SQL → jalankan isi `database/schema.sql`.
3. Upload seluruh isi folder ini ke `public_html/budget` (atau subdomain).
4. Salin `config/config.example.php` → `config/config.php`, isi nama database, user, password.
5. Buka `https://domain-anda/budget/` → login `admin` / `admin123` → wajib ganti password.
6. Menu **Upload** → pilih MASTER_SHARE_BUDGET.xlsx → cek pratinjau → **Simpan**.

Butuh PHP 7.4+ dengan ekstensi `pdo_mysql`, `zip`, `xmlreader`, `mbstring` (standar di cPanel).

## Format upload
Sama dengan sheet **DATA**: baris nama divisi, baris header `Date | Day | Note | total | 16 | 21 | ...`,
lalu satu baris per tanggal. Divisi dikenali dari **kode** di header. Tanggal yang sudah ada diganti.
File hasil *Unduh Excel/CSV* dan *template* bisa langsung di-upload ulang.

## Cloudflare
Browser Cache TTL = "Respect Existing Headers", dan *Purge Cache* setelah upload file baru.
