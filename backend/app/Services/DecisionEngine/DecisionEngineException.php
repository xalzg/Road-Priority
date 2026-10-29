<?php

namespace App\Services\DecisionEngine;

use RuntimeException;

/**
 * Kesalahan input atau kondisi tak terdefinisi pada mesin AHP-TOPSIS.
 * `$errorCode` adalah kode mesin yang stabil agar dapat diuji dan ditampilkan.
 */
class DecisionEngineException extends RuntimeException
{
    private string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

