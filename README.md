# Presensi Event Alumni Pesantren - Backend API Service

Sistem backend RESTful API untuk platform **Presensi Event Alumni Pondok Pesantren**, dibangun dengan arsitektur modern menggunakan **Laravel 11**, **PHP 8.2+**, dan **Laravel Sanctum**.

Layanan ini mendukung autentikasi multi-portal (Admin & Alumni), registrasi & persetujuan akun, integrasi Google OAuth 2.0, manajemen master wilayah Indonesia, pembuatan token QR presensi dinamis, pemetaan engagement kehadiran alumni, serta dokumentasi API interaktif berbasis OpenAPI/Swagger.

---

## 🛠️ Tech Stack & Fitur Utama

- **Framework**: Laravel 11.x (PHP 8.2+)
- **Database**: MySQL / MariaDB (Production), SQLite (Testing/Dev)
- **Autentikasi & Otorisasi**:
  - Laravel Sanctum (Token-based API authentication dengan auto-purge token kedaluwarsa)
  - Role-based Access Control (`admin` / `super_admin` vs `alumni`)
  - Google OAuth 2.0 (Socialite)
- **Dokumentasi API**: OpenAPI 3.0 / Swagger via `darkaonline/l5-swagger`
- **Fitur Inti**:
  - Event Management (Kategori, Kuota, Jadwal, Filter & Rekomendasi)
  - Presensi Berbasis QR Code (Validasi Token UUID & Anti-Duplikasi)
  - Verifikasi & Persetujuan Akun Alumni (Pending, Active, Inactive, Rejected dengan log alasan)
  - Hierarki Wilayah Domisili (Provinsi, Kota/Kabupaten, Kecamatan, Kelurahan)
  - Visualisasi Grafik Kehadiran & Pemetaan Engagement Alumni
  - Integrasi Notifikasi & Broadcast WhatsApp (Fonnte API Gateway)

---

## 📋 Prasyarat Sistem

Pastikan server atau lingkungan lokal Anda telah memenuhi spesifikasi berikut:
- **PHP** >= 8.2
- **Ekstensi PHP**: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql` (atau `pdo_sqlite`), `tokenizer`, `xml`
- **Composer** >= 2.x
- **Database**: MySQL >= 8.0 / MariaDB >= 10.4

---

## 🚀 Panduan Instalasi

### 1. Clone & Masuk ke Direktori Proyek
```bash
git clone <URL_REPOSITORY>
cd presensi-event-backend
```

### 2. Install Dependensi PHP
```bash
composer install --optimize-autoloader --no-dev # Untuk production
# ATAU untuk development:
composer install
```

### 3. Konfigurasi Environment (`.env`)
Salin file template `.env.example` ke `.env`:
```bash
cp .env.example .env
```

Buka file `.env` dan sesuaikan parameter berikut:
```env
APP_NAME="Presensi Event Alumni"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Database Connection
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presensi_event
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# CORS Allowed Origins
CORS_ALLOWED_ORIGINS="https://app.yourdomain.com"
SANCTUM_STATEFUL_DOMAINS="app.yourdomain.com"

# Initial Super Admin (Digunakan saat menjalankan seeder awal)
INITIAL_ADMIN_NAME="Admin Pesantren"
INITIAL_ADMIN_EMAIL="admin@pesantren.com"
INITIAL_ADMIN_PASSWORD="GantiDenganPasswordKuat123!"
INITIAL_ADMIN_PHONE="081234567890"
INITIAL_ADMIN_GENDER="Laki-laki"

# Google OAuth 2.0 (Dapatkan dari Google Cloud Console)
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_BASE_URL="${APP_URL}"

# WhatsApp Gateway Fonnte (Opsional)
FONNTE_TOKEN=your_fonnte_token
```

### 4. Generate Application Key & Storage Link
```bash
php artisan key:generate
php artisan storage:link
```

---

## 🗄️ Inisialisasi Database & Seeding

### Inisialisasi Production (Data Bersih & Akun Super Admin)
Jalankan migrasi dan seeder default:
```bash
php artisan migrate --seed
```
> Perintah di atas **hanya** akan membuat tabel, kategori dasar event (`CategorySeeder`), dan satu akun Super Admin (`AdminSeeder`) sesuai data di `.env`. **Tidak ada data dummy yang dimasukkan ke production.**

### Data Demo / Development (Opsional)
Jika Anda membutuhkan data dummy/simulasi (puluhan data alumni, event, absensi, dan domisili) untuk pengujian lokal:
```bash
php artisan db:seed --class=DummyDataSeeder
```

---

## 📖 Dokumentasi API (Swagger UI)

Dokumentasi API lengkap dapat diakses secara interaktif melalui browser.

1. **Generate Dokumentasi Swagger**:
   ```bash
   php artisan l5-swagger:generate
   ```
2. **Buka URL di Browser**:
   ```
   http://localhost:8000/api/documentation
   # atau
   https://api.yourdomain.com/api/documentation
   ```

---

## ⏰ Background Tasks & Scheduled Commands

Untuk memastikan pengingat event dan pembersihan token Sanctum berjalan otomatis, tambahkan Cron Job berikut di server produksi:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Perintah terjadwal yang otomatis dieksekusi:
- `notifications:upcoming-events` — Mengirim notifikasi event yang akan berlangsung dalam ~1 jam (dijalankan tiap 10 menit).
- `sanctum:purge-expired` — Membersihkan personal access token yang sudah kedaluwarsa (dijalankan tiap jam).

---

## 🧪 Menjalankan Automated Tests

Proyek ini dilengkapi dengan comprehensive test suite (Unit & Feature):
```bash
php artisan test
```

---

## 📚 Panduan Integrasi Frontend

Panduan teknis dan spesifikasi integrasi untuk tim pengembang Frontend tersedia di dalam direktori `docs/`:
- [`docs/FRONTEND_GOOGLE_AUTH_GUIDE.md`](docs/FRONTEND_GOOGLE_AUTH_GUIDE.md) — Alur autentikasi Google OAuth alumni.
- [`docs/FRONTEND_ADMIN_AUTHORIZATION_INTEGRATION_GUIDE.md`](docs/FRONTEND_ADMIN_AUTHORIZATION_INTEGRATION_GUIDE.md) — Panduan integrasi otorisasi admin & multi-level access.
- [`docs/FRONTEND_DOMICILE_INTEGRATION_GUIDE.md`](docs/FRONTEND_DOMICILE_INTEGRATION_GUIDE.md) — Integrasi dropdown wilayah domisili bertingkat.
- [`docs/FRONTEND_ENGAGEMENT_MAPPING_INTEGRATION_GUIDE.md`](docs/FRONTEND_ENGAGEMENT_MAPPING_INTEGRATION_GUIDE.md) — Integrasi visualisasi pemetaan kehadiran alumni.
- [`docs/FRONTEND_ATTENDANCE_HISTORY_INTEGRATION_GUIDE.md`](docs/FRONTEND_ATTENDANCE_HISTORY_INTEGRATION_GUIDE.md) — Integrasi riwayat absensi & presensi.
- [`docs/GOOGLE_OAUTH_ERROR_HANDLING.md`](docs/GOOGLE_OAUTH_ERROR_HANDLING.md) — Panduan penanganan kode error OAuth.

---

## 🔒 Catatan Keamanan Penting

1. **Environment File (`.env`)**: Jangan pernah meng-commit file `.env` atau kredensial API asli ke repositori Git.
2. **Debug Mode**: Selalu pastikan `APP_DEBUG=false` pada server production untuk mencegah kebocoran stack trace error.
3. **CORS Configuration**: Batasi `CORS_ALLOWED_ORIGINS` hanya pada domain resmi frontend Anda.
