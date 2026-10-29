<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Jenis Kerusakan Awal (Dapat Dikonfigurasi - PRD 04 §4.1)
    |--------------------------------------------------------------------------
    | Catatan: Daftar awal ini adalah kategori pelaporan masyarakat/petugas dan
    | bukan klasifikasi ilmiah final tanpa verifikasi lapangan (SDI/PCI).
    */
    'damage_types' => [
        'lubang' => 'Lubang (Pothole)',
        'berlubang' => 'Lubang (Pothole)',
        'retak' => 'Retak (Crack)',
        'permukaan_bergelombang' => 'Permukaan Bergelombang',
        'aspal_mengelupas' => 'Aspal Mengelupas',
        'kerusakan_tepi_jalan' => 'Kerusakan Tepi Jalan',
        'lainnya' => 'Lainnya',
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Upload Foto Bukti (PRD 04 §5)
    |--------------------------------------------------------------------------
    */
    'photo_disk' => env('REPORT_PHOTO_DISK', 'local'),
    'max_photos_per_report' => (int) env('REPORT_MAX_PHOTOS', 5),
    'max_photo_size_kb' => (int) env('REPORT_MAX_PHOTO_SIZE_KB', 5120),
    'allowed_photo_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Kotak Pembatas Referensi Wilayah Studi BSD & Sekitarnya (PRD 04 §4.2, §7.3)
    |--------------------------------------------------------------------------
    | Digunakan untuk memberikan peringatan (warning) apabila titik laporan berada
    | di luar area referensi BSD/Tangerang ketika poligon resmi belum dimuat.
    */
    'study_area_reference_bbox' => [
        'min_lat' => -6.3800000,
        'max_lat' => -6.2200000,
        'min_lng' => 106.5800000,
        'max_lng' => 106.7500000,
        'center_lat' => -6.3015000,
        'center_lng' => 106.6525000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Parameter Bantuan Verifikasi Petugas (PRD 04 §6, §8)
    |--------------------------------------------------------------------------
    */
    'segment_suggestion_radius_m' => 50.0,
    'duplicate_candidate_radius_m' => 50.0,
    'duplicate_candidate_window_days' => 30,
];

