<?php

namespace App\Services\DecisionEngine;

/**
 * Hash SHA-256 dari input kanonik sebuah decision run (NFR-12).
 *
 * Kanonik: kunci asosiatif diurutkan, semua angka ditulis dengan 12 desimal
 * (menghindari perbedaan representasi float), urutan list dipertahankan.
 */
class InputHasher
{
    /**
     * @param  mixed  $input
     */
    public function hash($input): string
    {
        return hash('sha256', json_encode($this->canonicalize($input), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function canonicalize($value)
    {
        if (is_array($value)) {
            if ($this->isList($value)) {
                return array_map(fn ($v) => $this->canonicalize($v), $value);
            }
            ksort($value, SORT_STRING);
            $out = [];
            foreach ($value as $k => $v) {
                $out[(string) $k] = $this->canonicalize($v);
            }

            return $out;
        }
        if (is_int($value) || is_float($value)) {
            return sprintf('%.12F', $value);
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $arr
     */
    private function isList(array $arr): bool
    {
        return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
    }
}

