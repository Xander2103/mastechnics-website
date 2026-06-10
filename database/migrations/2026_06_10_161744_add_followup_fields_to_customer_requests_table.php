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
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('ai_detected_missing_fields');
            $table->timestamp('contacted_at')->nullable()->after('internal_notes');
            $table->timestamp('quote_sent_at')->nullable()->after('contacted_at');
            $table->timestamp('won_at')->nullable()->after('quote_sent_at');
            $table->timestamp('lost_at')->nullable()->after('won_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table) {
            $table->dropColumn(['internal_notes', 'contacted_at', 'quote_sent_at', 'won_at', 'lost_at']);
        });
    }
};
