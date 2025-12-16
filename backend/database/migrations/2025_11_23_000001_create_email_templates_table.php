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
     * Email Templates: Customizable email templates with placeholder support.
     *
     * Template keys: registration_confirmation, password_reset, booking_confirmation, etc.
     * Placeholders: {{user.name}}, {{class.title}}, {{event.date}}, etc.
     *
     * Versioning: Changes trigger version archival to email_template_versions.
     * Last 2 versions are recoverable.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();

            // Template identifier
            $table->string('slug', 100)->unique()->comment('Unique template identifier: registration_confirmation, etc.');

            // Email content
            $table->string('subject', 255)->comment('Subject line with {{var.name}} placeholders');
            $table->longText('html_body')->comment('HTML body with {{var.name}} placeholders');
            $table->text('fallback_body')->nullable()->comment('Plain text fallback body');

            // Version tracking
            $table->unsignedInteger('version')->default(1)->comment('Current template version');
            $table->boolean('is_active')->default(true)->comment('Template availability flag');

            // Audit fields
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index(['is_active', 'deleted_at'], 'idx_email_templates_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
