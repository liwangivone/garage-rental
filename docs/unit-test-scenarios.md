# Skenario Pengujian Garage Rental (Mermaid)

Dokumen ini memetakan seluruh skenario test otomatis di folder `tests/` dalam bentuk diagram Mermaid. Isinya dibaca langsung dari kode test, bukan dari asumsi.

**Bagian A: Unit test** (`tests/Unit`): 10 file, 106 method test.
**Bagian B: Feature / integration test** (`tests/Feature`): 4 file, 42 method test. Ini bukan unit test murni karena memanggil HTTP, seeder, dan scheduler. Bagian ini disertakan supaya gambaran pengujian lengkap.

## Ringkasan

| # | File test | Yang diuji | Jenis | Method | Kasus (termasuk data provider) | DB |
|---|---|---|---|---:|---:|---|
| A1 | `Unit/EnumsTest` | `VehicleType`, `GarageType`, `PaymentStatus` | murni (PHPUnit) | 6 | 17 | tidak |
| A2 | `Unit/RentalPeriodCalculatorTest` | `RentalPeriodCalculator` | murni (PHPUnit) | 17 | 36 | tidak |
| A3 | `Unit/Support/FormatTest` | `Format::rupiah` | murni (PHPUnit) | 1 | 8 | tidak |
| A4 | `Unit/Models/GarageTest` | model `Garage` | Laravel + DB | 13 | 22 | ya |
| A5 | `Unit/Models/RentalTest` | model `Rental` | Laravel + DB | 6 | 11 | ya |
| A6 | `Unit/Models/VehicleAndPricingTest` | `Vehicle`, `GarageBatch`, `GaragePrice` | Laravel + DB | 7 | 7 | ya |
| A7 | `Unit/Services/GarageBatchServiceTest` | `GarageBatchService` | Laravel + DB | 9 | 9 | ya |
| A8 | `Unit/Services/RentalBookingServiceTest` | `RentalBookingService` | Laravel + DB | 27 | 30 | ya |
| A9 | `Unit/Services/RentalExtensionServiceTest` | `RentalExtensionService` | Laravel + DB | 9 | 9 | ya |
| A10 | `Unit/Services/RentalReportServiceTest` | `RentalReportService` | Laravel + DB | 11 | 11 | ya |
| | **Total Unit** | | | **106** | **160** | |
| B1 | `Feature/GarageRentalSchemaTest` | skema, seeder, relasi, bentrok | Feature | 6 | 6 | ya |
| B2 | `Feature/PenyewaPortalTest` | portal penyewa (HTTP) | Feature | 14 | 14 | ya |
| B3 | `Feature/AdminPanelTest` | panel admin (HTTP) | Feature | 10 | 10 | ya |
| B4 | `Feature/BusinessRulesTest` | aturan bisnis end to end | Feature | 12 | 12 | ya |
| | **Total Feature** | | | **42** | **42** | |

Catatan teknis:

- Test yang memakai database memakai trait `RefreshDatabase` dan helper `Tests\Concerns\BuildsFixtures` (`garage()`, `vehicle()`, `rental()`, `adminUser()`, `penyewaUser()`). `rental()` menghitung biaya lewat `RentalPeriodCalculator`.
- Waktu dibekukan lewat `freezeToday()`: **Sabtu, 10 Oktober 2026 pukul 10:00 WIB**. Bulan Oktober punya 31 hari, jadi batas sewa harian maksimal 30 hari.
- `Storage::fake('public')` dipakai untuk semua test yang mengunggah bukti bayar.
- Kolom "Kasus" menghitung setiap baris data provider sebagai satu kasus. Satu method dengan data provider dihitung sekali di kolom "Method".

Cara membaca diagram: kotak biru adalah file test, kotak kuning adalah method test (`test_...`), dan kotak hijau adalah skenario atau assertion. Tanda `[X]` berarti skenario negatif, yaitu sistem harus menolak atau mengembalikan error. `[OK]` berarti skenario positif.

---

## Peta keseluruhan

```mermaid
flowchart LR
    ROOT(["Pengujian Garage Rental<br/>106 unit method + 42 feature method"])

    ROOT --> U["A. Unit test"]
    ROOT --> F["B. Feature test"]

    U --> U1["Enum dan util murni"]
    U --> U2["Model"]
    U --> U3["Service"]

    U1 --> A1["A1 EnumsTest<br/>6 method"]
    U1 --> A2["A2 RentalPeriodCalculatorTest<br/>17 method"]
    U1 --> A3["A3 FormatTest<br/>1 method"]

    U2 --> A4["A4 GarageTest<br/>13 method"]
    U2 --> A5["A5 RentalTest<br/>6 method"]
    U2 --> A6["A6 VehicleAndPricingTest<br/>7 method"]

    U3 --> A7["A7 GarageBatchServiceTest<br/>9 method"]
    U3 --> A8["A8 RentalBookingServiceTest<br/>27 method"]
    U3 --> A9["A9 RentalExtensionServiceTest<br/>9 method"]
    U3 --> A10["A10 RentalReportServiceTest<br/>11 method"]

    F --> B1["B1 GarageRentalSchemaTest<br/>6 method"]
    F --> B2["B2 PenyewaPortalTest<br/>14 method"]
    F --> B3["B3 AdminPanelTest<br/>10 method"]
    F --> B4["B4 BusinessRulesTest<br/>12 method"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef group fill:#e5e7eb,stroke:#6b7280,color:#111;
    class A1,A2,A3,A4,A5,A6,A7,A8,A9,A10,B1,B2,B3,B4 suite;
    class U,F,U1,U2,U3 group;
```

### Cakupan menurut kelas yang diuji

```mermaid
flowchart LR
    subgraph SUT["Kelas yang diuji"]
        VT["VehicleType"]
        GT["GarageType"]
        PS["PaymentStatus"]
        CALC["RentalPeriodCalculator"]
        FMT["Format"]
        GAR["Garage"]
        REN["Rental"]
        VEH["Vehicle"]
        GB["GarageBatch"]
        GP["GaragePrice"]
        USR["User"]
        SB["GarageBatchService"]
        SBK["RentalBookingService"]
        SEX["RentalExtensionService"]
        SRP["RentalReportService"]
        CMD["SyncGarageStatus"]
        MW["Middleware dan Controller"]
        REQ["StoreRentalRequest"]
    end

    A1["A1 EnumsTest"] --> VT & GT & PS
    A2["A2 CalculatorTest"] --> CALC
    A3["A3 FormatTest"] --> FMT
    A4["A4 GarageTest"] --> GAR
    A5["A5 RentalTest"] --> REN
    A6["A6 VehicleAndPricingTest"] --> VEH & GB & GP
    A7["A7 BatchServiceTest"] --> SB
    A8["A8 BookingServiceTest"] --> SBK
    A9["A9 ExtensionServiceTest"] --> SEX
    A10["A10 ReportServiceTest"] --> SRP
    B1["B1 SchemaTest"] --> USR & GAR & REN & VEH
    B2["B2 PenyewaPortalTest"] --> MW & REQ & SBK
    B3["B3 AdminPanelTest"] --> MW & SEX & SB & SRP
    B4["B4 BusinessRulesTest"] --> MW & CMD & GAR & USR
```

---

# Bagian A. Unit test

## A1. `EnumsTest` (6 method, 17 kasus)

```mermaid
flowchart LR
    S["EnumsTest<br/>PHPUnit murni, tanpa DB"]

    S --> T1["test_vehicle_garage_compatibility<br/>data provider, 12 kasus"]
    T1 --> T1a["Sepeda: garasi sepeda [OK], mobil [X], listrik [X]"]
    T1 --> T1b["Motor: garasi sepeda [OK], mobil [X], listrik [X]"]
    T1 --> T1c["Mobil: garasi mobil [OK], sepeda [X], listrik [X]"]
    T1 --> T1d["Mobil listrik: garasi mobil [OK], garasi listrik [OK], sepeda [X]"]
    T1 --> T1e["canUseGarage sama dengan hasil in_array allowedGarageTypes"]

    S --> T2["test_every_vehicle_type_has_at_least_one_garage_type"]
    T2 --> T2a["allowedGarageTypes tidak pernah kosong untuk semua VehicleType"]

    S --> T3["test_plate_and_battery_requirements"]
    T3 --> T3a["Plat wajib: motor, mobil, mobil listrik. Sepeda tidak"]
    T3 --> T3b["Kapasitas baterai wajib hanya untuk mobil listrik"]

    S --> T4["test_garage_code_prefix_and_electricity_price"]
    T4 --> T4a["Prefix kode: S sepeda, C mobil, E mobil listrik"]
    T4 --> T4b["hasElectricityPrice hanya benar untuk mobil listrik"]

    S --> T5["test_labels_are_present_and_unique"]
    T5 --> T5a["Label GarageType, VehicleType, PaymentStatus tidak kosong dan tidak ada yang kembar"]

    S --> T6["test_stored_values_are_stable"]
    T6 --> T6a["Nilai tersimpan GarageType: bicycle, car, electric_car"]
    T6 --> T6b["VehicleType: bicycle, motorcycle, car, electric_car"]
    T6 --> T6c["PaymentStatus: pending, approved, rejected"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6 test;
    class T1a,T1b,T1c,T1d,T1e,T2a,T3a,T3b,T4a,T4b,T5a,T6a,T6b,T6c leaf;
```

## A2. `RentalPeriodCalculatorTest` (17 method, 36 kasus)

Aturan inti yang diuji: ambang bulanan sama dengan jumlah hari di bulan tanggal mulai. Durasi di bawah ambang dihitung harian. Durasi sama dengan atau di atas ambang dihitung 1 blok bulanan (25 x harga harian) ditambah sisa hari harian.

```mermaid
flowchart LR
    S["RentalPeriodCalculatorTest<br/>PHPUnit murni, tanpa DB"]

    subgraph KLASIFIKASI["Klasifikasi harian atau bulanan"]
        T1["test_classify<br/>data provider, 8 kasus"]
        T1 --> T1a["Feb 2024 kabisat: 28 hari harian, 29 hari bulanan"]
        T1 --> T1b["Feb 2025: 27 hari harian, 28 hari bulanan"]
        T1 --> T1c["Okt: 30 hari harian, 31 hari bulanan"]
        T1 --> T1d["April: tepat 30 hari bulanan"]
        T1 --> T1e["Sehari harian"]

        T2["test_monthly_threshold_is_days_in_start_month<br/>data provider, 13 kasus"]
        T2 --> T2a["Jan 31, Feb 28, Feb kabisat 29, Mar 31, Apr 30, Mei 31, Jun 30"]
        T2 --> T2b["Jul 31, Agu 31, Sep 30, Okt 31, Nov 30, Des 31"]

        T3["test_threshold_follows_start_month_not_end_month"]
        T3 --> T3a["Mulai 25 Feb 2025 sampai 24 Mar: bulanan"]
        T3 --> T3b["Mulai 25 Mar 2025 sampai 21 Apr: harian"]

        T4["test_year_boundary"]
        T4 --> T4a["20 Des 2025 sampai 19 Jan 2026 (31 hari): bulanan"]
        T4 --> T4b["20 Des 2025 sampai 18 Jan 2026 (30 hari): harian"]
        T4 --> T4c["Akhir bulanan paling awal: 19 Jan 2026"]
    end

    subgraph DURASI["Durasi dan batas"]
        T5["test_single_rental_is_limited_to_one_month"]
        T5 --> T5a["Feb 2024: maksimal 29 hari, 30 hari ditolak"]
        T5 --> T5b["Oktober: maksimal 31 hari, 32 hari ditolak"]
        T5 --> T5c["Tanggal selesai sebelum mulai: ditolak"]

        T6["test_duration_is_inclusive"]
        T6 --> T6a["1 Okt sampai 1 Okt = 1 hari"]
        T6 --> T6b["27 Feb sampai 26 Mar 2024 = 29 hari"]

        T7["test_time_of_day_is_ignored"]
        T7 --> T7a["23:59:59 ke 00:00:01 hari berikutnya = 2 hari"]
        T7 --> T7b["Hari sama, jam berbeda = 1 hari"]

        T8["test_end_date_and_earliest_monthly_end_date"]
        T8 --> T8a["endDateForDays 1 Okt + 3 hari = 3 Okt"]
        T8 --> T8b["Akhir bulanan paling awal: 26 Mar 2024, 31 Okt 2025"]

        T9["test_start_at_month_end_crosses_into_next_month"]
        T9 --> T9a["Mulai 31 Jan 2025: 2 Maret"]
        T9 --> T9b["Mulai 31 Jan 2024 kabisat: 1 Maret"]
    end

    subgraph TANGGAL["Validasi tanggal mulai"]
        T10["test_start_date_cannot_be_backdated"]
        T10 --> T10a["Hari ini, besok, +7 hari: valid"]
        T10 --> T10b["Kemarin: tidak valid [X]"]

        T11["test_start_date_validation_uses_calendar_day"]
        T11 --> T11a["Pukul 23:30, tanggal hari ini pukul 00:00: valid"]
        T11 --> T11b["Kemarin 23:59: tidak valid [X]"]
    end

    subgraph BIAYA["Rincian dan biaya"]
        T12["test_breakdown_for_new_and_extended_rentals"]
        T12 --> T12a["10 hari: 0 bulan, 10 hari"]
        T12 --> T12b["31 hari: 1 bulan, 0 hari"]
        T12 --> T12c["Perpanjangan 1 Okt sampai 5 Nov: 1 bulan + 5 hari"]
        T12 --> T12d["1 Okt sampai 31 Des: 1 bulan + 61 hari"]
        T12 --> T12e["Feb 2024 kabisat sampai 31 Mar: 1 bulan + 5 hari"]

        T13["test_breakdown_edges"]
        T13 --> T13a["32 hari: 1 bulan + 1 hari"]
        T13 --> T13b["30 hari Okt: 0 bulan, 30 hari"]
        T13 --> T13c["Feb 2025: 28 hari bulanan, 27 hari harian"]
        T13 --> T13d["Perpanjangan 11 bulan: 1 bulan + 305 hari"]

        T14["test_rental_cost"]
        T14 --> T14a["3 hari = 300.000"]
        T14 --> T14b["1 bulan = 2.500.000 (25 x harian)"]
        T14 --> T14c["1 bulan + 5 hari = 3.000.000"]
        T14 --> T14d["92 hari = 8.600.000"]

        T15["test_cost_keeps_decimal_precision"]
        T15 --> T15a["12.500,50 x 3 hari = 37.501,50"]
        T15 --> T15b["Bulanan = 312.512,50"]
        T15 --> T15c["Harga 0 = 0,00"]

        T16["test_end_before_start_yields_nothing_billable"]
        T16 --> T16a["breakdown 0 bulan 0 hari, biaya 0,00, bukan dalam batas durasi"]

        T17["test_extension_cost_can_drop_when_crossing_into_monthly"]
        T17 --> T17a["30 hari harian 3.000.000 lebih mahal dari 31 hari bulanan 2.500.000"]
        T17 --> T17b["Selisih -500.000 (aturan harga yang diketahui)"]
    end

    S --> KLASIFIKASI
    S --> DURASI
    S --> TANGGAL
    S --> BIAYA

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11,T12,T13,T14,T15,T16,T17 test;
    class T1a,T1b,T1c,T1d,T1e,T2a,T2b,T3a,T3b,T4a,T4b,T4c,T5a,T5b,T5c,T6a,T6b,T7a,T7b,T8a,T8b,T9a,T9b,T10a,T10b,T11a,T11b,T12a,T12b,T12c,T12d,T12e,T13a,T13b,T13c,T13d,T14a,T14b,T14c,T14d,T15a,T15b,T15c,T16a,T17a,T17b leaf;
```

## A3. `FormatTest` (1 method, 8 kasus)

```mermaid
flowchart LR
    S["FormatTest"] --> T["test_rupiah<br/>data provider, 8 kasus"]
    T --> C1["0 menjadi Rp 0"]
    T --> C2["null dianggap nol: Rp 0"]
    T --> C3["75000 menjadi Rp 75.000"]
    T --> C4["1500000 menjadi Rp 1.500.000"]
    T --> C5["String desimal dari database 75000.00 menjadi Rp 75.000"]
    T --> C6["1234.56 dibulatkan menjadi Rp 1.235"]
    T --> C7["String angka 2500000 menjadi Rp 2.500.000"]
    T --> C8["Negatif -500000 menjadi Rp -500.000"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T test;
    class C1,C2,C3,C4,C5,C6,C7,C8 leaf;
```

## A4. `GarageTest` (13 method, 22 kasus)

Data uji: sewa terisi pada 20 s.d. 22 Okt 2026. Hari ini dibekukan di 10 Okt 2026.

```mermaid
flowchart LR
    S["GarageTest<br/>Model Garage, RefreshDatabase"]

    subgraph KODE["Kode dan nomor urut"]
        T1["test_make_code_pads_to_three_digits<br/>data provider, 4 kasus"]
        T1 --> T1a["S001, C010, C100, E999"]
        T2["test_next_sequence_is_per_type_and_uses_max"]
        T2 --> T2a["Tanpa data: mulai 1"]
        T2 --> T2b["Memakai max + 1 (1 dan 5 menghasilkan 6), bukan jumlah + 1"]
        T2 --> T2c["Tiap tipe berdiri sendiri"]
    end

    subgraph HARGA["Accessor harga"]
        T3["test_price_accessors_come_from_type_price"]
        T3 --> T3a["daily 80.000, monthly 2.000.000, listrik null untuk mobil"]
        T3 --> T3b["Garasi kedua tidak menimpa harga tipe yang sudah ada"]
        T3 --> T3c["Harga tipe diubah: semua garasi tipe itu ikut berubah"]
        T4["test_price_accessors_are_null_without_price"]
        T4 --> T4a["Tanpa GaragePrice: daily_price dan monthly_price null"]
    end

    subgraph KETERSEDIAAN["Ketersediaan dan bentrok"]
        T5["test_availability_boundaries<br/>data provider, 7 kasus"]
        T5 --> T5a["Berakhir sehari sebelum mulai: bebas [OK]"]
        T5 --> T5b["Berakhir tepat di hari mulai: bentrok [X]"]
        T5 --> T5c["Di dalam, mulai di hari terakhir, membungkus penuh: bentrok [X]"]
        T5 --> T5d["Sehari setelah selesai: bebas [OK]"]
        T5 --> T5e["isFreeBetween dan scope availableBetween konsisten"]
        T6["test_pending_blocks_but_rejected_does_not"]
        T6 --> T6a["Rejected tidak menahan garasi"]
        T6 --> T6b["Pending menahan garasi"]
        T7["test_free_between_can_ignore_a_rental"]
        T7 --> T7a["Bentrok dengan sewa sendiri, kecuali ignoreRentalId diberikan"]
        T8["test_rented_on_and_available_on_scopes"]
        T8 --> T8a["rentedOn dan notRentedOn hanya sewa aktif hari ini"]
        T8 --> T8b["availableOn hari ini dan di tanggal lain"]
        T9["test_for_vehicle_type_scope"]
        T9 --> T9a["Motor: S001. Mobil: C001. Mobil listrik: C001 dan E001"]
    end

    subgraph CURRENT["currentRental"]
        T10["test_current_rental_ignores_rejected_and_other_days"]
        T10 --> T10a["Sewa selesai, belum mulai, atau rejected: null"]
        T10 --> T10b["Sewa 1 hari hari ini: terdeteksi"]
        T11["test_current_rental_found_even_when_a_later_rental_exists"]
        T11 --> T11a["Regresi: sewa mendatang tidak menutupi sewa yang sedang berjalan"]
        T11 --> T11b["Berlaku juga untuk eager loading with currentRental"]
    end

    subgraph STATUS["Sinkron status"]
        T12["test_sync_status_transitions"]
        T12 --> T12a["Sewa mendatang: tetap available"]
        T12 --> T12b["Hari pertama sewa: used"]
        T12 --> T12c["Hari terakhir: masih used. Sehari setelahnya: available"]
        T12 --> T12d["Sewa ditolak: garasi dilepas jadi available"]
        T13["test_sync_all_statuses_updates_in_bulk"]
        T13 --> T13a["Garasi disewa menjadi used"]
        T13 --> T13b["Status basi used tanpa sewa menjadi available"]
    end

    S --> KODE
    S --> HARGA
    S --> KETERSEDIAAN
    S --> CURRENT
    S --> STATUS

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11,T12,T13 test;
    class T1a,T2a,T2b,T2c,T3a,T3b,T3c,T4a,T5a,T5b,T5c,T5d,T5e,T6a,T6b,T7a,T8a,T8b,T9a,T10a,T10b,T11a,T11b,T12a,T12b,T12c,T12d,T13a,T13b leaf;
```

## A5. `RentalTest` (6 method, 11 kasus)

```mermaid
flowchart LR
    S["RentalTest<br/>Model Rental, hari ini = 10 Okt 2026"]

    S --> T1["test_phase<br/>data provider, 6 kasus"]
    T1 --> T1a["Mulai besok: upcoming"]
    T1 --> T1b["Mulai hari ini, di tengah, selesai hari ini, sehari hari ini: active"]
    T1 --> T1c["Selesai kemarin: finished"]
    T1 --> T1d["isActiveOn benar hanya bila phase sama dengan active"]

    S --> T2["test_payment_scopes_and_flag"]
    T2 --> T2a["approved: hanya status Approved"]
    T2 --> T2b["occupying: Approved dan Pending, tanpa Rejected"]
    T2 --> T2c["isPaymentApproved benar hanya untuk Approved"]

    S --> T3["test_extendable_error_by_payment_and_end_date"]
    T3 --> T3a["Belum mulai dan terverifikasi: boleh diperpanjang [OK]"]
    T3 --> T3b["Selesai hari ini: masih boleh [OK]"]
    T3 --> T3c["Sudah berakhir: pesan berisi berakhir [X]"]
    T3 --> T3d["Pending atau Rejected: pesan berisi Verifikasi [X]"]

    S --> T4["test_extension_error_matrix"]
    T4 --> T4a["Tanggal sama atau lebih awal: setelah tanggal selesai [X]"]
    T4 --> T4b["Tanggal lebih akhir: boleh, canExtendTo benar [OK]"]
    T4 --> T4c["Bentrok sewa lain di garasi sama: pesan garasi ini [X]"]
    T4 --> T4d["Berhenti sehari sebelum sewa lain: boleh [OK]"]
    T4 --> T4e["Bentrok sewa kendaraan sama di garasi lain: pesan kendaraan yang sama [X]"]

    S --> T5["test_rejected_rental_of_others_does_not_block_extension"]
    T5 --> T5a["Sewa Rejected milik orang lain tidak menghalangi perpanjangan"]

    S --> T6["test_casts"]
    T6 --> T6a["start_date adalah Carbon, payment_status adalah enum"]
    T6 --> T6b["total_price 300000.00, duration_days 3"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6 test;
    class T1a,T1b,T1c,T1d,T2a,T2b,T2c,T3a,T3b,T3c,T3d,T4a,T4b,T4c,T4d,T4e,T5a,T6a,T6b leaf;
```

## A6. `VehicleAndPricingTest` (7 method)

```mermaid
flowchart LR
    S["VehicleAndPricingTest<br/>Vehicle, GarageBatch, GaragePrice"]

    S --> T1["test_vehicle_rental_overlap_boundaries"]
    T1 --> T1a["Sewa 20-22 Okt. Cek 18-19: tidak bentrok [OK]"]
    T1 --> T1b["19-20 dan 22-25: bentrok [X]"]
    T1 --> T1c["23-25: tidak bentrok, sewa kedua setelah yang pertama selesai [OK]"]

    S --> T2["test_vehicle_overlap_ignores_rejected_and_given_rental"]
    T2 --> T2a["Rejected tidak dihitung bentrok"]
    T2 --> T2b["Pending dihitung bentrok"]
    T2 --> T2c["ignoreRentalId mengecualikan sewa itu sendiri"]

    S --> T3["test_vehicle_overlap_is_per_vehicle"]
    T3 --> T3a["Sewa kendaraan A tidak memengaruhi kendaraan B"]

    S --> T4["test_vehicle_can_use_garage_type_and_casts"]
    T4 --> T4a["type jadi enum, battery_capacity_kwh 57.50"]
    T4 --> T4b["Mobil listrik: boleh garasi mobil dan listrik, tidak boleh garasi sepeda"]

    S --> T5["test_plate_number_may_repeat_across_vehicles"]
    T5 --> T5a["Dua kendaraan dengan plat sama tersimpan, jumlah 2"]

    S --> T6["test_monthly_price_is_25_times_daily"]
    T6 --> T6a["GarageBatch dan GaragePrice: 12.500,50 x 25 = 312.512,50"]
    T6 --> T6b["MONTHLY_PRICE_MULTIPLIER = 25"]

    S --> T7["test_garage_price_lookup"]
    T7 --> T7a["Belum ada harga: forType null"]
    T7 --> T7b["Setelah garasi dibuat: harga 75.000 untuk mobil"]
    T7 --> T7c["Tipe lain tetap null"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7 test;
    class T1a,T1b,T1c,T2a,T2b,T2c,T3a,T4a,T4b,T5a,T6a,T6b,T7a,T7b,T7c leaf;
```

## A7. `GarageBatchServiceTest` (9 method)

```mermaid
flowchart LR
    S["GarageBatchServiceTest<br/>GarageBatchService create"]

    subgraph PENOMORAN["Penomoran kode"]
        T1["test_creates_sequential_codes_all_available"]
        T1 --> T1a["3 garasi mobil: C001, C002, C003"]
        T1 --> T1b["quantity 3, created_by sama dengan admin, semua status available"]
        T2["test_second_batch_continues_numbering"]
        T2 --> T2a["Batch kedua lanjut: C001 sampai C004"]
        T3["test_numbering_is_independent_per_type"]
        T3 --> T3a["S001, C001-C002, E001 berdiri sendiri"]
    end

    subgraph HARGA["Harga tipe"]
        T4["test_type_price_is_created_then_updated_not_duplicated"]
        T4 --> T4a["Harga dibuat 100.000, lalu diperbarui menjadi 150.000"]
        T4 --> T4b["Hanya 1 baris GaragePrice per tipe"]
        T4 --> T4c["Riwayat batch tetap 2 baris"]
        T5["test_electricity_price_only_kept_for_electric_garages"]
        T5 --> T5a["Harga listrik diabaikan untuk garasi mobil (null)"]
        T5 --> T5b["Garasi mobil listrik menyimpan 3.500"]
    end

    subgraph KUOTA["Batas 999 per tipe"]
        T6["test_exactly_999_garages_is_allowed"]
        T6 --> T6a["999 garasi diterima, kode terakhir S999 [OK]"]
        T7["test_exceeding_999_is_rejected_and_nothing_changes"]
        T7 --> T7a["Garasi ke-1000: ValidationException pada quantity [X]"]
        T7 --> T7b["Jumlah garasi, batch, dan harga tidak berubah (atomic)"]
        T8["test_remaining_quota_is_reported"]
        T8 --> T8a["Pesan berisi Sisa kuota dan 9 garasi"]
        T8 --> T8b["Menambah tepat sisa kuota (9) berhasil, total 999"]
        T9["test_full_type_does_not_block_other_types"]
        T9 --> T9a["Sepeda penuh, mobil tetap bisa dibuat (C001)"]
    end

    S --> PENOMORAN
    S --> HARGA
    S --> KUOTA

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9 test;
    class T1a,T1b,T2a,T3a,T4a,T4b,T4c,T5a,T5b,T6a,T7a,T7b,T8a,T8b,T9a leaf;
```

## A8. `RentalBookingServiceTest` (27 method, 30 kasus)

Data uji: hari ini 10 Okt 2026, penyewa disetujui, garasi mobil C001 harga 100.000/hari. Dibagi dua diagram: `quote` (hitung dan validasi) dan `book` (simpan transaksi).

### A8.1 `quote` dan `quoteForJson`

```mermaid
flowchart LR
    S["RentalBookingServiceTest<br/>quote, quoteForJson"]

    subgraph TGL["Tanggal dan durasi"]
        T1["test_max_daily_days<br/>data provider, 4 kasus"]
        T1 --> T1a["Okt 30, Apr 29, Feb 2027 27, Feb 2028 kabisat 28"]
        T2["test_valid_daily_quote"]
        T2 --> T2a["Tanpa error, selesai 12 Okt (inklusif), 3 hari"]
        T2 --> T2b["Total 300.000, listrik 0,00, max_daily_days 30"]
        T3["test_valid_monthly_quote"]
        T3 --> T3a["Mulai 15 Okt selesai 14 Nov (31 hari), 1 bulan, total 2.500.000"]
        T4["test_start_date_cannot_be_in_the_past"]
        T4 --> T4a["Kemarin: error start_date, costs dan end null [X]"]
        T5["test_start_date_limited_to_30_days_ahead"]
        T5 --> T5a["+30 hari: valid [OK]"]
        T5 --> T5b["+31 hari: error start_date [X]"]
        T6["test_daily_days_bounds"]
        T6 --> T6a["0 dan 31 hari: error days [X]"]
        T6 --> T6b["1 dan 30 hari: valid [OK]"]
        T7["test_daily_days_bound_shrinks_in_short_month"]
        T7 --> T7a["Feb 2027: 28 hari ditolak, 27 hari diterima, max 27"]
        T8["test_missing_inputs_do_not_crash"]
        T8 --> T8a["Input kosong: end, costs null dan garages kosong"]
        T8 --> T8b["Tanggal bukan-tanggal: error start_date"]
    end

    subgraph GRS["Kendaraan, garasi, dan bentrok"]
        T9["test_incompatible_garage_type_is_rejected"]
        T9 --> T9a["Mobil di garasi listrik: error garage_type dan garage_id, costs null"]
        T10["test_motorcycle_cannot_take_car_garage"]
        T10 --> T10a["Motor di garasi mobil: error garage_id [X]"]
        T11["test_garage_list_excludes_occupied_but_not_rejected"]
        T11 --> T11a["Daftar garasi hanya C002 (C001 terisi, sewa Rejected tidak menahan)"]
        T12["test_occupied_garage_is_rejected"]
        T12 --> T12a["Garasi bentrok di 11 Okt: error garage_id, costs null [X]"]
        T13["test_garage_list_empty_without_garage_type"]
        T13 --> T13a["Tanpa garage_type: daftar garasi kosong"]
        T14["test_unknown_garage_is_rejected"]
        T14 --> T14a["garage_id 99999: error garage_id [X]"]
        T15["test_cannot_use_someone_elses_vehicle"]
        T15 --> T15a["Kendaraan milik penyewa lain: error vehicle_id [X]"]
        T16["test_vehicle_overlap_is_rejected_but_back_to_back_is_fine"]
        T16 --> T16a["Kendaraan sama mulai 12 Okt (bertumpuk): error vehicle_id [X]"]
        T16 --> T16b["Mulai 13 Okt (berurutan): valid [OK]"]
        T17["test_booking_fails_when_type_has_no_price"]
        T17 --> T17a["Tanpa GaragePrice: error garage_id, costs null [X]"]
    end

    subgraph LST["Biaya listrik mobil listrik"]
        T18["test_electricity_cost_is_capacity_minus_current_battery_times_price"]
        T18 --> T18a["57,5 - 15 = 42,5 kWh x 3.000 = 127.500, total 367.500"]
        T18 --> T18b["Baterai penuh: listrik 0,00"]
        T18 --> T18c["Baterai kosong: 172.500 (kapasitas penuh)"]
        T18 --> T18d["Mobil listrik di garasi mobil biasa: listrik 0 dan tanpa error baterai"]
        T19["test_current_battery_is_required_and_cannot_exceed_capacity"]
        T19 --> T19a["Sisa baterai kosong: error battery_current_kwh [X]"]
        T19 --> T19b["Sisa 50,5 lebih dari kapasitas 50: error [X]"]
        T19 --> T19c["Nilai abc: error [X]"]
        T20["test_saved_electric_vehicle_uses_its_own_battery"]
        T20 --> T20a["Kendaraan tersimpan 40 kWh, sisa 10: listrik 90.000, charge 30,00"]
    end

    subgraph JSN["Format JSON"]
        T21["test_quote_for_json_shape"]
        T21 --> T21a["end_date 2026-10-12, duration 3, total Rp 300.000"]
        T21 --> T21b["has_electricity false, allowed_garage_types car"]
        T21 --> T21c["Garasi C001: harian Rp 100.000, bulanan Rp 2.500.000"]
    end

    S --> TGL
    S --> GRS
    S --> LST
    S --> JSN

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11,T12,T13,T14,T15,T16,T17,T18,T19,T20,T21 test;
    class T1a,T2a,T2b,T3a,T4a,T5a,T5b,T6a,T6b,T7a,T8a,T8b,T9a,T10a,T11a,T12a,T13a,T14a,T15a,T16a,T16b,T17a,T18a,T18b,T18c,T18d,T19a,T19b,T19c,T20a,T21a,T21b,T21c leaf;
```

### A8.2 `book`

```mermaid
flowchart LR
    S["RentalBookingServiceTest<br/>book, transaksi simpan"]

    S --> T1["test_book_creates_pending_rental_with_snapshot_and_vehicle"]
    T1 --> T1a["Status Pending, tipe Daily, total 300.000"]
    T1 --> T1b["Snapshot harga: daily 100.000, monthly 2.500.000, daily_count 3"]
    T1 --> T1c["Kendaraan baru dibuat dengan plat DD 1 AB milik penyewa"]
    T1 --> T1d["Bukti bayar tersimpan di storage"]
    T1 --> T1e["Harga tipe diubah kemudian: sewa lama tetap 100.000"]

    S --> T2["test_book_monthly_records_one_monthly_block"]
    T2 --> T2a["Mulai 20 Okt: tipe Monthly, 1 bulan, 0 hari, total 2.500.000, selesai 19 Nov"]

    S --> T3["test_book_reuses_saved_vehicle"]
    T3 --> T3a["vehicle_id dipakai ulang, jumlah Vehicle tetap 1"]

    S --> T4["test_garage_status_depends_on_start_date"]
    T4 --> T4a["Mulai hari ini: garasi Used"]
    T4 --> T4b["Mulai 20 Okt: garasi Available"]

    S --> T5["test_invalid_booking_is_atomic"]
    T5 --> T5a["Booking kedua bentrok: ValidationException pada garage_id [X]"]
    T5 --> T5b["Rental tetap 1 dan kendaraan baru tidak tersimpan (rollback)"]

    S --> T6["test_booking_requires_a_garage"]
    T6 --> T6a["Tanpa garage_id: pesan Pilih garasi yang tersedia [X]"]
    T6 --> T6b["Tidak ada Rental yang dibuat"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6 test;
    class T1a,T1b,T1c,T1d,T1e,T2a,T3a,T4a,T4b,T5a,T5b,T6a,T6b leaf;
```

## A9. `RentalExtensionServiceTest` (9 method)

```mermaid
flowchart LR
    S["RentalExtensionServiceTest<br/>preview dan extend"]

    subgraph PRV["preview"]
        T1["test_preview_within_daily_range"]
        T1 --> T1a["Sewa 5 hari (500.000) menjadi 10 hari: total 1.000.000, tambahan 500.000"]
        T2["test_preview_crossing_into_monthly"]
        T2 --> T2a["36 hari: 1 bulan + 5 hari, total 3.000.000, tambahan 2.500.000"]
        T3["test_preview_can_be_negative_when_crossing_the_monthly_threshold"]
        T3 --> T3a["30 hari menjadi 31 hari: tambahan -500.000 (keputusan bisnis, dibiarkan)"]
        T4["test_preview_uses_price_snapshot_not_current_price"]
        T4 --> T4a["Harga tipe naik 500.000, perpanjangan tetap memakai 100.000/hari"]
        T5["test_preview_adds_extra_electricity"]
        T5 --> T5a["Listrik 100.000 + extra 50.000 = 150.000"]
        T5 --> T5b["Biaya sewa 6 hari 600.000, total baru 750.000, tambahan 150.000"]
        T6["test_preview_without_extra_electricity_keeps_existing"]
        T6 --> T6a["Tanpa extra: listrik tetap 100.000, extra_electricity 0,00"]
    end

    subgraph EXT["extend"]
        T7["test_extend_updates_rental_and_records_history"]
        T7 --> T7a["Rental diperbarui: selesai 20 Okt, 13 hari, rental 1.300.000, listrik 25.000, total 1.325.000"]
        T7 --> T7b["Riwayat: rental_id, processed_by admin, tanggal lama dan baru"]
        T7 --> T7c["Total lama 500.000, total baru 1.325.000, tambahan 825.000"]
        T7 --> T7d["Bukti bayar perpanjangan tersimpan"]
        T8["test_extend_into_monthly_changes_type"]
        T8 --> T8a["Riwayat: Daily menjadi Monthly, Rental menjadi Monthly"]
        T8 --> T8b["monthly_count 1 dan daily_count 54 - 31 = 23"]
        T9["test_extend_keeps_garage_status_in_sync"]
        T9 --> T9a["Sewa aktif diperpanjang: garasi Used"]
        T9 --> T9b["Sewa mendatang diperpanjang: garasi tetap Available"]
    end

    S --> PRV
    S --> EXT

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9 test;
    class T1a,T2a,T3a,T4a,T5a,T5b,T6a,T7a,T7b,T7c,T7d,T8a,T8b,T9a,T9b leaf;
```

## A10. `RentalReportServiceTest` (11 method)

```mermaid
flowchart LR
    S["RentalReportServiceTest<br/>RentalReportService build"]

    S --> T1["test_monthly_has_twelve_rows_and_groups_by_start_month"]
    T1 --> T1a["12 baris. Maret: 2 sewa 300.000. Mei: 1 sewa 400.000"]
    T1 --> T1b["Sewa 30 Mei sampai 2 Jun dihitung di Mei (tanggal mulai). Juni kosong"]
    T1 --> T1c["Total: 3 sewa, pendapatan 700.000"]

    S --> T2["test_only_approved_payments_are_counted"]
    T2 --> T2a["Approved, Pending, Rejected: hanya Approved dihitung (1 sewa, 100.000)"]

    S --> T3["test_year_boundary_uses_start_date"]
    T3 --> T3a["Mulai 31 Des 2025: masuk laporan 2025, bukan 2026"]

    S --> T4["test_electricity_is_reported_separately"]
    T4 --> T4a["rental 200.000, electricity 50.000, revenue 250.000, per baris juga benar"]

    S --> T5["test_daily_view_rows_match_days_in_month"]
    T5 --> T5a["Okt 31 baris, Feb 2027 28 baris, Feb 2028 29 baris"]
    T5 --> T5b["Sewa bulan lain tidak ikut dihitung"]

    S --> T6["test_daily_view_defaults_to_current_month"]
    T6 --> T6a["Bulan kosong menjadi bulan berjalan (Okt, 31 baris)"]

    S --> T7["test_by_type_always_lists_all_three_types"]
    T7 --> T7a["Label: Garasi Sepeda, Garasi Mobil, Garasi Mobil Listrik"]
    T7 --> T7b["Jumlah 0, 1, 0"]

    S --> T8["test_by_penyewa_sorted_by_revenue_desc_and_skips_idle_users"]
    T8 --> T8a["Urutan Besar lalu Kecil (pendapatan menurun)"]
    T8 --> T8b["Jumlah 2 dan 1, pendapatan 600.000 dan 100.000"]
    T8 --> T8c["Penyewa tanpa sewa tidak muncul"]

    S --> T9["test_month_filter_applies_to_type_and_penyewa_views_but_not_monthly"]
    T9 --> T9a["Filter bulan berlaku di view type dan penyewa"]
    T9 --> T9b["View monthly mengabaikan filter bulan"]

    S --> T10["test_empty_report"]
    T10 --> T10a["Tanpa data: rows 0, count 0, revenue 0"]

    S --> T11["test_view_list_matches_supported_views"]
    T11 --> T11a["VIEWS: monthly, daily, type, penyewa"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11 test;
    class T1a,T1b,T1c,T2a,T3a,T4a,T5a,T5b,T6a,T7a,T7b,T8a,T8b,T8c,T9a,T9b,T10a,T11a leaf;
```

---

# Bagian B. Feature / integration test

Test di bagian ini memanggil rute HTTP, seeder, dan scheduler. Karena itu bukan unit test murni, dan dipisahkan dari Bagian A.

## B1. `GarageRentalSchemaTest` (6 method)

```mermaid
flowchart LR
    S["GarageRentalSchemaTest<br/>skema, seeder, relasi"]

    S --> T1["test_admin_seeder_creates_admin_and_is_idempotent"]
    T1 --> T1a["AdminSeeder dijalankan 2 kali: tetap 1 admin"]
    T1 --> T1b["Admin: isAdmin, isApproved, nik null"]

    S --> T2["test_new_penyewa_defaults_to_pending"]
    T2 --> T2a["Role Penyewa, status Pending, masuk pendingVerification"]

    S --> T3["test_vehicle_garage_compatibility"]
    T3 --> T3a["Motor: garasi sepeda [OK], mobil [X]"]
    T3 --> T3b["Mobil: garasi mobil [OK], listrik [X]"]
    T3 --> T3c["Mobil listrik: mobil [OK], listrik [OK]"]

    S --> T4["test_garage_codes_and_monthly_price"]
    T4 --> T4a["Kode S001, S002 dan E010"]
    T4 --> T4b["monthly_price 250.000 (25 x 10.000), status awal Available"]

    S --> T5["test_rental_relations_and_availability"]
    T5 --> T5a["Relasi: penyewa, garage.batch, vehicle.owner, extensions.admin, user.rentals"]
    T5 --> T5b["currentRental garasi E001 adalah sewa tersebut"]
    T5 --> T5c["availableOn dan rentedOn hari ini dan +10 hari"]
    T5 --> T5d["forVehicleType: mobil listrik dapat E001 dan E002, mobil biasa kosong"]

    S --> T6["test_overlap_checks_for_second_rental_and_extension"]
    T6 --> T6a["Sewa kedua harus mulai setelah sewa pertama selesai (kendaraan dan garasi)"]
    T6 --> T6b["Perpanjangan mengabaikan sewa sendiri, tetapi sewa lain tetap bentrok"]
    T6 --> T6c["canExtendTo: +39 hari [OK]. +40, +60, +31 [X]"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6 test;
    class T1a,T1b,T2a,T3a,T3b,T3c,T4a,T4b,T5a,T5b,T5c,T5d,T6a,T6b,T6c leaf;
```

## B2. `PenyewaPortalTest` (14 method)

```mermaid
flowchart LR
    S["PenyewaPortalTest<br/>portal penyewa lewat HTTP"]

    subgraph AKUN["Akun dan akses"]
        T1["test_registration_creates_pending_penyewa"]
        T1 --> T1a["POST register: redirect ke /penyewa, user Penyewa Pending, otomatis login"]
        T1 --> T1b["Dashboard menampilkan Menunggu verifikasi"]
        T2["test_registration_validation"]
        T2 --> T2a["NIK 3 digit, nama kosong, telepon abc, email duplikat, password beda: semua error [X]"]
        T2 --> T2b["NIK duplikat ditolak [X]"]
        T3["test_role_separation_and_pending_cannot_rent"]
        T3 --> T3a["Tamu ke /penyewa: redirect ke /login"]
        T3 --> T3b["Penyewa ke /admin: 403. Admin ke /penyewa: 403"]
        T3 --> T3c["Pending: halaman sewa dialihkan dengan error, POST sewa tidak membuat Rental"]
    end

    subgraph SEWA["Membuat sewa"]
        T4["test_daily_rental_with_new_vehicle"]
        T4 --> T4a["Tipe Daily, 3 hari inklusif, total 300.000, plat dinormalkan menjadi DD 1234 AB"]
        T4 --> T4b["Garasi Used, bukti tersimpan, halaman detail menampilkan C001"]
        T5["test_future_start_monthly_and_electric_cost"]
        T5 --> T5a["Mulai +7 hari, bulanan, mobil listrik di E001"]
        T5 --> T5b["Listrik (58 - 20) x 3.000 = 114.000, charge 38,00"]
        T5 --> T5c["Total 3.000.000 + 114.000"]
        T6["test_electric_car_in_regular_garage_pays_no_electricity"]
        T6 --> T6a["Mobil listrik di garasi mobil: listrik 0,00"]
    end

    subgraph VALIDASI["Validasi input"]
        T7["test_current_battery_required_for_electric_garage_and_not_above_capacity"]
        T7 --> T7a["Sisa baterai kosong atau 60 lebih dari 58: error [X]"]
        T8["test_vehicle_garage_compatibility_and_required_fields"]
        T8 --> T8a["Motor di garasi mobil: error garage_id [X]"]
        T8 --> T8b["Mobil di garasi listrik: error [X]"]
        T8 --> T8c["Plat wajib kecuali sepeda, kapasitas baterai wajib untuk mobil listrik [X]"]
        T8 --> T8d["Sepeda tanpa plat di garasi sepeda: berhasil, plat null [OK]"]
        T9["test_date_and_duration_rules"]
        T9 --> T9a["Mulai kemarin, 31 hari, 0 hari: error [X]"]
        T10["test_daily_limit_is_shorter_in_a_28_day_month"]
        T10 --> T10a["Feb 2027: 28 hari ditolak, 27 hari diterima"]
        T11["test_proof_must_be_an_image"]
        T11 --> T11a["PDF atau tanpa bukti: error payment_proof [X]"]
    end

    subgraph BENTROK["Bentrok dan akses data"]
        T12["test_garage_cannot_be_double_booked_but_second_rental_after_first_works"]
        T12 --> T12a["Penyewa lain, garasi sama, tanggal bertumpuk: error garage_id [X]"]
        T12 --> T12b["Penyewa lain setelah sewa pertama selesai: berhasil [OK]"]
        T12 --> T12c["Kendaraan sama bertumpuk di garasi lain: error vehicle_id [X]"]
        T12 --> T12d["Sewa kedua setelah selesai memakai kendaraan tersimpan: berhasil, Vehicle tetap 1"]
        T13["test_quote_endpoint_lists_only_free_garages_and_costs"]
        T13 --> T13a["Endpoint quote: hanya C002 yang bebas, end_date benar"]
        T13 --> T13b["Biaya total Rp 200.000"]
        T14["test_cannot_view_others_rental_and_pages_render"]
        T14 --> T14a["Melihat sewa orang lain: 404"]
        T14 --> T14b["Empat halaman penyewa render 200"]
    end

    S --> AKUN
    S --> SEWA
    S --> VALIDASI
    S --> BENTROK

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11,T12,T13,T14 test;
    class T1a,T1b,T2a,T2b,T3a,T3b,T3c,T4a,T4b,T5a,T5b,T5c,T6a,T7a,T8a,T8b,T8c,T8d,T9a,T10a,T11a,T12a,T12b,T12c,T12d,T13a,T13b,T14a,T14b leaf;
```

## B3. `AdminPanelTest` (10 method)

```mermaid
flowchart LR
    S["AdminPanelTest<br/>panel admin lewat HTTP, DemoSeeder"]

    S --> T1["test_guests_are_redirected_and_penyewa_is_forbidden"]
    T1 --> T1a["Tamu ke /admin: redirect /login. Penyewa ke /admin: 403"]

    S --> T2["test_login_redirects_by_role"]
    T2 --> T2a["Admin login: ke /admin. Penyewa login: ke /penyewa"]
    T2 --> T2b["Password salah: error pada email [X]"]

    S --> T3["test_all_admin_pages_render_with_demo_data"]
    T3 --> T3a["16 URL admin render 200: dashboard, garasi, registrasi, sewa, detail sewa, laporan, dengan filter"]

    S --> T4["test_batch_creation_generates_sequential_codes_and_enforces_999"]
    T4 --> T4a["Dua batch sepeda: S001 sampai S005"]
    T4 --> T4b["Mobil listrik tanpa harga listrik: error electricity_price_per_kwh [X]"]
    T4 --> T4c["Dengan harga listrik: E001 dan E002"]
    T4 --> T4d["Quantity 999 dan 1000 melebihi kuota: error quantity, tidak ada garasi baru [X]"]

    S --> T5["test_approve_and_reject_registration"]
    T5 --> T5a["Approve: Approved dan verified_by admin. Reject: alasan KTP buram tersimpan"]
    T5 --> T5b["Sudah diproses tidak bisa diubah lagi (error)"]
    T5 --> T5c["Admin tidak bisa diverifikasi: 404"]

    S --> T6["test_extension_flow_charges_new_rule_and_stores_history"]
    T6 --> T6a["Sewa C001 600.000 diperpanjang 39 hari: total sesuai calculator"]
    T6 --> T6b["1 bulan tercatat, riwayat perpanjangan dan bukti tersimpan"]

    S --> T7["test_extension_is_rejected_when_not_later_or_overlapping_other_rental"]
    T7 --> T7a["Tanggal sama atau lebih awal: error new_end_date [X]"]
    T7 --> T7b["Bentrok sewa lain di garasi sama: error, tidak ada riwayat [X]"]
    T7 --> T7c["Halaman detail menampilkan bentrok dengan sewa lain"]

    S --> T8["test_upcoming_rental_can_be_extended_but_ended_or_unverified_cannot"]
    T8 --> T8a["Belum mulai dan terverifikasi: boleh [OK]"]
    T8 --> T8b["Sudah berakhir: error, halaman menampilkan sudah berakhir [X]"]
    T8 --> T8c["Belum diverifikasi (pending): error [X]"]

    S --> T9["test_extension_admin_electricity_is_added"]
    T9 --> T9a["Perpanjangan E001 + extra listrik 150.000: electricity_cost bertambah"]
    T9 --> T9b["Tambahan 240.000 (1 hari 90.000 + listrik 150.000), total baru benar"]

    S --> T10["test_report_numbers"]
    T10 --> T10a["Laporan tahunan menampilkan total pendapatan sewa Approved dalam format Rupiah"]

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10 test;
    class T1a,T2a,T2b,T3a,T4a,T4b,T4c,T4d,T5a,T5b,T5c,T6a,T6b,T7a,T7b,T7c,T8a,T8b,T8c,T9a,T9b,T10a leaf;
```

## B4. `BusinessRulesTest` (12 method)

```mermaid
flowchart LR
    S["BusinessRulesTest<br/>aturan bisnis end to end"]

    subgraph STATUS["Status garasi dan zona waktu"]
        T1["test_timezone_is_wib"]
        T1 --> T1a["config app.timezone = Asia/Jakarta"]
        T2["test_garage_status_follows_rental_dates"]
        T2 --> T2a["Sebelum mulai: Available"]
        T2 --> T2b["Hari mulai setelah garages:sync-status: Used"]
        T2 --> T2c["Setelah tanggal selesai: kembali Available"]
        T3["test_booking_starting_today_marks_garage_used_immediately"]
        T3 --> T3a["Sewa mulai hari ini: garasi langsung Used, pembayaran Pending"]
    end

    subgraph PEMBAYARAN["Verifikasi pembayaran"]
        T4["test_pending_rental_holds_garage_and_rejection_releases_it"]
        T4 --> T4a["Pending tetap menahan garasi"]
        T4 --> T4b["Admin reject dengan alasan: status Rejected, alasan dan verifikator tersimpan"]
        T4 --> T4c["Garasi dilepas, kembali Available, bisa dipesan penyewa lain"]
        T4 --> T4d["Penyewa melihat Pembayaran ditolak dan alasannya"]
        T5["test_approve_payment_and_cannot_decide_twice"]
        T5 --> T5a["Approve berhasil, reject setelahnya: error, status tetap Approved"]
        T5 --> T5b["Penyewa memverifikasi: 403 [X]"]
        T6["test_admin_can_delete_rental_and_garage_is_released"]
        T6 --> T6a["Penyewa menghapus sewa: 403 [X]"]
        T6 --> T6b["Admin menghapus: Rental hilang, file bukti dihapus, garasi Available"]
    end

    subgraph LAPORAN["Laporan"]
        T7["test_reports_count_only_approved_and_split_electricity"]
        T7 --> T7a["Sebelum verifikasi: laporan 0"]
        T7 --> T7b["2 sewa Approved: rental 320.000, listrik 90.000, pendapatan 410.000"]
        T7 --> T7c["Sewa Rejected tidak dihitung, halaman laporan tipe menampilkan Listrik"]
    end

    subgraph HARGA["Harga dan data"]
        T8["test_start_date_limited_to_30_days_ahead"]
        T8 --> T8a["+31 hari: error start_date [X]. +30 hari: berhasil [OK]"]
        T9["test_one_price_per_garage_type_and_old_rentals_keep_snapshot"]
        T9 --> T9a["Batch baru 150.000 memperbarui harga tipe, hanya 1 baris per tipe"]
        T9 --> T9b["Semua garasi tipe itu memakai harga baru, bulanan 3.750.000"]
        T9 --> T9c["Sewa lama tetap 100.000, sewa baru 150.000, halaman garasi menampilkan Rp 150.000"]
        T10["test_plate_number_is_not_unique"]
        T10 --> T10a["Dua penyewa memakai plat sama: keduanya berhasil"]
    end

    subgraph AKUN["Registrasi ditolak dan reset password"]
        T11["test_rejected_registrant_can_fix_data_and_resubmit"]
        T11 --> T11a["Penyewa ditolak melihat alasan di halaman profil"]
        T11 --> T11b["NIK 3 digit atau NIK orang lain: error [X]. NIK sendiri bukan duplikat"]
        T11 --> T11c["Update valid: kembali Pending, alasan dan verified_by dikosongkan"]
        T11 --> T11d["Bukan berstatus ditolak: profil dialihkan, update 403"]
        T12["test_password_reset_via_email"]
        T12 --> T12a["Email terdaftar menerima notifikasi, email tak dikenal: error [X]"]
        T12 --> T12b["Token salah: error [X]. Token benar: redirect /login"]
        T12 --> T12c["Login dengan password baru berhasil"]
    end

    S --> STATUS
    S --> PEMBAYARAN
    S --> LAPORAN
    S --> HARGA
    S --> AKUN

    classDef suite fill:#dbeafe,stroke:#2563eb,color:#111;
    classDef test fill:#fef9c3,stroke:#ca8a04,color:#111;
    classDef leaf fill:#dcfce7,stroke:#16a34a,color:#111;
    class S suite;
    class T1,T2,T3,T4,T5,T6,T7,T8,T9,T10,T11,T12 test;
    class T1a,T2a,T2b,T2c,T3a,T4a,T4b,T4c,T4d,T5a,T5b,T6a,T6b,T7a,T7b,T7c,T8a,T9a,T9b,T9c,T10a,T11a,T11b,T11c,T11d,T12a,T12b,T12c leaf;
```

---

## Matriks aturan bisnis dan test yang menutupinya

```mermaid
flowchart LR
    R1["Kecocokan kendaraan dan garasi"] --> A1["A1 Enums"] & A4["A4 Garage forVehicleType"] & A8["A8 Booking"] & B1["B1"] & B2["B2"]
    R2["Ambang bulanan = hari di bulan mulai"] --> A2["A2 Calculator"] & A8 & B2
    R3["Bulanan = 25 x harian"] --> A2 & A6["A6 Pricing"] & B1 & B4["B4"]
    R4["Batas mulai: tidak mundur dan maks 30 hari"] --> A2 & A8 & B2 & B4
    R5["Bentrok garasi dan kendaraan"] --> A4 & A5["A5 Rental"] & A6 & A8 & B1 & B2
    R6["Pending menahan, Rejected melepas garasi"] --> A4 & A5 & A6 & A8 & B4
    R7["Biaya listrik: kapasitas - sisa baterai x harga kWh"] --> A8 & A9["A9 Extension"] & B2 & B3["B3"] & B4
    R8["Satu harga per tipe, sewa menyimpan snapshot"] --> A4 & A7["A7 Batch"] & A8 & A9 & B4
    R9["Maks 999 garasi per tipe, kode S, C, E"] --> A4 & A7 & B3
    R10["Perpanjangan: hanya sewa terverifikasi dan belum berakhir"] --> A5 & A9 & B3
    R11["Status garasi mengikuti tanggal sewa"] --> A4 & A8 & A9 & B4
    R12["Laporan hanya sewa Approved"] --> A10["A10 Report"] & B3 & B4
    R13["Format Rupiah"] --> A3["A3 Format"] & A8
    R14["Akses peran dan registrasi"] --> B2 & B3 & B4
```
