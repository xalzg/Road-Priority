<?php

namespace App\Services;

use App\Models\DamageReport;
use App\Models\ReportPhoto;
use App\Models\ReportStatusHistory;
use App\Models\RoadSegment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DamageReportService
{
    private const MIME_EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private AuditLogService $auditLogs,
        private GeoGeometryService $geoGeometry
    ) {
    }

    /**
     * Membuat kode laporan unik dari sisi server tanpa informasi pribadi (PRD 04 §4.3).
     */
    public function generateUniqueReportCode(): string
    {
        $datePart = now()->format('Ymd');

        do {
            $randomPart = strtoupper(Str::random(6));
            $code = "RPT-{$datePart}-{$randomPart}";
        } while (DamageReport::query()->where('report_code', $code)->exists());

        return $code;
    }

    /**
     * Membuat laporan kerusakan baru beserta foto bukti dan histori status awal secara atomik.
     * Menjamin pembersihan file yatim (orphan file cleanup) apabila transaksi database gagal (PRD 04 §5).
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>  $uploadedPhotos
     */
    public function createReport(User $actor, array $validated, array $uploadedPhotos = []): DamageReport
    {
        $disk = (string) config('reporting.photo_disk', 'local');
        $allowedMimes = config('reporting.allowed_photo_mimes', ['image/jpeg', 'image/png', 'image/webp']);
        $preparedPhotos = [];

        // 1. Validasi isi biner dan dimensi gambar sebenarnya sebelum mulai menyimpan
        foreach ($uploadedPhotos as $index => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                throw ValidationException::withMessages([
                    "photos.{$index}" => ['File foto tidak valid atau gagal diunggah.'],
                ]);
            }

            $realPath = $file->getRealPath();
            $imageInfo = $realPath !== false ? @getimagesize($realPath) : false;
            $detectedMime = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;

            if (
                $imageInfo === false
                || ! is_string($detectedMime)
                || ! in_array($detectedMime, $allowedMimes, true)
                || (int) ($imageInfo[0] ?? 0) <= 0
                || (int) ($imageInfo[1] ?? 0) <= 0
            ) {
                throw ValidationException::withMessages([
                    "photos.{$index}" => ['Isi file bukan gambar JPEG, PNG, atau WebP yang valid.'],
                ]);
            }

            $preparedPhotos[] = [
                'file' => $file,
                'mime_type' => $detectedMime,
                'extension' => self::MIME_EXTENSION_MAP[$detectedMime] ?? 'jpg',
                'width' => (int) $imageInfo[0],
                'height' => (int) $imageInfo[1],
                'file_size_bytes' => (int) $file->getSize(),
                'sha256' => hash_file('sha256', $realPath) ?: null,
                'original_filename' => Str::limit(basename((string) $file->getClientOriginalName()), 200, ''),
            ];
        }

        $writtenPaths = [];

        try {
            $directory = 'report-photos/' . now()->format('Y/m');
            foreach ($preparedPhotos as $idx => $meta) {
                $serverFilename = Str::uuid()->toString() . '.' . $meta['extension'];
                $storedPath = $meta['file']->storeAs($directory, $serverFilename, $disk);

                if ($storedPath === false) {
                    throw new \RuntimeException('Gagal menyimpan file foto ke media penyimpanan.');
                }

                $writtenPaths[] = $storedPath;
                $preparedPhotos[$idx]['file_path'] = $storedPath;
            }

            return DB::transaction(function () use ($actor, $validated, $preparedPhotos, $disk): DamageReport {
                $lat = (float) $validated['latitude'];
                $lng = (float) $validated['longitude'];
                $roadSegmentId = isset($validated['road_segment_id']) ? (int) $validated['road_segment_id'] : null;
                $matchDistanceM = null;

                if ($roadSegmentId !== null) {
                    $segment = RoadSegment::query()->find($roadSegmentId);
                    if ($segment !== null) {
                        $matchDistanceM = $this->geoGeometry->distanceToLineStringMeters(
                            $lat,
                            $lng,
                            $segment->geometry_geojson
                        );
                        if ($matchDistanceM === null && $segment->centroid_latitude !== null && $segment->centroid_longitude !== null) {
                            $matchDistanceM = $this->geoGeometry->distanceMeters(
                                $lat,
                                $lng,
                                (float) $segment->centroid_latitude,
                                (float) $segment->centroid_longitude
                            );
                        }
                    }
                }

                $report = new DamageReport([
                    'report_code' => $this->generateUniqueReportCode(),
                    'road_segment_id' => $roadSegmentId,
                    'segment_match_distance_m' => $matchDistanceM,
                    'damage_type' => $validated['damage_type'],
                    'description' => trim((string) $validated['description']),
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'gps_accuracy_m' => isset($validated['gps_accuracy_m']) ? (float) $validated['gps_accuracy_m'] : null,
                    'reported_at' => now(),
                    'data_origin' => 'citizen_report',
                    'is_demo' => false,
                ]);

                // Tetapkan pemilik dan status secara eksplisit di server (anti-mass-assignment manipulation)
                $report->reporter_id = $actor->id;
                $report->status = DamageReport::STATUS_SUBMITTED;
                $report->save();

                foreach ($preparedPhotos as $photoMeta) {
                    ReportPhoto::query()->create([
                        'damage_report_id' => $report->id,
                        'storage_disk' => $disk,
                        'file_path' => $photoMeta['file_path'],
                        'original_filename' => $photoMeta['original_filename'],
                        'mime_type' => $photoMeta['mime_type'],
                        'file_size_bytes' => $photoMeta['file_size_bytes'],
                        'width' => $photoMeta['width'],
                        'height' => $photoMeta['height'],
                        'sha256' => $photoMeta['sha256'],
                        'is_public' => false,
                        'captured_at' => now(),
                    ]);
                }

                ReportStatusHistory::query()->create([
                    'damage_report_id' => $report->id,
                    'previous_status' => null,
                    'new_status' => DamageReport::STATUS_SUBMITTED,
                    'changed_by' => $actor->id,
                    'notes' => 'Laporan kerusakan dikirim.',
                    'changed_at' => now(),
                ]);

                $this->auditLogs->record($actor, 'report.created', 'damage_report', $report->id, [
                    'report_code' => $report->report_code,
                    'damage_type' => $report->damage_type,
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,
                    'photos_count' => count($preparedPhotos),
                ]);

                return $report->fresh(['photos', 'statusHistories', 'roadSegment']);
            });
        } catch (Throwable $e) {
            foreach ($writtenPaths as $orphanPath) {
                Storage::disk($disk)->delete($orphanPath);
            }

            throw $e;
        }
    }
}
