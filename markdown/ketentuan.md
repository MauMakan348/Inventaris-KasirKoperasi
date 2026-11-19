# Ketentuan Fase 8C - Frontend: Katalog Anggota dan Halaman Admin

Instruksi rinci untuk fase yang sedang dikerjakan. File ini ditimpa setiap ganti fase atau sesi.

Fase 8 dipecah menjadi tiga sesi. Sesi 8A dan 8B selesai. Sesi ini adalah **8C**, sesi terakhir Fase 8:

- Katalog dan pemesanan online anggota.
- Halaman admin: barang, stok (restock, opname, riwayat mutasi), laporan penjualan.
- Daftar verifikasi manual per role untuk pemilik proyek.

Kerjakan HANYA sesi 8C. Jangan mengerjakan Fase 9. Jangan mengubah kode backend. Jika kontrak API ternyata berbeda dari dokumen, laporkan, jangan mengubah backend.

Bagian dokumen yang relevan: `REQUIREMENTS.md` bagian 5.2, 5.3, 5.4, 5.5, 7, 8. `PRD.md` bagian 1 (BR-01 sampai BR-11), 3.3, 3.4, 3.5. `ARCHITECTURE.md` bagian 3 (Client), 4, 6.2, 6.3, 6.4. `RULES.md` bagian 3 poin 7, bagian 4 (frontend), bagian 5.

Jika isi file ini bertentangan dengan dokumen di `markdown/`, hentikan pekerjaan dan tanyakan dulu.

## LANGKAH AWAL

1. Commit TERPISAH perubahan milik pemilik proyek (`markdown/ketentuan.md`) dengan pesan `docs: update phase instructions`. Jangan mengubah isinya.
2. Pastikan `php artisan test`, `npm run lint`, dan `npm run build` lulus sebelum mulai.
3. Jalankan `git grep -n "demo-password"` dan laporkan hasilnya. Tidak boleh ada kata sandi di source code (RULES bagian 6). Jika ada, hapus dan commit `fix: remove hardcoded password`.
4. Perbaiki ketidaksesuaian kecil di `REQUIREMENTS.md` bagian 7: contoh pesan error 409 harus sama persis dengan kode ("Stok tidak mencukupi untuk satu atau beberapa barang."). Hanya satu baris itu. Commit `docs: align 409 message with code`.
5. **Cek kontrak API asli SEBELUM menulis klien** (jangan menebak). Dengan token ADMIN lewat `curl`, tampilkan bentuk respons dan nama field untuk: `POST /items`, `PUT /items/{id}`, `POST /items/{id}/restock`, `POST /items/{id}/stock-opname` (termasuk kasus selisih 0 dengan `movement: null`), `GET /items/{id}/movements`, `GET /reports/sales?from&to`, dan contoh error 422 untuk `POST /items` dengan SKU duplikat. Ringkas temuannya di laporan.

## KEPUTUSAN DESAIN (sudah ditetapkan, ikuti dan sebutkan di laporan)

1. **Prinsip sama dengan 8B:** klien bukan sumber kebenaran, tidak memblokir aturan bisnis kecuali batas UX yang disebut jelas. Pesan error server (termasuk `fieldErrors` per field) selalu ditampilkan. Semua pemanggilan API hanya lewat `src/api/`.
2. **Anti klik ganda** untuk SEMUA form dan tombol yang mengubah data (checkout anggota, tambah/ubah barang, restock, opname). Buat hook `useSubmitGuard` (state `submitting` dan `useRef` penjaga) untuk halaman baru. Jangan merombak `PosPage` yang sudah berfungsi.
3. **Katalog anggota (BR-09):** metode pembayaran hanya `TRANSFER` (tanpa pilihan CASH, tanpa input uang). Tampilkan catatan singkat: "Pesanan langsung tercatat dan stok berkurang. Pembayaran dicatat sebagai transfer." Tanpa reservasi dan tanpa batas waktu bayar.
4. **Ubah barang tidak mengubah stok.** Form ubah hanya `sku`, `name`, `price`. Stok ditampilkan read-only dengan petunjuk "Ubah stok lewat menu Stok". Stok awal hanya ada di form tambah.
5. **Opname meminta konfirmasi** sebelum dikirim (`window.confirm`) dengan ringkasan: stok sistem saat ini, stok fisik yang diinput, dan selisihnya. Restock tanpa konfirmasi.
6. **Opname selisih 0:** respons `movement: null`. Tampilkan pesan "Stok sudah sesuai, tidak ada perubahan." (bukan error).
7. **Laporan:** kirim selalu `from` dan `to` eksplisit (jangan mengandalkan default server). Default isian = hari ini pada `VITE_BUSINESS_TIMEZONE`. Validasi UX: `from <= to` dan rentang maksimal 366 hari. Pesan error server tetap ditampilkan.
8. **Label bahasa Indonesia** untuk nilai enum, disimpan terpusat di `utils/labels.js`: channel (`POS` = "Kasir", `ONLINE` = "Online"), pembayaran (`CASH` = "Tunai", `TRANSFER` = "Transfer"), tipe mutasi (`SALE_POS` = "Penjualan Kasir", `SALE_ONLINE` = "Penjualan Online", `RESTOCK` = "Restock", `OPNAME` = "Stock Opname"). Nilai asli dari API tetap dipakai untuk logika.
9. **Tanpa dependency baru, tanpa test framework baru.** CSS biasa.

## FASE 8C

### Struktur yang ditambahkan
```text
frontend/src/
├── api/
│   ├── items.js          # tambah: createItem, updateItem (listItems sudah ada)
│   ├── stock.js          # restock, opname, listMovements
│   └── reports.js        # salesReport
├── hooks/
│   └── useSubmitGuard.js
├── components/
│   ├── ItemPicker.jsx    # daftar barang + pencarian (diekstrak dari PosPage)
│   └── SuccessPanel.jsx  # hanya bila memang mengurangi duplikasi
├── utils/
│   └── labels.js
└── pages/
    ├── CatalogPage.jsx
    ├── AdminItemsPage.jsx
    ├── AdminStockPage.jsx
    └── AdminReportPage.jsx
```
Ganti rute `/catalog`, `/admin/items`, `/admin/stock`, `/admin/reports` dari `Placeholder` ke halaman nyata. Setelah semua rute memakai halaman nyata, **hapus `Placeholder.jsx`** jika tidak terpakai lagi (tanpa kode mati).

Ekstraksi `ItemPicker` dari `PosPage` boleh dilakukan HANYA jika perilaku POS tetap identik (cari, stok habis nonaktif, klik menambah ke keranjang). Lakukan di commit terpisah lebih dulu: `refactor(frontend): extract ItemPicker`, dan pastikan lint dan build lulus. Jika ragu, duplikasikan sebagian kecil markup daripada mengubah POS.

### `CatalogPage` (`/catalog`, hanya MEMBER)
- Pakai ulang `useCart`, `CartTable`, `ErrorAlert`, `StockShortageAlert`, `buildCheckoutPayload`, dan pola pemuatan ulang stok dari `PosPage`.
- Daftar barang dengan pencarian (nama atau SKU), tampilan nama, SKU, harga, stok. Stok 0 = "Habis" dan tombol tambah nonaktif.
- Keranjang dan tombol "Pesan Sekarang" sesuai keputusan nomor 3. Payload: `payment_method = "TRANSFER"` dan `items`, tanpa `amount_paid` dan tanpa harga.
- Setelah sukses (201): panel hasil dari respons server (kode, total, jumlah barang), tombol "Lihat Struk" (`/orders/:id`), "Pesanan Saya" (`/orders`), "Belanja Lagi". Kosongkan keranjang, muat ulang stok.
- Penanganan error sama seperti POS: 409 (daftar semua barang kurang, baris disorot, keranjang tetap, stok dimuat ulang), 404 (barang tidak ditemukan), 422 lainnya, `NETWORK_ERROR` dengan pesan: "Koneksi terputus. Periksa halaman Pesanan Saya sebelum mengulang agar tidak tercatat dua kali."

### `AdminItemsPage` (`/admin/items`, hanya ADMIN)
- Tabel barang: SKU, nama, harga, stok, tombol "Ubah". Tombol "Tambah Barang".
- Form tambah/ubah sebagai panel inline di halaman (bukan modal berlapis): tambah = `sku`, `name`, `price`, `stock` (stok awal >= 0). Ubah = `sku`, `name`, `price` (stok read-only).
- Error 422 ditampilkan per field (misalnya SKU duplikat di field SKU). Isi form tidak hilang saat error.
- Setelah sukses: tutup form, muat ulang daftar, tampilkan pesan sukses singkat. Tanpa hapus barang (di luar ruang lingkup).
- Angka harga dan stok dikirim sebagai integer.

### `AdminStockPage` (`/admin/stock`, hanya ADMIN)
- Pilih barang dari daftar (dropdown atau daftar yang bisa dicari). Tampilkan stok saat ini.
- Form **Restock**: `quantity` (integer >= 1), `note` opsional (maksimal 255).
- Form **Opname**: `actual_stock` (integer >= 0), `note` opsional, dengan konfirmasi (keputusan nomor 5) dan penanganan selisih 0 (keputusan nomor 6).
- Tabel **Riwayat mutasi** barang terpilih (terbaru di atas, dari server): waktu (`formatDateTime`), tipe (label Indonesia), perubahan (tanda + atau -), stok sebelum dan sesudah, nama pelaku, catatan. Mutasi yang punya `order_id` menautkan ke `/orders/:id`.
- Setelah restock/opname sukses: perbarui stok yang tampil dan muat ulang riwayat. Tampilkan pesan sukses.

### `AdminReportPage` (`/admin/reports`, hanya ADMIN)
- Dua input tanggal (`type="date"`) dan tombol "Tampilkan", sesuai keputusan nomor 7.
- Hasil: periode (dengan zona waktu), total transaksi, total pendapatan (`formatRupiah`), tabel per channel (selalu Kasir dan Online), tabel barang terlaris (nama, jumlah terjual, pendapatan).
- Keadaan: memuat, kosong (semua 0 dan "Belum ada penjualan pada periode ini"), error server per field atau umum.

## VERIFIKASI

Opencode tidak dapat melihat tampilan, jadi laporkan bukti yang bisa diperiksa:

1. `npm run lint` bersih dan `npm run build` lulus (tampilkan ringkasan).
2. Hasil cek kontrak API (langkah awal nomor 5), termasuk pengakuan jika ada yang berbeda dari dokumen.
3. Bukti payload checkout anggota tidak memuat harga atau `amount_paid` (skrip Node sekali pakai, tidak di-commit, memakai `buildCheckoutPayload` dengan TRANSFER).
4. Daftar file yang dibuat, diubah, dan dihapus, serta tabel rute akhir.
5. **Daftar periksa end-to-end per role** yang akan dijalankan pemilik di browser. Tulis sebagai tabel (langkah, hasil yang diharapkan) untuk ADMIN, CASHIER, dan MEMBER, mencakup: login dan navigasi, tambah dan ubah barang, restock dan opname (termasuk selisih 0), riwayat mutasi, checkout kasir (sukses dan 409), pesanan online anggota (sukses dan 409), struk dan cetak, riwayat transaksi tiap role, laporan penjualan, serta akses terlarang antar-role.

## PENUTUP

Jangan menambah fitur di luar dokumen (tanpa hapus barang, pagination, ekspor laporan, grafik, penyesuaian otomatis jumlah ke stok, pembatalan pesanan). Setelah selesai:

1. Centang di Fase 8 `TASKS.md` HANYA kotak yang tercakup sesi ini: `CatalogPage`, `AdminItemsPage`, `AdminStockPage`, dan `AdminReportPage`. **Jangan mencentang "Verifikasi manual alur tiap role dari ujung ke ujung"**: itu dicentang pemilik setelah mencoba di browser.
2. Commit `feat(frontend): add catalog and admin pages (phase 8c)` (ekstraksi `ItemPicker` dan perbaikan kecil di langkah awal sudah di commit terpisah).
3. Laporkan ringkasan, temuan kontrak API, keputusan, asumsi, output verifikasi, dan hal yang diragukan.
