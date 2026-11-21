<?php

return [
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa email yang valid.',
    'numeric' => ':attribute harus berupa angka.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'date' => ':attribute bukan tanggal yang valid.',
    'string' => ':attribute harus berupa teks.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute tidak valid.',
    'enum' => ':attribute tidak valid.',
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'max' => [
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
        'file' => ':attribute maksimal :max KB.',
    ],
    'between' => [
        'numeric' => ':attribute harus antara :min dan :max.',
    ],
    'uploaded' => ':attribute gagal diunggah.',

    'attributes' => [
        'email' => 'Email',
        'password' => 'Password',
        'type' => 'Tipe garasi',
        'quantity' => 'Jumlah garasi',
        'daily_price' => 'Harga sewa harian',
        'electricity_price_per_kwh' => 'Harga isi listrik per kWh',
        'new_end_date' => 'Tanggal selesai baru',
        'payment_proof' => 'Bukti bayar',
        'rejection_reason' => 'Alasan penolakan',
        'year' => 'Tahun',
        'month' => 'Bulan',
        'view' => 'Jenis laporan',
    ],
];
