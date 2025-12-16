<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Notifications: Queued and sent notifications.
     * Channel: email, sms, in_app
     * Status: pending, sent, failed, cancelled
     *
     * Template examples:
     * - booking_confirmation
     * - reminder_24h, reminder_3h
     * - waitlist_available
     * - class_cancelled
     * - monthly_summary
     * - no_show_notification
     *
     * NO SOFT DELETES: Retain full notification history for audit.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Recipient
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Delivery method
            $table->enum('channel', ['email', 'sms', 'in_app']);

            // Template and content
            $table->string('template_key')->comment('booking_confirmation, reminder_24h, etc.');
            $table->json('payload')->comment('Template variables and data');

            // Status tracking
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('scheduled_for')->nullable()->comment('For future scheduled notifications');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable()->comment('Failure reason if status=failed');

            // Audit fields
            $table->timestamps();

            // Indexes
            $table->index(['status', 'scheduled_for'], 'idx_pending_notifications');
            $table->index(['user_id', 'created_at']);
            $table->index('template_key');
            $table->index('channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
