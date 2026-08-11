<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable supplier-file mapping profiles for the guided HVAC import.
        // Stores the mapping recipe only — never the uploaded file itself.
        Schema::create('hvac_mapping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('supplier_name');
            // Optional case-insensitive substring the worksheet name should
            // contain (e.g. "prijslijst"); null = first/only sheet.
            $table->string('worksheet_name_pattern')->nullable();
            // 1-based header row in the worksheet.
            $table->unsignedSmallInteger('header_row')->default(1);
            // normalized source header text => internal field name.
            $table->json('column_map');
            $table->string('decimal_format', 10)->default('auto'); // auto | comma | point
            $table->string('currency_format', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_mapping_profiles');
    }
};
