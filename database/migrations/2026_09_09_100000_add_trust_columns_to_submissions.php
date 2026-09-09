<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trust verdict of the pre-mail trust gate (sprint 21). A submission the
     * evaluator is not sure about is stored with trust_verdict =
     * needs_review and causes no mail until an admin releases it. Existing
     * rows are trusted by default. The workflow `status` of a request is a
     * separate dimension and is not touched.
     */
    public function up(): void
    {
        foreach (['customer_requests', 'contact_submissions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->string('trust_verdict', 20)->default('trusted')->after('locale')->index("{$table}_trust_verdict_index");
                $blueprint->unsignedSmallInteger('trust_score')->nullable()->after('trust_verdict');
                $blueprint->json('trust_reasons')->nullable()->after('trust_score');
                $blueprint->timestamp('trust_reviewed_at')->nullable()->after('trust_reasons');
                $blueprint->string('trust_reviewed_by')->nullable()->after('trust_reviewed_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['customer_requests', 'contact_submissions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex("{$table}_trust_verdict_index");
                $blueprint->dropColumn(['trust_verdict', 'trust_score', 'trust_reasons', 'trust_reviewed_at', 'trust_reviewed_by']);
            });
        }
    }
};
