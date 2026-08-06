<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hvac_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hvac_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hvac_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_supplier_id')->nullable()
                ->constrained('hvac_suppliers')->nullOnDelete();
            $table->foreignId('hvac_brand_id')
                ->constrained('hvac_brands')->restrictOnDelete();
            $table->string('sku')->index();
            $table->string('model');
            $table->string('name');
            $table->string('product_type')->index();
            $table->decimal('cooling_capacity_kw', 6, 2)->nullable()->index();
            $table->decimal('heating_capacity_kw', 6, 2)->nullable();
            $table->decimal('minimum_capacity_kw', 6, 2)->nullable();
            $table->decimal('maximum_capacity_kw', 6, 2)->nullable();
            $table->unsignedInteger('nominal_input_power_w')->nullable();
            $table->string('supported_voltage')->nullable();
            $table->string('supported_phase')->nullable();
            $table->unsignedSmallInteger('required_breaker_a')->nullable();
            $table->string('required_cable')->nullable();
            $table->string('liquid_pipe_diameter')->nullable();
            $table->string('gas_pipe_diameter')->nullable();
            $table->decimal('maximum_pipe_length_m', 6, 1)->nullable();
            $table->decimal('maximum_pipe_length_per_unit_m', 6, 1)->nullable();
            $table->decimal('maximum_height_difference_m', 6, 1)->nullable();
            $table->unsignedTinyInteger('maximum_connected_indoor_units')->nullable();
            $table->decimal('purchase_price_excl_vat', 10, 2)->nullable();
            $table->decimal('default_sale_price_excl_vat', 10, 2)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->decimal('sound_level_db', 5, 1)->nullable();
            $table->boolean('wifi_included')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['hvac_supplier_id', 'sku']);
        });

        Schema::create('hvac_product_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')
                ->constrained('hvac_products')->restrictOnDelete();
            $table->foreignId('compatible_product_id')
                ->constrained('hvac_products')->restrictOnDelete();
            $table->string('compatibility_type')->index();
            $table->decimal('minimum_connected_capacity_kw', 6, 2)->nullable();
            $table->decimal('maximum_connected_capacity_kw', 6, 2)->nullable();
            $table->unsignedTinyInteger('maximum_units')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['parent_product_id', 'compatible_product_id', 'compatibility_type'],
                'hvac_compat_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_product_compatibilities');
        Schema::dropIfExists('hvac_products');
        Schema::dropIfExists('hvac_suppliers');
        Schema::dropIfExists('hvac_brands');
    }
};
