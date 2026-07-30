<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_emails', function (Blueprint $table) {
            $table->id();

            // Stored normalized (trimmed + lowercased) so matching stays
            // case-insensitive; one row per address, re-blocking updates it.
            $table->string('email')->unique();
            $table->string('reason', 500)->nullable();
            $table->string('blocked_by');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_emails');
    }
};
