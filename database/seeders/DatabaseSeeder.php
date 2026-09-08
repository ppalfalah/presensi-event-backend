<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Essential master data & Initial Super Admin configuration
        $this->call([
            CategorySeeder::class,
            AdminSeeder::class,
        ]);

        /*
         |--------------------------------------------------------------------------
         | Dummy / Demo Data
         |--------------------------------------------------------------------------
         | Jika memerlukan data dummy untuk keperluan development atau demo,
         | jalankan perintah berikut secara terpisah:
         |
         | php artisan db:seed --class=DummyDataSeeder
         |
         */
    }
}
