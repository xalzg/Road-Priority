<?php

namespace App\Services\DecisionEngine;

use App\Models\CriterionWeightVersion;
use App\Models\DecisionRun;
use App\Models\DecisionRunItem;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DecisionRunService
{
    public function __construct(
        private DecisionDataProvider $dataProvider,
        private SnapshotService $snapshotService,
        private SensitivityService $sensitivityService,
        private AuditLogService $auditLogs
    ) {
    }

    /**
     * Menjalankan satu eksekusi TOPSIS dengan versi bobot tertentu dan menyimpan
     * snapshot input + hasil secara atomik dan immutable (PRD 05 §6, §7, §8, §11).
     *
     * @param  array<string, mixed>  $validated
     */
    public function executeRun(User $actor, array $validated): DecisionRun
    {
        $isSimulation = (bool) ($validated['is_simulation'] ?? false);
        $parameters = is_array($validated['parameters'] ?? null) ? $validated['parameters'] : [];
        $segmentIds = is_array($validated['segment_ids'] ?? null) ? array_map('intval', $validated['segment_ids']) : null;

        /** @var CriterionWeightVersion $weightVersion */
        $weightVersion = CriterionWeightVersion::query()
            ->with(['weightItems.criterion'])
            ->findOrFail((int) $validated['criterion_weight_version_id']);

        if ($weightVersion->weightItems->isEmpty()) {
            throw new DecisionEngineException(
                'weight_version_empty',
                'Versi bobot yang dipilih belum memiliki item bobot kriteria.'
            );
        }

        // Bangun daftar kriteria & bobot sesuai item yang tersimpan pada versi bobot
        $criteriaSpecs = [];
        $weightsMap = [];
        $criterionNames = [];

        foreach ($weightVersion->weightItems as $wItem) {
            $c = $wItem->criterion;
            if ($c === null || ! $c->is_active) {
                throw new DecisionEngineException(
                    'criterion_inactive_or_missing',
                    'Versi bobot memuat kriteria yang sudah tidak aktif atau tidak ditemukan.'
                );
            }
            $criteriaSpecs[] = [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->criterion_type,
                'criterion_type' => $c->criterion_type,
                'unit' => $c->unit,
                'min' => $c->minimum_value !== null ? (float) $c->minimum_value : null,
                'max' => $c->maximum_value !== null ? (float) $c->maximum_value : null,
                'aggregation_rule' => $c->aggregation_rule,
            ];
            $weightsMap[$c->code] = (float) $wItem->weight_value;
            $criterionNames[$c->code] = $c->name;
        }

        usort($criteriaSpecs, static fn (array $a, array $b) => strcmp($a['code'], $b['code']));

        // Ambil alternatif dari database (atau dari explicit alternatives_override khusus mode simulasi)
        $includeDemo = $isSimulation && (bool) ($parameters['include_demo'] ?? true);
        $alternatives = $this->dataProvider->buildAlternatives(
            $criteriaSpecs,
            $includeDemo,
            $segmentIds,
            $parameters
        );

        if ($alternatives === []) {
            throw new DecisionEngineException(
                'insufficient_alternatives',
                'Tidak ditemukan segmen jalan aktif untuk dievaluasi pada mode ini.'
            );
        }

        $weightVersionMeta = [
            'version_id' => $weightVersion->id,
            'label' => $weightVersion->version_name,
            'status' => $weightVersion->status,
            'lambda_max' => (float) $weightVersion->lambda_max,
            'ci' => (float) $weightVersion->consistency_index,
            'cr' => (float) $weightVersion->consistency_ratio,
            'is_consistent' => (bool) $weightVersion->is_consistent,
            'weights' => $weightsMap,
        ];

        $snapshot = $this->snapshotService->createSnapshot(
            $criteriaSpecs,
            $weightVersionMeta,
            $alternatives,
            $isSimulation,
            $parameters
        );

        // Jalankan analisis sensitivitas OAT (+-10% dan +-20%) serta bobot sama rata
        $sensitivity = $this->sensitivityService->analyze(
            $criteriaSpecs,
            $weightsMap,
            $alternatives
        );
        $equalScenario = null;
        foreach ($sensitivity['scenarios'] ?? [] as $scen) {
            if (($scen['scenario'] ?? '') === 'B:equal_weights') {
                $equalScenario = $scen;
                break;
            }
        }
        $snapshot['sensitivity_summary'] = [
            'equal_weights_spearman' => $equalScenario['spearman_vs_base'] ?? null,
            'equal_weights_top_k_overlap' => $equalScenario['top_k_overlap'] ?? null,
            'summary_by_alternative' => $sensitivity['summary'] ?? [],
            'scenarios_count' => count($sensitivity['scenarios'] ?? []),
        ];

        $segmentMapByCode = [];
        foreach ($alternatives as $alt) {
            $segmentMapByCode[$alt['code']] = $alt;
        }

        return DB::transaction(function () use (
            $actor,
            $validated,
            $weightVersion,
            $isSimulation,
            $snapshot,
            $segmentMapByCode,
            $criterionNames
        ): DecisionRun {
            $runCode = 'RUN-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

            $run = DecisionRun::query()->create([
                'run_code' => $runCode,
                'name' => trim((string) $validated['name']),
                'criterion_weight_version_id' => $weightVersion->id,
                'algorithm_name' => 'AHP-TOPSIS',
                'algorithm_version' => $snapshot['algorithm_version'],
                'status' => DecisionRun::STATUS_COMPLETED,
                'is_simulation' => $isSimulation,
                'input_hash' => $snapshot['input_hash'],
                'input_snapshot_reference' => $snapshot,
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);

            // Simpan alternatif yang berhasil diperingkat
            foreach ($snapshot['items'] as $rankedItem) {
                $altMeta = $segmentMapByCode[$rankedItem['code']] ?? null;
                if ($altMeta === null) {
                    continue;
                }

                $drivingCode = $rankedItem['largest_driving_criterion'] ?? null;
                $drivingName = $drivingCode !== null ? ($criterionNames[$drivingCode] ?? $drivingCode) : null;

                DecisionRunItem::query()->create([
                    'decision_run_id' => $run->id,
                    'road_segment_id' => (int) $altMeta['segment_id'],
                    'is_included' => true,
                    'exclusion_reason' => null,
                    'rank_position' => (int) $rankedItem['rank'],
                    'preference_score' => (float) $rankedItem['closeness'],
                    'd_plus' => (float) $rankedItem['d_plus'],
                    'd_minus' => (float) $rankedItem['d_minus'],
                    'largest_driving_criterion' => $drivingCode,
                    'input_values_snapshot' => [
                        'raw' => $rankedItem['raw'],
                        'assessment_id' => $altMeta['assessment_id'] ?? null,
                        'assessment_date' => $altMeta['assessment_date'] ?? null,
                        'source_report_ids' => $altMeta['source_report_ids'] ?? [],
                        'data_origin' => $altMeta['data_origin'] ?? 'unknown',
                        'is_demo' => (bool) ($altMeta['is_demo'] ?? false),
                    ],
                    'intermediate_values_snapshot' => [
                        'normalized' => $rankedItem['normalized'],
                        'weighted' => $rankedItem['weighted'],
                        'explanation' => sprintf(
                            'Peringkat #%d (C_i = %.4f, D+ = %.4f, D- = %.4f). Kriteria pendorong utama menuju solusi ideal: %s (%s).',
                            (int) $rankedItem['rank'],
                            (float) $rankedItem['closeness'],
                            (float) $rankedItem['d_plus'],
                            (float) $rankedItem['d_minus'],
                            (string) $drivingCode,
                            (string) $drivingName
                        ),
                    ],
                ]);
            }

            // Simpan alternatif yang dikeluarkan beserta alasannya (D-09 & PRD 05 §7)
            foreach ($snapshot['excluded'] as $excludedItem) {
                $altMeta = $segmentMapByCode[$excludedItem['code']] ?? null;
                if ($altMeta === null) {
                    continue;
                }

                DecisionRunItem::query()->create([
                    'decision_run_id' => $run->id,
                    'road_segment_id' => (int) $altMeta['segment_id'],
                    'is_included' => false,
                    'exclusion_reason' => implode('; ', $excludedItem['reasons'] ?? []),
                    'rank_position' => null,
                    'preference_score' => null,
                    'd_plus' => null,
                    'd_minus' => null,
                    'largest_driving_criterion' => null,
                    'input_values_snapshot' => [
                        'raw' => $altMeta['values'] ?? [],
                        'assessment_id' => $altMeta['assessment_id'] ?? null,
                        'source_report_ids' => $altMeta['source_report_ids'] ?? [],
                        'data_origin' => $altMeta['data_origin'] ?? 'unknown',
                        'is_demo' => (bool) ($altMeta['is_demo'] ?? false),
                    ],
                    'intermediate_values_snapshot' => null,
                ]);
            }

            $this->auditLogs->record($actor, 'decision_run.executed', 'decision_run', $run->id, [
                'run_code' => $run->run_code,
                'criterion_weight_version_id' => $weightVersion->id,
                'is_simulation' => $isSimulation,
                'input_hash' => $run->input_hash,
                'ranked_count' => count($snapshot['items']),
                'excluded_count' => count($snapshot['excluded']),
            ]);

            return $run->fresh(['weightVersion', 'items.roadSegment', 'executor:id,name,email,role']);
        });
    }

    /**
     * Memverifikasi reproduktibilitas snapshot historis dan menyusun payload detail lengkap (PRD 05 §8 & §12).
     *
     * @return array<string, mixed>
     */
    public function formatRunDetail(DecisionRun $run): array
    {
        $snapshot = is_array($run->input_snapshot_reference) ? $run->input_snapshot_reference : [];
        $reproduction = $this->snapshotService->reproduce($snapshot);

        $rankedItems = $run->items
            ->where('is_included', true)
            ->sortBy('rank_position')
            ->values()
            ->map(fn (DecisionRunItem $item) => [
                'id' => $item->id,
                'road_segment_id' => $item->road_segment_id,
                'road_reference_code' => $item->roadSegment?->road_reference_code,
                'road_segment_name' => $item->roadSegment?->name,
                'rank_position' => $item->rank_position,
                'preference_score' => $item->preference_score,
                'd_plus' => $item->d_plus,
                'd_minus' => $item->d_minus,
                'largest_driving_criterion' => $item->largest_driving_criterion,
                'input_values' => $item->input_values_snapshot['raw'] ?? [],
                'normalized_values' => $item->intermediate_values_snapshot['normalized'] ?? [],
                'weighted_values' => $item->intermediate_values_snapshot['weighted'] ?? [],
                'explanation' => $item->intermediate_values_snapshot['explanation'] ?? null,
                'data_origin' => $item->input_values_snapshot['data_origin'] ?? null,
                'assessment_date' => $item->input_values_snapshot['assessment_date'] ?? null,
                'source_report_ids' => $item->input_values_snapshot['source_report_ids'] ?? [],
                'is_demo' => (bool) ($item->input_values_snapshot['is_demo'] ?? false),
            ])
            ->all();

        $excludedItems = $run->items
            ->where('is_included', false)
            ->values()
            ->map(fn (DecisionRunItem $item) => [
                'id' => $item->id,
                'road_segment_id' => $item->road_segment_id,
                'road_reference_code' => $item->roadSegment?->road_reference_code,
                'road_segment_name' => $item->roadSegment?->name,
                'exclusion_reason' => $item->exclusion_reason,
                'input_values' => $item->input_values_snapshot['raw'] ?? [],
            ])
            ->all();

        return [
            'id' => $run->id,
            'run_code' => $run->run_code,
            'name' => $run->name,
            'algorithm_name' => $run->algorithm_name,
            'algorithm_version' => $run->algorithm_version,
            'status' => $run->status,
            'is_simulation' => (bool) $run->is_simulation,
            'input_hash' => $run->input_hash,
            'reproducibility_verified' => $reproduction['reproducible'],
            'executed_by' => $run->executor ? [
                'id' => $run->executor->id,
                'name' => $run->executor->name,
                'role' => $run->executor->role,
            ] : null,
            'executed_at' => $run->executed_at?->toIso8601String(),
            'weight_version' => $snapshot['weight_version_snapshot'] ?? null,
            'criteria_snapshot' => $snapshot['criteria_snapshot'] ?? [],
            'ideal_solutions' => $snapshot['ideal_solutions'] ?? null,
            'flags' => $snapshot['flags'] ?? null,
            'sensitivity_summary' => $snapshot['sensitivity_summary'] ?? null,
            'ranked_items' => $rankedItems,
            'excluded_items' => $excludedItems,
            'disclaimer' => $snapshot['disclaimer']
                ?? 'Rekomendasi prioritas berbasis data dan asumsi yang digunakan; bukan keputusan otomatis yang menggantikan kewenangan pemerintah.',
        ];
    }
}
