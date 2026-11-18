# AGENTS.md - Smart Koperasi System

Proyek: POS dan inventaris koperasi kampus (React + Laravel + PostgreSQL).

## Wajib dibaca sebelum menulis kode

Dokumen konteks ada di folder `markdown/`:

1. `markdown/REQUIREMENTS.md`: spesifikasi, skema database, endpoint, format error.
2. `markdown/PRD.md`: aturan bisnis, acceptance criteria, skenario uji HP-01 sampai HP-07.
3. `markdown/ARCHITECTURE.md`: arsitektur berlapis dan alur checkout All-or-Nothing.
4. `markdown/RULES.md`: aturan kode, ACID, keamanan, pengujian. **Prioritas tertinggi.**
5. `markdown/TASKS.md`: daftar tugas berurutan. Kerjakan sesuai urutan.
6. `ketentuan.md`: instruksi rinci untuk fase yang sedang dikerjakan (ditimpa setiap ganti fase). Jika bertentangan dengan dokumen di `markdown/`, hentikan dan tanyakan dulu.

## Ringkasan aturan penting

- Kerjakan `TASKS.md` berurutan. Jangan menambah fitur di luar dokumen.
- Semua perubahan stok berada dalam `DB::transaction()` di Service, dengan `FOR UPDATE` berurutan `item_id` menaik.
- Stok tidak boleh negatif. Checkout gagal harus rollback total.
- Harga dan total selalu dihitung server dari database.
- Controller tipis, aturan bisnis di Service, query di Repository.
- Centang `[x]` di `TASKS.md` hanya setelah tes lulus. Jika ada yang ambigu, tanyakan dulu.
