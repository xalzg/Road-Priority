<?php

namespace App\Services\DecisionEngine;

/**
 * Perhitungan AHP: bobot prioritas, konsistensi, dan agregasi kelompok.
 *
 * Murni (tanpa database/framework) agar mudah diuji. Rumus: docs/FORMULAS.md.
 * Status keputusan: agregasi geometric mean (AIJ) adalah USULAN D-07 dan
 * belum disetujui; kriteria final (D-06) tidak memengaruhi kelas ini.
 */
class AhpService
{
    public const MAX_CRITERIA = 10;
    public const CR_THRESHOLD = 0.10;
    public const POWER_TOLERANCE = 1e-12;
    public const MAX_ITERATIONS = 10000;
    public const MATRIX_TOLERANCE = 1e-9;

    /** Random Index untuk n = 1..10. [PERLU VERIFIKASI terhadap sumber yang dikutip] */
    public const RI = [
        1 => 0.0, 2 => 0.0, 3 => 0.58, 4 => 0.90, 5 => 1.12,
        6 => 1.24, 7 => 1.32, 8 => 1.41, 9 => 1.45, 10 => 1.49,
    ];

    /**
     * Hitung bobot dan indikator konsistensi dari satu matriks perbandingan.
     *
     * @param  array<int, array<int, float|int>>  $matrix
     * @return array{n:int, weights:float[], lambda_max:float, ci:float, ri:float, cr:float, is_consistent:bool, iterations:int}
     */
    public function computeWeights(array $matrix): array
    {
        $a = $this->validateMatrix($matrix);
        $n = count($a);

        if ($n === 1) {
            return [
                'n' => 1, 'weights' => [1.0], 'lambda_max' => 1.0, 'ci' => 0.0,
                'ri' => 0.0, 'cr' => 0.0, 'is_consistent' => true, 'iterations' => 0,
            ];
        }

        // Power iteration, dinormalisasi hingga jumlah = 1.
        $w = array_fill(0, $n, 1.0 / $n);
        $iterations = 0;
        $converged = false;
        while ($iterations < self::MAX_ITERATIONS) {
            $iterations++;
            $y = $this->multiply($a, $w);
            $sum = array_sum($y);
            $next = array_map(static fn ($v) => $v / $sum, $y);
            $diff = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $diff = max($diff, abs($next[$i] - $w[$i]));
            }
            $w = $next;
            if ($diff < self::POWER_TOLERANCE) {
                $converged = true;
                break;
            }
        }
        if (! $converged) {
            throw new DecisionEngineException(
                'ahp_not_converged',
                'Power iteration tidak konvergen dalam ' . self::MAX_ITERATIONS . ' iterasi.'
            );
        }

        $aw = $this->multiply($a, $w);
        $lambda = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $lambda += $aw[$i] / $w[$i];
        }
        $lambda /= $n;

        $ci = ($lambda - $n) / ($n - 1);
        if ($ci < 0 && $ci > -1e-12) {
            $ci = 0.0; // pembulatan floating point; secara teori CI >= 0
        }
        $ri = self::RI[$n];
        $cr = $n <= 2 ? 0.0 : $ci / $ri;

        return [
            'n' => $n,
            'weights' => $w,
            'lambda_max' => $lambda,
            'ci' => $ci,
            'ri' => $ri,
            'cr' => $cr,
            'is_consistent' => $cr <= self::CR_THRESHOLD,
            'iterations' => $iterations,
        ];
    }

    /**
     * Agregasi penilaian kelompok: geometric mean per pasangan (AIJ).
     * Matriks hasil resiprokal dengan diagonal 1.
     *
     * @param  array<int, array<int, array<int, float|int>>>  $matrices
     * @return array<int, array<int, float>>
     */
    public function aggregateGeometricMean(array $matrices): array
    {
        if ($matrices === []) {
            throw new DecisionEngineException('ahp_no_matrices', 'Minimal satu matriks diperlukan untuk agregasi.');
        }

        $validated = [];
        foreach (array_values($matrices) as $m) {
            $validated[] = $this->validateMatrix($m);
        }
        $n = count($validated[0]);
        foreach ($validated as $m) {
            if (count($m) !== $n) {
                throw new DecisionEngineException('ahp_size_mismatch', 'Semua matriks harus berukuran sama.');
            }
        }

        $k = count($validated);
        $result = array_fill(0, $n, array_fill(0, $n, 1.0));
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $logSum = 0.0;
                foreach ($validated as $m) {
                    $logSum += log($m[$i][$j]);
                }
                $gm = exp($logSum / $k);
                $result[$i][$j] = $gm;
                $result[$j][$i] = 1.0 / $gm;
            }
        }

        return $result;
    }

    /**
     * Bentuk matriks resiprokal dari daftar penilaian pakar.
     * Setiap pasangan (i, j) dengan i != j harus muncul tepat satu kali, dalam
     * arah mana pun. Nilai harus pada skala Saaty: 1..9 atau kebalikannya.
     *
     * @param  array<int, array{0:int, 1:int, 2:float|int}>  $judgments  [i, j, nilai a_ij]
     * @return array<int, array<int, float>>
     */
    public function matrixFromJudgments(int $n, array $judgments): array
    {
        if ($n < 1 || $n > self::MAX_CRITERIA) {
            throw new DecisionEngineException('ahp_invalid_size', 'Jumlah kriteria harus 1 sampai ' . self::MAX_CRITERIA . '.');
        }

        $matrix = array_fill(0, $n, array_fill(0, $n, 1.0));
        $seen = [];
        foreach ($judgments as $judgment) {
            [$i, $j, $value] = array_values($judgment) + [null, null, null];
            if (! is_int($i) || ! is_int($j) || $i < 0 || $j < 0 || $i >= $n || $j >= $n || $i === $j) {
                throw new DecisionEngineException('ahp_invalid_pair', 'Indeks pasangan kriteria tidak valid.');
            }
            $this->assertSaatyValue($value);
            $key = min($i, $j) . ':' . max($i, $j);
            if (isset($seen[$key])) {
                throw new DecisionEngineException('ahp_duplicate_pair', "Pasangan {$key} muncul lebih dari satu kali.");
            }
            $seen[$key] = true;
            $matrix[$i][$j] = (float) $value;
            $matrix[$j][$i] = 1.0 / (float) $value;
        }

        $expected = $n * ($n - 1) / 2;
        if (count($seen) !== $expected) {
            throw new DecisionEngineException(
                'ahp_incomplete_judgments',
                'Perbandingan tidak lengkap: diperlukan ' . $expected . ' pasangan, diterima ' . count($seen) . '.'
            );
        }

        return $matrix;
    }

    /**
     * Skala Saaty: bilangan bulat 1..9 atau kebalikannya.
     *
     * @param  mixed  $value
     */
    public function assertSaatyValue($value): void
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new DecisionEngineException('ahp_invalid_scale', 'Nilai penilaian harus berupa angka.');
        }
        $v = (float) $value;
        if (! is_finite($v) || $v <= 0) {
            throw new DecisionEngineException('ahp_invalid_scale', 'Nilai penilaian harus positif dan terbatas.');
        }
        $magnitude = $v >= 1 ? $v : 1.0 / $v;
        $rounded = round($magnitude);
        if ($rounded < 1 || $rounded > 9 || abs($magnitude - $rounded) > 1e-9) {
            throw new DecisionEngineException(
                'ahp_invalid_scale',
                'Nilai penilaian harus pada skala Saaty 1-9 atau kebalikannya.'
            );
        }
    }

    /**
     * @param  array<int, array<int, float|int>>  $matrix
     * @return array<int, array<int, float>>
     */
    private function validateMatrix(array $matrix): array
    {
        $rows = array_values($matrix);
        $n = count($rows);
        if ($n < 1 || $n > self::MAX_CRITERIA) {
            throw new DecisionEngineException('ahp_invalid_size', 'Ukuran matriks harus 1 sampai ' . self::MAX_CRITERIA . '.');
        }

        $a = [];
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                throw new DecisionEngineException('ahp_not_square', 'Matriks harus berbentuk persegi.');
            }
            $row = array_values($row);
            if (count($row) !== $n) {
                throw new DecisionEngineException('ahp_not_square', 'Matriks harus berbentuk persegi.');
            }
            foreach ($row as $j => $value) {
                if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value <= 0) {
                    throw new DecisionEngineException('ahp_invalid_value', 'Semua elemen matriks harus angka positif yang terbatas.');
                }
                $a[$i][$j] = (float) $value;
            }
        }

        for ($i = 0; $i < $n; $i++) {
            if (abs($a[$i][$i] - 1.0) > self::MATRIX_TOLERANCE) {
                throw new DecisionEngineException('ahp_invalid_diagonal', 'Diagonal matriks harus bernilai 1.');
            }
            for ($j = $i + 1; $j < $n; $j++) {
                if (abs($a[$i][$j] * $a[$j][$i] - 1.0) > self::MATRIX_TOLERANCE) {
                    throw new DecisionEngineException('ahp_not_reciprocal', 'Matriks harus resiprokal (a_ij * a_ji = 1).');
                }
            }
        }

        return $a;
    }

    /**
     * @param  array<int, array<int, float>>  $a
     * @param  float[]  $w
     * @return float[]
     */
    private function multiply(array $a, array $w): array
    {
        $n = count($w);
        $y = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $s = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $s += $a[$i][$j] * $w[$j];
            }
            $y[$i] = $s;
        }

        return $y;
    }
}

