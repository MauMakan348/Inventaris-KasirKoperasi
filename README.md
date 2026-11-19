# Smart Koperasi

POS dan inventaris koperasi kampus. Backend memakai Laravel dan PostgreSQL.
Frontend memakai React dan Vite. Skrip verifikasi ada di `scripts/`.

## Dokumen konteks

- [REQUIREMENTS](markdown/REQUIREMENTS.md) — API dan skema data.
- [PRD](markdown/PRD.md) — aturan bisnis dan acceptance criteria.
- [ARCHITECTURE](markdown/ARCHITECTURE.md) — arsitektur dan alur transaksi.
- [RULES](markdown/RULES.md) — aturan implementasi, keamanan, dan tes.
- [TASKS](markdown/TASKS.md) — urutan pekerjaan dan Definition of Done.
- [DEMO](markdown/DEMO.md) — naskah presentasi.
- [CODE_REVIEW](markdown/CODE_REVIEW.md) — self-review berbasis kode.

## Prasyarat

- PHP 8.3+ dengan ekstensi `pdo_pgsql`.
- Composer 2+.
- Node.js 18+ dan npm.
- PostgreSQL 14+.

Port database dapat diubah melalui `DB_PORT`. Default standar PostgreSQL adalah
`5432`. File `.env.example` pada repository memakai `5433` karena cluster dev
lokal fase verifikasi berjalan di port tersebut.

## Instalasi

Buat dua database PostgreSQL, atau gunakan database yang sudah tersedia:

```bash
createdb -h 127.0.0.1 -p 5432 smart_koperasi
createdb -h 127.0.0.1 -p 5432 smart_koperasi_test
```

Siapkan backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Atur nilai berikut di `backend/.env`:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smart_koperasi
DB_USERNAME=smart_koperasi
DB_PASSWORD=
SEED_DEFAULT_PASSWORD=<minimal-8-karakter>
FRONTEND_URL=http://localhost:5173
BUSINESS_TIMEZONE=Asia/Makassar
```

`SEED_DEFAULT_PASSWORD` wajib berisi minimal delapan karakter. Jangan commit
`.env` atau nilai kata sandi.

Migrasikan dan isi data demo:

```bash
php artisan migrate:fresh --seed
```

Siapkan frontend pada terminal lain:

```bash
cd frontend
npm install
cp .env.example .env
```

Pastikan `frontend/.env` berisi URL API yang benar:

```dotenv
VITE_API_BASE_URL=http://localhost:8000/api
```

## Menjalankan aplikasi

Backend mode biasa:

```bash
cd backend
php artisan serve --port=8000
```

Backend untuk uji konkurensi:

```bash
cd backend
PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload --port=8000
```

Laravel 13 memerlukan `--no-reload` agar jumlah worker dihormati. Windows tidak
mendukung `PHP_CLI_SERVER_WORKERS`; gunakan WSL, Laragon, atau Nginx + PHP-FPM.

Frontend:

```bash
cd frontend
npm run dev
```

## Akun demo

Seeder membuat akun berikut:

| Role | Email |
|---|---|
| ADMIN | `admin@example.test` |
| CASHIER | `cashier1@example.test` |
| CASHIER | `cashier2@example.test` |
| MEMBER | `member1@example.test` |
| MEMBER | `member2@example.test` |

Semua akun memakai nilai `SEED_DEFAULT_PASSWORD` dari `.env`. README tidak
menuliskan nilai kata sandi.

Seeder membuat 10 barang:

| SKU | Nama | Harga | Stok awal |
|---|---|---:|---:|
| SKU-001 | Buku Tulis | Rp5.000 | 40 |
| SKU-002 | Pulpen Hitam | Rp3.000 | 60 |
| SKU-003 | Pensil 2B | Rp2.500 | 50 |
| SKU-004 | Penghapus Putih | Rp2.000 | 35 |
| SKU-005 | Penggaris 30cm | Rp7.000 | 25 |
| SKU-006 | Map Plastik | Rp4.500 | 30 |
| SKU-007 | Kertas A4 | Rp55.000 | 15 |
| SKU-008 | Spidol Permanen | Rp8.500 | 20 |
| SKU-009 | Stapler Mini | Rp18.000 | 12 |
| SKU-010 | Isi Stapler | Rp6.000 | 28 |

## Tes

Backend memakai database `smart_koperasi_test` dari `phpunit.xml`:

```bash
cd backend
php artisan test
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

Skrip konkurensi memakai Node.js 18+, `fetch` bawaan, dan tidak mereset
database. Skrip membuat barang uji baru dan meninggalkannya:

```bash
cd ..
DEMO_PASSWORD='<nilai-SEED_DEFAULT_PASSWORD>' node scripts/concurrency-test.mjs
```

Variabel skrip: `BASE_URL` default `http://localhost:8000/api`, `ADMIN_EMAIL`,
`CASHIER_EMAIL`, `MEMBER_EMAIL`, dan `ROUNDS`. `DEMO_PASSWORD` wajib diisi.
Kode keluar `0` berarti semua ronde lulus. Kode keluar `1` berarti ada ronde,
respons, timeout, atau login yang gagal.

## Ringkasan arsitektur

Sistem memakai tiga tier: React client, REST API Laravel berlapis, dan
PostgreSQL. Checkout memakai satu transaksi All-or-Nothing. Service mengunci
baris dengan `FOR UPDATE` dalam urutan `item_id` menaik, lalu database menjaga
`CHECK (stock >= 0)`. Harga, subtotal, dan total selalu dihitung server.

## Batasan yang diketahui

- Belum ada idempotency key checkout.
- Token frontend disimpan di `sessionStorage`.
- Daftar transaksi tidak memakai pagination.
- Tidak ada uji otomatis komponen UI.
- Pembatas laju hanya aktif pada login, bukan global untuk seluruh `/api`.
- Cluster dev fase verifikasi berada di `/tmp`; gunakan PostgreSQL stabil untuk
  demo pemilik proyek.

Skenario demo non-happy-path dan bukti hasil ada di [markdown/DEMO.md](markdown/DEMO.md)
dan [evidence/README.md](evidence/README.md).
