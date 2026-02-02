<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;
use App\Models\EmailTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Seeds the daily schedule notification settings and email template.
     */
    public function up(): void
    {
        // Add setting for notification hour (default: 7 AM)
        Setting::set('daily_schedule_notification_hour', 7);

        // Create email template for daily schedule
        EmailTemplate::create([
            'slug' => 'daily-schedule',
            'subject' => 'Mai programod - {{date}}',
            'html_body' => $this->getHtmlTemplate(),
            'fallback_body' => $this->getTextTemplate(),
            'is_active' => true,
            'version' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::forget('daily_schedule_notification_hour');
        EmailTemplate::where('slug', 'daily-schedule')->forceDelete();
    }

    /**
     * Get HTML email template
     */
    private function getHtmlTemplate(): string
    {
        return <<<'HTML'
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #333; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">
        Mai programod
    </h1>

    <p style="font-size: 16px; color: #555;">
        Kedves <strong>{{trainer.name}}</strong>!
    </p>

    <p style="font-size: 14px; color: #666;">
        Íme a mai ({{date}}) beosztásod:
    </p>

    <div style="background: #f8fafc; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; font-size: 14px;">
            <strong>Összes esemény:</strong> {{events_count}} db<br>
            <strong>Személyi edzések:</strong> {{individual_count}} db<br>
            <strong>Csoportos órák:</strong> {{group_count}} db
        </p>
    </div>

    {{events_table}}

    <p style="font-size: 12px; color: #888; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;">
        Ez egy automatikus értesítés a FunctionalFit rendszerből.<br>
        Ha nem szeretnél több ilyen értesítést kapni, kérd az adminisztrátort a beállítás módosítására.
    </p>
</div>
HTML;
    }

    /**
     * Get plain text email template
     */
    private function getTextTemplate(): string
    {
        return <<<'TEXT'
Mai programod - {{date}}

Kedves {{trainer.name}}!

Íme a mai beosztásod:

Összes esemény: {{events_count}} db
Személyi edzések: {{individual_count}} db
Csoportos órák: {{group_count}} db

{{events_list}}

---
Ez egy automatikus értesítés a FunctionalFit rendszerből.
TEXT;
    }
};
