<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipster_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipster_id')->constrained('users')->onDelete('cascade');
            
            // Prediction statistics
            $table->integer('total_predictions')->default(0);
            $table->integer('won_predictions')->default(0);
            $table->integer('lost_predictions')->default(0);
            $table->integer('void_predictions')->default(0);
            
            // Performance metrics
            $table->decimal('win_rate', 5, 2)->default(0.00); // Percentage (0.00 - 100.00)
            $table->decimal('average_odds', 8, 2)->default(0.00);
            $table->decimal('roi', 8, 2)->default(0.00); // Return on Investment
            
            // Streak tracking
            $table->integer('current_streak')->default(0); // Positive for wins, negative for losses
            $table->integer('best_win_streak')->default(0);
            $table->integer('worst_loss_streak')->default(0);
            
            // Rating scores
            $table->decimal('rating_score', 8, 2)->default(0.00); // Overall rating score (0-100)
            $table->integer('star_rating')->default(0); // 1-5 stars
            $table->string('rating_tier')->default('New Tipster'); // Elite, Expert, Professional, etc.
            
            // Time-based metrics
            $table->integer('predictions_last_30_days')->default(0);
            $table->decimal('win_rate_last_30_days', 5, 2)->default(0.00);
            
            // Additional metrics
            $table->integer('subscribers_count')->default(0);
            $table->decimal('avg_confidence_level', 5, 2)->default(0.00);
            
            // Tracking
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('tipster_id');
            $table->index('rating_score');
            $table->index('win_rate');
            $table->index('total_predictions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipster_ratings');
    }
};
