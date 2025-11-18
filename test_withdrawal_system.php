<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\CommissionConfig;

// Test the withdrawal system
echo "=== Testing Tipster Earnings Withdrawal System ===\n\n";

// 1. Test tipster earnings calculation
echo "1. Testing tipster earnings calculation...\n";
$tipster = User::where('role', 'tipster')->first();
if ($tipster) {
    $totalEarnings = $tipster->getTotalEarnings();
    $availableBalance = $tipster->getAvailableBalance();
    $minLimit = config('app.min_withdrawal_limit');
    
    echo "   Tipster: {$tipster->name}\n";
    echo "   Total Earnings: {$totalEarnings} TZS\n";
    echo "   Available Balance: {$availableBalance} TZS\n";
    echo "   Min Withdrawal Limit: {$minLimit} TZS\n";
    echo "   Can request 5000 TZS: " . ($tipster->canRequestWithdrawal(5000) ? 'Yes' : 'No') . "\n";
    echo "   Can request 500 TZS: " . ($tipster->canRequestWithdrawal(500) ? 'Yes' : 'No') . "\n\n";
} else {
    echo "   No tipster found!\n\n";
}

// 2. Test withdrawal request creation
echo "2. Testing withdrawal request creation...\n";
if ($tipster && $tipster->canRequestWithdrawal(5000)) {
    try {
        $withdrawal = WithdrawalRequest::create([
            'tipster_id' => $tipster->id,
            'amount' => 5000,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        echo "   Created withdrawal request ID: {$withdrawal->id}\n";
        echo "   Amount: {$withdrawal->amount} TZS\n";
        echo "   Status: {$withdrawal->status}\n\n";
    } catch (Exception $e) {
        echo "   Error creating withdrawal: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "   Cannot create withdrawal request (insufficient balance or no tipster)\n\n";
}

// 3. Test withdrawal statistics
echo "3. Testing withdrawal statistics...\n";
$stats = [
    'total_requests' => WithdrawalRequest::count(),
    'pending_requests' => WithdrawalRequest::where('status', 'pending')->count(),
    'paid_requests' => WithdrawalRequest::where('status', 'paid')->count(),
    'rejected_requests' => WithdrawalRequest::where('status', 'rejected')->count(),
    'cancelled_requests' => WithdrawalRequest::where('status', 'cancelled')->count(),
    'total_amount_requested' => WithdrawalRequest::sum('amount'),
    'total_amount_pending' => WithdrawalRequest::where('status', 'pending')->sum('amount'),
];

foreach ($stats as $key => $value) {
    echo "   {$key}: {$value}\n";
}
echo "\n";

// 4. Test withdrawal request processing
echo "4. Testing withdrawal request processing...\n";
$pendingWithdrawal = WithdrawalRequest::where('status', 'pending')->first();
if ($pendingWithdrawal) {
    $admin = User::where('role', 'admin')->first();
    if ($admin) {
        // Mark as paid
        $pendingWithdrawal->markAsPaid($admin->id, 'Payment processed via mobile money');
        echo "   Marked withdrawal {$pendingWithdrawal->id} as paid\n";
        echo "   Processed by: {$admin->name}\n";
        echo "   Paid at: {$pendingWithdrawal->paid_at}\n\n";
    } else {
        echo "   No admin found to process withdrawal\n\n";
    }
} else {
    echo "   No pending withdrawal requests found\n\n";
}

// 5. Test API endpoints (simulate)
echo "5. Testing API endpoints (simulation)...\n";
echo "   GET /api/withdrawals - Tipster can view their withdrawal requests\n";
echo "   POST /api/withdrawals - Tipster can create withdrawal request\n";
echo "   GET /admin/withdrawals - Admin can view all withdrawal requests\n";
echo "   PATCH /admin/withdrawals/{id}/mark-paid - Admin can mark as paid\n";
echo "   PATCH /admin/withdrawals/{id}/reject - Admin can reject request\n";
echo "   GET /admin/withdrawal-stats - Admin can view withdrawal statistics\n\n";

echo "=== Withdrawal System Test Complete ===\n";
echo "To test the full system:\n";
echo "1. Run: php artisan serve\n";
echo "2. Test tipster endpoints with tipster token\n";
echo "3. Test admin endpoints with admin token\n";
echo "4. Check the admin panel at: http://localhost:3000/withdrawals\n"; 