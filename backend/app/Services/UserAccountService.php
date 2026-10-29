<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserAccountService
{
    private AuditLogService $auditLogs;

    public function __construct(AuditLogService $auditLogs)
    {
        $this->auditLogs = $auditLogs;
    }

    /**
     * @param  array{name:string, email:string, password:string, role?:string, is_active?:bool}  $data
     */
    public function createAccount(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->role = $data['role'] ?? User::ROLE_REPORTER;
            $user->is_active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
            $user->save();

            $this->auditLogs->record($actor, 'user.created', 'user', $user->id, [
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name?:string, email?:string, password?:string, role?:string, is_active?:bool}  $data
     */
    public function updateAccount(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data) {
            $newRole = $data['role'] ?? $target->role;
            $newActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $target->is_active;

            // PRD 03 §7: Lindungi akun admin aktif terakhir agar sistem tidak terkunci
            if ($target->role === User::ROLE_ADMIN && $target->is_active && ($newRole !== User::ROLE_ADMIN || ! $newActive)) {
                $otherActiveAdmins = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->where('id', '!=', $target->id)
                    ->lockForUpdate()
                    ->count();

                if ($otherActiveAdmins === 0) {
                    throw new ConflictHttpException('Cannot deactivate or demote the last active admin account.');
                }
            }

            $before = [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'is_active' => (bool) $target->is_active,
            ];

            if (array_key_exists('name', $data)) {
                $target->name = $data['name'];
            }
            if (array_key_exists('email', $data)) {
                $target->email = $data['email'];
            }
            if (! empty($data['password'])) {
                $target->password = Hash::make($data['password']);
            }
            $target->role = $newRole;
            $target->is_active = $newActive;
            $target->save();

            if (! $target->is_active) {
                $target->tokens()->delete();
            }

            $this->auditLogs->record($actor, 'user.updated', 'user', $target->id, [
                'before' => $before,
                'after' => [
                    'name' => $target->name,
                    'email' => $target->email,
                    'role' => $target->role,
                    'is_active' => (bool) $target->is_active,
                ],
            ]);

            return $target;
        });
    }
}

