<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventQrCode;
use App\Models\User;
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
        $this->assertDatabaseCount('events', 35);
        $this->assertDatabaseCount('presensis', 87);

        $this->artisan('e2e:fixture', ['state' => 'engagement-pagination'])->assertSuccessful();
        $this->assertSame(11, User::query()->where('role', 'alumni')->count());
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('presensis', 0);
    }
}
