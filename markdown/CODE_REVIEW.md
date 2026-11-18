# Code Review Checklist - Smart Koperasi System

## 1. Tujuan Review

Memeriksa kualitas implementasi agar sesuai dengan `REQUIREMENTS.md`, `PRD.md`, `ARCHITECTURE.md`, dan `RULES.md`. Fokus: integritas stok, transaksi ACID, penanganan konkurensi, keamanan, dan kualitas API.

## 2. Requirement Compliance

- [ ] Klien menggunakan React, backend Laravel, database PostgreSQL, data JSON.
- [ ] Seluruh endpoint pada `REQUIREMENTS.md` bagian 5 tersedia dengan method dan path yang benar.
- [ ] Nama field, enum, dan status sesuai spesifikasi.
- [ ] Hak akses tiap endpoint sesuai tabel role.

## 3. Data Model dan Database

- [ ] Semua tabel dan kolom sesuai `REQUIREMENTS.md` bagian 4.
- [ ] PK, FK (RESTRICT), UNIQUE, dan indeks sudah didefinisikan.
- [ ] `CHECK (stock >= 0)` aktif di `items`. CHECK lain (price, quantity, enum) aktif.
- [ ] Nilai uang disimpan sebagai integer. Timestamp dalam UTC.
- [ ] Tidak ada SQL hasil concatenation dari input pengguna. `DB::raw` tidak memuat input pengguna.
- [ ] `$fillable` aman. `role` dan `stock` tidak dapat diisi lewat mass assignment dari input publik.

## 4. Business Logic

### 4.1 Checkout (kritis)
- [ ] Seluruh proses berada dalam satu `DB::transaction` yang dimulai di Service.
- [ ] Baris item dikunci `FOR UPDATE` **sebelum** stok diperiksa.
- [ ] Penguncian dalam urutan `item_id` menaik.
- [ ] Item duplikat digabung sebelum validasi.
- [ ] Semua barang bermasalah dilaporkan sekaligus pada error 409.
- [ ] Item tidak ditemukan -> 404 tanpa perubahan data.
- [ ] Harga dan total dihitung dari database. Input harga klien diabaikan.
- [ ] Snapshot `item_name` dan `unit_price` tersimpan.
- [ ] Mutasi stok tercatat dengan `stock_before` dan `stock_after` yang benar.
- [ ] Tidak ada `try/catch` yang menelan exception di dalam transaksi.
- [ ] Channel ditentukan dari role. ADMIN ditolak.
- [ ] Validasi pembayaran CASH dan perhitungan kembalian benar.

### 4.2 Restock & Opname
- [ ] Menggunakan transaksi dan penguncian baris.
- [ ] Mutasi `RESTOCK` dan `OPNAME` tercatat. Selisih opname benar (boleh negatif).
- [ ] Validasi kuantitas dan `actual_stock` benar.

### 4.3 Barang, Riwayat, Laporan
- [ ] `PUT /items/{id}` tidak mengubah stok.
- [ ] MEMBER hanya melihat transaksi miliknya. Transaksi orang lain -> 404.
- [ ] Laporan memakai agregasi SQL dan batas tanggal Asia/Makassar yang benar.

## 5. API Contract

- [ ] Struktur respons sukses dan error konsisten (`data` / `error.code`, `message`, `details`).
- [ ] HTTP status sesuai `REQUIREMENTS.md` bagian 8.
- [ ] Error tidak membocorkan SQL, stack trace, atau path internal. `APP_DEBUG=false` di luar development.
- [ ] `GET /items` menampilkan stok terkini dari database.

## 6. Arsitektur & Kualitas Kode

- [ ] Controller tipis. Aturan bisnis ada di Service. Query ada di Repository.
- [ ] Validasi bentuk input ada di Form Request.
- [ ] Penamaan sesuai `RULES.md`. Tidak ada kode mati, `dd()`, `console.log` debug, atau TODO tanpa penjelasan.
- [ ] Tidak ada duplikasi logika transaksi atau validasi yang tidak perlu.
- [ ] Komentar menjelaskan *mengapa* pada bagian penguncian dan transaksi.
- [ ] Panggilan API frontend terpusat di `src/api/`.

## 7. Security & Reliability

- [ ] Password di-hash. Pesan login generik. Rate limit login aktif.
- [ ] Registrasi publik hanya menghasilkan `MEMBER`.
- [ ] Semua endpoint (kecuali register/login) memerlukan token. Middleware role terpasang.
- [ ] Tidak ada secret di source code. `.env` tidak ter-commit. `.env.example` tersedia.
- [ ] CORS dibatasi ke origin klien.
- [ ] Tidak ada nilai stok yang hanya disimpan di memori atau cache.

## 8. Frontend

- [ ] Tombol checkout dinonaktifkan selama request berjalan (anti klik ganda).
- [ ] Error 409 menampilkan daftar barang bermasalah dengan jelas. Keranjang tidak hilang.
- [ ] Route dilindungi per role. Token tidak di-hardcode.
- [ ] Klien tidak menjadi sumber kebenaran harga atau stok.

## 9. Test Evidence

- [ ] Ada tes jalur sukses dan gagal untuk setiap endpoint.
- [ ] Ada tes HP-01 sampai HP-07 sesuai `PRD.md`.
- [ ] Tes rollback membuktikan stok dan jumlah baris `orders`, `order_items`, `stock_movements` tidak berubah setelah gagal.
- [ ] Tes memakai PostgreSQL, bukan SQLite.
- [ ] `scripts/concurrency-test.mjs` membuktikan HP-02, HP-03, HP-04 dan outputnya terdokumentasi.
- [ ] Hasil pengujian dapat diulang dengan langkah di `README.md`.

## 10. Review Findings

| ID | File/Bagian | Temuan | Tingkat | Rekomendasi | Status |
|---|---|---|---|---|---|
| CR-01 | - | - | - | - | Open |

Tingkat prioritas:

- **Critical:** stok bisa negatif, rollback tidak total, race condition, atau celah keamanan serius.
- **High:** aturan bisnis utama atau endpoint utama gagal, otorisasi bocor.
- **Medium:** validasi, respons API, atau kualitas kode bermasalah dengan dampak terbatas.
- **Low:** keterbacaan, konsistensi, perbaikan kecil.

## 11. Review Summary

- **Status:** Pending / Approved / Approved with Changes / Rejected
- **Jumlah temuan Critical:**
- **Jumlah temuan High:**
- **Jumlah temuan Medium:**
- **Jumlah temuan Low:**
- **Catatan reviewer:**

Kode hanya layak disetujui apabila seluruh alur utama berjalan, acceptance criteria terpenuhi, skenario HP-01 sampai HP-07 lulus, dan tidak ada temuan Critical atau High yang belum diselesaikan.
