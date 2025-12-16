<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Email Logs: Audit trail for all sent emails.
     *
     * Status flow: queued -> sent | failed
     * Retry logic: Max 3 attempts with exponential backoff.
     *
     * NO SOFT DELETES: Retain full email history for audit compliance.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            // Recipient (stored for audit even if user is deleted)
            $table->string('recipient_email', 255)->comment('Email address the message was sent to');

            // Template reference (slug, not FK - template may be deleted/changed)
            $table->string('template_slug', 100)->comment('Email template key used');

            // Rendered content
            $table->string('subject', 255)->comment('Rendered subject line');
            $table->json('payload')->nullable()->comment('Variables passed to template');

            // Delivery status
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->text('error_message')->nullable()->comment('Error details when status=failed');
            $table->timestamp('sent_at')->nullable()->comment('When email was delivered');
            $table->unsignedTinyInteger('attempts')->default(0)->comment('Number of send attempts (max 3)');

            // Audit fields
            $table->timestamps();

            // Performance indexes
            $table->index('recipient_email', 'idx_email_logs_recipient');
            $table->index('template_slug', 'idx_email_logs_template');
            $table->index(['status', 'created_at'], 'idx_email_logs_status');
            $table->index('sent_at', 'idx_email_logs_sent_at');
            $table->index(['status', 'attempts', 'created_at'], 'idx_email_logs_retry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
