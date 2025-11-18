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
        User::factory()->create([
            'name' => 'Tipster User',
            'phone_number' => '1234567890',
            'role' => 'tipster',
            'password' => bcrypt('password@123')
        ]);

        User::factory()->create([
            'name' => 'Customer User',
            'phone_number' => '0670556695',
            'role' => 'customer',
            'password' => bcrypt('password@123')
        ]);

        User::factory()->create([
            'name' => 'Admin User',
            'phone_number' => '0762000043',
            'role' => 'admin',
            'password' => bcrypt('Pr@y2G0d')
        ]);

        // Seed commission configs
        $this->call([
            CommissionConfigSeeder::class,
            WithdrawalRequestSeeder::class,
        ]);
    }
}
