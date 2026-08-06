<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hvac_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_recommendation_id')->nullable()
                ->constrained('hvac_recommendations')->nullOnDelete();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('input_hash')->index();
            $table->json('output')->nullable();
            $table->string('validation_status'); // valid / rejected / provider_error
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_ai_logs');
    }
};
