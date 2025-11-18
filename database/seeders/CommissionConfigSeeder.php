<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CommissionConfig;

class CommissionConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default commission configuration
        CommissionConfig::create([
            'name' => 'default',
            'commission_rate' => 15.00, // 15%
            'description' => 'Default commission rate for all tipsters',
            'is_active' => true,
        ]);

        // Premium tipsters commission (lower rate for high-performing tipsters)
        CommissionConfig::create([
            'name' => 'premium_tipsters',
            'commission_rate' => 10.00, // 10%
            'description' => 'Lower commission rate for premium tipsters with high success rates',
            'is_active' => true,
        ]);

        // New tipsters commission (higher rate for new tipsters)
        CommissionConfig::create([
            'name' => 'new_tipsters',
            'commission_rate' => 20.00, // 20%
            'description' => 'Higher commission rate for new tipsters to encourage growth',
            'is_active' => true,
        ]);

        // Inactive configuration (for testing)
        CommissionConfig::create([
            'name' => 'inactive_config',
            'commission_rate' => 25.00, // 25%
            'description' => 'Inactive commission configuration for testing purposes',
            'is_active' => false,
        ]);
    }
}
