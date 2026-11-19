# Ketentuan Fase 9 - Pengujian Konkurensi & Verifikasi

Instruksi rinci untuk fase yang sedang dikerjakan. File ini ditimpa setiap ganti fase atau sesi.

Tujuan fase ini: **membuktikan dengan request paralel nyata** bahwa checkout aman terhadap race condition (HP-02, HP-03, HP-04, serta varian All-or-Nothing di bawah beban). Tes PHPUnit sebelumnya berjalan dalam satu koneksi, jadi belum membuktikan perilaku saat banyak request berebut stok.

Kerjakan HANYA Fase 9. Jangan mengerjakan Fase 10 (kecuali bagian README yang disebut di bawah). Jangan mengubah kode aplikasi (backend maupun frontend) kecuali yang disebut eksplisit di LANGKAH AWAL atau di uji kontrol negatif sementara (yang TIDAK boleh di-commit).

Bagian dokumen yang relevan: `PRD.md` bagian 3.2 dan 4 (HP-01 sampai HP-07). `ARCHITECTURE.md` bagian 6.1, 9, 10. `RULES.md` bagian 2 dan 7.

Jika isi file ini bertentangan dengan dokumen di `markdown/`, hentikan pekerjaan dan tanyakan dulu.

## LANGKAH AWAL

Kerjakan berurutan. Langkah yang mengubah file diberi commit sendiri.

1. Commit TERPISAH perubahan milik pemilik proyek (`markdown/ketentuan.md`) dengan pesan `docs: update phase instructions`. Jangan mengubah isinya.
2. Pastikan `php artisan test`, `npm run lint`, dan `npm run build` lulus.
3. **Lingkungan database.** Laporan 8C menyebut PostgreSQL port 5433 tidak berjalan dan dibuatkan cluster sementara di `/tmp/opencode/pgdata`. Laporkan: (a) apakah ada PostgreSQL lain yang sudah berjalan atau terpasang sebagai service di mesin ini, (b) di mana data `smart_koperasi` dev sekarang berada. Jangan memulai cluster kedua pada port yang sudah terpakai. Untuk fase ini boleh memakai cluster yang ada, tetapi catat risikonya (data di `/tmp` bisa hilang) dan sarankan pemilik memakai instalasi PostgreSQL yang stabil untuk demo.
4. **Pesan error 409.** Laporan 8B menampilkan respons nyata "satu atau **beberapa** barang", sedangkan laporan 8C menyebut kode dan `REQUIREMENTS.md` memakai "satu atau **lebih**". Keduanya tidak mungkin benar. Jalankan `grep -rn "satu atau" backend/app backend/tests markdown/` dan tampilkan hasilnya. Samakan `REQUIREMENTS.md` dengan teks yang BENAR-BENAR dikembalikan server (jangan mengubah kode). Commit `docs: align 409 message with server`.
5. **Aturan rentang laporan.** Server memakai selisih hari (`diffInDays > 366` ditolak), sehingga selisih 366 hari diterima, sedangkan klien memakai hitungan inklusif yang lebih ketat satu hari. Server adalah acuan. Ubah validasi rentang di `AdminReportPage` agar sama dengan server (selisih `to - from` maksimal 366 hari) dan perjelas kalimatnya di `REQUIREMENTS.md` bagian 5.5 ("selisih antara `from` dan `to` maksimal 366 hari"). Commit `fix(frontend): align report range validation with server`.
6. **Pembatas laju global.** Jalankan `grep -rn "throttle\|RateLimiter" backend/bootstrap backend/routes backend/app backend/config` dan laporkan apakah ada pembatas laju selain login. Jika ada pembatas laju global pada `/api`, JANGAN mengubahnya diam-diam: laporkan batasnya karena dapat menggagalkan uji beban (respons 429), dan tanyakan keputusan.

## KEPUTUSAN DESAIN (sudah ditetapkan, ikuti dan sebutkan di laporan)

1. **Skrip tunggal, tanpa dependency:** `scripts/concurrency-test.mjs`, modul ES, hanya fitur bawaan Node (fetch global, Node 18+). Dilarang menambah paket npm.
2. **Konfigurasi lewat environment, tanpa kata sandi di kode:**
   - `BASE_URL` (default `http://localhost:8000/api`)
   - `DEMO_PASSWORD` (WAJIB, tanpa default. Jika kosong, skrip berhenti dengan pesan jelas)
   - `ADMIN_EMAIL`, `CASHIER_EMAIL`, `MEMBER_EMAIL` (default email seeder: `admin@example.test`, `cashier1@example.test`, `member1@example.test`)
   - `ROUNDS` (opsional, menimpa jumlah ronde default tiap skenario)
3. **Tidak merusak data yang ada.** Skrip tidak mereset database dan tidak menyentuh barang yang sudah ada. Setiap ronde membuat barang baru lewat `POST /items` (admin) dengan SKU unik (`CT-<runId>-<skenario>-<ronde>`) dan stok awal sesuai skenario. Sisa data uji dibiarkan (tidak ada endpoint hapus). Pemilik me-reset dengan `migrate:fresh --seed` sebelum demo.
4. **Login sekali per run** (tiga akun) lalu memakai token. Login dibatasi 5 percobaan per menit: jika menerima 429 saat login, cetak "Tunggu satu menit lalu ulangi" dan keluar.
5. **Start serentak:** siapkan semua payload dan token lebih dulu, lalu luncurkan seluruh request pada saat yang sama lewat satu gerbang (`await gate`) dan `Promise.allSettled`. Setiap request memakai `AbortSignal.timeout(30000)`.
6. **Klasifikasi hasil.** 201 = sukses. 409 dengan `error.code = "INSUFFICIENT_STOCK"` = gagal yang diharapkan. SEMUA yang lain (5xx, 429, timeout, kesalahan jaringan, kode error lain) = **tak terduga**, dihitung sebagai kegagalan uji dan dicatat.
7. **Verifikasi akhir langsung dari API setelah tiap ronde:** `GET /items/{id}` untuk stok akhir dan `GET /items/{id}/movements` untuk buku besar. Periksa: stok akhir tidak pernah negatif; stok akhir = stok awal - jumlah terjual; jumlah mutasi bertipe `SALE_*` = jumlah request 201; jumlah seluruh `quantity_change` = stok akhir (invarian ledger).
8. **Ronde default** (dapat ditimpa `ROUNDS`): HP-02 = 10 ronde, HP-03 = 5, HP-04 = 5, HP-08 = 5. Balapan bersifat probabilistik, jadi satu kali lolos tidak cukup.
9. **Keluaran:** ringkasan terbaca manusia per skenario dan per ronde (PASS/FAIL, jumlah 201/409/tak terduga, stok akhir, pemenang untuk HP-02), tabel ringkas di akhir, dan kode keluar proses `0` hanya jika SEMUA lolos, selain itu `1`. Pesan keluaran berbahasa Indonesia.
10. **Persyaratan server:** skrip mencetak pengingat di awal bahwa server harus berjalan dengan banyak worker. `php artisan serve` biasa melayani satu request sekali jalan sehingga uji tidak bermakna. Catatan platform: `PHP_CLI_SERVER_WORKERS` tidak didukung di Windows (gunakan WSL, Laragon, atau Nginx + PHP-FPM).

## FASE 9

### Skenario dalam `scripts/concurrency-test.mjs`

**HP-02 - rebutan stok terakhir (POS vs ONLINE).** Barang stok 1. Satu request POS (kasir, CASH dengan uang cukup) dan satu request ONLINE (anggota, TRANSFER), masing-masing kuantitas 1, diluncurkan serentak. Harapan: tepat satu 201 dan satu 409 `INSUFFICIENT_STOCK`, stok akhir 0, tepat satu mutasi `SALE_*`. Catat channel pemenang tiap ronde (informasi: seharusnya bervariasi).

**HP-03 - stok 5, 20 request paralel.** Barang stok 5, 20 request kuantitas 1 (selang-seling kasir dan anggota). Harapan: tepat 5 respons 201, 15 respons 409, nol tak terduga, stok akhir 0, tepat 5 mutasi `SALE_*`.

**HP-04 - urutan barang berlawanan (deadlock).** Dua barang A dan B masing-masing stok 1000. 10 request dengan keranjang [A, B] dan 10 request dengan keranjang [B, A] (kuantitas 1 per baris), serentak. Harapan: 20 respons 201, nol tak terduga (terutama nol 5xx), stok akhir A dan B masing-masing 980, ledger konsisten.

**HP-08 (tambahan) - All-or-Nothing di bawah beban.** Barang A stok 1000 dan barang B stok 1. 10 request paralel, masing-masing keranjang berisi A x1 dan B x1. Harapan: tepat 1 respons 201 dan 9 respons 409; stok akhir **A = 999** (bukan 990: pengurangan A pada transaksi yang gagal wajib ter-rollback), stok akhir B = 0, mutasi `SALE_*` tepat 2 (satu untuk A, satu untuk B), ledger konsisten.

### README
Tambahkan bagian pendek di `README.md` (jika belum ada file, buat minimal): cara menjalankan server dengan banyak worker (`PHP_CLI_SERVER_WORKERS=8 php artisan serve`, catatan Windows), variabel environment skrip, perintah menjalankan skrip, dan arti kode keluarnya. Pelengkapan README lain dikerjakan di Fase 10.

### PRD
Tambahkan satu baris HP-08 pada tabel di `PRD.md` bagian 4 (skenario di atas). Perubahan dokumen ini disetujui, hanya itu.

## VERIFIKASI (sertakan perintah dan output asli di laporan)

1. Jalankan server: `PHP_CLI_SERVER_WORKERS=8 php artisan serve` (tampilkan perintahnya).
2. Jalankan skrip penuh dengan `DEMO_PASSWORD` dari `backend/.env` (jangan menampilkan nilai sandinya di laporan). Tampilkan seluruh output, termasuk tabel ringkas dan kode keluar. Jalankan **dua kali berturut-turut** (tunggu satu menit di antaranya karena pembatas login) untuk menunjukkan hasil yang stabil.
3. Setelah skrip, jalankan query konsistensi pada database dev dan tampilkan hasilnya (semuanya harus **0 baris**):

```sql
-- stok item harus sama dengan total mutasi
SELECT i.id FROM items i
LEFT JOIN stock_movements m ON m.item_id = i.id
GROUP BY i.id, i.stock
HAVING i.stock <> COALESCE(SUM(m.quantity_change), 0);

-- tidak boleh ada stok negatif
SELECT id, stock FROM items WHERE stock < 0;

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

4. **Uji kontrol negatif (WAJIB, untuk membuktikan skrip memang mampu mendeteksi race).** Pada branch sementara `tmp/negative-control` (JANGAN di-commit ke branch utama): ubah sementara `ItemRepository::lockItemsForUpdate` menjadi query TANPA `FOR UPDATE` (guard transaksi dibiarkan). Jalankan ulang HP-03 (dan HP-08 bila memungkinkan) dan tampilkan hasilnya. Harapan: skrip **GAGAL** (kelebihan penjualan, ledger tidak konsisten, atau respons 5xx karena CHECK database). Setelah itu kembalikan: `git checkout` branch utama, hapus branch sementara, dan tunjukkan bahwa `git status` bersih dan `git diff main` kosong. Jika skrip tetap LOLOS tanpa kunci, uji dianggap tidak valid: laporkan dan perbaiki skrip (misalnya perbesar paralelisme atau jumlah ronde) sebelum melanjutkan.
5. `php artisan test` hijau sesudah branch kembali ke utama.
6. Laporkan juga durasi tiap skenario dan hal yang mungkin membatasi paralelisme (jumlah worker, pembatas laju).

## PENUTUP

Jangan menambah fitur atau endpoint (tanpa endpoint hapus, tanpa idempotency key, tanpa mengubah logika checkout). Setelah selesai:

1. Centang SATU PER SATU kotak Fase 9 di `TASKS.md` yang terverifikasi.
2. Commit `test(concurrency): add parallel checkout verification script (phase 9)` (skrip, README, dan baris PRD HP-08; commit perbaikan langkah awal sudah terpisah).
3. Laporkan ringkasan, keputusan, asumsi, seluruh output verifikasi (skrip dua kali, query, uji kontrol negatif, tes PHPUnit), dan hal yang diragukan atau tidak sempat dikerjakan.
