<?php

namespace Tests\Feature;

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
}
