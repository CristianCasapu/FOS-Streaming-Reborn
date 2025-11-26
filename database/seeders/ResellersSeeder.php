<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Resellers Seeder
 *
 * Seeds demo reseller accounts for testing
 * Phase 1: Foundation - Multi-tenant System
 */
class ResellersSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding Resellers...');
        }

        $appEnv = env('APP_ENV', 'production');

        if ($appEnv === 'production') {
            if ($this->command) {
                $this->command->warn('Skipping reseller seeding in production environment');
            }
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        $resellers = [
            [
                'username' => 'demo_reseller',
                'email' => 'reseller@demo.local',
                'password' => password_hash('reseller123', PASSWORD_BCRYPT),
                'company_name' => 'Demo IPTV Services',
                'contact_name' => 'John Reseller',
                'phone' => '+1-555-0100',
                'address' => '123 Demo Street, Demo City',
                'commission_rate' => 15.00,
                'max_subscribers' => 100,
                'max_packages' => 5,
                'status' => 'active',
                'is_active' => 1,
                'credit_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'api_enabled' => 1,
                'api_rate_limit' => 1000,
                'notes' => 'Demo reseller account for testing',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'username' => 'premium_reseller',
                'email' => 'premium@demo.local',
                'password' => password_hash('premium123', PASSWORD_BCRYPT),
                'company_name' => 'Premium Streaming Ltd',
                'contact_name' => 'Jane Premium',
                'phone' => '+1-555-0200',
                'address' => '456 Premium Ave, Premium City',
                'commission_rate' => 20.00,
                'max_subscribers' => 500,
                'max_packages' => 10,
                'status' => 'active',
                'is_active' => 1,
                'credit_balance' => 1500.00,
                'pending_balance' => 250.00,
                'total_earned' => 5000.00,
                'total_withdrawn' => 3500.00,
                'api_enabled' => 1,
                'api_rate_limit' => 5000,
                'custom_domain' => 'premium-iptv.demo',
                'brand_name' => 'Premium IPTV',
                'theme_settings' => json_encode([
                    'primary_color' => '#1E40AF',
                    'secondary_color' => '#10B981',
                    'logo_url' => 'https://example.com/logo.png',
                ]),
                'notes' => 'Premium tier reseller with white-label features',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'username' => 'starter_reseller',
                'email' => 'starter@demo.local',
                'password' => password_hash('starter123', PASSWORD_BCRYPT),
                'company_name' => 'Starter IPTV',
                'contact_name' => 'Bob Starter',
                'phone' => '+1-555-0300',
                'commission_rate' => 10.00,
                'max_subscribers' => 50,
                'max_packages' => 3,
                'status' => 'active',
                'is_active' => 1,
                'credit_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'api_enabled' => 0,
                'api_rate_limit' => 100,
                'notes' => 'Starter tier reseller account',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($resellers as $resellerData) {
            // Check if already exists
            $exists = Capsule::table('resellers')->where('username', $resellerData['username'])->exists();

            if ($exists) {
                if ($this->command) {
                    $this->command->warn("  Reseller {$resellerData['username']} already exists");
                }
                continue;
            }

            Capsule::table('resellers')->insert($resellerData);

            if ($this->command) {
                $this->command->info("  Created reseller: {$resellerData['username']}");
            }
        }

        if ($this->command) {
            $this->command->info('');
            $this->command->info('  Demo Reseller Credentials:');
            $this->command->info('    Demo:     demo_reseller / reseller123');
            $this->command->info('    Premium:  premium_reseller / premium123');
            $this->command->info('    Starter:  starter_reseller / starter123');
            $this->command->info('');
            $this->command->info('Resellers seeded successfully!');
        }
    }
}
