# Architecture - Smart Koperasi System

## 1. Tujuan

Dokumen ini menjelaskan rancangan teknis **Smart Koperasi System** berdasarkan `REQUIREMENTS.md` dan `PRD.md`. Sistem terdiri dari klien React, REST API Laravel, dan database PostgreSQL (arsitektur 3-tier wajib).

## 2. Teknologi

- **Klien:** React (JavaScript, Vite), React Router, Axios
- **Backend:** Laravel (PHP 8.2+), Laravel Sanctum
- **Database:** PostgreSQL
- **Format data:** JSON
- **Pengujian:** PHPUnit, skrip konkurensi Node.js

## 3. Gaya Arsitektur

Arsitektur 3-tier, dengan backend berlapis (*layered architecture*):

```text
React Client (UI, validasi UX, state)
        |  HTTPS + JSON + Bearer token
        v
Routes (routes/api.php) + Middleware (auth, role)
        |
        v
Controllers  --- Form Requests (validasi input)
        |
        v
Service Layer (aturan bisnis, batas transaksi DB)
        |
        v
Repository Layer (query Eloquent / Query Builder)
        |
        v
PostgreSQL (constraint, transaksi ACID, row locking)
```

### Tanggung jawab lapisan

1. **Client (React)**
   - Antarmuka per role, validasi UX, manajemen state (keranjang, sesi).
   - Menampilkan error API (termasuk daftar barang yang stoknya kurang).
   - Tidak pernah dianggap sebagai sumber kebenaran harga atau stok.
2. **Routes & Middleware**
   - Mendefinisikan endpoint dan menerapkan `auth:sanctum` dan middleware role (`EnsureRole`).
3. **Controllers**
   - Menerima request, memanggil service, mengembalikan Resource/JSON dan status code.
   - Tidak berisi aturan bisnis.
4. **Form Requests**
   - Validasi bentuk dan tipe input (kuantitas bilangan bulat positif, keranjang tidak kosong, dan seterusnya).
5. **Service Layer**
   - Menjalankan aturan bisnis dan **memiliki batas transaksi database** (`DB::transaction`).
   - Menggabungkan item duplikat, menghitung total, memvalidasi stok, memanggil repository.
6. **Repository Layer**
   - Seluruh query database. Memakai Eloquent/Query Builder (binding parameter otomatis).
   - Menyediakan `lockItemsForUpdate(array $ids)` yang mengurutkan ID dan memakai `FOR UPDATE`.
7. **Database**
   - Menegakkan PK, FK, UNIQUE, dan CHECK (`stock >= 0`) sebagai pertahanan terakhir.

## 4. Struktur Direktori

```text
KasirKoperasi/
├── backend/                         # Laravel
│   ├── app/
│   │   ├── Enums/
│   │   │   ├── Role.php
│   │   │   ├── OrderChannel.php
│   │   │   ├── PaymentMethod.php
│   │   │   └── StockMovementType.php
│   │   ├── Exceptions/
│   │   │   ├── InsufficientStockException.php
│   │   │   ├── InsufficientPaymentException.php
│   │   │   └── ApiExceptionHandler.php
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── ItemController.php
│   │   │   │   ├── StockController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   └── ReportController.php
│   │   │   ├── Middleware/EnsureRole.php
│   │   │   ├── Requests/            # StoreItemRequest, CheckoutRequest, dst.
│   │   │   └── Resources/           # ItemResource, OrderResource, dst.
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Item.php
│   │   │   ├── Order.php
│   │   │   ├── OrderItem.php
│   │   │   └── StockMovement.php
│   │   ├── Repositories/
│   │   │   ├── ItemRepository.php
│   │   │   ├── OrderRepository.php
│   │   │   ├── StockMovementRepository.php
│   │   │   └── ReportRepository.php
│   │   └── Services/
│   │       ├── AuthService.php
│   │       ├── ItemService.php
│   │       ├── StockService.php
│   │       ├── OrderService.php     # inti The 30% Hard Problem
│   │       └── ReportService.php
│   ├── database/
│   │   ├── migrations/
│   │   ├── factories/
│   │   └── seeders/
│   ├── routes/api.php
│   ├── tests/
│   │   ├── Feature/
│   │   │   ├── AuthTest.php
│   │   │   ├── ItemTest.php
│   │   │   ├── StockTest.php
│   │   │   ├── CheckoutTest.php
│   │   │   ├── CheckoutRollbackTest.php
│   │   │   ├── OrderHistoryTest.php
│   │   │   └── ReportTest.php
│   │   └── Unit/
│   ├── .env.example
│   └── composer.json
├── frontend/                        # React + Vite
│   ├── src/
│   │   ├── api/                     # axios instance, pemanggil endpoint
│   │   ├── context/AuthContext.jsx
│   │   ├── components/
│   │   ├── pages/
│   │   │   ├── LoginPage.jsx
│   │   │   ├── PosPage.jsx          # kasir
│   │   │   ├── CatalogPage.jsx      # anggota
│   │   │   ├── OrdersPage.jsx
│   │   │   ├── ReceiptPage.jsx
│   │   │   ├── AdminItemsPage.jsx
│   │   │   ├── AdminStockPage.jsx
│   │   │   └── AdminReportPage.jsx
│   │   ├── App.jsx
│   │   └── main.jsx
│   └── package.json
├── scripts/
│   └── concurrency-test.mjs         # bukti HP-02, HP-03, HP-04
├── markdown/                        # dokumen konteks AI
│   ├── REQUIREMENTS.md
│   ├── PRD.md
│   ├── ARCHITECTURE.md
│   ├── RULES.md
│   ├── TASKS.md
│   └── CODE_REVIEW.md
├── AGENTS.md                        # instruksi otomatis untuk opencode
└── README.md
```

Struktur ini adalah usulan. Boleh disesuaikan selama tanggung jawab lapisan dan alur bisnis tetap terjaga.

## 5. Skema Database

Lihat detail kolom di `REQUIREMENTS.md` bagian 4. Ringkasan constraint penting:

| Tabel | Constraint kunci |
|---|---|
| users | UNIQUE(email), CHECK(role IN ...) |
| items | UNIQUE(sku), CHECK(price > 0), **CHECK(stock >= 0)** |
| orders | UNIQUE(order_code), CHECK(channel, status, payment_method), FK(user_id) |
| order_items | CHECK(quantity > 0), FK(order_id), FK(item_id) ON DELETE RESTRICT |
| stock_movements | CHECK(type), CHECK(quantity_change <> 0), CHECK(stock_before >= 0 AND stock_after >= 0) |

Indeks: `orders(user_id, created_at)`, `orders(created_at)`, `order_items(order_id)`, `order_items(item_id)`, `stock_movements(item_id, created_at)`.

## 6. Alur Proses Bisnis

### 6.1 Checkout multi-item (inti sistem)

Dijalankan di `OrderService::checkout()` dalam **satu** `DB::transaction()`:

1. Controller menerima request tervalidasi (bentuk input sudah benar).
2. Service menggabungkan baris keranjang dengan `item_id` sama (BR-06).
3. Service mengurutkan `item_id` **menaik**.
4. **Mulai transaksi** (isolation default PostgreSQL: READ COMMITTED, retry otomatis jika deadlock: `DB::transaction($callback, 3)`).
5. `ItemRepository::lockItemsForUpdate($ids)`:
   `SELECT * FROM items WHERE id IN (...) ORDER BY id FOR UPDATE`.
6. Jika ada `item_id` tidak ditemukan -> lempar `ModelNotFoundException` (404), rollback.
7. Periksa seluruh baris: kumpulkan **semua** barang dengan `stock < quantity`. Jika ada -> lempar `InsufficientStockException` membawa daftar detail (409), rollback.
8. Hitung `unit_price`, `subtotal`, `total_amount` dari data yang baru dikunci. Untuk POS CASH, periksa `amount_paid >= total_amount`.
9. Buat `orders` dan `order_items`.
10. Kurangi `items.stock` dan tulis `stock_movements` (`stock_before`, `stock_after`) untuk tiap barang.
11. **Commit**. Jika terjadi exception apa pun sebelum commit, Laravel melakukan rollback otomatis, sehingga tidak ada perubahan parsial.

Mengapa aman:
- Baris item dikunci sebelum diperiksa, sehingga tidak ada celah *check-then-act* antara pengecekan dan pengurangan stok.
- Urutan kunci selalu menaik, sehingga dua transaksi yang saling tumpang tindih tidak menunggu secara melingkar (mencegah deadlock).
- `CHECK (stock >= 0)` menjadi jaring pengaman terakhir di level database.

### 6.2 Restock
1. Mulai transaksi. 2. Kunci baris item (`FOR UPDATE`). 3. `stock = stock + quantity`. 4. Tulis mutasi `RESTOCK`. 5. Commit.

### 6.3 Stock opname
1. Mulai transaksi. 2. Kunci baris item. 3. Hitung selisih = `actual_stock - stock`. 4. Set `stock = actual_stock`. 5. Tulis mutasi `OPNAME` (selisih boleh negatif). 6. Commit.

### 6.4 Riwayat, struk, dan laporan
- `GET /orders` difilter berdasarkan role (MEMBER hanya miliknya).
- Struk berasal dari `GET /orders/{id}` (data order dan order_items snapshot). Klien mencetak lewat `window.print()`.
- Laporan memakai agregasi SQL (`SUM`, `COUNT`, `GROUP BY`) dengan batas tanggal pada zona waktu `Asia/Makassar`, dikonversi ke UTC saat query.

## 7. Autentikasi & Otorisasi

- Laravel Sanctum token. Token dikirim lewat header `Authorization: Bearer <token>`.
- Middleware `EnsureRole` memeriksa role per route group.
- Kepemilikan data (MEMBER hanya melihat order sendiri) diperiksa di service/repository, bukan hanya di UI.
- Password di-hash (bcrypt). Rate limit pada `/auth/login`.
- Klien menyimpan token di memori/`sessionStorage`, bukan di source code.

## 8. Penanganan Error

Seluruh exception dipetakan di satu tempat (`bootstrap/app.php` atau `ApiExceptionHandler`) ke format error pada `REQUIREMENTS.md` bagian 7. Pesan error tidak boleh membocorkan SQL, stack trace, atau detail internal.

## 9. Konsistensi & Integritas Data

- Seluruh operasi yang mengubah lebih dari satu tabel wajib berada dalam `DB::transaction`.
- Jangan pernah membaca stok lalu menulisnya kembali tanpa mengunci baris (*lost update*).
- Harga dan total tidak pernah dipercaya dari klien.
- Timestamp UTC di database. Konversi ke WITA hanya di tampilan dan batas laporan.
- Uang berupa integer Rupiah.

## 10. Catatan Lingkungan Demo

- `php artisan serve` secara default hanya melayani satu request sekali jalan, sehingga tes konkurensi tidak bermakna. Jalankan dengan `PHP_CLI_SERVER_WORKERS=8 php artisan serve` (atau gunakan Nginx + PHP-FPM) saat demo.
- Gunakan database PostgreSQL yang sama untuk demo dan skrip konkurensi. Jangan gunakan SQLite.

## 11. Batasan dan Hal yang Belum Ditentukan

Lihat `PRD.md` bagian 5. Fokus implementasi awal pada endpoint dan aturan bisnis yang sudah ditetapkan.
