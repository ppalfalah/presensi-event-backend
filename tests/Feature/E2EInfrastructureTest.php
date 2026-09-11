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
    }
}
