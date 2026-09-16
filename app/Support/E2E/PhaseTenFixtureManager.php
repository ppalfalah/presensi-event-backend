<?php

namespace App\Support\E2E;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhaseTenFixtureManager
{
    public const STATES = [
        'alumni-events-list',
        'alumni-events-empty',
        'alumni-events-filters',
        'alumni-event-unregistered',
        'alumni-event-registered',
        'alumni-event-quota-register',
        'alumni-event-quota-full',
        'alumni-event-quota-cancel',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown Phase 10 fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            [$admin, $alumni] = $this->resetDomain();

            match ($state) {
                'alumni-events-empty' => null,
                'alumni-events-list', 'alumni-events-filters' => $this->createListState($admin),
                'alumni-event-unregistered' => $this->createEventState(
                    $admin,
                    'E2E Registration Open',
                    5,
                ),
                'alumni-event-registered' => $this->createRegisteredState($admin, $alumni),
                'alumni-event-quota-register' => $this->createQuotaRegisterState($admin),
                'alumni-event-quota-full' => $this->createQuotaFullState($admin),
                'alumni-event-quota-cancel' => $this->createQuotaCancelState($admin, $alumni),
            };

            return [
                'state' => $state,
                'alumni' => User::query()->where('role', 'alumni')->count(),
                'events' => Event::query()->count(),
                'attendances' => Presensi::query()->count(),
            ];
        });
    }

    private function resetDomain(): array
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
                'phone' => '080000000001',
                'gender' => 'Laki-laki',
                'role' => 'admin',
                'admin_level' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->tokens()->delete();

        $alumni = $this->createAlumni(
            'E2E',
            'Alumni',
            (string) config('e2e.alumni.email'),
            '080000000002',
        );

        return [$admin, $alumni];
    }

    private function createListState(User $admin): void
    {
        $workshop = $this->createCategory('E2E Workshop');
        $seminar = $this->createCategory('E2E Seminar');

        $this->createEvent(
            $admin,
            $workshop,
            'E2E Alumni Workshop',
            'Aula Alumni E2E',
            7,
            10,
        );
        $this->createEvent(
            $admin,
            $seminar,
            'E2E Alumni Seminar',
            'Lapangan Alumni E2E',
            8,
            20,
        );
    }

    private function createEventState(User $admin, string $title, int $quota): Event
    {
        $category = $this->createCategory('E2E Registration');

        return $this->createEvent($admin, $category, $title, 'Aula Registrasi E2E', 7, $quota);
    }

    private function createRegisteredState(User $admin, User $alumni): void
    {
        $event = $this->createEventState($admin, 'E2E Registration Cancel', 5);
        $this->register($event, $alumni);
    }

    private function createQuotaRegisterState(User $admin): void
    {
        $event = $this->createEventState($admin, 'E2E Quota Decrease', 3);
        $filler = $this->createAlumni(
            'Quota',
            'Filler Satu',
            'e2e.phase10.quota.one@example.test',
            '080000001001',
        );
        $this->register($event, $filler);
    }

    private function createQuotaFullState(User $admin): void
    {
        $event = $this->createEventState($admin, 'E2E Quota Full Alumni', 2);

        foreach (range(1, 2) as $number) {
            $filler = $this->createAlumni(
                'Quota',
                "Full {$number}",
                "e2e.phase10.full.{$number}@example.test",
                '0800000011'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            );
            $this->register($event, $filler);
        }
    }

    private function createQuotaCancelState(User $admin, User $alumni): void
    {
        $event = $this->createEventState($admin, 'E2E Quota Restore', 3);
        $filler = $this->createAlumni(
            'Quota',
            'Filler Dua',
            'e2e.phase10.quota.two@example.test',
            '080000001002',
        );

        $this->register($event, $alumni);
        $this->register($event, $filler);
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'category_name' => $name,
            'description' => "Kategori fixture {$name}",
        ]);
    }

    private function createEvent(
        User $admin,
        Category $category,
        string $title,
        string $location,
        int $dayOffset,
        int $quota,
    ): Event {
        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => "Deskripsi {$title}",
            'location' => $location,
            'event_date' => today()->addDays($dayOffset),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'qr_token' => 'e2e-phase10-'.md5($title),
            'status_event' => 'active',
            'quota' => $quota,
        ]);
    }

    private function createAlumni(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
    ): User {
        return User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make((string) config('e2e.alumni.password')),
            'phone' => $phone,
            'graduation_year' => '2020',
            'birth_date' => '2000-01-01',
            'gender' => 'Perempuan',
            'role' => 'alumni',
            'admin_level' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function register(Event $event, User $alumni): void
    {
        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }
}
