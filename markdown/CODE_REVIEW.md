# Code Review Checklist - Smart Koperasi System

## 1. Tujuan Review

Review ini menelusuri implementasi nyata, tes PHPUnit, konfigurasi, dan bukti
konkurensi. Kotak hanya dicentang jika ada rujukan.

## 2. Requirement Compliance

- [x] Klien menggunakan React, backend Laravel, database PostgreSQL, data JSON. (`frontend/package.json`, `backend/composer.json`, `backend/phpunit.xml`)
- [x] Seluruh endpoint pada `REQUIREMENTS.md` bagian 5 tersedia dengan method dan path yang benar. (`backend/routes/api.php:9-33`; `AuthTest`, `ItemTest`, `StockTest`, `CheckoutTest`, `OrderHistoryTest`, `ReportTest`)
- [x] Nama field, enum, dan status sesuai spesifikasi. (`backend/app/Enums/`, `backend/app/Http/Resources/`, `CheckoutTest`)
- [x] Hak akses tiap endpoint sesuai tabel role. (`backend/routes/api.php:13-33`; `RoleMiddlewareTest`; `CheckoutTest::admin_cannot_checkout`)

## 3. Data Model dan Database

- [x] Semua tabel dan kolom sesuai `REQUIREMENTS.md` bagian 4. (migrasi `backend/database/migrations/2026_11_18_213822_create_items_table.php` sampai `2026_11_18_220048_create_stock_movements_table.php`)
- [x] PK, FK (RESTRICT), UNIQUE, dan indeks sudah didefinisikan. (migrasi orders, order_items, stock_movements)
- [x] `CHECK (stock >= 0)` aktif di `items`. CHECK lain (price, quantity, enum) aktif. (`CheckoutRollbackTest::database_check_constraint_still_rejects_negative_stock`; migrasi)
- [x] Nilai uang disimpan sebagai integer. Timestamp dalam UTC. (`REQUIREMENTS.md:30`; model casts dan `ReportService`)
- [x] Tidak ada SQL hasil concatenation dari input pengguna. `DB::raw` tidak memuat input pengguna. (`backend/app/Repositories/`; `git grep -n 'DB::raw'` hanya ekspresi agregasi tetap)
- [x] `$fillable` aman. `role` dan `stock` tidak dapat diisi lewat mass assignment dari input publik. (`backend/app/Models/User.php`, `Item.php`; `ItemTest`)

## 4. Business Logic

### 4.1 Checkout (kritis)

- [x] Seluruh proses berada dalam satu `DB::transaction` yang dimulai di Service. (`backend/app/Services/OrderService.php:47-137`)
- [x] Baris item dikunci `FOR UPDATE` sebelum stok diperiksa. (`OrderService.php:51-68`; `CheckoutTest::checkout_locks_items_with_one_ordered_for_update_query`)
- [x] Penguncian dalam urutan `item_id` menaik. (`ItemRepository::lockItemsForUpdate`; tes lock)
- [x] Item duplikat digabung sebelum validasi. (`OrderService::mergeLines`; `CheckoutRollbackTest::hp05_duplicate_lines_are_merged_before_stock_check`)
- [x] Semua barang bermasalah dilaporkan sekaligus pada error 409. (`CheckoutRollbackTest::hp01_one_short_item_rolls_back_the_whole_cart`)
- [x] Item tidak ditemukan -> 404 tanpa perubahan data. (`CheckoutTest::unknown_item_returns_not_found_without_changes`)
- [x] Harga dan total dihitung dari database. Input harga klien diabaikan. (`CheckoutTest::fake_prices_from_client_are_ignored`)
- [x] Snapshot `item_name` dan `unit_price` tersimpan. (`CheckoutTest::pos_cash_checkout_stores_order_and_returns_change`)
- [x] Mutasi stok tercatat dengan `stock_before` dan `stock_after` yang benar. (`CheckoutTest`; `evidence/sample-stock-movements.txt`)
- [x] Tidak ada `try/catch` yang menelan exception di dalam transaksi. (`OrderService::checkout`; `CheckoutRollbackTest`)
- [x] Channel ditentukan dari role. ADMIN ditolak. (`OrderService::channelFor`; `CheckoutTest::admin_cannot_checkout`)
- [x] Validasi pembayaran CASH dan perhitungan kembalian benar. (`CheckoutTest::pos_cash_checkout_stores_order_and_returns_change`; `cash_payment_below_total_returns_insufficient_payment`)

### 4.2 Restock & Opname

- [x] Menggunakan transaksi dan penguncian baris. (`StockService.php:27-49`, `60-86`)
- [x] Mutasi `RESTOCK` dan `OPNAME` tercatat. Selisih opname benar, termasuk negatif. (`StockTest`; `evidence/sample-stock-movements.txt`)
- [x] Validasi kuantitas dan `actual_stock` benar. (`StockTest`)

### 4.3 Barang, Riwayat, Laporan

- [x] `PUT /items/{id}` tidak mengubah stok. (`ItemTest`)
- [x] MEMBER hanya melihat transaksi miliknya. Transaksi orang lain -> 404. (`OrderHistoryTest`)
- [x] Laporan memakai agregasi SQL dan batas tanggal Asia/Makassar yang benar. (`ReportRepository`; `ReportService`; `ReportTest`)

## 5. API Contract

- [x] Struktur respons sukses dan error konsisten (`data` / `error.code`, `message`, `details`). (`ApiExceptionHandler.php`; `ErrorResponseTest`; resources)
- [x] HTTP status sesuai `REQUIREMENTS.md` bagian 8. (`AuthTest`, `RoleMiddlewareTest`, `CheckoutTest`, `StockTest`, `ReportTest`)
- [x] Error tidak membocorkan SQL, stack trace, atau path internal. `APP_DEBUG=false` di luar development. (`ErrorResponseTest`; `ApiExceptionHandler.php`)
- [x] `GET /items` menampilkan stok terkini dari database. (`ItemTest::item_list_returns_current_stock_ordered_by_name`)

## 6. Arsitektur & Kualitas Kode

- [x] Controller tipis. Aturan bisnis ada di Service. Query ada di Repository. (`backend/app/Http/Controllers/Api/`; `backend/app/Services/`; `backend/app/Repositories/`)
- [x] Validasi bentuk input ada di Form Request. (`backend/app/Http/Requests/`; feature tests)
- [x] Penamaan sesuai `RULES.md`. Tidak ada kode mati, `dd()`, `console.log` debug, atau TODO tanpa penjelasan. (scan Fase 10; `console.log` pada `scripts/concurrency-test.mjs` adalah output hasil, bukan debug)
- [x] Tidak ada duplikasi logika transaksi atau validasi yang tidak perlu. (`OrderService`, `StockService`, repository)
- [x] Komentar menjelaskan *mengapa* pada bagian penguncian dan transaksi. (`OrderService.php:28-34`; `ItemRepository.php:22-33`)
- [x] Panggilan API frontend terpusat di `src/api/`. (`frontend/src/api/`; halaman hanya memanggil modul API)

## 7. Security & Reliability

- [x] Password di-hash. Pesan login generik. Rate limit login aktif. (`AuthService.php`; `AuthTest`; `AppServiceProvider.php:26-29`; `routes/api.php:11`)
- [x] Registrasi publik hanya menghasilkan `MEMBER`. (`AuthTest`)
- [x] Semua endpoint kecuali register/login memerlukan token. Middleware role terpasang. (`routes/api.php`; `RoleMiddlewareTest`; `ItemTest::item_endpoints_require_authentication`)
- [x] Tidak ada secret di source code. `.env` tidak ter-commit. `.env.example` tersedia. (`git ls-files`; `.gitignore`; `backend/.env.example`)
- [x] CORS dibatasi ke origin klien. (`backend/config/cors.php`; `FRONTEND_URL`)
- [x] Tidak ada nilai stok yang hanya disimpan di memori atau cache. (`StockService`; migration constraint; `evidence/sql-consistency.txt`)

## 8. Frontend

- [x] Tombol checkout dinonaktifkan selama request berjalan. (`frontend/src/pages/PosPage.jsx:183-212,318-325`; `useSubmitGuard`)
- [x] Error 409 menampilkan daftar barang bermasalah dengan jelas. Keranjang tidak hilang. (`PosPage.jsx:301-315`; `StockShortageAlert.jsx`; `useCart`)
- [x] Route dilindungi per role. Token tidak di-hardcode. (`ProtectedRoute.jsx`; `App.jsx`; `api/client.js:3-29`)
- [x] Klien tidak menjadi sumber kebenaran harga atau stok. (`OrderService`; `PosPage` reload setelah 409)

## 9. Test Evidence

- [x] Ada tes jalur sukses dan gagal untuk endpoint utama. (`AuthTest`, `ItemTest`, `StockTest`, `CheckoutTest`, `OrderHistoryTest`, `ReportTest`)
- [x] Ada tes HP-01 sampai HP-07 sesuai `PRD.md`. (`CheckoutRollbackTest`; `CheckoutTest`; 83 tes lulus)
- [x] Tes rollback membuktikan stok dan jumlah baris `orders`, `order_items`, `stock_movements` tidak berubah setelah gagal. (`CheckoutRollbackTest::hp01_one_short_item_rolls_back_the_whole_cart`; `hp07_*`)
- [x] Tes memakai PostgreSQL, bukan SQLite. (`backend/phpunit.xml:28-34`)
- [x] `scripts/concurrency-test.mjs` membuktikan HP-02, HP-03, HP-04 dan outputnya terdokumentasi. (`evidence/concurrency-run-clean.txt`; `concurrency-run-repeat.txt`; kontrol negatif)
- [x] Hasil pengujian dapat diulang dengan satu perintah yang tertulis di README. (`README.md` bagian Tes)

## 10. Review Findings

| ID | File/Bagian | Temuan | Tingkat | Rekomendasi | Status |
|---|---|---|---|---|---|
| CR-01 | Checkout API | Tidak ada idempotency key; retry setelah timeout dapat membuat order kedua. | Medium | Tambahkan idempotency setelah ada keputusan API dan skema. | Accepted, di luar scope |
| CR-02 | `frontend/src/api/client.js:6` | Token berada di `sessionStorage`; XSS pada origin dapat membacanya. | Medium | Gunakan strategi cookie HttpOnly setelah deployment dan keputusan auth. | Accepted, batasan diketahui |
| CR-03 | `frontend/src/` | Tidak ada uji otomatis komponen UI. | Medium | Tambahkan test runner UI pada fase pengujian frontend berikutnya. | Accepted, bukan fitur Fase 10 |
| CR-04 | `OrderRepository` | Daftar order tidak memakai pagination. | Low | Tambahkan pagination saat kebutuhan dan kontrak API berubah. | Accepted, `RULES.md` melarang fitur tambahan |
| CR-05 | `routes/api.php` | Rate limit hanya ada pada login; `/api` tidak memiliki pembatas global. | Low | Tinjau rate limit global sebelum deployment publik. | Accepted, dicatat di README |
| CR-06 | Environment demo | Cluster dev saat verifikasi berada di `/tmp`, sehingga data dapat hilang. | Low | Gunakan instalasi PostgreSQL stabil sebelum presentasi. | Accepted, risiko lingkungan |

Tidak ditemukan temuan Critical atau High. Tidak ada perubahan kode aplikasi
diperlukan pada Fase 10.

## 11. Review Summary

- **Status:** Approved with Changes
- **Jumlah temuan Critical:** 0
- **Jumlah temuan High:** 0
- **Jumlah temuan Medium:** 3
- **Jumlah temuan Low:** 3
- **Catatan reviewer:** Alur stok, rollback, role, kontrak API, dan konkurensi
  memiliki bukti tes atau output nyata. Enam batasan diterima dan tidak ditutup
  dengan perubahan dokumen yang menyembunyikan masalah.
