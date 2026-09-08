<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the dummy/demo database seeders for local development and testing.
     * 
     * Usage:
     * php artisan db:seed --class=DummyDataSeeder
     */
    public function run(): void
    {
        $this->command->warn('⚠️  Menjalankan Seeder Data Dummy (Hanya untuk Development & Demo)...');

        $this->call([
            AdminSeeder::class,
            CategorySeeder::class,
            AlumniSeeder::class,
            EventSeeder::class,
            UserDomicileSeeder::class,
        ]);

        $this->command->info('✅ Data dummy berhasil ditambahkan!');
    }
}
