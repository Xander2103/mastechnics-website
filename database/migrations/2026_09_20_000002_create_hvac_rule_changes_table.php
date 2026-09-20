<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hvac_rule_changes', function (Blueprint $table) {
            $table->id();
            // Rule sets are never deleted (calculations restrict it), so the
            // audit trail lives as long as the version it describes.
            $table->foreignId('hvac_rule_set_id')->constrained('hvac_rule_sets')->restrictOnDelete();
            $table->string('rule_key');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['hvac_rule_set_id', 'rule_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_rule_changes');
    }
};
