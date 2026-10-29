<?php

namespace Tests\Unit\DecisionEngine;

use App\Services\DecisionEngine\DecisionEngineException;
use App\Services\DecisionEngine\RankCorrelation;
use App\Services\DecisionEngine\SensitivityService;
use PHPUnit\Framework\TestCase;

class SensitivityServiceTest extends TestCase
{
    use LoadsReferenceFixture;

    private function setup3(): array
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $weights = [];
        foreach ($criteria as $j => $c) {
            $weights[$c['code']] = $ref['ahp']['weights'][$j];
        }

        return [$criteria, $weights, $alternatives, $ref];
    }

    /** Lampiran C.5: bobot sama menghasilkan urutan A1, A4, A3, A2 dan Spearman -0,40. */
    public function test_equal_weights_scenario_matches_lampiran_c5(): void
    {
        [$criteria, $weights, $alternatives, $ref] = $this->setup3();
        $analysis = (new SensitivityService())->analyze($criteria, $weights, $alternatives);

        $equal = null;
        foreach ($analysis['scenarios'] as $s) {
            if ($s['scenario'] === 'B:equal_weights') {
                $equal = $s;
            }
        }
        $this->assertNotNull($equal);
        $this->assertSame($ref['equal_weights']['rank'], $equal['ranks']);
        $this->assertEqualsWithDelta($ref['spearman_equal_vs_base'], $equal['spearman_vs_base'], 1e-9);
        $this->assertEqualsWithDelta(-0.40, $equal['spearman_vs_base'], 1e-9);
    }

    public function test_one_at_a_time_scenarios_keep_weights_summing_to_one(): void
    {
        [$criteria, $weights, $alternatives] = $this->setup3();
        $analysis = (new SensitivityService())->analyze($criteria, $weights, $alternatives);

        // 3 kriteria x 4 delta + 1 skenario bobot sama (tidak ada yang dilewati pada bobot ini)
        $this->assertCount(13, $analysis['scenarios']);
        $this->assertSame([], $analysis['skipped']);
        foreach ($analysis['scenarios'] as $s) {
            $this->assertEqualsWithDelta(1.0, array_sum($s['weights']), 1e-12, $s['scenario']);
            foreach ($s['weights'] as $w) {
                $this->assertGreaterThanOrEqual(0.0, $w);
            }
            $this->assertGreaterThanOrEqual(0.0, $s['top_k_overlap']);
            $this->assertLessThanOrEqual(1.0, $s['top_k_overlap']);
        }
    }

    public function test_changed_weight_is_exactly_scaled_by_delta(): void
    {
        [$criteria, $weights, $alternatives] = $this->setup3();
        $analysis = (new SensitivityService())->analyze($criteria, $weights, $alternatives);

        foreach ($analysis['scenarios'] as $s) {
            if ($s['scenario'] === 'A:K1:+10%') {
                $this->assertEqualsWithDelta($weights['K1'] * 1.10, $s['weights']['K1'], 1e-12);
                // Bobot lain diskalakan dengan faktor yang sama
                $ratio = $s['weights']['K2'] / $weights['K2'];
                $this->assertEqualsWithDelta($ratio, $s['weights']['K3'] / $weights['K3'], 1e-12);
                return;
            }
        }
        $this->fail('Skenario A:K1:+10% tidak ditemukan');
    }

    public function test_summary_contains_min_max_mean_rank_per_alternative(): void
    {
        [$criteria, $weights, $alternatives] = $this->setup3();
        $analysis = (new SensitivityService())->analyze($criteria, $weights, $alternatives);

        $this->assertSame(['A1', 'A2', 'A3', 'A4'], array_keys($analysis['summary']));
        foreach ($analysis['summary'] as $row) {
            $this->assertGreaterThanOrEqual($row['min_rank'], $row['base_rank']);
            $this->assertLessThanOrEqual($row['max_rank'], $row['base_rank']);
            $this->assertGreaterThanOrEqual($row['min_rank'], $row['mean_rank']);
            $this->assertLessThanOrEqual($row['max_rank'], $row['mean_rank']);
        }
        $this->assertSame(1, $analysis['summary']['A2']['base_rank']);
    }

    public function test_weight_that_would_exceed_one_is_skipped_not_applied(): void
    {
        $criteria = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'benefit']];
        $alts = [
            ['code' => 'A', 'values' => ['K1' => 1, 'K2' => 5]],
            ['code' => 'B', 'values' => ['K1' => 2, 'K2' => 4]],
        ];
        $analysis = (new SensitivityService())->analyze($criteria, ['K1' => 0.95, 'K2' => 0.05], $alts);

        $labels = array_column($analysis['skipped'], 'scenario');
        $this->assertContains('A:K1:+10%', $labels);
        $this->assertContains('A:K1:+20%', $labels);
    }

    public function test_spearman_known_values(): void
    {
        $c = new RankCorrelation();
        $a = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];

        $this->assertEqualsWithDelta(1.0, $c->spearman($a, $a), 1e-12);
        $this->assertEqualsWithDelta(-1.0, $c->spearman($a, ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1]), 1e-12);
        // rho = 1 - 6*sum(d^2)/(n(n^2-1)); d = (1,1,0,0)... -> 1 - 6*2/60 = 0.8
        $this->assertEqualsWithDelta(0.8, $c->spearman($a, ['A' => 2, 'B' => 1, 'C' => 3, 'D' => 4]), 1e-12);
        $this->assertNull($c->spearman(['A' => 1], ['A' => 1]));
        $this->assertNull($c->spearman(['A' => 1, 'B' => 1], ['A' => 1, 'B' => 2]));
    }

    public function test_spearman_rejects_mismatched_sets(): void
    {
        $this->expectException(DecisionEngineException::class);
        (new RankCorrelation())->spearman(['A' => 1, 'B' => 2], ['A' => 1, 'C' => 2]);
    }
}

