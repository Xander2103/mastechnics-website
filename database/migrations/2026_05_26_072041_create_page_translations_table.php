<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('page_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('locale', 2);
            $table->string('slug');

            $table->string('title');
            $table->text('intro')->nullable();
            $table->longText('content')->nullable();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->unique(['page_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};