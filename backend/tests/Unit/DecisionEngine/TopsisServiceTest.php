<?php

namespace Tests\Unit\DecisionEngine;

use App\Services\DecisionEngine\DecisionEngineException;
use App\Services\DecisionEngine\TopsisService;
use PHPUnit\Framework\TestCase;

class TopsisServiceTest extends TestCase
{
    use LoadsReferenceFixture;

    private const TOL = 1e-6;

    private TopsisService $topsis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topsis = new TopsisService();
    }

    private function weightsOf(array $list, array $criteria): array
    {
        $w = [];
        foreach ($criteria as $j => $c) {
            $w[$c['code']] = $list[$j];
        }

        return $w;
    }

    private function byCode(array $result): array
    {
        $out = [];
        foreach ($result['items'] as $item) {
            $out[$item['code']] = $item;
        }

        return $out;
    }

    /** Tes minimum 3 dan 11: Lampiran C cocok dengan hitungan independen (toleransi 1e-6). */
    public function test_lampiran_c_matches_independent_reference(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $result = $this->topsis->calculate($criteria, $this->weightsOf($ref['ahp']['weights'], $criteria), $alternatives);
        $items = $this->byCode($result);
        $t = $ref['topsis'];

        foreach ($t['codes'] as $i => $code) {
            $this->assertEqualsWithDelta($t['d_plus'][$i], $items[$code]['d_plus'], self::TOL, "D+ {$code}");
            $this->assertEqualsWithDelta($t['d_minus'][$i], $items[$code]['d_minus'], self::TOL, "D- {$code}");
            $this->assertEqualsWithDelta($t['closeness'][$i], $items[$code]['closeness'], self::TOL, "C {$code}");
            $this->assertSame($t['rank'][$code], $items[$code]['rank'], "rank {$code}");
            foreach (['K1', 'K2', 'K3'] as $j => $k) {
                $this->assertEqualsWithDelta($t['normalized'][$i][$j], $items[$code]['normalized'][$k], self::TOL);
                $this->assertEqualsWithDelta($t['weighted'][$i][$j], $items[$code]['weighted'][$k], self::TOL);
            }
        }
        foreach (['K1', 'K2', 'K3'] as $j => $k) {
            $this->assertEqualsWithDelta($t['a_plus'][$j], $result['ideal_positive'][$k], self::TOL);
            $this->assertEqualsWithDelta($t['a_minus'][$j], $result['ideal_negative'][$k], self::TOL);
        }
        $this->assertSame(['A2', 'A1', 'A3', 'A4'], array_column($result['items'], 'code'));
    }

    /** Angka yang dicetak pada tabel Lampiran C.4 (4 desimal). */
    public function test_lampiran_c_matches_printed_table(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $result = $this->topsis->calculate($criteria, $this->weightsOf($ref['ahp']['weights'], $criteria), $alternatives);
        $items = $this->byCode($result);

        $printed = [
            'A1' => [0.1085, 0.1959, 0.6436, 2],
            'A2' => [0.1285, 0.2647, 0.6732, 1],
            'A3' => [0.1799, 0.1391, 0.4360, 3],
            'A4' => [0.2665, 0.1078, 0.2880, 4],
        ];
        foreach ($printed as $code => [$dp, $dm, $c, $rank]) {
            $this->assertEqualsWithDelta($dp, $items[$code]['d_plus'], 5e-5, $code);
            $this->assertEqualsWithDelta($dm, $items[$code]['d_minus'], 5e-5, $code);
            $this->assertEqualsWithDelta($c, $items[$code]['closeness'], 5e-5, $code);
            $this->assertSame($rank, $items[$code]['rank'], $code);
        }
    }

    /** Tes minimum 5: benefit dan cost menghasilkan solusi ideal yang benar. */
    public function test_ideal_solutions_respect_benefit_and_cost(): void
    {
        $criteria = [['code' => 'B', 'type' => 'benefit'], ['code' => 'C', 'type' => 'cost']];
        $alts = [
            ['code' => 'X', 'values' => ['B' => 3, 'C' => 10]],
            ['code' => 'Y', 'values' => ['B' => 4, 'C' => 20]],
        ];
        $result = $this->topsis->calculate($criteria, ['B' => 0.5, 'C' => 0.5], $alts);
        $items = $this->byCode($result);

        // Benefit: ideal positif = maksimum terbobot; cost: minimum terbobot.
        $this->assertEqualsWithDelta($items['Y']['weighted']['B'], $result['ideal_positive']['B'], 1e-12);
        $this->assertEqualsWithDelta($items['X']['weighted']['B'], $result['ideal_negative']['B'], 1e-12);
        $this->assertEqualsWithDelta($items['X']['weighted']['C'], $result['ideal_positive']['C'], 1e-12);
        $this->assertEqualsWithDelta($items['Y']['weighted']['C'], $result['ideal_negative']['C'], 1e-12);
    }

    public function test_independent_5x3_reference_with_mixed_types(): void
    {
        $ref = $this->reference()['topsis_5x3'];
        $criteria = [];
        foreach ($ref['types'] as $j => $type) {
            $criteria[] = ['code' => 'K' . ($j + 1), 'type' => $type];
        }
        $alts = [];
        foreach ($ref['alternatives'] as $code => $v) {
            $alts[] = ['code' => $code, 'values' => ['K1' => $v[0], 'K2' => $v[1], 'K3' => $v[2]]];
        }
        $result = $this->topsis->calculate($criteria, $this->weightsOf($ref['weights'], $criteria), $alts);
        $items = $this->byCode($result);

        foreach ($ref['topsis']['codes'] as $i => $code) {
            $this->assertEqualsWithDelta($ref['topsis']['closeness'][$i], $items[$code]['closeness'], self::TOL, $code);
            $this->assertSame($ref['topsis']['rank'][$code], $items[$code]['rank'], $code);
        }
    }

    /** Tes minimum 6: kolom konstan, satu alternatif, dan matriks kosong. */
    public function test_single_alternative_is_rejected(): void
    {
        $this->expectEngineError('insufficient_alternatives', function () {
            $this->topsis->calculate(
                [['code' => 'K1', 'type' => 'benefit']],
                ['K1' => 1],
                [['code' => 'A', 'values' => ['K1' => 5]]]
            );
        });
    }

    public function test_empty_alternatives_and_empty_criteria_are_rejected(): void
    {
        $this->expectEngineError('insufficient_alternatives', function () {
            $this->topsis->calculate([['code' => 'K1', 'type' => 'benefit']], ['K1' => 1], []);
        });
        $this->expectEngineError('criteria_empty', function () {
            $this->topsis->calculate([], [], [['code' => 'A', 'values' => []], ['code' => 'B', 'values' => []]]);
        });
    }

    public function test_constant_column_is_flagged_and_does_not_break(): void
    {
        $criteria = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'benefit']];
        $alts = [
            ['code' => 'A', 'values' => ['K1' => 5, 'K2' => 7]],
            ['code' => 'B', 'values' => ['K1' => 5, 'K2' => 3]],
            ['code' => 'C', 'values' => ['K1' => 5, 'K2' => 9]],
        ];
        $result = $this->topsis->calculate($criteria, ['K1' => 0.5, 'K2' => 0.5], $alts);

        $this->assertSame(['K1'], $result['flags']['constant_criteria']);
        $this->assertSame([], $result['flags']['zero_norm_criteria']);
        $this->assertSame(['C', 'A', 'B'], array_column($result['items'], 'code'));
        foreach ($result['items'] as $item) {
            $this->assertTrue(is_finite($item['closeness']));
        }
    }

    public function test_zero_column_sets_normalized_to_zero_and_flags_criterion(): void
    {
        $criteria = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'cost']];
        $alts = [
            ['code' => 'A', 'values' => ['K1' => 0, 'K2' => 4]],
            ['code' => 'B', 'values' => ['K1' => 0, 'K2' => 8]],
        ];
        $result = $this->topsis->calculate($criteria, ['K1' => 0.5, 'K2' => 0.5], $alts);

        $this->assertSame(['K1'], $result['flags']['zero_norm_criteria']);
        $items = $this->byCode($result);
        $this->assertSame(0.0, $items['A']['normalized']['K1']);
        $this->assertSame(0.0, $items['B']['normalized']['K1']);
        $this->assertSame(1, $items['A']['rank']); // biaya lebih kecil -> lebih baik
    }

    public function test_identical_alternatives_make_run_non_discriminative(): void
    {
        $criteria = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'cost']];
        $alts = [
            ['code' => 'B', 'values' => ['K1' => 5, 'K2' => 7]],
            ['code' => 'A', 'values' => ['K1' => 5, 'K2' => 7]],
            ['code' => 'C', 'values' => ['K1' => 5, 'K2' => 7]],
        ];
        $result = $this->topsis->calculate($criteria, ['K1' => 0.6, 'K2' => 0.4], $alts);

        $this->assertTrue($result['flags']['non_discriminative_run']);
        foreach ($result['items'] as $item) {
            $this->assertSame(0.5, $item['closeness']);
        }
        // Seri penuh: urut berdasarkan kode menaik
        $this->assertSame(['A', 'B', 'C'], array_column($result['items'], 'code'));
        $this->assertSame([1, 2, 3], array_column($result['items'], 'rank'));
    }

    /** Tes minimum 7: tie-breaking deterministik. */
    public function test_tie_breaking_uses_key_criterion_then_code(): void
    {
        // Dua alternatif simetris: nilai C sama persis, tapi kriteria kunci (K1, bobot terbesar) berbeda.
        $criteria = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'benefit']];
        $alts = [
            ['code' => 'Z', 'values' => ['K1' => 1, 'K2' => 3]],
            ['code' => 'A', 'values' => ['K1' => 3, 'K2' => 1]],
        ];
        // Bobot sama: simetris -> C identik; kriteria kunci = K1 (kode terkecil). A unggul di K1.
        $equal = $this->topsis->calculate($criteria, ['K1' => 0.5, 'K2' => 0.5], $alts);
        $this->assertEqualsWithDelta($equal['items'][0]['closeness'], $equal['items'][1]['closeness'], 1e-12);
        $this->assertSame('K1', $equal['flags']['tie_breaker_criterion']);
        $this->assertSame(['A', 'Z'], array_column($equal['items'], 'code'));

        // Bila K2 diberi bobot lebih besar, kriteria kunci pindah ke K2 dan Z unggul di K2.
        // (C tidak lagi sama, tetapi flag kunci berubah.)
        $shift = $this->topsis->calculate($criteria, ['K1' => 0.2, 'K2' => 0.8], $alts);
        $this->assertSame('K2', $shift['flags']['tie_breaker_criterion']);
    }

    public function test_tie_breaking_cost_key_criterion_prefers_smaller_value(): void
    {
        $criteria = [['code' => 'K1', 'type' => 'cost'], ['code' => 'K2', 'type' => 'cost']];
        $alts = [
            ['code' => 'Z', 'values' => ['K1' => 1, 'K2' => 3]],
            ['code' => 'A', 'values' => ['K1' => 3, 'K2' => 1]],
        ];
        $result = $this->topsis->calculate($criteria, ['K1' => 0.5, 'K2' => 0.5], $alts);

        $this->assertEqualsWithDelta($result['items'][0]['closeness'], $result['items'][1]['closeness'], 1e-12);
        // Kunci K1 bertipe cost: nilai terbobot lebih kecil lebih dekat ideal -> Z (K1=1) unggul.
        $this->assertSame(['Z', 'A'], array_column($result['items'], 'code'));
    }

    /** Tes minimum 8: dua run dengan input identik menghasilkan hasil dan hash identik. */
    public function test_identical_input_gives_identical_result_and_hash(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $weights = $this->weightsOf($ref['ahp']['weights'], $criteria);

        $first = $this->topsis->calculate($criteria, $weights, $alternatives);
        $second = $this->topsis->calculate($criteria, $weights, $alternatives);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['input_hash']);
    }

    public function test_result_and_hash_do_not_depend_on_alternative_input_order(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $weights = $this->weightsOf($ref['ahp']['weights'], $criteria);

        $a = $this->topsis->calculate($criteria, $weights, $alternatives);
        $b = $this->topsis->calculate($criteria, $weights, array_reverse($alternatives));

        $this->assertSame($a, $b);
    }

    public function test_hash_changes_when_input_changes(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $weights = $this->weightsOf($ref['ahp']['weights'], $criteria);
        $base = $this->topsis->calculate($criteria, $weights, $alternatives)['input_hash'];

        $alternatives[0]['values']['K1'] += 1;
        $changedValue = $this->topsis->calculate($criteria, $weights, $alternatives)['input_hash'];
        $this->assertNotSame($base, $changedValue);

        [$criteria, $alternatives] = $this->lampiranC();
        $weights['K1'] -= 0.01;
        $weights['K2'] += 0.01;
        $changedWeight = $this->topsis->calculate($criteria, $weights, $alternatives)['input_hash'];
        $this->assertNotSame($base, $changedWeight);
    }

    /** Padanan unit untuk tes 9: perhitungan baru dengan bobot lain tidak memutasi hasil/ input lama. */
    public function test_new_weights_do_not_alter_previous_result(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $weights = $this->weightsOf($ref['ahp']['weights'], $criteria);

        $old = $this->topsis->calculate($criteria, $weights, $alternatives);
        $snapshot = $old;
        $this->topsis->calculate($criteria, ['K1' => 1 / 3, 'K2' => 1 / 3, 'K3' => 1 / 3], $alternatives);

        $this->assertSame($snapshot, $old);
        $this->assertSame($this->weightsOf($ref['ahp']['weights'], $criteria), $weights);
    }

    /** Tes minimum 10: invarian jumlah bobot = 1 dan 0 <= C <= 1 pada data acak (seed tetap). */
    public function test_invariants_on_seeded_random_data(): void
    {
        mt_srand(20260101);
        for ($run = 0; $run < 200; $run++) {
            $n = mt_rand(1, 8);
            $m = mt_rand(2, 12);
            $criteria = [];
            $raw = [];
            for ($j = 0; $j < $n; $j++) {
                $criteria[] = ['code' => 'K' . $j, 'type' => mt_rand(0, 1) ? 'benefit' : 'cost'];
                $raw[] = mt_rand(1, 1000) / 10.0;
            }
            $sum = array_sum($raw);
            $weights = [];
            foreach ($criteria as $j => $c) {
                $weights[$c['code']] = $raw[$j] / $sum;
            }
            $alts = [];
            for ($i = 0; $i < $m; $i++) {
                $values = [];
                foreach ($criteria as $c) {
                    $values[$c['code']] = mt_rand(0, 5000) / 10.0;
                }
                $alts[] = ['code' => sprintf('S-%03d', $i), 'values' => $values];
            }

            $result = $this->topsis->calculate($criteria, $weights, $alts);

            $this->assertEqualsWithDelta(1.0, array_sum($result['weights']), 1e-9);
            $ranks = [];
            foreach ($result['items'] as $item) {
                $this->assertGreaterThanOrEqual(0.0, $item['closeness']);
                $this->assertLessThanOrEqual(1.0, $item['closeness']);
                $this->assertTrue(is_finite($item['d_plus']) && is_finite($item['d_minus']));
                $ranks[] = $item['rank'];
            }
            $this->assertSame(range(1, $m), $ranks, 'peringkat harus 1..m tanpa lubang');
            // Urutan C tidak naik: item pada peringkat lebih kecil tidak boleh C-nya lebih rendah (di luar toleransi seri).
            for ($k = 1; $k < $m; $k++) {
                $this->assertGreaterThanOrEqual(
                    $result['items'][$k]['closeness'] - TopsisService::TIE_TOLERANCE,
                    $result['items'][$k - 1]['closeness']
                );
            }
        }
    }

    /** D-09 (usulan): alternatif dengan data wajib hilang dikeluarkan beserta alasannya. */
    public function test_alternatives_with_missing_or_invalid_data_are_excluded_with_reasons(): void
    {
        $criteria = [
            ['code' => 'K1', 'type' => 'benefit', 'min' => 1, 'max' => 5],
            ['code' => 'K2', 'type' => 'cost'],
        ];
        $alts = [
            ['code' => 'OK-1', 'values' => ['K1' => 2, 'K2' => 10]],
            ['code' => 'OK-2', 'values' => ['K1' => 4, 'K2' => 30]],
            ['code' => 'MISS', 'values' => ['K1' => 3]],
            ['code' => 'NULL', 'values' => ['K1' => null, 'K2' => 5]],
            ['code' => 'NAN', 'values' => ['K1' => NAN, 'K2' => 5]],
            ['code' => 'TEXT', 'values' => ['K1' => 'banyak', 'K2' => 5]],
            ['code' => 'RANGE', 'values' => ['K1' => 9, 'K2' => 5]],
            ['code' => 'BOTH', 'values' => ['K1' => 99]],
        ];
        $result = $this->topsis->calculate($criteria, ['K1' => 0.5, 'K2' => 0.5], $alts);

        $this->assertSame(['OK-1', 'OK-2'], array_column($result['items'], 'code'));
        $this->assertCount(2, $result['items']);

        $excluded = [];
        foreach ($result['excluded'] as $e) {
            $excluded[$e['code']] = $e['reasons'];
        }
        $this->assertSame(['missing_or_invalid:K2'], $excluded['MISS']);
        $this->assertSame(['missing_or_invalid:K1'], $excluded['NULL']);
        $this->assertSame(['missing_or_invalid:K1'], $excluded['NAN']);
        $this->assertSame(['missing_or_invalid:K1'], $excluded['TEXT']);
        $this->assertSame(['out_of_range:K1'], $excluded['RANGE']);
        $this->assertSame(['out_of_range:K1', 'missing_or_invalid:K2'], $excluded['BOTH']);
        $this->assertSame(['BOTH', 'MISS', 'NAN', 'NULL', 'RANGE', 'TEXT'], array_column($result['excluded'], 'code'));
    }

    public function test_too_few_eligible_after_exclusion_is_rejected(): void
    {
        $this->expectEngineError('insufficient_alternatives', function () {
            $this->topsis->calculate(
                [['code' => 'K1', 'type' => 'benefit']],
                ['K1' => 1],
                [
                    ['code' => 'A', 'values' => ['K1' => 1]],
                    ['code' => 'B', 'values' => []],
                ]
            );
        });
    }

    /**
     * @dataProvider invalidWeightsAndCriteria
     */
    public function test_invalid_configuration_is_rejected(array $criteria, array $weights, array $alts, string $code): void
    {
        $this->expectEngineError($code, function () use ($criteria, $weights, $alts) {
            $this->topsis->calculate($criteria, $weights, $alts);
        });
    }

    public function invalidWeightsAndCriteria(): array
    {
        $c = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'cost']];
        $alts = [
            ['code' => 'A', 'values' => ['K1' => 1, 'K2' => 2]],
            ['code' => 'B', 'values' => ['K1' => 2, 'K2' => 1]],
        ];
        $many = [];
        for ($i = 0; $i < 11; $i++) {
            $many[] = ['code' => 'K' . $i, 'type' => 'benefit'];
        }

        return [
            'jumlah bobot bukan 1' => [$c, ['K1' => 0.5, 'K2' => 0.6], $alts, 'weights_sum'],
            'bobot hilang' => [$c, ['K1' => 1.0], $alts, 'weights_missing'],
            'bobot kriteria asing' => [$c, ['K1' => 0.5, 'K2' => 0.5, 'K3' => 0.0], $alts, 'weights_unknown'],
            'bobot negatif' => [$c, ['K1' => 1.5, 'K2' => -0.5], $alts, 'weights_invalid'],
            'bobot bukan angka' => [$c, ['K1' => 'a', 'K2' => 0.5], $alts, 'weights_invalid'],
            'tipe salah' => [[['code' => 'K1', 'type' => 'maximize']], ['K1' => 1], $alts, 'criteria_invalid_type'],
            'kode kriteria ganda' => [[['code' => 'K1', 'type' => 'benefit'], ['code' => 'K1', 'type' => 'cost']], ['K1' => 1], $alts, 'criteria_duplicate'],
            'kode kriteria kosong' => [[['code' => '', 'type' => 'benefit']], ['' => 1], $alts, 'criteria_invalid_code'],
            'rentang terbalik' => [[['code' => 'K1', 'type' => 'benefit', 'min' => 5, 'max' => 1]], ['K1' => 1], $alts, 'criteria_invalid_range'],
            'lebih dari 10 kriteria' => [$many, [], $alts, 'criteria_too_many'],
            'kode alternatif ganda' => [$c, ['K1' => 0.5, 'K2' => 0.5], [$alts[0], $alts[0]], 'alternative_duplicate'],
            'kode alternatif kosong' => [$c, ['K1' => 0.5, 'K2' => 0.5], [['code' => '', 'values' => []], $alts[1]], 'alternative_invalid_code'],
        ];
    }

    public function test_zero_weight_criterion_is_allowed_and_has_no_influence(): void
    {
        $criteria = [['code' => 'K1', 'type' => 'benefit'], ['code' => 'K2', 'type' => 'benefit']];
        $alts = [
            ['code' => 'A', 'values' => ['K1' => 1, 'K2' => 100]],
            ['code' => 'B', 'values' => ['K1' => 2, 'K2' => 1]],
            ['code' => 'C', 'values' => ['K1' => 3, 'K2' => 50]],
        ];
        $result = $this->topsis->calculate($criteria, ['K1' => 1.0, 'K2' => 0.0], $alts);

        $this->assertSame(['C', 'B', 'A'], array_column($result['items'], 'code'));
    }

    public function test_algorithm_version_is_recorded(): void
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $result = $this->topsis->calculate($criteria, $this->weightsOf($ref['ahp']['weights'], $criteria), $alternatives);

        $this->assertSame(TopsisService::ALGORITHM_VERSION, $result['algorithm_version']);
    }

    private function expectEngineError(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Seharusnya melempar DecisionEngineException ({$code})");
        } catch (DecisionEngineException $e) {
            $this->assertSame($code, $e->errorCode());
        }
    }
}

