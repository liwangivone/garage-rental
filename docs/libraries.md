# Library dan Komponen yang Digunakan

Tidak ada library UI pihak ketiga (Bootstrap, Alpine, Chart.js, jQuery) dan tidak ada CDN. Semua komponen UI dibuat sendiri.

## Library backend (Composer)

| Library | Fungsi | Dipakai di |
|---|---|---|
| `laravel/framework` ^13.17 | Framework utama: routing, Eloquent ORM, Blade, validasi, auth, session, storage, scheduler, pagination | Seluruh project |
| `laravel/tinker` | REPL untuk mencoba kode atau query dari terminal | Alat bantu development, tidak dipakai di kode |

### Fitur bawaan Laravel yang dipakai aktif

| Fitur | Fungsi | Dipakai di |
|---|---|---|
| Eloquent (`Model`, `BelongsTo`, `HasMany`, `HasOne`, `Builder`) | ORM dan relasi tabel | `app/Models/*` (7 model) |
| `Attributes\Fillable` dan `Attributes\Hidden` | Penanda mass-assignment dan field tersembunyi | Model `User` dan model lain |
| `FormRequest` | Validasi form | `StoreRentalRequest` (sewa baru) |
| `Validation\Rule` dan `ValidationException` | Aturan validasi kustom | Controller Admin dan Penyewa |
| `Facades\DB` | Transaksi dan query | Service dan controller |
| `Facades\Auth` | Login dan logout | `AuthController` |
| `Facades\Password` | Reset password | `PasswordResetController` |
| `Facades\Storage` (disk `public`) | Simpan dan hapus bukti bayar | Upload di `RentalController` (Admin dan Penyewa) |
| `Facades\Blade` | Membuat directive `@rupiah` | `AppServiceProvider` |
| `Facades\Schedule` | Menjadwalkan `garages:sync-status` setiap hari pukul 00:01 | `routes/console.php` |
| `Console\Command` | Perintah artisan kustom | `SyncGarageStatus` |
| `Carbon` | Hitung tanggal sewa dan durasi | `RentalPeriodCalculator`, controller, laporan |
| `paginate()` dan `->links()` | Pagination | Admin: garasi, sewa, registrasi. Penyewa: riwayat sewa |
| Middleware | Pembatas akses | `EnsureAdmin`, `EnsurePenyewa`, `EnsureApproved` |

## Library development dan testing (`require-dev`)

| Library | Fungsi | Dipakai di |
|---|---|---|
| `phpunit/phpunit` ^12 | Framework unit dan feature test | `tests/Unit` dan `tests/Feature`, memakai SQLite in-memory (`phpunit.xml`) |
| `mockery/mockery` | Mocking di test | Bawaan Laravel, tidak ada pemakaian langsung di test |
| `fakerphp/faker` | Data palsu | Factory dan seeder |
| `laravel/pint` | Code formatter (PSR/Laravel style) | Dijalankan manual dengan `./vendor/bin/pint` |
| `laravel/pail` | Melihat log real-time di terminal | Dipakai lewat `php artisan dev` |
| `nunomaduro/collision` | Tampilan error CLI dan test yang lebih rapi | Otomatis di artisan dan test |
| `laravel/pao` | Bagian dari tooling dev Laravel | Tidak dipakai langsung di kode |

## Library frontend (npm)

| Library | Fungsi | Dipakai di |
|---|---|---|
| `tailwindcss` ^4 dan `@tailwindcss/vite` | CSS utility-first untuk seluruh tampilan. Tema kustom (charcoal, cream, ember) didefinisikan di `@theme` | `resources/css/app.css` dan semua file Blade |
| `vite` ^8 | Build tool dan dev server aset | `vite.config.js`, `@vite(...)` di `partials/head.blade.php` |
| `laravel-vite-plugin` | Menghubungkan Vite dengan Blade, hot refresh | `vite.config.js` |
| `laravel-vite-plugin/fonts` (`bunny`) | Memuat font Barlow dan Barlow Condensed dari Bunny Fonts | `vite.config.js`, directive `@fonts` di `head.blade.php` |
| `concurrently` | Menjalankan beberapa proses dev sekaligus | Script `composer dev` |
| `@laravel/multiplex` (opsional) | Tooling Laravel | Tidak dipakai langsung |

JavaScript buatan sendiri hanya satu fungsi di `resources/js/app.js`: toggle sidebar di layar kecil, tanpa library.

## Komponen sendiri (Blade components)

Tidak ada komponen siap pakai dari luar. Semua ada di `resources/views/components/`.

| Komponen | Fungsi | Dipakai di |
|---|---|---|
| `layouts/admin` | Layout panel admin (sidebar, header) | 7 halaman admin |
| `layouts/customer` | Layout portal penyewa | 5 halaman penyewa |
| `layouts/guest` | Layout halaman login, daftar, dan reset password | 4 halaman auth |
| `x-flash` | Menampilkan pesan sukses atau error | Layout admin dan customer |
| `x-icon` | Ikon SVG inline (dashboard, garage, users, clock, chart, logout, menu, plus) | Layout admin dan customer, `admin/garages/index`, `penyewa/dashboard`, `penyewa/rentals/index` |
| `x-stat` | Kartu statistik angka | `admin/dashboard`, `admin/reports/index`, `penyewa/dashboard` |
| `x-bar-chart` | Grafik batang buatan sendiri dari HTML dan CSS, tanpa Chart.js | `admin/reports/index` |
| `x-payment-badge` | Badge status pembayaran (pending, approved, rejected) | `admin/rentals/index`, `admin/rentals/show`, `penyewa/rentals/index`, `_card`, `show` |

Kelas CSS kustom seperti `.card` dan `.card-title` ada di `resources/css/app.css` (`@layer components`).

## Kode pendukung buatan sendiri (bukan library)

- `app/Services/`: `GarageBatchService` (pembuatan garasi massal), `RentalBookingService` (sewa baru), `RentalExtensionService` (perpanjangan), `RentalPeriodCalculator` (hitung hari dan bulan), `RentalReportService` (laporan).
- `app/Support/Format.php`: format rupiah, dipakai lewat `@rupiah` dan di `bar-chart`.
- `app/Enums/`: `GarageType`, `VehicleType`, `RentalType`, `RegistrationStatus`, `UserRole`, `PaymentStatus`, `GarageStatus`.

Catatan: `resources/views/welcome.blade.php` adalah halaman bawaan Laravel yang memuat CSS Tailwind 4.0.7 secara inline. Itu bukan dependensi project.
