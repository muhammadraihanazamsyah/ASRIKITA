# ASRIKITA

Prototipe akademik Capstone Sistem Informasi Universitas Terbuka untuk Bank Sampah Asri RW 02, Malaka Sari, Jakarta Timur. Bukan aplikasi resmi Bank Sampah Asri. Gunakan data simulasi untuk pengembangan dan pengujian.

## Kebutuhan

PHP 8.2+ dengan ekstensi Laravel dan pdo_pgsql, Composer 2, Node.js 22+, npm, dan PostgreSQL 14 (Laragon).
Stack: Laravel 12, Blade, Tailwind CSS 4, Alpine.js, Eloquent, PHPUnit.

## Setup Windows (PowerShell)

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm ci
```

Jalankan PostgreSQL **14** pada Laragon. Buat database lokal `asrikita` melalui pgAdmin atau psql, lalu gunakan username dan password lokal Anda. Instalasi saat ini menggunakan username `postgres`; role khusus aplikasi dapat digunakan sesuai konfigurasi lokal.

Isi `.env` sesuai server lokal:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=asrikita
DB_USERNAME=postgres
DB_PASSWORD=
```

Isi password sendiri; jangan commit `.env`. Setelah PostgreSQL berjalan:

```powershell
php artisan config:clear
php artisan db:show
php artisan migrate
php artisan migrate:status
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Buka http://127.0.0.1:8000 dan klik **Tentang prototipe** untuk memeriksa Alpine.js. Endpoint `/up` hanya memeriksa boot aplikasi, bukan koneksi database.

Untuk pengembangan dengan Vite, jalankan `composer run dev`, atau `php artisan serve` dan `npm run dev` pada dua terminal. Session dan cache menggunakan file, queue menggunakan sync agar halaman awal dapat berjalan sebelum database disiapkan.

## Verifikasi

```powershell
composer validate --strict
php artisan test
npm run build
```

Pengujian awal memeriksa halaman dan boot aplikasi; hasilnya bukan bukti integritas PostgreSQL. Verifikasi database secara terpisah dengan `db:show`, `migrate`, dan `migrate:status`. Migration bawaan Laravel hanya menyediakan tabel infrastruktur. Migration domain tersedia setelah rekonsiliasi PRD dan ERD. Seeder hanya membuat master DATA SIMULASI tanpa akun login dan transaksi.

## Lingkup

Dokumentasi schema, keputusan penyelarasan sumber, batas integritas, dan cara menjalankan tes PostgreSQL: [docs/database-v1.md](docs/database-v1.md).

Tahap 1 dan fondasi database tahap 2 tersedia. Tahap 3 menyediakan login/logout pengelola, pemeriksaan status akun, otorisasi admin/petugas, dan dashboard per peran. Tahap 4 menyediakan master nasabah, jenis/harga sampah, dan pengepul beserta audit perubahan. Service transaksi dan laporan belum tersedia. Ikuti `AGENTS.md` untuk aturan pengembangan.

Panduan master data: [docs/master-data.md](docs/master-data.md). Login sebagai admin lalu gunakan menu master pada dashboard. Record dinonaktifkan melalui status agar riwayat tetap terjaga.

## Login pengelola

Buat akun pertama dengan `php artisan asrikita:create-operator`, pilih peran dan masukkan kata sandi melalui input tersembunyi. Kemudian buka http://127.0.0.1:8000/login. Panduan dan hasil pengujian: [docs/authentication.md](docs/authentication.md).
## Hasil verifikasi bootstrap (17 Oktober 2026)

- Laravel 12.70.0; `composer validate --strict` lolos.
- `php artisan test`: 2 tes lolos, 5 assertions.
- `npm run build`: berhasil; npm melaporkan 0 vulnerabilities saat instalasi.
- HTTP `/` dan `/up`: 200; halaman awal memuat ASRIKITA dan referensi asset hasil build.
- Saat bootstrap awal, koneksi PostgreSQL belum diverifikasi. Versi proyek kemudian disesuaikan menjadi PostgreSQL 14 sesuai Laragon pengguna.
- Optimasi autoload Composer dinonaktifkan untuk setup lokal karena proses optimasi tidak selesai dalam waktu pemeriksaan. Untuk deployment, jalankan `composer install --no-dev --optimize-autoloader` dan verifikasi pada lingkungan target.
- Interaksi Alpine.js belum diperiksa melalui browser; modul berhasil masuk build.

## Hasil fondasi database (26 Oktober 2026)

- PostgreSQL 14.5 terhubung; migration domain berhasil sebagai batch 2, mempertahankan migration bawaan.
- Database berisi 20 tabel dan 2 view agregat saldo/stok.
- Seeder berhasil: 3 nasabah, 3 jenis sampah, dan 1 pengepul DATA SIMULASI, tanpa akun atau transaksi.
- Tes termasuk integrasi PostgreSQL: 9 lolos, 49 assertions. Migration dari schema kosong, rollback, constraint, pembulatan, snapshot, mutasi, dan seeder diuji pada schema sementara.
- Langkah berikutnya: autentikasi dan otorisasi admin/petugas. Service transaksi bisnis belum tersedia.

## Hasil autentikasi (26 Oktober 2026)

- Login/logout, CSRF, pembatasan percobaan login, pemeriksaan akun aktif, dan otorisasi dashboard berhasil diuji.
- Seluruh suite: 21 tes lolos, 172 assertions (termasuk integrasi PostgreSQL).
- Build frontend berhasil; halaman login diperiksa pada desktop/mobile serta interaksi Alpine.js.
- Akun pertama dibuat melalui command interaktif; tidak ada kredensial default.
- Tahap berikutnya: CRUD master data nasabah, jenis/harga sampah, dan pengepul.

## Hasil master data (26 Oktober 2026)

- Tambah/lihat/ubah/nonaktifkan/aktifkan kembali nasabah, jenis sampah, dan pengepul tersedia untuk admin.
- Pencarian, filter status, pagination, riwayat harga, dan audit perubahan tersedia.
- Perubahan harga memakai transaksi dan row lock; formulir dengan harga awal kedaluwarsa ditolak.
- Seluruh suite: 31 tes lolos, 325 assertions; build frontend dan pemeriksaan browser desktop/mobile berhasil.
- Tidak memerlukan migration tambahan. Data QA browser dibuat pada schema terpisah dan sudah dibersihkan.
- Berikutnya: transaksi setoran multi-item, ledger tabungan, dan mutasi stok.
