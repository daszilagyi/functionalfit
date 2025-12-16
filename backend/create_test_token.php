<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find or create admin user
$user = App\Models\User::firstOrCreate(
    ['email' => 'admin@functionalfit.hu'],
    [
        'name' => 'Admin User',
        'password' => bcrypt('password123'),
        'role' => 'admin',
    ]
);

// Delete old tokens
$user->tokens()->delete();

// Create new token
$token = $user->createToken('dev-token');

echo "Token created successfully!\n";
echo "User: {$user->name} ({$user->email})\n";
echo "Token: {$token->plainTextToken}\n";
echo "\nAdd this to localStorage in the browser:\n";
echo "localStorage.setItem('auth_token', '{$token->plainTextToken}');\n";
