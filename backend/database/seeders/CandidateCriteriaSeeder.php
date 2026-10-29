<?php

namespace Database\Seeders;

use App\Models\DecisionCriterion;
use Illuminate\Database\Seeder;

class CandidateCriteriaSeeder extends Seeder
{
    /**
     * Seeder kandidat kriteria K1-K7 sesuai Master PRD v2.0 §5.1.
     * Digunakan sebagai referensi konfigurasi awal (status akhir menunggu D-06).
     */
    public function run(): void
    {
        $criteria = [
            [
                'code' => 'K1',
                'name' => 'Tingkat kerusakan',
                'description' => 'Skor kondisi fisik perkerasan jalan dari penilaian lapangan final terbaru menurut protokol SDI-ADAPTED-1.0.',
                'data_type' => 'ordinal',
                'unit' => 'Skala 1-5',
                'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
                'minimum_value' => 1,
                'maximum_value' => 5,
                'aggregation_rule' => 'latest_final_assessment',
                'is_required' => true,
                'is_active' => true,
            ],
            [
                'code' => 'K2',
                'name' => 'Risiko keselamatan',
                'description' => 'Tingkat bahaya kontekstual bagi pengguna jalan yang dinilai petugas pada skala berjangkar 1-5.',
                'data_type' => 'ordinal',
                'unit' => 'Skala 1-5',
                'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
                'minimum_value' => 1,
                'maximum_value' => 5,
                'aggregation_rule' => 'latest_final_assessment',
                'is_required' => true,
                'is_active' => true,
            ],
            [
                'code' => 'K3',
                'name' => 'Fungsi jalan',
                'description' => 'Klasifikasi fungsi jalan (lingkungan=1, lokal=2, kolektor=3, arteri=4).',
                'data_type' => 'ordinal',
                'unit' => 'Skala 1-4',
                'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
                'minimum_value' => 1,
                'maximum_value' => 4,
                'aggregation_rule' => 'segment_attribute',
                'is_required' => true,
                'is_active' => true,
            ],
            [
                'code' => 'K4',
                'name' => 'Intensitas pengguna jalan',
                'description' => 'Kelas kepadatan lalu lintas pengguna jalan berdasarkan observasi lapangan atau data lalu lintas (kandidat D-06).',
                'data_type' => 'ordinal',
                'unit' => 'Skala 1-5',
                'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
                'minimum_value' => 1,
                'maximum_value' => 5,
                'aggregation_rule' => 'latest_final_assessment',
                'is_required' => true,
                'is_active' => true,
            ],
            [
                'code' => 'K5',
                'name' => 'Kedekatan fasilitas publik',
                'description' => 'Jarak minimum ruas jalan ke fasilitas publik penting (sekolah, layanan kesehatan, pasar, transportasi umum).',
                'data_type' => 'numeric',
                'unit' => 'Meter',
                'criterion_type' => DecisionCriterion::TYPE_COST,
                'minimum_value' => 0,
                'maximum_value' => null,
                'aggregation_rule' => 'minimum_distance_m',
                'is_required' => true,
                'is_active' => true,
            ],
            [
                'code' => 'K6',
                'name' => 'Frekuensi laporan',
                'description' => 'Jumlah laporan terverifikasi aktif yang unik (bukan duplikat tertaut) pada ruas jalan dalam jendela waktu evaluasi.',
                'data_type' => 'integer',
                'unit' => 'Laporan',
                'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
                'minimum_value' => 0,
                'maximum_value' => null,
                'aggregation_rule' => 'count_unique_verified_reports',
                'is_required' => true,
                'is_active' => true,
            ],
            [
                'code' => 'K7',
                'name' => 'Lama belum ditangani',
                'description' => 'Jumlah hari sejak laporan terverifikasi aktif tertua pada ruas jalan.',
                'data_type' => 'integer',
                'unit' => 'Hari',
                'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
                'minimum_value' => 0,
                'maximum_value' => null,
                'aggregation_rule' => 'max_verified_report_age_days',
                'is_required' => true,
                'is_active' => true,
            ],
        ];

        foreach ($criteria as $item) {
            DecisionCriterion::updateOrCreate(['code' => $item['code']], $item);
        }
    }
}
