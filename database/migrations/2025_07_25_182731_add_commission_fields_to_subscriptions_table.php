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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(0.00); // Commission rate applied
            $table->decimal('commission_amount', 10, 2)->default(0.00); // Actual commission amount
            $table->decimal('tipster_earnings', 10, 2)->default(0.00); // Amount tipster receives
            $table->foreignId('commission_config_id')->nullable()->constrained('commission_configs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['commission_config_id']);
            $table->dropColumn(['commission_rate', 'commission_amount', 'tipster_earnings', 'commission_config_id']);
        });
    }
};
