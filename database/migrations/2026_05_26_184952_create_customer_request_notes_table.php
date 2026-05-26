<?php

use App\Models\CustomerRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_request_notes', function (Blueprint $table): void {
            $table->id();

            $table->foreignIdFor(CustomerRequest::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->string('author_email')->nullable();
            $table->text('body');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_request_notes');
    }
};