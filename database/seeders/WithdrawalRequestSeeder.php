<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\Carbon;

class WithdrawalRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get tipster users
        $tipsters = User::where('role', 'tipster')->get();
        $admins = User::where('role', 'admin')->get();

        if ($tipsters->isEmpty()) {
            $this->command->info('No tipsters found. Skipping withdrawal request seeding.');
            return;
        }

        $statuses = ['pending', 'paid', 'rejected', 'cancelled'];
        $amounts = [5000, 10000, 15000, 20000, 25000, 30000];

        foreach ($tipsters as $tipster) {
            // Create 2-5 withdrawal requests per tipster
            $numRequests = rand(2, 5);
            
            for ($i = 0; $i < $numRequests; $i++) {
                $status = $statuses[array_rand($statuses)];
                $amount = $amounts[array_rand($amounts)];
                $createdAt = Carbon::now()->subDays(rand(1, 30));
                
                $withdrawalData = [
                    'tipster_id' => $tipster->id,
                    'amount' => $amount,
                    'status' => $status,
                    'requested_at' => $createdAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                // Add admin_id and paid_at for paid/rejected requests
                if (in_array($status, ['paid', 'rejected']) && !$admins->isEmpty()) {
                    $admin = $admins->random();
                    $withdrawalData['admin_id'] = $admin->id;
                    $withdrawalData['notes'] = $status === 'paid' 
                        ? 'Payment processed successfully' 
                        : 'Request rejected due to insufficient documentation';
                    
                    if ($status === 'paid') {
                        $withdrawalData['paid_at'] = $createdAt->addDays(rand(1, 3));
                    }
                }

                // Add notes for some requests
                if (rand(0, 1) && !isset($withdrawalData['notes'])) {
                    $notes = [
                        'Please process via mobile money',
                        'Bank transfer preferred',
                        'Urgent request',
                        'Regular monthly withdrawal',
                    ];
                    $withdrawalData['notes'] = $notes[array_rand($notes)];
                }

                WithdrawalRequest::create($withdrawalData);
            }
        }

        $this->command->info('Withdrawal requests seeded successfully!');
    }
} 