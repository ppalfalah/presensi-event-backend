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

class PhaseTwelveFixtureManager
{
    public const QR_TOKEN = '11111111-1111-4111-8111-111111111111';

    public const STATES = [
        'history-empty',
        'history-populated',
        'history-detail',
        'history-after-scan',
        'recommendation-single-category',
        'recommendation-new-user',
        'recommendation-active-match',
        'recommendation-no-active-match',
        'recommendation-inactive-past',
        'recommendation-dominant-category',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown Phase 12 fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            [$admin, $alumni] = $this->resetDomain();

            match ($state) {
                'history-empty' => null,
                'history-populated' => $this->createPopulatedHistory($admin, $alumni),
                'history-detail' => $this->createHistoryDetail($admin, $alumni),
                'history-after-scan' => $this->createHistoryScanState($admin, $alumni),
                'recommendation-single-category' => $this->createSingleCategoryRecommendation($admin, $alumni),
                'recommendation-new-user' => $this->createNewUserRecommendationState($admin),
                'recommendation-active-match' => $this->createActiveMatchRecommendation($admin, $alumni),
                'recommendation-no-active-match' => $this->createNoActiveMatchRecommendation($admin, $alumni),
                'recommendation-inactive-past' => $this->createInactivePastRecommendation($admin, $alumni),
                'recommendation-dominant-category' => $this->createDominantCategoryRecommendation($admin, $alumni),
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

    private function createPopulatedHistory(User $admin, User $alumni): void
    {
        $seminar = $this->createCategory('E2E History Seminar');
        $reuni = $this->createCategory('E2E History Reuni');

        $this->attend($this->createEvent($admin, $seminar, 'E2E History Seminar', -8), $alumni, 8);
        $this->attend($this->createEvent($admin, $reuni, 'E2E History Reuni', -5), $alumni, 5);
    }

    private function createHistoryDetail(User $admin, User $alumni): void
    {
        $category = $this->createCategory('E2E History Detail');
        $event = $this->createEvent(
            $admin,
            $category,
            'E2E History Detail Event',
            -7,
            location: 'Aula Riwayat E2E',
        );
        $this->attend($event, $alumni, 7, 10, 15);
    }

    private function createHistoryScanState(User $admin, User $alumni): void
    {
        $category = $this->createCategory('E2E History Scan');
        $event = $this->createEvent($admin, $category, 'E2E Newly Scanned History', 0);
        $event->update([
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'registered',
            'registered_at' => now()->subHour(),
        ]);

        EventQrCode::query()->create([
            'event_id' => $event->id,
            'qr_token' => self::QR_TOKEN,
            'qr_code_image' => null,
            'qr_code_url' => null,
            'valid_from' => now()->subMinutes(2),
            'duration_days' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }

    private function createSingleCategoryRecommendation(User $admin, User $alumni): void
    {
        $seminar = $this->createCategory('Seminar Alumni');
        $reuni = $this->createCategory('Reuni Alumni');
        $this->createInterestHistory($admin, $alumni, $seminar, 'E2E Past Seminar', 2);
        $this->createEvent($admin, $seminar, 'E2E Recommended Seminar', 7);
        $this->createEvent($admin, $reuni, 'E2E Unrelated Reuni', 6);
    }

    private function createNewUserRecommendationState(User $admin): void
    {
        $seminar = $this->createCategory('E2E New User Seminar');
        $reuni = $this->createCategory('E2E New User Reuni');
        $this->createEvent($admin, $seminar, 'E2E New User Recommendation A', 6);
        $this->createEvent($admin, $reuni, 'E2E New User Recommendation B', 8);
    }

    private function createActiveMatchRecommendation(User $admin, User $alumni): void
    {
        $category = $this->createCategory('E2E Active Match Category');
        $this->createInterestHistory($admin, $alumni, $category, 'E2E Active Match History', 2);
        $this->createEvent($admin, $category, 'E2E Active Matching Recommendation', 7);
    }

    private function createNoActiveMatchRecommendation(User $admin, User $alumni): void
    {
        $seminar = $this->createCategory('E2E Preferred Seminar');
        $reuni = $this->createCategory('E2E Fallback Category');
        $this->createInterestHistory($admin, $alumni, $seminar, 'E2E Preferred History', 2);
        $this->createEvent($admin, $seminar, 'E2E Inactive Preferred Event', 7, status: 'inactive');
        $this->createEvent($admin, $seminar, 'E2E Past Preferred Event', -1);
        $this->createEvent($admin, $reuni, 'E2E Fallback Reuni', 5);
    }

    private function createInactivePastRecommendation(User $admin, User $alumni): void
    {
        $seminar = $this->createCategory('E2E Exclusion Seminar');
        $this->createInterestHistory($admin, $alumni, $seminar, 'E2E Exclusion History', 2);
        $this->createEvent($admin, $seminar, 'E2E Inactive Matching Event', 7, status: 'inactive');
        $this->createEvent($admin, $seminar, 'E2E Past Matching Event', -1);
        $this->createEvent($admin, $seminar, 'E2E Valid Matching Event', 9);
    }

    private function createDominantCategoryRecommendation(User $admin, User $alumni): void
    {
        $seminar = $this->createCategory('E2E Dominant Seminar');
        $reuni = $this->createCategory('E2E Secondary Reuni');
        $this->createInterestHistory($admin, $alumni, $seminar, 'E2E Dominant History', 3);
        $this->createInterestHistory($admin, $alumni, $reuni, 'E2E Secondary History', 2);

        $this->createEvent($admin, $reuni, 'E2E Secondary Reuni Recommendation', 5);
        $this->createEvent($admin, $seminar, 'E2E Dominant Seminar Recommendation', 10);
    }

    private function createInterestHistory(
        User $admin,
        User $alumni,
        Category $category,
        string $titlePrefix,
        int $count,
    ): void {
        foreach (range(1, $count) as $number) {
            $event = $this->createEvent(
                $admin,
                $category,
                "{$titlePrefix} {$number}",
                -($number + 3),
            );
            $this->attend($event, $alumni, $number + 3);
        }
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
        int $dayOffset,
        string $status = 'active',
        string $location = 'Aula Phase 12 E2E',
    ): Event {
        return Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => $title,
            'description' => "Fixture {$title}",
            'location' => $location,
            'event_date' => today()->addDays($dayOffset),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'qr_token' => 'e2e-phase12-'.md5($title),
            'status_event' => $status,
            'quota' => 20,
        ]);
    }

    private function attend(
        Event $event,
        User $alumni,
        int $daysAgo,
        int $hour = 10,
        int $minute = 0,
    ): void {
        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'attended',
            'registered_at' => now()->subDays($daysAgo)->subHour(),
        ]);

        Presensi::query()->create([
            'event_id' => $event->id,
            'user_id' => $alumni->id,
            'status' => 'hadir',
            'scanned_at' => Carbon::now('Asia/Jakarta')
                ->subDays($daysAgo)
                ->setTime($hour, $minute),
        ]);
    }
}
