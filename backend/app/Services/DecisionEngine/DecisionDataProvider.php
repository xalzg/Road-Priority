<?php

namespace App\Services\DecisionEngine;

use App\Models\DamageAssessment;
use App\Models\DamageReport;
use App\Models\DecisionCriterion;
use App\Models\RoadSegment;
use Carbon\Carbon;

class DecisionDataProvider
{
    private const ROAD_FUNCTION_SCORES = [
        'lingkungan' => 1.0,
        'lokal' => 2.0,
        'kolektor' => 3.0,
        'arteri' => 4.0,
    ];

    /**
     * Mengambil daftar kriteria aktif (atau berdasarkan daftar ID kriteria tertentu)
     * dalam format kanonik untuk TopsisService & SnapshotService.
     *
     * @param  array<int, int>|null  $criterionIds
     * @return array<int, array<string, mixed>>
     */
    public function getCriteriaSpecs(?array $criterionIds = null): array
    {
        $query = DecisionCriterion::query()->where('is_active', true)->orderBy('code');
        if ($criterionIds !== null && $criterionIds !== []) {
            $query->whereIn('id', $criterionIds);
        }

        return $query->get()->map(fn (DecisionCriterion $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'data_type' => $c->data_type,
            'unit' => $c->unit,
            'type' => $c->criterion_type,
            'criterion_type' => $c->criterion_type,
            'min' => $c->minimum_value !== null ? (float) $c->minimum_value : null,
            'max' => $c->maximum_value !== null ? (float) $c->maximum_value : null,
            'aggregation_rule' => $c->aggregation_rule,
            'is_required' => (bool) $c->is_required,
        ])->values()->all();
    }

    /**
     * Menyusun alternatif ruas jalan beserta nilai setiap kriteria, asal data, dan ID laporan sumber.
     *
     * @param  array<int, array<string, mixed>>  $criteriaSpecs
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function buildAlternatives(
        array $criteriaSpecs,
        bool $includeDemo = false,
        ?array $segmentIds = null,
        array $parameters = []
    ): array {
        $asOf = isset($parameters['as_of']) ? Carbon::parse((string) $parameters['as_of']) : now();
        $windowDays = isset($parameters['report_window_days']) ? (int) $parameters['report_window_days'] : null;

        $query = RoadSegment::query()
            ->where('is_active', true)
            ->with([
                'damageAssessments' => function ($q) use ($includeDemo) {
                    $q->where('status', DamageAssessment::STATUS_FINAL)
                        ->when(! $includeDemo, fn ($sq) => $sq->where('is_demo', false))
                        ->orderByDesc('assessment_date')
                        ->orderByDesc('id');
                },
                'damageReports' => function ($q) use ($includeDemo, $windowDays, $asOf) {
                    $q->where('status', DamageReport::STATUS_VERIFIED)
                        ->whereNull('duplicate_of_id')
                        ->when(! $includeDemo, fn ($sq) => $sq->where('is_demo', false))
                        ->when($windowDays !== null && $windowDays > 0, function ($sq) use ($windowDays, $asOf) {
                            $sq->where('reported_at', '>=', $asOf->copy()->subDays($windowDays));
                        });
                },
            ])
            ->orderBy('road_reference_code');

        if (! $includeDemo) {
            $query->where('is_demo', false);
        }

        if ($segmentIds !== null && $segmentIds !== []) {
            $query->whereIn('id', $segmentIds);
        }

        $segments = $query->get();
        $alternatives = [];

        foreach ($segments as $segment) {
            /** @var DamageAssessment|null $latestAssessment */
            $latestAssessment = $segment->damageAssessments->first();
            $verifiedReports = $segment->damageReports;

            $values = [];
            foreach ($criteriaSpecs as $c) {
                $code = (string) $c['code'];
                $values[$code] = $this->extractCriterionValue(
                    $code,
                    (string) ($c['aggregation_rule'] ?? ''),
                    $segment,
                    $latestAssessment,
                    $verifiedReports,
                    $asOf
                );
            }

            $alternatives[] = [
                'segment_id' => $segment->id,
                'code' => $segment->road_reference_code,
                'name' => $segment->name,
                'road_function' => $segment->road_function,
                'road_authority' => $segment->road_authority,
                'values' => $values,
                'is_demo' => (bool) $segment->is_demo || (bool) ($latestAssessment?->is_demo ?? false),
                'data_origin' => $segment->data_origin,
                'assessment_id' => $latestAssessment?->id,
                'assessment_date' => $latestAssessment?->assessment_date?->toIso8601String(),
                'assessment_protocol' => $latestAssessment?->protocol_version,
                'source_report_ids' => $verifiedReports->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        }

        return $alternatives;
    }

    /**
     * Laporan Kesiapan Data (PRD 05 §2): menjelaskan data yang tersedia, yang belum tersedia,
     * sumber/tanggal pengukuran, unit/skala, alternatif layak, dan kebijakan data hilang.
     *
     * @return array<string, mixed>
     */
    public function buildReadinessReport(bool $includeDemo = false): array
    {
        $criteriaSpecs = $this->getCriteriaSpecs();
        $alternatives = $this->buildAlternatives($criteriaSpecs, $includeDemo);

        $eligibleSegments = [];
        $incompleteSegments = [];

        foreach ($alternatives as $alt) {
            $missingOrInvalid = [];
            foreach ($criteriaSpecs as $c) {
                $code = $c['code'];
                $val = $alt['values'][$code] ?? null;
                if ($val === null || (! is_int($val) && ! is_float($val)) || ! is_finite((float) $val)) {
                    $missingOrInvalid[] = [
                        'criterion_code' => $code,
                        'reason' => "missing_or_invalid:{$code}",
                    ];
                    continue;
                }
                if (($c['min'] !== null && $val < $c['min']) || ($c['max'] !== null && $val > $c['max'])) {
                    $missingOrInvalid[] = [
                        'criterion_code' => $code,
                        'reason' => "out_of_range:{$code}",
                    ];
                }
            }

            $row = [
                'segment_id' => $alt['segment_id'],
                'code' => $alt['code'],
                'name' => $alt['name'],
                'is_demo' => $alt['is_demo'],
                'data_origin' => $alt['data_origin'],
                'assessment_id' => $alt['assessment_id'],
                'assessment_date' => $alt['assessment_date'],
                'source_report_count' => count($alt['source_report_ids']),
                'values' => $alt['values'],
                'missing_or_invalid' => $missingOrInvalid,
                'is_eligible' => $missingOrInvalid === [],
            ];

            if ($missingOrInvalid === []) {
                $eligibleSegments[] = $row;
            } else {
                $incompleteSegments[] = $row;
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'include_demo' => $includeDemo,
            'missing_data_policy' => [
                'code' => 'exclude_and_log',
                'description' => 'Alternatif dengan nilai kriteria wajib kosong atau di luar rentang tidak diimputasi dengan nol/rata-rata, melainkan dikeluarkan dari perhitungan dan dicatat beserta alasannya (D-09).',
            ],
            'criteria' => $criteriaSpecs,
            'summary' => [
                'active_criteria_count' => count($criteriaSpecs),
                'total_active_segments' => count($alternatives),
                'eligible_segments_count' => count($eligibleSegments),
                'incomplete_segments_count' => count($incompleteSegments),
                'min_required_for_topsis' => TopsisService::MIN_ALTERNATIVES,
                'ready_for_topsis_run' => count($criteriaSpecs) >= 1 && count($eligibleSegments) >= TopsisService::MIN_ALTERNATIVES,
            ],
            'eligible_segments' => $eligibleSegments,
            'incomplete_segments' => $incompleteSegments,
        ];
    }

    /**
     * Mengekstrak nilai numerik satu kriteria untuk satu ruas jalan berdasarkan aturan agregasi.
     *
     * @param  \Illuminate\Support\Collection<int, DamageReport>  $verifiedReports
     */
    private function extractCriterionValue(
        string $code,
        string $aggregationRule,
        RoadSegment $segment,
        ?DamageAssessment $assessment,
        $verifiedReports,
        Carbon $asOf
    ): ?float {
        $measurements = is_array($assessment?->measurements) ? $assessment->measurements : [];

        // Jika nilai eksplisit tersedia pada measurements[code], gunakan nilai terukur tersebut
        if (array_key_exists($code, $measurements) && is_numeric($measurements[$code])) {
            return (float) $measurements[$code];
        }

        if ($code === 'K1') {
            return $assessment?->severity_level !== null ? (float) $assessment->severity_level : null;
        }

        if ($code === 'K2') {
            return $assessment?->safety_risk_score !== null ? (float) $assessment->safety_risk_score : null;
        }

        if ($code === 'K3' || $aggregationRule === 'segment_attribute') {
            if ($assessment?->road_function_score !== null) {
                return (float) $assessment->road_function_score;
            }
            $func = strtolower((string) $segment->road_function);

            return self::ROAD_FUNCTION_SCORES[$func] ?? null;
        }

        if ($code === 'K4') {
            return $assessment?->impact_score !== null ? (float) $assessment->impact_score : null;
        }

        if ($code === 'K5' || $aggregationRule === 'minimum_distance_m') {
            return $segment->nearest_facility_meters !== null ? (float) $segment->nearest_facility_meters : null;
        }

        if ($code === 'K6' || $aggregationRule === 'count_unique_verified_reports') {
            return (float) $verifiedReports->count();
        }

        if ($code === 'K7' || $aggregationRule === 'max_verified_report_age_days') {
            if ($verifiedReports->isEmpty()) {
                return 0.0;
            }
            $oldest = $verifiedReports->min('reported_at');
            if ($oldest === null) {
                return 0.0;
            }
            $oldestDate = $oldest instanceof Carbon ? $oldest : Carbon::parse((string) $oldest);

            return (float) max(0, $oldestDate->diffInDays($asOf, false));
        }

        return null;
    }
}
