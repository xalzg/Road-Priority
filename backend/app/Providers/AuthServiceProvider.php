<?php

namespace App\Providers;

use App\Models\CriterionWeightVersion;
use App\Models\DamageAssessment;
use App\Models\DamageReport;
use App\Models\DecisionCriterion;
use App\Models\DecisionRun;
use App\Models\User;
use App\Policies\CriterionWeightVersionPolicy;
use App\Policies\DamageAssessmentPolicy;
use App\Policies\DamageReportPolicy;
use App\Policies\DecisionCriterionPolicy;
use App\Policies\DecisionRunPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        DamageReport::class => DamageReportPolicy::class,
        DamageAssessment::class => DamageAssessmentPolicy::class,
        DecisionCriterion::class => DecisionCriterionPolicy::class,
        CriterionWeightVersion::class => CriterionWeightVersionPolicy::class,
        DecisionRun::class => DecisionRunPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}
