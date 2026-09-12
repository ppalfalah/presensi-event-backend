<?php

namespace App\Support\E2E;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DashboardFixtureManager
{
    public const STATES = [
        'admin-empty',
        'admin-populated',
        'alumni-no-attendance',
        'alumni-with-attendance',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown dashboard fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            [$admin, $alumni] = $this->resetDashboardDomain($state !== 'admin-empty');

            return match ($state) {
                'admin-empty' => $this->summary($state),
                'admin-populated' => $this->prepareAdminPopulated($state, $admin, $alumni),
                'alumni-no-attendance' => $this->summary($state),
                'alumni-with-attendance' => $this->prepareAlumniWithAttendance($state, $admin, $alumni),
            };
        });
    }

    private function resetDashboardDomain(bool $includeAlumni): array
    {
        Event::query()->delete();

        $adminEmail = (string) config('e2e.admin.email');
        User::query()->where('email', '!=', $adminEmail)->delete();

        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'first_name' => 'E2E',
                'last_name' => 'Admin',
                'password' => Hash::make((string) config('e2e.admin.password')),
                'phone' => '080000000001',
                'gender' => 'Laki-laki',
                'role' => 'admin',
                'admin_level' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->tokens()->delete();

        if (! $includeAlumni) {
            return [$admin, null];
        }

        $alumni = $this->createAlumni(
            (string) config('e2e.alumni.email'),
            (string) config('e2e.alumni.password'),
            'E2E',
            'Alumni',
            '080000000002'
        );

        return [$admin, $alumni];
    }

    private function prepareAdminPopulated(string $state, User $admin, User $baselineAlumni): array
    {
        $alumni = collect([
            $baselineAlumni,
            $this->createAlumni('e2e.dashboard.alumni2@example.test', 'E2E-Dashboard-2026!', 'Dashboard', 'Alumni Dua', '080000000012'),
            $this->createAlumni('e2e.dashboard.alumni3@example.test', 'E2E-Dashboard-2026!', 'Dashboard', 'Alumni Tiga', '080000000013'),
        ]);

        $events = collect([
            $this->createEvent($admin, 'E2E Dashboard Event 1', now()->addDays(7)),
            $this->createEvent($admin, 'E2E Dashboard Event 2', now()->addDays(14)),
        ]);

        foreach ($alumni as $index => $user) {
            Presensi::query()->create([
                'event_id' => $events[$index % 2]->id,
                'user_id' => $user->id,
                'status' => 'hadir',
                'scanned_at' => now(),
            ]);
        }

        return $this->summary($state);
    }

    private function prepareAlumniWithAttendance(string $state, User $admin, User $alumni): array
    {
        $events = collect(range(1, 5))->map(
            fn (int $number) => $this->createEvent(
                $admin,
                "E2E Dashboard Attendance {$number}",
                now()->subDays(6 - $number)
            )
        );

        foreach ($events->take(2) as $index => $event) {
            EventRegistration::query()->create([
                'event_id' => $event->id,
                'user_id' => $alumni->id,
                'status' => 'attended',
                'registered_at' => $event->event_date->copy()->subDay()->setTime(9, 0),
            ]);

            Presensi::query()->create([
                'event_id' => $event->id,
                'user_id' => $alumni->id,
                'status' => 'hadir',
                'scanned_at' => $event->event_date->copy()->setTime(9, 0)->addMinutes($index),
            ]);
        }

        return $this->summary($state);
    }

    private function createAlumni(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        string $phone,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make($password),
                'phone' => $phone,
                'graduation_year' => '2020',
                'birth_date' => '2000-01-01',
                'gender' => 'Perempuan',
                'role' => 'alumni',
                'admin_level' => null,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $user->tokens()->delete();

        return $user;
    }

    private function createEvent(User $admin, string $title, CarbonInterface $date): Event
    {
        $category = Category::query()->firstOrCreate(
            ['category_name' => 'E2E Dashboard'],
            ['description' => 'Kategori fixture dashboard E2E']
        );

        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => 'Fixture dashboard E2E.',
            'location' => 'Aula E2E',
            'event_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
            'quota' => 100,
        ]);
    }

    private function summary(string $state): array
    {
        return [
            'state' => $state,
            'alumni' => User::query()->where('role', 'alumni')->count(),
            'events' => Event::query()->count(),
            'attendances' => Presensi::query()->where('status', 'hadir')->count(),
        ];
    }
}
