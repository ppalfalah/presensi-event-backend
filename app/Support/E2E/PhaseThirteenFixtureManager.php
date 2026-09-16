<?php

namespace App\Support\E2E;

use App\Models\AlumniNotification;
use App\Models\Category;
use App\Models\Event;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class PhaseThirteenFixtureManager
{
    public const ALUMNI_EMAIL = 'phase13.profile@example.test';

    public const AVATAR_FILENAME = 'e2e-phase13-existing.png';

    public const STATES = [
        'notifications-unread',
        'notifications-zero-unread',
        'notifications-popup',
        'notifications-event',
        'notifications-mixed',
        'notifications-all-unread',
        'notifications-summary',
        'notifications-refresh',
        'notifications-refresh-add',
        'notifications-empty',
        'profile-complete',
        'profile-avatar',
        'profile-no-avatar',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown Phase 13 fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            if ($state === 'notifications-refresh-add') {
                $alumni = User::query()->where('email', self::ALUMNI_EMAIL)->firstOrFail();
                AlumniNotification::query()
                    ->where('user_id', $alumni->id)
                    ->where('title', 'E2E Refresh Notification')
                    ->delete();
                $this->createNotification(
                    $alumni,
                    'E2E Refresh Notification',
                    'Notifikasi baru setelah halaman dimuat.',
                    false,
                    0,
                );

                return $this->summary($state);
            }

            $alumni = $this->resetDomain();

            match ($state) {
                'notifications-unread' => $this->createUnreadNotifications($alumni, 3),
                'notifications-zero-unread' => $this->createReadNotifications($alumni, 2),
                'notifications-popup' => $this->createPopupNotifications($alumni),
                'notifications-event' => $this->createEventNotification($alumni),
                'notifications-mixed' => $this->createMixedNotifications($alumni),
                'notifications-all-unread' => $this->createUnreadNotifications($alumni, 2),
                'notifications-summary' => $this->createSummaryNotifications($alumni),
                'notifications-refresh' => $this->createNotification(
                    $alumni,
                    'E2E Initial Notification',
                    'Notifikasi awal sebelum refresh.',
                    false,
                    5,
                ),
                'notifications-empty', 'profile-complete', 'profile-no-avatar' => null,
                'profile-avatar' => $this->prepareExistingAvatar($alumni),
            };

            return $this->summary($state);
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
                'phone' => '080000000001',
                'gender' => 'Laki-laki',
                'role' => 'admin',
                'admin_level' => 'super_admin',
                'status' => 'active',
                'avatar_url' => null,
                'email_verified_at' => now(),
            ],
        );
        $admin->tokens()->delete();

        $this->cleanE2EAvatars();

        $alumni = User::query()->create([
            'first_name' => 'E2E',
            'last_name' => 'Alumni Profile',
            'email' => self::ALUMNI_EMAIL,
            'password' => Hash::make((string) config('e2e.alumni.password')),
            'phone' => '081234567890',
            'graduation_year' => '2020',
            'birth_date' => '2000-01-15',
            'gender' => 'Laki-laki',
            'role' => 'alumni',
            'admin_level' => null,
            'status' => 'active',
            'avatar_url' => null,
            'email_verified_at' => now(),
        ]);

        $alumni->domicile()->create([
            'province_code' => '32',
            'province_name' => 'Jawa Barat',
            'city_code' => '32.73',
            'city_name' => 'Kota Bandung',
            'district_code' => '32.73.01',
            'district_name' => 'Sukasari',
            'village_code' => '32.73.01.1001',
            'village_name' => 'Isola',
            'postal_code' => '40111',
            'address' => 'Jl. E2E Phase 13 No. 13',
        ]);

        return $alumni;
    }

    private function createUnreadNotifications(User $alumni, int $count): void
    {
        foreach (range(1, $count) as $number) {
            $title = $number === 1
                ? 'E2E Event Notification Unread'
                : "E2E Unread Notification {$number}";
            $this->createNotification(
                $alumni,
                $title,
                "Pesan notifikasi belum dibaca {$number}.",
                false,
                $number,
            );
        }
    }

    private function createReadNotifications(User $alumni, int $count): void
    {
        foreach (range(1, $count) as $number) {
            $this->createNotification(
                $alumni,
                "E2E Read Notification {$number}",
                "Pesan notifikasi sudah dibaca {$number}.",
                true,
                $number,
            );
        }
    }

    private function createPopupNotifications(User $alumni): void
    {
        $this->createNotification($alumni, 'E2E Popup Old Notification', 'Ringkasan lama.', true, 20);
        $this->createNotification($alumni, 'E2E Popup Latest Three', 'Ringkasan terbaru ketiga.', false, 3);
        $this->createNotification($alumni, 'E2E Popup Latest Two', 'Ringkasan terbaru kedua.', false, 2);
        $this->createNotification($alumni, 'E2E Popup Latest One', 'Ringkasan paling baru.', false, 1);
    }

    private function createEventNotification(User $alumni): void
    {
        $this->createNotification(
            $alumni,
            'E2E Event Notification Baru',
            'Event baru E2E Phase 13 tersedia untuk alumni.',
            false,
            1,
            [
                'event_title' => 'E2E Phase 13 Event Baru',
                'location' => 'Aula E2E Phase 13',
                'starts_at' => today()->addDays(7)->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'category' => 'Silaturahmi',
            ],
        );
    }

    private function createMixedNotifications(User $alumni): void
    {
        $this->createNotification($alumni, 'E2E Unread Notification A', 'Unread A.', false, 1);
        $this->createNotification($alumni, 'E2E Unread Notification B', 'Unread B.', false, 2);
        $this->createNotification($alumni, 'E2E Read Notification A', 'Read A.', true, 3);
        $this->createNotification($alumni, 'E2E Read Notification B', 'Read B.', true, 4);

        $otherAlumni = User::query()->create([
            'first_name' => 'Other',
            'last_name' => 'E2E Alumni',
            'email' => 'phase13.other@example.test',
            'password' => Hash::make((string) config('e2e.alumni.password')),
            'phone' => '081200000013',
            'graduation_year' => '2019',
            'birth_date' => '1999-01-15',
            'gender' => 'Perempuan',
            'role' => 'alumni',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->createNotification(
            $otherAlumni,
            'E2E Other User Notification',
            'Notifikasi ini tidak boleh tampil untuk alumni target.',
            false,
            0,
        );
    }

    private function createSummaryNotifications(User $alumni): void
    {
        $this->createNotification($alumni, 'E2E Summary Unread A', 'Summary unread A.', false, 1);
        $this->createNotification($alumni, 'E2E Summary Unread B', 'Summary unread B.', false, 2);
        $this->createNotification($alumni, 'E2E Summary Read', 'Summary read.', true, 3);
    }

    private function createNotification(
        User $alumni,
        string $title,
        string $body,
        bool $isRead,
        int $minutesAgo,
        ?array $data = null,
    ): AlumniNotification {
        $createdAt = now()->subMinutes($minutesAgo);
        $notification = AlumniNotification::query()->create([
            'user_id' => $alumni->id,
            'title' => $title,
            'body' => $body,
            'type' => $data === null ? 'account_info' : 'upcoming_event',
            'priority' => 'normal',
            'data' => $data,
            'is_read' => $isRead,
            'read_at' => $isRead ? $createdAt->copy()->addMinute() : null,
        ]);
        $notification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $notification;
    }

    private function prepareExistingAvatar(User $alumni): void
    {
        $directory = storage_path('app/public/e2e/avatars');
        File::ensureDirectoryExists($directory);
        File::put(
            $directory.DIRECTORY_SEPARATOR.self::AVATAR_FILENAME,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlQAAAABJRU5ErkJggg==', true),
        );
        $alumni->update([
            'avatar_url' => '/storage/avatars/'.self::AVATAR_FILENAME,
        ]);
    }

    private function cleanE2EAvatars(): void
    {
        $directory = storage_path('app/public/e2e/avatars');
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $path) {
            File::delete($path->getPathname());
        }
    }

    private function summary(string $state): array
    {
        return [
            'state' => $state,
            'alumni' => User::query()->where('role', 'alumni')->count(),
            'events' => Event::query()->count(),
            'attendances' => Presensi::query()->count(),
        ];
    }
}
