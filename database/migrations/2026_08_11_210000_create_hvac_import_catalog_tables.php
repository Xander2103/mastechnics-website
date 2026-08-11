<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First-class import catalogs ("Productlijsten"): every guided or
        // template import belongs to one, so the admin browses per supplier
        // list instead of one flat product table.
        Schema::create('hvac_import_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('hvac_supplier_id')->nullable()
                ->constrained('hvac_suppliers')->nullOnDelete();
            $table->string('source_filename')->nullable();
            $table->string('source_type', 20)->default('guided'); // guided | template
            $table->string('imported_by')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->string('status', 20)->default('active')->index(); // active | archived | superseded | failed
            $table->text('notes')->nullable();
            $table->foreignId('hvac_mapping_profile_id')->nullable()
                ->constrained('hvac_mapping_profiles')->nullOnDelete();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
        });

        // A product can appear in several catalogs (yearly lists, updates) —
        // the pivot also keeps the raw source facts per catalog.
        Schema::create('hvac_import_catalog_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_import_catalog_id')
                ->constrained('hvac_import_catalogs')->cascadeOnDelete();
            $table->foreignId('hvac_product_id')
                ->constrained('hvac_products')->restrictOnDelete();
            $table->unsignedInteger('source_row')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->decimal('source_price', 10, 2)->nullable();
            $table->integer('source_stock')->nullable();
            $table->unsignedSmallInteger('source_lead_time')->nullable();
            $table->timestamps();
            $table->unique(['hvac_import_catalog_id', 'hvac_product_id'], 'hvac_catalog_product_unique');
        });

        // Per-catalog import history ("11/08/2026 — 123 created, 0 updated").
        Schema::create('hvac_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hvac_import_catalog_id')
                ->constrained('hvac_import_catalogs')->cascadeOnDelete();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('deactivated_count')->default(0);
            $table->string('imported_by')->nullable();
            $table->string('source_filename')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvac_import_runs');
        Schema::dropIfExists('hvac_import_catalog_product');
        Schema::dropIfExists('hvac_import_catalogs');
    }
};
