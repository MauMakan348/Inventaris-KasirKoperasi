# Bukti Verifikasi

Folder ini menyimpan keluaran verifikasi Fase 9 dan Fase 10 secara permanen.
Tidak ada kredensial atau nilai sensitif di berkas bukti.

| Berkas | Isi | Cara menghasilkan |
|---|---|---|
| `concurrency-run-clean.txt` | Run bersih: HP-02, HP-03, HP-04, HP-08; semua ronde lulus. | `node scripts/concurrency-test.mjs` dengan environment demo pada server multi-worker dan database baru. |
| `concurrency-run-repeat.txt` | Run kedua yang stabil, lebih dari satu menit setelah run bersih. | Perintah yang sama pada database development. |
| `concurrency-negative-control.txt` | Run sementara tanpa `FOR UPDATE`; skrip mendeteksi oversell dan gagal. | Branch kontrol negatif Fase 9, tidak dipakai sebagai implementasi. |
| `sql-consistency.txt` | Empat query ledger, stok, subtotal, dan mutasi order; semuanya 0 baris. | `psql` pada database sesudah bukti checkout. |
| `sample-409.json` | Respons HTTP 409 nyata dengan dua barang kurang stok. | Checkout online terhadap dua item stok 0. |
| `sample-stock-movements.txt` | Riwayat item dengan RESTOCK awal, SALE_POS, SALE_ONLINE, RESTOCK, dan OPNAME. | `GET /api/items/1/movements` sebagai ADMIN. |

## Screenshot yang diisi pemilik

Folder `evidence/screenshots/` disediakan untuk tangkapan layar manual:

1. Panel 409 pada halaman POS dengan daftar semua barang bermasalah.
2. Keranjang POS tetap ada setelah checkout gagal.
3. Struk checkout sukses dan dialog cetak.
4. Katalog anggota dan pesanan online.
5. Riwayat mutasi stok pada halaman ADMIN.
6. Laporan penjualan pada halaman ADMIN.

Kredensial demo dibaca dari environment; nilainya tidak disalin ke bukti atau
dokumentasi.
