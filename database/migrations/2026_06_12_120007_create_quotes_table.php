<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_request_id')
                  ->unique()
                  ->constrained('customer_requests')
                  ->cascadeOnDelete();
            $table->string('quote_number', 20)->unique()->nullable();
            $table->string('quote_status', 20)->default('draft');
            $table->string('title', 200)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount_excl_vat', 10, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->default(21.00);
            $table->decimal('amount_vat', 10, 2)->nullable();
            $table->decimal('amount_incl_vat', 10, 2)->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
