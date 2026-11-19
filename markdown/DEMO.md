# Naskah Demo Smart Koperasi

Durasi target: 10–12 menit. Demo memprioritaskan non-happy-path.

## 1. Persiapan sebelum tampil — 1 menit 30 detik

**Tindakan:**

```bash
cd backend
php artisan migrate:fresh --seed
PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload --port=8000
```

Jalankan frontend pada terminal lain:

```bash
cd frontend
npm run dev
```

Siapkan tab browser untuk ADMIN, CASHIER, dan MEMBER. Pastikan akun seeder
tersedia, lalu cek tabel barang: `SKU-001` sampai `SKU-010` dengan harga dan
stok awal yang tercantum di `README.md`. Siapkan screenshot dan berkas
`evidence/` sebagai cadangan bila browser atau server gagal.

**Hasil yang diharapkan:** tiga role dapat login, API memakai PostgreSQL, dan
server berjalan dengan delapan worker.

**Poin bicara:** Reset membuat demo dapat diulang; `--no-reload` membuat Laravel
menghormati `PHP_CLI_SERVER_WORKERS=8`.

## 2. Konsep 70/30 dan arsitektur — 30 detik

**Tindakan:** Tunjukkan diagram tiga tier di `ARCHITECTURE.md`, tanpa membuka
kode.

**Hasil yang diharapkan:** penguji melihat React, API Laravel berlapis, dan
PostgreSQL.

**Poin bicara:** Sebagian besar aplikasi adalah alur POS, tetapi 30% tersulit
adalah stok multi-item, rollback, dan konkurensi.

## 3. Happy path singkat — 1 menit

**Tindakan:** Login sebagai CASHIER. Tambahkan `Buku Tulis` satu unit. Pilih
CASH, masukkan `10000`, lalu tekan **Checkout** dan buka struk.

**Hasil yang diharapkan:** HTTP 201, total Rp5.000, kembalian Rp5.000, stok
berkurang satu, dan struk dapat dicetak.

**Poin bicara:** Harga dan total berasal dari server; frontend hanya memberi UX.

## 4. HP-01: rollback lima barang di UI — 2 menit

**Tindakan:** Reset database bila happy path sebelumnya mengubah stok. Login
sebagai MEMBER, lalu kirim pesanan online yang menghabiskan `SKU-004`:

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Authorization: Bearer <TOKEN_MEMBER>" \
  -H 'Content-Type: application/json' \
  -d '{"payment_method":"TRANSFER","items":[{"item_id":4,"quantity":35}]}'
```

Login sebagai CASHIER. Isi keranjang dengan item 1, 2, 3, 4, dan 5, masing-masing
satu unit. Pilih CASH dengan uang cukup, lalu tekan **Checkout**.

**Hasil yang diharapkan:** UI menampilkan panel 409 dan semua barang yang kurang.
Keranjang tetap ada. Stok item 1, 2, 3, dan 5 tidak berubah. Riwayat mutasi
tidak berisi penjualan dari checkout yang gagal.

**Poin bicara:** Satu item gagal membuat seluruh transaksi batal; tidak ada
perubahan parsial.

## 5. HP-02 dan HP-03: konkurensi — 2 menit

**Tindakan:** Jalankan dari root setelah login environment tersedia:

```bash
DEMO_PASSWORD='<nilai-SEED_DEFAULT_PASSWORD>' node scripts/concurrency-test.mjs
```

Atau tampilkan `evidence/concurrency-run-clean.txt` bila waktu terbatas.

**Hasil yang diharapkan:** HP-02 menghasilkan tepat satu 201 dan satu 409 per
ronde. HP-03 menghasilkan tepat 5 dari 20 request berhasil. Tidak ada respons
tak terduga dan stok akhir 0.

**Poin bicara:** `FOR UPDATE` membuat satu pemenang per stok yang tersedia,
sedangkan request lain mendapat 409 tanpa oversell.

## 6. HP-08: rollback di bawah beban — 1 menit

**Tindakan:** Tunjukkan baris HP-08 pada output skrip atau
`evidence/concurrency-run-clean.txt`.

**Hasil yang diharapkan:** Dengan stok A 1000 dan B 1, tepat satu request
berhasil. Stok A menjadi 999, bukan 990; stok B menjadi 0.

**Poin bicara:** Pengurangan A pada sembilan transaksi gagal ikut di-rollback.

## 7. Kontrol negatif — 30 detik

**Tindakan:** Buka `evidence/concurrency-negative-control.txt`.

**Hasil yang diharapkan:** Output menunjukkan `HASIL AKHIR: GAGAL`, oversell,
dan ketidaksesuaian ledger setelah `FOR UPDATE` dihapus sementara.

**Poin bicara:** Bukti ini membuktikan skrip mampu mendeteksi race condition,
bukan hanya mencetak hasil lulus.

## 8. Bukti database — 1 menit

**Tindakan:** Jalankan empat query pada `evidence/sql-consistency.txt`. Tampilkan
`evidence/sample-stock-movements.txt`. Untuk constraint stok, gunakan:

```sql
BEGIN;
UPDATE items SET stock = -1 WHERE id = 1;
ROLLBACK;
```

**Hasil yang diharapkan:** Semua query konsistensi menghasilkan `(0 rows)`.
PostgreSQL menolak update dengan `items_stock_non_negative_check`.

**Poin bicara:** Ledger dan constraint database menjadi pertahanan terakhir,
termasuk bila ada kesalahan di lapisan aplikasi.

## 9. Dokumen konteks sebagai otak AI — 1 menit

**Tindakan:** Tunjukkan `REQUIREMENTS.md`, `ARCHITECTURE.md`, `RULES.md`, dan
`TASKS.md`, lalu jelaskan urutan fase.

**Hasil yang diharapkan:** Penguji melihat keputusan bisnis, desain transaksi,
aturan keamanan, dan bukti tugas.

**Poin bicara:** Dokumen konteks membatasi AI agar mengikuti kebutuhan dan tidak
menambah endpoint di luar scope.

## 10. Penutup dan pertanyaan penguji — 1 menit 30 detik

### Mengapa `FOR UPDATE`, bukan optimistic locking?

Checkout sudah memakai transaksi PostgreSQL dan perlu mengunci beberapa item
sekaligus. Row lock memberi keputusan atomik langsung; pengurutan ID menaik
mencegah circular wait. Bukti: `CheckoutTest::checkout_locks_items_with_one_ordered_for_update_query`.

### Apa yang mencegah deadlock?

Semua checkout mengunci item dalam urutan `item_id` menaik. HP-04 menjalankan
urutan keranjang terbalik dan tetap selesai tanpa error tak terduga.

### Mengapa stok kurang memakai 409, bukan 400?

Payload valid, tetapi state stok saat ini menolak konflik transaksi. Karena itu
server mengembalikan `INSUFFICIENT_STOCK` dengan HTTP 409.

### Apa yang terjadi bila server mati di tengah transaksi?

PostgreSQL membatalkan transaksi yang belum commit. Order, order item, stok, dan
mutasi tidak tertinggal sebagai perubahan parsial.

### Mengapa total aman dari harga palsu?

Server mengambil harga dari row item yang sudah dikunci. Tes
`CheckoutTest::fake_prices_from_client_are_ignored` membuktikannya.

### Mengapa login dibatasi tetapi API tidak global?

Rate limit aktif pada endpoint login, lima percobaan per menit per email dan IP.
Pembatas global API belum menjadi scope implementasi dan dicatat sebagai batasan.

### Apa batas sistem ini?

Belum ada payment gateway, retur, reservasi, idempotency key, pagination, atau
deployment production. Token browser juga masih berada di `sessionStorage`.

### Bukti utama apa yang dapat diulang?

`php artisan test`, `npm run lint`, `npm run build`, skrip konkurensi, empat
query konsistensi, dan respons nyata di `evidence/`.
