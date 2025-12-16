<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Seed initial users, staff profiles, and clients.
     *
     * Creates:
     * - 1 Admin user
     * - 3 Sample staff members (one per site)
     * - 3 Sample clients
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Admin User
        $adminId = DB::table('users')->insertGetId([
            'role' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin@functionalfit.hu',
            'phone' => '+36201234567',
            'password' => Hash::make('password'), // Change in production
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->command->info("Created admin user: admin@functionalfit.hu");

        // Sample Staff Members
        $staffUsers = [
            [
                'name' => 'János Kovács',
                'email' => 'janos.kovacs@functionalfit.hu',
                'phone' => '+36201234568',
                'site' => 'SASAD',
                'bio' => 'Certified personal trainer with 10+ years of experience in strength training and rehabilitation.',
                'skills' => 'Personal Training, Rehabilitation, Sports Massage',
            ],
            [
                'name' => 'Éva Nagy',
                'email' => 'eva.nagy@functionalfit.hu',
                'phone' => '+36201234569',
                'site' => 'TB',
                'bio' => 'Yoga instructor and group fitness specialist.',
                'skills' => 'Yoga, Pilates, Group Classes',
            ],
            [
                'name' => 'Péter Tóth',
                'email' => 'peter.toth@functionalfit.hu',
                'phone' => '+36201234570',
                'site' => 'ÚJBUDA',
                'bio' => 'CrossFit coach and nutrition consultant.',
                'skills' => 'CrossFit, Functional Training, Nutrition',
            ],
        ];

        foreach ($staffUsers as $staffData) {
            $userId = DB::table('users')->insertGetId([
                'role' => 'staff',
                'name' => $staffData['name'],
                'email' => $staffData['email'],
                'phone' => $staffData['phone'],
                'password' => Hash::make('password'), // Change in production
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('staff_profiles')->insert([
                'user_id' => $userId,
                'bio' => $staffData['bio'],
                'skills' => $staffData['skills'],
                'default_site' => $staffData['site'],
                'visibility' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->command->info("Created staff: {$staffData['name']} at {$staffData['site']}");
        }

        // Sample Clients
        $clientUsers = [
            [
                'name' => 'Anna Szabó',
                'email' => 'anna.szabo@example.com',
                'phone' => '+36301234567',
            ],
            [
                'name' => 'Béla Kiss',
                'email' => 'bela.kiss@example.com',
                'phone' => '+36301234568',
            ],
            [
                'name' => 'Csilla Varga',
                'email' => 'csilla.varga@example.com',
                'phone' => '+36301234569',
            ],
        ];

        foreach ($clientUsers as $clientData) {
            $userId = DB::table('users')->insertGetId([
                'role' => 'client',
                'name' => $clientData['name'],
                'email' => $clientData['email'],
                'phone' => $clientData['phone'],
                'password' => Hash::make('password'), // Change in production
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('clients')->insert([
                'user_id' => $userId,
                'full_name' => $clientData['name'],
                'date_of_joining' => $now->subDays(rand(30, 365))->toDateString(),
                'gdpr_consent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->command->info("Created client: {$clientData['name']}");
        }
    }
}
