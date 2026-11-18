# Software Requirement Specification (SRS) - Smart Koperasi System

## 1. Overview Sistem

Sistem Point of Sales (POS) dan inventaris untuk koperasi kampus. Sistem mengelola master barang, stok riil, transaksi kasir (offline/POS), dan pemesanan online oleh anggota, dengan kontrol stok yang aman terhadap akses bersamaan (*concurrency*).

**Masalah inti (The 30% Hard Problem):** integritas transaksi multi-item dan rollback. Satu checkout dapat berisi banyak barang. Jika salah satu barang gagal divalidasi (misalnya stok habis karena baru dibeli lewat pemesanan online), **seluruh transaksi dibatalkan** dan stok barang lain tidak boleh berubah. Stok tidak boleh pernah bernilai negatif.

## 2. Tech Stack & Environment

| Lapisan | Teknologi |
|---|---|
| Klien App | React (web), JavaScript, Vite |
| Backend | Laravel (versi stabil terbaru, PHP 8.2+), REST API, pola MVC berlapis |
| Autentikasi | Laravel Sanctum (personal access token) |
| Database | PostgreSQL (relasional, mendukung transaksi ACID dan row-level locking) |
| Pertukaran data | JSON |
| Pengujian | PHPUnit (backend), skrip konkurensi Node.js (`scripts/concurrency-test.mjs`) |

## 3. Peran Pengguna (Role)

| Role | Deskripsi |
|---|---|
| `ADMIN` | Mengelola master barang, restock, stock opname, dan melihat laporan. |
| `CASHIER` | Melakukan checkout di kasir (POS) dan melihat riwayat transaksi. |
| `MEMBER` | Anggota koperasi. Melihat katalog, memesan online, dan melihat riwayat pesanan sendiri. |

## 4. Data Model & Database Schema

Semua nilai uang disimpan sebagai **bilangan bulat Rupiah** (tanpa desimal). Semua timestamp disimpan dalam UTC.

### 4.1 users
| Kolom | Tipe | Aturan |
|---|---|---|
| id | BIGINT, PK | |
| name | VARCHAR(100) | Wajib |
| email | VARCHAR(150) | Wajib, unik |
| password | VARCHAR | Hash (bcrypt) |
| role | VARCHAR(20) | CHECK: `ADMIN`, `CASHIER`, `MEMBER` |
| created_at, updated_at | TIMESTAMP | |

### 4.2 items (Barang)
| Kolom | Tipe | Aturan |
|---|---|---|
| id | BIGINT, PK | |
| sku | VARCHAR(30) | Wajib, unik |
| name | VARCHAR(150) | Wajib |
| price | BIGINT | CHECK `price > 0` |
| stock | INTEGER | NOT NULL, **CHECK `stock >= 0`** |
| created_at, updated_at | TIMESTAMP | |

### 4.3 orders (Transaksi)
| Kolom | Tipe | Aturan |
|---|---|---|
| id | BIGINT, PK | |
| order_code | VARCHAR(30) | Unik, dibuat server |
| user_id | BIGINT, FK -> users.id | Kasir (POS) atau anggota (ONLINE) pembuat transaksi |
| channel | VARCHAR(10) | CHECK: `POS`, `ONLINE` |
| status | VARCHAR(15) | CHECK: `COMPLETED` |
| payment_method | VARCHAR(15) | CHECK: `CASH`, `TRANSFER` |
| total_amount | BIGINT | CHECK `>= 0`, dihitung server |
| amount_paid | BIGINT | Nullable. Wajib untuk `CASH` di POS |
| change_amount | BIGINT | Nullable. `amount_paid - total_amount` |
| created_at, updated_at | TIMESTAMP | |

### 4.4 order_items
| Kolom | Tipe | Aturan |
|---|---|---|
| id | BIGINT, PK | |
| order_id | BIGINT, FK -> orders.id | |
| item_id | BIGINT, FK -> items.id | |
| item_name | VARCHAR(150) | Snapshot nama saat transaksi |
| quantity | INTEGER | CHECK `quantity > 0` |
| unit_price | BIGINT | Snapshot harga dari database saat transaksi |
| subtotal | BIGINT | `quantity * unit_price` |

### 4.5 stock_movements (Jejak Audit Stok)
| Kolom | Tipe | Aturan |
|---|---|---|
| id | BIGINT, PK | |
| item_id | BIGINT, FK -> items.id | |
| user_id | BIGINT, FK -> users.id | Pelaku perubahan |
| order_id | BIGINT, FK -> orders.id | Nullable (kosong untuk restock/opname) |
| type | VARCHAR(20) | CHECK: `SALE_POS`, `SALE_ONLINE`, `RESTOCK`, `OPNAME` |
| quantity_change | INTEGER | CHECK `<> 0` (negatif untuk penjualan) |
| stock_before | INTEGER | CHECK `>= 0` |
| stock_after | INTEGER | CHECK `>= 0` |
| note | TEXT | Nullable |
| created_at | TIMESTAMP | |

### 4.6 Relasi
```text
users (1) ---- (N) orders (1) ---- (N) order_items (N) ---- (1) items
users (1) ---- (N) stock_movements (N) ---- (1) items
orders (1) ---- (N) stock_movements
```

## 5. API Endpoints

Prefix seluruh endpoint: `/api`. Kolom "Akses" menunjukkan role yang diizinkan.

### 5.1 Autentikasi
| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| POST | `/auth/register` | Publik | Registrasi anggota (role otomatis `MEMBER`) |
| POST | `/auth/login` | Publik | Login, mengembalikan token |
| POST | `/auth/logout` | Terautentikasi | Mencabut token aktif |
| GET | `/auth/me` | Terautentikasi | Data pengguna saat ini |

### 5.2 Barang
| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| GET | `/items` | Semua role | Daftar barang dengan stok terkini |
| GET | `/items/{id}` | Semua role | Detail satu barang |
| POST | `/items` | ADMIN | Menambah barang baru |
| PUT | `/items/{id}` | ADMIN | Mengubah nama, SKU, harga (bukan stok) |

### 5.3 Stok
| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| POST | `/items/{id}/restock` | ADMIN | Menambah stok masuk |
| POST | `/items/{id}/stock-opname` | ADMIN | Menyesuaikan stok ke jumlah fisik hasil hitung |
| GET | `/items/{id}/movements` | ADMIN | Riwayat mutasi stok barang |

### 5.4 Transaksi
| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| POST | `/orders` | CASHIER, MEMBER | Checkout multi-item. Channel ditentukan dari role: CASHIER = `POS`, MEMBER = `ONLINE` |
| GET | `/orders` | CASHIER, ADMIN: semua. MEMBER: milik sendiri | Riwayat transaksi |
| GET | `/orders/{id}` | CASHIER, ADMIN: semua. MEMBER: milik sendiri | Detail transaksi dan data struk |

### 5.5 Laporan
| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| GET | `/reports/sales?from=YYYY-MM-DD&to=YYYY-MM-DD` | ADMIN | Ringkasan penjualan per periode |

## 6. Contoh Request

**POST `/api/orders`**
```json
{
  "payment_method": "CASH",
  "amount_paid": 50000,
  "items": [
    { "item_id": 1, "quantity": 2 },
    { "item_id": 4, "quantity": 1 }
  ]
}
```
Harga **tidak** dikirim klien. Server mengambil harga dari database.

**POST `/api/items/1/restock`**
```json
{ "quantity": 20, "note": "Pembelian dari supplier" }
```

**POST `/api/items/1/stock-opname`**
```json
{ "actual_stock": 37, "note": "Hasil hitung fisik" }
```

## 7. Format Respons

Sukses:
```json
{ "data": { } }
```

Error:
```json
{
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "Stok tidak mencukupi untuk satu atau lebih barang.",
    "details": [
      { "item_id": 4, "name": "Buku Tulis", "requested": 3, "available": 1 }
    ]
  }
}
```

## 8. Kode Error & HTTP Status

| Kondisi | HTTP | `error.code` |
|---|---|---|
| Input tidak valid | 422 | `VALIDATION_ERROR` |
| Belum login / token tidak valid | 401 | `UNAUTHENTICATED` |
| Role tidak berhak | 403 | `FORBIDDEN` |
| Barang / transaksi tidak ditemukan | 404 | `NOT_FOUND` |
| Stok tidak mencukupi | 409 | `INSUFFICIENT_STOCK` |
| Uang bayar kurang (CASH) | 422 | `INSUFFICIENT_PAYMENT` |
| Login melebihi batas percobaan | 429 | `RATE_LIMITED` |
| Kesalahan server tak terduga | 500 | `INTERNAL_ERROR` (tanpa detail internal) |
