<?php

namespace Tests\Feature;

use App\Models\AlumniNotification;
use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\User;
use App\Support\E2E\PhaseThirteenFixtureManager;
use App\Support\E2E\E2EEnvironmentGuard;
use Database\Seeders\E2EDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class E2EInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_command_refuses_the_normal_phpunit_environment_before_connecting_elsewhere(): void
    {
        $this->artisan('e2e:reset')
            ->expectsOutputToContain('APP_ENV must be e2e')
            ->assertExitCode(1);

        $this->artisan('e2e:fixture', ['state' => 'admin-empty'])
            ->expectsOutputToContain('APP_ENV must be e2e')
            ->assertExitCode(1);
    }

    public function test_baseline_seeder_creates_deterministic_authorized_accounts(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->once();

        $this->seed(E2EDatabaseSeeder::class);

        $admin = User::query()->where('email', config('e2e.admin.email'))->firstOrFail();
        $alumni = User::query()->where('email', config('e2e.alumni.email'))->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertSame('super_admin', $admin->admin_level);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check((string) config('e2e.admin.password'), $admin->password));

        $this->assertSame('alumni', $alumni->role);
        $this->assertNull($alumni->admin_level);
        $this->assertSame('active', $alumni->status);
        $this->assertTrue(Hash::check((string) config('e2e.alumni.password'), $alumni->password));

        $this->assertDatabaseHas('regions', [
            'code' => '32.73.01.1001',
            'name' => 'Isola',
            'type' => 'village',
            'parent_code' => '32.73.01',
            'postal_code' => '40154',
        ]);
        $this->assertDatabaseHas('regions', [
            'code' => '33.74.04.1001',
            'name' => 'Bulusan',
            'type' => 'village',
            'parent_code' => '33.74.04',
            'postal_code' => '50277',
        ]);
    }

    public function test_dashboard_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(5);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'admin-populated'])->assertSuccessful();
        $this->assertSame(3, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('presensis', 3);

        $this->artisan('e2e:fixture', ['state' => 'admin-empty'])->assertSuccessful();
        $this->assertSame(0, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'alumni-no-attendance'])->assertSuccessful();
        $this->assertSame(1, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'alumni-with-attendance'])->assertSuccessful();
        $this->assertSame(1, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 5);
        $this->assertDatabaseCount('event_registrations', 2);
        $this->assertDatabaseCount('presensis', 2);
    }

    public function test_user_management_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(7);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'users-filter'])->assertSuccessful();
        $this->assertSame(5, User::query()->where('role', 'alumni')->count());
        $this->assertSame(4, User::query()->whereHas('domicile')->count());
        $this->assertSame(2, User::query()->where('role', 'alumni')->where('status', 'active')->count());
        $this->assertSame(1, User::query()->where('role', 'alumni')->where('status', 'pending')->count());
        $this->assertSame(1, User::query()->where('role', 'alumni')->where('status', 'inactive')->count());
        $this->assertSame(1, User::query()->where('role', 'alumni')->where('status', 'rejected')->count());

        $this->artisan('e2e:fixture', ['state' => 'users-empty'])->assertSuccessful();
        $this->assertSame(0, User::query()->where('role', 'alumni')->count());

        $this->artisan('e2e:fixture', ['state' => 'users-editable'])->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'e2e.editable@example.test']);

        $this->artisan('e2e:fixture', ['state' => 'users-deletable'])->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'e2e.disposable@example.test']);

        $this->artisan('e2e:fixture', ['state' => 'users-pagination-10'])->assertSuccessful();
        $this->assertSame(10, User::query()->where('role', 'alumni')->count());

        $this->artisan('e2e:fixture', ['state' => 'users-pagination-11'])->assertSuccessful();
        $this->assertSame(11, User::query()->where('role', 'alumni')->count());
    }

    public function test_event_management_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(8);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'events-status'])->assertSuccessful();
        $this->assertDatabaseCount('events', 3);
        $this->assertSame(1, Event::query()->where('status_event', 'inactive')->count());

        $this->artisan('e2e:fixture', ['state' => 'events-registrations'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 2);

        $this->artisan('e2e:fixture', ['state' => 'events-pagination'])->assertSuccessful();
        $this->assertDatabaseCount('events', 4);

        $this->artisan('e2e:fixture', ['state' => 'event-categories'])->assertSuccessful();
        $this->assertDatabaseCount('categories', 2);

        $this->artisan('e2e:fixture', ['state' => 'quota-one-remaining'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 1);

        $this->artisan('e2e:fixture', ['state' => 'quota-full'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 2);

        $this->artisan('e2e:fixture', ['state' => 'quota-race'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 0);
        $this->assertDatabaseHas('users', ['email' => 'e2e.quota.b@example.test', 'status' => 'active']);
    }

    public function test_qr_code_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(7);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'qr-generate'])->assertSuccessful();
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('event_qr_codes', 0);

        $this->artisan('e2e:fixture', ['state' => 'qr-event-no-code'])->assertSuccessful();
        $this->assertDatabaseCount('event_qr_codes', 0);

        $this->artisan('e2e:fixture', ['state' => 'qr-event-active-code'])->assertSuccessful();
        $this->assertSame(1, EventQrCode::query()->where('is_active', true)->count());
        $this->assertDatabaseHas('event_qr_codes', [
            'qr_token' => '00000000-0000-4000-8000-000000000106',
            'duration_days' => 7,
            'is_active' => true,
        ]);

        $this->artisan('e2e:fixture', ['state' => 'qr-multiple-events'])->assertSuccessful();
        $this->assertDatabaseCount('events', 2);

        $this->artisan('e2e:fixture', ['state' => 'qr-regenerate'])->assertSuccessful();
        $this->assertDatabaseHas('event_qr_codes', [
            'qr_token' => '00000000-0000-4000-8000-000000000109',
            'duration_days' => 5,
            'is_active' => true,
        ]);

        $this->artisan('e2e:fixture', ['state' => 'qr-pagination'])->assertSuccessful();
        $this->assertDatabaseCount('events', 11);
        $this->assertDatabaseCount('event_qr_codes', 0);
    }

    public function test_report_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(11);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'reports-empty'])->assertSuccessful();
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'reports-summary'])->assertSuccessful();
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('presensis', 3);

        $this->artisan('e2e:fixture', ['state' => 'reports-detail'])->assertSuccessful();
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('presensis', 3);

        $this->artisan('e2e:fixture', ['state' => 'reports-detail-empty'])->assertSuccessful();
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'reports-full-attendance'])->assertSuccessful();
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('event_registrations', 2);
        $this->assertDatabaseCount('presensis', 2);

        $this->artisan('e2e:fixture', ['state' => 'reports-pagination'])->assertSuccessful();
        $this->assertDatabaseCount('events', 6);

        $this->artisan('e2e:fixture', ['state' => 'engagement-overview'])->assertSuccessful();
        $this->assertSame(4, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 5);
        $this->assertDatabaseCount('presensis', 8);

        $this->artisan('e2e:fixture', ['state' => 'engagement-17'])->assertSuccessful();
        $this->assertDatabaseCount('events', 17);
        $this->assertDatabaseCount('presensis', 27);

        $this->artisan('e2e:fixture', ['state' => 'engagement-boundaries'])->assertSuccessful();
        $this->assertDatabaseCount('events', 100);
        $this->assertDatabaseCount('presensis', 319);

        $this->artisan('e2e:fixture', ['state' => 'engagement-pagination'])->assertSuccessful();
        $this->assertSame(11, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('presensis', 0);
    }

    public function test_phase_nine_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(3);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'broadcast-event'])->assertSuccessful();
        $this->assertDatabaseHas('events', [
            'event_title' => 'E2E WhatsApp Gathering',
            'location' => 'Aula Phase 9',
            'quota' => 25,
        ]);

        $this->artisan('e2e:fixture', ['state' => 'settings-admin'])->assertSuccessful();
        $this->assertDatabaseCount('events', 0);
        $this->assertSame(0, User::query()->where('role', 'alumni')->count());

        $admin = User::query()->where('email', config('e2e.admin.email'))->firstOrFail();
        $this->assertSame('super_admin', $admin->admin_level);
        $this->assertSame('active', $admin->status);
        $this->assertNull($admin->avatar_url);
        $this->assertTrue(Hash::check((string) config('e2e.admin.password'), $admin->password));
    }

    public function test_phase_ten_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(9);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'alumni-events-list'])->assertSuccessful();
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('event_registrations', 0);

        $this->artisan('e2e:fixture', ['state' => 'alumni-events-empty'])->assertSuccessful();
        $this->assertDatabaseCount('events', 0);

        $this->artisan('e2e:fixture', ['state' => 'alumni-events-filters'])->assertSuccessful();
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('categories', 2);

        $this->artisan('e2e:fixture', ['state' => 'alumni-event-unregistered'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 0);

        $this->artisan('e2e:fixture', ['state' => 'alumni-event-registered'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 1);

        $this->artisan('e2e:fixture', ['state' => 'alumni-event-quota-register'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 1);

        $this->artisan('e2e:fixture', ['state' => 'alumni-event-quota-full'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 2);

        $this->artisan('e2e:fixture', ['state' => 'alumni-event-quota-cancel'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 2);
        $this->assertDatabaseHas('event_registrations', [
            'user_id' => User::query()->where('email', config('e2e.alumni.email'))->value('id'),
            'status' => 'registered',
        ]);
    }

    public function test_phase_eleven_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(10);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'scan-base'])->assertSuccessful();
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'scan-valid'])->assertSuccessful();
        $this->assertDatabaseCount('event_qr_codes', 1);
        $this->assertDatabaseCount('event_registrations', 1);
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'scan-event-not-started'])->assertSuccessful();
        $this->assertTrue(Event::query()->firstOrFail()->event_date->isTomorrow());

        $this->artisan('e2e:fixture', ['state' => 'scan-event-ended'])->assertSuccessful();
        $this->assertTrue(Event::query()->firstOrFail()->event_date->isYesterday());

        $this->artisan('e2e:fixture', ['state' => 'scan-unregistered'])->assertSuccessful();
        $this->assertDatabaseCount('event_registrations', 0);

        $this->artisan('e2e:fixture', ['state' => 'scan-already-attended'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 1);
        $this->assertDatabaseHas('event_registrations', ['status' => 'attended']);

        $this->artisan('e2e:fixture', ['state' => 'scan-qr-before-valid'])->assertSuccessful();
        $this->assertTrue(EventQrCode::query()->firstOrFail()->valid_from->isFuture());

        $this->artisan('e2e:fixture', ['state' => 'scan-qr-valid-window'])->assertSuccessful();
        $this->assertTrue(EventQrCode::query()->firstOrFail()->is_valid_now);

        $this->artisan('e2e:fixture', ['state' => 'scan-qr-expired'])->assertSuccessful();
        $this->assertTrue(EventQrCode::query()->firstOrFail()->is_expired);
    }

    public function test_phase_twelve_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(11);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'history-empty'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 0);

        $this->artisan('e2e:fixture', ['state' => 'history-populated'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 2);

        $this->artisan('e2e:fixture', ['state' => 'history-detail'])->assertSuccessful();
        $this->assertDatabaseHas('events', [
            'event_title' => 'E2E History Detail Event',
            'location' => 'Aula Riwayat E2E',
        ]);
        $this->assertDatabaseCount('presensis', 1);

        $this->artisan('e2e:fixture', ['state' => 'history-after-scan'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 0);
        $this->assertDatabaseCount('event_qr_codes', 1);

        $this->artisan('e2e:fixture', ['state' => 'recommendation-single-category'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 2);

        $this->artisan('e2e:fixture', ['state' => 'recommendation-new-user'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 0);
        $this->assertDatabaseCount('events', 2);

        $this->artisan('e2e:fixture', ['state' => 'recommendation-active-match'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 2);

        $this->artisan('e2e:fixture', ['state' => 'recommendation-no-active-match'])->assertSuccessful();
        $this->assertDatabaseHas('events', ['event_title' => 'E2E Fallback Reuni']);

        $this->artisan('e2e:fixture', ['state' => 'recommendation-inactive-past'])->assertSuccessful();
        $this->assertDatabaseHas('events', [
            'event_title' => 'E2E Inactive Matching Event',
            'status_event' => 'inactive',
        ]);

        $this->artisan('e2e:fixture', ['state' => 'recommendation-dominant-category'])->assertSuccessful();
        $this->assertDatabaseCount('presensis', 5);
        $this->assertDatabaseHas('events', ['event_title' => 'E2E Dominant Seminar Recommendation']);
        $this->assertDatabaseHas('events', ['event_title' => 'E2E Secondary Reuni Recommendation']);
    }

    public function test_phase_thirteen_fixtures_are_deterministic_and_isolated(): void
    {
        $guard = $this->mock(E2EEnvironmentGuard::class);
        $guard->shouldReceive('assertSafe')->times(7);

        $this->seed(E2EDatabaseSeeder::class);

        $this->artisan('e2e:fixture', ['state' => 'notifications-mixed'])->assertSuccessful();
        $target = User::query()->where('email', PhaseThirteenFixtureManager::ALUMNI_EMAIL)->firstOrFail();
        $this->assertSame(2, $target->alumniNotifications()->where('is_read', false)->count());
        $this->assertSame(2, $target->alumniNotifications()->where('is_read', true)->count());
        $otherAlumni = User::query()->where('email', 'phase13.other@example.test')->firstOrFail();
        $this->assertDatabaseHas('alumni_notifications', [
            'user_id' => $otherAlumni->id,
            'title' => 'E2E Other User Notification',
            'is_read' => false,
        ]);

        $this->artisan('e2e:fixture', ['state' => 'notifications-refresh'])->assertSuccessful();
        $this->artisan('e2e:fixture', ['state' => 'notifications-refresh-add'])->assertSuccessful();
        $target = User::query()->where('email', PhaseThirteenFixtureManager::ALUMNI_EMAIL)->firstOrFail();
        $this->assertSame(2, AlumniNotification::query()->where('user_id', $target->id)->count());
        $this->assertDatabaseHas('alumni_notifications', ['title' => 'E2E Refresh Notification']);

        $this->artisan('e2e:fixture', ['state' => 'profile-avatar'])->assertSuccessful();
        $this->artisan('e2e:fixture', ['state' => 'profile-avatar'])->assertSuccessful();
        $target = User::query()->where('email', PhaseThirteenFixtureManager::ALUMNI_EMAIL)->firstOrFail();
        $this->assertSame('/storage/avatars/'.PhaseThirteenFixtureManager::AVATAR_FILENAME, $target->avatar_url);
        $this->assertSame('Jl. E2E Phase 13 No. 13', $target->domicile()->value('address'));
        $this->assertSame(1, User::query()->where('role', 'alumni')->count());

        $this->artisan('e2e:fixture', ['state' => 'profile-no-avatar'])->assertSuccessful();
        $target = User::query()->where('email', PhaseThirteenFixtureManager::ALUMNI_EMAIL)->firstOrFail();
        $this->assertNull($target->avatar_url);
        $this->assertSame('40111', $target->domicile()->value('postal_code'));
    }
}
