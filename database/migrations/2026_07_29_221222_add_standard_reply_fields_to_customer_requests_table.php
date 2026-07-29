<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_requests', function (Blueprint $table): void {
            $table->timestamp('standard_reply_sent_at')->nullable()->after('viewed_at');
            $table->string('standard_reply_sent_by')->nullable()->after('standard_reply_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_requests', function (Blueprint $table): void {
            $table->dropColumn(['standard_reply_sent_at', 'standard_reply_sent_by']);
        });
    }
};
