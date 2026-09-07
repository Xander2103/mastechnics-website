<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side idempotency for the request wizard, mirroring the contact
     * form: a fresh random token is rendered into a hidden field on every
     * GET of the request page and stored here on submit. The unique index
     * is what actually prevents a double-click, refresh-resubmit or client
     * retry from creating a second request and a second pair of e-mails.
     * Nullable so requests that arrive without a token (older forms, API
     * clients) keep working exactly as before.
     */
    public function up(): void
    {
        Schema::table('customer_requests', function (Blueprint $table): void {
            $table->string('submission_token', 64)->nullable()->unique()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });
    }
};
