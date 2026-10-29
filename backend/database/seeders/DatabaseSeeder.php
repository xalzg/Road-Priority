<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database safely and idempotently (PRD 05.1 BUG-P1-01).
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            CandidateCriteriaSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call([
                LocalAdminSeeder::class,
            ]);
        }
    }
}
