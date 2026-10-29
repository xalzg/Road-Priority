<?php

namespace App\Services\DecisionEngine;

/**
 * Perhitungan TOPSIS dengan normalisasi vektor.
 *
 * Murni (tanpa database/framework). Rumus dan kasus tepi: docs/FORMULAS.md.
 * Kebijakan data hilang mengikuti USULAN D-09 (alternatif dikeluarkan dan
 * dicatat); belum disetujui secara eksplisit.
 */
class TopsisService
{
    public const ALGORITHM_VERSION = 'ahp-topsis-1.0.0';
    public const MIN_ALTERNATIVES = 2;
    public const WEIGHT_SUM_TOLERANCE = 1e-9;
    public const TIE_TOLERANCE = 1e-9;
    public const EQUAL_VALUE_TOLERANCE = 1e-12;
    public const DEGENERATE_EPSILON = 1e-15;

    private InputHasher $hasher;

    public function __construct(?InputHasher $hasher = null)
    {
        $this->hasher = $hasher ?? new InputHasher();
    }

    /**
     * @param  array<int, array{code:string, type:string, min?:float|int|null, max?:float|int|null}>  $criteria
     * @param  array<string, float|int>  $weights  kode kriteria => bobot (jumlah 1)
     * @param  array<int, array{code:string, values:array<string, mixed>}>  $alternatives
     * @return array<string, mixed>
     */
    public function calculate(array $criteria, array $weights, array $alternatives): array
    {
        $criteria = $this->validateCriteria($criteria);
        $codes = array_column($criteria, 'code');
        $weights = $this->validateWeights($codes, $weights);
        $alternatives = $this->normalizeAlternatives($alternatives);

        $inputHash = $this->hasher->hash([
            'algorithm_version' => self::ALGORITHM_VERSION,
            'criteria' => $criteria,
            'weights' => $weights,
            'alternatives' => $alternatives,
        ]);

        [$eligible, $excluded] = $this->partition($criteria, $alternatives);

        if (count($eligible) < self::MIN_ALTERNATIVES) {
            throw new DecisionEngineException(
                'insufficient_alternatives',
                'Minimal ' . self::MIN_ALTERNATIVES . ' alternatif yang memenuhi syarat diperlukan; tersedia ' . count($eligible) . '.'
            );
        }

        $m = count($eligible);
        $n = count($criteria);

        // Matriks keputusan X
        $x = [];
        foreach ($eligible as $i => $alt) {
            foreach ($criteria as $j => $c) {
                $x[$i][$j] = (float) $alt['values'][$c['code']];
            }
        }

        // Normalisasi vektor
        $denominators = [];
        $zeroColumns = [];
        $constantColumns = [];
        for ($j = 0; $j < $n; $j++) {
            $sumSq = 0.0;
            $min = INF;
            $max = -INF;
            for ($i = 0; $i < $m; $i++) {
                $sumSq += $x[$i][$j] ** 2;
                $min = min($min, $x[$i][$j]);
                $max = max($max, $x[$i][$j]);
            }
            $denominators[$j] = sqrt($sumSq);
            if ($denominators[$j] == 0.0) {
                $zeroColumns[] = $criteria[$j]['code'];
            }
            if ($max - $min <= self::EQUAL_VALUE_TOLERANCE) {
                $constantColumns[] = $criteria[$j]['code'];
            }
        }

        $r = [];
        $v = [];
        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $r[$i][$j] = $denominators[$j] == 0.0 ? 0.0 : $x[$i][$j] / $denominators[$j];
                $v[$i][$j] = $weights[$criteria[$j]['code']] * $r[$i][$j];
            }
        }

        // Solusi ideal
        $aPlus = [];
        $aMinus = [];
        for ($j = 0; $j < $n; $j++) {
            $col = array_column($v, $j);
            $hi = max($col);
            $lo = min($col);
            if ($criteria[$j]['type'] === 'benefit') {
                $aPlus[$j] = $hi;
                $aMinus[$j] = $lo;
            } else {
                $aPlus[$j] = $lo;
                $aMinus[$j] = $hi;
            }
        }

        // Jarak dan nilai preferensi
        $dPlus = [];
        $dMinus = [];
        $closeness = [];
        $degenerate = true;
        for ($i = 0; $i < $m; $i++) {
            $sp = 0.0;
            $sm = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $sp += ($v[$i][$j] - $aPlus[$j]) ** 2;
                $sm += ($v[$i][$j] - $aMinus[$j]) ** 2;
            }
            $dPlus[$i] = sqrt($sp);
            $dMinus[$i] = sqrt($sm);
            if ($dPlus[$i] + $dMinus[$i] > self::DEGENERATE_EPSILON) {
                $degenerate = false;
            }
        }
        for ($i = 0; $i < $m; $i++) {
            $closeness[$i] = $degenerate ? 0.5 : $dMinus[$i] / ($dPlus[$i] + $dMinus[$i]);
        }

        // Peringkat dengan tie-breaker deterministik
        $keyIndex = $this->keyCriterionIndex($criteria, $weights);
        $order = $this->rank($eligible, $closeness, $v, $criteria, $keyIndex);

        $items = [];
        foreach ($order as $position => $i) {
            $raw = [];
            $norm = [];
            $weighted = [];
            foreach ($criteria as $j => $c) {
                $raw[$c['code']] = $x[$i][$j];
                $norm[$c['code']] = $r[$i][$j];
                $weighted[$c['code']] = $v[$i][$j];
            }
            $items[] = [
                'code' => $eligible[$i]['code'],
                'rank' => $position + 1,
                'raw' => $raw,
                'normalized' => $norm,
                'weighted' => $weighted,
                'd_plus' => $dPlus[$i],
                'd_minus' => $dMinus[$i],
                'closeness' => $closeness[$i],
            ];
        }

        $ideal = static function (array $values) use ($criteria): array {
            $out = [];
            foreach ($criteria as $j => $c) {
                $out[$c['code']] = $values[$j];
            }

            return $out;
        };

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'input_hash' => $inputHash,
            'criteria' => $criteria,
            'weights' => $weights,
            'ideal_positive' => $ideal($aPlus),
            'ideal_negative' => $ideal($aMinus),
            'items' => $items,
            'excluded' => $excluded,
            'flags' => [
                'non_discriminative_run' => $degenerate,
                'zero_norm_criteria' => $zeroColumns,
                'constant_criteria' => $constantColumns,
                'tie_breaker_criterion' => $criteria[$keyIndex]['code'],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<int, array{code:string, type:string, min:?float, max:?float}>
     */
    private function validateCriteria(array $criteria): array
    {
        $criteria = array_values($criteria);
        if ($criteria === []) {
            throw new DecisionEngineException('criteria_empty', 'Minimal satu kriteria diperlukan.');
        }
        if (count($criteria) > AhpService::MAX_CRITERIA) {
            throw new DecisionEngineException('criteria_too_many', 'Maksimal ' . AhpService::MAX_CRITERIA . ' kriteria.');
        }

        $out = [];
        $seen = [];
        foreach ($criteria as $c) {
            $code = $c['code'] ?? null;
            if (! is_string($code) || $code === '') {
                throw new DecisionEngineException('criteria_invalid_code', 'Setiap kriteria harus memiliki kode.');
            }
            if (isset($seen[$code])) {
                throw new DecisionEngineException('criteria_duplicate', "Kode kriteria ganda: {$code}.");
            }
            $seen[$code] = true;

            $type = $c['type'] ?? null;
            if ($type !== 'benefit' && $type !== 'cost') {
                throw new DecisionEngineException('criteria_invalid_type', "Tipe kriteria {$code} harus benefit atau cost.");
            }

            $min = $this->optionalNumber($c['min'] ?? null, $code);
            $max = $this->optionalNumber($c['max'] ?? null, $code);
            if ($min !== null && $max !== null && $min > $max) {
                throw new DecisionEngineException('criteria_invalid_range', "Rentang kriteria {$code}: min lebih besar dari max.");
            }

            $out[] = ['code' => $code, 'type' => $type, 'min' => $min, 'max' => $max];
        }

        return $out;
    }

    /**
     * @param  mixed  $value
     */
    private function optionalNumber($value, string $code): ?float
    {
        if ($value === null) {
            return null;
        }
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw new DecisionEngineException('criteria_invalid_range', "Batas rentang kriteria {$code} harus angka terbatas.");
        }

        return (float) $value;
    }

    /**
     * @param  string[]  $codes
     * @param  array<string, float|int>  $weights
     * @return array<string, float>
     */
    private function validateWeights(array $codes, array $weights): array
    {
        $out = [];
        $sum = 0.0;
        foreach ($codes as $code) {
            if (! array_key_exists($code, $weights)) {
                throw new DecisionEngineException('weights_missing', "Bobot untuk kriteria {$code} tidak ada.");
            }
            $w = $weights[$code];
            if ((! is_int($w) && ! is_float($w)) || ! is_finite((float) $w) || $w < 0) {
                throw new DecisionEngineException('weights_invalid', "Bobot kriteria {$code} harus angka terbatas dan tidak negatif.");
            }
            $out[$code] = (float) $w;
            $sum += (float) $w;
        }
        if (count($weights) !== count($codes)) {
            throw new DecisionEngineException('weights_unknown', 'Terdapat bobot untuk kriteria yang tidak dikenal.');
        }
        if (abs($sum - 1.0) > self::WEIGHT_SUM_TOLERANCE) {
            throw new DecisionEngineException('weights_sum', 'Jumlah bobot harus 1 (toleransi ' . self::WEIGHT_SUM_TOLERANCE . '); diterima ' . $sum . '.');
        }

        return $out;
    }

    /**
     * Urutkan berdasarkan kode agar hasil dan hash tidak bergantung urutan input.
     *
     * @param  array<int, array<string, mixed>>  $alternatives
     * @return array<int, array{code:string, values:array<string, mixed>}>
     */
    private function normalizeAlternatives(array $alternatives): array
    {
        $out = [];
        $seen = [];
        foreach (array_values($alternatives) as $alt) {
            $code = $alt['code'] ?? null;
            if (! is_string($code) || $code === '') {
                throw new DecisionEngineException('alternative_invalid_code', 'Setiap alternatif harus memiliki kode.');
            }
            if (isset($seen[$code])) {
                throw new DecisionEngineException('alternative_duplicate', "Kode alternatif ganda: {$code}.");
            }
            $seen[$code] = true;
            $out[] = ['code' => $code, 'values' => is_array($alt['values'] ?? null) ? $alt['values'] : []];
        }
        usort($out, static fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $out;
    }

    /**
     * Pisahkan alternatif yang layak dari yang dikeluarkan beserta alasannya (D-09).
     *
     * @param  array<int, array{code:string, type:string, min:?float, max:?float}>  $criteria
     * @param  array<int, array{code:string, values:array<string, mixed>}>  $alternatives
     * @return array{0: array<int, array{code:string, values:array<string, mixed>}>, 1: array<int, array{code:string, reasons:string[]}>}
     */
    private function partition(array $criteria, array $alternatives): array
    {
        $eligible = [];
        $excluded = [];
        foreach ($alternatives as $alt) {
            $reasons = [];
            foreach ($criteria as $c) {
                $value = $alt['values'][$c['code']] ?? null;
                if ($value === null || (! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                    $reasons[] = 'missing_or_invalid:' . $c['code'];
                    continue;
                }
                if (($c['min'] !== null && $value < $c['min']) || ($c['max'] !== null && $value > $c['max'])) {
                    $reasons[] = 'out_of_range:' . $c['code'];
                }
            }
            if ($reasons === []) {
                $eligible[] = $alt;
            } else {
                $excluded[] = ['code' => $alt['code'], 'reasons' => $reasons];
            }
        }

        return [$eligible, $excluded];
    }

    /**
     * Kriteria berbobot terbesar; bila seri, kode terkecil.
     *
     * @param  array<int, array{code:string, type:string}>  $criteria
     * @param  array<string, float>  $weights
     */
    private function keyCriterionIndex(array $criteria, array $weights): int
    {
        $best = 0;
        foreach ($criteria as $j => $c) {
            $diff = $weights[$c['code']] - $weights[$criteria[$best]['code']];
            if ($diff > self::EQUAL_VALUE_TOLERANCE
                || (abs($diff) <= self::EQUAL_VALUE_TOLERANCE && strcmp($c['code'], $criteria[$best]['code']) < 0)) {
                $best = $j;
            }
        }

        return $best;
    }

    /**
     * Urutan indeks alternatif dari peringkat 1 ke terakhir.
     * C menurun; nilai C berurutan dengan selisih <= TIE_TOLERANCE dianggap seri,
     * lalu (a) nilai terbobot kriteria kunci lebih dekat ke ideal, (b) kode menaik.
     *
     * @param  array<int, array{code:string}>  $eligible
     * @param  float[]  $closeness
     * @param  array<int, array<int, float>>  $v
     * @param  array<int, array{type:string}>  $criteria
     * @return int[]
     */
    private function rank(array $eligible, array $closeness, array $v, array $criteria, int $keyIndex): array
    {
        $indices = array_keys($eligible);
        // Urutan awal: C menurun, kode menaik (stabil dan deterministik).
        usort($indices, static function ($a, $b) use ($closeness, $eligible) {
            if ($closeness[$a] !== $closeness[$b]) {
                return $closeness[$a] < $closeness[$b] ? 1 : -1;
            }

            return strcmp($eligible[$a]['code'], $eligible[$b]['code']);
        });

        // Kelompokkan nilai C yang berdekatan (rantai), urutkan tiap kelompok dengan tie-breaker.
        $ordered = [];
        $group = [];
        $flush = function () use (&$group, &$ordered, $eligible, $v, $criteria, $keyIndex) {
            if (count($group) > 1) {
                $isBenefit = $criteria[$keyIndex]['type'] === 'benefit';
                usort($group, function ($a, $b) use ($eligible, $v, $keyIndex, $isBenefit) {
                    $diff = $v[$a][$keyIndex] - $v[$b][$keyIndex];
                    if (abs($diff) > self::EQUAL_VALUE_TOLERANCE) {
                        $aBetter = $isBenefit ? $diff > 0 : $diff < 0;

                        return $aBetter ? -1 : 1;
                    }

                    return strcmp($eligible[$a]['code'], $eligible[$b]['code']);
                });
            }
            foreach ($group as $i) {
                $ordered[] = $i;
            }
            $group = [];
        };

        foreach ($indices as $i) {
            if ($group !== [] && abs($closeness[end($group)] - $closeness[$i]) > self::TIE_TOLERANCE) {
                $flush();
            }
            $group[] = $i;
        }
        $flush();

        return $ordered;
    }
}

