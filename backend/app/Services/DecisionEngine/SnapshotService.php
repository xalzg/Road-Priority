<?php

namespace App\Services\DecisionEngine;

/**
 * Membangun, memvalidasi kelayakan, dan mereproduksi snapshot decision run
 * (Master PRD §10, PRD 02 §2.4, PRD 05 §5.2–5.5, NFR-12).
 *
 * Kelas ini murni (tanpa ketergantungan tabel database) sehingga dapat diuji
 * sebelum skema PRD 02 dibuat. Pada tahap PRD 02/05 integrasi DB, hasil
 * `createSnapshot()` disimpan ke `decision_runs` dan `decision_run_items`.
 */
class SnapshotService
{
    private TopsisService $topsis;
    private InputHasher $hasher;

    public function __construct(?TopsisService $topsis = null, ?InputHasher $hasher = null)
    {
        $this->hasher = $hasher ?? new InputHasher();
        $this->topsis = $topsis ?? new TopsisService($this->hasher);
    }

    /**
     * Buat snapshot beku dari satu eksekusi AHP-TOPSIS.
     *
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array{
     *     version_id?: int|string|null,
     *     label?: string,
     *     status: string,
     *     cr: float|int,
     *     is_consistent: bool,
     *     weights: array<string, float|int>
     * }  $weightVersion
     * @param  array<int, array{
     *     code: string,
     *     values: array<string, mixed>,
     *     is_demo?: bool,
     *     data_origin?: string,
     *     source_report_ids?: array<int, int|string>
     * }>  $alternatives
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function createSnapshot(
        array $criteria,
        array $weightVersion,
        array $alternatives,
        bool $isSimulation = false,
        array $parameters = []
    ): array {
        $this->validateWeightVersionForRun($weightVersion, $isSimulation);
        $this->validateDataOriginForRun($alternatives, $isSimulation);

        // Salin beku input sebelum perhitungan
        $frozenCriteria = $this->deepCopy($criteria);
        $frozenWeights = $this->deepCopy($weightVersion['weights']);
        $frozenAlternatives = $this->deepCopy($alternatives);
        $frozenParameters = $this->deepCopy($parameters);

        $calc = $this->topsis->calculate($frozenCriteria, $frozenWeights, $frozenAlternatives);

        // Petakan metadata sumber (source_report_ids, data_origin) & kriteria pendorong terbesar
        $metaByCode = [];
        foreach ($frozenAlternatives as $alt) {
            $metaByCode[$alt['code']] = [
                'source_report_ids' => array_values($alt['source_report_ids'] ?? []),
                'data_origin' => $alt['data_origin'] ?? 'unknown',
                'is_demo' => (bool) ($alt['is_demo'] ?? false),
            ];
        }

        $items = [];
        foreach ($calc['items'] as $item) {
            $code = $item['code'];
            $items[] = $item + [
                'largest_driving_criterion' => $this->largestDrivingCriterion(
                    $item['weighted'],
                    $calc['ideal_positive'],
                    $calc['ideal_negative']
                ),
                'source_report_ids' => $metaByCode[$code]['source_report_ids'] ?? [],
                'data_origin' => $metaByCode[$code]['data_origin'] ?? 'unknown',
                'is_demo' => $metaByCode[$code]['is_demo'] ?? false,
            ];
        }

        return [
            'algorithm_version' => $calc['algorithm_version'],
            'input_hash' => $calc['input_hash'],
            'is_simulation' => $isSimulation,
            'parameters' => $frozenParameters,
            'weight_version_snapshot' => [
                'version_id' => $weightVersion['version_id'] ?? null,
                'label' => $weightVersion['label'] ?? null,
                'status' => $weightVersion['status'],
                'cr' => (float) $weightVersion['cr'],
                'is_consistent' => (bool) $weightVersion['is_consistent'],
                'weights' => $calc['weights'],
            ],
            'criteria_snapshot' => $calc['criteria'],
            'alternatives_snapshot' => $this->canonicalAlternativesSnapshot($frozenAlternatives),
            'ideal_solutions' => [
                'positive' => $calc['ideal_positive'],
                'negative' => $calc['ideal_negative'],
            ],
            'items' => $items,
            'excluded' => $calc['excluded'],
            'flags' => $calc['flags'],
            'disclaimer' => 'Prototipe penelitian dan alat bantu pengambilan keputusan. '
                . 'Hasil ini tidak menggantikan inspeksi teknis, kewenangan pemerintah, '
                . 'atau keputusan resmi penanganan jalan.',
        ];
    }

    /**
     * Jalankan ulang perhitungan dari snapshot beku dan pastikan hasilnya identik (NFR-12).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{reproducible: bool, input_hash_matches: bool, recalculated: array<string, mixed>}
     */
    public function reproduce(array $snapshot): array
    {
        if (($snapshot['algorithm_version'] ?? null) !== TopsisService::ALGORITHM_VERSION) {
            throw new DecisionEngineException(
                'snapshot_algorithm_mismatch',
                'Versi algoritma snapshot tidak cocok dengan mesin saat ini.'
            );
        }

        $recalculated = $this->topsis->calculate(
            $snapshot['criteria_snapshot'] ?? [],
            $snapshot['weight_version_snapshot']['weights'] ?? [],
            $snapshot['alternatives_snapshot'] ?? []
        );

        $hashMatches = ($recalculated['input_hash'] === ($snapshot['input_hash'] ?? null));
        if (! $hashMatches) {
            throw new DecisionEngineException(
                'snapshot_hash_mismatch',
                'Hash input hasil reproduksi tidak cocok dengan hash pada snapshot.'
            );
        }

        $origItems = $snapshot['items'] ?? [];
        if (count($recalculated['items']) !== count($origItems)) {
            throw new DecisionEngineException('snapshot_reproduction_failed', 'Jumlah alternatif hasil reproduksi berbeda.');
        }

        foreach ($recalculated['items'] as $idx => $item) {
            $orig = $origItems[$idx];
            if ($item['code'] !== $orig['code']
                || $item['rank'] !== $orig['rank']
                || abs($item['closeness'] - $orig['closeness']) > 1e-12
                || abs($item['d_plus'] - $orig['d_plus']) > 1e-12
                || abs($item['d_minus'] - $orig['d_minus']) > 1e-12
            ) {
                throw new DecisionEngineException(
                    'snapshot_reproduction_failed',
                    "Hasil reproduksi alternatif {$item['code']} berbeda dari snapshot."
                );
            }
        }

        return [
            'reproducible' => true,
            'input_hash_matches' => true,
            'recalculated' => $recalculated,
        ];
    }

    /**
     * PRD 05 §5.2 & §5.4: Hanya versi approved dengan CR <= 0,10 yang boleh dipakai run resmi.
     *
     * @param  array<string, mixed>  $weightVersion
     */
    private function validateWeightVersionForRun(array $weightVersion, bool $isSimulation): void
    {
        $status = $weightVersion['status'] ?? null;
        $cr = $weightVersion['cr'] ?? null;
        $isConsistent = $weightVersion['is_consistent'] ?? null;

        if (! is_string($status) || (! is_int($cr) && ! is_float($cr)) || ! is_bool($isConsistent)) {
            throw new DecisionEngineException('weight_version_invalid', 'Metadata versi bobot tidak lengkap.');
        }

        if (! $isSimulation) {
            if ($status !== 'approved') {
                throw new DecisionEngineException(
                    'weight_version_not_approved',
                    'Run resmi hanya boleh memakai versi bobot berstatus approved.'
                );
            }
            if (! $isConsistent || (float) $cr > AhpService::CR_THRESHOLD) {
                throw new DecisionEngineException(
                    'weight_version_inconsistent',
                    'Run resmi tidak boleh memakai versi bobot dengan CR > 0,10.'
                );
            }
        }
    }

    /**
     * Master PRD §0.1 & §13: Data demo tidak boleh masuk perhitungan resmi.
     *
     * @param  array<int, array<string, mixed>>  $alternatives
     */
    private function validateDataOriginForRun(array $alternatives, bool $isSimulation): void
    {
        if ($isSimulation) {
            return;
        }
        foreach ($alternatives as $alt) {
            $isDemo = (bool) ($alt['is_demo'] ?? false);
            $origin = $alt['data_origin'] ?? null;
            if ($isDemo || $origin === 'demo') {
                $code = $alt['code'] ?? '?';
                throw new DecisionEngineException(
                    'demo_data_in_official_run',
                    "Data demo ({$code}) tidak boleh dimasukkan ke dalam decision run resmi."
                );
            }
        }
    }

    /**
     * Kriteria pendorong terbesar menuju solusi ideal positif:
     * kriteria dengan kedekatan relatif tertinggi terhadap A+ pada rentang [A-, A+],
     * dikalikan bobot terbobotnya (yaitu kontribusi terhadap pengurangan D+ / penambahan D-).
     * Secara operasional: argmax_j |v_ij - A-_j| (kontribusi jarak dari solusi ideal negatif).
     *
     * @param  array<string, float>  $weighted
     * @param  array<string, float>  $idealPositive
     * @param  array<string, float>  $idealNegative
     */
    private function largestDrivingCriterion(array $weighted, array $idealPositive, array $idealNegative): string
    {
        $bestCode = '';
        $bestScore = -INF;
        foreach ($weighted as $code => $v) {
            // Kontribusi terhadap kuadrat jarak dari solusi anti-ideal (D-^2)
            $contribution = abs($v - ($idealNegative[$code] ?? 0.0));
            if ($contribution > $bestScore + TopsisService::EQUAL_VALUE_TOLERANCE
                || (abs($contribution - $bestScore) <= TopsisService::EQUAL_VALUE_TOLERANCE
                    && ($bestCode === '' || strcmp($code, $bestCode) < 0))
            ) {
                $bestScore = $contribution;
                $bestCode = $code;
            }
        }

        return $bestCode;
    }

    /**
     * @param  array<int, array<string, mixed>>  $alternatives
     * @return array<int, array{code: string, values: array<string, mixed>}>
     */
    private function canonicalAlternativesSnapshot(array $alternatives): array
    {
        $out = [];
        foreach ($alternatives as $alt) {
            $out[] = [
                'code' => (string) ($alt['code'] ?? ''),
                'values' => is_array($alt['values'] ?? null) ? $alt['values'] : [],
            ];
        }
        usort($out, static fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $out;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function deepCopy($value)
    {
        return json_decode(json_encode($value), true);
    }
}
