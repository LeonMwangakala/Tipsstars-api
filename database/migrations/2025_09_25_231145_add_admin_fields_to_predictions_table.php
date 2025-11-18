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
        Schema::table('predictions', function (Blueprint $table) {
            $table->boolean('created_by_admin')->default(false)->after('result_updated_by');
            $table->unsignedBigInteger('admin_id')->nullable()->after('created_by_admin');
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn(['created_by_admin', 'admin_id']);
        });
    }
};
