<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Subscribe to a tipster
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'tipster_id' => 'required|exists:users,id',
            'plan_type' => 'required|in:daily,weekly,monthly',
            'price' => 'nullable|numeric',
            'currency' => 'required|string|max:3',
        ]);

        $tipster = User::where('id', $request->tipster_id)
            ->where('role', 'tipster')
            ->firstOrFail();

        // Check if user already has an active subscription to this tipster
        $existingSubscription = Subscription::where('user_id', auth()->id())
            ->where('tipster_id', $request->tipster_id)
            ->active()
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'message' => 'You already have an active subscription to this tipster'
            ], 409);
        }

        // Calculate subscription period
        $startAt = now();
        $endAt = $this->calculateEndDate($request->plan_type, $startAt);

        // Determine commission_config_id
        $commissionConfigId = $request->has('commission_config_id') && $request->commission_config_id
            ? $request->commission_config_id
            : $tipster->commission_config_id;

        $planType = $request->plan_type;
        $price = $request->price;

        if ($planType === 'weekly') {
            if (is_null($tipster->weekly_subscription_amount)) {
                return response()->json([
                    'message' => 'This tipster has not set a weekly subscription amount',
                ], 422);
            }

            $price = $tipster->weekly_subscription_amount;

            if ($price > 10000) {
                return response()->json([
                    'message' => 'Weekly subscription amount exceeds the allowed maximum',
                ], 422);
            }
        } elseif ($planType === 'monthly') {
            if (is_null($tipster->monthly_subscription_amount)) {
                return response()->json([
                    'message' => 'This tipster has not set a monthly subscription amount',
                ], 422);
            }

            $price = $tipster->monthly_subscription_amount;

            if ($price > 40000) {
                return response()->json([
                    'message' => 'Monthly subscription amount exceeds the allowed maximum',
                ], 422);
            }
        }

        if (is_null($price)) {
            return response()->json([
                'message' => 'Subscription amount is required for the selected plan',
            ], 422);
        }

        $subscription = Subscription::create([
            'user_id' => auth()->id(),
            'tipster_id' => $request->tipster_id,
            'plan_type' => $planType,
            'price' => $price,
            'currency' => $request->currency,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'active',
            'commission_config_id' => $commissionConfigId,
        ]);

        return response()->json([
            'message' => 'Subscription created successfully',
            'subscription' => $subscription->load('tipster:id,name'),
        ], 201);
    }

    /**
     * Get user's subscriptions
     */
    public function mySubscriptions()
    {
        $subscriptions = auth()->user()->subscriptions()
            ->with('tipster:id,name')
            ->latest()
            ->paginate(20);

        return response()->json([
            'subscriptions' => $subscriptions
        ]);
    }

    /**
     * Check subscription status
     */
    public function getStatus($id)
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        return response()->json([
            'subscription' => $subscription->load('tipster:id,name'),
            'is_active' => $subscription->isActive(),
            'has_expired' => $subscription->hasExpired(),
        ]);
    }

    /**
     * Calculate end date based on plan type
     */
    private function calculateEndDate($planType, $startDate)
    {
        $startDate = Carbon::parse($startDate);

        switch ($planType) {
            case 'daily':
                return $startDate->addDay();
            case 'weekly':
                return $startDate->addWeek();
            case 'monthly':
                return $startDate->addMonth();
            default:
                return $startDate->addDay();
        }
    }
}
