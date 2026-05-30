<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // source was partially added by a prior failed migration run; skip if it already exists
        if (!Schema::hasColumn('customer_requests', 'source')) {
            Schema::table('customer_requests', function (Blueprint $table) {
                $table->string('source')->default('website')->after('id');
            });
        }

        Schema::table('customer_requests', function (Blueprint $table) {
            $table->string('service_category')->nullable()->after('source');
            $table->string('urgency_level')->nullable()->after('service_category');
            $table->string('preferred_time')->nullable()->after('urgency_level');
            $table->text('customer_message')->nullable()->after('preferred_time');
            $table->text('ai_summary')->nullable()->after('customer_message');
            $table->json('ai_detected_missing_fields')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        $columns = [
            'service_category',
            'urgency_level',
            'preferred_time',
            'customer_message',
            'ai_summary',
            'ai_detected_missing_fields',
        ];

        Schema::table('customer_requests', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });

        if (Schema::hasColumn('customer_requests', 'source')) {
            Schema::table('customer_requests', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
