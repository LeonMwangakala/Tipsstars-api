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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('weekly_subscription_amount', 10, 2)->nullable()->after('commission_config_id');
            $table->decimal('monthly_subscription_amount', 10, 2)->nullable()->after('weekly_subscription_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_subscription_amount', 'monthly_subscription_amount']);
        });
    }
};

