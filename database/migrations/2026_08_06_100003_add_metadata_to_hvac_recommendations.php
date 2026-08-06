<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hvac_recommendations', function (Blueprint $table) {
            // Full audit context per option: the selector candidate (products,
            // checks, compatibility), labor breakdown and all warnings.
            $table->json('metadata')->nullable()->after('explanation_en');
        });
    }

    public function down(): void
    {
        Schema::table('hvac_recommendations', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
