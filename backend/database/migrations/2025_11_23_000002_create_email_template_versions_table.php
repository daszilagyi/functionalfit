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
     * Email Template Versions: Version history for email templates.
     * Only the last 2 versions are retained (older versions are pruned).
     */
    public function up(): void
    {
        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->unsignedInteger('version')->comment('Version number at time of snapshot');
            $table->string('subject', 255);
            $table->longText('html_body');
            $table->text('fallback_body')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['email_template_id', 'version'], 'idx_template_versions_lookup');
            $table->unique(['email_template_id', 'version'], 'uniq_template_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_template_versions');
    }
};
