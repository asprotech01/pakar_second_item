<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@tilik.test'],
            [
                'name' => 'Admin Tilik',
                'password' => 'admin123',
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'user@tilik.test'],
            [
                'name' => 'User Tilik',
                'password' => 'user123',
                'role' => User::ROLE_USER,
            ]
        );
    }
}
