# ERD Garage Rental

ERD ini mengikuti skema database yang ada di `database/migrations` (tabel framework seperti `cache`, `jobs`, `sessions` tidak digambarkan).

```mermaid
erDiagram
    users {
        bigint id PK
        string name "nama lengkap"
        string nik UK "16 digit, null untuk admin"
        string phone
        string email UK
        timestamp email_verified_at
        string password
        string role "admin | penyewa"
        string registration_status "pending | approved | rejected"
        text rejection_reason
        timestamp verified_at
        bigint verified_by FK "admin, nullable"
        timestamp created_at
        timestamp updated_at
    }

    garage_prices {
        bigint id PK
        string type UK "bicycle | car | electric_car"
        decimal daily_price
        decimal electricity_price_per_kwh "hanya electric_car"
        bigint updated_by FK "nullable"
        timestamp created_at
        timestamp updated_at
    }

    garage_batches {
        bigint id PK
        string type "bicycle | car | electric_car"
        smallint quantity "1 sampai 999"
        decimal daily_price "catatan historis"
        decimal electricity_price_per_kwh "catatan historis"
        bigint created_by FK "admin"
        timestamp created_at
        timestamp updated_at
    }

    garages {
        bigint id PK
        bigint garage_batch_id FK
        string type "bicycle | car | electric_car"
        smallint sequence "unik per type, 1 sampai 999"
        string code UK "S001, C010, E003"
        string status "available | used"
        timestamp created_at
        timestamp updated_at
    }

    vehicles {
        bigint id PK
        bigint user_id FK "pemilik (penyewa)"
        string type "bicycle | motorcycle | car | electric_car"
        string plate_number "null untuk sepeda, tidak unik"
        string brand
        string model
        decimal battery_capacity_kwh "hanya mobil listrik"
        timestamp created_at
        timestamp updated_at
    }

    rentals {
        bigint id PK
        bigint user_id FK "penyewa"
        bigint vehicle_id FK
        bigint garage_id FK
        string rental_type "daily | monthly"
        date start_date
        date end_date "diperbarui saat perpanjangan"
        smallint duration_days
        smallint monthly_count
        smallint daily_count
        decimal daily_price "snapshot"
        decimal monthly_price "snapshot, 25 x daily_price"
        decimal electricity_price_per_kwh "snapshot"
        decimal battery_current_kwh "sisa baterai saat sewa"
        decimal battery_charge_kwh "kWh yang harus diisi"
        decimal electricity_cost
        decimal rental_cost
        decimal total_price "ikut diperbarui saat perpanjangan"
        string payment_proof_path "bukti pelunasan"
        string payment_status "pending | approved | rejected"
        text payment_rejection_reason
        timestamp payment_verified_at
        bigint payment_verified_by FK "admin, nullable"
        timestamp created_at
        timestamp updated_at
    }

    rental_extensions {
        bigint id PK
        bigint rental_id FK
        bigint processed_by FK "admin"
        date previous_end_date
        date new_end_date
        string previous_rental_type
        string new_rental_type
        decimal previous_total_price
        decimal new_total_price
        decimal additional_cost "sisa biaya, termasuk listrik"
        decimal electricity_cost "biaya isi listrik tambahan"
        string payment_proof_path
        timestamp created_at
        timestamp updated_at
    }

    users ||--o{ users : "memverifikasi pendaftaran"
    users ||--o{ vehicles : "memiliki"
    users ||--o{ rentals : "menyewa"
    users |o--o{ rentals : "memverifikasi pembayaran"
    users ||--o{ garage_batches : "membuat"
    users |o--o{ garage_prices : "mengubah harga"
    users ||--o{ rental_extensions : "memproses"
    garage_batches ||--|{ garages : "menghasilkan"
    vehicles ||--o{ rentals : "digunakan dalam"
    garages ||--o{ rentals : "disewa dalam"
    rentals ||--o{ rental_extensions : "diperpanjang melalui"
```

## Catatan

- Tidak ada tabel `penyewa`. Data penyewa (NIK, status verifikasi) ada di `users`, dibedakan lewat kolom `role`.
- Tidak ada tabel `tipe_garasi`. Tipe berupa kolom `type`, dan `garage_prices` menyimpan satu harga per tipe (relasi logis lewat `type`, bukan foreign key).
- Tidak ada tabel `pembayaran`. Bukti bayar ada di `rentals.payment_proof_path` (sewa awal) dan `rental_extensions.payment_proof_path` (perpanjangan).
- Status sewa (aktif/selesai) tidak disimpan, bisa diturunkan dari `end_date`.
