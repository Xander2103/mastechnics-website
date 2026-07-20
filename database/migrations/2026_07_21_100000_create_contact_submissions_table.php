<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side idempotency for the public contact form. A fresh random
     * token is rendered into a hidden field on every GET of the contact
     * page; ContactController inserts it here before sending any mail. The
     * unique index on `token` is what actually prevents a duplicate send —
     * a double-click, a page refresh that resubmits the POST, or any other
     * retry of the exact same form all carry the same token, so the second
     * insert attempt fails and no second pair of emails goes out.
     */
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('token')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('locale', 2);
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
