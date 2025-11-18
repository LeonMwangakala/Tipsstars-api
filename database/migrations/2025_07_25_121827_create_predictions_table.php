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
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipster_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url');
            $table->jsonb('booking_codes')->nullable();
            $table->decimal('odds_total', 10, 2)->nullable();
            $table->timestamp('kickoff_at')->nullable();
            $table->integer('confidence_level')->nullable(); // 1-100
            $table->boolean('is_premium')->default(true);
            $table->enum('status', ['draft', 'published', 'expired'])->default('draft');
            $table->enum('result_status', ['pending', 'won', 'lost', 'void'])->default('pending');
            $table->text('result_notes')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('lock_at')->nullable();
            $table->timestamps();

            $table->foreign('tipster_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
