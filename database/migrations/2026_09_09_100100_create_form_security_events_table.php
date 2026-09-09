<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Security log of the public forms: one row per trust decision
     * (trusted / needs_review / blocked) with the signals that led to it and
     * what happened to the mails. Privacy by design: hashed IP, masked
     * e-mail, parsed user-agent family only — never tokens, secrets, raw
     * headers, names or message bodies. Pruned by forms:prune-security-log.
     */
    public function up(): void
    {
        Schema::create('form_security_events', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('form', 20);
            $table->string('decision', 20);
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->string('risk_level', 10)->default('low');
            $table->json('reasons')->nullable();
            $table->json('signals')->nullable();
            $table->string('captcha_status', 30)->nullable();
            $table->boolean('captcha_hostname_ok')->nullable();
            $table->boolean('captcha_action_ok')->nullable();
            $table->string('fill_time_bucket', 20)->nullable();
            $table->unsignedInteger('fill_time_seconds')->nullable();
            $table->boolean('attack_mode')->default(false);
            $table->string('mail_admin', 20)->default('not_applicable');
            $table->string('mail_customer', 20)->default('not_applicable');
            $table->string('mail_reason', 40)->nullable();
            $table->unsignedTinyInteger('mails_prevented')->default(0);
            $table->string('ip_hash', 16)->nullable();
            $table->string('email_masked', 120)->nullable();
            $table->string('email_hash', 16)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('user_agent_family', 60)->nullable();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['form', 'decision', 'occurred_at'], 'form_security_events_form_decision_idx');
            $table->index(['subject_type', 'subject_id'], 'form_security_events_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_security_events');
    }
};
