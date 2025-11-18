<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class TipsterPublicController extends Controller
{
    /**
     * List all tipsters with basic info
     */
    public function listTipsters()
    {
        $tipsters = User::where('role', 'tipster')
            ->withCount('predictions')
            ->with([
                'predictions' => function ($query) {
                    $query->published()->latest()->take(3);
                },
                'tipsterRating'
            ])
            ->get()
            ->map(function ($tipster) {
                $rating = $tipster->tipsterRating;
                
                return [
                    'id' => $tipster->id,
                    'name' => $tipster->name,
                    'predictions_count' => $tipster->predictions_count,
                    'recent_predictions' => $tipster->predictions,
                    'rating' => $rating ? [
                        'win_rate' => $rating->win_rate,
                        'total_predictions' => $rating->total_predictions,
                        'star_rating' => $rating->star_rating,
                        'rating_tier' => $rating->rating_tier,
                        'current_streak' => $rating->current_streak,
                        'subscribers_count' => $rating->subscribers_count,
                        'rating_score' => $rating->rating_score,
                    ] : null
                ];
            });

        // Sort by rating score (highest first)
        $tipsters = $tipsters->sortByDesc(function ($tipster) {
            return $tipster['rating']['rating_score'] ?? 0;
        })->values();

        return response()->json([
            'tipsters' => $tipsters
        ]);
    }

    /**
     * Get predictions from a specific tipster
     */
    public function getTipsterPredictions($tipsterId)
    {
        $tipster = User::where('role', 'tipster')->findOrFail($tipsterId);
        $user = auth()->user();

        // Check if user has active subscription to this tipster
        $hasActiveSubscription = false;
        if ($user) {
            $hasActiveSubscription = Subscription::where('user_id', $user->id)
                ->where('tipster_id', $tipsterId)
                ->active()
                ->exists();
        }

        $query = $tipster->predictions()->published();

        // If user doesn't have subscription, only show free predictions
        if (!$hasActiveSubscription) {
            $query->free();
        }

        $predictions = $query->with('tipster:id,name')
            ->latest()
            ->paginate(20);

        return response()->json([
            'tipster' => [
                'id' => $tipster->id,
                'name' => $tipster->name,
            ],
            'has_subscription' => $hasActiveSubscription,
            'predictions' => $predictions,
        ]);
    }

    /**
     * View single prediction if permitted
     */
    public function showPrediction($id)
    {
        $prediction = Prediction::with('tipster:id,name')->findOrFail($id);
        $user = auth()->user();

        // If it's a premium prediction, check subscription
        if ($prediction->is_premium) {
            if (!$user) {
                return response()->json(['message' => 'Authentication required to view premium prediction'], 401);
            }

            $hasActiveSubscription = Subscription::where('user_id', $user->id)
                ->where('tipster_id', $prediction->tipster_id)
                ->active()
                ->exists();

            if (!$hasActiveSubscription) {
                return response()->json(['message' => 'Active subscription required to view this premium prediction'], 403);
            }
        }

        return response()->json([
            'prediction' => $prediction
        ]);
    }
}
