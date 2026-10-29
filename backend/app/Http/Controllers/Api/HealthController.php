<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Pemeriksaan ketersediaan API. Sengaja tidak mengembalikan
     * konfigurasi, versi, atau informasi internal server.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'RoadPriority API is running',
        ]);
    }
}

