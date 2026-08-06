<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hvac_products', function (Blueprint $table) {
            $table->decimal('seer', 5, 2)->nullable()->after('sound_level_db');
            $table->decimal('scop', 5, 2)->nullable()->after('seer');
        });

        // Per-rule validation administration: which rule of which rule-set
        // version Martin validated, when, and with which note. Historical
        // calculations are never touched by validation records.
        Schema::create('hvac_rule_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_rule_set_id')
                ->constrained('hvac_rule_sets')->cascadeOnDelete();
            $table->string('rule_key');
            $table->string('status')->default('validated'); // validated only, for now
            $table->text('note')->nullable();
            $table->string('validated_by');
            $table->timestamp('validated_at');
            $table->timestamps();

            $table->unique(['hvac_rule_set_id', 'rule_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_rule_validations');
        Schema::table('hvac_products', function (Blueprint $table) {
            $table->dropColumn(['seer', 'scop']);
        });
    }
};
