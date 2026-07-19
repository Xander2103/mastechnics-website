<?php

use App\Models\CustomerRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_request_appointments', function (Blueprint $table): void {
            $table->id();

            $table->foreignIdFor(CustomerRequest::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->date('date');
            $table->time('time')->nullable();
            $table->string('technician')->nullable(); // placeholder, no technician/user model yet
            $table->string('location')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_request_appointments');
    }
};
