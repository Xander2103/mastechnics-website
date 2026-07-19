<?php

use App\Models\CustomerRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignIdFor(CustomerRequest::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('mailable');
            $table->string('recipient');
            $table->string('subject');
            $table->string('status'); // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
