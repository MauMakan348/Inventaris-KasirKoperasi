# RULES - Smart Koperasi System

Aturan wajib untuk seluruh kode yang dihasilkan, baik oleh manusia maupun AI (opencode). Jika aturan di sini bertentangan dengan permintaan lain, aturan di sini menang. Jika ada hal yang ambigu, **tanyakan dulu, jangan berasumsi**.

## 1. Aturan Kerja AI

1. Baca `REQUIREMENTS.md`, `PRD.md`, `ARCHITECTURE.md`, dan `RULES.md` sebelum menulis kode. Kerjakan `TASKS.md` **berurutan**, satu tugas atau satu kelompok kecil tugas per sesi.
2. Jangan menambah fitur, endpoint, kolom, atau dependency di luar dokumen. Jika dirasa perlu, usulkan dulu dan tunggu persetujuan.
3. Jangan mengubah dokumen konteks (`markdown/*.md`) kecuali diminta. Centang `[x]` di `TASKS.md` hanya setelah tugas selesai **dan** tes terkait lulus.
4. Jangan menulis ulang file yang sudah berfungsi tanpa alasan jelas. Ubah seperlunya.
5. Setelah setiap tugas: jalankan tes, ringkas apa yang berubah, sebutkan asumsi yang diambil.
6. Jangan pernah mengarang nama paket, method, atau API. Jika tidak yakin, periksa dokumentasi atau kode yang ada.
7. Jangan menyimpan secret, password, atau token di source code.

## 2. Aturan Transaksi & Integritas Data (ACID) - PRIORITAS TERTINGGI

1. **Semua operasi yang mengubah stok atau lebih dari satu tabel wajib berada di dalam `DB::transaction()`**, dan transaksi dimulai di **Service**, bukan di Controller atau Repository.
2. **Dilarang pola check-then-act tanpa kunci.** Jangan membaca stok, memeriksa di PHP, lalu menulis tanpa mengunci baris. Gunakan `lockForUpdate()` (`SELECT ... FOR UPDATE`) sebelum memeriksa stok.
3. **Kunci baris dalam urutan `item_id` menaik** (satu query `ORDER BY id FOR UPDATE`) untuk mencegah deadlock.
4. **Seluruh validasi stok dan eksistensi barang dilakukan setelah kunci diperoleh**, di dalam transaksi.
5. **Rollback harus total.** Exception apa pun dalam transaksi tidak boleh meninggalkan order, order_item, mutasi stok, atau perubahan stok. Jangan menangkap exception di dalam transaksi lalu melanjutkan (jangan menelan error). Biarkan exception keluar dari closure transaksi.
6. Pertahankan `CHECK (stock >= 0)` di database sebagai pertahanan terakhir. Jangan menghapusnya agar tes lolos.
7. Setiap perubahan stok wajib menulis `stock_movements` (`stock_before`, `stock_after`) di transaksi yang sama.
8. Harga, subtotal, total, dan kembalian **selalu dihitung server** dari database. Abaikan nilai tersebut dari klien.
9. Gabungkan baris keranjang dengan `item_id` sama sebelum validasi.
10. Gunakan retry deadlock bawaan Laravel (`DB::transaction($callback, 3)`). Dilarang memakai `sleep()` atau lock buatan di level aplikasi sebagai pengganti kunci database.
11. Jangan memakai cache untuk nilai stok pada jalur transaksi.

## 3. Aturan Arsitektur

1. Ikuti lapisan: Route -> Controller -> Service -> Repository -> Database. Lapisan hanya boleh memanggil lapisan tepat di bawahnya.
2. **Controller:** tipis. Hanya menerima request, memanggil service, mengembalikan Resource. Tanpa aturan bisnis dan tanpa query.
3. **Service:** semua aturan bisnis dan batas transaksi.
4. **Repository:** semua query. Gunakan Eloquent atau Query Builder dengan binding parameter. **Dilarang** menyusun SQL dengan string concatenation dari input pengguna. `DB::raw` hanya untuk ekspresi tetap tanpa input pengguna.
5. Validasi bentuk input di **Form Request**. Aturan bisnis (stok cukup, kepemilikan) di Service.
6. Otorisasi role lewat middleware `EnsureRole`. Kepemilikan data diperiksa di sisi server.
7. Klien React tidak boleh berisi aturan bisnis penentu (harga, stok). Validasi di klien hanya untuk UX.

## 4. Aturan Penamaan & Gaya Kode

- **Bahasa kode:** Inggris (nama class, method, variabel, kolom, kode error). **Bahasa pesan ke pengguna:** Indonesia.
- Backend mengikuti PSR-12 dan konvensi Laravel:
  - Class: `PascalCase` (`OrderService`). Method dan variabel: `camelCase`.
  - Tabel: `snake_case` jamak (`order_items`). Kolom: `snake_case`.
  - Enum: `PascalCase` class, case `UPPER_SNAKE` sesuai nilai di database.
  - Controller: `{Resource}Controller`. Form Request: `{Action}{Resource}Request`.
- Frontend:
  - Komponen dan file halaman: `PascalCase.jsx`. Hook: `useXxx`. Variabel dan fungsi: `camelCase`.
  - Semua pemanggilan API berada di `src/api/`, bukan tersebar di komponen.
- Satu fungsi satu tanggung jawab. Hindari method lebih dari sekitar 40 baris.
- Tanpa kode mati, `dd()`, `dump()`, `console.log` debug, atau TODO tanpa penjelasan.
- Komentar hanya untuk menjelaskan *mengapa*, terutama pada bagian penguncian dan transaksi.

## 5. Aturan API

1. Semua respons JSON. Sukses: `{ "data": ... }`. Error: `{ "error": { "code", "message", "details?" } }`.
2. Gunakan status code dan `error.code` sesuai `REQUIREMENTS.md` bagian 8.
3. Pesan error tidak boleh memuat SQL, stack trace, nama tabel, atau path file. Aktifkan `APP_DEBUG=false` di luar development.
4. Semua endpoint selain `/auth/register` dan `/auth/login` wajib terautentikasi.
5. Tidak ada pagination, pencarian, atau fitur lain yang tidak tertulis di dokumen.

## 6. Aturan Keamanan

1. Password di-hash dengan bcrypt, minimal 8 karakter. Registrasi publik hanya boleh membuat `MEMBER`.
2. Jangan menampilkan perbedaan pesan antara "email tidak ada" dan "password salah". Gunakan pesan generik.
3. Rate limit pada login.
4. Gunakan *mass assignment protection* (`$fillable`). Field seperti `role`, `stock`, `price` tidak boleh ikut ter-assign dari input yang tidak semestinya.
5. File `.env` tidak boleh masuk repository. Sediakan `.env.example` tanpa nilai rahasia.
6. CORS hanya mengizinkan origin klien yang dikonfigurasi.

## 7. Aturan Pengujian

1. Setiap endpoint memiliki tes jalur sukses dan jalur gagal.
2. Wajib ada tes untuk setiap skenario HP-01 sampai HP-07 di `PRD.md`.
3. Tes rollback wajib membuktikan: setelah checkout gagal, **stok semua barang, jumlah baris `orders`, `order_items`, dan `stock_movements` tidak berubah**.
4. Tes fitur memakai database PostgreSQL khusus testing (bukan SQLite) agar perilaku `FOR UPDATE` dan `CHECK` sama dengan production.
5. Skrip `scripts/concurrency-test.mjs` membuktikan HP-02, HP-03, dan HP-04 terhadap server yang berjalan dengan banyak worker.
6. Tes harus dapat diulang dengan satu perintah yang tertulis di `README.md`.

## 8. Aturan Git & Dokumentasi

- Commit kecil dan sering, satu commit per tugas atau kelompok tugas di `TASKS.md`.
- Format pesan commit: `type(scope): ringkasan` (contoh: `feat(order): add all-or-nothing checkout`). Tipe: `feat`, `fix`, `test`, `docs`, `refactor`, `chore`.
- `README.md` wajib memuat: prasyarat, instalasi backend dan frontend, konfigurasi `.env`, migrasi dan seeder, cara menjalankan server dengan banyak worker, cara menjalankan tes, dan akun demo.
- Dokumen konteks diperbarui jika keputusan desain berubah (dengan persetujuan).
