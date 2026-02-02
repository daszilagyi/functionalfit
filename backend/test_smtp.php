<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "Testing SMTP connection...\n";
echo "MAIL_MAILER: " . env('MAIL_MAILER') . "\n";
echo "MAIL_HOST: " . env('MAIL_HOST') . "\n";
echo "MAIL_PORT: " . env('MAIL_PORT') . "\n";
echo "MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS') . "\n";

try {
    Mail::raw('Ez egy SMTP teszt email a FunctionalFit rendszerből.', function($message) {
        $message->to('daszilagyi@gmail.com')
                ->subject('SMTP Teszt - ' . date('Y-m-d H:i:s'));
    });
    echo "\nSUCCESS: Email sent successfully!\n";
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
}
