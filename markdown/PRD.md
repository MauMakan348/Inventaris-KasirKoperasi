# Product Requirement Document (PRD) - Smart Koperasi System

## 1. Business Logic & Constraints

- **BR-01** Stok barang berkurang otomatis hanya jika transaksi berhasil di-commit.
- **BR-02** Stok tidak boleh pernah bernilai kurang dari 0, dari channel mana pun (POS maupun ONLINE).
- **BR-03** Checkout bersifat **All-or-Nothing**: jika satu barang dalam keranjang gagal divalidasi, seluruh transaksi di-rollback. Tidak ada order, order item, mutasi stok, maupun perubahan stok yang tersisa.
- **BR-04** Harga dan total dihitung oleh server dari data database. Nilai harga atau total dari klien diabaikan.
- **BR-05** Harga yang berlaku saat transaksi disimpan sebagai snapshot di `order_items`, sehingga perubahan harga di kemudian hari tidak mengubah riwayat.
- **BR-06** Jika keranjang memuat `item_id` yang sama lebih dari sekali, kuantitasnya digabung sebelum divalidasi.
- **BR-07** Setiap perubahan stok (penjualan, restock, opname) wajib tercatat di `stock_movements` dalam transaksi yang sama.
- **BR-08** Untuk pembayaran `CASH` di POS, `amount_paid` harus >= `total_amount`. Kembalian dihitung server.
- **BR-09** Pemesanan online langsung mengurangi stok saat berhasil dibuat (tanpa reservasi atau batas waktu bayar). Status transaksi langsung `COMPLETED`.
- **BR-10** Restock hanya menerima kuantitas positif. Stock opname menetapkan stok sama dengan jumlah fisik (>= 0) dan mencatat selisihnya.
- **BR-11** Anggota hanya dapat melihat transaksi miliknya sendiri.

## 2. User Stories

- **US-01** Sebagai Admin, saya ingin menambah dan mengubah master barang (SKU, nama, harga, stok awal).
- **US-02** Sebagai Admin, saya ingin mencatat restock dan stock opname agar stok sistem sesuai kondisi fisik.
- **US-03** Sebagai Admin, saya ingin melihat laporan penjualan per periode.
- **US-04** Sebagai Kasir, saya ingin men-checkout beberapa barang sekaligus dan mencetak struk.
- **US-05** Sebagai Kasir, saya ingin sistem menolak seluruh transaksi bila ada barang yang stoknya tidak cukup, tanpa merusak stok barang lain.
- **US-06** Sebagai Anggota, saya ingin melihat katalog dan stok barang, lalu memesan secara online.
- **US-07** Sebagai Anggota, saya ingin melihat riwayat pesanan saya.
- **US-08** Sebagai pengguna, saya ingin login dan hanya mengakses fitur sesuai role saya.

## 3. Acceptance Criteria

### 3.1 Checkout (POST /api/orders)
1. Mengembalikan **201 Created** jika valid. Stok setiap barang berkurang tepat sesuai kuantitas, order dan order item tersimpan, mutasi stok tercatat.
2. Mengembalikan **409 INSUFFICIENT_STOCK** jika ada satu atau lebih barang dengan stok kurang. Respons memuat daftar seluruh barang bermasalah. **Stok semua barang tidak berubah** dan tidak ada order yang tersimpan.
3. Mengembalikan **404 NOT_FOUND** jika ada `item_id` yang tidak ada. Tidak ada perubahan data.
4. Mengembalikan **422** jika keranjang kosong, kuantitas bukan bilangan bulat positif, atau uang bayar CASH kurang.
5. `total_amount` dihitung dari harga di database, bukan dari klien.
6. Role `ADMIN` tidak boleh checkout (403). Role `MEMBER` selalu tercatat sebagai channel `ONLINE`, `CASHIER` sebagai `POS`.

### 3.2 Konkurensi
1. Jika stok tersisa N dan M request (M > N, total kuantitas melebihi N) masuk bersamaan, **hanya request yang muat dalam N yang berhasil**. Sisanya menerima 409. Stok akhir tidak pernah negatif.
2. Jika request POS dan ONLINE memperebutkan stok terakhir yang sama, tepat satu yang berhasil.
3. Dua checkout bersamaan dengan barang yang saling tumpang tindih tidak boleh menyebabkan deadlock yang membuat server macet atau data rusak.

### 3.3 Restock & Opname
1. Restock valid mengembalikan 200 dan menambah stok. Mutasi `RESTOCK` tercatat dengan `stock_before` dan `stock_after`.
2. Opname valid mengembalikan 200, stok menjadi `actual_stock`, mutasi `OPNAME` mencatat selisih (boleh positif atau negatif).
3. Kuantitas restock <= 0 atau `actual_stock` < 0 mengembalikan 422.
4. Hanya `ADMIN`. Role lain mendapat 403.

### 3.4 Barang
1. `GET /items` menampilkan stok terkini dari database.
2. `POST /items` dan `PUT /items/{id}` hanya untuk `ADMIN`. SKU duplikat mengembalikan 422. Harga <= 0 atau stok negatif mengembalikan 422.
3. `PUT /items/{id}` tidak dapat mengubah stok.

### 3.5 Transaksi & Laporan
1. `GET /orders` untuk MEMBER hanya memuat transaksi miliknya. `GET /orders/{id}` milik orang lain mengembalikan 404.
2. `GET /orders/{id}` memuat data lengkap untuk struk (kode, waktu, daftar barang, total, bayar, kembalian).
3. `GET /reports/sales` memuat total transaksi, total pendapatan, rincian per channel, dan barang terlaris pada periode. Hanya `ADMIN`.

### 3.6 Autentikasi
1. Registrasi publik hanya membuat role `MEMBER`. Field `role` dari request diabaikan.
2. Login salah mengembalikan 401 dengan pesan generik. Login dibatasi 5 percobaan per menit.
3. Endpoint terproteksi tanpa token mengembalikan 401.

## 4. Skenario Uji "The 30% Hard Problem" (Bahan Demo)

| ID | Skenario | Hasil yang Diharapkan |
|---|---|---|
| HP-01 | Kasir checkout 5 barang berbeda. Barang ke-4 stoknya sudah habis (dibeli anggota via online sesaat sebelumnya). | 409. Stok barang 1, 2, 3, 5 **tidak berubah**. Tidak ada order dan mutasi baru. |
| HP-02 | Stok barang X = 1. Kasir (POS) dan anggota (ONLINE) checkout barang X pada saat yang sama. | Tepat satu 201 dan satu 409. Stok akhir = 0. |
| HP-03 | Stok barang X = 5. 20 request paralel masing-masing membeli 1. | Tepat 5 berhasil, 15 gagal 409. Stok akhir = 0, tidak pernah negatif. |
| HP-04 | Dua checkout paralel berisi barang A dan B dengan urutan berbeda (A,B dan B,A). | Keduanya selesai tanpa deadlock. Hasil konsisten. |
| HP-05 | Keranjang memuat barang yang sama dua kali (qty 2 + qty 3) dengan stok 4. | Digabung menjadi 5, ditolak 409. Stok tetap 4. |
| HP-06 | Klien mengirim harga palsu di body. | Diabaikan. Total tetap dari harga database. |
| HP-07 | Kegagalan paksa di tengah proses (setelah 3 barang diproses). | Rollback penuh. Stok seluruh barang kembali seperti semula. |

## 5. Di Luar Ruang Lingkup (Belum Ditentukan)

Jangan dibangun tanpa keputusan lanjutan: payment gateway, pembatalan atau retur transaksi, reservasi stok dengan batas waktu bayar, hapus atau nonaktifkan barang, diskon dan harga khusus anggota, pagination dan pencarian lanjutan, notifikasi, upload gambar barang, deployment production.
