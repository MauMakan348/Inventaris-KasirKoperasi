# Ketentuan Fase 6 - Checkout Multi-Item (The 30% Hard Problem)

Instruksi rinci untuk fase yang sedang dikerjakan. File ini ditimpa setiap ganti fase.

Ini fase INTI penilaian. Utamakan ketepatan di atas kecepatan. Jangan mengerjakan Fase 7 atau seterusnya.

Bagian dokumen yang relevan: `REQUIREMENTS.md` bagian 4.3, 4.4, 4.5, 5.4, 7, 8. `PRD.md` bagian 1 (BR-01 sampai BR-11), 3.1, 3.2, 3.5, 4. `ARCHITECTURE.md` bagian 6.1, 9. `RULES.md` bagian 2, 3, 7.

Jika isi file ini bertentangan dengan dokumen di `markdown/`, hentikan pekerjaan dan tanyakan dulu.

## LANGKAH AWAL

- Working tree berisi perubahan `AGENTS.md`, `markdown/ketentuan.md`, `markdown/prompt.txt` dari pemilik proyek. Commit TERPISAH lebih dulu dengan pesan `docs: update agent context and phase instructions`. Jangan mengubah isinya.
- Konfirmasi `php artisan test` hijau sebelum mulai.

## KEPUTUSAN DESAIN (sudah ditetapkan, ikuti dan sebutkan di laporan)

1. **Format `order_code`:** `KOP-YYYYMMDD-XXXXXXXX`. Tanggal memakai zona waktu bisnis (`BUSINESS_TIMEZONE`). `XXXXXXXX` = 8 karakter acak huruf besar/angka. Dibuat di dalam callback transaksi. JANGAN menangkap `UniqueConstraintViolation` lalu mencoba lagi di dalam transaksi (di PostgreSQL itu membuat transaksi berstatus aborted). Entropi cukup, tabrakan diabaikan.
2. **Pembayaran:**
   - ONLINE (MEMBER): hanya `TRANSFER`. `CASH` => 422 VALIDATION_ERROR.
   - POS (CASHIER) `CASH`: `amount_paid` wajib. Jika `amount_paid < total` => 422 INSUFFICIENT_PAYMENT. `change_amount = amount_paid - total`.
   - `TRANSFER` (POS maupun ONLINE): `amount_paid` dari request diabaikan. Simpan `amount_paid = total_amount` dan `change_amount = 0`.
3. **Urutan pemeriksaan di dalam transaksi:** kunci baris -> barang tidak ditemukan (404) -> stok kurang (409) -> pembayaran kurang (422). Validasi bentuk input (422) terjadi lebih dulu di Form Request, sebelum transaksi dibuka.
4. **`item_id` yang tidak ada harus 404, BUKAN 422.** Karena itu JANGAN memakai rule `exists:items,id` di Form Request. Pemeriksaan keberadaan dilakukan di service setelah kunci.
5. **Kuantitas ganda digabung** sebelum validasi stok (BR-06). Batas baris keranjang (maksimal 50) dihitung dari baris mentah di request.
6. **Kepemilikan:** MEMBER yang membuka transaksi milik orang lain mendapat **404** (bukan 403). Filter dilakukan di Repository, bukan di Controller.

## FASE 6

### Error & exception
- `InsufficientStockException` membawa daftar SEMUA barang bermasalah: `[{item_id, name, requested, available}]` (`requested` = kuantitas setelah digabung). Dipetakan di handler global ke **409** `INSUFFICIENT_STOCK`, pesan "Stok tidak mencukupi untuk satu atau lebih barang.", `details` berisi daftar tersebut.
- `InsufficientPaymentException` => **422** `INSUFFICIENT_PAYMENT`, `details`: `{total_amount, amount_paid}`.
- Barang tidak ditemukan => **404** `NOT_FOUND`, pesan "Barang tidak ditemukan.", `details`: `{item_ids: [...]}`. Boleh memakai exception khusus kecil untuk ini.
- Ketiganya dipetakan di handler global yang sudah ada (satu tempat), tanpa membocorkan detail internal.

### Repository
- `OrderRepository`: `createOrder(array)`, `createOrderItems(Order, array)`, `listForViewer(User)` (CASHIER/ADMIN semua, MEMBER hanya miliknya, urut `created_at` desc lalu `id` desc, eager load user), `findForViewerOrFail(id, User)` (eager load items dan user; MEMBER difilter `user_id` sehingga milik orang lain => `ModelNotFoundException`).
- Pakai `StockMovementRepository::create()` dan `ItemRepository::lockItemsForUpdate()` yang sudah ada. Pengurangan stok dilakukan eksplisit pada baris yang TERKUNCI, bukan lewat query terpisah di luar kunci.

### Form Request
- `CheckoutRequest`: `items` array wajib, 1..50 baris. `items.*.item_id` integer min 1. `items.*.quantity` integer 1..1000. `payment_method` wajib, salah satu `CASH`/`TRANSFER`. `amount_paid` integer min 0, wajib jika `payment_method = CASH`. Field lain (termasuk `price`, `unit_price`, `total_amount`, `channel`, `user_id`, `status`) TIDAK divalidasi dan diabaikan. Tidak ada rule `exists`. Pesan Indonesia.

### `OrderService::checkout(User $user, array $payload): Order`
Bungkus SEMUA dalam satu `DB::transaction($callback, 3)`. Callback boleh dijalankan ulang saat deadlock, jadi tidak boleh punya efek samping di luar database.

1. Tentukan channel dari role: CASHIER => `POS`, MEMBER => `ONLINE`. Role lain tidak mungkin sampai sini (middleware), tetapi lempar exception jika terjadi.
2. Gabungkan baris dengan `item_id` sama (jumlahkan kuantitas), urutkan `item_id` MENAIK.
3. Validasi aturan pembayaran per channel (keputusan nomor 2) yang tidak butuh data database.
4. `lockItemsForUpdate($ids)` (satu query, `ORDER BY id FOR UPDATE`).
5. Ada `item_id` tidak ditemukan => 404 (kumpulkan semuanya).
6. Kumpulkan SEMUA barang dengan `stock < quantity` => `InsufficientStockException`.
7. Hitung `unit_price`, `subtotal`, `total_amount` dari baris TERKUNCI. Terapkan pembayaran (keputusan nomor 2).
8. Buat `orders` (`status = COMPLETED`) dan `order_items` dengan snapshot `item_name` dan `unit_price`.
9. Untuk setiap barang (urutan `item_id` menaik): `stock_before` dari baris terkunci, `stock_after = before - quantity`, ubah `items.stock`, tulis `stock_movements` (`SALE_POS` atau `SALE_ONLINE`, `quantity_change` negatif, `order_id`, `user_id` pelaku, `note` null).
10. Kembalikan order beserta items. Jangan ada `try/catch` yang menelan exception di dalam transaksi.

Dilarang: membaca stok di luar kunci, memakai `Item::find()` untuk keputusan stok, `sleep()`, cache stok, atau mempercayai harga/total dari klien.

### Resource & Controller
- `OrderResource`: id, order_code, channel, status, payment_method, total_amount, amount_paid, change_amount, created_at, `user` {id, name}, dan `items` (hanya jika relasi dimuat) berisi id, item_id, item_name, quantity, unit_price, subtotal.
- `OrderController` tipis: `store`, `index`, `show`.
- Route:
  - `POST /api/orders` => `role:CASHIER,MEMBER`, **201** `{data: OrderResource}` (ADMIN => 403).
  - `GET /api/orders` => semua role terautentikasi, **200** `{data: [...]}` tanpa `items` di daftar, tanpa pagination.
  - `GET /api/orders/{id}` => semua role terautentikasi (`whereNumber`), **200** dengan `items`, data lengkap untuk struk.

## TES (Feature, `smart_koperasi_test`)

Tambahkan helper di `tests/TestCase.php`, misalnya `createItemWithLedger(...)`, yang membuat barang beserta mutasi stok awalnya agar `assertStockLedgerConsistent()` bermakna. Panggil `assertStockLedgerConsistent()` di akhir SETIAP tes yang mengubah stok.

Untuk tes kegagalan, definisikan helper `snapshotState()` yang menyimpan: stok semua barang dan jumlah baris `orders`, `order_items`, `stock_movements`. Bandingkan sebelum dan sesudah.

1. **Sukses:** POS CASH (kembalian benar), POS TRANSFER, ONLINE TRANSFER. Verifikasi stok berkurang tepat, order dan order_items tersimpan dengan snapshot, mutasi tipe benar (`SALE_POS`/`SALE_ONLINE`), `quantity_change` negatif, `stock_before`/`stock_after` benar, `order_id` terisi.
2. **Otorisasi:** ADMIN 403, tanpa token 401, MEMBER dengan CASH 422.
3. **Validasi 422:** keranjang kosong, lebih dari 50 baris, quantity 0 / negatif / non-integer / lebih dari 1000, `payment_method` hilang atau tidak valid, CASH tanpa `amount_paid`, `item_id` bukan integer. Setiap kegagalan: `snapshotState()` tidak berubah.
4. **404:** `item_id` tidak ada (campur dengan item valid) => 404, snapshot tidak berubah.
5. **INSUFFICIENT_PAYMENT:** uang kurang => 422, snapshot tidak berubah.
6. **HP-06:** body berisi `price`, `unit_price`, `total_amount` palsu => diabaikan, total dari harga database.
7. **HP-01:** keranjang 5 barang, barang ke-4 stok habis (tambahkan satu barang lain yang juga kurang untuk membuktikan daftar lengkap) => 409, `details` memuat SEMUA barang bermasalah, `snapshotState()` identik, ledger konsisten.
8. **HP-05:** `item_id` sama dua kali (2 + 3), stok 5 => sukses, stok 0. Stok 4 => 409 dengan `requested` = 5, stok tetap 4.
9. **HP-07:** paksa kegagalan SETELAH sebagian barang diproses, misalnya ganti binding `StockMovementRepository` dengan versi yang melempar exception pada pemanggilan ke-3. Buktikan rollback penuh: `snapshotState()` identik. Tambahkan satu varian kegagalan setelah stok barang pertama diubah tetapi sebelum order_items selesai ditulis.
10. **Pertahanan terakhir:** buktikan `CHECK (stock >= 0)` masih aktif dengan `UPDATE items SET stock = -1` langsung, dibungkus `DB::transaction` bersarang (savepoint) agar tes tidak merusak transaksi luar. Harapkan `QueryException`.
11. **Urutan kunci:** gunakan `DB::listen`/`DB::getQueryLog` untuk membuktikan checkout menjalankan satu query `select ... for update` dengan `order by` id pada tabel `items` di dalam transaksi.
12. **Riwayat & kepemilikan:** MEMBER hanya melihat transaksinya sendiri di `GET /orders`. CASHIER dan ADMIN melihat semua. MEMBER membuka transaksi orang lain => 404. Detail memuat items lengkap.
13. **Snapshot (BR-05):** ubah harga barang lewat `PUT /items/{id}` setelah transaksi, lalu `GET /orders/{id}` tetap menampilkan `unit_price` dan `item_name` lama.
14. **Gabungan beberapa transaksi berurutan:** setelah beberapa checkout dan restock, `assertStockLedgerConsistent()` lulus.

## VERIFIKASI MANUAL (sertakan perintah dan outputnya di laporan)

1. Di `smart_koperasi` (bukan DB test): jalankan `migrate:fresh --seed`, lakukan satu checkout POS sukses dan satu checkout yang gagal 409 lewat HTTP (`curl` terhadap server lokal), tampilkan responsnya.
2. Jalankan query berikut, semuanya harus mengembalikan **0 baris**:

```sql
-- stok item harus sama dengan total mutasi
SELECT i.id FROM items i
LEFT JOIN stock_movements m ON m.item_id = i.id
GROUP BY i.id, i.stock
HAVING i.stock <> COALESCE(SUM(m.quantity_change), 0);

-- total order harus sama dengan jumlah subtotal item
SELECT o.id FROM orders o
JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.id, o.total_amount
HAVING SUM(oi.subtotal) <> o.total_amount;

-- mutasi penjualan harus sama dengan kuantitas order
SELECT o.id FROM orders o
WHERE (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id)
   <> -(SELECT COALESCE(SUM(quantity_change), 0) FROM stock_movements WHERE order_id = o.id);
```

3. Tunjukkan bahwa `UPDATE items SET stock = -1 WHERE id = 1;` ditolak PostgreSQL (`items_stock_non_negative_check`).

## PENUTUP

Jangan menambah endpoint atau fitur di luar dokumen (tanpa pembatalan, retur, reservasi, idempotency key, diskon). Setelah selesai:

1. Jalankan `php artisan test`.
2. Centang SATU PER SATU kotak di Fase 6 `TASKS.md` yang benar-benar terverifikasi (termasuk sub-tugas). Jangan centang yang belum.
3. Commit `feat(order): add all-or-nothing checkout (phase 6)`.
4. Laporkan ringkasan, keputusan, asumsi, output tes, dan output verifikasi manual. Sebutkan juga hal yang TIDAK sempat dikerjakan atau diragukan.
