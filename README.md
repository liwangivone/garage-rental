# Garage Rental

Aplikasi web penyewaan garasi (sepeda, mobil, dan mobil listrik) berbasis **Laravel 13**. Ada dua peran:

- **Admin**: mengelola garasi & harga, memverifikasi pendaftaran penyewa, memverifikasi bukti bayar, memperpanjang sewa, dan melihat laporan.
- **Penyewa**: mendaftar, menyewa garasi (harian/bulanan), melihat riwayat sewa.

## Aturan bisnis singkat

- **Tipe garasi**: sepeda, mobil, mobil listrik. Mobil listrik boleh memakai garasi mobil atau garasi mobil listrik.
- **Sewa harian** 1 s.d. (jumlah hari dalam bulan − 1), maksimal 30 hari. **Sewa bulanan** tepat satu bulan (harga bulanan = 25 × harga harian). Sewa yang lebih lama dibuat sebagai transaksi terpisah.
- **Tanggal mulai** tidak boleh sebelum hari ini dan maksimal 30 hari ke depan.
- **Pembayaran**: penyewa mengunggah bukti bayar, lalu admin menyetujui atau menolak. Sewa yang belum diverifikasi tetap menahan garasi.
- **Biaya listrik** (hanya mobil listrik di garasi mobil listrik), dibayar sekali di awal:

  ```
  biaya listrik = (kapasitas baterai maks − sisa baterai saat ini) kWh × harga per kWh
  ```

  Kapasitas baterai (kWh) disimpan pada data kendaraan. Sisa baterai diisi penyewa saat membuat sewa, wajib diisi untuk garasi mobil listrik, dan tidak boleh melebihi kapasitas. Harga per kWh diatur admin saat menambah garasi mobil listrik. Nilai sisa baterai dan jumlah kWh yang diisi disimpan pada sewa sebagai catatan transaksi.
- **Status garasi** (`available`/`used`) mengikuti tanggal sewa dan disinkronkan setiap hari pukul 00:01 oleh scheduler.

## Kebutuhan sistem

| Kebutuhan | Versi / catatan |
|---|---|
| PHP | 8.3 atau lebih baru |
| Ekstensi PHP | `bcmath`, `mbstring`, `intl`, `fileinfo`, `gd`, `pdo_mysql` (plus ekstensi bawaan Laravel: `ctype`, `json`, `openssl`, `tokenizer`, `xml`) |
| Composer | 2.x |
| Node.js & npm | Node 20.19+ atau 22.12+ (dibutuhkan Vite 8) |
| MySQL | 8.x atau MariaDB 10.6+ (database default aplikasi) |
| Git | untuk meng-clone repositori |

Cek versi yang terpasang:

```bash
php -v
php -m | grep -iE "bcmath|mbstring|intl|fileinfo|gd|pdo_mysql"
composer -V
node -v && npm -v
mysql --version
```

Jika PHP/Composer belum terpasang, cara termudah di macOS: `/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"` (Linux dan Windows: lihat [php.new](https://php.new)), lalu buka ulang terminal.

## Instalasi di lokal (step by step)

### 1. Clone repositori

```bash
git clone <url-repositori> garage-rental
cd garage-rental
```

### 2. Pasang dependensi PHP

```bash
composer install
```

### 3. Buat file `.env` dan app key

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Siapkan database MySQL

Buat database kosong, misalnya dengan nama `garage_rental`:

```bash
mysql -u root -p -e "CREATE DATABASE garage_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Lalu sesuaikan bagian database di `.env` dengan akun MySQL Anda:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garage_rental
DB_USERNAME=root
DB_PASSWORD=
```

> **Ingin tanpa MySQL?** Pakai SQLite: ubah `.env` menjadi `DB_CONNECTION=sqlite` (hapus/komentari `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), lalu jalankan `touch database/database.sqlite`.

### 5. Jalankan migrasi dan seeder

Pilih salah satu:

```bash
# A. Hanya akun admin (database bersih)
php artisan migrate --seed

# B. Admin + data demo (garasi, penyewa, kendaraan, riwayat sewa untuk mencoba semua halaman)
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

Seeder admin memakai kredensial dari `.env` (bagian `ADMIN_*`). Ubah sebelum menjalankan seeder jika perlu:

```dotenv
ADMIN_NAME="Administrator"
ADMIN_EMAIL=admin@garagerental.test
ADMIN_PASSWORD=password
```

> `DemoSeeder` aman dijalankan ulang: bila data demo sudah ada, seeder melewatinya.

### 6. Buat symbolic link storage

Bukti bayar disimpan di disk `public`, sehingga link ini wajib agar gambarnya tampil:

```bash
php artisan storage:link
```

### 7. Pasang dependensi front-end dan build aset

```bash
npm install
npm run build
```

Untuk pengembangan dengan hot reload, jalankan `npm run dev` (lihat langkah 8).

### 8. Jalankan aplikasi

Cara paling sederhana:

```bash
php artisan serve
```

Buka <http://localhost:8000>.

Alternatif: `composer dev` menjalankan semua proses pengembangan sekaligus (lihat `php artisan dev:list`). Bila memakai `php artisan serve` saja, jalankan `npm run dev` di terminal kedua agar perubahan CSS/JS langsung terlihat. Jika Anda sudah menjalankan `npm run build`, langkah ini tidak diperlukan.

> Cara cepat untuk langkah 2-7 sekaligus: `composer setup` (install, salin `.env`, generate key, migrate, npm install, build). Tetap siapkan database di langkah 4 dan jalankan `php artisan storage:link` serta seeder secara manual.

### 9. (Opsional) Jalankan scheduler

Status garasi disinkronkan otomatis tiap hari lewat scheduler. Di lokal, jalankan:

```bash
php artisan schedule:work
```

Atau sinkronkan manual kapan saja:

```bash
php artisan garages:sync-status
```

Di server produksi, tambahkan satu entri cron: `* * * * * cd /path/ke/garage-rental && php artisan schedule:run >> /dev/null 2>&1`.

## Akun untuk login

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@garagerental.test` (atau nilai `ADMIN_EMAIL`) | `password` (atau nilai `ADMIN_PASSWORD`) |

Jika memakai `DemoSeeder`, tersedia juga akun penyewa dengan password `password`:

| Penyewa | Email | Status |
|---|---|---|
| Andi Pratama | `demo.andi.pratama@example.test` | Disetujui |
| Sari Wulandari | `demo.sari.wulandari@example.test` | Disetujui |
| Budi Santoso (pemilik mobil listrik Ioniq 5, kapasitas 58 kWh) | `demo.budi.santoso@example.test` | Disetujui |
| Citra Lestari, Dimas Saputra, Eka Putri | `demo.citra.lestari@example.test`, `demo.dimas.saputra@example.test`, `demo.eka.putri@example.test` | Menunggu verifikasi |
| Fajar Nugroho | `demo.fajar.nugroho@example.test` | Ditolak |

Penyewa baru bisa mendaftar sendiri di `/register`; akun baru berstatus menunggu sampai admin menyetujuinya di menu **Verifikasi Pendaftar**. Penyewa belum bisa membuat sewa sebelum disetujui.

## Mencoba alur biaya listrik

1. Login sebagai admin, buka **Garasi → Buat Garasi**, pilih tipe *Garasi Mobil Listrik*, isi jumlah, harga harian, dan **harga per kWh** (contoh: 3.500).
2. Login sebagai penyewa yang sudah disetujui, buka **Sewa Baru**.
3. Pilih kendaraan mobil listrik (atau tambah baru dengan kapasitas baterai, misalnya 58 kWh).
4. Isi **Sisa baterai saat ini** (misalnya 20 kWh) dan pilih garasi mobil listrik.
5. Rincian biaya menampilkan `Isi listrik (38 kWh)` = (58 − 20) × 3.500 = Rp133.000, ditambah biaya sewa.
6. Unggah bukti bayar, lalu admin memverifikasinya di menu **Sewa**.

## Menjalankan tes

Tes memakai SQLite in-memory (diatur di `phpunit.xml`), jadi tidak menyentuh database Anda:

```bash
composer test
# atau
php artisan test
```

## Perintah yang sering dipakai

| Perintah | Fungsi |
|---|---|
| `php artisan migrate` | Jalankan migrasi baru (mis. setelah `git pull`) |
| `php artisan migrate:fresh --seed` | **Hapus semua tabel** lalu migrasi dan seed ulang. Hanya untuk database lokal |
| `php artisan db:seed --class=DemoSeeder` | Isi data demo |
| `php artisan config:clear` | Bersihkan cache konfigurasi setelah mengubah `.env` |
| `php artisan garages:sync-status` | Sinkronkan status garasi dengan tanggal sewa |
| `vendor/bin/pint` | Rapikan gaya kode |

## Pemecahan masalah

- **`could not find driver` / `SQLSTATE[HY000] [2002] Connection refused`**: ekstensi `pdo_mysql` belum aktif, atau server MySQL belum berjalan. Nyalakan MySQL dan periksa `DB_*` di `.env`.
- **`Unknown database 'garage_rental'`**: database belum dibuat (langkah 4).
- **`Call to undefined function bcadd()`**: aktifkan ekstensi PHP `bcmath`. Perhitungan biaya memakainya.
- **Layar tanpa gaya (CSS tidak termuat) / `Vite manifest not found`**: jalankan `npm run build` atau `npm run dev`.
- **Gambar bukti bayar tidak tampil**: jalankan `php artisan storage:link`.
- **`No application encryption key has been specified`**: jalankan `php artisan key:generate`.
- **Perubahan `.env` tidak berpengaruh**: jalankan `php artisan config:clear`.

## Struktur singkat

```
app/Enums/                  Tipe garasi/kendaraan, status, peran
app/Http/Controllers/Admin  Panel admin (garasi, pendaftaran, sewa, laporan)
app/Http/Controllers/Penyewa  Portal penyewa (dashboard, profil, sewa)
app/Services/               Logika bisnis: pemesanan & biaya, perpanjangan, periode, laporan
database/migrations/        Skema database
database/seeders/           AdminSeeder dan DemoSeeder
resources/views/            Tampilan Blade (admin, penyewa, auth)
tests/                      Tes unit dan feature
```

## Lisensi

Dibangun di atas [Laravel](https://laravel.com) yang berlisensi [MIT](https://opensource.org/licenses/MIT).
