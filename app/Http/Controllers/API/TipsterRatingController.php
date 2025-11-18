<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TipsterRating;
use App\Models\User;
use Illuminate\Http\Request;

class TipsterRatingController extends Controller
{
    /**
     * Get top-rated tipsters
     */
    public function topRated()
    {
        $topTipsters = TipsterRating::with('tipster:id,name,phone_number')
            ->where('total_predictions', '>=', 5) // Only tipsters with at least 5 predictions
            ->orderBy('rating_score', 'desc')
            ->take(10)
            ->get()
            ->map(function ($rating) {
                return [
                    'tipster' => $rating->tipster,
                    'rating' => [
                        'win_rate' => $rating->win_rate,
                        'total_predictions' => $rating->total_predictions,
                        'star_rating' => $rating->star_rating,
                        'rating_tier' => $rating->rating_tier,
                        'current_streak' => $rating->current_streak,
                        'rating_score' => $rating->rating_score,
                        'subscribers_count' => $rating->subscribers_count,
                    ]
                ];
            });

        return response()->json([
            'top_tipsters' => $topTipsters
        ]);
    }

    /**
     * Get detailed rating for a specific tipster
     */
    public function show($tipsterId)
    {
        $tipster = User::where('role', 'tipster')->findOrFail($tipsterId);
        $rating = TipsterRating::where('tipster_id', $tipsterId)->first();

        if (!$rating) {
            return response()->json([
                'tipster' => $tipster,
                'rating' => null,
                'message' => 'No rating data available yet'
            ]);
        }

        return response()->json([
            'tipster' => $tipster,
            'rating' => [
                'total_predictions' => $rating->total_predictions,
                'won_predictions' => $rating->won_predictions,
                'lost_predictions' => $rating->lost_predictions,
                'void_predictions' => $rating->void_predictions,
                'win_rate' => $rating->win_rate,
                'average_odds' => $rating->average_odds,
                'roi' => $rating->roi,
                'current_streak' => $rating->current_streak,
                'best_win_streak' => $rating->best_win_streak,
                'worst_loss_streak' => $rating->worst_loss_streak,
                'star_rating' => $rating->star_rating,
                'rating_tier' => $rating->rating_tier,
                'rating_score' => $rating->rating_score,
                'predictions_last_30_days' => $rating->predictions_last_30_days,
                'win_rate_last_30_days' => $rating->win_rate_last_30_days,
                'subscribers_count' => $rating->subscribers_count,
                'avg_confidence_level' => $rating->avg_confidence_level,
                'last_calculated_at' => $rating->last_calculated_at,
            ]
        ]);
    }

    /**
     * Update ratings for a specific tipster (admin/tipster only)
     */
    public function update($tipsterId)
    {
        $tipster = User::where('role', 'tipster')->findOrFail($tipsterId);
        
        // Check if user is admin or the tipster themselves
        if (!auth()->user()->isAdmin() && auth()->id() !== $tipster->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rating = TipsterRating::updateRatingsForTipster($tipsterId);

        return response()->json([
            'message' => 'Ratings updated successfully',
            'rating' => $rating
        ]);
    }

    /**
     * Get rating leaderboard with filtering options
     */
    public function leaderboard(Request $request)
    {
        $query = TipsterRating::with('tipster:id,name')
            ->where('total_predictions', '>=', 5);

        // Filter by rating tier if specified
        if ($request->has('tier')) {
            $query->where('rating_tier', $request->tier);
        }

        // Filter by minimum win rate
        if ($request->has('min_win_rate')) {
            $query->where('win_rate', '>=', $request->min_win_rate);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'rating_score');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['rating_score', 'win_rate', 'total_predictions', 'roi', 'subscribers_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $ratings = $query->paginate(20);

        return response()->json([
            'leaderboard' => $ratings->items(),
            'pagination' => [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
            ]
        ]);
    }
}
