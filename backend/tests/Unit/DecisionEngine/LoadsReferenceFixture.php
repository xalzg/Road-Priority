<?php

namespace Tests\Unit\DecisionEngine;

/**
 * Memuat fixture hasil hitungan independen (Python, tanpa pembulatan).
 * Sumber: tests/Reference/ahp_topsis_reference.py
 */
trait LoadsReferenceFixture
{
    private static ?array $referenceCache = null;

    protected function reference(): array
    {
        if (self::$referenceCache === null) {
            $path = __DIR__ . '/../../Fixtures/reference.json';
            $json = file_get_contents($path);
            $data = json_decode($json, true);
            if (! is_array($data)) {
                throw new \RuntimeException('Fixture reference.json tidak valid.');
            }
            self::$referenceCache = $data;
        }

        return self::$referenceCache;
    }

    /** Dataset Lampiran C sebagai input mesin. */
    protected function lampiranC(): array
    {
        $ref = $this->reference()['lampiran_c'];
        $criteria = [];
        foreach ($ref['types'] as $j => $type) {
            $criteria[] = ['code' => 'K' . ($j + 1), 'type' => $type];
        }
        $alternatives = [];
        foreach ($ref['alternatives'] as $code => $values) {
            $alternatives[] = [
                'code' => $code,
                'values' => ['K1' => $values[0], 'K2' => $values[1], 'K3' => $values[2]],
            ];
        }

        return [$criteria, $alternatives, $ref];
    }
}

