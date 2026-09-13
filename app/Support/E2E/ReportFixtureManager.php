<?php

namespace App\Support\E2E;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Presensi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReportFixtureManager
{
    public const STATES = [
        'reports-empty',
        'reports-summary',
        'reports-detail',
        'reports-detail-empty',
        'reports-full-attendance',
        'reports-pagination',
        'engagement-overview',
        'engagement-17',
        'engagement-boundaries',
        'engagement-pagination',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown report fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            [$admin, $category] = $this->resetDomain();

            match ($state) {
                'reports-empty' => null,
                'reports-summary' => $this->createReportSummary($admin, $category),
                'reports-detail' => $this->createReportDetail($admin, $category),
                'reports-detail-empty' => $this->createEvent($admin, $category, 'E2E Report Empty Attendance', 3, 5),
                'reports-full-attendance' => $this->createFullAttendanceReport($admin, $category),
                'reports-pagination' => $this->createReportPagination($admin, $category),
                'engagement-overview' => $this->createEngagementOverview($admin, $category),
                'engagement-17' => $this->createEngagementSeventeen($admin, $category),
                'engagement-boundaries' => $this->createEngagementBoundaries($admin, $category),
                'engagement-pagination' => $this->createEngagementPagination($admin, $category),
            };

            return $this->summary($state);
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

        $category = Category::query()->create([
            'category_name' => 'E2E Report',
            'description' => 'Kategori fixture laporan E2E',
        ]);

        return [$admin, $category];
    }

    private function createReportSummary(User $admin, Category $category): void
    {
        $first = $this->createEvent($admin, $category, 'E2E Report Summary Full', 8, 2);
        $second = $this->createEvent($admin, $category, 'E2E Report Summary Half', 6, 2);
        $users = [
            $this->createAlumni('Summary', 'Alumni Satu', 'summary.one@example.test', '081100000001', '2020'),
            $this->createAlumni('Summary', 'Alumni Dua', 'summary.two@example.test', '081100000002', '2021'),
            $this->createAlumni('Summary', 'Alumni Tiga', 'summary.three@example.test', '081100000003', '2022'),
        ];

        $this->attend($first, $users[0], 1);
        $this->attend($first, $users[1], 2);
        $this->attend($second, $users[2], 3);
    }

    private function createReportDetail(User $admin, Category $category): void
    {
        $event = $this->createEvent($admin, $category, 'E2E Report Detail Event', 5, 3);
        $alya = $this->createAlumni('Alya', 'Laporan', 'alya.report@example.test', '081200000001', '2019');
        $bima = $this->createAlumni('Bima', 'Laporan', 'bima.report@example.test', '081200000002', '2021');
        $citra = $this->createAlumni('Citra', 'Laporan', 'citra.report@example.test', '081200000003', '2020');

        $this->addDomicile($alya, '32', 'Jawa Barat', '32.73', 'Kota Bandung');
        $this->addDomicile($bima, '33', 'Jawa Tengah', '33.74', 'Kota Semarang');
        $this->addDomicile($citra, '32', 'Jawa Barat', '32.76', 'Kota Depok');

        $this->attend($event, $alya, 1);
        $this->attend($event, $bima, 2);
        $this->attend($event, $citra, 3);
    }

    private function createFullAttendanceReport(User $admin, Category $category): void
    {
        $event = $this->createEvent($admin, $category, 'E2E Report Full Attendance', 4, 2);
        $first = $this->createAlumni('Full', 'Attendee Satu', 'full.one@example.test', '081300000001', '2020');
        $second = $this->createAlumni('Full', 'Attendee Dua', 'full.two@example.test', '081300000002', '2021');

        $this->attend($event, $first, 1);
        $this->attend($event, $second, 2);
    }

    private function createReportPagination(User $admin, Category $category): void
    {
        foreach (range(1, 6) as $number) {
            $title = 'E2E Report Page '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->createEvent($admin, $category, $title, $number + 2, 10);
        }
    }

    private function createEngagementOverview(User $admin, Category $category): void
    {
        $events = $this->createEligibleEvents($admin, $category, 5, 'E2E Engagement Overview');
        $alya = $this->createAlumni('Alya', 'Pemula', 'engagement.alya@example.test', '082100000001', '2019');
        $bima = $this->createAlumni('Bima', 'Pemula', 'engagement.bima@example.test', '082100000002', '2020');
        $citra = $this->createAlumni('Citra', 'Menengah', 'engagement.citra@example.test', '082100000003', '2021');
        $damar = $this->createAlumni('Damar', 'Aktif', 'engagement.damar@example.test', '082100000004', '2020');

        $this->attendEvents($alya, $events, 1);
        $this->attendEvents($bima, $events, 1);
        $this->attendEvents($citra, $events, 2);
        $this->attendEvents($damar, $events, 4);
    }

    private function createEngagementSeventeen(User $admin, Category $category): void
    {
        $events = $this->createEligibleEvents($admin, $category, 17, 'E2E Engagement Seventeen');
        $zero = $this->createAlumni('Engagement', 'Zero', 'engagement.zero@example.test', '082200000001', '2020');
        $partial = $this->createAlumni('Engagement', 'Partial', 'engagement.partial@example.test', '082200000002', '2020');
        $full = $this->createAlumni('Engagement', 'Full', 'engagement.full@example.test', '082200000003', '2020');

        $this->attendEvents($zero, $events, 0);
        $this->attendEvents($partial, $events, 10);
        $this->attendEvents($full, $events, 17);
    }

    private function createEngagementBoundaries(User $admin, Category $category): void
    {
        $events = $this->createEligibleEvents($admin, $category, 35, 'E2E Engagement Boundary');
        $cases = [
            ['Zero', 'engagement.boundary.zero@example.test', '082300000001', 0],
            ['Beginner Low', 'engagement.boundary.beginner-low@example.test', '082300000002', 1],
            ['Beginner High', 'engagement.boundary.beginner-high@example.test', '082300000003', 13],
            ['Middle Low', 'engagement.boundary.middle-low@example.test', '082300000004', 14],
            ['Middle High', 'engagement.boundary.middle-high@example.test', '082300000005', 24],
            ['Full', 'engagement.boundary.full@example.test', '082300000006', 35],
        ];

        foreach ($cases as [$lastName, $email, $phone, $attendanceCount]) {
            $user = $this->createAlumni('Boundary', $lastName, $email, $phone, '2020');
            $this->attendEvents($user, $events, $attendanceCount);
        }
    }

    private function createEngagementPagination(User $admin, Category $category): void
    {
        $this->createEligibleEvents($admin, $category, 1, 'E2E Engagement Pagination Event');

        foreach (range(1, 11) as $number) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->createAlumni(
                'Page',
                "Alumni {$suffix}",
                "engagement.page.{$suffix}@example.test",
                '0824000000'.$suffix,
                '2020',
            );
        }
    }

    private function createEligibleEvents(User $admin, Category $category, int $count, string $prefix): array
    {
        return collect(range(1, $count))
            ->map(fn (int $number) => $this->createEvent(
                $admin,
                $category,
                "{$prefix} {$number}",
                $number + 1,
                100,
            ))
            ->all();
    }

    private function createEvent(
        User $admin,
        Category $category,
        string $title,
        int $daysAgo,
        int $quota,
    ): Event {
        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => "Fixture {$title}",
            'location' => 'Aula Laporan E2E',
            'event_date' => today()->subDays($daysAgo),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
            'quota' => $quota,
        ]);
    }

    private function createAlumni(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $graduationYear,
    ): User {
        return User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make('E2E-Reports-2026!'),
            'phone' => $phone,
            'graduation_year' => $graduationYear,
            'birth_date' => '2000-01-01',
            'gender' => 'Perempuan',
            'role' => 'alumni',
            'admin_level' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function addDomicile(
        User $user,
        string $provinceCode,
        string $provinceName,
        string $cityCode,
        string $cityName,
    ): void {
        $user->domicile()->create([
            'province_code' => $provinceCode,
            'province_name' => $provinceName,
            'city_code' => $cityCode,
            'city_name' => $cityName,
            'district_code' => null,
            'district_name' => null,
            'village_code' => null,
            'village_name' => null,
            'postal_code' => null,
            'address' => 'Alamat fixture laporan E2E',
        ]);
    }

    private function attendEvents(User $user, array $events, int $count): void
    {
        foreach (array_slice($events, 0, $count) as $index => $event) {
            $this->attend($event, $user, $index + 1);
        }
    }

    private function attend(Event $event, User $user, int $minuteOffset): void
    {
        $registeredAt = $event->event_date->copy()->subDay()->setTime(9, 0)->addMinutes($minuteOffset);
        $scannedAt = $event->event_date->copy()->setTime(9, 0)->addMinutes($minuteOffset);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'attended',
            'registered_at' => $registeredAt,
        ]);

        Presensi::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'hadir',
            'scanned_at' => $scannedAt,
        ]);
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
