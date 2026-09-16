<?php

namespace App\Support\E2E;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhaseElevenFixtureManager
{
    public const QR_TOKEN = '11111111-1111-4111-8111-111111111111';

    public const STATES = [
        'scan-base',
        'scan-valid',
        'scan-event-not-started',
        'scan-event-ended',
        'scan-unregistered',
        'scan-already-attended',
        'scan-qr-before-valid',
        'scan-qr-valid-window',
        'scan-qr-expired',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown Phase 11 fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            [$admin, $alumni] = $this->resetDomain();

            match ($state) {
                'scan-base' => null,
                'scan-valid', 'scan-qr-valid-window' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan Valid',
                ),
                'scan-event-not-started' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan Event Belum Mulai',
                    eventDayOffset: 1,
                ),
                'scan-event-ended' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan Event Berakhir',
                    eventDayOffset: -1,
                ),
                'scan-unregistered' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan Belum Terdaftar',
                    registered: false,
                ),
                'scan-already-attended' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan Sudah Hadir',
                    attended: true,
                ),
                'scan-qr-before-valid' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan QR Belum Aktif',
                    qrValidFrom: now()->addMinutes(2),
                ),
                'scan-qr-expired' => $this->createScanState(
                    $admin,
                    $alumni,
                    'E2E Scan QR Kedaluwarsa',
                    qrValidFrom: now()->subDays(2),
                ),
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

        $alumni = User::query()->create([
            'first_name' => 'E2E',
            'last_name' => 'Alumni',
            'email' => config('e2e.alumni.email'),
            'password' => Hash::make((string) config('e2e.alumni.password')),
            'phone' => '080000000002',
            'graduation_year' => '2020',
            'birth_date' => '2000-01-01',
            'gender' => 'Perempuan',
            'role' => 'alumni',
            'admin_level' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return [$admin, $alumni];
    }

    private function createScanState(
        User $admin,
        User $alumni,
        string $title,
        int $eventDayOffset = 0,
        bool $registered = true,
        bool $attended = false,
        ?Carbon $qrValidFrom = null,
    ): void {
        $category = Category::query()->create([
            'category_name' => 'E2E Scan Presensi',
            'description' => 'Kategori fixture scan QR Phase 11',
        ]);

        $event = Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => "Fixture {$title}",
            'location' => 'Aula Scan E2E',
            'event_date' => today()->addDays($eventDayOffset),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'qr_token' => '22222222-2222-4222-8222-222222222222',
            'status_event' => 'active',
            'quota' => 10,
        ]);

        EventQrCode::query()->create([
            'event_id' => $event->id,
            'qr_token' => self::QR_TOKEN,
            'qr_code_image' => null,
            'qr_code_url' => null,
            'valid_from' => $qrValidFrom ?? now()->subMinutes(2),
            'duration_days' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        if (! $registered) {
            return;
        }

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => $attended ? 'attended' : 'registered',
            'registered_at' => now()->subHour(),
        ]);

        if ($attended) {
            Presensi::query()->create([
                'event_id' => $event->id,
                'user_id' => $alumni->id,
                'status' => 'hadir',
                'scanned_at' => now()->subMinutes(5),
            ]);
        }
    }
}
