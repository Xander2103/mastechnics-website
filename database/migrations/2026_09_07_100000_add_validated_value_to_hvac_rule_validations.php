<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of the rule value at validation time (JSON-encoded). A
     * validation only counts while the rule set still carries that exact
     * value; a draft whose value diverges must be re-validated. Legacy rows
     * (null) keep counting as validated.
     */
    public function up(): void
    {
        Schema::table('hvac_rule_validations', function (Blueprint $table) {
            $table->text('validated_value')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('hvac_rule_validations', function (Blueprint $table) {
            $table->dropColumn('validated_value');
        });
    }
};
