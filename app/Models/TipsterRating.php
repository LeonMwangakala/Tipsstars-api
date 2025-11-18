<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TipsterRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipster_id',
        'total_predictions',
        'won_predictions',
        'lost_predictions',
        'void_predictions',
        'win_rate',
        'average_odds',
        'roi',
        'current_streak',
        'best_win_streak',
        'worst_loss_streak',
        'rating_score',
        'star_rating',
        'rating_tier',
        'predictions_last_30_days',
        'win_rate_last_30_days',
        'subscribers_count',
        'avg_confidence_level',
        'last_calculated_at',
    ];

    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];

    /**
     * Get the tipster that owns this rating
     */
    public function tipster()
    {
        return $this->belongsTo(User::class, 'tipster_id');
    }

    /**
     * Calculate and update tipster ratings based on predictions
     */
    public static function updateRatingsForTipster($tipsterId)
    {
        $tipster = User::find($tipsterId);
        
        if (!$tipster || !$tipster->isTipster()) {
            return;
        }

        // Get or create rating record
        $rating = self::firstOrCreate(
            ['tipster_id' => $tipsterId],
            ['last_calculated_at' => now()]
        );

        // Get all graded predictions
        $predictions = $tipster->predictions()
            ->whereIn('result_status', ['won', 'lost', 'void'])
            ->get();

        // Basic statistics
        $totalPredictions = $predictions->count();
        $wonPredictions = $predictions->where('result_status', 'won')->count();
        $lostPredictions = $predictions->where('result_status', 'lost')->count();
        $voidPredictions = $predictions->where('result_status', 'void')->count();
        
        // Calculate win rate (excluding void predictions)
        $gradedPredictions = $wonPredictions + $lostPredictions;
        $winRate = $gradedPredictions > 0 ? ($wonPredictions / $gradedPredictions) * 100 : 0;
        
        // Calculate average odds
        $averageOdds = $predictions->where('odds_total', '>', 0)->avg('odds_total') ?? 0;
        
        // Calculate ROI (simplified)
        $roi = $rating->calculateROI($predictions);
        
        // Calculate streaks
        $currentStreak = $rating->calculateCurrentStreak($tipster);
        $bestWinStreak = $rating->calculateBestWinStreak($tipster);
        $worstLossStreak = $rating->calculateWorstLossStreak($tipster);
        
        // Calculate 30-day metrics
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $recentPredictions = $tipster->predictions()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereIn('result_status', ['won', 'lost', 'void'])
            ->get();
            
        $predictionsLast30Days = $recentPredictions->count();
        $recentWon = $recentPredictions->where('result_status', 'won')->count();
        $recentLost = $recentPredictions->where('result_status', 'lost')->count();
        $recentGraded = $recentWon + $recentLost;
        $winRateLast30Days = $recentGraded > 0 ? ($recentWon / $recentGraded) * 100 : 0;
        
        // Calculate average confidence level
        $avgConfidenceLevel = $predictions->where('confidence_level', '>', 0)->avg('confidence_level') ?? 0;
        
        // Calculate subscribers count
        $subscribersCount = $tipster->tipsterSubscriptions()->where('status', 'active')->count();
        
        // Calculate overall rating score and tier
        $ratingScore = $rating->calculateRatingScore($winRate, $totalPredictions, $averageOdds, $bestWinStreak, $predictionsLast30Days);
        $starRating = $rating->calculateStarRating($ratingScore, $totalPredictions);
        $ratingTier = $rating->calculateRatingTier($ratingScore, $totalPredictions);
        
        // Update the rating record
        $rating->update([
            'total_predictions' => $totalPredictions,
            'won_predictions' => $wonPredictions,
            'lost_predictions' => $lostPredictions,
            'void_predictions' => $voidPredictions,
            'win_rate' => round($winRate, 2),
            'average_odds' => round($averageOdds, 2),
            'roi' => round($roi, 2),
            'current_streak' => $currentStreak,
            'best_win_streak' => $bestWinStreak,
            'worst_loss_streak' => $worstLossStreak,
            'rating_score' => round($ratingScore, 2),
            'star_rating' => $starRating,
            'rating_tier' => $ratingTier,
            'predictions_last_30_days' => $predictionsLast30Days,
            'win_rate_last_30_days' => round($winRateLast30Days, 2),
            'subscribers_count' => $subscribersCount,
            'avg_confidence_level' => round($avgConfidenceLevel, 2),
            'last_calculated_at' => now(),
        ]);

        return $rating;
    }

    /**
     * Calculate ROI (Return on Investment)
     */
    private function calculateROI($predictions)
    {
        $totalStake = $predictions->count() * 100; // Assume 100 units per bet
        $totalReturn = 0;
        
        foreach ($predictions as $prediction) {
            if ($prediction->result_status === 'won' && $prediction->odds_total > 0) {
                $totalReturn += 100 * $prediction->odds_total;
            }
        }
        
        return $totalStake > 0 ? (($totalReturn - $totalStake) / $totalStake) * 100 : 0;
    }

    /**
     * Calculate current streak
     */
    private function calculateCurrentStreak($tipster)
    {
        $recentPredictions = $tipster->predictions()
            ->whereIn('result_status', ['won', 'lost'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $streak = 0;
        $lastResult = null;

        foreach ($recentPredictions as $prediction) {
            if ($lastResult === null) {
                $lastResult = $prediction->result_status;
                $streak = $prediction->result_status === 'won' ? 1 : -1;
            } elseif ($prediction->result_status === $lastResult) {
                $streak = $prediction->result_status === 'won' ? $streak + 1 : $streak - 1;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Calculate best winning streak
     */
    private function calculateBestWinStreak($tipster)
    {
        $predictions = $tipster->predictions()
            ->whereIn('result_status', ['won', 'lost'])
            ->orderBy('created_at', 'asc')
            ->get();

        $maxStreak = 0;
        $currentStreak = 0;

        foreach ($predictions as $prediction) {
            if ($prediction->result_status === 'won') {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $maxStreak;
    }

    /**
     * Calculate worst losing streak
     */
    private function calculateWorstLossStreak($tipster)
    {
        $predictions = $tipster->predictions()
            ->whereIn('result_status', ['won', 'lost'])
            ->orderBy('created_at', 'asc')
            ->get();

        $maxStreak = 0;
        $currentStreak = 0;

        foreach ($predictions as $prediction) {
            if ($prediction->result_status === 'lost') {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $maxStreak;
    }

    /**
     * Calculate overall rating score (0-100)
     */
    private function calculateRatingScore($winRate, $totalPredictions, $averageOdds, $bestStreak, $recentActivity)
    {
        // Base score from win rate (40% weight)
        $winRateScore = $winRate * 0.4;
        
        // Experience bonus (25% weight)
        $experienceScore = min(($totalPredictions / 100) * 25, 25);
        
        // Odds quality bonus (20% weight)
        $oddsScore = min(($averageOdds / 5) * 20, 20);
        
        // Streak bonus (10% weight)
        $streakScore = min(($bestStreak / 10) * 10, 10);
        
        // Activity bonus (5% weight) - recent predictions
        $activityScore = min(($recentActivity / 20) * 5, 5);
        
        return $winRateScore + $experienceScore + $oddsScore + $streakScore + $activityScore;
    }

    /**
     * Calculate star rating (0-5 stars)
     */
    private function calculateStarRating($ratingScore, $totalPredictions)
    {
        if ($totalPredictions < 5) {
            return 0; // New tipster
        }
        
        return max(1, min(5, ceil($ratingScore / 20)));
    }

    /**
     * Calculate rating tier
     */
    private function calculateRatingTier($ratingScore, $totalPredictions)
    {
        if ($totalPredictions < 5) {
            return 'New Tipster';
        }
        
        if ($ratingScore >= 90) return 'Elite';
        if ($ratingScore >= 80) return 'Expert';
        if ($ratingScore >= 70) return 'Professional';
        if ($ratingScore >= 60) return 'Good';
        if ($ratingScore >= 50) return 'Average';
        return 'Beginner';
    }
}
