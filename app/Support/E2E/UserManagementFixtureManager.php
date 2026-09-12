<?php

namespace App\Support\E2E;

use App\Models\Event;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementFixtureManager
{
    public const STATES = [
        'users-empty',
        'users-filter',
        'users-editable',
        'users-deletable',
        'users-pagination-10',
        'users-pagination-11',
    ];

    public function prepare(string $state): array
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Unknown user-management fixture state: {$state}");
        }

        return DB::transaction(function () use ($state): array {
            $this->resetUsersDomain();
            $this->ensureRegions();

            match ($state) {
                'users-empty' => null,
                'users-filter' => $this->createFilterDataset(),
                'users-editable' => $this->createAlumni('Editable', 'Alumni', 'e2e.editable@example.test', 'active', '080000000021', true),
                'users-deletable' => $this->createAlumni('Disposable', 'Alumni', 'e2e.disposable@example.test', 'active', '080000000022', true),
                'users-pagination-10' => $this->createPaginationDataset(10),
                'users-pagination-11' => $this->createPaginationDataset(11),
            };

            return $this->summary($state);
        });
    }

    private function resetUsersDomain(): void
    {
        Event::query()->delete();

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
    }

    private function ensureRegions(): void
    {
        foreach ([
            ['code' => '32', 'name' => 'Jawa Barat', 'type' => 'province', 'parent_code' => null, 'postal_code' => null],
            ['code' => '32.73', 'name' => 'Kota Bandung', 'type' => 'city', 'parent_code' => '32', 'postal_code' => null],
            ['code' => '32.73.01', 'name' => 'Sukasari', 'type' => 'district', 'parent_code' => '32.73', 'postal_code' => null],
            ['code' => '32.73.01.1001', 'name' => 'Isola', 'type' => 'village', 'parent_code' => '32.73.01', 'postal_code' => '40154'],
            ['code' => '33', 'name' => 'Jawa Tengah', 'type' => 'province', 'parent_code' => null, 'postal_code' => null],
            ['code' => '33.74', 'name' => 'Kota Semarang', 'type' => 'city', 'parent_code' => '33', 'postal_code' => null],
            ['code' => '33.74.04', 'name' => 'Tembalang', 'type' => 'district', 'parent_code' => '33.74', 'postal_code' => null],
            ['code' => '33.74.04.1001', 'name' => 'Bulusan', 'type' => 'village', 'parent_code' => '33.74.04', 'postal_code' => '50277'],
        ] as $region) {
            Region::query()->updateOrCreate(['code' => $region['code']], $region);
        }
    }

    private function createFilterDataset(): void
    {
        $this->createAlumni('Alia', 'Andini', 'alia.filter@example.test', 'active', '080000000031', true);
        $this->createAlumni('Bima', 'Basuki', 'bima.filter@example.test', 'pending', '080000000032', true);
        $this->createAlumni('Citra', 'Cahyani', 'citra.filter@example.test', 'inactive', '080000000033', true);
        $this->createAlumni('Damar', 'Darma', 'damar.filter@example.test', 'rejected', '080000000034', true);
        $this->createAlumni('Zaki', 'Zain', 'zaki.filter@example.test', 'active', '080000000035', false);
    }

    private function createPaginationDataset(int $count): void
    {
        foreach (range(1, $count) as $number) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $this->createAlumni(
                'Pagination',
                "Alumni {$suffix}",
                "pagination.{$suffix}@example.test",
                'active',
                '0810000000'.$suffix,
                false
            );
        }
    }

    private function createAlumni(
        string $firstName,
        string $lastName,
        string $email,
        string $status,
        string $phone,
        bool $withDomicile,
    ): User {
        $user = User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make('E2E-Users-2026!'),
            'phone' => $phone,
            'graduation_year' => '2020',
            'birth_date' => '2000-01-01',
            'gender' => 'Laki-laki',
            'role' => 'alumni',
            'admin_level' => null,
            'status' => $status,
            'email_verified_at' => now(),
        ]);

        if ($withDomicile) {
            $user->domicile()->create([
                'province_code' => '32',
                'province_name' => 'Jawa Barat',
                'city_code' => '32.73',
                'city_name' => 'Kota Bandung',
                'district_code' => '32.73.01',
                'district_name' => 'Sukasari',
                'village_code' => '32.73.01.1001',
                'village_name' => 'Isola',
                'postal_code' => '40154',
                'address' => 'Alamat fixture E2E',
            ]);
        }

        return $user;
    }

    private function summary(string $state): array
    {
        return [
            'state' => $state,
            'alumni' => User::query()->where('role', 'alumni')->count(),
            'events' => 0,
            'attendances' => 0,
        ];
    }
}
