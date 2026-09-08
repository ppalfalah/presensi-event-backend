<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL', 'admin@pesantren.com');
        $password = env('INITIAL_ADMIN_PASSWORD', 'admin123');
        $firstName = env('INITIAL_ADMIN_FIRST_NAME', 'Admin');
        $lastName = env('INITIAL_ADMIN_LAST_NAME', 'Pesantren');
        $phone = env('INITIAL_ADMIN_PHONE', '081234567890');
        $gender = env('INITIAL_ADMIN_GENDER', 'Laki-laki');

        User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make($password),
                'phone' => $phone,
                'gender' => $gender,
                'role' => 'admin',
                'admin_level' => 'super_admin',
                'status' => 'active',
            ]
        );

        $this->command->info("Initial Super Admin ({$email}) seeded successfully!");
        if ($password === 'admin123') {
            $this->command->warn('⚠️  SECURITY WARNING: Using default initial admin password. Please change the password immediately via the admin portal or set INITIAL_ADMIN_PASSWORD in your .env file.');
        }
    }
}
