# Development Tasks - Smart Koperasi System

## Petunjuk

- Kerjakan tugas **secara berurutan**. Jangan lompat fase.
- Tandai `[x]` hanya setelah tugas selesai dan sudah diperiksa (tes lulus bila ada).
- Gunakan `REQUIREMENTS.md`, `PRD.md`, `ARCHITECTURE.md`, dan `RULES.md` sebagai acuan.
- Jangan menambahkan fitur di luar kebutuhan tanpa persetujuan.
- Setiap akhir fase: jalankan seluruh tes, lalu commit.
- Fase 6 adalah inti penilaian (The 30% Hard Problem). Kerjakan dengan paling teliti.

## Fase 1. Project Setup

- [x] Buat struktur proyek `smart-koperasi/` dengan folder `backend/`, `frontend/`, `scripts/`, `markdown/`.
- [x] Inisialisasi proyek Laravel di `backend/`.
- [x] Konfigurasi `.env` dan `.env.example` untuk PostgreSQL (tanpa secret di repo). Pastikan `.env` ada di `.gitignore`.
- [x] Atur `APP_TIMEZONE=UTC`. Tambahkan konfigurasi zona waktu bisnis `Asia/Makassar` untuk laporan.
- [x] Pasang Laravel Sanctum.
- [x] Buat database PostgreSQL untuk development dan database terpisah untuk testing. Konfigurasi `phpunit.xml`.
- [x] Pastikan `php artisan migrate` berjalan tanpa error dan aplikasi dapat dijalankan lokal.
- [x] Inisialisasi proyek React (Vite) di `frontend/`. Pasang React Router dan Axios.

## Fase 2. Database & Model

- [x] Buat Enum: `Role`, `OrderChannel`, `PaymentMethod`, `StockMovementType`.
- [x] Migrasi `users` (tambah kolom `role` dengan CHECK).
- [x] Migrasi `items` (UNIQUE sku, CHECK `price > 0`, **CHECK `stock >= 0`**).
- [x] Migrasi `orders` (UNIQUE `order_code`, CHECK channel/status/payment_method, FK).
- [x] Migrasi `order_items` (CHECK `quantity > 0`, FK RESTRICT).
- [x] Migrasi `stock_movements` (CHECK type, quantity_change, stock_before/after).
- [x] Tambahkan indeks sesuai `ARCHITECTURE.md` bagian 5.
- [x] Buat Model dan relasi: `User`, `Item`, `Order`, `OrderItem`, `StockMovement` (dengan `$fillable` yang aman).
- [x] Buat Factory untuk model utama.
- [x] Buat Seeder: 1 ADMIN, 2 CASHIER, 2 MEMBER, dan sekitar 10 barang dengan stok awal. Password demo dibaca dari env.
- [x] Verifikasi: migrasi dan seeder berjalan bersih. Uji manual bahwa insert stok negatif ditolak oleh database.

## Fase 3. Autentikasi & Otorisasi

- [x] Buat `AuthService`, `AuthController`, dan Form Request untuk register dan login.
- [x] Implementasikan `POST /api/auth/register` (role dipaksa `MEMBER`).
- [x] Implementasikan `POST /api/auth/login` dengan pesan error generik dan rate limit.
- [x] Implementasikan `POST /api/auth/logout` dan `GET /api/auth/me`.
- [x] Buat middleware `EnsureRole` dan daftarkan.
- [x] Buat penanganan error global ke format `{ "error": { "code", "message" } }` (401, 403, 404, 422, 500).
- [x] Tes: register, login sukses/gagal, akses tanpa token, akses dengan role salah.

## Fase 4. Master Barang

- [x] Buat `ItemRepository` (list, find, create, update).
- [x] Buat `ItemService` (validasi bisnis, SKU unik).
- [x] Buat Form Request dan `ItemResource`.
- [x] Implementasikan `GET /api/items` dan `GET /api/items/{id}` (semua role).
- [x] Implementasikan `POST /api/items` dan `PUT /api/items/{id}` (ADMIN). `PUT` tidak boleh mengubah stok.
- [x] Tes: daftar kosong, tambah valid, SKU duplikat, harga <= 0, stok negatif, akses non-admin.

## Fase 5. Stok (Restock & Opname)

- [x] Buat `StockMovementRepository`.
- [x] Tambahkan `lockItemsForUpdate(array $ids)` di `ItemRepository` (`ORDER BY id FOR UPDATE`).
- [x] Buat `StockService::restock()` dalam `DB::transaction` dengan kunci baris dan pencatatan mutasi.
- [x] Buat `StockService::opname()` dalam `DB::transaction` dengan kunci baris dan pencatatan selisih.
- [x] Implementasikan `POST /api/items/{id}/restock`, `POST /api/items/{id}/stock-opname`, `GET /api/items/{id}/movements` (ADMIN).
- [x] Tes: restock valid, kuantitas <= 0, opname naik/turun, opname negatif ditolak, mutasi tercatat benar, akses non-admin.

## Fase 6. Checkout Multi-Item (The 30% Hard Problem)

- [x] Buat `InsufficientStockException` (membawa daftar barang bermasalah) dan `InsufficientPaymentException`. Petakan ke 409 dan 422.
- [x] Buat `OrderRepository` (create order, create order items) dan generator `order_code` unik.
- [x] Buat `CheckoutRequest` (keranjang 1..50 baris, quantity integer 1..1000, payment_method valid).
- [x] Buat `OrderService::checkout()` mengikuti `ARCHITECTURE.md` bagian 6.1:
  - [x] Gabungkan item duplikat dan urutkan `item_id` menaik.
  - [x] Buka `DB::transaction($callback, 3)`.
  - [x] Kunci baris item dengan `FOR UPDATE` secara berurutan.
  - [x] Tangani `item_id` tidak ditemukan (404) tanpa perubahan data.
  - [x] Kumpulkan **semua** barang yang stoknya kurang lalu lempar `InsufficientStockException`.
  - [x] Hitung harga, subtotal, total dari database (abaikan input harga klien).
  - [x] Validasi pembayaran CASH untuk POS dan hitung kembalian.
  - [x] Buat order dan order items (dengan snapshot nama dan harga).
  - [x] Kurangi stok dan tulis `stock_movements` (`SALE_POS` / `SALE_ONLINE`).
- [x] Implementasikan `POST /api/orders` (CASHIER, MEMBER). Channel ditentukan dari role. ADMIN ditolak 403.
- [x] Implementasikan `GET /api/orders` dan `GET /api/orders/{id}` dengan pembatasan kepemilikan untuk MEMBER.
- [x] Tes dasar: checkout POS valid, checkout ONLINE valid, keranjang kosong, quantity tidak valid, item tidak ada, uang kurang, harga palsu diabaikan.
- [x] Tes HP-01: 5 barang, barang ke-4 habis -> 409, **semua stok dan jumlah baris tabel terkait tidak berubah**.
- [x] Tes HP-05: item duplikat digabung lalu ditolak jika stok kurang.
- [x] Tes HP-07: paksa kegagalan setelah beberapa barang diproses -> rollback penuh.
- [x] Verifikasi manual di PostgreSQL bahwa constraint `stock >= 0` tetap aktif.

## Fase 7. Laporan

- [x] Buat `ReportRepository` dengan query agregasi (`SUM`, `COUNT`, `GROUP BY`).
- [x] Buat `ReportService` (konversi batas tanggal Asia/Makassar ke UTC).
- [x] Implementasikan `GET /api/reports/sales?from&to` (ADMIN): total transaksi, total pendapatan, rincian per channel, barang terlaris.
- [x] Tes: periode kosong, periode berisi, validasi tanggal, akses non-admin.

## Fase 8. Frontend React

- [x] Siapkan instance Axios (base URL dari env, header token) dan `AuthContext`.
- [x] Buat `ProtectedRoute` berbasis role dan navigasi per role.
- [x] `LoginPage` dan registrasi anggota.
- [ ] `PosPage` (kasir): cari/pilih barang, keranjang, input pembayaran, tombol checkout, tampilkan kembalian.
- [ ] Tampilkan error 409 dengan daftar barang yang stoknya kurang (mudah dibaca). Keranjang tidak dihapus saat gagal.
- [ ] Cegah klik ganda pada tombol checkout (disable saat request berjalan).
- [ ] `CatalogPage` (anggota): katalog, keranjang, pesan online.
- [ ] `OrdersPage` dan `ReceiptPage` (struk dapat dicetak dengan `window.print()`).
- [ ] `AdminItemsPage` (tambah/ubah barang), `AdminStockPage` (restock, opname, riwayat mutasi).
- [ ] `AdminReportPage` (laporan penjualan dengan filter tanggal).
- [ ] Verifikasi manual alur tiap role dari ujung ke ujung.

## Fase 9. Pengujian Konkurensi & Verifikasi

- [ ] Buat `scripts/concurrency-test.mjs` (Node.js, `Promise.all` terhadap server berjalan).
- [ ] HP-02: stok = 1, satu request POS dan satu ONLINE bersamaan -> tepat satu 201.
- [ ] HP-03: stok = 5, 20 request paralel qty 1 -> tepat 5 berhasil, stok akhir 0.
- [ ] HP-04: checkout paralel dengan urutan barang berlawanan (A,B vs B,A) -> tanpa deadlock.
- [ ] Skrip mencetak ringkasan hasil dan memverifikasi stok akhir langsung dari API.
- [ ] Jalankan server dengan banyak worker (`PHP_CLI_SERVER_WORKERS=8 php artisan serve`) dan dokumentasikan di README.
- [ ] Jalankan seluruh tes PHPUnit dan pastikan lulus.
- [ ] Periksa konsistensi: jumlah `stock_movements` dan stok akhir sesuai setelah banyak transaksi.

## Fase 10. Dokumentasi & Persiapan Presentasi

- [ ] Lengkapi `README.md` (prasyarat, instalasi, `.env`, migrasi, seeder, menjalankan server, menjalankan tes, akun demo).
- [ ] Pastikan keenam dokumen di `markdown/` sinkron dengan implementasi akhir.
- [ ] Hapus kode mati, debug, dan file yang tidak dipakai.
- [ ] Isi `CODE_REVIEW.md` (self-review) dan perbaiki temuan Critical/High.
- [ ] Siapkan skenario demo **non happy path**: HP-01 (rollback 5 barang), HP-02 dan HP-03 (konkurensi), bukti stok tidak minus.
- [ ] Siapkan bukti: output skrip konkurensi, isi tabel `stock_movements`, dan tampilan error 409 di UI.

## Definition of Done

Pekerjaan dianggap selesai apabila:

1. Seluruh endpoint pada `REQUIREMENTS.md` tersedia dan sesuai role.
2. Seluruh acceptance criteria pada `PRD.md` terpenuhi.
3. Stok tidak pernah negatif, dari channel POS maupun ONLINE, termasuk di bawah beban paralel.
4. Checkout gagal tidak meninggalkan perubahan apa pun (All-or-Nothing terbukti oleh tes HP-01 dan HP-07).
5. Skenario HP-01 sampai HP-07 lulus dan dapat didemonstrasikan.
6. Klien React berfungsi untuk ketiga role dan menampilkan error stok dengan jelas.
7. Dokumen konteks lengkap di repository dan `README.md` memungkinkan orang lain menjalankan proyek.
