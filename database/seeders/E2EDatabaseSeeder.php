<?php

namespace Database\Seeders;

use App\Models\Region;
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

        Region::query()->insert([
            ['code' => '32', 'name' => 'Jawa Barat', 'type' => 'province', 'parent_code' => null, 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '32.73', 'name' => 'Kota Bandung', 'type' => 'city', 'parent_code' => '32', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '32.73.01', 'name' => 'Sukasari', 'type' => 'district', 'parent_code' => '32.73', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '32.73.01.1001', 'name' => 'Isola', 'type' => 'village', 'parent_code' => '32.73.01', 'postal_code' => '40154', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '33', 'name' => 'Jawa Tengah', 'type' => 'province', 'parent_code' => null, 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '33.74', 'name' => 'Kota Semarang', 'type' => 'city', 'parent_code' => '33', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '33.74.04', 'name' => 'Tembalang', 'type' => 'district', 'parent_code' => '33.74', 'postal_code' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '33.74.04.1001', 'name' => 'Bulusan', 'type' => 'village', 'parent_code' => '33.74.04', 'postal_code' => '50277', 'created_at' => now(), 'updated_at' => now()],
        ]);

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
