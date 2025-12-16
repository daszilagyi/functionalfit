<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "Testing SMTP connection...\n";
echo "Host: " . config('mail.mailers.smtp.host') . "\n";
echo "Port: " . config('mail.mailers.smtp.port') . "\n";
echo "Encryption: " . config('mail.mailers.smtp.encryption') . "\n";
echo "Username: " . config('mail.mailers.smtp.username') . "\n\n";

try {
    Mail::raw('This is a test email from FunctionalFit Calendar.', function ($message) {
        $message->to('daszilagyi@gmail.com')
                ->subject('SMTP Test Email');
    });
    echo "✓ Email sent successfully!\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
}
