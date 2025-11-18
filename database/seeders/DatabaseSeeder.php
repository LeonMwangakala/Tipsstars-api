<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create users directly without factory (to avoid Faker dependency in production)
        User::firstOrCreate(
            ['phone_number' => '1234567890'],
            [
                'name' => 'Tipster User',
                'role' => 'tipster',
                'password' => bcrypt('password@123')
            ]
        );

        User::firstOrCreate(
            ['phone_number' => '0670556695'],
            [
                'name' => 'Customer User',
                'role' => 'customer',
                'password' => bcrypt('password@123')
            ]
        );

        User::firstOrCreate(
            ['phone_number' => '0762000043'],
            [
                'name' => 'Admin User',
                'role' => 'admin',
                'password' => bcrypt('Pr@y2G0d')
            ]
        );

        // Seed commission configs
        $this->call([
            CommissionConfigSeeder::class,
            WithdrawalRequestSeeder::class,
        ]);
    }
}
