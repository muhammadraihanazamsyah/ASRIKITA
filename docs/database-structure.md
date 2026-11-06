# Struktur Database ASRIKITA

Dokumen ini menggambarkan struktur PostgreSQL yang dibentuk oleh seluruh migration pada branch `feat/asrikita-mvp`. Struktur terdiri dari 13 tabel domain ASRIKITA, 7 tabel internal Laravel, dan 2 database view.

## Konvensi data

- Primary key domain menggunakan `BIGINT` identity.
- Nilai uang disimpan sebagai `BIGINT` dalam rupiah tanpa pecahan.
- Berat disimpan sebagai `NUMERIC(12,3)` dalam kilogram.
- Waktu pada tabel domain menggunakan `TIMESTAMPTZ`.
- Foreign key domain menggunakan `ON DELETE RESTRICT`, kecuali `audit_logs.actor_id` yang menggunakan `ON DELETE SET NULL`.
- Data transaksi tidak dihapus ketika dibatalkan. Status transaksi diubah dan mutasi balik dibuat agar tetap dapat diaudit.

## Tabel domain ASRIKITA

### `members`

Menyimpan data nasabah bank sampah.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `member_code` | VARCHAR(30) | Tidak | - | Kode unik nasabah |
| `full_name` | VARCHAR(150) | Tidak | - | Nama lengkap |
| `phone` | VARCHAR(30) | Ya | NULL | Nomor telepon |
| `address` | TEXT | Ya | NULL | Alamat |
| `status` | VARCHAR(12) | Tidak | `active` | `active` atau `inactive` |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu dibuat |
| `updated_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu diperbarui |

Constraint: `member_code` unik dan `status IN ('active','inactive')`.

### `users`

Menyimpan akun administrator, petugas, dan nasabah. Tabel bawaan Laravel ini diperluas oleh migration domain.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `name` | VARCHAR(255) | Tidak | - | Nama pengguna |
| `email` | VARCHAR(255) | Tidak | - | Email unik |
| `email_verified_at` | TIMESTAMP | Ya | NULL | Waktu verifikasi email |
| `password` | VARCHAR(255) | Tidak | - | Hash password |
| `remember_token` | VARCHAR(100) | Ya | NULL | Token sesi remember me |
| `member_id` | BIGINT | Ya | NULL | FK unik ke `members.id` |
| `role` | VARCHAR(12) | Tidak | - | `admin`, `staff`, atau `member` |
| `status` | VARCHAR(12) | Tidak | `disabled` | `active` atau `disabled` |
| `created_at` | TIMESTAMP | Ya | NULL | Waktu dibuat |
| `updated_at` | TIMESTAMP | Ya | NULL | Waktu diperbarui |

Constraint: `email` unik, `member_id` unik, akun dengan role `member` wajib mempunyai `member_id`.

### `waste_types`

Menyimpan jenis sampah dan harga beli yang sedang berlaku.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `name` | VARCHAR(100) | Tidak | - | Nama unik jenis sampah |
| `unit` | VARCHAR(2) | Tidak | `kg` | Satuan, selalu `kg` |
| `current_buy_price` | BIGINT | Tidak | - | Harga beli per kg, minimal 0 |
| `is_active` | BOOLEAN | Tidak | TRUE | Status jenis sampah |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu dibuat |
| `updated_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu diperbarui |

### `waste_price_history`

Mencatat histori perubahan harga beli sampah.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `waste_type_id` | BIGINT | Tidak | - | FK ke `waste_types.id` |
| `old_buy_price` | BIGINT | Ya | NULL | Harga sebelumnya, minimal 0 |
| `new_buy_price` | BIGINT | Tidak | - | Harga baru, minimal 0 |
| `changed_by` | BIGINT | Tidak | - | FK ke `users.id` |
| `changed_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu perubahan |
| `note` | VARCHAR(255) | Ya | NULL | Catatan perubahan |

### `collectors`

Menyimpan data pengepul sebagai mitra penjualan.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `name` | VARCHAR(150) | Tidak | - | Nama pengepul |
| `phone` | VARCHAR(30) | Ya | NULL | Nomor telepon |
| `address` | TEXT | Ya | NULL | Alamat |
| `is_active` | BOOLEAN | Tidak | TRUE | Status pengepul |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu dibuat |
| `updated_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu diperbarui |

### `deposits`

Menyimpan header transaksi setoran nasabah.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `deposit_number` | VARCHAR(40) | Tidak | - | Nomor setoran unik |
| `member_id` | BIGINT | Tidak | - | FK ke `members.id` |
| `staff_id` | BIGINT | Tidak | - | FK pencatat ke `users.id` |
| `occurred_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu transaksi |
| `status` | VARCHAR(10) | Tidak | `posted` | `posted` atau `void` |
| `total_amount` | BIGINT | Tidak | - | Total nilai setoran, minimal 0 |
| `idempotency_key` | VARCHAR(100) | Ya | NULL | Kunci unik pencegah transaksi ganda |
| `voided_at` | TIMESTAMPTZ | Ya | NULL | Waktu pembatalan |
| `voided_by` | BIGINT | Ya | NULL | FK pembatal ke `users.id` |
| `void_reason` | TEXT | Ya | NULL | Alasan pembatalan |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu dibuat |
| `updated_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu diperbarui |

Constraint pembatalan memastikan kolom `voided_at`, `voided_by`, dan `void_reason` hanya terisi lengkap saat status `void`.

### `deposit_items`

Menyimpan rincian jenis sampah dalam transaksi setoran.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `deposit_id` | BIGINT | Tidak | - | FK ke `deposits.id` |
| `waste_type_id` | BIGINT | Tidak | - | FK ke `waste_types.id` |
| `weight_kg` | NUMERIC(12,3) | Tidak | - | Berat, harus lebih dari 0 |
| `buy_price_snapshot` | BIGINT | Tidak | - | Harga beli historis per kg |
| `amount` | BIGINT | Tidak | - | Nilai item |

Constraint nilai item: `amount = ROUND(weight_kg * buy_price_snapshot, 0)`.

### `sales`

Menyimpan header transaksi penjualan kepada pengepul.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `sale_number` | VARCHAR(40) | Tidak | - | Nomor penjualan unik |
| `collector_id` | BIGINT | Tidak | - | FK ke `collectors.id` |
| `staff_id` | BIGINT | Tidak | - | FK pencatat ke `users.id` |
| `occurred_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu transaksi |
| `status` | VARCHAR(10) | Tidak | `posted` | `posted` atau `void` |
| `total_amount` | BIGINT | Tidak | - | Total penjualan, minimal 0 |
| `idempotency_key` | VARCHAR(100) | Ya | NULL | Kunci unik pencegah transaksi ganda |
| `voided_at` | TIMESTAMPTZ | Ya | NULL | Waktu pembatalan |
| `voided_by` | BIGINT | Ya | NULL | FK pembatal ke `users.id` |
| `void_reason` | TEXT | Ya | NULL | Alasan pembatalan |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu dibuat |
| `updated_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu diperbarui |

Constraint pembatalan memastikan kolom pembatalan hanya terisi lengkap saat status `void`.

### `sale_items`

Menyimpan rincian jenis sampah dalam transaksi penjualan.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `sale_id` | BIGINT | Tidak | - | FK ke `sales.id` |
| `waste_type_id` | BIGINT | Tidak | - | FK ke `waste_types.id` |
| `weight_kg` | NUMERIC(12,3) | Tidak | - | Berat, harus lebih dari 0 |
| `sell_price_snapshot` | BIGINT | Tidak | - | Harga jual historis per kg |
| `amount` | BIGINT | Tidak | - | Nilai item |

Constraint nilai item: `amount = ROUND(weight_kg * sell_price_snapshot, 0)`.

### `withdrawals`

Menyimpan transaksi penarikan saldo nasabah.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `withdrawal_number` | VARCHAR(40) | Tidak | - | Nomor penarikan unik |
| `member_id` | BIGINT | Tidak | - | FK ke `members.id` |
| `amount` | BIGINT | Tidak | - | Nilai penarikan, harus lebih dari 0 |
| `status` | VARCHAR(12) | Tidak | `posted` | `posted` atau `cancelled` |
| `notes` | TEXT | Ya | NULL | Catatan transaksi |
| `idempotency_key` | VARCHAR(100) | Tidak | - | Kunci unik pencegah transaksi ganda |
| `recorded_by` | BIGINT | Tidak | - | FK pencatat ke `users.id` |
| `cancelled_by` | BIGINT | Ya | NULL | FK pembatal ke `users.id` |
| `cancelled_at` | TIMESTAMPTZ | Ya | NULL | Waktu pembatalan |
| `cancellation_reason` | TEXT | Ya | NULL | Alasan pembatalan |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu dibuat |
| `updated_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu diperbarui |

### `ledger_entries`

Menyimpan seluruh mutasi saldo nasabah. Saldo tidak disimpan sebagai kolom yang dapat diedit.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `member_id` | BIGINT | Tidak | - | FK ke `members.id` |
| `deposit_id` | BIGINT | Ya | NULL | FK ke `deposits.id` |
| `withdrawal_id` | BIGINT | Ya | NULL | FK ke `withdrawals.id` |
| `entry_type` | VARCHAR(20) | Tidak | - | Jenis mutasi saldo |
| `amount_signed` | BIGINT | Tidak | - | Nilai bertanda positif/negatif |
| `reversal_of_id` | BIGINT | Ya | NULL | FK unik ke mutasi asli |
| `created_by` | BIGINT | Tidak | - | FK ke `users.id` |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu mutasi |

Nilai `entry_type`: `deposit_credit`, `deposit_reversal`, `withdrawal_debit`, atau `withdrawal_reversal`. Setiap baris wajib merujuk tepat satu sumber, yaitu setoran atau penarikan. Tanda nilai dan keberadaan `reversal_of_id` divalidasi sesuai jenis mutasi.

### `stock_movements`

Menyimpan seluruh mutasi stok sampah. Stok tidak disimpan sebagai kolom yang dapat diedit.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `waste_type_id` | BIGINT | Tidak | - | FK ke `waste_types.id` |
| `deposit_item_id` | BIGINT | Ya | NULL | FK ke `deposit_items.id` |
| `sale_item_id` | BIGINT | Ya | NULL | FK ke `sale_items.id` |
| `movement_type` | VARCHAR(24) | Tidak | - | Jenis pergerakan stok |
| `weight_signed` | NUMERIC(12,3) | Tidak | - | Berat bertanda, tidak boleh 0 |
| `created_by` | BIGINT | Tidak | - | FK ke `users.id` |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu mutasi |

Nilai `movement_type`: `deposit_in`, `deposit_reversal`, `sale_out`, atau `sale_reversal`. Setiap mutasi wajib merujuk tepat satu item setoran atau item penjualan. Arah tanda berat divalidasi sesuai jenis mutasi.

### `audit_logs`

Mencatat jejak audit tindakan penting.

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---:|---|---|
| `id` | BIGINT identity | Tidak | - | Primary key |
| `actor_id` | BIGINT | Ya | NULL | FK ke `users.id`; menjadi NULL jika akun dihapus |
| `action` | VARCHAR(80) | Tidak | - | Nama tindakan |
| `entity_type` | VARCHAR(80) | Tidak | - | Jenis entitas |
| `entity_id` | BIGINT | Tidak | - | ID entitas |
| `metadata` | JSONB | Tidak | `{}` | Data tambahan audit |
| `created_at` | TIMESTAMPTZ | Tidak | NOW() | Waktu kejadian |

## Tabel internal Laravel

### `password_reset_tokens`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `email` | VARCHAR(255) | Tidak | Primary key |
| `token` | VARCHAR(255) | Tidak | Token reset password |
| `created_at` | TIMESTAMP | Ya | Waktu dibuat |

### `sessions`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `id` | VARCHAR(255) | Tidak | Primary key |
| `user_id` | BIGINT | Ya | ID pengguna, memiliki index |
| `ip_address` | VARCHAR(45) | Ya | Alamat IP |
| `user_agent` | TEXT | Ya | Informasi browser |
| `payload` | TEXT | Tidak | Data sesi |
| `last_activity` | INTEGER | Tidak | Waktu aktivitas, memiliki index |

### `cache`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `key` | VARCHAR(255) | Tidak | Primary key |
| `value` | TEXT | Tidak | Nilai cache |
| `expiration` | INTEGER | Tidak | Waktu kedaluwarsa |

### `cache_locks`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `key` | VARCHAR(255) | Tidak | Primary key |
| `owner` | VARCHAR(255) | Tidak | Pemilik lock |
| `expiration` | INTEGER | Tidak | Waktu kedaluwarsa |

### `jobs`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `id` | BIGINT identity | Tidak | Primary key |
| `queue` | VARCHAR(255) | Tidak | Nama antrean, memiliki index |
| `payload` | TEXT | Tidak | Payload job |
| `attempts` | SMALLINT | Tidak | Jumlah percobaan |
| `reserved_at` | INTEGER | Ya | Waktu reservasi |
| `available_at` | INTEGER | Tidak | Waktu job tersedia |
| `created_at` | INTEGER | Tidak | Waktu job dibuat |

### `job_batches`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `id` | VARCHAR(255) | Tidak | Primary key |
| `name` | VARCHAR(255) | Tidak | Nama batch |
| `total_jobs` | INTEGER | Tidak | Total job |
| `pending_jobs` | INTEGER | Tidak | Job tertunda |
| `failed_jobs` | INTEGER | Tidak | Job gagal |
| `failed_job_ids` | TEXT | Tidak | Daftar ID job gagal |
| `options` | TEXT | Ya | Opsi batch |
| `cancelled_at` | INTEGER | Ya | Waktu pembatalan |
| `created_at` | INTEGER | Tidak | Waktu dibuat |
| `finished_at` | INTEGER | Ya | Waktu selesai |

### `failed_jobs`

| Kolom | Tipe | Null | Kunci/Keterangan |
|---|---|---:|---|
| `id` | BIGINT identity | Tidak | Primary key |
| `uuid` | VARCHAR(255) | Tidak | Nilai unik |
| `connection` | TEXT | Tidak | Nama koneksi |
| `queue` | TEXT | Tidak | Nama antrean |
| `payload` | TEXT | Tidak | Payload job |
| `exception` | TEXT | Tidak | Detail exception |
| `failed_at` | TIMESTAMP | Tidak | Default waktu saat ini |

## Database view

### `member_balances`

Menghasilkan saldo setiap nasabah dari penjumlahan `ledger_entries.amount_signed`.

| Kolom | Sumber |
|---|---|
| `member_id` | `members.id` |
| `member_code` | `members.member_code` |
| `full_name` | `members.full_name` |
| `balance_idr` | Total mutasi saldo, `BIGINT` |

### `waste_stock_balances`

Menghasilkan stok setiap jenis sampah dari penjumlahan `stock_movements.weight_signed`.

| Kolom | Sumber |
|---|---|
| `waste_type_id` | `waste_types.id` |
| `name` | `waste_types.name` |
| `unit` | `waste_types.unit` |
| `stock_kg` | Total mutasi stok, `NUMERIC(14,3)` |

## Relasi utama

```text
members ──< deposits ──< deposit_items >── waste_types
   │             │               │                 │
   │             └──< ledger_entries              ├──< waste_price_history
   │                                             │
   ├──< withdrawals ──< ledger_entries           └──< stock_movements
   │
   └── users (opsional untuk akun nasabah)

collectors ──< sales ──< sale_items >── waste_types
                         │
                         └──< stock_movements

users ──< deposits, sales, withdrawals, ledger_entries,
          stock_movements, waste_price_history, audit_logs
```

## Index khusus domain

- Pencarian setoran berdasarkan nasabah dan waktu.
- Pencarian penjualan berdasarkan pengepul dan waktu.
- Riwayat harga berdasarkan jenis sampah dan waktu.
- Mutasi saldo berdasarkan nasabah dan waktu.
- Mutasi stok berdasarkan jenis sampah dan waktu.
- Audit berdasarkan jenis entitas, ID entitas, dan waktu.
- Satu jenis mutasi ledger untuk setiap sumber transaksi.
- Satu jenis mutasi stok untuk setiap item transaksi.

Sumber implementasi: `database/migrations`.
