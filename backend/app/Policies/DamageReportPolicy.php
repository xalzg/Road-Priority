<?php

namespace App\Policies;

use App\Models\DamageReport;
use App\Models\ReportPhoto;
use App\Models\User;

class DamageReportPolicy
{
    public function viewAny(?User $actor): bool
    {
        return true;
    }

    /**
     * Pemeriksaan kepemilikan & visibilitas laporan (mencegah IDOR - PRD 04 §3 & §11).
     * - Pengunjung publik (tanpa login) atau pengguna lain hanya boleh melihat laporan
     *   berstatus 'verified' (atau 'resolved') yang bukan data demo.
     * - Petugas dan Admin aktif dapat melihat seluruh laporan.
     * - Pelapor aktif dapat melihat seluruh laporan miliknya sendiri.
     */
    public function view(?User $actor, DamageReport $report): bool
    {
        if ($actor !== null && ! $actor->is_active) {
            return false;
        }

        if ($actor !== null && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN)) {
            return true;
        }

        if (
            in_array($report->status, [DamageReport::STATUS_VERIFIED, DamageReport::STATUS_RESOLVED], true)
            && ! $report->is_demo
        ) {
            return true;
        }

        if ($actor === null) {
            return false;
        }

        return $report->reporter_id !== null && (int) $report->reporter_id === (int) $actor->id;
    }

    /**
     * Pemeriksaan akses foto bukti laporan (PRD 04 §5 & §11).
     * Foto hanya boleh diakses publik jika laporan berstatus verified/resolved, bukan demo, dan photo.is_public = true.
     * Selain itu hanya pemilik laporan, Petugas, dan Admin aktif yang diizinkan.
     */
    public function viewPhoto(?User $actor, DamageReport $report, ReportPhoto $photo): bool
    {
        if ((int) $photo->damage_report_id !== (int) $report->id) {
            return false;
        }

        if ($actor !== null && ! $actor->is_active) {
            return false;
        }

        if ($actor !== null && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN)) {
            return true;
        }

        if ($actor !== null && $report->reporter_id !== null && (int) $report->reporter_id === (int) $actor->id) {
            return true;
        }

        return in_array($report->status, [DamageReport::STATUS_VERIFIED, DamageReport::STATUS_RESOLVED], true)
            && ! $report->is_demo
            && (bool) $photo->is_public;
    }

    public function create(User $actor): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_REPORTER, User::ROLE_OFFICER, User::ROLE_ADMIN);
    }

    /**
     * Hanya Petugas dan Admin yang berhak mengubah status / memverifikasi laporan.
     * Pelapor tidak pernah boleh memverifikasi laporannya sendiri.
     */
    public function updateStatus(User $actor, DamageReport $report): bool
    {
        return $actor->is_active && $actor->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
    }
}
