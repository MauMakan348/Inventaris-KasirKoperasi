# Ketentuan Fase 8A - Frontend: Fondasi, Autentikasi, Routing

Instruksi rinci untuk fase yang sedang dikerjakan. File ini ditimpa setiap ganti fase atau sesi.

Fase 8 (Frontend React) dipecah menjadi tiga sesi:

- **8A (sesi ini):** fondasi, autentikasi, routing, layout.
- 8B: halaman kasir (POS), struk, daftar transaksi.
- 8C: katalog anggota, halaman admin (barang, stok, laporan), verifikasi per role.

Kerjakan HANYA sesi 8A. Jangan membuat halaman fungsional untuk 8B dan 8C (hanya placeholder rute). Jangan mengubah kode backend.

Bagian dokumen yang relevan: `REQUIREMENTS.md` bagian 3, 5.1, 7, 8. `ARCHITECTURE.md` bagian 2, 3 (Client), 4 (struktur `frontend/`), 7. `RULES.md` bagian 3 poin 7, bagian 4 (frontend), bagian 6.

Jika isi file ini bertentangan dengan dokumen di `markdown/`, hentikan pekerjaan dan tanyakan dulu.

## LANGKAH AWAL

1. Commit TERPISAH perubahan milik pemilik proyek (`markdown/ketentuan.md`, `markdown/prompt.txt`) dengan pesan `docs: update phase instructions`. Jangan mengubah isinya.
2. Pastikan `php artisan test` hijau dan `npm run build` di `frontend/` lulus sebelum mulai.
3. Baca kode backend yang relevan SEBELUM menulis klien (jangan menebak): `RegisterRequest`, `LoginRequest`, `AuthController`/`UserResource`. Catat bentuk respons asli `register`, `login`, `me`, dan aturan validasi register (apakah butuh `password_confirmation`). Laporkan temuannya.

## KEPUTUSAN DESAIN (sudah ditetapkan, ikuti dan sebutkan di laporan)

1. **Dependency:** hanya yang sudah terpasang (React, React Router, Axios). Tanpa UI library atau Tailwind. Gaya memakai CSS biasa (satu `index.css` global atau CSS per komponen). Tampilan bersih, rapi, dan responsif sederhana.
2. **Konfigurasi:** `VITE_API_BASE_URL` (default `http://localhost:8000/api`) dan `VITE_BUSINESS_TIMEZONE` (default `Asia/Makassar`). Buat `frontend/.env.example`. Pastikan `frontend/.env` ada di `.gitignore`.
3. **Penyimpanan token:** `sessionStorage` dengan key `koperasi_token`. Data user TIDAK disimpan permanen. Saat aplikasi dimuat, jika ada token, panggil `GET /auth/me`. Selama pengecekan tampilkan status memuat (jangan kedip ke halaman login).
4. **401 di mana pun:** interceptor Axios menghapus token dan mengarahkan ke `/login` (kecuali saat sedang di endpoint login itu sendiri, supaya pesan "email atau password salah" tetap tampil).
5. **Rute dan akses:**

| Rute | Role | Isi sesi 8A |
|---|---|---|
| `/login`, `/register` | publik | Halaman nyata |
| `/pos` | CASHIER | Placeholder |
| `/catalog` | MEMBER | Placeholder |
| `/orders`, `/orders/:id` | semua role | Placeholder |
| `/admin/items`, `/admin/stock`, `/admin/reports` | ADMIN | Placeholder |
| `*` | - | Halaman 404 |

   - Beranda per role setelah login: ADMIN -> `/admin/items`, CASHIER -> `/pos`, MEMBER -> `/catalog`. Rute `/` mengarah ke beranda sesuai role (atau `/login` jika belum login).
   - Role tidak sesuai: tampilkan halaman "Anda tidak memiliki akses" (BUKAN redirect berulang yang bisa menyebabkan loop).
   - Pengguna yang sudah login membuka `/login` atau `/register` -> diarahkan ke berandanya.
6. **Navigasi per role:** ADMIN: Barang, Stok, Laporan, Transaksi. CASHIER: Kasir, Transaksi. MEMBER: Katalog, Pesanan Saya.
7. **Registrasi:** hanya untuk anggota (backend memaksa role `MEMBER`). Klien TIDAK mengirim field `role`. Setelah register sukses: jika respons berisi token, pakai langsung. Jika tidak, panggil login dengan kredensial yang sama. Lalu arahkan ke beranda.
8. **Aturan bisnis tetap di server.** Validasi di klien hanya untuk UX (field wajib, format email, panjang password sesuai aturan backend). Pesan error server (termasuk `details` per field untuk 422) harus ditampilkan, bukan diganti pesan karangan klien.

## FASE 8A

### Struktur (sesuai `ARCHITECTURE.md` bagian 4, hanya yang dibutuhkan sesi ini)
```text
frontend/src/
├── api/
│   ├── client.js        # instance Axios + interceptor
│   ├── errors.js        # parseApiError
│   └── auth.js          # register, login, logout, me
├── context/AuthContext.jsx
├── components/
│   ├── AppLayout.jsx    # header, nav per role, <Outlet/>
│   ├── ProtectedRoute.jsx
│   ├── FormField.jsx    # label + input + pesan error field
│   └── Loading.jsx
├── pages/
│   ├── LoginPage.jsx
│   ├── RegisterPage.jsx
│   ├── ForbiddenPage.jsx
│   ├── NotFoundPage.jsx
│   └── Placeholder.jsx  # dipakai sementara oleh halaman 8B/8C
├── utils/format.js
├── App.jsx
└── main.jsx
```
Jangan membuat file untuk halaman 8B/8C selain `Placeholder.jsx` yang dipakai bersama. Jangan membuat modul API untuk `items`, `orders`, `stock`, `reports` sekarang (tanpa kode mati).

### `api/client.js`
- Instance Axios dengan `baseURL` dari `VITE_API_BASE_URL`, header `Accept: application/json`.
- Interceptor request: tambahkan `Authorization: Bearer <token>` dari `sessionStorage` bila ada.
- Interceptor response: penanganan 401 (keputusan nomor 4) tanpa menyebabkan loop redirect.

### `api/errors.js` - `parseApiError(err)`
Mengembalikan objek seragam `{ status, code, message, details, fieldErrors }`:
- Respons API: ambil dari `error.code`, `error.message`, `error.details`. Untuk 422, `fieldErrors` = `details` per field (ambil pesan pertama tiap field).
- Tanpa respons (jaringan/CORS/server mati): `code = "NETWORK_ERROR"`, `message = "Tidak dapat terhubung ke server."`.
- Respons tak terduga: `code = "UNKNOWN_ERROR"`, pesan umum. Jangan menampilkan data mentah.
Fungsi ini akan dipakai ulang di sesi 8B dan 8C (termasuk membaca `details` 409 `INSUFFICIENT_STOCK`), jadi pastikan `details` diteruskan apa adanya.

### `utils/format.js`
- `formatRupiah(n)` memakai `Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 })`.
- `formatDateTime(isoString)` mengubah timestamp UTC dari API ke zona waktu bisnis (`Intl.DateTimeFormat` dengan `timeZone` dari env), diberi akhiran "WITA" bila zonanya `Asia/Makassar`.

### `context/AuthContext.jsx`
- Menyediakan `{ user, token, loading, login, register, logout }`.
- `logout`: panggil `POST /auth/logout`, lalu SELALU hapus token dan state lokal walaupun request gagal.
- Hook `useAuth()` melempar error jika dipakai di luar provider.

### `ProtectedRoute` dan `AppLayout`
- `ProtectedRoute` menerima daftar role opsional. Belum login -> `/login` (simpan lokasi tujuan agar bisa kembali setelah login). Role salah -> `ForbiddenPage`.
- `AppLayout`: nama aplikasi, nama pengguna dan badge role, navigasi sesuai keputusan nomor 6 dengan penanda tautan aktif, tombol keluar, area konten via `<Outlet/>`.

### `LoginPage` dan `RegisterPage`
- Tombol submit dinonaktifkan dan menampilkan status saat request berjalan (cegah klik ganda).
- Tampilkan pesan error server. Login gagal menampilkan pesan generik dari API. Register menampilkan error per field dari `fieldErrors`.
- Label terhubung ke input, error dibacakan (`aria-live`/`role="alert"`).
- Isi form tidak hilang saat terjadi error validasi.

## VERIFIKASI

Opencode tidak dapat melihat tampilan, jadi laporkan bukti yang bisa diperiksa:

1. `npm run build` lulus (tampilkan ringkasan output). Jika ESLint terpasang, `npm run lint` bersih.
2. Cek CORS dari origin klien, tampilkan output header:
   `curl -i -X OPTIONS http://localhost:8000/api/auth/login -H "Origin: http://localhost:5173" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: content-type,authorization"`
   Harus memuat `Access-Control-Allow-Origin: http://localhost:5173`. Jika gagal, perbaiki konfigurasi CORS backend (itu satu-satunya perubahan backend yang diizinkan) dan jelaskan.
3. Tabel rute akhir (path, role, komponen) dan daftar file yang dibuat.
4. Panduan singkat cara pemilik mencoba: perintah menjalankan backend dan frontend, akun demo mana yang dipakai untuk tiap role (email dari seeder, password dari `SEED_DEFAULT_PASSWORD`), dan apa yang seharusnya terlihat. Pemilik proyek yang memeriksa tampilan di browser.

## PENUTUP

Jangan menambah fitur di luar dokumen (tanpa lupa password, edit profil, mode gelap, internasionalisasi). Setelah selesai:

1. Centang HANYA tiga kotak Fase 8 di `TASKS.md` yang tercakup sesi ini: Axios + `AuthContext`, `ProtectedRoute` + navigasi per role, `LoginPage` + registrasi anggota. Kotak lain tetap kosong.
2. Commit `feat(frontend): add auth, routing, and layout (phase 8a)`.
3. Laporkan ringkasan, temuan bentuk respons backend (langkah awal nomor 3), keputusan, asumsi, output build dan CORS, serta hal yang diragukan.
