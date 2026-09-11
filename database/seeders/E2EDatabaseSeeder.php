<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\E2E\E2EEnvironmentGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2EDatabaseSeeder extends Seeder
{
    public function run(E2EEnvironmentGuard $guard): void
    {
        $guard->assertSafe();

        $this->call(CategorySeeder::class);

        User::query()->create([
            'first_name' => 'E2E',
            'last_name' => 'Admin',
            'email' => config('e2e.admin.email'),
            'password' => Hash::make((string) config('e2e.admin.password')),
            'phone' => '080000000001',
            'gender' => 'Laki-laki',
            'role' => 'admin',
            'admin_level' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        User::query()->create([
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
    }
}
