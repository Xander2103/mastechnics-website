<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hvac_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('status')->default('draft')->index(); // draft / active / archived
            $table->date('effective_from')->nullable();
            $table->json('configuration');
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });

        Schema::create('hvac_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_request_id')
                ->constrained('customer_requests')->cascadeOnDelete();
            $table->foreignId('hvac_rule_set_id')
                ->constrained('hvac_rule_sets')->restrictOnDelete();
            $table->json('normalized_input');
            $table->json('result')->nullable();
            $table->json('warnings')->nullable();
            $table->string('status')->default('calculated')->index(); // calculated / blocked / superseded
            $table->string('calculated_by')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamp('manually_overridden_at')->nullable();
            $table->string('manually_overridden_by')->nullable();
            $table->timestamps();
        });

        Schema::create('hvac_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_calculation_id')
                ->constrained('hvac_calculations')->cascadeOnDelete();
            $table->string('option_type'); // budget / recommended / premium / single
            $table->string('status')->default('draft')->index(); // draft / manual_review / approved / rejected / converted / superseded
            $table->decimal('equipment_total_excl_vat', 10, 2)->default(0);
            $table->decimal('materials_total_excl_vat', 10, 2)->default(0);
            $table->decimal('labor_total_excl_vat', 10, 2)->default(0);
            $table->decimal('travel_total_excl_vat', 10, 2)->default(0);
            $table->decimal('subtotal_excl_vat', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(21);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_incl_vat', 10, 2)->default(0);
            $table->decimal('margin_amount', 10, 2)->nullable();
            $table->decimal('margin_percentage', 6, 2)->nullable();
            $table->text('explanation_nl')->nullable();
            $table->text('explanation_fr')->nullable();
            $table->text('explanation_en')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('converted_quote_id')->nullable()
                ->constrained('quotes')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hvac_recommendation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_recommendation_id')
                ->constrained('hvac_recommendations')->cascadeOnDelete();
            // restrictOnDelete gives DB-level protection: a product referenced
            // by a historical recommendation can never be hard-deleted.
            $table->foreignId('hvac_product_id')->nullable()
                ->constrained('hvac_products')->restrictOnDelete();
            $table->string('item_type'); // equipment / material / labor / travel
            $table->string('sku')->nullable();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('stuk');
            $table->decimal('purchase_unit_price', 10, 2)->nullable();
            $table->decimal('sale_unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('hvac_manual_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_calculation_id')
                ->constrained('hvac_calculations')->cascadeOnDelete();
            $table->string('field');
            $table->text('original_value')->nullable();
            $table->text('overridden_value')->nullable();
            $table->text('reason');
            $table->string('overridden_by');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_manual_overrides');
        Schema::dropIfExists('hvac_recommendation_items');
        Schema::dropIfExists('hvac_recommendations');
        Schema::dropIfExists('hvac_calculations');
        Schema::dropIfExists('hvac_rule_sets');
    }
};
