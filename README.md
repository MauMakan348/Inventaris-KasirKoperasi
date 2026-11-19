# Smart Koperasi

POS dan inventaris koperasi kampus. Backend Laravel di `backend/`, frontend React di `frontend/`, skrip verifikasi di `scripts/`.

## Menjalankan server untuk uji konkurensi

`php artisan serve` biasa hanya melayani satu request sekali jalan. Untuk uji konkurensi, jalankan server dengan banyak worker dari folder `backend/`:

```bash
PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload --port=8000
```

Laravel 13 memerlukan `--no-reload` agar variabel worker dihormati. Tanpa flag itu Artisan memberi peringatan dan hanya membuat satu server, sehingga uji tidak paralel.

Catatan Windows: `PHP_CLI_SERVER_WORKERS` tidak didukung. Gunakan WSL, Laragon, atau Nginx + PHP-FPM.

## Skrip uji konkurensi

Skrip `scripts/concurrency-test.mjs` memakai Node.js 18+ dan hanya fitur bawaan (tanpa paket npm). Skrip membuat barang uji baru dengan SKU unik, tidak mereset database, dan tidak menyentuh barang yang sudah ada.

Variabel environment:

| Variabel | Wajib | Default |
|---|---|---|
| `DEMO_PASSWORD` | ya | tanpa default; skrip berhenti jika kosong |
| `BASE_URL` | tidak | `http://localhost:8000/api` |
| `ADMIN_EMAIL` | tidak | `admin@example.test` |
| `CASHIER_EMAIL` | tidak | `cashier1@example.test` |
| `MEMBER_EMAIL` | tidak | `member1@example.test` |
| `ROUNDS` | tidak | jumlah ronde default tiap skenario |

Jalankan dari root proyek:

```bash
DEMO_PASSWORD='<kata-sandi-demo>' node scripts/concurrency-test.mjs
```

Kode keluar:

- `0`: semua ronde lolos.
- `1`: ada ronde yang gagal, ada respons tak terduga, atau skrip berhenti karena kesalahan.

Sisa barang uji dibiarkan. Reset database development sebelum demo:

```bash
cd backend
php artisan migrate:fresh --seed
```
