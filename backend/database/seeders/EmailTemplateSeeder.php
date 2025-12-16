<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = $this->getTemplates();

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }

    /**
     * Get all email templates with Hungarian text.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTemplates(): array
    {
        return [
            $this->registrationConfirmation(),
            $this->passwordReset(),
            $this->userDeleted(),
            $this->bookingConfirmation(),
            $this->bookingCancellation(),
            $this->waitlistPromotion(),
            $this->classReminder(),
            $this->classModified(),
            $this->classDeleted(),
        ];
    }

    /**
     * Registration confirmation template.
     */
    private function registrationConfirmation(): array
    {
        return [
            'slug' => 'registration_confirmation',
            'subject' => 'Sikeres regisztracio - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sikeres regisztracio</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background-color: #4F46E5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Udvozoljuk a {{company_name}} csaladban!</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <p>Koszonjuk, hogy regisztralt a {{company_name}} rendszerebe. Fiokja sikeresen letrejott.</p>

        <p>Kerem, erositse meg e-mail cimet az alabbi gombra kattintva:</p>

        <p style="text-align: center;">
            <a href="{{confirm_url}}" class="button">E-mail cim megerositese</a>
        </p>

        <p>Ha a gomb nem mukodik, masolja be az alabbi linket a bongeszojebe:</p>
        <p style="word-break: break-all; font-size: 12px;">{{confirm_url}}</p>

        <p>A megerosito link 24 oraig ervenyes.</p>

        <p>Ha nem On hozta letre ezt a fiokot, kerem, hagyja figyelmen kivul ezt az uzenetet.</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

Koszonjuk, hogy regisztralt a {{company_name}} rendszerebe. Fiokja sikeresen letrejott.

Kerem, erositse meg e-mail cimet az alabbi linkre kattintva:
{{confirm_url}}

A megerosito link 24 oraig ervenyes.

Ha nem On hozta letre ezt a fiokot, kerem, hagyja figyelmen kivul ezt az uzenetet.

Udvozlettel,
{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Password reset template.
     */
    private function passwordReset(): array
    {
        return [
            'slug' => 'password_reset',
            'subject' => 'Jelszo visszaallitasi kerelem - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelszo visszaallitas</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #DC2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; background-color: #DC2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .warning { background-color: #FEF3C7; border: 1px solid #F59E0B; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Jelszo visszaallitas</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <p>Jelszo visszaallitasi kerelmet kaptunk az On fiokjara vonatkozoan.</p>

        <p>Az uj jelszo megadasahoz kattintson az alabbi gombra:</p>

        <p style="text-align: center;">
            <a href="{{password_reset_url}}" class="button">Jelszo visszaallitasa</a>
        </p>

        <p>Ha a gomb nem mukodik, masolja be az alabbi linket a bongeszojebe:</p>
        <p style="word-break: break-all; font-size: 12px;">{{password_reset_url}}</p>

        <div class="warning">
            <strong>Fontos:</strong> Ez a link 60 percig ervenyes. Ha nem On kerte a jelszo visszaallitast, hagyja figyelmen kivul ezt az uzenetet, es jelszava valtozatlan marad.
        </div>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

Jelszo visszaallitasi kerelmet kaptunk az On fiokjara vonatkozoan.

Az uj jelszo megadasahoz latogasson el az alabbi linkre:
{{password_reset_url}}

FONTOS: Ez a link 60 percig ervenyes. Ha nem On kerte a jelszo visszaallitast, hagyja figyelmen kivul ezt az uzenetet, es jelszava valtozatlan marad.

Udvozlettel,
{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * User deleted template.
     */
    private function userDeleted(): array
    {
        return [
            'slug' => 'user_deleted',
            'subject' => 'Fiok torlese - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiok torolve</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #6B7280; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .info-box { background-color: #EFF6FF; border: 1px solid #3B82F6; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Fiok torolve</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <p>Ertesitjuk, hogy fiokja a {{company_name}} rendszereben torolve lett.</p>

        <p>A torlest vegezte: <strong>{{deleted_by}}</strong></p>

        <div class="info-box">
            <p>Ha ugy gondolja, hogy ez tevedesen tortent, vagy kerdesei vannak, kerem, vegye fel velunk a kapcsolatot az alabbi e-mail cimen:</p>
            <p><strong>{{support_email}}</strong></p>
        </div>

        <p>Koszonjuk, hogy velunk volt!</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

Ertesitjuk, hogy fiokja a {{company_name}} rendszereben torolve lett.

A torlest vegezte: {{deleted_by}}

Ha ugy gondolja, hogy ez tevedesen tortent, vagy kerdesei vannak, kerem, vegye fel velunk a kapcsolatot az alabbi e-mail cimen:
{{support_email}}

Koszonjuk, hogy velunk volt!

Udvozlettel,
{{company_name}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Booking confirmation template.
     */
    private function bookingConfirmation(): array
    {
        return [
            'slug' => 'booking_confirmation',
            'subject' => 'Foglalasi visszaigazolas: {{class.title}} - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foglalasi visszaigazolas</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #059669; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .details { background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .details-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
        .details-label { font-weight: bold; width: 120px; }
        .button { display: inline-block; background-color: #DC2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Foglalasa visszaigazolva!</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <p>Orommel ertesitjuk, hogy foglalasa sikeresen megtortent.</p>

        <div class="details">
            <h3 style="margin-top: 0;">Foglalasi reszletek</h3>
            <div class="details-row">
                <span class="details-label">Ora:</span>
                <span>{{class.title}}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Idopont:</span>
                <span>{{class.starts_at}}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Terem:</span>
                <span>{{class.room}}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Edzo:</span>
                <span>{{trainer.name}}</span>
            </div>
            <div class="details-row" style="border-bottom: none;">
                <span class="details-label">Statusz:</span>
                <span>{{status}}</span>
            </div>
        </div>

        <p>Ha le szeretne mondani a foglalast, kattintson az alabbi gombra:</p>

        <p style="text-align: center;">
            <a href="{{cancel_url}}" class="button">Foglatas lemondasa</a>
        </p>

        <p><em>Kerem, vegye figyelembe, hogy a lemondas legkesobb 24 oraval az ora kezdete elott lehetseges.</em></p>

        <p>Varjuk szeretettel!</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

Orommel ertesitjuk, hogy foglalasa sikeresen megtortent.

FOGLALASI RESZLETEK:
- Ora: {{class.title}}
- Idopont: {{class.starts_at}}
- Terem: {{class.room}}
- Edzo: {{trainer.name}}
- Statusz: {{status}}

Ha le szeretne mondani a foglalast, latogasson el az alabbi linkre:
{{cancel_url}}

Kerem, vegye figyelembe, hogy a lemondas legkesobb 24 oraval az ora kezdete elott lehetseges.

Varjuk szeretettel!

{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Booking cancellation template.
     */
    private function bookingCancellation(): array
    {
        return [
            'slug' => 'booking_cancellation',
            'subject' => 'Foglatas lemondva: {{class.title}} - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foglatas lemondva</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #DC2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .details { background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Foglatas lemondva</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <p>Ertesitjuk, hogy az alabbi foglalasa sikeresen lemondva lett:</p>

        <div class="details">
            <p><strong>Ora:</strong> {{class.title}}</p>
            <p><strong>Eredeti idopont:</strong> {{class.starts_at}}</p>
            <p><strong>Terem:</strong> {{class.room}}</p>
            <p><strong>Edzo:</strong> {{trainer.name}}</p>
        </div>

        <p>Ha kerdesei vannak, vagy ujra szeretne foglalni, kerem, latogasson el weboldalunkra vagy vegye fel velunk a kapcsolatot.</p>

        <p>Varjuk vissza hamarosan!</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

Ertesitjuk, hogy az alabbi foglalasa sikeresen lemondva lett:

- Ora: {{class.title}}
- Eredeti idopont: {{class.starts_at}}
- Terem: {{class.room}}
- Edzo: {{trainer.name}}

Ha kerdesei vannak, vagy ujra szeretne foglalni, kerem, latogasson el weboldalunkra vagy vegye fel velunk a kapcsolatot.

Varjuk vissza hamarosan!

{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Waitlist promotion template.
     */
    private function waitlistPromotion(): array
    {
        return [
            'slug' => 'waitlist_promotion',
            'subject' => 'Jo hir! Helye felszabadult: {{class.title}} - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Varolista - Hely felszabadult</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #F59E0B; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .highlight { background-color: #FEF3C7; border: 2px solid #F59E0B; padding: 20px; border-radius: 6px; margin: 20px 0; text-align: center; }
        .details { background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .button { display: inline-block; background-color: #059669; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Jo hir!</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <div class="highlight">
            <h2 style="margin: 0; color: #B45309;">Hely szabadult fel!</h2>
            <p style="margin: 10px 0 0;">On automatikusan be lett sorolva az orara!</p>
        </div>

        <p>A varolistan volt es most automatikusan bekerult az alabbi orara:</p>

        <div class="details">
            <p><strong>Ora:</strong> {{class.title}}</p>
            <p><strong>Idopont:</strong> {{class.starts_at}}</p>
            <p><strong>Terem:</strong> {{class.room}}</p>
            <p><strong>Edzo:</strong> {{trainer.name}}</p>
        </div>

        <p>Ha megsem tudna reszt venni, kerem, mondja le a foglalast mihamarabb, hogy mas is eljuthasson az orara!</p>

        <p style="text-align: center;">
            <a href="{{cancel_url}}" class="button" style="background-color: #DC2626;">Foglatas lemondasa</a>
        </p>

        <p>Varjuk szeretettel!</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

JO HIR! Hely szabadult fel es On automatikusan be lett sorolva az orara!

A varolistan volt es most automatikusan bekerult az alabbi orara:

- Ora: {{class.title}}
- Idopont: {{class.starts_at}}
- Terem: {{class.room}}
- Edzo: {{trainer.name}}

Ha megsem tudna reszt venni, kerem, mondja le a foglalast mihamarabb, hogy mas is eljuthasson az orara!

Lemondas: {{cancel_url}}

Varjuk szeretettel!

{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Class reminder template.
     */
    private function classReminder(): array
    {
        return [
            'slug' => 'class_reminder',
            'subject' => 'Emlekezeto: {{class.title}} holnap! - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ora emlekezeto</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #3B82F6; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .reminder-box { background-color: #DBEAFE; border: 2px solid #3B82F6; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .details { background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .checklist { background-color: #F0FDF4; padding: 15px 20px; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Ora emlekezeto</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <div class="reminder-box">
            <h2 style="margin: 0; color: #1D4ED8;">Ne feledje! Holnap oraja lesz!</h2>
        </div>

        <div class="details">
            <h3 style="margin-top: 0;">Az ora reszletei:</h3>
            <p><strong>Ora neve:</strong> {{class.title}}</p>
            <p><strong>Idopont:</strong> {{class.starts_at}}</p>
            <p><strong>Terem:</strong> {{class.room}}</p>
            <p><strong>Edzo:</strong> {{trainer.name}}</p>
        </div>

        <div class="checklist">
            <h4 style="margin-top: 0;">Ne felejtse el:</h4>
            <ul style="margin-bottom: 0;">
                <li>Sportruha es valtoruha</li>
                <li>Torolkozo</li>
                <li>Vizesuveg</li>
                <li>Erkezzen 10 perccel korabban</li>
            </ul>
        </div>

        <p>Ha megsem tud reszt venni, kerem, mondja le a foglalast mihamarabb!</p>

        <p>Varjuk szeretettel!</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

NE FELEDJE! Holnap oraja lesz!

AZ ORA RESZLETEI:
- Ora neve: {{class.title}}
- Idopont: {{class.starts_at}}
- Terem: {{class.room}}
- Edzo: {{trainer.name}}

NE FELEJTSE EL:
- Sportruha es valtoruha
- Torolkozo
- Vizesuveg
- Erkezzen 10 perccel korabban

Ha megsem tud reszt venni, kerem, mondja le a foglalast mihamarabb!

Varjuk szeretettel!

{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Class modified template.
     */
    private function classModified(): array
    {
        return [
            'slug' => 'class_modified',
            'subject' => 'Ora valtozas: {{class.title}} - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ora valtozas ertesites</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #F59E0B; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .warning-box { background-color: #FEF3C7; border: 2px solid #F59E0B; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .comparison { display: table; width: 100%; margin: 20px 0; }
        .comparison-row { display: table-row; }
        .comparison-cell { display: table-cell; padding: 10px; background-color: white; border: 1px solid #e5e7eb; }
        .old-value { background-color: #FEE2E2; text-decoration: line-through; }
        .new-value { background-color: #D1FAE5; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Ora valtozas</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <div class="warning-box">
            <h2 style="margin: 0; color: #B45309;">Figyelem! Az ora adatai megvaltoztak!</h2>
        </div>

        <p>A(z) <strong>{{class.title}}</strong> ora, amelyre jelentkezett, modositasra kerult.</p>

        <h3>Valtozasok:</h3>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <th style="text-align: left; padding: 10px; background-color: #f3f4f6; border: 1px solid #e5e7eb;">Adat</th>
                <th style="text-align: left; padding: 10px; background-color: #f3f4f6; border: 1px solid #e5e7eb;">Korabbi</th>
                <th style="text-align: left; padding: 10px; background-color: #f3f4f6; border: 1px solid #e5e7eb;">Uj</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #e5e7eb;"><strong>Idopont</strong></td>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background-color: #FEE2E2;">{{old.starts_at}}</td>
                <td style="padding: 10px; border: 1px solid #e5e7eb; background-color: #D1FAE5;">{{new.starts_at}}</td>
            </tr>
        </table>

        <p><strong>Modositotta:</strong> {{modified_by}}</p>

        <h3>Aktualis adatok:</h3>
        <ul>
            <li><strong>Ora:</strong> {{class.title}}</li>
            <li><strong>Uj idopont:</strong> {{class.starts_at}}</li>
            <li><strong>Terem:</strong> {{class.room}}</li>
            <li><strong>Edzo:</strong> {{trainer.name}}</li>
            <li><strong>Foglalasi statusz:</strong> {{status}}</li>
        </ul>

        <p>Ha az uj idopont nem megfelelo Onnek, lehetosege van lemondani a foglalast.</p>

        <p>Kerdeseivel forduljon hozzank bizalommal!</p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

FIGYELEM! Az ora adatai megvaltoztak!

A(z) {{class.title}} ora, amelyre jelentkezett, modositasra kerult.

VALTOZASOK:
- Korabbi idopont: {{old.starts_at}}
- Uj idopont: {{new.starts_at}}

Modositotta: {{modified_by}}

AKTUALIS ADATOK:
- Ora: {{class.title}}
- Uj idopont: {{class.starts_at}}
- Terem: {{class.room}}
- Edzo: {{trainer.name}}
- Foglalasi statusz: {{status}}

Ha az uj idopont nem megfelelo Onnek, lehetosege van lemondani a foglalast.

Kerdeseivel forduljon hozzank bizalommal!

{{company_name}}
E-mail: {{support_email}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }

    /**
     * Class deleted template.
     */
    private function classDeleted(): array
    {
        return [
            'slug' => 'class_deleted',
            'subject' => 'Ora torolve: {{class.title}} - {{company_name}}',
            'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ora torolve</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #DC2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .alert-box { background-color: #FEE2E2; border: 2px solid #DC2626; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .details { background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .info-box { background-color: #EFF6FF; border: 1px solid #3B82F6; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Ora torolve</h1>
    </div>
    <div class="content">
        <p>Kedves {{user.name}}!</p>

        <div class="alert-box">
            <h2 style="margin: 0; color: #B91C1C;">Sajnaljuk, az ora torolve lett!</h2>
        </div>

        <p>Ertesitjuk, hogy az alabbi ora, amelyre jelentkezett, sajnos torolve lett:</p>

        <div class="details">
            <p><strong>Ora neve:</strong> {{class.title}}</p>
            <p><strong>Eredeti idopont:</strong> {{class.starts_at}}</p>
            <p><strong>Terem:</strong> {{class.room}}</p>
            <p><strong>Edzo:</strong> {{trainer.name}}</p>
            <p><strong>Az On foglalasi statusza:</strong> {{status}}</p>
        </div>

        <p><strong>Torlo szemely:</strong> {{deleted_by}}</p>

        <div class="info-box">
            <p style="margin: 0;">Ha barki berlet vagy kreditalapú foglalasal rendelkezett, az automatikusan visszairjuk a fiokjaba.</p>
        </div>

        <p>Elnezest kerunk az esetleges kellemetlensegert! Kerem, nezzzen korul tobbi orink kozott, es foglaljon egy masikat.</p>

        <p>Kerdeseivel forduljon hozzank bizalommal:</p>
        <p><strong>{{support_email}}</strong></p>
    </div>
    <div class="footer">
        <p>{{company_name}}<br>
        E-mail: {{support_email}}</p>
        <p>&copy; {{current_year}} {{company_name}}. Minden jog fenntartva.</p>
    </div>
</body>
</html>
HTML,
            'fallback_body' => <<<'TEXT'
Kedves {{user.name}}!

SAJNALJUK, AZ ORA TOROLVE LETT!

Ertesitjuk, hogy az alabbi ora, amelyre jelentkezett, sajnos torolve lett:

- Ora neve: {{class.title}}
- Eredeti idopont: {{class.starts_at}}
- Terem: {{class.room}}
- Edzo: {{trainer.name}}
- Az On foglalasi statusza: {{status}}

Torlo szemely: {{deleted_by}}

Ha barki berlet vagy kreditalapú foglalasal rendelkezett, az automatikusan visszairjuk a fiokjaba.

Elnezest kerunk az esetleges kellemetlensegert! Kerem, nezzen korul tobbi orunk kozott, es foglaljon egy masikat.

Kerdeseivel forduljon hozzank bizalommal:
{{support_email}}

{{company_name}}
TEXT,
            'is_active' => true,
            'version' => 1,
        ];
    }
}
