<?php

namespace Tests\Unit\DecisionEngine;

use App\Services\DecisionEngine\AhpService;
use App\Services\DecisionEngine\DecisionEngineException;
use PHPUnit\Framework\TestCase;

class AhpServiceTest extends TestCase
{
    use LoadsReferenceFixture;

    private const TOL = 1e-6;

    private AhpService $ahp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ahp = new AhpService();
    }

    /** Tes minimum 1: matriks semua 1 menghasilkan bobot sama dan CI = 0. */
    public function test_all_ones_matrix_gives_equal_weights_and_zero_ci(): void
    {
        foreach ([2, 3, 4, 7, 10] as $n) {
            $matrix = array_fill(0, $n, array_fill(0, $n, 1));
            $r = $this->ahp->computeWeights($matrix);

            foreach ($r['weights'] as $w) {
                $this->assertEqualsWithDelta(1 / $n, $w, 1e-12, "n={$n}");
            }
            $this->assertEqualsWithDelta(0.0, $r['ci'], 1e-12, "n={$n}");
            $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-12, "n={$n}");
            $this->assertEqualsWithDelta((float) $n, $r['lambda_max'], 1e-9, "n={$n}");
        }
    }

    /** Tes minimum 2: matriks konsisten sempurna (a_ij = w_i / w_j) menghasilkan CR ~ 0. */
    public function test_perfectly_consistent_matrix_has_cr_near_zero_and_recovers_weights(): void
    {
        $ref = $this->reference()['consistent_5x5'];
        $r = $this->ahp->computeWeights($ref['matrix']);

        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-9);
        $this->assertTrue($r['is_consistent']);
        foreach ($ref['weights'] as $i => $w) {
            $this->assertEqualsWithDelta($w, $r['weights'][$i], 1e-9);
        }
    }

    /** Tes minimum 3: contoh Lampiran C cocok dalam toleransi 1e-6 terhadap hitungan independen. */
    public function test_lampiran_c_matches_independent_reference(): void
    {
        $ref = $this->reference()['lampiran_c']['ahp'];
        $r = $this->ahp->computeWeights($this->reference()['lampiran_c']['matrix']);

        foreach ($ref['weights'] as $i => $w) {
            $this->assertEqualsWithDelta($w, $r['weights'][$i], self::TOL);
        }
        $this->assertEqualsWithDelta($ref['lambda_max'], $r['lambda_max'], self::TOL);
        $this->assertEqualsWithDelta($ref['ci'], $r['ci'], self::TOL);
        $this->assertEqualsWithDelta($ref['cr'], $r['cr'], self::TOL);
        $this->assertEqualsWithDelta(1.0, array_sum($r['weights']), 1e-12);
    }

    /** Angka yang dicetak pada tabel Lampiran C (4 desimal) juga harus terpenuhi. */
    public function test_lampiran_c_matches_printed_values(): void
    {
        $r = $this->ahp->computeWeights($this->reference()['lampiran_c']['matrix']);

        $this->assertEqualsWithDelta(0.6483, $r['weights'][0], 5e-5);
        $this->assertEqualsWithDelta(0.2297, $r['weights'][1], 5e-5);
        $this->assertEqualsWithDelta(0.1220, $r['weights'][2], 5e-5);
        $this->assertEqualsWithDelta(3.0037, $r['lambda_max'], 5e-5);
        $this->assertEqualsWithDelta(0.0018, $r['ci'], 5e-5);
        $this->assertEqualsWithDelta(0.0032, $r['cr'], 5e-5);
    }

    /** Tes minimum 4: matriks inkonsisten dengan CR > 0,10 ditandai tidak konsisten. */
    public function test_inconsistent_matrix_is_flagged(): void
    {
        $ref = $this->reference()['inconsistent_4x4'];
        $r = $this->ahp->computeWeights($ref['matrix']);

        $this->assertGreaterThan(0.10, $r['cr']);
        $this->assertFalse($r['is_consistent']);
        $this->assertEqualsWithDelta($ref['ahp']['cr'], $r['cr'], self::TOL);
    }

    public function test_consistency_threshold_boundary_matches_reference(): void
    {
        $below = $this->reference()['borderline_below'];
        $above = $this->reference()['borderline_above'];

        $rb = $this->ahp->computeWeights($below['matrix']);
        $ra = $this->ahp->computeWeights($above['matrix']);

        $this->assertEqualsWithDelta($below['ahp']['cr'], $rb['cr'], self::TOL);
        $this->assertLessThanOrEqual(0.10, $rb['cr']);
        $this->assertTrue($rb['is_consistent']);

        $this->assertEqualsWithDelta($above['ahp']['cr'], $ra['cr'], self::TOL);
        $this->assertGreaterThan(0.10, $ra['cr']);
        $this->assertFalse($ra['is_consistent']);
    }

    public function test_group_geometric_mean_matches_reference(): void
    {
        $ref = $this->reference()['group_gm'];
        $aggregated = $this->ahp->aggregateGeometricMean($ref['experts']);

        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $this->assertEqualsWithDelta($ref['aggregated'][$i][$j], $aggregated[$i][$j], 1e-9);
            }
        }
        $r = $this->ahp->computeWeights($aggregated);
        foreach ($ref['ahp']['weights'] as $i => $w) {
            $this->assertEqualsWithDelta($w, $r['weights'][$i], self::TOL);
        }
        $this->assertEqualsWithDelta($ref['ahp']['cr'], $r['cr'], self::TOL);
    }

    public function test_aggregation_of_single_expert_is_identity(): void
    {
        $m = $this->reference()['lampiran_c']['matrix'];
        $agg = $this->ahp->aggregateGeometricMean([$m]);

        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $this->assertEqualsWithDelta($m[$i][$j], $agg[$i][$j], 1e-12);
            }
        }
    }

    public function test_n_of_one_and_two_have_zero_cr(): void
    {
        $one = $this->ahp->computeWeights([[1]]);
        $this->assertSame([1.0], $one['weights']);
        $this->assertSame(0.0, $one['cr']);

        $two = $this->ahp->computeWeights([[1, 5], [1 / 5, 1]]);
        $this->assertSame(0.0, $two['cr']);
        $this->assertTrue($two['is_consistent']);
        $this->assertEqualsWithDelta(5 / 6, $two['weights'][0], 1e-9);
        $this->assertEqualsWithDelta(1 / 6, $two['weights'][1], 1e-9);
    }

    public function test_ri_table_matches_prd(): void
    {
        $expected = [0, 0, 0.58, 0.90, 1.12, 1.24, 1.32, 1.41, 1.45, 1.49];
        foreach ($expected as $i => $ri) {
            $this->assertSame((float) $ri, AhpService::RI[$i + 1]);
        }
    }

    public function test_matrix_from_judgments_builds_reciprocal_matrix(): void
    {
        $m = $this->ahp->matrixFromJudgments(3, [[0, 1, 3], [0, 2, 5], [2, 1, 2]]);

        $this->assertSame(3.0, $m[0][1]);
        $this->assertEqualsWithDelta(1 / 3, $m[1][0], 1e-15);
        $this->assertSame(5.0, $m[0][2]);
        $this->assertSame(2.0, $m[2][1]);
        $this->assertEqualsWithDelta(0.5, $m[1][2], 1e-15);
        $this->assertSame(1.0, $m[1][1]);
    }

    /**
     * @dataProvider invalidMatrices
     */
    public function test_invalid_matrices_are_rejected(array $matrix, string $code): void
    {
        try {
            $this->ahp->computeWeights($matrix);
            $this->fail('Seharusnya melempar DecisionEngineException');
        } catch (DecisionEngineException $e) {
            $this->assertSame($code, $e->errorCode());
        }
    }

    public function invalidMatrices(): array
    {
        $big = array_fill(0, 11, array_fill(0, 11, 1));

        return [
            'kosong' => [[], 'ahp_invalid_size'],
            'lebih dari 10' => [$big, 'ahp_invalid_size'],
            'tidak persegi' => [[[1, 2], [0.5]], 'ahp_not_square'],
            'diagonal bukan 1' => [[[2, 1], [1, 1]], 'ahp_invalid_diagonal'],
            'tidak resiprokal' => [[[1, 3], [1, 1]], 'ahp_not_reciprocal'],
            'nol' => [[[1, 0], [0, 1]], 'ahp_invalid_value'],
            'negatif' => [[[1, -2], [-0.5, 1]], 'ahp_invalid_value'],
            'bukan angka' => [[[1, 'x'], [1, 1]], 'ahp_invalid_value'],
            'NaN' => [[[1, NAN], [NAN, 1]], 'ahp_invalid_value'],
        ];
    }

    /**
     * @dataProvider invalidJudgments
     */
    public function test_invalid_judgments_are_rejected(int $n, array $judgments, string $code): void
    {
        try {
            $this->ahp->matrixFromJudgments($n, $judgments);
            $this->fail('Seharusnya melempar DecisionEngineException');
        } catch (DecisionEngineException $e) {
            $this->assertSame($code, $e->errorCode());
        }
    }

    public function invalidJudgments(): array
    {
        return [
            'di luar skala 10' => [2, [[0, 1, 10]], 'ahp_invalid_scale'],
            'di luar skala 1/10' => [2, [[0, 1, 0.1]], 'ahp_invalid_scale'],
            'pecahan 2.5' => [2, [[0, 1, 2.5]], 'ahp_invalid_scale'],
            'nol' => [2, [[0, 1, 0]], 'ahp_invalid_scale'],
            'bukan angka' => [2, [[0, 1, 'tiga']], 'ahp_invalid_scale'],
            'pasangan ganda' => [3, [[0, 1, 3], [1, 0, 2], [0, 2, 1], [1, 2, 1]], 'ahp_duplicate_pair'],
            'tidak lengkap' => [3, [[0, 1, 3]], 'ahp_incomplete_judgments'],
            'diagonal' => [2, [[1, 1, 3]], 'ahp_invalid_pair'],
            'indeks di luar' => [2, [[0, 5, 3]], 'ahp_invalid_pair'],
            'n terlalu besar' => [11, [], 'ahp_invalid_size'],
        ];
    }

    public function test_aggregation_requires_same_size_and_at_least_one_matrix(): void
    {
        try {
            $this->ahp->aggregateGeometricMean([]);
            $this->fail('harus gagal');
        } catch (DecisionEngineException $e) {
            $this->assertSame('ahp_no_matrices', $e->errorCode());
        }

        try {
            $this->ahp->aggregateGeometricMean([[[1]], [[1, 2], [0.5, 1]]]);
            $this->fail('harus gagal');
        } catch (DecisionEngineException $e) {
            $this->assertSame('ahp_size_mismatch', $e->errorCode());
        }
    }

    public function test_saaty_scale_accepts_integers_and_reciprocals(): void
    {
        foreach ([1, 2, 5, 9, 1 / 2, 1 / 3, 1 / 9, 9.0] as $v) {
            $this->ahp->assertSaatyValue($v);
        }
        $this->addToAssertionCount(1);
    }
}

