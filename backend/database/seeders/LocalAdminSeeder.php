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

        /** @var User|null $existing */
        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            if ($configuredPassword === '') {
                $configuredPassword = Str::random(20);
                $generated = true;
            }

            $admin = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($configuredPassword),
                'email_verified_at' => now(),
            ]);
            $admin->role = User::ROLE_ADMIN;
            $admin->is_active = true;
            $admin->save();
            $passwordSource = $generated ? 'generated_ephemeral' : 'env_configured';
        } else {
            $admin = $existing;
            $admin->name = $name;
            if ($configuredPassword !== '') {
                $admin->password = Hash::make($configuredPassword);
                $passwordSource = 'env_configured';
            } else {
                $passwordSource = 'unchanged_existing';
            }
            if ($admin->email_verified_at === null) {
                $admin->email_verified_at = now();
            }
            $admin->role = User::ROLE_ADMIN;
            $admin->is_active = true;
            $admin->save();
        }

        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'action' => 'user.bootstrap_admin_seeded',
            'entity_type' => 'user',
            'entity_id' => $admin->id,
            'changes_summary' => [
                'email' => $admin->email,
                'role' => $admin->role,
                'password_source' => $passwordSource,
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
