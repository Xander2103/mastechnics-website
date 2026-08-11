<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive: wizard v2 settings so a saved profile can replay the whole
        // import (delimiter, category selection, price meaning, type fallback)
        // and be auto-recognized by the file's header signature.
        Schema::table('hvac_mapping_profiles', function (Blueprint $table) {
            $table->string('delimiter', 8)->nullable()->after('decimal_format');
            // ['column_header' => string, 'selected' => string[]]
            $table->json('category_filter')->nullable()->after('delimiter');
            // ['column_header' => string, 'meaning' => gross|net_purchase|sale|unknown]
            $table->json('price_semantics')->nullable()->after('category_filter');
            // normalized header names of the source file, for auto-recognition
            $table->json('source_headers')->nullable()->after('price_semantics');
            $table->string('type_fallback')->nullable()->after('source_headers');
        });
    }

    public function down(): void
    {
        Schema::table('hvac_mapping_profiles', function (Blueprint $table) {
            $table->dropColumn(['delimiter', 'category_filter', 'price_semantics', 'source_headers', 'type_fallback']);
        });
    }
};
