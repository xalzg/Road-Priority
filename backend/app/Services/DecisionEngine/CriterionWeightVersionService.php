<?php

namespace App\Services\DecisionEngine;

use App\Models\AhpPairwiseComparison;
use App\Models\CriterionWeightItem;
use App\Models\CriterionWeightVersion;
use App\Models\DecisionCriterion;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CriterionWeightVersionService
{
    public function __construct(
        private AhpService $ahpService,
        private AuditLogService $auditLogs
    ) {
    }

    /**
     * Membuat draft versi bobot baru (PRD 05 §5.4 & §11).
     *
     * @param  array<string, mixed>  $validated
     */
    public function createDraft(User $actor, array $validated): CriterionWeightVersion
    {
        return DB::transaction(function () use ($actor, $validated): CriterionWeightVersion {
            $activeCount = DecisionCriterion::query()->where('is_active', true)->count();

            $version = CriterionWeightVersion::query()->create([
                'version_name' => trim((string) $validated['version_name']),
                'status' => CriterionWeightVersion::STATUS_DRAFT,
                'criteria_count' => $activeCount,
                'aggregation_method' => $validated['aggregation_method'] ?? 'geometric_mean_aij',
                'created_by' => $actor->id,
            ]);

            $this->auditLogs->record($actor, 'weight_version.created', 'criterion_weight_version', $version->id, [
                'version_name' => $version->version_name,
                'status' => $version->status,
            ]);

            return $version->fresh(['weightItems.criterion', 'ahpComparisons']);
        });
    }

    /**
     * Menyimpan matriks perbandingan berpasangan AHP, menghitung bobot eigenvector utama,
     * serta menguji CI dan CR (PRD 05 §5.1–§5.4).
     *
     * @param  array<string, mixed>  $validated
     */
    public function calculateAhp(
        User $actor,
        CriterionWeightVersion $version,
        array $validated
    ): CriterionWeightVersion {
        if (
            in_array($version->status, [CriterionWeightVersion::STATUS_APPROVED, CriterionWeightVersion::STATUS_ARCHIVED], true)
            || $version->decisionRuns()->exists()
        ) {
            throw new ConflictHttpException(
                'Approved, archived, or historically used weight versions are immutable and cannot be recalculated.'
            );
        }

        // Ambil kriteria yang menjadi objek perbandingan (urut kode secara deterministik)
        $criterionIds = $validated['criterion_ids'] ?? null;
        $criteriaQuery = DecisionCriterion::query()->where('is_active', true)->orderBy('code');
        if (is_array($criterionIds) && $criterionIds !== []) {
            $criteriaQuery->whereIn('id', $criterionIds);
        }
        $criteria = $criteriaQuery->get()->values();
        $n = $criteria->count();

        if ($n < 1 || $n > AhpService::MAX_CRITERIA) {
            throw new DecisionEngineException(
                'ahp_invalid_size',
                'Jumlah kriteria aktif harus antara 1 sampai ' . AhpService::MAX_CRITERIA . '.'
            );
        }

        $indexById = [];
        foreach ($criteria as $idx => $criterion) {
            $indexById[(int) $criterion->id] = $idx;
        }

        // Kelompokkan perbandingan per pakar (mendukung 1 pakar maupun multi-pakar AIJ geometric mean)
        $rawComparisons = $validated['comparisons'] ?? [];
        $byExpert = [];
        foreach ($rawComparisons as $row) {
            $expertId = isset($row['expert_id']) ? (int) $row['expert_id'] : (int) $actor->id;
            $aId = (int) $row['criterion_a_id'];
            $bId = (int) $row['criterion_b_id'];
            $val = (float) $row['comparison_value'];

            if (! isset($indexById[$aId]) || ! isset($indexById[$bId])) {
                throw new DecisionEngineException(
                    'ahp_unknown_criterion',
                    'Terdapat ID kriteria yang tidak aktif atau tidak termasuk dalam versi ini.'
                );
            }

            $byExpert[$expertId][] = [
                'i' => $indexById[$aId],
                'j' => $indexById[$bId],
                'criterion_a_id' => $aId,
                'criterion_b_id' => $bId,
                'value' => $val,
            ];
        }

        if ($n > 1 && $byExpert === []) {
            throw new DecisionEngineException(
                'ahp_incomplete_judgments',
                'Daftar perbandingan berpasangan (comparisons) wajib diisi untuk jumlah kriteria lebih dari 1.'
            );
        }

        $expertMatrices = [];
        if ($n === 1) {
            $expertMatrices[] = [[1.0]];
        } else {
            foreach ($byExpert as $expertJudgments) {
                $tuples = array_map(
                    static fn (array $item) => [$item['i'], $item['j'], $item['value']],
                    $expertJudgments
                );
                $expertMatrices[] = $this->ahpService->matrixFromJudgments($n, $tuples);
            }
        }

        $aggregatedMatrix = count($expertMatrices) === 1
            ? $expertMatrices[0]
            : $this->ahpService->aggregateGeometricMean($expertMatrices);

        $ahpResult = $this->ahpService->computeWeights($aggregatedMatrix);

        return DB::transaction(function () use (
            $actor,
            $version,
            $criteria,
            $byExpert,
            $ahpResult
        ): CriterionWeightVersion {
            $version->ahpComparisons()->delete();
            $version->weightItems()->delete();

            foreach ($byExpert as $expertId => $expertJudgments) {
                foreach ($expertJudgments as $jRow) {
                    AhpPairwiseComparison::query()->create([
                        'criterion_weight_version_id' => $version->id,
                        'expert_id' => $expertId,
                        'criterion_a_id' => $jRow['criterion_a_id'],
                        'criterion_b_id' => $jRow['criterion_b_id'],
                        'comparison_value' => $jRow['value'],
                    ]);
                }
            }

            foreach ($criteria as $idx => $criterion) {
                CriterionWeightItem::query()->create([
                    'criterion_weight_version_id' => $version->id,
                    'decision_criterion_id' => $criterion->id,
                    'weight_value' => $ahpResult['weights'][$idx],
                ]);
            }

            $version->criteria_count = $ahpResult['n'];
            $version->lambda_max = $ahpResult['lambda_max'];
            $version->consistency_index = $ahpResult['ci'];
            $version->consistency_ratio = $ahpResult['cr'];
            $version->is_consistent = $ahpResult['is_consistent'];
            $version->status = CriterionWeightVersion::STATUS_COMPUTED;
            $version->save();

            $this->auditLogs->record($actor, 'weight_version.calculated', 'criterion_weight_version', $version->id, [
                'criteria_count' => $version->criteria_count,
                'lambda_max' => $version->lambda_max,
                'consistency_index' => $version->consistency_index,
                'consistency_ratio' => $version->consistency_ratio,
                'is_consistent' => $version->is_consistent,
                'experts_count' => count($byExpert),
            ]);

            return $version->fresh(['weightItems.criterion', 'ahpComparisons']);
        });
    }

    /**
     * Mengaktifkan/menyetujui versi bobot setelah memenuhi ambang konsistensi CR <= 0,10 (PRD 05 §5.3 & §5.4).
     */
    public function activateVersion(User $actor, CriterionWeightVersion $version): CriterionWeightVersion
    {
        if ($version->status === CriterionWeightVersion::STATUS_DRAFT || $version->weightItems()->count() === 0) {
            throw new DecisionEngineException(
                'weight_version_not_calculated',
                'Versi bobot harus dihitung (calculate) terlebih dahulu sebelum dapat diaktifkan.'
            );
        }

        if (! $version->is_consistent || (float) $version->consistency_ratio > AhpService::CR_THRESHOLD) {
            throw new DecisionEngineException(
                'weight_version_inconsistent',
                'Versi bobot tidak memenuhi ambang konsistensi (CR = '
                . round((float) $version->consistency_ratio, 4)
                . ' > ' . AhpService::CR_THRESHOLD
                . '). Tinjau kembali perbandingan berpasangan AHP.'
            );
        }

        return DB::transaction(function () use ($actor, $version): CriterionWeightVersion {
            $version->status = CriterionWeightVersion::STATUS_APPROVED;
            $version->approved_by = $actor->id;
            $version->approved_at = now();
            $version->save();

            $this->auditLogs->record($actor, 'weight_version.approved', 'criterion_weight_version', $version->id, [
                'version_name' => $version->version_name,
                'consistency_ratio' => $version->consistency_ratio,
            ]);

            return $version->fresh(['weightItems.criterion', 'ahpComparisons', 'approver:id,name,email,role']);
        });
    }
}

