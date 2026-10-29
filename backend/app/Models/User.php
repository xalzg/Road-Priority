<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_REPORTER = 'reporter';
    public const ROLE_OFFICER = 'officer';
    public const ROLE_EXPERT = 'expert';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_REPORTER,
        self::ROLE_OFFICER,
        self::ROLE_EXPERT,
        self::ROLE_ADMIN,
    ];

    /**
     * The attributes that are mass assignable.
     * `role` dan `is_active` sengaja tidak dimasukkan ke `$fillable` untuk
     * mencegah eskalasi hak akses melalui mass assignment (NFR-02).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function hasRole(string ...$roles): bool
    {
        return $this->is_active && in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isOfficer(): bool
    {
        return $this->hasRole(self::ROLE_OFFICER);
    }

    public function isExpert(): bool
    {
        return $this->hasRole(self::ROLE_EXPERT);
    }

    public function isReporter(): bool
    {
        return $this->hasRole(self::ROLE_REPORTER);
    }

    public function reportedDamageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'reporter_id');
    }

    public function verifiedDamageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'verified_by');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ReportStatusHistory::class, 'changed_by');
    }

    public function damageAssessments(): HasMany
    {
        return $this->hasMany(DamageAssessment::class, 'assessed_by');
    }

    public function createdWeightVersions(): HasMany
    {
        return $this->hasMany(CriterionWeightVersion::class, 'created_by');
    }

    public function approvedWeightVersions(): HasMany
    {
        return $this->hasMany(CriterionWeightVersion::class, 'approved_by');
    }

    public function ahpComparisons(): HasMany
    {
        return $this->hasMany(AhpPairwiseComparison::class, 'expert_id');
    }

    public function executedDecisionRuns(): HasMany
    {
        return $this->hasMany(DecisionRun::class, 'executed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
