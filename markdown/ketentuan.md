# Ketentuan Fase 8B - Frontend: Kasir (POS), Struk, Daftar Transaksi

Instruksi rinci untuk fase yang sedang dikerjakan. File ini ditimpa setiap ganti fase atau sesi.

Fase 8 dipecah menjadi tiga sesi. Sesi 8A selesai. Sesi ini adalah **8B**:

- **8B (sesi ini):** halaman kasir (POS), tampilan error checkout, anti klik ganda, struk, daftar transaksi.
- 8C: katalog anggota, halaman admin (barang, stok, laporan), verifikasi tiap role.

Kerjakan HANYA sesi 8B. Jangan membuat halaman 8C (`/catalog`, `/admin/*`) dan jangan mengerjakan Fase 9. Jangan mengubah kode backend.

Bagian dokumen yang relevan: `REQUIREMENTS.md` bagian 5.4, 7, 8. `PRD.md` bagian 1 (BR-01 sampai BR-11), 3.1, 3.5 (butir 1 dan 2), 4. `ARCHITECTURE.md` bagian 3 (Client), 4. `RULES.md` bagian 3 poin 7, bagian 4 (frontend), bagian 5.

Jika isi file ini bertentangan dengan dokumen di `markdown/`, hentikan pekerjaan dan tanyakan dulu.

## LANGKAH AWAL

1. Commit TERPISAH perubahan milik pemilik proyek (`markdown/ketentuan.md`) dengan pesan `docs: update phase instructions`. Jangan mengubah isinya.
2. Pastikan `php artisan test` hijau dan `npm run build` serta `npm run lint` di `frontend/` lulus sebelum mulai.
3. Baca bentuk respons asli backend untuk `GET /api/items`, `POST /api/orders`, `GET /api/orders`, `GET /api/orders/{id}` (lihat `ItemResource`, `OrderResource`, dan tes `CheckoutTest`/`OrderHistoryTest`). Jangan menebak nama field. Laporkan temuannya.

## KEPUTUSAN DESAIN (sudah ditetapkan, ikuti dan sebutkan di laporan)

1. **Klien tidak pernah menjadi sumber kebenaran harga, total, atau stok.** Payload checkout hanya berisi `payment_method`, `amount_paid` (hanya untuk CASH), dan `items[{item_id, quantity}]`. Dilarang mengirim harga, total, channel, atau role. Total yang tampil sebelum checkout diberi label "Total (perkiraan)". Setelah sukses, tampilkan angka dari **respons server**, bukan hitungan klien.
2. **Klien tidak memblokir aturan bisnis; server yang memutuskan.** Tombol checkout hanya dinonaktifkan bila: keranjang kosong, CASH tanpa `amount_paid` terisi, atau request sedang berjalan. Kuantitas yang melebihi stok yang sedang tampil dan uang yang kurang dari perkiraan total hanya diberi **peringatan visual**, tetapi tetap boleh dikirim. Alasan: data stok di layar bisa basi, dan demo harus bisa menunjukkan penolakan dari server (409 dan 422). Batas UX yang BOLEH memblokir: kuantitas 1..1000 (dijepit ke rentang itu) dan maksimal 50 baris keranjang (tampilkan pesan).
3. **Keranjang tidak pernah dihapus saat checkout gagal.** Keranjang hanya dikosongkan setelah sukses.
4. **Anti klik ganda dua lapis:** state `submitting` yang menonaktifkan tombol, DAN `useRef` penjaga yang menolak panggilan kedua sebelum state sempat dirender ulang.
5. **Hasil tak pasti saat jaringan putus:** karena tidak ada idempotency key, jika terjadi `NETWORK_ERROR` pada checkout, tampilkan: "Koneksi terputus. Periksa halaman Transaksi sebelum mengulang agar tidak tercatat dua kali." Keranjang tetap dipertahankan.
6. **Data stok dimuat ulang** (`GET /items`) saat halaman dibuka, setelah checkout sukses, dan setelah checkout gagal 409/404. Sediakan tombol "Muat ulang stok".
7. **Pencarian di POS** adalah filter sisi klien atas daftar yang sudah dimuat (nama atau SKU, tidak sensitif huruf besar-kecil). Tanpa pagination dan tanpa pencarian ke server.
8. **Tanpa dependency baru.** CSS biasa. Tanpa test framework baru.

## FASE 8B

### Struktur yang ditambahkan
```text
frontend/src/
├── api/
│   ├── items.js          # listItems
│   └── orders.js         # checkout, listOrders, getOrder
├── hooks/
│   └── useCart.js        # state keranjang (dipakai ulang di 8C untuk katalog anggota)
├── components/
│   ├── CartTable.jsx     # baris keranjang, ubah jumlah, hapus (dipakai ulang di 8C)
│   ├── ErrorAlert.jsx    # tampil error API seragam, role="alert"
│   └── StockShortageAlert.jsx   # daftar barang stok kurang dari 409
├── utils/
│   └── checkout.js       # buildCheckoutPayload (murni, tanpa efek samping)
└── pages/
    ├── PosPage.jsx
    ├── OrdersPage.jsx
    └── ReceiptPage.jsx
```
Ganti rute `/pos`, `/orders`, `/orders/:id` dari `Placeholder` ke halaman nyata. Jangan menyentuh rute 8C. Jangan membuat modul API untuk stok, laporan, atau pembuatan barang.

### `utils/checkout.js` - `buildCheckoutPayload(lines, paymentMethod, amountPaid)`
Fungsi murni. Keluaran: `{ payment_method, items: [{item_id, quantity}], amount_paid? }`. `amount_paid` HANYA disertakan jika `payment_method === "CASH"` dan berupa integer. Tidak boleh ada field harga atau total.

### `hooks/useCart.js`
- Baris: `{ itemId, sku, name, price, quantity }` (harga hanya untuk tampilan perkiraan).
- Fungsi: `add(item)` (barang yang sama menambah kuantitas), `setQuantity(itemId, qty)` (dijepit 1..1000), `remove(itemId)`, `clear()`, serta nilai turunan `lines`, `lineCount`, `estimatedTotal`.
- Tolak penambahan baris ke-51 dan beri tahu pemanggil.

### `PosPage` (`/pos`, hanya CASHIER)
Tata letak dua panel (satu kolom di layar sempit):
- **Kiri, daftar barang:** kolom pencarian, daftar nama, SKU, harga, stok. Stok 0 menampilkan "Habis" dan tombol tambah dinonaktifkan. Klik menambah ke keranjang.
- **Kanan, keranjang dan pembayaran:**
  - `CartTable` dengan kontrol jumlah dan hapus. Baris yang kuantitasnya melebihi stok yang sedang tampil diberi peringatan "Melebihi stok tampil (n)" tetapi tetap bisa diproses.
  - Pilihan metode: `CASH` (default) atau `TRANSFER`.
  - CASH: input `amount_paid` (angka bulat >= 0). Tampilkan perkiraan kembalian, atau peringatan "Uang kurang dari perkiraan total" bila kurang. TRANSFER: tanpa input uang.
  - Tombol "Checkout" sesuai keputusan nomor 2 dan 4.
- **Setelah sukses (201):** tampilkan panel hasil dari respons server (kode, total, dibayar, kembalian, jumlah barang), tombol "Lihat Struk" (`/orders/:id`) dan "Transaksi Baru". Kosongkan keranjang dan reset input pembayaran, muat ulang stok.
- **Penanganan error** (semua lewat `parseApiError`, keranjang tetap):
  - `409 INSUFFICIENT_STOCK`: tampilkan `StockShortageAlert` yang mendaftar SEMUA barang di `details` (nama, diminta, tersedia). Baris keranjang terkait disorot. Muat ulang stok.
  - `404 NOT_FOUND` dengan `details.item_ids`: pesan bahwa ada barang yang tidak ditemukan, sorot barangnya jika masih ada di keranjang, muat ulang stok.
  - `422 INSUFFICIENT_PAYMENT`: tampilkan pesan server beserta `total_amount` dan `amount_paid` dari `details`.
  - `422 VALIDATION_ERROR` lainnya: tampilkan pesan server dan `fieldErrors` bila ada.
  - `NETWORK_ERROR`: keputusan nomor 5.
  - Lainnya: pesan umum dari `parseApiError`, tanpa data mentah.
- Alert error hilang saat pengguna mengubah keranjang atau memulai checkout baru.

### `OrdersPage` (`/orders`, semua role)
- `GET /orders`. Judul "Pesanan Saya" untuk MEMBER, "Riwayat Transaksi" untuk CASHIER dan ADMIN.
- Kolom: kode, waktu (`formatDateTime`), channel (POS/ONLINE), metode pembayaran, total (`formatRupiah`), dan nama pelaku (hanya untuk CASHIER dan ADMIN). Baris menautkan ke `/orders/:id`.
- Keadaan: memuat, kosong ("Belum ada transaksi"), error dengan tombol coba lagi. Tanpa pagination (backend mengembalikan semua).

### `ReceiptPage` (`/orders/:id`, semua role)
- `GET /orders/{id}`. Tampilkan: nama koperasi, kode, waktu (`formatDateTime`), nama pelaku, channel, metode pembayaran, tabel barang (nama, jumlah, harga satuan, subtotal), total, dibayar, kembalian. Untuk TRANSFER tampilkan "Transfer" dan tanpa baris kembalian.
- 404 -> pesan "Transaksi tidak ditemukan" dengan tautan kembali ke `/orders` (MEMBER yang membuka transaksi orang lain juga mendapat 404 dari server, perlakukan sama).
- Tombol "Cetak" memanggil `window.print()` dan tombol "Kembali".
- CSS cetak (`@media print`): sembunyikan header, navigasi, dan tombol. Struk berukuran sempit (sekitar lebar kertas struk) dan rapi.

## VERIFIKASI

Opencode tidak dapat melihat tampilan, jadi laporkan bukti yang bisa diperiksa:

1. `npm run lint` bersih dan `npm run build` lulus (tampilkan ringkasan).
2. Bukti payload tidak memuat harga: jalankan skrip Node satu kali pakai (tidak di-commit) yang mengimpor `buildCheckoutPayload` dan mencetak payload untuk kasus CASH dan TRANSFER, termasuk saat `amount_paid` diisi string atau kosong. Tampilkan output.
3. Siapkan **urutan `curl` untuk skenario HP-01 di UI** dan tulis di laporan sehingga pemilik bisa mencobanya: login sebagai kasir dan anggota, ambil `item_id` dan stok, lalu habiskan stok satu barang lewat checkout ONLINE (anggota) SETELAH keranjang kasir terisi di browser. Setelah itu pemilik menekan Checkout di POS dan harus melihat panel 409 yang mendaftar barang bermasalah dengan keranjang tetap utuh.
4. Daftar file yang dibuat atau diubah dan tabel rute akhir.
5. Panduan singkat cara pemilik mencoba tiap alur dan apa yang seharusnya terlihat (sukses CASH dengan kembalian, sukses TRANSFER, 409, uang kurang, klik ganda cepat, cetak struk, riwayat sebagai kasir dan sebagai anggota).

## PENUTUP

Jangan menambah fitur di luar dokumen (tanpa penyesuaian otomatis jumlah ke stok, diskon, scan barcode, atau idempotency key). Setelah selesai:

1. Centang HANYA kotak Fase 8 `TASKS.md` yang tercakup sesi ini: `PosPage` (cari/pilih, keranjang, pembayaran, checkout, kembalian), tampilan error 409 dengan keranjang tetap, cegah klik ganda, dan `OrdersPage` serta `ReceiptPage`. Kotak lain tetap kosong.
2. Commit `feat(frontend): add pos, receipt, and order history (phase 8b)`.
3. Laporkan ringkasan, temuan bentuk respons backend, keputusan, asumsi, output verifikasi, dan hal yang diragukan.
