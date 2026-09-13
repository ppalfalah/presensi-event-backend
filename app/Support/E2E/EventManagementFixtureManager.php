<?php

namespace App\Support\E2E;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EventManagementFixtureManager
{
    public const STATES = [
        'events-empty',
        'events-list',
        'events-form',
        'events-edit',
        'events-status',
        'events-registrations',
        'events-pagination',
        'event-categories',
        'quota-one-remaining',
        'quota-full',
        'quota-race',
        'qr-generate',
        'qr-event-no-code',
        'qr-event-active-code',
        'qr-multiple-events',
        'qr-regenerate',
        'qr-pagination',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown event-management fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            [$admin, $alumni] = $this->resetEventDomain();
            $category = $this->createCategory('E2E General', 'Kategori utama fixture event E2E');

            match ($state) {
                'events-empty', 'events-form' => null,
                'events-list' => $this->createListEvents($admin, $category),
                'events-edit' => $this->createEvent($admin, $category, 'E2E Editable Event', 7),
                'events-status' => $this->createStatusEvents($admin, $category),
                'events-registrations' => $this->createRegistrationsState($admin, $alumni, $category),
                'events-pagination' => $this->createPaginationEvents($admin, $category),
                'event-categories' => $this->createCategoryState($admin, $category),
                'quota-one-remaining' => $this->createQuotaState($admin, $alumni, $category, 'E2E Quota One Remaining', 2, 1),
                'quota-full' => $this->createQuotaState($admin, $alumni, $category, 'E2E Quota Full', 2, 2),
                'quota-race' => $this->createQuotaRaceState($admin, $category),
                'qr-generate' => $this->createEvent($admin, $category, 'E2E QR Generate Event', 7),
                'qr-event-no-code' => $this->createEvent($admin, $category, 'E2E QR No Code Event', 7),
                'qr-event-active-code' => $this->createQrEvent(
                    $admin,
                    $category,
                    'E2E QR Active Event',
                    '00000000-0000-4000-8000-000000000106',
                    7,
                ),
                'qr-multiple-events' => $this->createQrSelectionEvents($admin, $category),
                'qr-regenerate' => $this->createQrEvent(
                    $admin,
                    $category,
                    'E2E QR Regenerate Event',
                    '00000000-0000-4000-8000-000000000109',
                    5,
                ),
                'qr-pagination' => $this->createQrPaginationEvents($admin, $category),
            };

            return $this->summary($state);
        });
    }

    private function resetEventDomain(): array
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
            '080000000002'
        );

        return [$admin, $alumni];
    }

    private function createListEvents(User $admin, Category $category): void
    {
        $this->createEvent($admin, $category, 'E2E Event Alpha', 5);
        $this->createEvent($admin, $category, 'E2E Event Beta', 8);
    }

    private function createStatusEvents(User $admin, Category $category): void
    {
        $this->createEvent($admin, $category, 'E2E Status Upcoming', 7);
        $this->createEvent($admin, $category, 'E2E Status Finished', -7);
        $this->createEvent($admin, $category, 'E2E Status Unpublished', 10, 'inactive');
    }

    private function createRegistrationsState(User $admin, User $alumni, Category $category): void
    {
        $event = $this->createEvent($admin, $category, 'E2E Registered Event', 7, 'active', 5);
        $secondAlumni = $this->createAlumni('Registered', 'Alumni Dua', 'e2e.registered.two@example.test', '080000000102');

        $this->register($event, $alumni);
        $this->register($event, $secondAlumni);
    }

    private function createPaginationEvents(User $admin, Category $category): void
    {
        foreach (range(1, 4) as $number) {
            $this->createEvent($admin, $category, "E2E Pagination Event {$number}", $number + 3);
        }
    }

    private function createCategoryState(User $admin, Category $usedCategory): void
    {
        $this->createCategory('E2E Unused Category', 'Kategori yang aman untuk dihapus');
        $this->createEvent($admin, $usedCategory, 'E2E Category Usage Event', 7);
    }

    private function createQuotaState(
        User $admin,
        User $targetAlumni,
        Category $category,
        string $title,
        int $quota,
        int $registrations,
    ): void {
        $event = $this->createEvent($admin, $category, $title, 7, 'active', $quota);

        foreach (range(1, $registrations) as $number) {
            $filler = $this->createAlumni(
                'Quota',
                "Filler {$number}",
                "e2e.quota.filler.{$number}@example.test",
                '0800000002'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)
            );
            $this->register($event, $filler);
        }

        $targetAlumni->tokens()->delete();
    }

    private function createQuotaRaceState(User $admin, Category $category): void
    {
        $this->createEvent($admin, $category, 'E2E Quota Race', 7, 'active', 1);
        $this->createAlumni('Quota', 'Alumni B', 'e2e.quota.b@example.test', '080000000299');
    }

    private function createQrEvent(
        User $admin,
        Category $category,
        string $title,
        string $token,
        int $durationDays,
    ): void {
        $event = $this->createEvent($admin, $category, $title, 7);

        EventQrCode::query()->create([
            'event_id' => $event->id,
            'qr_token' => $token,
            'qr_code_image' => null,
            'qr_code_url' => null,
            'valid_from' => now()->subMinute(),
            'duration_days' => $durationDays,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }

    private function createQrSelectionEvents(User $admin, Category $category): void
    {
        $this->createEvent($admin, $category, 'E2E QR Selection Alpha', 7);
        $this->createEvent($admin, $category, 'E2E QR Selection Beta', 8);
    }

    private function createQrPaginationEvents(User $admin, Category $category): void
    {
        foreach (range(1, 11) as $number) {
            $title = 'E2E QR Pagination '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->createEvent($admin, $category, $title, $number + 3);
        }
    }

    private function createCategory(string $name, string $description): Category
    {
        return Category::query()->create([
            'category_name' => $name,
            'description' => $description,
        ]);
    }

    private function createEvent(
        User $admin,
        Category $category,
        string $title,
        int $dayOffset,
        string $status = 'active',
        ?int $quota = null,
    ): Event {
        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => "Deskripsi {$title}",
            'location' => 'Aula E2E',
            'event_date' => today()->addDays($dayOffset),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'qr_token' => 'e2e-'.md5($title),
            'status_event' => $status,
            'quota' => $quota,
        ]);
    }

    private function createAlumni(string $firstName, string $lastName, string $email, string $phone): User
    {
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

    private function register(Event $event, User $user): void
    {
        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    private function summary(string $state): array
    {
        return [
            'state' => $state,
            'alumni' => User::query()->where('role', 'alumni')->count(),
            'events' => Event::query()->count(),
            'attendances' => 0,
        ];
    }
}
