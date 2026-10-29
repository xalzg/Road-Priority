<?php

namespace App\Services\DecisionEngine;

/**
 * Analisis sensitivitas bobot (Master PRD §5.6):
 *  - Skenario A: satu bobot diubah sebesar ±10% dan ±20% (relatif), bobot lain
 *    diskalakan proporsional agar jumlah tetap 1.
 *  - Skenario B: bobot sama untuk semua kriteria.
 * Skenario C (Monte Carlo, opsional) belum diimplementasikan.
 */
class SensitivityService
{
    public const DELTAS = [-0.20, -0.10, 0.10, 0.20];

    private TopsisService $topsis;
    private RankCorrelation $correlation;

    public function __construct(?TopsisService $topsis = null, ?RankCorrelation $correlation = null)
    {
        $this->topsis = $topsis ?? new TopsisService();
        $this->correlation = $correlation ?? new RankCorrelation();
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<string, float|int>  $weights
     * @param  array<int, array<string, mixed>>  $alternatives
     * @return array<string, mixed>
     */
    public function analyze(array $criteria, array $weights, array $alternatives, int $topK = 3): array
    {
        $base = $this->topsis->calculate($criteria, $weights, $alternatives);
        $baseRanks = $this->ranksOf($base);
        $codes = array_keys($base['weights']);

        $scenarios = [];
        $skipped = [];

        // Skenario A
        foreach ($codes as $code) {
            $w = $base['weights'][$code];
            foreach (self::DELTAS as $delta) {
                $label = sprintf('A:%s:%+d%%', $code, (int) round($delta * 100));
                $changed = $w * (1.0 + $delta);
                if ($w >= 1.0 || $changed > 1.0 || $changed < 0.0) {
                    $skipped[] = ['scenario' => $label, 'reason' => 'bobot hasil perubahan di luar rentang [0,1]'];
                    continue;
                }
                $scale = (1.0 - $changed) / (1.0 - $w);
                $new = [];
                foreach ($codes as $k) {
                    $new[$k] = $k === $code ? $changed : $base['weights'][$k] * $scale;
                }
                $scenarios[] = $this->runScenario($label, $criteria, $new, $alternatives, $baseRanks, $topK);
            }
        }

        // Skenario B
        $equal = [];
        foreach ($codes as $k) {
            $equal[$k] = 1.0 / count($codes);
        }
        $scenarios[] = $this->runScenario('B:equal_weights', $criteria, $equal, $alternatives, $baseRanks, $topK);

        // Ringkasan per alternatif: peringkat min/maks/rata-rata di seluruh skenario + dasar.
        $summary = [];
        foreach ($baseRanks as $alt => $baseRank) {
            $ranks = [$baseRank];
            foreach ($scenarios as $s) {
                $ranks[] = $s['ranks'][$alt];
            }
            $summary[$alt] = [
                'base_rank' => $baseRank,
                'min_rank' => min($ranks),
                'max_rank' => max($ranks),
                'mean_rank' => array_sum($ranks) / count($ranks),
            ];
        }
        ksort($summary, SORT_STRING);

        return [
            'algorithm_version' => TopsisService::ALGORITHM_VERSION,
            'top_k' => $topK,
            'base_ranks' => $baseRanks,
            'scenarios' => $scenarios,
            'skipped' => $skipped,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, float>  $weights
     * @param  array<string, int>  $baseRanks
     * @return array<string, mixed>
     */
    private function runScenario(string $label, array $criteria, array $weights, array $alternatives, array $baseRanks, int $topK): array
    {
        $result = $this->topsis->calculate($criteria, $weights, $alternatives);
        $ranks = $this->ranksOf($result);

        $baseTop = $this->topCodes($baseRanks, $topK);
        $scenTop = $this->topCodes($ranks, $topK);
        $k = max(1, min($topK, count($ranks)));

        return [
            'scenario' => $label,
            'weights' => $weights,
            'ranks' => $ranks,
            'spearman_vs_base' => $this->correlation->spearman($baseRanks, $ranks),
            'top_k_overlap' => count(array_intersect($baseTop, $scenTop)) / $k,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function ranksOf(array $result): array
    {
        $ranks = [];
        foreach ($result['items'] as $item) {
            $ranks[$item['code']] = $item['rank'];
        }

        return $ranks;
    }

    /**
     * @param  array<string, int>  $ranks
     * @return string[]
     */
    private function topCodes(array $ranks, int $k): array
    {
        asort($ranks);

        return array_slice(array_keys($ranks), 0, $k);
    }
}

