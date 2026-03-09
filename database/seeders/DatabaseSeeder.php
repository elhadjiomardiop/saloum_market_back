<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@mini-market.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'vendor@mini-market.test'],
            [
                'name' => 'Vendor User',
                'password' => Hash::make('password123'),
                'role' => 'vendor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'client@mini-market.test'],
            [
                'name' => 'Client User',
                'password' => Hash::make('password123'),
                'role' => 'client',
            ]
        );
    }
}
