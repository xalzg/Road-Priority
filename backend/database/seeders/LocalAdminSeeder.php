<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class LocalAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('LocalAdminSeeder is strictly for local/testing environments.');
        }

        $email = strtolower(trim((string) env('INITIAL_ADMIN_EMAIL', 'admin@roadpriority.local')));
        $name = trim((string) env('INITIAL_ADMIN_NAME', 'Initial System Administrator'));
        $configuredPassword = (string) env('INITIAL_ADMIN_PASSWORD', '');
        $generated = false;

        if ($configuredPassword === '') {
            $configuredPassword = Str::random(20);
            $generated = true;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($configuredPassword),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'action' => 'user.bootstrap_admin_seeded',
            'entity_type' => 'user',
            'entity_id' => $admin->id,
            'changes_summary' => [
                'email' => $admin->email,
                'role' => $admin->role,
                'password_source' => $generated ? 'generated_ephemeral' : 'env_configured',
            ],
            'created_at' => now(),
        ]);

        if ($this->command !== null) {
            $this->command->info("Local admin ready: {$admin->email}");
            if ($generated) {
                $this->command->warn("Generated one-time local admin password: {$configuredPassword}");
            }
        }
    }
}
