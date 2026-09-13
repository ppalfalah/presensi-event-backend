<?php

namespace App\Support\E2E;

use App\Models\Category;
use App\Models\Event;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhaseNineFixtureManager
{
    public const STATES = [
        'broadcast-event',
        'settings-admin',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown Phase 9 fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            $admin = $this->resetDomain();

            if ($state === 'broadcast-event') {
                $category = Category::query()->create([
                    'category_name' => 'E2E Phase 9',
                    'description' => 'Kategori fixture pesan WhatsApp E2E',
                ]);

                Event::query()->create([
                    'category_id' => $category->id,
                    'created_by' => $admin->id,
                    'event_title' => 'E2E WhatsApp Gathering',
                    'description' => 'Silaturahmi alumni untuk fixture Phase 9',
                    'location' => 'Aula Phase 9',
                    'event_date' => today()->addDays(7),
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'qr_token' => 'e2e-phase-nine-broadcast',
                    'status_event' => 'active',
                    'quota' => 25,
                ]);
            }

            return [
                'state' => $state,
                'alumni' => User::query()->where('role', 'alumni')->count(),
                'events' => Event::query()->count(),
                'attendances' => Presensi::query()->count(),
            ];
        });
    }

    private function resetDomain(): User
    {
        Event::query()->delete();
        Category::query()->delete();

        $adminEmail = (string) config('e2e.admin.email');
        User::query()->where('email', '!=', $adminEmail)->delete();

        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'first_name' => 'E2E',
                'last_name' => 'Admin',
                'password' => Hash::make((string) config('e2e.admin.password')),
                'password_changed_at' => null,
                'phone' => '080000000001',
                'gender' => 'Laki-laki',
                'role' => 'admin',
                'admin_level' => 'super_admin',
                'status' => 'active',
                'status_reason' => null,
                'avatar_url' => null,
                'google_id' => null,
                'auth_provider' => 'email',
                'email_verified_at' => now(),
            ]
        );
        $admin->tokens()->delete();

        return $admin;
    }
}
