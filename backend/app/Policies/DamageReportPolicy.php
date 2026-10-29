<?php

namespace App\Policies;

use App\Models\DamageReport;
use App\Models\User;

class DamageReportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
    }

    /**
     * Pemeriksaan kepemilikan & visibilitas laporan (mencegah IDOR).
     * - Petugas dan Admin dapat melihat semua laporan.
     * - Pelapor hanya dapat melihat laporannya sendiri (atau laporan yang sudah berstatus verified).
     */
    public function view(User $actor, DamageReport $report): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN)) {
            return true;
        }

        if ($report->status === DamageReport::STATUS_VERIFIED && ! $report->is_demo) {
            return true;
        }

        return $report->reporter_id !== null && (int) $report->reporter_id === (int) $actor->id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(User::ROLE_REPORTER, User::ROLE_OFFICER, User::ROLE_ADMIN);
    }

    /**
     * Hanya Petugas dan Admin yang berhak mengubah status / memverifikasi laporan.
     * Pelapor tidak pernah boleh memverifikasi laporannya sendiri.
     */
    public function updateStatus(User $actor, DamageReport $report): bool
    {
        return $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
    }
}
