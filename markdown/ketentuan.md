# Ketentuan Fase 10 - Dokumentasi, Bukti, dan Persiapan Presentasi

Instruksi rinci untuk fase yang sedang dikerjakan. File ini ditimpa setiap ganti fase atau sesi.

Ini fase terakhir. Tidak ada fitur baru. Tujuannya: repositori bersih, dokumen sinkron dengan kode, bukti tersimpan permanen di repositori, skenario demo siap, dan self-review yang jujur.

Kerjakan HANYA Fase 10. Jangan mengubah logika aplikasi (backend maupun frontend). Jika menemukan bug atau ketidaksesuaian antara dokumen dan kode, **laporkan** dan perbaiki dokumennya bila kode yang benar. Perbaikan kode hanya untuk temuan Critical atau High di self-review, dengan commit `fix(...)` sendiri dan tes yang membuktikannya.

Bagian dokumen yang relevan: seluruh dokumen di `markdown/` (fase ini menyinkronkannya), terutama `TASKS.md` Fase 10 dan Definition of Done, `CODE_REVIEW.md`, `RULES.md` bagian 6, 7, 8.

Jika isi file ini bertentangan dengan dokumen di `markdown/`, hentikan pekerjaan dan tanyakan dulu.

## LANGKAH AWAL

1. Commit TERPISAH perubahan milik pemilik proyek (`markdown/ketentuan.md`) dengan pesan `docs: update phase instructions`. Jangan mengubah isinya.
2. Pastikan `php artisan test`, `npm run lint`, `npm run build` lulus.
3. **Pesan 409, buktikan dengan keluaran asli.** Laporan 8B (respons server nyata) menulis "satu atau **beberapa** barang", sedangkan laporan 8C dan 9 menyebut "satu atau **lebih**", dan instruksi sebelumnya untuk menampilkan hasil `grep` tidak dipenuhi. Jalankan dan TAMPILKAN keluarannya:
   `grep -rn "satu atau" backend/app backend/tests frontend/src markdown/`
   lalu satu request nyata yang memicu 409 dan salin `error.message` apa adanya. Samakan `REQUIREMENTS.md` (dan teks lain yang menyalin kalimat itu) dengan yang BENAR-BENAR dikembalikan server. Commit `docs: align 409 message with server` bila ada perubahan.
4. **Port database.** Laporkan nilai `DB_PORT` di `backend/.env.example` dan `phpunit.xml`. Cluster dev berjalan di port 5433 pada `/tmp`, sedangkan PostgreSQL sistem memakai 5432. Jangan mengubah konfigurasi lingkungan mesin. Untuk README, port harus ditulis sebagai nilai yang dapat diubah, dengan default standar PostgreSQL 5432.

## KEPUTUSAN DESAIN (sudah ditetapkan, ikuti dan sebutkan di laporan)

1. **Lokasi dokumen konteks tetap `markdown/`** (mengikuti struktur contoh dosen). Jangan menyalin ke root. README di root menjadi penunjuk ke dokumen tersebut.
2. **Bukti permanen disimpan di repositori** pada folder `evidence/` di root (log di `/tmp` akan hilang). Perbarui pohon direktori di `ARCHITECTURE.md` agar memuat `evidence/` dan `markdown/DEMO.md`.
3. **Tidak ada rahasia di bukti.** Sebelum commit, periksa seluruh isi `evidence/` dengan `grep -rniE "token|password|bearer|secret" evidence/` dan sunting (ganti dengan `[REDACTED]`) bila ada.
4. **Self-review harus berbasis bukti.** `CODE_REVIEW.md` diisi dengan menelusuri kode sungguhan, bukan menyetujui semua kotak. Setiap kotak yang dicentang harus punya rujukan singkat `file:baris` atau nama tes. Kotak yang tidak dapat dibuktikan dibiarkan kosong dengan catatan.
5. **Berkas proses milik pemilik** (`markdown/ketentuan.md`, `markdown/prompt.txt`, `AGENTS.md`) tidak diubah, tidak dihapus, dan tidak dihitung sebagai "file tidak terpakai". Pemilik yang menentukan apakah disertakan.

## FASE 10

### A. Kebersihan repositori
- Jalankan `git ls-files` dan periksa: tidak ada `.env`, `node_modules`, `vendor`, `dist`, `__pycache__`, file `.log`, atau database terlacak. Perbaiki `.gitignore` bila perlu.
- Cari kode mati dan sisa debug: `grep -rnE "dd\(|dump\(|var_dump|console\.log|TODO|FIXME" backend/app backend/routes backend/database frontend/src scripts` dan laporkan setiap temuan beserta tindakannya.
- Cari kata sandi atau rahasia tertulis: `git grep -nIE "password\s*=|secret|api[_-]?key" -- . ':!*.lock'` dan jelaskan tiap temuan (yang sah, misalnya nama variabel atau placeholder, harus ditandai sah).
- Periksa file atau komponen yang tidak dirujuk (misalnya export tidak terpakai) pada `frontend/src` dan `backend/app`. Hapus hanya yang jelas tidak terpakai dan lulus build serta tes sesudahnya. Commit `chore: remove unused files`.

### B. Sinkronisasi dokumen (perubahan dokumen ini DISETUJUI pemilik proyek)
Bandingkan setiap dokumen dengan kode dan tes yang ada, lalu perbaiki dokumennya. Commit `docs: final sync with implementation`. Wajib dicakup:
- `ARCHITECTURE.md` bagian 4: pohon direktori disamakan dengan `git ls-files` (hooks, utils, components frontend, file backend yang sebenarnya, `scripts/`, `evidence/`, `markdown/DEMO.md`).
- `ARCHITECTURE.md` bagian 10 dan `README.md`: perintah server multi-worker harus `PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload` (Laravel 13 mengabaikan variabel tanpa `--no-reload`), dengan catatan Windows.
- `REQUIREMENTS.md`: endpoint, respons, kode error, dan contoh sesuai kode (termasuk bentuk respons restock, opname, laporan, dan `details` error).
- `PRD.md`: Acceptance Criteria dan tabel HP sesuai tes yang ada (sebut nama kelas tes bila memudahkan penelusuran).
- `TASKS.md`: semua kotak terverifikasi tercentang. Kotak "Verifikasi manual alur tiap role dari ujung ke ujung" dan sisa kotak Fase 10 yang bergantung pemilik JANGAN dicentang.
- `RULES.md`: ubah hanya bila praktik nyata menyimpang dari aturan tertulis (jelaskan alasannya di laporan).
Buat tabel di laporan: dokumen, ketidaksesuaian yang ditemukan, tindakan. Ketidaksesuaian yang berasal dari bug kode dilaporkan, bukan disembunyikan lewat penyesuaian dokumen.

### C. README.md
Lengkapi, ringkas dan dapat diikuti orang lain dari nol:
- Deskripsi singkat dan tautan ke dokumen di `markdown/`.
- Prasyarat: versi PHP dan ekstensi (termasuk `pdo_pgsql`), Composer, Node (versi minimal sesuai skrip), PostgreSQL.
- Instalasi backend: `composer install`, salin `.env.example` ke `.env`, `php artisan key:generate`, konfigurasi database (host, port yang dapat diubah, dua database `smart_koperasi` dan `smart_koperasi_test`), `SEED_DEFAULT_PASSWORD`, `FRONTEND_URL`, `BUSINESS_TIMEZONE`.
- Migrasi dan seeder: `php artisan migrate:fresh --seed`.
- Menjalankan backend (mode biasa dan mode multi-worker), menjalankan frontend (`VITE_API_BASE_URL`).
- Akun demo (email dari seeder, kata sandi dari `SEED_DEFAULT_PASSWORD`, TANPA menuliskan nilai kata sandinya).
- Menjalankan tes backend (`php artisan test`) dan skrip konkurensi (rujuk bagian yang sudah ada).
- Ringkasan arsitektur 3 tier dan keputusan kunci (transaksi All-or-Nothing, `FOR UPDATE` berurutan, `CHECK stock >= 0`).
- Batasan yang diketahui (lihat bagian F, temuan yang diterima).
Uji README: ikuti langkahnya secara harfiah pada database kosong (`migrate:fresh --seed`, jalankan backend, jalankan `curl` login) dan laporkan bila ada langkah yang gagal atau tidak jelas.

### D. Bukti permanen di `evidence/`
Salin dan rapikan (dengan penjelasan satu kalimat di awal tiap file bila perlu) lalu commit `docs: add verification evidence`:
- `evidence/concurrency-run-clean.txt`: keluaran lengkap skrip pada database baru (salin dari `/tmp/opencode/concurrency-main-run-3-clean.txt` bila masih ada. Jika sudah hilang, jalankan ulang skrip dan simpan keluarannya).
- `evidence/concurrency-run-repeat.txt`: satu run lagi (jeda minimal satu menit dari run sebelumnya) untuk menunjukkan stabilitas.
- `evidence/concurrency-negative-control.txt`: keluaran uji kontrol negatif (salin dari `/tmp/opencode/concurrency-negative-control.txt` bila ada). Jika sudah hilang, jangan mengulang di branch utama. Cukup nyatakan di `evidence/README.md` bahwa log asli hilang, atau ulangi prosedur aman di branch sementara seperti Fase 9 dan kembalikan seperti semula.
- `evidence/sql-consistency.txt`: perintah dan hasil empat query konsistensi dari `ketentuan` Fase 9 pada database sesudah run bersih (semua harus 0 baris).
- `evidence/sample-409.json`: satu respons 409 nyata dengan beberapa barang bermasalah.
- `evidence/sample-stock-movements.txt`: contoh mutasi stok satu barang (penjualan POS, penjualan online, restock, opname) dari `GET /items/{id}/movements`.
- `evidence/README.md`: daftar isi folder dan cara tiap file dihasilkan. Sebutkan juga folder `evidence/screenshots/` yang diisi pemilik secara manual (tangkapan layar panel 409 di UI, struk, laporan) dan cantumkan daftar tangkapan layar yang disarankan.

### E. `markdown/DEMO.md` - skenario presentasi
Tulis naskah demo untuk sekitar 10 sampai 12 menit, berfokus pada **non happy path** (sesuai ketentuan tugas). Setiap langkah memuat: perintah atau tindakan, hasil yang diharapkan, dan satu kalimat poin bicara. Urutan:
1. Persiapan sebelum tampil (checklist): reset database `migrate:fresh --seed`, server multi-worker (`--no-reload`), frontend berjalan, akun demo siap, tab browser per role, tabel barang seeder dengan stok awal dan harga (ambil dari seeder yang sebenarnya), dan rencana cadangan bila ada kegagalan (tangkapan layar dan `evidence/`).
2. Konsep 70/30 dan arsitektur 3 tier (30 detik, tanpa kode).
3. Happy path singkat: satu checkout kasir sukses beserta struk (1 menit).
4. **HP-01 di UI:** isi keranjang kasir, habiskan stok satu barang lewat checkout online anggota (perintah `curl` atau tab anggota), tekan Checkout, tunjukkan panel 409 dan buktikan stok barang lain tidak berubah (tabel mutasi atau riwayat).
5. **HP-02 dan HP-03 lewat skrip:** jalankan `scripts/concurrency-test.mjs` (atau tampilkan `evidence/` bila waktu terbatas) dan jelaskan hasil: tepat satu pemenang, tepat 5 dari 20.
6. **HP-08:** pengurangan stok pada transaksi gagal ter-rollback di bawah beban.
7. **Uji kontrol negatif:** tunjukkan `evidence/concurrency-negative-control.txt` sebagai bukti skrip mampu mendeteksi oversell.
8. Bukti database: jalankan query konsistensi (hasil 0 baris) dan tunjukkan `CHECK stock >= 0` menolak `UPDATE` negatif.
9. Dokumen konteks Markdown sebagai "otak" AI (REQUIREMENTS, ARCHITECTURE, RULES, TASKS) dan alur kerja per fase (1 menit).
10. Penutup dan antisipasi pertanyaan: daftar 6 sampai 8 pertanyaan yang mungkin diajukan penguji beserta jawaban singkat berbasis bukti (misalnya: mengapa `FOR UPDATE` dan bukan optimistic locking, apa yang mencegah deadlock, mengapa 409 bukan 400, apa yang terjadi bila server mati di tengah transaksi, apa batas sistem ini).
Sertakan perkiraan waktu per langkah.

### F. Self-review `CODE_REVIEW.md`
Isi `markdown/CODE_REVIEW.md` sesuai keputusan nomor 4:
- Telusuri kode nyata untuk bagian 4.1 (Checkout), 4.2, 6, 7, dan 8 (frontend) minimal; bagian lain berdasarkan tes dan konfigurasi.
- Setiap kotak tercentang punya rujukan `file:baris` atau nama tes.
- Tabel temuan (bagian 10) berisi temuan NYATA dengan tingkat Critical, High, Medium, Low, termasuk batasan yang diterima. Contoh yang layak diperiksa dan dilaporkan jujur (jangan dipaksakan bila tidak ada): daftar transaksi tanpa pagination, tanpa idempotency key untuk checkout, token di `sessionStorage`, tidak ada uji otomatis UI, tidak ada pembatas laju global pada `/api` selain login, batas rentang laporan, cluster dev sementara di `/tmp`.
- Isi bagian 11 (ringkasan) dengan jumlah temuan per tingkat dan status. Gunakan status `Approved with Changes` atau `Pending` bila masih ada Critical atau High yang belum selesai. Jangan memaksakan `Approved`.
- Perbaiki temuan Critical atau High dengan commit `fix(...)` terpisah dan tes pembuktinya.

### G. Pemetaan Definition of Done
Di laporan (bukan file baru), buat tabel dari 7 butir Definition of Done di `TASKS.md` ke bukti konkret (nama tes, file di `evidence/`, atau perintah) dan status (Terpenuhi, Sebagian, atau Belum). Jelaskan butir yang "Sebagian" atau "Belum" secara jujur.

## VERIFIKASI (sertakan perintah dan output asli di laporan)

1. `php artisan test`, `npm run lint`, `npm run build` hijau di akhir.
2. `git status` bersih dan `git log --oneline` menampilkan seluruh commit fase ini.
3. Keluaran pemeriksaan kebersihan (bagian A) dan pemeriksaan rahasia pada `evidence/`.
4. Hasil uji README (langkah harfiah pada database kosong).
5. Dry-run perintah di `DEMO.md` yang dapat dijalankan tanpa browser (login, `curl` checkout sukses dan 409, skrip konkurensi sekali jalan, query konsistensi). Laporkan bila ada perintah yang gagal dan perbaiki dokumennya.
6. Tabel sinkronisasi dokumen (bagian B) dan tabel Definition of Done (bagian G).

## PENUTUP

Jangan menambah fitur atau endpoint. Setelah selesai:

1. Centang kotak Fase 10 `TASKS.md` yang terverifikasi oleh opencode. Kotak yang membutuhkan pemilik (verifikasi manual di browser, latihan presentasi) tetap kosong.
2. Pastikan semua perubahan sudah ter-commit dengan pesan sesuai bagian di atas.
3. Laporkan ringkasan, tabel-tabel yang diminta, seluruh output verifikasi, daftar hal yang perlu dikerjakan pemilik sebelum presentasi, dan hal yang diragukan.
