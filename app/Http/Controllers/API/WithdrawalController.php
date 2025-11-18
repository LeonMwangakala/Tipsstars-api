<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    /**
     * Get tipster's withdrawal requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isTipster()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $withdrawals = $user->withdrawalRequests()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
            'earnings_summary' => [
                'total_earnings' => $user->getTotalEarnings(),
                'available_balance' => $user->getAvailableBalance(),
                'min_withdrawal_limit' => config('app.min_withdrawal_limit'),
            ]
        ]);
    }

    /**
     * Create a new withdrawal request
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isTipster()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = $request->amount;

        // Check if user can request withdrawal
        if (!$user->canRequestWithdrawal($amount)) {
            $availableBalance = $user->getAvailableBalance();
            $minLimit = config('app.min_withdrawal_limit');
            
            return response()->json([
                'message' => "Cannot request withdrawal. Available balance: {$availableBalance}, Minimum limit: {$minLimit}",
                'available_balance' => $availableBalance,
                'min_withdrawal_limit' => $minLimit,
            ], 400);
        }

        try {
            DB::beginTransaction();

            $withdrawal = WithdrawalRequest::create([
                'tipster_id' => $user->id,
                'amount' => $amount,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            DB::commit();

            Log::info('Withdrawal request created', [
                'tipster_id' => $user->id,
                'amount' => $amount,
                'request_id' => $withdrawal->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request created successfully',
                'data' => $withdrawal->load('tipster'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create withdrawal request', [
                'tipster_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create withdrawal request',
            ], 500);
        }
    }

    /**
     * Get withdrawal request details
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isTipster()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $withdrawal = $user->withdrawalRequests()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $withdrawal->load('tipster', 'admin'),
        ]);
    }

    /**
     * Cancel a pending withdrawal request
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isTipster()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $withdrawal = $user->withdrawalRequests()->findOrFail($id);

        if (!$withdrawal->isPending()) {
            return response()->json([
                'message' => 'Only pending withdrawal requests can be cancelled',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $withdrawal->update([
                'status' => 'cancelled',
                'notes' => 'Cancelled by tipster',
            ]);

            DB::commit();

            Log::info('Withdrawal request cancelled', [
                'tipster_id' => $user->id,
                'request_id' => $withdrawal->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request cancelled successfully',
                'data' => $withdrawal,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel withdrawal request', [
                'tipster_id' => $user->id,
                'request_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to cancel withdrawal request',
            ], 500);
        }
    }
} 