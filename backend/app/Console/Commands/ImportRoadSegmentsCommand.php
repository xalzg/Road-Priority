<?php

namespace App\Console\Commands;

use App\Services\RoadSegmentGeoJsonImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class ImportRoadSegmentsCommand extends Command
{
    /**
     * Nama dan signature perintah Artisan (PRD 05.2).
     *
     * @var string
     */
    protected $signature = 'roadpriority:import-segments
        {geojson_path : Path ke file GeoJSON FeatureCollection ruas jalan}
        {--dry-run : Validasi dan tampilkan ringkasan tanpa menulis ke database}
        {--update-existing : Izinkan pembaruan ruas resmi yang sudah ada jika atribut/geometri berubah}
        {--allow-partial : Simpan feature yang valid meskipun ada feature lain yang ditolak (non-atomik)}
        {--enforce-study-bbox : Tolak feature yang berada di luar kotak referensi wilayah studi}
        {--source-name= : Nama sumber dataset (mis. OpenStreetMap)}
        {--source-reference= : Referensi/atribusi sumber (mis. ODbL)}
        {--data-origin=public_dataset : Kode asal data (bukan demo)}
        {--json : Cetak ringkasan hasil dalam format JSON}';

    /**
     * Deskripsi perintah Artisan.
     *
     * @var string
     */
    protected $description = 'Impor ruas jalan (LineString) dari file GeoJSON FeatureCollection ke tabel road_segments dengan validasi dan perlindungan data (PRD 05.2)';

    public function handle(RoadSegmentGeoJsonImporter $importer): int
    {
        $filePath = (string) $this->argument('geojson_path');
        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
            'update_existing' => (bool) $this->option('update-existing'),
            'atomic' => ! (bool) $this->option('allow-partial'),
            'enforce_study_bbox' => (bool) $this->option('enforce-study-bbox'),
            'source_name' => $this->option('source-name'),
            'source_reference' => $this->option('source-reference'),
            'data_origin' => (string) ($this->option('data-origin') ?: 'public_dataset'),
        ];

        try {
            $summary = $importer->importFromFile($filePath, $options);
        } catch (InvalidArgumentException $e) {
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error('Impor GeoJSON gagal: ' . $e->getMessage());
            }

            return Command::FAILURE;
        } catch (Throwable $e) {
            if ((bool) $this->option('json')) {
                $this->line((string) json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error('Kesalahan tidak terduga saat mengimpor GeoJSON: ' . $e->getMessage());
            }

            return Command::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $modeLabel = $summary['dry_run'] ? 'DRY-RUN (Tanpa Tulis Database)' : 'IMPORT AKTIF';
            $atomicLabel = $summary['atomic'] ? 'Atomik (All-or-Nothing)' : 'Parsial (--allow-partial)';

            $this->info("=== RoadPriority GeoJSON Road Segment Importer ({$modeLabel}) ===");
            $this->line("File             : {$summary['file_path']}");
            $this->line("Mode Transaksi   : {$atomicLabel}");
            $this->newLine();

            $this->table(
                ['Metrik Ringkasan', 'Jumlah / Status'],
                [
                    ['total_features', (string) $summary['total_features']],
                    ['valid_features', (string) $summary['valid_features']],
                    ['rejected_features', (string) $summary['rejected_features']],
                    ['created_count', (string) $summary['created_count']],
                    ['updated_count', (string) $summary['updated_count']],
                    ['unchanged_count', (string) $summary['unchanged_count']],
                    ['conflict_count', (string) $summary['conflict_count']],
                    ['rolled_back', $summary['rolled_back'] ? 'YA (Transaksi Dibatalkan)' : 'TIDAK'],
                ]
            );

            if (! empty($summary['boundary_warnings'])) {
                $this->newLine();
                $this->warn('Peringatan Batas Referensi Wilayah Studi:');
                foreach ($summary['boundary_warnings'] as $warning) {
                    $this->line("  - {$warning}");
                }
            }

            if (! empty($summary['errors'])) {
                $this->newLine();
                $this->error('Detail Kegagalan / Konflik Feature:');
                foreach ($summary['errors'] as $err) {
                    $this->line("  - {$err['reason']}");
                }
            }

            if ($summary['rolled_back']) {
                $this->newLine();
                $this->error(
                    'Impor dibatalkan secara atomik karena terdapat feature yang tidak valid atau mengalami konflik.'
                );
            } elseif ($summary['dry_run']) {
                $this->newLine();
                $this->info('Mode --dry-run selesai. Tidak ada perubahan yang ditulis ke database.');
            } else {
                $this->newLine();
                $this->info(
                    "Impor selesai: {$summary['created_count']} dibuat, {$summary['updated_count']} diperbarui, {$summary['unchanged_count']} tidak berubah."
                );
            }
        }

        $hasErrors = ($summary['rejected_features'] > 0 || $summary['conflict_count'] > 0 || $summary['rolled_back']);

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}

