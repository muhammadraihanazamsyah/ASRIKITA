# Modul Penarikan Tabungan

Modul ini mencatat penyerahan sebagian saldo tabungan kepada nasabah oleh administrator atau petugas. Nasabah harus aktif dan nominal harus berupa rupiah bulat, lebih dari nol, serta tidak melebihi saldo yang dihitung dari seluruh `ledger_entries`.

Penyimpanan mengunci baris nasabah, menggunakan transaksi basis data, dan menulis mutasi `withdrawal_debit`. Kunci idempotensi mencegah satu formulir tersimpan dua kali. Penarikan tidak mengubah stok sampah.

Pembatalan hanya dapat dilakukan administrator. Data asli dipertahankan dengan status `cancelled`, lalu sistem menulis `withdrawal_reversal` bernilai positif. Alasan, waktu, pelaku, dan audit log disimpan; pembatalan kedua ditolak.

Riwayat tersedia pada menu Penarikan dan Tabungan. Ringkasan serta CSV tersedia pada menu Laporan. Data pengembangan dan pengujian harus menggunakan data simulasi.

## Alur pengujian internal

1. Masuk sebagai admin atau petugas, pilih Penarikan, lalu Catat penarikan.
2. Pilih nasabah aktif, periksa saldo, masukkan nominal dan catatan, kemudian simpan.
3. Periksa saldo dan riwayat ledger nasabah; stok harus tetap sama.
4. Sebagai admin, batalkan transaksi dengan alasan yang jelas dan pastikan saldo kembali.
5. Unduh CSV penarikan pada halaman Laporan dan cocokkan periode serta status.
