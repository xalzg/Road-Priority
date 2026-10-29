<?php

namespace App\Services\DecisionEngine;

class RankCorrelation
{
    /**
     * Spearman rho = korelasi Pearson antara peringkat (benar juga bila ada seri
     * karena peringkat seri dirata-ratakan oleh pemanggil).
     * Mengembalikan null bila n < 2 atau salah satu peringkat tanpa variasi.
     *
     * @param  array<string, int|float>  $ranksA  kode => peringkat
     * @param  array<string, int|float>  $ranksB
     */
    public function spearman(array $ranksA, array $ranksB): ?float
    {
        $keys = array_keys($ranksA);
        sort($keys, SORT_STRING);
        $keysB = array_keys($ranksB);
        sort($keysB, SORT_STRING);
        if ($keys !== $keysB) {
            throw new DecisionEngineException('rank_mismatch', 'Himpunan alternatif kedua peringkat harus sama.');
        }
        $n = count($keys);
        if ($n < 2) {
            return null;
        }

        $meanA = array_sum($ranksA) / $n;
        $meanB = array_sum($ranksB) / $n;
        $cov = 0.0;
        $varA = 0.0;
        $varB = 0.0;
        foreach ($keys as $k) {
            $da = $ranksA[$k] - $meanA;
            $db = $ranksB[$k] - $meanB;
            $cov += $da * $db;
            $varA += $da * $da;
            $varB += $db * $db;
        }
        if ($varA == 0.0 || $varB == 0.0) {
            return null;
        }

        return $cov / sqrt($varA * $varB);
    }
}

