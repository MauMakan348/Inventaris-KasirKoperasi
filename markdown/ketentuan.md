Ketentuan:
- Laravel 13 + Sanctum: periksa kode/dokumentasi versi terpasang, jangan
  berasumsi dari versi lama. Pastikan routes/api.php dan prefix /api aktif.
- Struktur: AuthController tipis -> AuthService -> (model/repository sesuai
  ARCHITECTURE.md). Validasi bentuk input di Form Request.
- POST /api/auth/register: publik, role SELALU MEMBER; field role dari request
  diabaikan. Password min 8, email unik.
- POST /api/auth/login: pesan error GENERIK untuk email tidak ada maupun
  password salah (401, code UNAUTHENTICATED). Rate limit 5 percobaan/menit.
  Respons berisi token dan data user (tanpa password).
- POST /api/auth/logout: hanya mencabut token yang sedang dipakai.
- GET /api/auth/me.
- Token Sanctum kedaluwarsa 480 menit (atur di config/sanctum.php).
- Middleware EnsureRole (alias 'role', mis. role:ADMIN,CASHIER): 403 FORBIDDEN
  jika role tidak cocok.
- Penanganan error global di satu tempat (bootstrap/app.php atau
  ApiExceptionHandler) ke format
  {"error":{"code","message","details?"}} untuk: 401 UNAUTHENTICATED,
  403 FORBIDDEN, 404 NOT_FOUND (termasuk ModelNotFoundException dan route tidak
  ada), 422 VALIDATION_ERROR (details berisi error per field), 500
  INTERNAL_ERROR (tanpa detail internal, hormati APP_DEBUG). Semua route /api
  harus selalu merespons JSON walaupun klien tidak mengirim header Accept.
- Pesan ke pengguna dalam bahasa Indonesia.
- Konfigurasi CORS: hanya origin klien React dari env (mis. FRONTEND_URL).
  Tambahkan ke .env.example tanpa nilai rahasia.
- Tes (Feature, database smart_koperasi_test): register sukses, register dengan
  field role=ADMIN tetap menjadi MEMBER, email duplikat 422, login sukses,
  login salah (pesan sama untuk email tak terdaftar dan password salah), rate
  limit login, akses /me tanpa token 401, logout mencabut token (token lama
  gagal setelahnya), EnsureRole menolak role salah 403 dan menerima role benar
  (daftarkan route sementara di dalam tes, jangan menambah endpoint produksi).
- Jangan menambah endpoint atau fitur di luar dokumen.