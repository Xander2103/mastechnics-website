<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')
                  ->constrained('quotes')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price_excl_vat', 10, 2)->default(0.00);
            $table->decimal('vat_rate', 5, 2)->default(21.00);
            $table->decimal('line_total_excl_vat', 10, 2)->default(0.00);
            $table->decimal('line_vat_amount', 10, 2)->default(0.00);
            $table->decimal('line_total_incl_vat', 10, 2)->default(0.00);
            $table->timestamps();

            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
